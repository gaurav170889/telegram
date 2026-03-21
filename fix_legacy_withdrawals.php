<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Core/Database.php';

$db = \App\Core\Database::getInstance();

echo "Fixing legacy withdrawals...\n";
$stmt = $db->query("
    UPDATE withdrawals 
    SET amount_requested = amount, 
        amount_remaining = amount 
    WHERE amount_requested = 0 
    AND status IN ('queued', 'partially_paid')
");
echo "Updated " . $stmt->rowCount() . " rows.\n";
