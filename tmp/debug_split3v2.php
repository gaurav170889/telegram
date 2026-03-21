<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
$db = App\Core\Database::getInstance();

$splitId = 3;
$stmt = $db->prepare("SELECT ds.*, d.player_id as dep_player_id, d.bookie_id FROM deposit_splits ds JOIN deposits d ON d.id = ds.deposit_id WHERE ds.id = ?");
$stmt->execute([$splitId]);
$split = $stmt->fetch(PDO::FETCH_ASSOC);

$lines = [];
$lines[] = "Split #3: deposit_id={$split['deposit_id']} player_id={$split['dep_player_id']} bookie_id={$split['bookie_id']} amount={$split['amount']} status={$split['status']}";

$bookie_id = $split['bookie_id'];
$player_id = $split['dep_player_id'];
$stmt2 = $db->prepare("SELECT w.id FROM wallets w JOIN bookie_players bp ON bp.id = w.bookie_player_id WHERE bp.bookie_id = ? AND bp.player_id = ? LIMIT 1");
$stmt2->execute([$bookie_id, $player_id]);
$wid = $stmt2->fetchColumn();
$lines[] = "getWalletIdByPlayer($bookie_id, $player_id) => wallet_id=" . ($wid ?: "NULL - THIS IS THE BUG!");

$stmt3 = $db->prepare("SELECT id, wallet_id, entry_type, direction, amount, description FROM wallet_ledger WHERE reference_type = 'split' AND reference_id = ?");
$stmt3->execute([$splitId]);
$entries = $stmt3->fetchAll(PDO::FETCH_ASSOC);
$lines[] = "Ledger entries for split#3: " . count($entries);
foreach ($entries as $r) {
    $lines[] = "  #{$r['id']} W#{$r['wallet_id']} {$r['direction']} ${$r['amount']} [{$r['entry_type']}] {$r['description']}";
}

$stmt4 = $db->prepare("SELECT * FROM withdrawal_reservations WHERE deposit_split_id = ?");
$stmt4->execute([$splitId]);
$res = $stmt4->fetch(PDO::FETCH_ASSOC);
if ($res) {
    $lines[] = "WR: wr_id={$res['id']} withdrawal_id={$res['withdrawal_id']} status={$res['status']} reserved={$res['reserved_amount']}";
} else {
    $lines[] = "WR: NO RESERVATION for split#3";
}

$out = implode("\n", $lines) . "\n";
file_put_contents(__DIR__ . '/split3_out.txt', $out);
echo $out;
