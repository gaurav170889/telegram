<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();
$stmt = $db->query("DESCRIBE withdrawals");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
