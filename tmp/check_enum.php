<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
$db = App\Core\Database::getInstance();

// Check the ENUM allowed values for entry_type
$col = $db->query("SHOW COLUMNS FROM wallet_ledger LIKE 'entry_type'")->fetch(PDO::FETCH_ASSOC);
echo "entry_type ENUM: " . $col['Type'] . "\n\n";

// Check if split_dispute_correction is in the ENUM
if (strpos($col['Type'], 'split_dispute_correction') !== false) {
    echo "OK: split_dispute_correction is a valid ENUM value\n";
} else {
    echo "BUG FOUND: split_dispute_correction is NOT in ENUM! This is why the re-credit silently fails.\n";
}
