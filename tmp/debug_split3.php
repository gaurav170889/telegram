<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
$db = App\Core\Database::getInstance();

// Check what getWalletIdByPlayer would return for split #3
$splitId = 3;
$stmt = $db->prepare("SELECT ds.*, d.player_id, d.bookie_id FROM deposit_splits ds JOIN deposits d ON d.id = ds.deposit_id WHERE ds.id = ?");
$stmt->execute([$splitId]);
$split = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Split #3: deposit_id={$split['deposit_id']} player_id={$split['player_id']} bookie_id={$split['bookie_id']} amount={$split['amount']} status={$split['status']}\n";

// getWalletIdByPlayer($bookieId, $playerId)
$bookie_id = $split['bookie_id'];
$player_id = $split['player_id'];
$stmt2 = $db->prepare("SELECT w.id FROM wallets w JOIN bookie_players bp ON bp.id = w.bookie_player_id WHERE bp.bookie_id = ? AND bp.player_id = ? LIMIT 1");
$stmt2->execute([$bookie_id, $player_id]);
$wid = $stmt2->fetchColumn();
echo "getWalletIdByPlayer($bookie_id, $player_id) => wallet_id=$wid\n";

// What are the ledger entries for split #3 reference?
echo "\nLedger entries referencing split#3:\n";
$stmt3 = $db->prepare("SELECT id, wallet_id, entry_type, direction, amount, description FROM wallet_ledger WHERE reference_type = 'split' AND reference_id = ?");
$stmt3->execute([$splitId]);
foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  Ledger #{$r['id']} wallet#{$r['wallet_id']} {$r['direction']} \${$r['amount']} [{$r['entry_type']}] {$r['description']}\n";
}

// Also check what withdrawal_reservations says for split #3
echo "\nWithdrawal reservation for split#3:\n";
$stmt4 = $db->prepare("SELECT * FROM withdrawal_reservations WHERE deposit_split_id = ?");
$stmt4->execute([$splitId]);
$res = $stmt4->fetch(PDO::FETCH_ASSOC);
if ($res) {
    echo "  wr_id={$res['id']} withdrawal_id={$res['withdrawal_id']} status={$res['status']} reserved_amount={$res['reserved_amount']}\n";
} else {
    echo "  No reservation found - this means $res block was skipped!\n";
}
