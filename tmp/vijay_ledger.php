<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
$db = App\Core\Database::getInstance();

// Find Vijay
$stmt = $db->query("SELECT p.id, p.username, w.id as wid, w.current_balance, w.available_balance FROM players p JOIN bookie_players bp ON bp.player_id = p.id JOIN wallets w ON w.bookie_player_id = bp.id WHERE p.username LIKE '%vijay%' OR p.first_name LIKE '%vijay%'");
$vijay = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vijay) die("Vijay not found!\n");
echo "Vijay: Player #{$vijay['id']} Wallet #{$vijay['wid']} Balance: {$vijay['current_balance']} Available: {$vijay['available_balance']}\n\n";

// His ledger
echo "=== VIJAY LEDGER ===\n";
$stmt = $db->prepare("SELECT id, entry_type, direction, amount, opening_balance, closing_balance, reference_type, reference_id, description, created_at FROM wallet_ledger WHERE wallet_id = ? ORDER BY id ASC");
$stmt->execute([$vijay['wid']]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "#{$r['id']} [{$r['entry_type']}] {$r['direction']} \${$r['amount']} open:{$r['opening_balance']} close:{$r['closing_balance']} ref:{$r['reference_type']}#{$r['reference_id']}\n";
    if ($r['description']) echo "   desc: {$r['description']}\n";
}

// His deposits
echo "\n=== VIJAY DEPOSITS ===\n";
$stmt = $db->prepare("SELECT d.id, d.amount, d.status, d.deposit_type FROM deposits d WHERE d.player_id = ?");
$stmt->execute([$vijay['id']]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
    echo "Deposit #{$d['id']} \${$d['amount']} [{$d['status']}] [{$d['deposit_type']}]\n";
    // splits for this deposit
    $s2 = $db->prepare("SELECT ds.id, ds.sequence_no, ds.amount, ds.status, p.first_name as recip FROM deposit_splits ds LEFT JOIN players p ON p.id = ds.recipient_player_id WHERE ds.deposit_id = ?");
    $s2->execute([$d['id']]);
    foreach ($s2->fetchAll(PDO::FETCH_ASSOC) as $sp) {
        echo "  Split #{$sp['id']} seq#{$sp['sequence_no']} =>{$sp['recip']} \${$sp['amount']} [{$sp['status']}]\n";
    }
}
