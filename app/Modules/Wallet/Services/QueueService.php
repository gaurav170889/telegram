<?php

namespace App\Modules\Wallet\Services;

use App\Core\Database;
use App\Modules\Wallet\Repositories\WalletRepository;
use PDO;

class QueueService {
    private $db;
    private $ledgerService;
    private $walletRepo;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->ledgerService = new LedgerService();
        $this->walletRepo = new WalletRepository();
    }

    /**
     * Generate a split plan for a deposit based on the withdrawal queue.
     * Strategy: Best-fit to minimize depositor splits.
     */
    /**
     * Atomically generate a P2P split plan AND reserve the withdrawals in one transaction.
     * This prevents two concurrent depositors from being assigned the same withdrawal.
     */
    public function generateAndReserveSplits($bookieId, $depositId, $amount, $methodName = null, $excludePlayerId = null) {
        $remainingAmount = (float) $amount;
        $splits = [];
        $sequenceOrder = 1;

        $this->db->beginTransaction();

        try {
            // ── Step 1: Select and lock all eligible withdrawal rows ──────────────
            $query = "
                SELECT w.* 
                FROM withdrawals w
                JOIN payout_methods pm ON w.method_id = pm.id
                WHERE w.bookie_id = ? 
                AND w.status IN ('queued', 'partially_paid') 
                AND w.amount_remaining > 0
            ";
            $params = [$bookieId];

            if ($excludePlayerId) {
                $query .= " AND w.player_id != ?";
                $params[] = $excludePlayerId;
            }

            if ($methodName) {
                $query .= " AND pm.method_name = ?";
                $params[] = $methodName;
            }

            // FOR UPDATE locks rows for the duration of this transaction — any concurrent
            // cancel or deposit assignment will wait until we commit.
            $query .= " ORDER BY w.created_at ASC FOR UPDATE";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $allCandidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $selectedWithdrawals = [];

            // PRIORITY 1: Exact withdrawal amount match
            foreach ($allCandidates as $k => $w) {
                if ((float)$w['amount_remaining'] === $remainingAmount) {
                    $selectedWithdrawals[] = ['withdrawal' => $w, 'amount' => $remainingAmount];
                    $remainingAmount = 0;
                    unset($allCandidates[$k]);
                    break;
                }
            }

            // PRIORITY 2: Single withdrawal that can absorb the full deposit amount
            if ($remainingAmount > 0) {
                $bestAbsorber = null;
                $bestAbsorberKey = null;

                foreach ($allCandidates as $k => $w) {
                    $rem = (float)$w['amount_remaining'];
                    if ($rem >= $remainingAmount) {
                        if ($bestAbsorber === null || $rem < (float)$bestAbsorber['amount_remaining']) {
                            $bestAbsorber = $w;
                            $bestAbsorberKey = $k;
                        }
                    }
                }

                if ($bestAbsorber) {
                    $selectedWithdrawals[] = ['withdrawal' => $bestAbsorber, 'amount' => $remainingAmount];
                    $remainingAmount = 0;
                    unset($allCandidates[$bestAbsorberKey]);
                }
            }

            // PRIORITY 3: Greedy largest-first combination
            if ($remainingAmount > 0) {
                usort($allCandidates, function($a, $b) {
                    return (float)$b['amount_remaining'] <=> (float)$a['amount_remaining'];
                });

                foreach ($allCandidates as $k => $w) {
                    if ($remainingAmount <= 0) break;
                    $allocatedAmount = min($remainingAmount, (float) $w['amount_remaining']);
                    $selectedWithdrawals[] = ['withdrawal' => $w, 'amount' => $allocatedAmount];
                    $remainingAmount -= $allocatedAmount;
                }
            }

            // ── Step 2: Build split data and INSERT reservation rows atomically ───
            foreach ($selectedWithdrawals as $item) {
                $withdrawal = $item['withdrawal'];
                $allocatedAmount = $item['amount'];

                $payoutStmt = $this->db->prepare("
                    SELECT pm.* 
                    FROM payout_methods pm 
                    JOIN withdrawals w ON w.method_id = pm.id
                    WHERE w.id = ?
                ");
                $payoutStmt->execute([$withdrawal['id']]);
                $payoutMethod = $payoutStmt->fetch(PDO::FETCH_ASSOC);

                // Insert split row
                $insSplit = $this->db->prepare("
                    INSERT INTO deposit_splits (
                        deposit_id, sequence_no, recipient_type, 
                        recipient_player_id, recipient_bookie_id, 
                        payment_method, payment_handle, amount, status
                    ) VALUES (?, ?, 'withdraw_player', ?, NULL, ?, ?, ?, 'assigned')
                ");
                $insSplit->execute([
                    $depositId,
                    $sequenceOrder,
                    $withdrawal['player_id'],
                    $payoutMethod ? $payoutMethod['method_name'] : 'System Transfer',
                    $payoutMethod ? $payoutMethod['handle'] : 'Contact Support',
                    $allocatedAmount
                ]);
                $splitId = $this->db->lastInsertId();

                // Insert reservation row
                $this->db->prepare("
                    INSERT INTO withdrawal_reservations (
                        withdrawal_id, deposit_split_id, reserved_amount, status, expires_at
                    ) VALUES (?, ?, ?, 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))
                ")->execute([$withdrawal['id'], $splitId, $allocatedAmount]);

                // Decrement amount_remaining on the withdrawal (prevents double-assignment)
                $this->db->prepare("
                    UPDATE withdrawals 
                    SET amount_remaining = amount_remaining - ?,
                        status = CASE WHEN amount_remaining - ? <= 0 THEN 'partially_paid' ELSE status END
                    WHERE id = ?
                ")->execute([$allocatedAmount, $allocatedAmount, $withdrawal['id']]);

                $splits[] = [
                    'split_id'            => $splitId,
                    'sequence_no'         => $sequenceOrder,
                    'recipient_type'      => 'withdraw_player',
                    'recipient_player_id' => $withdrawal['player_id'],
                    'withdrawal_id'       => $withdrawal['id'],
                    'amount'              => $allocatedAmount,
                    'payment_method'      => $payoutMethod ? $payoutMethod['method_name'] : 'System Transfer',
                    'payment_handle'      => $payoutMethod ? $payoutMethod['handle'] : 'Contact Support',
                ];
                $sequenceOrder++;
            }

            // ── Step 3: Bookie fallback for any remaining amount ─────────────────
            if ($remainingAmount > 0) {
                $gwQuery = "
                    SELECT g.name as type, bg.config_values as details 
                    FROM bookie_gateways bg
                    JOIN gateways g ON bg.gateway_id = g.id
                    WHERE bg.bookie_id = ? AND bg.status = 'active'
                ";
                $gwParams = [$bookieId];
                if ($methodName) {
                    $gwQuery .= " AND g.name = ?";
                    $gwParams[] = $methodName;
                }
                $gwQuery .= " LIMIT 1";

                $bookieMethodStmt = $this->db->prepare($gwQuery);
                $bookieMethodStmt->execute($gwParams);
                $bookieMethod = $bookieMethodStmt->fetch(PDO::FETCH_ASSOC);
                
                $details = $bookieMethod ? json_decode($bookieMethod['details'], true) : [];
                $handle = 'Contact Bookie';
                if (!empty($details)) {
                    $handle = reset($details);
                }

                // Insert bookie split row
                $insSplit = $this->db->prepare("
                    INSERT INTO deposit_splits (
                        deposit_id, sequence_no, recipient_type,
                        recipient_player_id, recipient_bookie_id,
                        payment_method, payment_handle, amount, status
                    ) VALUES (?, ?, 'bookie', NULL, ?, ?, ?, ?, 'assigned')
                ");
                $insSplit->execute([
                    $depositId,
                    $sequenceOrder,
                    $bookieId,
                    $bookieMethod ? $bookieMethod['type'] : ($methodName ?: 'Bookie Direct'),
                    $handle,
                    $remainingAmount
                ]);
                $splitId = $this->db->lastInsertId();

                $splits[] = [
                    'split_id'        => $splitId,
                    'sequence_no'     => $sequenceOrder,
                    'recipient_type'  => 'bookie',
                    'amount'          => $remainingAmount,
                    'payment_method'  => $bookieMethod ? $bookieMethod['type'] : ($methodName ?: 'Bookie Direct'),
                    'payment_handle'  => $handle,
                ];
            }

            $this->db->commit();
            return $splits;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @deprecated Use generateAndReserveSplits() instead.
     * Kept for generateBookieFirstDepositPlan compatibility.
     */
    public function generateSplitPlan($bookieId, $amount, $methodName = null, $excludePlayerId = null) {
        // Thin wrapper — returns plan array only (no reservation INSERT).
        // Only used by generateBookieFirstDepositPlan flow which goes to bookie directly.
        $remainingAmount = (float) $amount;
        $splits = [];
        $sequenceOrder = 1;

        $this->db->beginTransaction();

        try {
            $query = "
                SELECT w.* 
                FROM withdrawals w
                JOIN payout_methods pm ON w.method_id = pm.id
                WHERE w.bookie_id = ? 
                AND w.status IN ('queued', 'partially_paid') 
                AND w.amount_remaining > 0
            ";
            $params = [$bookieId];
            if ($excludePlayerId) { $query .= " AND w.player_id != ?"; $params[] = $excludePlayerId; }
            if ($methodName) { $query .= " AND pm.method_name = ?"; $params[] = $methodName; }
            $query .= " ORDER BY w.created_at ASC FOR UPDATE";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $allCandidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $selectedWithdrawals = [];

            foreach ($allCandidates as $k => $w) {
                if ((float)$w['amount_remaining'] === $remainingAmount) {
                    $selectedWithdrawals[] = ['withdrawal' => $w, 'amount' => $remainingAmount];
                    $remainingAmount = 0;
                    unset($allCandidates[$k]);
                    break;
                }
            }
            if ($remainingAmount > 0) {
                $best = null; $bk = null;
                foreach ($allCandidates as $k => $w) {
                    $rem = (float)$w['amount_remaining'];
                    if ($rem >= $remainingAmount && ($best === null || $rem < (float)$best['amount_remaining'])) { $best = $w; $bk = $k; }
                }
                if ($best) { $selectedWithdrawals[] = ['withdrawal' => $best, 'amount' => $remainingAmount]; $remainingAmount = 0; unset($allCandidates[$bk]); }
            }
            if ($remainingAmount > 0) {
                usort($allCandidates, fn($a,$b) => (float)$b['amount_remaining'] <=> (float)$a['amount_remaining']);
                foreach ($allCandidates as $w) {
                    if ($remainingAmount <= 0) break;
                    $a = min($remainingAmount, (float)$w['amount_remaining']);
                    $selectedWithdrawals[] = ['withdrawal' => $w, 'amount' => $a];
                    $remainingAmount -= $a;
                }
            }

            foreach ($selectedWithdrawals as $item) {
                $w = $item['withdrawal'];
                $pm = $this->db->prepare("SELECT pm.* FROM payout_methods pm JOIN withdrawals w ON w.method_id = pm.id WHERE w.id = ?");
                $pm->execute([$w['id']]);
                $payoutMethod = $pm->fetch(PDO::FETCH_ASSOC);
                $splits[] = [
                    'sequence_no' => $sequenceOrder++,
                    'recipient_type' => 'withdraw_player',
                    'recipient_player_id' => $w['player_id'],
                    'withdrawal_id' => $w['id'],
                    'amount' => $item['amount'],
                    'payment_method' => $payoutMethod ? $payoutMethod['method_name'] : 'System Transfer',
                    'payment_handle' => $payoutMethod ? $payoutMethod['handle'] : 'Contact Support',
                ];
            }

            if ($remainingAmount > 0) {
                $gwQuery = "SELECT g.name as type, bg.config_values as details FROM bookie_gateways bg JOIN gateways g ON bg.gateway_id = g.id WHERE bg.bookie_id = ? AND bg.status = 'active'";
                $gwParams = [$bookieId];
                if ($methodName) { $gwQuery .= " AND g.name = ?"; $gwParams[] = $methodName; }
                $gwQuery .= " LIMIT 1";
                $bm = $this->db->prepare($gwQuery); $bm->execute($gwParams);
                $bookieMethod = $bm->fetch(PDO::FETCH_ASSOC);
                $details = $bookieMethod ? json_decode($bookieMethod['details'], true) : [];
                $handle = !empty($details) ? reset($details) : 'Contact Bookie';
                $splits[] = ['sequence_no' => $sequenceOrder++, 'recipient_type' => 'bookie', 'recipient_bookie_id' => $bookieId, 'amount' => $remainingAmount, 'payment_method' => $bookieMethod ? $bookieMethod['type'] : ($methodName ?: 'Bookie Direct'), 'payment_handle' => $handle];
            }

            $this->db->commit();
            return $splits;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }


    /**
     * Complete bypass of P2P queue for the Bookie's very first deposit.
     */
    public function generateBookieFirstDepositPlan($bookieId, $amount, $methodName) {
        $splits = [];
        
        $stmt = $this->db->prepare("
            SELECT g.name as type, bg.config_values as details 
            FROM bookie_gateways bg
            JOIN gateways g ON bg.gateway_id = g.id
            WHERE bg.bookie_id = ? AND bg.status = 'active' AND g.name = ?
            LIMIT 1
        ");
        $stmt->execute([$bookieId, $methodName]);
        $bookieMethod = $stmt->fetch(\PDO::FETCH_ASSOC);

        $details = $bookieMethod ? json_decode($bookieMethod['details'], true) : [];
        $handle = 'Contact Bookie';
        if (!empty($details)) {
            $handle = reset($details);
        }

        $splits[] = [
            'sequence_no' => 1,
            'recipient_type' => 'bookie',
            'recipient_bookie_id' => $bookieId,
            'amount' => $amount,
            'payment_method' => $methodName,
            'payment_handle' => $handle
        ];
        
        return $splits;
    }

    /**
     * Finalize a deposit after bookie approval.
     */
    public function allocateDeposit($depositId) {
        $stmt = $this->db->prepare("SELECT * FROM deposits WHERE id = ?");
        $stmt->execute([$depositId]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$deposit) return false;

        if ($deposit['deposit_type'] !== 'split_p2p') {
            // DIRECT DEPOSIT: Credit the player's wallet immediately
            $walletRepo = new \App\Modules\Wallet\Repositories\WalletRepository();
            $wallet = $walletRepo->getWalletByPlayerId($deposit['player_id'], $deposit['bookie_id']);
            
            if ($wallet) {
                $walletService = new \App\Modules\Wallet\Services\WalletService();
                $walletService->creditDeposit($wallet['id'], $deposit['amount'], $depositId);
            }
        } else {
            // P2P SPLIT DEPOSIT: 
            // 1. Resolve any remaining 'disputed' splits for this deposit
            $stmt = $this->db->prepare("SELECT id FROM deposit_splits WHERE deposit_id = ? AND status = 'disputed'");
            $stmt->execute([$depositId]);
            $disputedSplits = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($disputedSplits as $splitId) {
                // If the bookie manually approves the whole deposit, we assume they are overriding disputes
                $this->resolveSplitDispute($splitId, 'confirm');
            }
            
            // Note: credits to depositor for each split are handled at receipt upload time.
            // This fallback ensures the deposit overall isn't "stuck" if the bookie approves the header.
        }

        return true;
    }

    /**
     * Create reservations for the generated splits.
     */
    public function reserveSplits($depositId, $splits) {
        $this->db->beginTransaction();
        try {
            foreach ($splits as $splitData) {
                // 1. Create split record
                $stmt = $this->db->prepare("
                    INSERT INTO deposit_splits (
                        deposit_id, sequence_no, recipient_type, 
                        recipient_player_id, recipient_bookie_id, 
                        payment_method, payment_handle, amount, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'assigned')
                ");
                $stmt->execute([
                    $depositId, 
                    $splitData['sequence_no'], 
                    $splitData['recipient_type'],
                    $splitData['recipient_player_id'] ?? null,
                    $splitData['recipient_bookie_id'] ?? null,
                    $splitData['payment_method'],
                    $splitData['payment_handle'],
                    $splitData['amount']
                ]);
                $splitId = $this->db->lastInsertId();

                // 2. If it's a withdrawal split, create reservation and update withdrawal tracking
                if ($splitData['recipient_type'] === 'withdraw_player' && isset($splitData['withdrawal_id'])) {
                    $withdrawalId = $splitData['withdrawal_id'];
                    $amount = $splitData['amount'];

                    // Create reservation (expires in 1 hour)
                    $resStmt = $this->db->prepare("
                        INSERT INTO withdrawal_reservations (
                            withdrawal_id, deposit_split_id, reserved_amount, status, expires_at
                        ) VALUES (?, ?, ?, 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))
                    ");
                    $resStmt->execute([$withdrawalId, $splitId, $amount]);

                    // Update withdrawal: move amount from remaining to submitted (reserved)
                    // Note: amount_submitted will be actually confirmed when receipt is uploaded
                    $updWith = $this->db->prepare("
                        UPDATE withdrawals 
                        SET amount_remaining = amount_remaining - ?,
                            status = CASE WHEN amount_remaining - ? <= 0 THEN 'partially_paid' ELSE status END
                        WHERE id = ?
                    ");
                    $updWith->execute([$amount, $amount, $withdrawalId]);
                    
                    // Log reservation in ledger (Future: can add specific ledger entry here if needed)
                }
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Handle receipt upload for a split.
     */
    public function handleReceiptUpload($splitId, $receiptUrl, $playerId) {
        $this->db->beginTransaction();
        try {
            // 1. Get Split
            $stmt = $this->db->prepare("SELECT * FROM deposit_splits WHERE id = ? FOR UPDATE");
            $stmt->execute([$splitId]);
            $split = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$split) throw new \Exception("Split not found.");

            // 2. Insert receipt record
            $insRec = $this->db->prepare("
                INSERT INTO deposit_split_receipts (deposit_split_id, receipt_url, uploaded_by_player_id)
                VALUES (?, ?, ?)
            ");
            $insRec->execute([$splitId, $receiptUrl, $playerId]);

            // 3. Update split status and set dispute window
            // 3. Update split status and set dispute window
            // Dispute window is 10 minutes from now, but status is instantly accepted. 
            // The recipient can still dispute within 10 minutes which will revert this.
            $updSplit = $this->db->prepare("
                UPDATE deposit_splits 
                SET status = 'accepted_no_dispute', 
                    dispute_window_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                    recipient_notified_at = NOW()
                WHERE id = ?
            ");
            $updSplit->execute([$splitId]);

            // Propagate receipt to main deposit for easier dashboard visibility
            $updDep = $this->db->prepare("UPDATE deposits SET receipt_url = ? WHERE id = ? AND (receipt_url IS NULL OR receipt_url = '')");
            $updDep->execute([$receiptUrl, $split['deposit_id']]);

            // 4. Update withdrawal tracking: move from reserved straight to paid because it's instantly accepted
            $resStmt = $this->db->prepare("SELECT withdrawal_id, reserved_amount FROM withdrawal_reservations WHERE deposit_split_id = ?");
            $resStmt->execute([$splitId]);
            $reservation = $resStmt->fetch(PDO::FETCH_ASSOC);

            if ($reservation) {
                // Since it's instantly accepted, we add to amount_confirmed directly
                $updWith = $this->db->prepare("
                    UPDATE withdrawals 
                    SET amount_confirmed = amount_confirmed + ?
                    WHERE id = ?
                ");
                $updWith->execute([$reservation['reserved_amount'], $reservation['withdrawal_id']]);
                
                // Check if fully paid to mark completed
                $checkWith = $this->db->prepare("SELECT amount_requested, amount_confirmed FROM withdrawals WHERE id = ?");
                $checkWith->execute([$reservation['withdrawal_id']]);
                $wdata = $checkWith->fetch(PDO::FETCH_ASSOC);
                if ($wdata && $wdata['amount_confirmed'] >= $wdata['amount_requested']) {
                    $this->db->prepare("UPDATE withdrawals SET status = 'completed' WHERE id = ?")->execute([$reservation['withdrawal_id']]);
                }
            }

            // 5. Instantly credit depositor wallet
            $depositStmt = $this->db->prepare("SELECT deposit_id, amount FROM deposit_splits WHERE id = ?");
            $depositStmt->execute([$splitId]);
            $dInfo = $depositStmt->fetch(PDO::FETCH_ASSOC);
            
            $depStmt = $this->db->prepare("SELECT player_id, bookie_id FROM deposits WHERE id = ?");
            $depStmt->execute([$dInfo['deposit_id']]);
            $depData = $depStmt->fetch(PDO::FETCH_ASSOC);
            $depositorId = $depData['player_id'];
            $bookieId = $depData['bookie_id'];
            
            $walletRepo = new \App\Modules\Wallet\Repositories\WalletRepository();
            $depositorWallet = $walletRepo->getWalletByPlayerId($depositorId, $bookieId);
            
            $walletService = new \App\Modules\Wallet\Services\WalletService();
            $walletService->creditDeposit($depositorWallet['id'], $dInfo['amount'], $dInfo['deposit_id']);

            // 6. Log in ledger for recipient (if it's a withdrawal split)
            if ($reservation) {
                $recipientWalletId = $this->getWalletIdBySplit($splitId);
                if ($recipientWalletId) {
                    $this->ledgerService->createEntry(
                        $recipientWalletId,
                        'split_auto_accepted',
                        'debit',
                        $split['amount'],
                        'split',
                        $splitId,
                        -$split['amount'] // Locked funds are now paid out
                    );
                }
            }

            $this->db->commit();

            // 6. Notify recipient asynchronously
            error_log("QueueService: Splitting payment #{$splitId}. Trying to notify. receiptUrl is: {$receiptUrl}");

            $notifier = new \App\Modules\Telegram\Services\NotificationService();
            $notifier->notifyRecipientSplit($splitId, $receiptUrl);

            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("QueueService: handleReceiptUpload FAILED: " . $e->getMessage() . " \n " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Handle recipient dispute.
     */
    public function disputeSplit($splitId) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM deposit_splits WHERE id = ? FOR UPDATE");
            $stmt->execute([$splitId]);
            $split = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$split) throw new \Exception("Split not found.");

            // 1. Update split status
            $updSplit = $this->db->prepare("
                UPDATE deposit_splits 
                SET status = 'disputed', 
                    recipient_disputed_at = NOW() 
                WHERE id = ?
            ");
            $updSplit->execute([$splitId]);

            // 2. Update withdrawal status
            $resStmt = $this->db->prepare("SELECT withdrawal_id, reserved_amount FROM withdrawal_reservations WHERE deposit_split_id = ?");
            $resStmt->execute([$splitId]);
            $res = $resStmt->fetch(PDO::FETCH_ASSOC);

            if ($res) {
                $updWith = $this->db->prepare("
                    UPDATE withdrawals 
                    SET status = 'partial_dispute', 
                        amount_disputed = amount_disputed + ?,
                        amount_confirmed = amount_confirmed - ?,
                        amount_remaining = amount_remaining + ?
                    WHERE id = ?
                ");
                $updWith->execute([$res['reserved_amount'], $res['reserved_amount'], $res['reserved_amount'], $res['withdrawal_id']]);
            }

            // 4. Ledger Entry: Revert the auto-accept debit from recipient and re-lock
            $this->ledgerService->createEntry(
                $this->getWalletIdBySplit($splitId),
                'split_disputed',
                'credit',
                $split['amount'],
                'split',
                $splitId,
                $split['amount'] // Re-lock the funds since it's now back in dispute
            );

            // 5. Ledger Entry: Debit the depositor since the recipient claims they didn't get it.
            // This fixes the balance issue where depositor kept the credit despite a dispute.
            $depStmt = $this->db->prepare("SELECT player_id, bookie_id FROM deposits WHERE id = ?");
            $depStmt->execute([$split['deposit_id']]);
            $depData = $depStmt->fetch(PDO::FETCH_ASSOC);
            $depositorId = $depData['player_id'];
            $bookieId = $depData['bookie_id'];

            $depositorWalletId = $this->getWalletIdByPlayer($bookieId, $depositorId);
            if ($depositorWalletId) {
                $this->ledgerService->createEntry(
                    $depositorWalletId,
                    'split_dispute_correction',
                    'debit',
                    $split['amount'],
                    'split',
                    $splitId,
                    0,
                    "Debit due to dispute on split #{$splitId}"
                );
            }

            $this->db->commit();

            // Notify Bookie
            $notifier = new \App\Modules\Telegram\Services\NotificationService();
            $notifier->notifyBookie($split['recipient_bookie_id'] ?: 1, "🚨 <b>DISPUTE: Split #{$splitId}</b>\nRecipient claimed they didn't receive payment.");

            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Periodically called to auto-accept splits with expired dispute windows.
     */
    public function autoAcceptExpiredSplits() {
        $stmt = $this->db->prepare("
            SELECT id FROM deposit_splits 
            WHERE status = 'receipt_uploaded' 
            AND dispute_window_expires_at <= NOW()
        ");
        $stmt->execute();
        $splits = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($splits as $splitId) {
            $this->acceptSplit($splitId);
        }
    }

    public function acceptSplit($splitId) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM deposit_splits WHERE id = ? FOR UPDATE");
            $stmt->execute([$splitId]);
            $split = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$split || $split['status'] !== 'receipt_uploaded') {
                $this->db->rollBack();
                return;
            }

            // 1. Update split status
            $updSplit = $this->db->prepare("
                UPDATE deposit_splits SET status = 'accepted_no_dispute', auto_accepted_at = NOW() WHERE id = ?
            ");
            $updSplit->execute([$splitId]);

            // 2. Update withdrawal: move from submitted to confirmed
            $resStmt = $this->db->prepare("SELECT withdrawal_id, reserved_amount FROM withdrawal_reservations WHERE deposit_split_id = ?");
            $resStmt->execute([$splitId]);
            $res = $resStmt->fetch(PDO::FETCH_ASSOC);

            if ($res) {
                $updWith = $this->db->prepare("
                    UPDATE withdrawals 
                    SET amount_confirmed = amount_confirmed + ?,
                        status = CASE WHEN amount_confirmed + ? >= amount_requested THEN 'completed' ELSE 'partially_paid' END
                    WHERE id = ?
                ");
                $updWith->execute([$res['reserved_amount'], $res['reserved_amount'], $res['withdrawal_id']]);
                
                // Finalize reservation
                $updRes = $this->db->prepare("UPDATE withdrawal_reservations SET status = 'completed' WHERE deposit_split_id = ?");
                $updRes->execute([$splitId]);
                
                // Record allocation
                $insAlloc = $this->db->prepare("
                    INSERT INTO ledger_allocations (deposit_id, deposit_split_id, withdrawal_id, amount, allocation_type)
                    VALUES (?, ?, ?, ?, 'queue_settlement')
                ");
                $insAlloc->execute([$split['deposit_id'], $splitId, $res['withdrawal_id'], $split['amount']]);
            } else {
                // Bookie remainder split
                $insAlloc = $this->db->prepare("
                    INSERT INTO ledger_allocations (deposit_id, deposit_split_id, withdrawal_id, amount, allocation_type)
                    VALUES (?, ?, 0, ?, 'bookie_remainder')
                ");
                $insAlloc->execute([$split['deposit_id'], $splitId, $split['amount']]);
            }

            // 3. Ledger: Update balances
            $this->ledgerService->createEntry(
                $this->getWalletIdBySplit($splitId),
                'split_auto_accepted',
                'debit',
                $split['amount'],
                'withdrawal',
                $res['withdrawal_id'] ?? 0
            );

            // 4. Check if whole deposit is finalized
            $this->checkDepositFinalization($split['deposit_id']);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function checkDepositFinalization($depositId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM deposit_splits WHERE deposit_id = ? AND status NOT IN ('accepted_no_dispute', 'resolved_confirmed')");
        $stmt->execute([$depositId]);
        if ($stmt->fetchColumn() == 0) {
            // All splits accepted!
            $updDep = $this->db->prepare("UPDATE deposits SET status = 'approved' WHERE id = ?");
            $updDep->execute([$depositId]);
        }
    }

    public function manualClearWithdrawal($withdrawalId, $amount, $note = null) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM withdrawals WHERE id = ? FOR UPDATE");
            $stmt->execute([$withdrawalId]);
            $w = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$w) {
                throw new \Exception("Withdrawal ID $withdrawalId not found in DB.");
            }
            if ((float)$w['amount_remaining'] < $amount) {
                throw new \Exception("Insufficient amount remaining. Have: " . $w['amount_remaining'] . " Need: $amount");
            }

            // 1. Update Withdrawal tracking
            $stmt = $this->db->prepare("
                UPDATE withdrawals 
                SET amount_confirmed = amount_confirmed + ?, 
                    amount_remaining = amount_remaining - ?,
                    amount_disputed = GREATEST(0, amount_disputed - ?),
                    status = CASE WHEN amount_remaining - ? <= 0 THEN 'completed' ELSE 'partially_paid' END
                WHERE id = ?
            ");
            $stmt->execute([$amount, $amount, $amount, $amount, $withdrawalId]);

            // 2. Record Ledger Allocation
            $stmt = $this->db->prepare("
                INSERT INTO ledger_allocations (withdrawal_id, amount, allocation_type) 
                VALUES (?, ?, 'manual_clear')
            ");
            $stmt->execute([$withdrawalId, $amount]);

            // 3. Update Wallet Balance via Ledger
            $bpStmt = $this->db->prepare("SELECT id FROM bookie_players WHERE bookie_id = ? AND player_id = ?");
            $bpStmt->execute([$w['bookie_id'], $w['player_id']]);
            $bpId = $bpStmt->fetchColumn();
            
            $walletStmt = $this->db->prepare("SELECT id FROM wallets WHERE bookie_player_id = ?");
            $walletStmt->execute([$bpId]);
            $walletId = $walletStmt->fetchColumn();
            
            if ($walletId) {
                // Determine if we need to reduce locked balance. 
                // If the player had $100 locked, and we manually pay $100, we should unlock $100.
                $lockedChange = -$amount;

                $this->ledgerService->createEntry(
                    $walletId,
                    'withdrawal_manual_clear',
                    'debit',
                    $amount,
                    'withdrawal',
                    $withdrawalId,
                    $lockedChange,
                    $note
                );
            }

            // 4. Resolve any linked deposit disputes
            // Find all splits linked to this withdrawal
            $splitStmt = $this->db->prepare("
                SELECT ds.id, ds.deposit_id 
                FROM deposit_splits ds
                JOIN withdrawal_reservations wr ON wr.deposit_split_id = ds.id
                WHERE wr.withdrawal_id = ? AND ds.status = 'disputed'
            ");
            $splitStmt->execute([$withdrawalId]);
            $linkedSplits = $splitStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($linkedSplits as $ls) {
                // Resolve the split
                $updSplit = $this->db->prepare("UPDATE deposit_splits SET status = 'resolved_confirmed' WHERE id = ?");
                $updSplit->execute([$ls['id']]);

                // Update reservation status
                $updRes = $this->db->prepare("UPDATE withdrawal_reservations SET status = 'completed' WHERE deposit_split_id = ?");
                $updRes->execute([$ls['id']]);

                // Check parent deposit. If all splits are now resolved/accepted, move it back to submitted
                $depCheckStmt = $this->db->prepare("
                    SELECT COUNT(*) as disputed_count 
                    FROM deposit_splits 
                    WHERE deposit_id = ? AND status = 'disputed'
                ");
                $depCheckStmt->execute([$ls['deposit_id']]);
                $disputedCount = $depCheckStmt->fetchColumn();

                if ($disputedCount == 0) {
                    $updDep = $this->db->prepare("UPDATE deposits SET status = 'receipts_submitted' WHERE id = ?");
                    $updDep->execute([$ls['deposit_id']]);
                }
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Manual Clear Error: " . $e->getMessage());
            return false;
        }
    }

    public function resolveSplitDispute($splitId, $action) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM deposit_splits WHERE id = ? FOR UPDATE");
            $stmt->execute([$splitId]);
            $split = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$split) {
                throw new \Exception("Split not found.");
            }

            if ($split['status'] !== 'disputed') {
                throw new \Exception("Split not in disputed state.");
            }

            if ($action === 'confirm') {
                // BOOKIE CONFIRMS RECEIPT IS VALID

                // 1. Re-credit depositor (since we debited them when dispute started)
                $depStmt = $this->db->prepare("SELECT player_id, bookie_id FROM deposits WHERE id = ?");
                $depStmt->execute([$split['deposit_id']]);
                $depData = $depStmt->fetch(PDO::FETCH_ASSOC);
                
                $depositorWalletId = $this->getWalletIdByPlayer($depData['bookie_id'], $depData['player_id']);
                if ($depositorWalletId) {
                    $this->ledgerService->createEntry(
                        $depositorWalletId,
                        'split_dispute_correction',
                        'credit',
                        $split['amount'],
                        'split',
                        $splitId,
                        0,
                        "Re-credit after dispute resolution (Confirm) Split #{$splitId}"
                    );
                }

                // 2. Update split status AFTER credits are posted
                $updSplit = $this->db->prepare("UPDATE deposit_splits SET status = 'resolved_confirmed', recipient_disputed_at = NOW() WHERE id = ?");
                $updSplit->execute([$splitId]);

                // 3. Debit recipient and unlock funds (Actually finalize the payout)
                $resStmt = $this->db->prepare("SELECT withdrawal_id, reserved_amount FROM withdrawal_reservations WHERE deposit_split_id = ?");
                $resStmt->execute([$splitId]);
                $res = $resStmt->fetch(PDO::FETCH_ASSOC);

                if ($res) {
                    // Finalize reservation
                    $this->db->prepare("UPDATE withdrawal_reservations SET status = 'completed' WHERE deposit_split_id = ?")->execute([$splitId]);

                    $recipientWalletId = $this->getWalletIdBySplit($splitId);
                    if ($recipientWalletId) {
                        $this->ledgerService->createEntry(
                            $recipientWalletId,
                            'split_dispute_correction',
                            'debit',
                            $split['amount'],
                            'split',
                            $splitId,
                            -$split['amount'] // UNLOCK funds and finalize debit
                        );
                    }

                    // Update withdrawal: restore amounts
                    $this->db->prepare("
                        UPDATE withdrawals 
                        SET status = CASE WHEN amount_remaining <= 0 THEN 'completed' ELSE 'partially_paid' END,
                            amount_disputed = GREATEST(0, amount_disputed - ?),
                            amount_confirmed = amount_confirmed + ?,
                            amount_remaining = GREATEST(0, amount_remaining - ?)
                        WHERE id = ?
                    ")->execute([$res['reserved_amount'], $res['reserved_amount'], $res['reserved_amount'], $res['withdrawal_id']]);
                }

                // Notify Players
                $notifier = new \App\Modules\Telegram\Services\NotificationService();
                $notifier->notifyPlayer($depData['player_id'], "✅ <b>Dispute Resolved!</b>\nYour payment for split #{$splitId} was confirmed by the bookie.");

            } elseif ($action === 'reject') {
                // BOOKIE REJECTS RECEIPT (Receipt was fake/invalid)
                // 1. Update split status
                $updSplit = $this->db->prepare("UPDATE deposit_splits SET status = 'resolved_rejected', recipient_disputed_at = NOW() WHERE id = ?");
                $updSplit->execute([$splitId]);

                // 2. Depositor stays debited (they didn't pay). No action needed as we already debited them.

                // 3. Return recipient's locked funds to available
                $resStmt = $this->db->prepare("SELECT withdrawal_id, reserved_amount FROM withdrawal_reservations WHERE deposit_split_id = ?");
                $resStmt->execute([$splitId]);
                $res = $resStmt->fetch(PDO::FETCH_ASSOC);

                if ($res) {
                    // Update withdrawal: return amount to remaining since this split failed
                    $this->db->prepare("
                        UPDATE withdrawals 
                        SET amount_disputed = GREATEST(0, amount_disputed - ?),
                            amount_remaining = amount_remaining + ?
                        WHERE id = ?
                    ")->execute([$res['reserved_amount'], $res['reserved_amount'], $res['withdrawal_id']]);

                    // Cancel reservation
                    $this->db->prepare("UPDATE withdrawal_reservations SET status = 'cancelled' WHERE deposit_split_id = ?")->execute([$splitId]);

                    $recipientWalletId = $this->getWalletIdBySplit($splitId);
                    if ($recipientWalletId) {
                        $this->ledgerService->createEntry(
                            $recipientWalletId,
                            'split_dispute_correction',
                            'credit',
                            0, // No new funds
                            'split',
                            $splitId,
                            -$split['amount'] // Just unlock the funds back to available
                        );
                    }
                }

                // Notify Players
                $notifier = new \App\Modules\Telegram\Services\NotificationService();
                $depStmt = $this->db->prepare("SELECT player_id FROM deposits WHERE id = ?");
                $depStmt->execute([$split['deposit_id']]);
                $depositorId = $depStmt->fetchColumn();
                $notifier->notifyPlayer($depositorId, "❌ <b>Payment Rejected</b>\nThe bookie rejected your receipt for split #{$splitId}. Please provide a valid receipt.");
            }

            // check if whole deposit can be moved back to under_review or approved
            $this->checkDepositFinalization($split['deposit_id']);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Resolve Dispute Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function getWalletIdByPlayer($bookieId, $playerId) {
        $stmt = $this->db->prepare("
            SELECT w.id 
            FROM wallets w 
            JOIN bookie_players bp ON w.bookie_player_id = bp.id 
            WHERE bp.bookie_id = ? AND bp.player_id = ?
        ");
        $stmt->execute([$bookieId, $playerId]);
        return $stmt->fetchColumn();
    }

    private function getWalletIdBySplit($splitId) {
        $stmt = $this->db->prepare("
            SELECT w.id 
            FROM wallets w 
            JOIN bookie_players bp ON bp.id = w.bookie_player_id
            JOIN withdrawals wd ON wd.player_id = bp.player_id AND wd.bookie_id = bp.bookie_id
            WHERE wd.id = (SELECT withdrawal_id FROM withdrawal_reservations WHERE deposit_split_id = ? LIMIT 1)
        ");
        $stmt->execute([$splitId]);
        return $stmt->fetchColumn();
    }

    /**
     * Request cancellation of a withdrawal with row-level locking.
     */
    public function requestCancelWithdrawal($withdrawalId) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM withdrawals WHERE id = ? FOR UPDATE");
            $stmt->execute([$withdrawalId]);
            $withdrawal = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$withdrawal) {
                $this->db->rollBack();
                return ["blocked" => true, "reason" => "not_found"];
            }

            if ($withdrawal['status'] !== 'queued') {
                $this->db->rollBack();
                return ["blocked" => true, "reason" => "not_queued"];
            }

            // Check if there are active reservations
            $resStmt = $this->db->prepare("SELECT COUNT(*) FROM withdrawal_reservations WHERE withdrawal_id = ? AND status = 'active'");
            $resStmt->execute([$withdrawalId]);
            if ($resStmt->fetchColumn() > 0) {
                $this->db->rollBack();
                return ["blocked" => true, "reason" => "reserved"];
            }

            // Mark as pending cancel
            $upd = $this->db->prepare("
                UPDATE withdrawals 
                SET status = 'cancel_pending', 
                    cancel_confirmation_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
                WHERE id = ?
            ");
            $upd->execute([$withdrawalId]);

            $this->db->commit();
            return ["blocked" => false];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Confirm withdrawal cancellation and refund wallet.
     */
    public function confirmCancelWithdrawal($withdrawalId) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM withdrawals WHERE id = ? FOR UPDATE");
            $stmt->execute([$withdrawalId]);
            $withdrawal = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$withdrawal || $withdrawal['status'] !== 'cancel_pending') {
                $this->db->rollBack();
                return ["success" => false, "expired" => true];
            }

            // Check expiration
            $dbNow = $this->db->query("SELECT NOW()")->fetchColumn();
            if ($withdrawal['cancel_confirmation_expires_at'] < $dbNow) {
                // Expired: restore to queued
                $this->db->prepare("UPDATE withdrawals SET status = 'queued', cancel_confirmation_expires_at = NULL WHERE id = ?")->execute([$withdrawalId]);
                $this->db->commit();
                return ["success" => false, "expired" => true];
            }

            $this->db->prepare("UPDATE withdrawals SET status = 'cancelled', cancel_confirmation_expires_at = NULL WHERE id = ?")->execute([$withdrawalId]);

            // Refund wallet
            $walletRepo = new \App\Modules\Wallet\Repositories\WalletRepository();
            $wallet = $walletRepo->getWalletByPlayerId($withdrawal['player_id'], $withdrawal['bookie_id']);
            
            if ($wallet) {
                $this->db->prepare("UPDATE wallets SET available_balance = available_balance + ? WHERE id = ?")->execute([$withdrawal['amount_remaining'], $wallet['id']]);
                
                $this->ledgerService->createEntry(
                    $wallet['id'],
                    'withdrawal_cancelled',
                    'credit',
                    $withdrawal['amount_remaining'],
                    'withdrawal',
                    $withdrawalId,
                    $withdrawal['amount_remaining'],
                    "Refund for cancelled withdrawal #{$withdrawalId}"
                );
            }

            $this->db->commit();
            return ["success" => true, "amount_refunded" => $withdrawal['amount_remaining']];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Enforce cancel expiry lazily.
     */
    public function checkAndExpirePendingCancels($playerId, $bookieId) {
        $stmt = $this->db->prepare("
            SELECT id, amount_requested FROM withdrawals 
            WHERE player_id = ? AND bookie_id = ? 
            AND status = 'cancel_pending' 
            AND cancel_confirmation_expires_at < NOW()
        ");
        $stmt->execute([$playerId, $bookieId]);
        $expired = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($expired)) return;

        $this->db->beginTransaction();
        try {
            $ids = array_column($expired, 'id');
            $in = str_repeat('?,', count($ids) - 1) . '?';
            $upd = $this->db->prepare("UPDATE withdrawals SET status = 'queued', cancel_confirmation_expires_at = NULL WHERE id IN ($in)");
            $upd->execute($ids);
            
            $this->db->commit();

            // Notify bookie
            $notifier = new \App\Modules\Telegram\Services\NotificationService();
            foreach ($expired as $w) {
                $notifier->notifyBookie(
                    $bookieId, 
                    "⚠️ <b>Cancellation Expired</b>\n\nPlayer cancelled withdrawal #{$w['id']} (\${$w['amount_requested']}) but did not confirm in time. It has been restored to the queue."
                );
            }
        } catch (\Exception $e) {
            $this->db->rollBack();
        }
    }
}
