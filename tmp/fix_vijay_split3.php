<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Modules/Wallet/Repositories/LedgerRepository.php';
require_once dirname(__DIR__) . '/app/Modules/Wallet/Repositories/WalletRepository.php';
require_once dirname(__DIR__) . '/app/Modules/Wallet/Services/LedgerService.php';

use App\Modules\Wallet\Services\LedgerService;

$db = App\Core\Database::getInstance();
$ledgerService = new LedgerService();

// Vijay is Wallet #2
$vijayWalletId = 2;
$splitId = 3;
$amount = 50.00;

// Check wallet current state
$w = $db->query("SELECT current_balance, available_balance FROM wallets WHERE id = $vijayWalletId")->fetch(PDO::FETCH_ASSOC);
echo "Vijay before: current={$w['current_balance']} avail={$w['available_balance']}\n";

// Apply the missing re-credit
$result = $ledgerService->createEntry(
    $vijayWalletId,
    'split_dispute_correction',
    'credit',
    $amount,
    'split',
    $splitId,
    0,
    "Manual correction: Missing re-credit after dispute resolution (Confirm) Split #$splitId"
);

if ($result) {
    echo "SUCCESS: Ledger entry #$result created.\n";
    $w2 = $db->query("SELECT current_balance, available_balance FROM wallets WHERE id = $vijayWalletId")->fetch(PDO::FETCH_ASSOC);
    echo "Vijay after:  current={$w2['current_balance']} avail={$w2['available_balance']}\n";
} else {
    echo "FAILED: Could not create ledger entry.\n";
}
