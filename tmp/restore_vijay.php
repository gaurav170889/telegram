<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();
$stmt = $db->query("SELECT * FROM deposit_splits WHERE id = 2");
$split = $stmt->fetch(\PDO::FETCH_ASSOC);

echo "Split 2 Status: " . $split['status'] . "\n";

if ($split['status'] === 'resolved_confirmed') {
    // I need to reverse the dispute debit I falsely applied earlier because the dispute is finished
    $stmt2 = $db->query("SELECT w.id, w.current_balance FROM wallets w JOIN bookie_players bp ON w.bookie_player_id = bp.id WHERE bp.bookie_id = 1 AND bp.player_id = 4");
    $wallet = $stmt2->fetch(\PDO::FETCH_ASSOC);
    $walletId = $wallet['id'];
    
    echo "Vijay Wallet Balance: " . $wallet['current_balance'] . "\n";
    
    if ($wallet['current_balance'] == 50.00) {
        $db->beginTransaction();
        $amount = 50.00;
        $opening = $wallet['current_balance'];
        $closing = $wallet['current_balance'] + $amount;
        
        $ins = $db->prepare("INSERT INTO wallet_ledger (wallet_id, entry_type, direction, amount, opening_balance, closing_balance, reference_type, reference_id, description) VALUES (?, 'split_dispute_correction', 'credit', ?, ?, ?, 'split', ?, ?)");
        $ins->execute([$walletId, $amount, $opening, $closing, 2, "Credit back to restore balance after incorrect manual debit"]);
        
        $upd = $db->prepare("UPDATE wallets SET current_balance = current_balance + ?, available_balance = available_balance + ? WHERE id = ?");
        $upd->execute([$amount, $amount, $walletId]);
        
        $db->commit();
        echo "Successfully credited $50 back to Vijay's wallet. New Balance: $closing\n";
    }
}
