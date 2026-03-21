<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();
$dep = $db->query("SELECT * FROM deposits WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$splits = $db->query("SELECT * FROM deposit_splits WHERE deposit_id = 1")->fetchAll(PDO::FETCH_ASSOC);

echo "Deposit:\n";
print_r($dep);
echo "Splits:\n";
print_r($splits);
