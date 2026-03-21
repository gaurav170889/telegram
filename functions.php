<?php
require_once __DIR__ . '/config.php';

function tgRequest(string $method, array $data, string $token) {
  $url = BASE_API_URL . $token . '/' . $method;
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
  ]);

  $res = curl_exec($ch);
  $err = curl_error($ch);
  curl_close($ch);

  // Debug log
  file_put_contents(__DIR__ . "/tg_debug.log",
    date('c') . " TOKEN=" . substr($token, 0, 10) . "... METHOD={$method}\nREQ=" . json_encode($data) . "\nRES={$res}\nERR={$err}\n-----------------\n",
    FILE_APPEND
  );

  return $res;
}

function sendMessage($chatId, $text, $token, $keyboard = null) {
  $payload = [
    'chat_id' => $chatId,
    'text' => $text,
    'parse_mode' => 'HTML'
  ];

  if ($keyboard !== null) {
    $payload['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
  }

  return tgRequest('sendMessage', $payload, $token);
}

function editMessage($chatId, $messageId, $text, $token, $keyboard = null) {
  $payload = [
    'chat_id' => $chatId,
    'message_id' => $messageId,
    'text' => $text,
    'parse_mode' => 'HTML',
  ];
  if ($keyboard !== null) {
    $payload['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
  }
  return tgRequest('editMessageText', $payload, $token);
}

function answerCallbackQuery($callbackQueryId, $token, $text = null) {
  $payload = ['callback_query_id' => $callbackQueryId];
  if ($text) $payload['text'] = $text;
  return tgRequest('answerCallbackQuery', $payload, $token);
}

/** KEYBOARDS */
function mainMenuInlineKeyboard() {
  return [
    'inline_keyboard' => [
      [
        ['text' => '💰 Deposit', 'callback_data' => 'MENU_DEPOSIT'],
        ['text' => '📊 Balance', 'callback_data' => 'MENU_BALANCE'],
      ],
      [
        ['text' => '🏧 Withdraw', 'callback_data' => 'MENU_WITHDRAW'],
        ['text' => '📜 History', 'callback_data' => 'MENU_HISTORY'],
      ],
      [
        ['text' => '🧑‍💬 Support', 'callback_data' => 'MENU_SUPPORT']
      ]
    ]
  ];
}
