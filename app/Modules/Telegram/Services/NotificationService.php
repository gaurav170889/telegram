<?php

namespace App\Modules\Telegram\Services;

use App\Core\Database;
use App\Modules\Telegram\Services\TelegramService;
use App\Modules\Bookie\Services\BookieService;

class NotificationService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Send a notification to a specific player's Telegram chat
     */
    public function notifyPlayer($playerId, $message, $photoFileId = null, $inlineKeyboard = []) {
        $stmt = $this->db->prepare("
            SELECT p.telegram_id, bp.bookie_id 
            FROM players p 
            JOIN bookie_players bp ON bp.player_id = p.id
            WHERE p.id = ? 
            LIMIT 1
        ");
        $stmt->execute([$playerId]);
        $playerLink = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$playerLink || !$playerLink['telegram_id']) {
            return false;
        }

        $bookieService = new BookieService();
        $bookie = $bookieService->getBookieById($playerLink['bookie_id']);

        if ($bookie && $bookie->telegramBotToken) {
            $telegramService = new TelegramService($bookie->telegramBotToken);
            
            if ($photoFileId) {
                $options = ['parse_mode' => 'HTML'];
                if (!empty($inlineKeyboard)) {
                    $options['inline_keyboard'] = $inlineKeyboard;
                }
                $telegramService->sendPhoto($playerLink['telegram_id'], $photoFileId, $message, $options);
            } else {
                $replyMarkup = null;
                if (!empty($inlineKeyboard)) {
                    $replyMarkup = ['inline_keyboard' => $inlineKeyboard];
                }
                $telegramService->sendMessage($playerLink['telegram_id'], $message, $replyMarkup);
            }
            return true;
        }

        return false;
    }

    /**
     * Send a notification to the Bookie's Telegram chat
     */
    public function notifyBookie($bookieId, $message, $photoFileId = null, $inlineKeyboard = []) {
        $bookieService = new BookieService();
        $bookie = $bookieService->getBookieById($bookieId);

        if ($bookie && $bookie->telegramBotToken && $bookie->telegramId) {
            $telegramService = new TelegramService($bookie->telegramBotToken);
            
            if ($photoFileId) {
                $options = ['parse_mode' => 'HTML'];
                if (!empty($inlineKeyboard)) {
                    $options['inline_keyboard'] = $inlineKeyboard;
                }
                $telegramService->sendPhoto($bookie->telegramId, $photoFileId, $message, $options);
            } else {
                $replyMarkup = null;
                if (!empty($inlineKeyboard)) {
                    $replyMarkup = ['inline_keyboard' => $inlineKeyboard];
                }
                $telegramService->sendMessage($bookie->telegramId, $message, $replyMarkup);
            }
            return true;
        }

        return false;
    }

    /**
     * Specialized notification for split payment recipients
     */
    public function notifyRecipientSplit($splitId, $receiptFileId) {
        $stmt = $this->db->prepare("
            SELECT ds.*, w.amount_requested, w.amount_submitted, w.amount_confirmed, w.amount_remaining, w.id as withdrawal_id,
                   p.telegram_id, d.bookie_id, dp.first_name as depositor_first_name, dp.username as depositor_username
            FROM deposit_splits ds
            LEFT JOIN withdrawals w ON w.id = (SELECT withdrawal_id FROM withdrawal_reservations WHERE deposit_split_id = ds.id LIMIT 1)
            LEFT JOIN players p ON p.id = ds.recipient_player_id
            JOIN deposits d ON d.id = ds.deposit_id
            JOIN players dp ON dp.id = d.player_id
            WHERE ds.id = ?
        ");
        $stmt->execute([$splitId]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$data) return false;

        $amount = number_format($data['amount'], 2);
        $depositorName = $data['depositor_first_name'] . ($data['depositor_username'] ? " (@{$data['depositor_username']})" : "");

        if ($data['recipient_type'] === 'withdraw_player') {
            $total = number_format($data['amount_requested'], 2);
            $paidSoFar = number_format($data['amount_confirmed'], 2);
            $remaining = number_format($data['amount_remaining'], 2);

            $msg = "<b>💰 Withdrawal Credited</b>\n\n";
            $msg .= "Player <b>{$depositorName}</b> has sent <b>\${$amount}</b> toward your withdrawal request.\n";
            $msg .= "This amount has been <b>deposited into your account</b>.\n\n";
            $msg .= "This payment: <b>\${$amount}</b>\n";
            $msg .= "Total requested: <b>\${$total}</b>\n";
            $msg .= "Paid so far: <b>\${$paidSoFar}</b> of \${$total}\n";
            $msg .= "Remaining: <b>\${$remaining}</b>\n\n";
            $msg .= "Payment method: {$data['payment_method']}\n";
            $msg .= "Reference: WD-{$data['withdrawal_id']} / PART-{$data['id']}\n\n";
            $msg .= "<i>Please wait at least 10 minutes before pressing 'Didn't Receive' as notifications and transfers can sometimes take a few minutes to process.</i>\n\n";
            $msg .= "Receipt attached below.";

            $keyboard = [
                [
                    ['text' => '🚨 Didn\'t Receive', 'callback_data' => 'DISPUTE_SPLIT_' . $splitId],
                    ['text' => 'Back to Menu', 'callback_data' => 'MAIN_MENU']
                ]
            ];

            return $this->notifyPlayer($data['recipient_player_id'], $msg, $receiptFileId, $keyboard);
        } else if ($data['recipient_type'] === 'bookie') {
            $msg = "<b>💰 Deposit Credited</b>\n\n";
            $msg .= "Player <b>{$depositorName}</b> has sent <b>\${$amount}</b> directly to you.\n";
            $msg .= "This amount has been <b>deposited into your account</b>.\n\n";
            $msg .= "Payment method: {$data['payment_method']}\n";
            $msg .= "Reference: PART-{$data['id']}\n\n";
            $msg .= "<i>Please wait at least 10 minutes before pressing 'Didn't Receive' as notifications and transfers can sometimes take a few minutes to process.</i>\n\n";
            $msg .= "Receipt attached below.";

            $keyboard = [
                [
                    ['text' => '🚨 Didn\'t Receive', 'callback_data' => 'DISPUTE_SPLIT_' . $splitId],
                    ['text' => 'Back to Menu', 'callback_data' => 'MAIN_MENU']
                ]
            ];

            return $this->notifyBookie($data['bookie_id'], $msg, $receiptFileId, $keyboard);
        }

        return false;
    }
}
