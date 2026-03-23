<?php

namespace App\Modules\Telegram\Controllers;

use App\Modules\Telegram\Services\TelegramService;
use App\Modules\Telegram\Repositories\PlayerRepository;
use App\Modules\Bookie\Services\BookieService;
use App\Modules\Wallet\Services\WalletService;

class TelegramWebhookController {
    public function handle($params) {
        $token = $params['token'] ?? '';
        
        $bookieService = new BookieService();
        $bookie = $bookieService->getBookieByToken($token);

        file_put_contents(__DIR__ . '/../../../../storage/logs/webhook.log', "[" . date('Y-m-d H:i:s') . "] Webhook hit. Token=" . $token . " Bookie=" . ($bookie ? $bookie->id : 'NOT_FOUND') . "\n", FILE_APPEND);

        if (!$bookie) {
            http_response_code(404);
            return;
        }

        $input = file_get_contents('php://input');
        
        file_put_contents(__DIR__ . '/../../../../storage/logs/webhook.log', "[" . date('Y-m-d H:i:s') . "] Input: " . $input . "\n", FILE_APPEND);

        $update = json_decode($input, true);

        if (!$update) {
            file_put_contents(__DIR__ . '/../../../../storage/logs/webhook.log', "[" . date('Y-m-d H:i:s') . "] Error: Invalid JSON.\n", FILE_APPEND);
            return;
        }

        $telegramService = new TelegramService($token);
        $playerRepository = new PlayerRepository();

        try {
            // Routing within the bot
            if (isset($update['message'])) {
                file_put_contents(__DIR__ . '/../../../../storage/logs/webhook.log', "[" . date('Y-m-d H:i:s') . "] Routing to handleMessage\n", FILE_APPEND);
                $this->handleMessage($update['message'], $telegramService, $playerRepository, $bookie->id);
            } elseif (isset($update['callback_query'])) {
                file_put_contents(__DIR__ . '/../../../../storage/logs/webhook.log', "[" . date('Y-m-d H:i:s') . "] Routing to handleCallback\n", FILE_APPEND);
                $this->handleCallback($update['callback_query'], $telegramService, $playerRepository, $bookie->id);
            }
        } catch (\Exception $e) {
            file_put_contents(__DIR__ . '/../../../../storage/logs/webhook.log', "[" . date('Y-m-d H:i:s') . "] Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
        } catch (\Error $e) {
            file_put_contents(__DIR__ . '/../../../../storage/logs/webhook.log', "[" . date('Y-m-d H:i:s') . "] Fatal Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
        }
    }

    private function handleMessage($message, $tg, $playerRepo, $bookieId) {
        $chatId = $message['chat']['id'];
        $from = $message['from'];
        $text = $message['text'] ?? '';

        $playerId = $playerRepo->ensurePlayer($from);
        $bookiePlayerId = $playerRepo->ensureMapping($bookieId, $playerId);

        // Enforce withdrawal cancel expiry
        $queueService = new \App\Modules\Wallet\Services\QueueService();
        $queueService->checkAndExpirePendingCancels($playerId, $bookieId);

        file_put_contents(__DIR__ . '/../../../../storage/logs/webhook.log', "[" . date('Y-m-d H:i:s') . "] Processing handleMessage loop, text: $text\n", FILE_APPEND);

        // Reverse Binding OTP Check
        if (is_numeric($text) && strlen($text) === 6) {
            $stmt = \App\Core\Database::getInstance()->prepare("SELECT id FROM bookies WHERE telegram_verification_code = ? AND telegram_verified = 0");
            $stmt->execute([trim($text)]);
            $matchingBookieId = $stmt->fetchColumn();

            if ($matchingBookieId) {
                $bookieService = new BookieService();
                $bookieService->bindTelegramId($matchingBookieId, $chatId);
                
                // Clear the OTP so it can't be reused
                \App\Core\Database::getInstance()->prepare("UPDATE bookies SET telegram_verification_code = NULL WHERE id = ?")->execute([$matchingBookieId]);

                $tg->sendMessage($chatId, "✅ <b>Verification Successful!</b>\nYour Telegram account is now permanently bound to your Bookie profile.\n\nYou may now return to the Web Dashboard and refresh the page to continue your setup.");
                return;
            }
        }

        // Ensure wallet exists for this relationship
        $walletService = new WalletService();
        $wallet = $walletService->getWalletForBookiePlayer($bookiePlayerId);

        $state = $playerRepo->getState($bookieId, $playerId);

        if ($text === '/start') {
            $playerRepo->setState($bookieId, $playerId, 'MAIN_MENU');
            $response = $tg->sendMessage($chatId, "Welcome to <b>{$message['chat']['first_name']}</b>'s bookie bot!", [
                'inline_keyboard' => [
                    [
                        ['text' => '💰 Deposit', 'callback_data' => 'DEPOSIT'],
                        ['text' => '📊 Balance', 'callback_data' => 'BALANCE']
                    ],
                    [
                        ['text' => '🏧 Withdraw', 'callback_data' => 'WITHDRAW'],
                        ['text' => '🕒 Active Request', 'callback_data' => 'ACTIVE_REQUESTS']
                    ]
                ]
            ]);
            file_put_contents(__DIR__ . '/../../../../storage/logs/webhook.log', "[" . date('Y-m-d H:i:s') . "] Telegram response: " . $response . "\n", FILE_APPEND);
        } elseif ($text === '/balance') {
            $avail = number_format($wallet['available_balance'], 2);
            $locked = number_format($wallet['locked_balance'], 2);
            $msg = "<b>💰 Your Wallet</b>\n\n";
            $msg .= "Available Balance: <b>\${$avail}</b>\n";
            if ($wallet['locked_balance'] > 0) {
                $msg .= "Pending Withdrawals: <b>\${$locked}</b>";
            }
            $tg->sendMessage($chatId, $msg, [
                'inline_keyboard' => [[['text' => 'Back to Menu', 'callback_data' => 'MAIN_MENU']]]
            ]);
        } elseif ($state === 'AWAITING_DEPOSIT_AMOUNT') {
            $amount = (float) $text;
            if ($amount <= 0) {
                $tg->sendMessage($chatId, "Please enter a valid amount greater than 0.");
                return;
            }

            // Fetch active configured gateways for the bookie
            $stmt = \App\Core\Database::getInstance()->prepare("
                SELECT g.id, g.name 
                FROM bookie_gateways bg 
                JOIN gateways g ON bg.gateway_id = g.id 
                WHERE bg.bookie_id = ? AND bg.status = 'active'
            ");
            $stmt->execute([$bookieId]);
            $gateways = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($gateways)) {
                $tg->sendMessage($chatId, "No deposit methods are currently available. Please contact your bookie.");
                $playerRepo->setState($bookieId, $playerId, 'MAIN_MENU');
                return;
            }

            $keyboard = [];
            $buttonRow = [];
            $count = 0;
            foreach ($gateways as $g) {
                // Using DEPOSIT_METHOD_{gatewayName}
                $buttonRow[] = ['text' => "Wallet: " . $g['name'], 'callback_data' => 'DEPOSIT_METHOD_' . $g['name']];
                $count++;
                if ($count % 2 == 0) {
                    $keyboard[] = $buttonRow;
                    $buttonRow = [];
                }
            }
            if (!empty($buttonRow)) {
                $keyboard[] = $buttonRow;
            }

            $playerRepo->setState($bookieId, $playerId, 'AWAITING_DEPOSIT_METHOD', ['amount' => $amount]);
            $tg->sendMessage($chatId, "Please select the wallet you want to use for this \${$amount} deposit:", [
                'inline_keyboard' => $keyboard
            ]);

        } elseif ($state === 'AWAITING_SPLIT_RECEIPT') {
            if (!isset($message['photo'])) {
                $tg->sendMessage($chatId, "Please upload a photo of your payment receipt.");
                return;
            }

            $photo = end($message['photo']);
            $receiptFileId = $photo['file_id'];
            $payload = $playerRepo->getPayload($bookieId, $playerId);
            
            if (!$payload || !isset($payload['deposit_id']) || !isset($payload['current_split_index'])) {
                $tg->sendMessage($chatId, "Session expired. Returning to main menu.");
                $playerRepo->setState($bookieId, $playerId, 'MAIN_MENU');
                return;
            }

            $depositId = $payload['deposit_id'];
            $currentIndex = $payload['current_split_index'];

            // Get current split ID
            $stmt = \App\Core\Database::getInstance()->prepare("SELECT id FROM deposit_splits WHERE deposit_id = ? AND sequence_no = ?");
            $stmt->execute([$depositId, $currentIndex]);
            $splitId = $stmt->fetchColumn();

            if (!$splitId) {
                $tg->sendMessage($chatId, "Split not found. Returning to main menu.");
                $playerRepo->setState($bookieId, $playerId, 'MAIN_MENU');
                return;
            }

            // Save receipt for this split
            $queueService = new \App\Modules\Wallet\Services\QueueService();
            $queueService->handleReceiptUpload($splitId, $receiptFileId, $playerId);

            // Move to next split or finish
            $stmt = \App\Core\Database::getInstance()->prepare("SELECT COUNT(*) FROM deposit_splits WHERE deposit_id = ?");
            $stmt->execute([$depositId]);
            $totalSplits = $stmt->fetchColumn();

            if ($currentIndex < $totalSplits) {
                $this->sendNextSplitInstruction($tg, $chatId, $bookieId, $playerId, $depositId, $currentIndex + 1);
            } else {
                // All finished - Instantly approve the deposit in main table since P2P logic already credited wallet
                $playerRepo->setState($bookieId, $playerId, 'MAIN_MENU');
                $stmt = \App\Core\Database::getInstance()->prepare("UPDATE deposits SET status = 'approved' WHERE id = ?");
                $stmt->execute([$depositId]);

                $tg->sendMessage($chatId, "✅ <b>All payment receipts have been submitted.</b>\nYour deposit has been approved and your wallet is credited instantly! Recipients have 10 minutes to review the payment.", [
                    'inline_keyboard' => [[['text' => 'Back to Menu', 'callback_data' => 'MAIN_MENU']]]
                ]);
            }

        } elseif ($state === 'AWAITING_RECEIPT') {
            // Check if photo exists
            if (!isset($message['photo'])) {
                $tg->sendMessage($chatId, "Please upload a photo of your payment receipt (as an image, not a file document).");
                return;
            }

            // Get largest size photo
            $photo = end($message['photo']);
            $receiptFileId = $photo['file_id'];

            $payload = $playerRepo->getPayload($bookieId, $playerId);
            if (!$payload || !isset($payload['deposit_id'])) {
                $tg->sendMessage($chatId, "Session expired. Returning to main menu.");
                $playerRepo->setState($bookieId, $playerId, 'MAIN_MENU');
                return;
            }

            // Update Database with receipt
            $depositService = new \App\Modules\Wallet\Services\DepositService();
            $depositService->saveReceipt($payload['deposit_id'], $receiptFileId);

            // Set state to main menu
            $playerRepo->setState($bookieId, $playerId, 'MAIN_MENU');

            $tg->sendMessage($chatId, "✅ Receipt uploaded successfully! The Bookie will review it shortly.", [
                'inline_keyboard' => [
                    [['text' => 'Back to Menu', 'callback_data' => 'MAIN_MENU']]
                ]
            ]);

            // Notify Bookie
            $notifier = new \App\Modules\Telegram\Services\NotificationService();
            $depAmt = number_format($payload['amount'], 2);
            $cap = "🚨 <b>New Deposit Request</b>\n\nPlayer: <b>{$message['chat']['first_name']}</b> (@{$message['from']['username']})\nAmount: <b>\${$depAmt}</b>";
            
            $notifier->notifyBookie($bookieId, $cap, $receiptFileId, [
                [
                    ['text' => '✅ Approve', 'callback_data' => 'APPROVE_DEPOSIT_' . $payload['deposit_id']],
                    ['text' => '❌ Reject', 'callback_data' => 'REJECT_DEPOSIT_' . $payload['deposit_id']]
                ]
            ]);
        } elseif ($state === 'AWAITING_WITHDRAW_AMOUNT') {
            $amount = (float) $text;
            if ($amount <= 0 || $amount > $wallet['available_balance']) {
                $tg->sendMessage($chatId, "Invalid amount. You can withdraw up to \${$wallet['available_balance']}.");
                return;
            }

            $playerRepo->setState($bookieId, $playerId, 'AWAITING_WITHDRAW_METHOD', ['amount' => $amount]);
            $keyboard = [];

            // Fetch Bookie's accepted gateways to show them as primary buttons
            $gatewaysStmt = \App\Core\Database::getInstance()->prepare("
                SELECT g.name 
                FROM bookie_gateways bg 
                JOIN gateways g ON bg.gateway_id = g.id 
                WHERE bg.bookie_id = ? AND bg.status = 'active'
            ");
            $gatewaysStmt->execute([$bookieId]);
            $gateways = $gatewaysStmt->fetchAll();

            $suggestedNames = [];
            foreach ($gateways as $g) {
                $suggestedNames[] = $g['name'];
            }
            // Fallback options if bookie has NO gateways configured yet
            if (empty($suggestedNames)) {
                $suggestedNames = ['CashApp', 'Venmo', 'PayPal', 'Crypto'];
            }

            $buttonRow = [];
            $count = 0;
            foreach ($suggestedNames as $name) {
                $buttonRow[] = ['text' => $name, 'callback_data' => 'SET_METHOD_NAME_' . $name];
                $count++;
                if ($count % 2 == 0) {
                    $keyboard[] = $buttonRow;
                    $buttonRow = [];
                }
            }
            if (!empty($buttonRow)) {
                $keyboard[] = $buttonRow;
            }

            $keyboard[] = [['text' => '➕ Other Method', 'callback_data' => 'ADD_PAYOUT_METHOD']];

            $tg->sendMessage($chatId, "Please select a payout method to receive your \${$amount}:", [
                'inline_keyboard' => $keyboard
            ]);
            
        } elseif ($state === 'AWAITING_PAYOUT_METHOD_NAME') {
            $payload = $playerRepo->getPayload($bookieId, $playerId);
            $payload['method_name'] = $text;
            $playerRepo->setState($bookieId, $playerId, 'AWAITING_PAYOUT_METHOD_HANDLE', $payload);
            $tg->sendMessage($chatId, "Great. Now please enter the Handle/Account number for {$text}:");

        } elseif ($state === 'AWAITING_PAYOUT_METHOD_HANDLE') {
            $payload = $playerRepo->getPayload($bookieId, $playerId);
            $methodName = $payload['method_name'] ?? 'Unknown';
            $handle = $text;
            $payload['handle'] = $handle;

            $playerRepo->setState($bookieId, $playerId, 'CONFIRM_PAYOUT_DETAILS', $payload);
            
            $amt = number_format($payload['amount'], 2);
            $msg = "<b>Please confirm your payout details:</b>\n\n";
            $msg .= "💰 Amount: <b>\${$amt}</b>\n";
            $msg .= "🏦 Method: <b>{$methodName}</b>\n";
            $msg .= "👤 Account: <code>{$handle}</code>\n\n";
            $msg .= "Is this correct?";

            $tg->sendMessage($chatId, $msg, [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Yes, Proceed', 'callback_data' => 'CONFIRM_PAYOUT_YES'],
                        ['text' => '❌ No, Change', 'callback_data' => 'CONFIRM_PAYOUT_NO']
                    ]
                ]
            ]);
        }
    }

    private function handleCallback($callback, $tg, $playerRepo, $bookieId) {
        $chatId = $callback['message']['chat']['id'];
        $from = $callback['from'];
        $data = $callback['data'];

        $playerId = $playerRepo->ensurePlayer($from);
        $bookiePlayerId = $playerRepo->ensureMapping($bookieId, $playerId);

        // Enforce withdrawal cancel expiry
        $queueService = new \App\Modules\Wallet\Services\QueueService();
        $queueService->checkAndExpirePendingCancels($playerId, $bookieId);

        // Ensure wallet exists
        $walletService = new WalletService();
        $wallet = $walletService->getWalletForBookiePlayer($bookiePlayerId);

        $tg->answerCallbackQuery($callback['id']);

        if ($data === 'DEPOSIT') {
            $playerRepo->setState($bookieId, $playerId, 'AWAITING_DEPOSIT_AMOUNT');
            $oldText = $callback['message']['text'] ?? "Main Menu";
            $tg->editMessageText($chatId, $callback['message']['message_id'], $oldText . "\n\n<b>➔ You selected: Deposit</b>");
            $tg->sendMessage($chatId, "How much would you like to deposit? (Please type a number, e.g. 50)");
        } elseif ($data === 'MAIN_MENU') {
            $playerRepo->setState($bookieId, $playerId, 'MAIN_MENU');
            $tg->sendMessage($chatId, "Welcome to the bookie bot!", [
                'inline_keyboard' => [
                    [
                        ['text' => '💰 Deposit', 'callback_data' => 'DEPOSIT'],
                        ['text' => '📊 Balance', 'callback_data' => 'BALANCE']
                    ],
                    [
                        ['text' => '🏧 Withdraw', 'callback_data' => 'WITHDRAW'],
                        ['text' => '🕒 Active Request', 'callback_data' => 'ACTIVE_REQUESTS']
                    ]
                ]
            ]);
        } elseif ($data === 'ACTIVE_REQUESTS') {
            // Remove buttons from menu
            $oldText = $callback['message']['text'] ?? "Main Menu";
            $tg->editMessageText($chatId, $callback['message']['message_id'], $oldText . "\n\n<b>➔ Selected: Active Requests</b>");

            $db = \App\Core\Database::getInstance();
            
            // 1. Pending Deposits (where they might need to upload receipt)
            // For split_p2p, we look at splits. For gateway, we look at main status.
            $stmt = $db->prepare("
                SELECT id, amount, status, deposit_type, created_at 
                FROM deposits 
                WHERE player_id = ? AND bookie_id = ? 
                AND status NOT IN ('approved', 'rejected')
                ORDER BY created_at DESC
            ");
            $stmt->execute([$playerId, $bookieId]);
            $deposits = $stmt->fetchAll();

            // 2. Pending Withdrawals
            $stmt = $db->prepare("
                SELECT w.id, w.amount_requested, w.amount_confirmed, w.status, pm.method_name, w.created_at
                FROM withdrawals w
                JOIN payout_methods pm ON w.method_id = pm.id
                WHERE w.player_id = ? AND w.bookie_id = ?
                AND w.status IN ('queued', 'partially_paid', 'cancel_pending')
                ORDER BY w.created_at DESC
            ");
            $stmt->execute([$playerId, $bookieId]);
            $withdrawals = $stmt->fetchAll();

            $msg = "<b>🕒 Your Active Requests</b>\n\n";
            $hasAny = false;
            $keyboard = [];

            if (!empty($deposits)) {
                $hasAny = true;
                $msg .= "<b>💳 Pending Deposits:</b>\n";
                foreach ($deposits as $d) {
                    $amt = number_format($d['amount'], 2);
                    $status = ucwords(str_replace('_', ' ', $d['status']));
                    $date = date('M d, H:i', strtotime($d['created_at']));
                    $msg .= "• #{$d['id']} - <b>\${$amt}</b> ({$status}) - <i>{$date}</i>\n";
                }
                $msg .= "\n";
            }

            if (!empty($withdrawals)) {
                $hasAny = true;
                $msg .= "<b>🏧 Pending Withdrawals:</b>\n";
                foreach ($withdrawals as $w) {
                    $req = number_format($w['amount_requested'], 2);
                    $conf = number_format($w['amount_confirmed'], 2);
                    $status = $w['status'] === 'cancel_pending' ? 'Expiring...' : ucwords(str_replace('_', ' ', $w['status']));
                    $msg .= "• #{$w['id']} - <b>\${$req}</b> ({$status})\n";
                    if ($w['amount_confirmed'] > 0) {
                        $msg .= "  <i>Paid: \${$conf}</i>\n";
                    }

                    if ($w['status'] === 'queued') {
                        $keyboard[] = [['text' => "❌ Cancel Withdrawal #{$w['id']}", 'callback_data' => "CANCEL_WITHDRAWAL_{$w['id']}"]];
                    }
                }
            }

            if (!$hasAny) {
                $msg = "<b>🕒 Your Active Requests</b>\n\nYou have no active deposits or withdrawals at the moment.";
            }

            $keyboard[] = [['text' => 'Back to Menu', 'callback_data' => 'MAIN_MENU']];

            $tg->sendMessage($chatId, $msg, [
                'inline_keyboard' => $keyboard
            ]);

        } elseif (strpos($data, 'CANCEL_WITHDRAWAL_') === 0) {
            $withdrawalId = str_replace('CANCEL_WITHDRAWAL_', '', $data);
            $queueService = new \App\Modules\Wallet\Services\QueueService();
            
            $result = $queueService->requestCancelWithdrawal($withdrawalId);
            
            if (isset($result['blocked']) && $result['blocked']) {
                $reason = $result['reason'] === 'reserved' ? 'It is currently processing a split payment.' : 'It is no longer in the queue.';
                $tg->answerCallbackQuery($callback['id'], "Cannot cancel this withdrawal: {$reason}", true);
            } else {
                $msg = "⚠️ <b>Cancel Withdrawal #{$withdrawalId}</b>\n\n<b>Selected :</b> Cancel Withdraw\n\nAre you sure you want to cancel this withdrawal? The funds will be credited back to your balance.\n\n<i>This request will expire automatically in 10 minutes if not confirmed.</i>";

                $tg->editMessageText($chatId, $callback['message']['message_id'], $msg, [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Confirm Cancel', 'callback_data' => "CONFIRM_CANCEL_WD_{$withdrawalId}"],
                            ['text' => '❌ Keep Withdrawal', 'callback_data' => "KEEP_WITHDRAWAL_{$withdrawalId}"]
                        ]
                    ]
                ]);
            }
        } elseif (strpos($data, 'CONFIRM_CANCEL_WD_') === 0) {
            $withdrawalId = str_replace('CONFIRM_CANCEL_WD_', '', $data);
            $queueService = new \App\Modules\Wallet\Services\QueueService();
            
            $result = $queueService->confirmCancelWithdrawal($withdrawalId);
            
            if (isset($result['expired']) && $result['expired']) {
                $tg->editMessageText($chatId, $callback['message']['message_id'], "❌ <b>Cancellation Expired</b>\n\nThe 10-minute confirmation window has expired. Your withdrawal has been restored to the queue.");
            } elseif ($result['success']) {
                $refund = number_format($result['amount_refunded'], 2);
                $tg->editMessageText(
                    $chatId,
                    $callback['message']['message_id'],
                    "✅ <b>Withdrawal Cancelled!</b>\n\n\${$refund} has been successfully credited back to your available balance.",
                    ['inline_keyboard' => [[['text' => 'Back to Menu', 'callback_data' => 'MAIN_MENU']]]]
                );
            } else {
                $tg->answerCallbackQuery($callback['id'], "Failed to confirm cancellation.", true);
            }
        } elseif (strpos($data, 'KEEP_WITHDRAWAL_') === 0) {
            $withdrawalId = str_replace('KEEP_WITHDRAWAL_', '', $data);
            $db = \App\Core\Database::getInstance();
            $db->prepare("UPDATE withdrawals SET status = 'queued', cancel_confirmation_expires_at = NULL WHERE id = ?")->execute([$withdrawalId]);
            $tg->editMessageText(
                $chatId,
                $callback['message']['message_id'],
                "✅ <b>Withdrawal Kept</b>\n\nYour withdrawal is still safe in the queue.",
                ['inline_keyboard' => [[['text' => 'Back to Menu', 'callback_data' => 'MAIN_MENU']]]]
            );
        } elseif (strpos($data, 'DISPUTE_SPLIT_') === 0) {
            $splitId = str_replace('DISPUTE_SPLIT_', '', $data);
            $queueService = new \App\Modules\Wallet\Services\QueueService();
            
            try {
                $queueService->disputeSplit($splitId);
                
                // Get details for notification
                $db = \App\Core\Database::getInstance();
                $stmt = $db->prepare("
                    SELECT ds.id, ds.amount, ds.recipient_notified_at, d.id as deposit_id, d.bookie_id, p.first_name as depositor_name
                    FROM deposit_splits ds
                    JOIN deposits d ON d.id = ds.deposit_id
                    JOIN players p ON p.id = d.player_id
                    WHERE ds.id = ?
                ");
                $stmt->execute([$splitId]);
                $details = $stmt->fetch(\PDO::FETCH_ASSOC);

                $tg->sendMessage($chatId, "We have notified the bookie and Admin.");
                
                // Edit previous message (remove button and show disputed)
                $oldText = htmlspecialchars($callback['message']['caption'] ?? $callback['message']['text'] ?? "Split #{$splitId}");
                $newText = $oldText . "\n\n❌ <b>DISPUTED</b>";
                
                if (isset($callback['message']['photo'])) {
                    $tg->editMessageCaption($chatId, $callback['message']['message_id'], $newText, ['inline_keyboard' => []]);
                } else {
                    $tg->editMessageText($chatId, $callback['message']['message_id'], $newText, ['inline_keyboard' => []]);
                }

                if ($details && $details['bookie_id']) {
                    $notifier = new \App\Modules\Telegram\Services\NotificationService();
                    $amt = number_format($details['amount'], 2);
                    $notifiedAt = date('H:i:s', strtotime($details['recipient_notified_at']));
                    
                    $msg = "⚠️ <b>Split Disputed!</b>\n\n";
                    $msg .= "Split ID: <b>#{$splitId}</b>\n";
                    $msg .= "Deposit ID: <b>#{$details['deposit_id']}</b>\n";
                    $msg .= "Depositor: <b>{$details['depositor_name']}</b>\n";
                    $msg .= "Amount: <b>\${$amt}</b>\n";
                    $msg .= "Notified At: <b>{$notifiedAt}</b>\n\n";
                    $msg .= "The recipient claims they did NOT receive this payment. Please check your dashboard and resolve manually.";
                    
                    $notifier->notifyBookie($details['bookie_id'], $msg);
                }
            } catch (\Exception $e) {
                $tg->answerCallbackQuery($callback['id'], "Failed to dispute: " . $e->getMessage());
            }
        } elseif (strpos($data, 'DEPOSIT_METHOD_') === 0) {
            $methodName = str_replace('DEPOSIT_METHOD_', '', $data);
            
            $payload = $playerRepo->getPayload($bookieId, $playerId);
            if (!$payload || !isset($payload['amount'])) {
                $tg->sendMessage($chatId, "Session expired. Returning to main menu.");
                $playerRepo->setState($bookieId, $playerId, 'MAIN_MENU');
                return;
            }

            $amount = $payload['amount'];
            
            // Remove buttons & show selection
            $oldText = $callback['message']['text'] ?? "Wallet selection";
            $tg->editMessageText($chatId, $callback['message']['message_id'], $oldText . "\n\n<b>➔ Wallet selected is " . htmlspecialchars($methodName) . "</b>");

            $db = \App\Core\Database::getInstance();
            
            // Check if it's the Bookie's first deposit (ever)
            $stmt = $db->prepare("SELECT COUNT(*) FROM deposits WHERE bookie_id = ? AND status != 'rejected'");
            $stmt->execute([$bookieId]);
            $bookieHasDeposits = ($stmt->fetchColumn() > 0);

            $depositService = new \App\Modules\Wallet\Services\DepositService();
            $queueService = new \App\Modules\Wallet\Services\QueueService();

            if (!$bookieHasDeposits) {
                // BOOKIE'S FIRST DEPOSIT: Bypass P2P entirely
                $depositId = $depositService->initiateDeposit($bookieId, $playerId, $amount, null);
                
                $updStmt = $db->prepare("UPDATE deposits SET deposit_type = 'split_p2p', status = 'split_assigned' WHERE id = ?");
                $updStmt->execute([$depositId]);

                $splits = $queueService->generateBookieFirstDepositPlan($bookieId, $amount, $methodName);
                $queueService->reserveSplits($depositId, $splits);

                $this->sendNextSplitInstruction($tg, $chatId, $bookieId, $playerId, $depositId, 1);
            } else {
                // SUBSEQUENT DEPOSITS: Use P2P Queue (atomic generation + reservation)
                $depositId = $depositService->initiateDeposit($bookieId, $playerId, $amount, null);
                
                $updStmt = $db->prepare("UPDATE deposits SET deposit_type = 'split_p2p', status = 'split_assigned' WHERE id = ?");
                $updStmt->execute([$depositId]);

                // generateAndReserveSplits atomically selects, inserts splits, and reserves
                // withdrawals in one transaction — prevents concurrent double-assignment.
                $splits = $queueService->generateAndReserveSplits($bookieId, $depositId, $amount, $methodName, $playerId);

                $this->sendNextSplitInstruction($tg, $chatId, $bookieId, $playerId, $depositId, 1);
            }

        } elseif (strpos($data, 'GATEWAY_') === 0) {
            // Player selected a gateway. E.g., GATEWAY_1
            $gatewayId = str_replace('GATEWAY_', '', $data);
            
            $payload = $playerRepo->getPayload($bookieId, $playerId);
            if (!$payload || !isset($payload['amount'])) {
                $tg->sendMessage($chatId, "Session expired. Returning to main menu.");
                $playerRepo->setState($bookieId, $playerId, 'MAIN_MENU');
                return;
            }

            // Create Pending Deposit
            $depositService = new \App\Modules\Wallet\Services\DepositService();
            $depositId = $depositService->initiateDeposit($bookieId, $playerId, $payload['amount'], $gatewayId);

            // Fetch gateway config to show the player how to pay
            $gwStmt = \App\Core\Database::getInstance()->prepare("SELECT config_values FROM bookie_gateways WHERE bookie_id = ? AND gateway_id = ?");
            $gwStmt->execute([$bookieId, $gatewayId]);
            $configJson = $gwStmt->fetchColumn();
            
            $config = json_decode($configJson, true) ?: [];
            
            $msg = "Please send <b>\${$payload['amount']}</b> to the following details:\n\n";
            foreach ($config as $key => $val) {
                $cleanKey = ucwords(str_replace('_', ' ', $key));
                $msg .= "{$cleanKey}: <code>{$val}</code>\n";
            }
            $msg .= "\n📸 <b>After sending, please reply to this message with a screenshot of your payment receipt.</b>";
            
            $playerRepo->setState($bookieId, $playerId, 'AWAITING_RECEIPT', [
                'amount' => $payload['amount'],
                'deposit_id' => $depositId
            ]);

            $tg->sendMessage($chatId, $msg);

        } elseif (strpos($data, 'APPROVE_DEPOSIT_') === 0) {
            $depositId = str_replace('APPROVE_DEPOSIT_', '', $data);
            $depositService = new \App\Modules\Wallet\Services\DepositService();
            $deposit = $depositService->getDeposit($depositId);

            $allowedStatuses = ['pending', 'receipts_submitted', 'under_review_window', 'partially_disputed'];
            if ($deposit && in_array($deposit['status'], $allowedStatuses)) {
                $depositService->updateStatus($depositId, 'approved');
                
                // Trigger Queue Matching Here
                $queueService = new \App\Modules\Wallet\Services\QueueService();
                $queueService->allocateDeposit($depositId);

                $oldText = htmlspecialchars($callback['message']['caption'] ?? $callback['message']['text'] ?? "Deposit #{$depositId}");
                $newText = $oldText . "\n\n<b>Action:</b> ✅ Approved";
                $tg->editMessageCaption($chatId, $callback['message']['message_id'], $newText);
                
                // Notify Player
                $notifier = new \App\Modules\Telegram\Services\NotificationService();
                if ($deposit['deposit_type'] === 'split_p2p') {
                    $notifier->notifyPlayer($deposit['player_id'], "✅ <b>Deposit Finalized!</b>\nYour \${$deposit['amount']} split deposit is fully approved. (Note: Credits were incrementally added as splits were accepted).");
                } else {
                    $notifier->notifyPlayer($deposit['player_id'], "✅ <b>Deposit Approved!</b>\n\${$deposit['amount']} has been added to your queue/wallet.");
                }
            } else {
                $tg->answerCallbackQuery($callback['id'], "Deposit already processed or not found.");
            }

        } elseif (strpos($data, 'REJECT_DEPOSIT_') === 0) {
            $depositId = str_replace('REJECT_DEPOSIT_', '', $data);
            $depositService = new \App\Modules\Wallet\Services\DepositService();
            $deposit = $depositService->getDeposit($depositId);

            $allowedStatuses = ['pending', 'receipts_submitted', 'under_review_window', 'partially_disputed'];
            if ($deposit && in_array($deposit['status'], $allowedStatuses)) {
                $depositService->updateStatus($depositId, 'rejected');
                
                $oldText = htmlspecialchars($callback['message']['caption'] ?? $callback['message']['text'] ?? "Deposit #{$depositId}");
                $newText = $oldText . "\n\n<b>Action:</b> ❌ Rejected";
                $tg->editMessageCaption($chatId, $callback['message']['message_id'], $newText);
                
                $notifier->notifyPlayer($deposit['player_id'], "❌ <b>Deposit Rejected</b>\nYour deposit of \${$deposit['amount']} was rejected. Please contact support.");
            } else {
                $tg->answerCallbackQuery($callback['id'], "Deposit already processed or not found.");
            }
        } elseif ($data === 'BALANCE') {
            $avail = number_format($wallet['available_balance'], 2);
            $msg = "<b>💰 Balance is : \${$avail}</b>";
            $tg->editMessageText($chatId, $callback['message']['message_id'], $msg, [
                'inline_keyboard' => [[['text' => 'Back to Menu', 'callback_data' => 'MAIN_MENU']]]
            ]);
        } elseif ($data === 'WITHDRAW') {
            // Check if player already has an active withdrawal
            $db = \App\Core\Database::getInstance();
            $stmt = $db->prepare("
                SELECT amount_remaining 
                FROM withdrawals 
                WHERE player_id = ? AND bookie_id = ? 
                AND status IN ('queued', 'partially_paid', 'partial_dispute')
                LIMIT 1
            ");
            $stmt->execute([$playerId, $bookieId]);
            $activeWith = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($activeWith) {
                $pendingAmt = number_format($activeWith['amount_remaining'], 2);
                $tg->sendMessage($chatId, "⚠️ <b>Action Blocked</b>\n\nYour earlier withdrawal request is still pending (<b>\${$pendingAmt}</b>), so you cannot request another withdrawal request.\n\nIf you want urgent withdrawal, talk to Bookie directly.", [
                    'inline_keyboard' => [[['text' => 'Back to Menu', 'callback_data' => 'MAIN_MENU']]]
                ]);
                return;
            }

            $playerRepo->setState($bookieId, $playerId, 'AWAITING_WITHDRAW_AMOUNT');
            $oldText = $callback['message']['text'] ?? "Main Menu";
            $tg->editMessageText($chatId, $callback['message']['message_id'], $oldText . "\n\n<b>➔ You selected: Withdraw</b>");
            $tg->sendMessage($chatId, "How much would you like to withdraw? (Your available balance is \${$wallet['available_balance']})");
        } elseif ($data === 'ADD_PAYOUT_METHOD') {
            $payload = $playerRepo->getPayload($bookieId, $playerId);
            $playerRepo->setState($bookieId, $playerId, 'AWAITING_PAYOUT_METHOD_NAME', $payload);
            
            $oldText = $callback['message']['text'] ?? "Withdrawal method selection";
            $tg->editMessageText($chatId, $callback['message']['message_id'], $oldText . "\n\n<b>➔ Method Selected: Other</b>", ['inline_keyboard' => []]);

            $tg->sendMessage($chatId, "Please type the name of the payout platform you want to use (e.g., Zelle, Apple Pay):");
        } elseif (strpos($data, 'SET_METHOD_NAME_') === 0) {
            $methodName = str_replace('SET_METHOD_NAME_', '', $data);
            $payload = $playerRepo->getPayload($bookieId, $playerId);
            $payload['method_name'] = $methodName;
            $playerRepo->setState($bookieId, $playerId, 'AWAITING_PAYOUT_METHOD_HANDLE', $payload);
            
            $oldText = $callback['message']['text'] ?? "Withdrawal method selection";
            $tg->editMessageText($chatId, $callback['message']['message_id'], $oldText . "\n\n<b>➔ Method Selected: " . htmlspecialchars($methodName) . "</b>", ['inline_keyboard' => []]);

            $tg->sendMessage($chatId, "Great. Now please enter your Handle/Address for {$methodName} in chat:");
        } elseif ($data === 'CONFIRM_PAYOUT_YES') {
            $payload = $playerRepo->getPayload($bookieId, $playerId);
            if (!$payload || !isset($payload['amount']) || !isset($payload['method_name']) || !isset($payload['handle'])) {
                $tg->sendMessage($chatId, "Session expired. Returning to main menu.");
                $playerRepo->setState($bookieId, $playerId, 'MAIN_MENU');
                return;
            }

            $amount = $payload['amount'];
            $methodName = $payload['method_name'];
            $handle = $payload['handle'];

            $withRepo = new \App\Modules\Wallet\Repositories\WithdrawalRepository();
            $methodId = $withRepo->addPlayerMethod($playerId, $methodName, $handle);

            // Create the withdrawal 
            $withdrawalId = $withRepo->createWithdrawal($bookieId, $playerId, $amount, $methodId);
            
            // Lock funds
            $walletService = new WalletService();
            $walletService->lockForWithdrawal($wallet['id'], $amount, $withdrawalId);

            $playerRepo->setState($bookieId, $playerId, 'MAIN_MENU');
            
            // Edit confirmation message to show it's done
            $oldText = $callback['message']['text'] ?? "Confirmation";
            $tg->editMessageText($chatId, $callback['message']['message_id'], $oldText . "\n\n✅ <b>Confirmed!</b>");

            $tg->sendMessage($chatId, "✅ <b>Withdrawal Requested!</b>\n\${$amount} has been locked and added to the payout queue. We will notify you when it is processed.", [
                'inline_keyboard' => [[['text' => 'Back to Menu', 'callback_data' => 'MAIN_MENU']]]
            ]);
            
            // Notify Bookie
            $notifier = new \App\Modules\Telegram\Services\NotificationService();
            $notifier->notifyBookie($bookieId, "🏧 <b>New Withdrawal Request</b>\n\nPlayer: <b>{$from['first_name']}</b> (@" . ($from['username'] ?? 'unknown') . ")\nAmount: <b>\${$amount}</b>\nMethod: {$methodName} ({$handle})\nStatus: Queued");

        } elseif ($data === 'CONFIRM_PAYOUT_NO') {
            // Take them back to method selection
            $payload = $playerRepo->getPayload($bookieId, $playerId);
            $amount = $payload['amount'] ?? 0;
            
            $playerRepo->setState($bookieId, $playerId, 'AWAITING_WITHDRAW_METHOD', ['amount' => $amount]);
            
            $tg->editMessageText($chatId, $callback['message']['message_id'], "Payout cancelled. Please select a payout method again:");
            
            // Re-send selection menu (duplicating logic from handleMessage for WITHDRAW)
            $gatewaysStmt = \App\Core\Database::getInstance()->prepare("
                SELECT g.name 
                FROM bookie_gateways bg 
                JOIN gateways g ON bg.gateway_id = g.id 
                WHERE bg.bookie_id = ? AND bg.status = 'active'
            ");
            $gatewaysStmt->execute([$bookieId]);
            $gateways = $gatewaysStmt->fetchAll();

            $suggestedNames = [];
            foreach ($gateways as $g) { $suggestedNames[] = $g['name']; }
            if (empty($suggestedNames)) { $suggestedNames = ['CashApp', 'Venmo', 'PayPal', 'Crypto']; }

            $keyboard = [];
            $buttonRow = [];
            $count = 0;
            foreach ($suggestedNames as $name) {
                $buttonRow[] = ['text' => $name, 'callback_data' => 'SET_METHOD_NAME_' . $name];
                $count++;
                if ($count % 2 == 0) { $keyboard[] = $buttonRow; $buttonRow = []; }
            }
            if (!empty($buttonRow)) { $keyboard[] = $buttonRow; }
            $keyboard[] = [['text' => '➕ Other Method', 'callback_data' => 'ADD_PAYOUT_METHOD']];

            $tg->sendMessage($chatId, "Please select a payout method to receive your \${$amount}:", [
                'inline_keyboard' => $keyboard
            ]);
        }
    }

    private function sendNextSplitInstruction($tg, $chatId, $bookieId, $playerId, $depositId, $index) {
        $stmt = \App\Core\Database::getInstance()->prepare("
            SELECT * FROM deposit_splits 
            WHERE deposit_id = ? AND sequence_no = ?
        ");
        $stmt->execute([$depositId, $index]);
        $split = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$split) return;

        $totalSplitsStmt = \App\Core\Database::getInstance()->prepare("SELECT COUNT(*) FROM deposit_splits WHERE deposit_id = ?");
        $totalSplitsStmt->execute([$depositId]);
        $total = $totalSplitsStmt->fetchColumn();

        $amount = number_format($split['amount'], 2);
        $recipientName = "Recipient";
        
        if ($split['recipient_type'] === 'withdraw_player') {
            $userStmt = \App\Core\Database::getInstance()->prepare("SELECT first_name, username FROM players WHERE id = ?");
            $userStmt->execute([$split['recipient_player_id']]);
            $user = $userStmt->fetch(\PDO::FETCH_ASSOC);
            $recipientName = $user['first_name'] ?? $user['username'] ?? "Player";
        } else {
            $recipientName = "Bookie";
        }

        $msg = "<b>Step {$index} of {$total}</b>\n";
        $msg .= "Send <b>\${$amount}</b> to <b>{$recipientName}</b>\n\n";
        $msg .= "Method: <code>{$split['payment_method']}</code>\n";
        $msg .= "Handle: <code>{$split['payment_handle']}</code>\n\n";
        $msg .= "📸 After sending the payment, upload the receipt here.";

        $playerRepository = new PlayerRepository();
        $playerRepository->setState($bookieId, $playerId, 'AWAITING_SPLIT_RECEIPT', [
            'deposit_id' => $depositId,
            'current_split_index' => $index
        ]);

        $tg->sendMessage($chatId, $msg);
    }
}
