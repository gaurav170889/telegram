<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
$db = App\Core\Database::getInstance();

// Vijay is Player #4 Wallet #2 (confirmed from previous run)
$vijayWalletId = 2;
$vijayPlayerId = 4;

// Wallet state
$w = $db->query("SELECT current_balance, available_balance, locked_balance FROM wallets WHERE id = $vijayWalletId")->fetch(PDO::FETCH_ASSOC);
echo "WALLET: current={$w['current_balance']} avail={$w['available_balance']} locked={$w['locked_balance']}\n\n";

// Ledger entries
$stmt = $db->prepare("SELECT id, entry_type, direction, amount, opening_balance, closing_balance, reference_type, reference_id FROM wallet_ledger WHERE wallet_id = ? ORDER BY id ASC");
$stmt->execute([$vijayWalletId]);
$ledger = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "LEDGER ENTRIES:\n";
$total = 0;
foreach ($ledger as $r) {
    $sign = $r['direction'] === 'credit' ? '+' : '-';
    $total += ($r['direction'] === 'credit' ? (float)$r['amount'] : -(float)$r['amount']);
    echo "#{$r['id']} {$sign}\${$r['amount']} [{$r['entry_type']}] ref:{$r['reference_type']}#{$r['reference_id']} => close:{$r['closing_balance']}\n";
}
echo "\nLEDGER SUM: $total\n";

// Deposits by Vijay
echo "\nDEPOSITS:\n";
$deps = $db->prepare("SELECT id, amount, status, deposit_type FROM deposits WHERE player_id = ?");
$deps->execute([$vijayPlayerId]);
foreach ($deps->fetchAll(PDO::FETCH_ASSOC) as $d) {
    echo "  Dep#{$d['id']} \${$d['amount']} status={$d['status']} type={$d['deposit_type']}\n";
    $sp = $db->prepare("SELECT id, sequence_no, amount, status, recipient_player_id FROM deposit_splits WHERE deposit_id = ? ORDER BY sequence_no");
    $sp->execute([$d['id']]);
    foreach ($sp->fetchAll(PDO::FETCH_ASSOC) as $s) {
        echo "    Split#{$s['id']} seq#{$s['sequence_no']} recip_player={$s['recipient_player_id']} \${$s['amount']} status={$s['status']}\n";
    }
}
