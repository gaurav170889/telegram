<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Modules/Wallet/Services/QueueService.php';
require_once __DIR__ . '/../app/Modules/Wallet/Services/LedgerService.php';
require_once __DIR__ . '/../app/Modules/Wallet/Services/WalletService.php';
require_once __DIR__ . '/../app/Modules/Wallet/Repositories/LedgerRepository.php';
require_once __DIR__ . '/../app/Modules/Wallet/Repositories/WalletRepository.php';
require_once __DIR__ . '/../app/Modules/Telegram/Services/NotificationService.php';
require_once __DIR__ . '/../app/Modules/Telegram/Services/TelegramService.php';

use App\Modules\Wallet\Services\QueueService;

echo "[" . date('Y-m-d H:i:s') . "] Starting auto-acceptance of expired splits...\n";

try {
    $queueService = new QueueService();
    $queueService->autoAcceptExpiredSplits();
    echo "[" . date('Y-m-d H:i:s') . "] Finished successfully.\n";
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
}
