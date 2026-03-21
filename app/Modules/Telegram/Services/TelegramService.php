<?php

namespace App\Modules\Telegram\Services;

class TelegramService {
    private $token;

    public function __construct($token) {
        $this->token = $token;
    }

    public function request($method, $data) {
        $url = BASE_API_URL . $this->token . '/' . $method;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        // Logging
        $logPath = __DIR__ . '/../../../../storage/logs/telegram.log';
        $logMsg = date('c') . " | TOKEN: " . substr($this->token, 0, 8) . "... | METHOD: $method | RES: $res | ERR: $err\n";
        file_put_contents($logPath, $logMsg, FILE_APPEND);

        return json_decode($res, true);
    }

    public function sendMessage($chatId, $text, $replyMarkup = []) {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        if (!empty($replyMarkup)) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
        return $this->request('sendMessage', $data);
    }

    public function answerCallbackQuery($callbackQueryId, $text = '') {
        $data = ['callback_query_id' => $callbackQueryId];
        if ($text) $data['text'] = $text;
        return $this->request('answerCallbackQuery', $data);
    }

    public function sendPhoto($chatId, $photo, $caption = '', $options = []) {
        $data = [
            'chat_id' => $chatId,
            'photo' => $photo,
        ];
        if ($caption) {
            $data['caption'] = $caption;
        }
        if (isset($options['parse_mode'])) {
            $data['parse_mode'] = $options['parse_mode'];
        }
        if (isset($options['inline_keyboard'])) {
            $data['reply_markup'] = json_encode(['inline_keyboard' => $options['inline_keyboard']]);
        }
        return $this->request('sendPhoto', $data);
    }

    public function getFileUrl($fileId) {
        $res = $this->request('getFile', ['file_id' => $fileId]);
        if (isset($res['ok']) && $res['ok'] && isset($res['result']['file_path'])) {
            return "https://api.telegram.org/file/bot{$this->token}/{$res['result']['file_path']}";
        }
        return null;
    }

    public function editMessageCaption($chatId, $messageId, $caption, $options = []) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];
        if (isset($options['inline_keyboard'])) {
            $data['reply_markup'] = json_encode(['inline_keyboard' => $options['inline_keyboard']]);
        }
        return $this->request('editMessageCaption', $data);
    }

    public function editMessageText($chatId, $messageId, $text, $options = []) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        if (isset($options['inline_keyboard'])) {
            $data['reply_markup'] = json_encode(['inline_keyboard' => $options['inline_keyboard']]);
        }
        return $this->request('editMessageText', $data);
    }

    public function editMessageReplyMarkup($chatId, $messageId, $replyMarkup = []) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode($replyMarkup)
        ];
        return $this->request('editMessageReplyMarkup', $data);
    }
}
