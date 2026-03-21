<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Core/Database.php';

$db = \App\Core\Database::getInstance();

$output = "";
function out($label, $data) {
    global $output;
    $output .= "--- " . $label . " ---\n";
    $output .= json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
}

$playerId = 4;

// 1. Vijay Info
$stmt = $db->prepare("SELECT * FROM players WHERE id = ?");
$stmt->execute([$playerId]);
out("Vijay Player Info", $stmt->fetch(PDO::FETCH_ASSOC));

// 2. Vijay State in Bookie
$stmt = $db->prepare("SELECT * FROM bookie_players WHERE player_id = ?");
$stmt->execute([$playerId]);
out("Vijay Bookie State", $stmt->fetch(PDO::FETCH_ASSOC));

// 3. Vijay Deposits
$stmt = $db->prepare("SELECT * FROM deposits WHERE player_id = ? ORDER BY id DESC");
$stmt->execute([$playerId]);
$deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
out("Vijay Deposits", $deposits);

if (!empty($deposits)) {
    $latestDepId = $deposits[0]['id'];
    // 4. Splits for latest deposit
    $stmt = $db->prepare("SELECT * FROM deposit_splits WHERE deposit_id = ?");
    $stmt->execute([$latestDepId]);
    out("Latest Deposit Splits", $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// 5. Withdrawal Queue
$stmt = $db->query("SELECT w.*, p.first_name, p.username FROM withdrawals w JOIN players p ON w.player_id = p.id WHERE w.status IN ('queued', 'partially_paid') ORDER BY w.created_at ASC");
out("Current Withdrawal Queue", $stmt->fetchAll(PDO::FETCH_ASSOC));

file_put_contents(__DIR__ . '/debug_output.json', $output);
echo "Output written to debug_output.json\n";
