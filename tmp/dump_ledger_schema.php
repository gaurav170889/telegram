<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();
$res = $db->query("SHOW CREATE TABLE wallet_ledger")->fetch(PDO::FETCH_ASSOC);
file_put_contents(__DIR__ . '/ledger_schema.txt', "Wallet Ledger Schema:\n" . $res['Create Table'] . "\n");
