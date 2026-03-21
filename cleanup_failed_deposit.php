<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Core/Database.php';

$db = \App\Core\Database::getInstance();
$db->exec("DELETE FROM deposits WHERE id = 3");
echo "Deleted deposit 3\n";
