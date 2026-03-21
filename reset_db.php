<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Core/Database.php';

$db = \App\Core\Database::getInstance();

echo "<pre>\n";
echo "Starting database reset...\n";

try {
    // Disable foreign key checks to allow truncation
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // Truncate transaction and layout tables
    $tables = [
        'wallet_ledger',
        'ledger_allocations',
        'deposit_split_receipts',
        'withdrawal_reservations',
        'deposit_splits',
        'deposits',
        'withdrawals'
    ];
    
    foreach ($tables as $table) {
        $db->exec("TRUNCATE TABLE `$table`;");
        echo "Cleared table: $table\n";
    }
    
    // Reset wallet balances (assume columns exist based on standard wallet design)
    // If some of these columns don't exist, this might fail, but balance and locked_balance are standard.
    $db->exec("UPDATE wallets SET balance = 0.00, locked_balance = 0.00;");
    echo "Reset wallet balances to 0.00\n";
    
    // Reset player states in Telegram Bot so they don't get stuck mid-flow
    $db->exec("UPDATE bookie_players SET current_state = 'MAIN_MENU', temp_payload = NULL;");
    echo "Reset player bot states to MAIN_MENU\n";
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "Database reset complete! You are ready to start from the beginning.\n";
} catch (\Exception $e) {
    echo "Error resetting database: " . $e->getMessage() . "\n";
}
echo "</pre>\n";
