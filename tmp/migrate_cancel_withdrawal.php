<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

$db = App\Core\Database::getInstance();

// 1. Add cancel_pending to withdrawals.status ENUM
try {
    $db->exec("ALTER TABLE withdrawals MODIFY COLUMN status ENUM('queued','partially_paid','partial_dispute','completed','cancelled','cancel_pending') NOT NULL DEFAULT 'queued'");
    echo "OK: withdrawals.status ENUM updated\n";
} catch (Exception $e) {
    echo "ERROR status ENUM: " . $e->getMessage() . "\n";
}

// 2. Add cancel_confirmation_expires_at column
try {
    $db->exec("ALTER TABLE withdrawals ADD COLUMN cancel_confirmation_expires_at DATETIME NULL AFTER status");
    echo "OK: cancel_confirmation_expires_at column added\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
        echo "SKIP: cancel_confirmation_expires_at already exists\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

// Verify
$col = $db->query("SHOW COLUMNS FROM withdrawals LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
echo "\nVerified status ENUM: " . $col['Type'] . "\n";
$col2 = $db->query("SHOW COLUMNS FROM withdrawals LIKE 'cancel_confirmation_expires_at'")->fetch(PDO::FETCH_ASSOC);
echo "Verified column: " . ($col2 ? $col2['Field'] . " " . $col2['Type'] : "NOT FOUND") . "\n";
