<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();
// Vijay is player_id 4, bookie_id 1
$stmt2 = $db->prepare("SELECT w.id, w.current_balance FROM wallets w JOIN bookie_players bp ON w.bookie_player_id = bp.id WHERE bp.bookie_id = 1 AND bp.player_id = 4");
$stmt2->execute();
$wallet = $stmt2->fetch(\PDO::FETCH_ASSOC);

echo "Vijay Wallet Balance: " . $wallet['current_balance'] . "\n\n";

$res = $db->query("SELECT * FROM wallet_ledger WHERE wallet_id = {$wallet['id']} ORDER BY id DESC LIMIT 10")->fetchAll(\PDO::FETCH_ASSOC);
echo "Ledger:\n" . json_encode($res, JSON_PRETTY_PRINT) . "\n\n";
