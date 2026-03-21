<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Modules/Wallet/Services/QueueService.php';
require_once __DIR__ . '/app/Modules/Wallet/Services/LedgerService.php';
require_once __DIR__ . '/app/Modules/Wallet/Services/WalletService.php';
require_once __DIR__ . '/app/Modules/Wallet/Repositories/LedgerRepository.php';
require_once __DIR__ . '/app/Modules/Wallet/Repositories/WalletRepository.php';
require_once __DIR__ . '/app/Modules/Telegram/Services/NotificationService.php';
require_once __DIR__ . '/app/Modules/Telegram/Services/TelegramService.php';
require_once __DIR__ . '/app/Modules/Bookie/Services/BookieService.php';
require_once __DIR__ . '/app/Modules/Bookie/Repositories/BookieRepository.php';
require_once __DIR__ . '/app/Modules/Telegram/Repositories/PlayerRepository.php';

try {
    $db = \App\Core\Database::getInstance();
    
    // Find the latest split that needs a receipt 
    // Usually the user's latest action
    $stmt = $db->query("SELECT id FROM deposit_splits ORDER BY created_at DESC LIMIT 1");
    $splitId = $stmt->fetchColumn();
    
    if (!$splitId) {
        die("No splits found in DB.\n");
    }

    echo "Attempting to handle receipt upload for Split ID: {$splitId}\n";
    
    $queueService = new \App\Modules\Wallet\Services\QueueService();
    // Assuming player 4 (Vijay48)
    $queueService->handleReceiptUpload($splitId, 'dummy_file_id_test', 4);
    
    echo "SUCCESS!\n";
} catch (\Throwable $e) {
    echo "FATAL ERROR CAUGHT:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
