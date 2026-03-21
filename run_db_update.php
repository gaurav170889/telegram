<?php
require_once __DIR__ . '/app/Config/database.php';
$db = \App\Core\Database::getInstance();
$sql = file_get_contents(__DIR__ . '/init_db.sql');
$db->exec($sql);
echo "Phase 1 schemas executed successfully.";
