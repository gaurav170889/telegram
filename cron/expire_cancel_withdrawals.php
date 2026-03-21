<?php

require_once __DIR__ . '/../app/Config/database.php';

// Simple Autoloader
spl_autoload_register(function ($class) {
    str_replace('\\', DIRECTORY_SEPARATOR, ltrim($class, '\\'));
    $file = __DIR__ . '/../' . str_replace('\\', '/', lcfirst(str_replace('App\\', 'app\\', $class))) . '.php';
    if (file_exists($file)) require_once $file;
});
use App\Core\Database;
use App\Modules\Telegram\Services\NotificationService;

$db = Database::getInstance();

try {
    $db->beginTransaction();

    // Find all expired cancel_pending withdrawals
    $stmt = $db->query("
        SELECT id, bookie_id, player_id, amount_requested 
        FROM withdrawals 
        WHERE status = 'cancel_pending' 
        AND cancel_confirmation_expires_at < NOW()
        FOR UPDATE
    ");
    $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expired)) {
        $db->rollBack();
        echo "No expired pending cancellations found.\n";
        exit;
    }

    $ids = array_column($expired, 'id');
    $in = str_repeat('?,', count($ids) - 1) . '?';

    // Restore them to queued
    $upd = $db->prepare("UPDATE withdrawals SET status = 'queued', cancel_confirmation_expires_at = NULL WHERE id IN ($in)");
    $upd->execute($ids);

    $db->commit();

    // Group by bookie for notifications
    $notifier = new NotificationService();
    foreach ($expired as $w) {
        $msg = "⚠️ <b>Cancellation Expired</b>\n\nPlayer cancelled withdrawal #{$w['id']} (\${$w['amount_requested']}) but did not confirm in time. It has been restored to the queue.";
        try {
            $notifier->notifyBookie($w['bookie_id'], $msg);
            $notifier->notifyPlayer($w['player_id'], "❌ <b>Withdrawal Cancellation Expired</b>\n\nYou did not confirm the cancellation of withdrawal #{$w['id']} within the 10-minute window. It has been restored to the active queue.");
        } catch (\Exception $e) {
            error_log("Failed to notify about expired cancel for WID #{$w['id']}: " . $e->getMessage());
        }
    }

    echo "Processed " . count($expired) . " expired cancellations.\n";

} catch (\Exception $e) {
    $db->rollBack();
    error_log("Cron Error - expire_cancel_withdrawals: " . $e->getMessage());
    echo "Error processing expirations.\n";
}
