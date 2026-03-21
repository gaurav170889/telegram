<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();
$bookieId = 1;

$stmt = $db->prepare("
    SELECT DISTINCT d.*, p.first_name, p.username 
    FROM deposits d 
    JOIN players p ON p.id = d.player_id 
    LEFT JOIN deposit_splits ds ON ds.deposit_id = d.id
    WHERE d.bookie_id = ? 
    AND (d.status NOT IN ('approved', 'rejected') OR ds.status = 'disputed')
    ORDER BY d.created_at ASC
");
$stmt->execute([$bookieId]);
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($res);
