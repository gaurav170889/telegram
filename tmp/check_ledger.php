<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();
$res = $db->query("SELECT * FROM wallet_ledger ORDER BY id DESC LIMIT 10")->fetchAll(\PDO::FETCH_ASSOC);
$out = "Ledger:\n" . json_encode($res, JSON_PRETTY_PRINT) . "\n\n";

$res2 = $db->query("SELECT * FROM deposits ORDER BY id DESC LIMIT 2")->fetchAll(\PDO::FETCH_ASSOC);
$out .= "Deposits:\n" . json_encode($res2, JSON_PRETTY_PRINT) . "\n\n";

$res3 = $db->query("SELECT * FROM deposit_splits ORDER BY id DESC LIMIT 2")->fetchAll(\PDO::FETCH_ASSOC);
$out .= "Splits:\n" . json_encode($res3, JSON_PRETTY_PRINT) . "\n\n";

file_put_contents(__DIR__ . '/ledger_out.txt', $out);

