<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();
$stmt = $db->prepare("SELECT * FROM deposits WHERE id = 4");
$stmt->execute();
$deposit = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt2 = $db->prepare("SELECT * FROM deposit_splits WHERE deposit_id = 4");
$stmt2->execute();
$splits = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['deposit' => $deposit, 'splits' => $splits], JSON_PRETTY_PRINT);
