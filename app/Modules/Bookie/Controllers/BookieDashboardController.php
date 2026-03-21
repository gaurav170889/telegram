<?php

namespace App\Modules\Bookie\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Middleware\OnboardingMiddleware;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Bookie\Services\BookieService;

class BookieDashboardController extends Controller {
    public function index() {
        AuthMiddleware::handle('bookie');
        OnboardingMiddleware::handle();

        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        $bookieService = new BookieService();
        $bookie = $bookieService->getBookieById($user->bookieId);

        $depRepo = new \App\Modules\Wallet\Repositories\DepositRepository();
        $allPending = $depRepo->getPendingByBookie($user->bookieId);
        
        $pendingDeposits = [];
        foreach ($allPending as $d) {
            $d['splits'] = $depRepo->getSplitsByDeposit($d['id']);
            $pendingDeposits[] = $d;
        }

        $withRepo = new \App\Modules\Wallet\Repositories\WithdrawalRepository();
        $queuedWithdrawals = $withRepo->getQueuedByBookie($user->bookieId);

        $ledgerRepo = new \App\Modules\Wallet\Repositories\LedgerRepository();
        $ledgerHistory = $ledgerRepo->getLedgerByBookie($user->bookieId, 500);

        $this->render('Bookie.Views.dashboard', [
            'bookie' => $bookie,
            'pendingDeposits' => $pendingDeposits,
            'queuedWithdrawals' => $queuedWithdrawals,
            'ledgerHistory' => $ledgerHistory
        ]);
    }

    public function handleApproveDeposit() {
        AuthMiddleware::handle('bookie');
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        $depositId = $_POST['id'] ?? null;
        if (!$depositId) {
            $this->redirect('/dashboard?error=invalid_deposit');
            return;
        }

        $depositService = new \App\Modules\Wallet\Services\DepositService();
        $deposit = $depositService->getDeposit($depositId);

        // Security check
        $allowedStatuses = ['pending', 'receipts_submitted', 'under_review_window', 'partially_disputed'];
        if ($deposit && in_array($deposit['status'], $allowedStatuses) && $deposit['bookie_id'] == $user->bookieId) {
            $depositService->updateStatus($depositId, 'approved');
            
            // Trigger Queue Matching Here
            $queueService = new \App\Modules\Wallet\Services\QueueService();
            $queueService->allocateDeposit($depositId);

            // Notify Player
            $notifier = new \App\Modules\Telegram\Services\NotificationService();
            if ($deposit['deposit_type'] === 'split_p2p') {
                $notifier->notifyPlayer($deposit['player_id'], "✅ <b>Deposit Finalized!</b>\nYour \${$deposit['amount']} split deposit is fully approved. (Note: Credits were incrementally added as splits were accepted).");
            } else {
                $notifier->notifyPlayer($deposit['player_id'], "✅ <b>Deposit Approved!</b>\n\${$deposit['amount']} has been added to your queue/wallet.");
            }

            $this->redirect('/dashboard?success=approved');
        } else {
            $this->redirect('/dashboard?error=not_found_or_processed');
        }
        return;
    }

    public function handleRejectDeposit() {
        AuthMiddleware::handle('bookie');
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        $depositId = $_POST['id'] ?? null;
        if (!$depositId) {
            $this->redirect('/dashboard?error=invalid_deposit');
            return;
        }

        $depositService = new \App\Modules\Wallet\Services\DepositService();
        $deposit = $depositService->getDeposit($depositId);

        $allowedStatuses = ['pending', 'receipts_submitted', 'under_review_window', 'partially_disputed'];
        if ($deposit && in_array($deposit['status'], $allowedStatuses) && $deposit['bookie_id'] == $user->bookieId) {
            $depositService->updateStatus($depositId, 'rejected');

            // Notify Player
            $notifier = new \App\Modules\Telegram\Services\NotificationService();
            $notifier->notifyPlayer($deposit['player_id'], "❌ <b>Deposit Rejected</b>\nYour deposit of \${$deposit['amount']} was rejected. Please contact support.");

            $this->redirect('/dashboard?success=rejected');
        } else {
            $this->redirect('/dashboard?error=not_found_or_processed');
        }
        return;
    }

    public function handleManualSettlement() {
        AuthMiddleware::handle('bookie');
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        $withdrawalId = $_POST['id'] ?? null;
        $amount = (float) ($_POST['amount'] ?? 0);
        $note = $_POST['note'] ?? null;

        if (!$withdrawalId || $amount <= 0) {
            $this->redirect('/dashboard?error=invalid_params');
            return;
        }

        $queueService = new \App\Modules\Wallet\Services\QueueService();
        if ($queueService->manualClearWithdrawal($withdrawalId, $amount, $note)) {
            $this->redirect('/dashboard?success=manual_cleared');
        } else {
            $this->redirect('/dashboard?error=manual_clear_failed');
        }
    }

    public function handleResolveDispute() {
        AuthMiddleware::handle('bookie');
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        $splitId = $_POST['split_id'] ?? null;
        $action = $_POST['action'] ?? null; // 'confirm' or 'reject'

        if (!$splitId || !in_array($action, ['confirm', 'reject'])) {
            $this->redirect('/dashboard?error=invalid_params');
            return;
        }

        $queueService = new \App\Modules\Wallet\Services\QueueService();
        try {
            $success = $queueService->resolveSplitDispute($splitId, $action);
            
            if ($success) {
                $this->redirect('/dashboard?success=resolved');
            } else {
                $this->redirect('/dashboard?error=resolution_failed');
            }
        } catch (\Exception $e) {
            $this->redirect('/dashboard?error=' . urlencode($e->getMessage()));
        }
    }

