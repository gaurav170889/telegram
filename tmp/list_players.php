<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();
$players = $db->query("SELECT id, username, first_name FROM players")->fetchAll(PDO::FETCH_ASSOC);
$mappings = $db->query("SELECT * FROM bookie_players")->fetchAll(PDO::FETCH_ASSOC);
$wallets = $db->query("SELECT * FROM wallets")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'players' => $players,
    'mappings' => $mappings,
    'wallets' => $wallets
], JSON_PRETTY_PRINT);
