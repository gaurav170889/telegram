<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();
$stmt = $db->query("SELECT id, deposit_id, status FROM deposit_splits WHERE status = 'disputed'");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($res, JSON_PRETTY_PRINT);