    public function handleReverseDeposit() {
        AuthMiddleware::handle('bookie');
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        $depositId = $_POST['id'] ?? null;
        if (!$depositId) {
            $this->redirect('/dashboard?error=invalid_deposit');
            return;
        }

        $depositService = new \App\Modules\Wallet\Services\DepositService();
        $deposit = $depositService->getDeposit($depositId);

        if ($deposit && $deposit['bookie_id'] == $user->bookieId) {
            $db = \App\Core\Database::getInstance();
            
            if ($deposit['status'] === 'rejected') {
                $db->prepare("UPDATE deposits SET status = 'pending' WHERE id = ?")->execute([$depositId]);
            } else if ($deposit['status'] === 'approved') {
                $db->beginTransaction();
                try {
                    // Remove queue allocations
                    $db->prepare("DELETE FROM ledger_allocations WHERE deposit_id = ?")->execute([$depositId]);
                    // Remove deposit ledger entries
                    $db->prepare("DELETE FROM wallet_ledger WHERE reference_type = 'deposit' AND reference_id = ?")->execute([$depositId]);
                    // Mark deposit back to pending
                    $db->prepare("UPDATE deposits SET status = 'pending' WHERE id = ?")->execute([$depositId]);
                    // Reset withdrawal statuses conservatively
                    $db->prepare("UPDATE withdrawals w LEFT JOIN ledger_allocations la ON w.id = la.withdrawal_id SET w.status = 'pending' WHERE w.status IN ('partially_paid', 'completed') AND la.id IS NULL")->execute();
                    $db->commit();
                } catch (\Exception $e) {
                    $db->rollBack();
                    $this->redirect('/dashboard?error=reversal_failed');
                    return;
                }
            }

            $this->redirect('/dashboard?success=reversed');
            return;
        }

        $this->redirect('/dashboard?error=not_found');
    }

    public function reports() {
        AuthMiddleware::handle('bookie');
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        $bookieService = new BookieService();
        $bookie = $bookieService->getBookieById($user->bookieId);

        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        $ledgerRepo = new \App\Modules\Wallet\Repositories\LedgerRepository();
        $stats = $ledgerRepo->getAggregatedStats($user->bookieId, $startDate, $endDate);
        $playerBreakdown = $ledgerRepo->getPlayerBreakdown($user->bookieId, $startDate, $endDate);
        $methodBreakdown = $ledgerRepo->getMethodBreakdown($user->bookieId, $startDate, $endDate);

        $this->render('Bookie.Views.reports', [
            'bookie' => $bookie,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'stats' => $stats,
            'playerBreakdown' => $playerBreakdown,
            'methodBreakdown' => $methodBreakdown
        ]);
    }

    public function viewReceipt() {
        AuthMiddleware::handle('bookie');
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        
        $depositId = $_GET['id'] ?? null;
        $splitId = $_GET['split'] ?? null;

        if (!$depositId && $splitId) {
            // Find depositId from splitId for security check
            $stmt = \App\Core\Database::getInstance()->prepare("SELECT deposit_id FROM deposit_splits WHERE id = ?");
            $stmt->execute([$splitId]);
            $depositId = $stmt->fetchColumn();
        }

        if (!$depositId) {
            http_response_code(400);
            return;
        }

        $depositService = new \App\Modules\Wallet\Services\DepositService();
        $deposit = $depositService->getDeposit($depositId);

        if (!$deposit || $deposit['bookie_id'] != $user->bookieId) {
            http_response_code(403);
            return;
        }

        $receiptUrl = $deposit['receipt_url'];
        $splitId = $_GET['split'] ?? null;
        if ($splitId) {
            $stmt = \App\Core\Database::getInstance()->prepare("SELECT receipt_url FROM deposit_split_receipts WHERE deposit_split_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$splitId]);
            $fetched = $stmt->fetchColumn();
            if ($fetched) {
                $receiptUrl = $fetched;
            }
        }
        
        error_log("Dashboard viewReceipt Debug: splitId={$splitId}, deposit_id={$depositId}, deposit_receipt_url={$deposit['receipt_url']}, final_receiptUrl={$receiptUrl}");

        if (!$receiptUrl) {
            error_log("Dashboard viewReceipt: receiptUrl is totally empty!");
            http_response_code(404);
            return;
        }

        $bookieService = new BookieService();
        $bookie = $bookieService->getBookieById($user->bookieId);

        $telegramService = new \App\Modules\Telegram\Services\TelegramService($bookie->telegramBotToken);
        
        error_log("Dashboard viewReceipt: calling getFileUrl with " . trim($receiptUrl));
        $fileUrl = $telegramService->getFileUrl(trim($receiptUrl));

        if ($fileUrl) {
            // Fetch image from Telegram and serve it
            // Fetch image from Telegram using cURL for better reliability
            $ch = curl_init($fileUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $img = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($img && $httpCode == 200) {
                while (ob_get_level()) { ob_end_clean(); } // Clear buffer to prevent corrupted images
                header("Content-Type: image/jpeg");
                header("Content-Length: " . strlen($img));
                echo $img;
                exit;
            }
        }
        
        http_response_code(404);
        echo "Image not found on Telegram servers.";
    }
}
