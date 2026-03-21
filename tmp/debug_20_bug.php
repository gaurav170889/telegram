<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();

// We are simulating what happens when "Approve Deposit" is clicked
// And it executes `QueueService::allocateDeposit` on a Deposit containing a disputed split.

// 1. First, check if there is an active deposit in 'approved' state, with a recently disputed split.
$stmt = $db->query("SELECT * FROM deposit_splits ORDER BY id DESC LIMIT 5");
$splits = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Recent Splits:\n";
print_r($splits);

// Let's check ledger entries for Wallet 3 (Vijay)
$stmt2 = $db->query("SELECT * FROM wallet_ledger WHERE wallet_id = 3 ORDER BY id DESC LIMIT 5");
$ledger = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Check current balances
$stmt3 = $db->query("SELECT * FROM wallets WHERE id = 3");
$wallet = $stmt3->fetch(PDO::FETCH_ASSOC);

$out = [
    'splits' => $splits,
    'ledger' => $ledger,
    'wallet' => $wallet
];

file_put_contents(__DIR__ . '/vijay_20_debug.json', json_encode($out, JSON_PRETTY_PRINT));
echo "Debug data written to vijay_20_debug.json\n";
