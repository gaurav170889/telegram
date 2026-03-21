<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();

// List all players
echo "=== PLAYERS ===\n";
$rows = $db->query("SELECT id, username, first_name FROM players")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "Player #{$r['id']}: {$r['first_name']} (@{$r['username']})\n";
}

// List all wallets
echo "\n=== WALLETS ===\n";
$rows = $db->query("SELECT w.id, w.current_balance, w.available_balance, w.locked_balance, p.first_name, p.username FROM wallets w JOIN bookie_players bp ON bp.id = w.bookie_player_id JOIN players p ON p.id = bp.player_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "Wallet #{$r['id']}: {$r['first_name']} - current:${$r['current_balance']} avail:${$r['available_balance']} locked:${$r['locked_balance']}\n";
}

// List all deposits
echo "\n=== DEPOSITS ===\n";
$rows = $db->query("SELECT d.id, d.amount, d.status, d.deposit_type, p.first_name FROM deposits d JOIN players p ON p.id = d.player_id ORDER BY d.id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "Deposit #{$r['id']}: {$r['first_name']} \${$r['amount']} [{$r['status']}] [{$r['deposit_type']}]\n";
}

// List all splits
echo "\n=== SPLITS ===\n";
$rows = $db->query("SELECT ds.*, p.first_name as recip_name FROM deposit_splits ds LEFT JOIN players p ON p.id = ds.recipient_player_id ORDER BY ds.id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "Split #{$r['id']} (Dep #{$r['deposit_id']}): seq#{$r['sequence_no']} => {$r['recip_name']} \${$r['amount']} [{$r['status']}]\n";
}

// Full ledger for Vijay (need to find his wallet)
echo "\n=== LEDGER (ALL ENTRIES) ===\n";
$rows = $db->query("SELECT l.*, w.id as wallet_id, p.first_name FROM wallet_ledger l JOIN wallets w ON w.id = l.wallet_id JOIN bookie_players bp ON bp.id = w.bookie_player_id JOIN players p ON p.id = bp.player_id ORDER BY l.id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "Ledger #{$r['id']}: {$r['first_name']} W#{$r['wallet_id']} {$r['direction']} \${$r['amount']} [{$r['entry_type']}] open:{$r['opening_balance']} close:{$r['closing_balance']}\n";
}
