<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();

// Split ID 2 is the disputed one.
$stmt = $db->query("SELECT * FROM deposit_splits WHERE id = 2");
$split = $stmt->fetch(\PDO::FETCH_ASSOC);

if ($split && $split['status'] === 'disputed') {
    $depStmt = $db->prepare("SELECT player_id, bookie_id FROM deposits WHERE id = ?");
    $depStmt->execute([$split['deposit_id']]);
    $depData = $depStmt->fetch(\PDO::FETCH_ASSOC);
    $depositorId = $depData['player_id'];
    $bookieId = $depData['bookie_id'];

    $stmt2 = $db->prepare("SELECT w.id, w.current_balance FROM wallets w JOIN bookie_players bp ON w.bookie_player_id = bp.id WHERE bp.bookie_id = ? AND bp.player_id = ?");
    $stmt2->execute([$bookieId, $depositorId]);
    $wallet = $stmt2->fetch(\PDO::FETCH_ASSOC);
    $walletId = $wallet['id'];

    if ($walletId) {
        $check = $db->prepare("SELECT id FROM wallet_ledger WHERE wallet_id = ? AND entry_type = 'split_dispute_correction' AND reference_id = ?");
        $check->execute([$walletId, $split['id']]);
        if (!$check->fetchColumn()) {
            
            $db->beginTransaction();
            
            // Insert ledger entry manually
            $amount = $split['amount'];
            $opening = $wallet['current_balance'];
            $closing = $wallet['current_balance'] - $amount;
            
            $ins = $db->prepare("INSERT INTO wallet_ledger (wallet_id, entry_type, direction, amount, opening_balance, closing_balance, reference_type, reference_id, description) VALUES (?, 'split_dispute_correction', 'debit', ?, ?, ?, 'split', ?, ?)");
            $ins->execute([$walletId, $amount, $opening, $closing, $split['id'], "Debit due to dispute on split #{$split['id']}"]);
            
            // Update wallet balances
            $upd = $db->prepare("UPDATE wallets SET current_balance = current_balance - ?, available_balance = available_balance - ? WHERE id = ?");
            $upd->execute([$amount, $amount, $walletId]);
            
            $db->commit();
            echo "Successfully retroactively debited wallet $walletId for split 2 dispute.\n";
        } else {
            echo "Already debited.\n";
        }
    }
}

