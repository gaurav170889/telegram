<?php
require_once __DIR__ . '/config.php';

function tgRequest(string $method, array $data) {
  $ch = curl_init(BASE_API_URL . $method);
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

  if (!defined('APP_ENV') || APP_ENV !== 'production') {
    file_put_contents(__DIR__ . "/tg_debug.log",
      date('c') . " METHOD={$method}\nREQ=" . json_encode($data) . "\nRES={$res}\nERR={$err}\n-----------------\n",
      FILE_APPEND
    );
  }

  return $res;
}

function sendMessage($chatId, $text, $keyboard = null) {
  $payload = [
    'chat_id' => $chatId,
    'text' => $text,
    'parse_mode' => 'HTML'
  ];

  if ($keyboard !== null) {
    $payload['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
  }

  return tgRequest('sendMessage', $payload);
}

function removeInlineKeyboard($chatId, $messageId) {
  return tgRequest('editMessageReplyMarkup', [
    'chat_id' => $chatId,
    'message_id' => $messageId,
    'reply_markup' => json_encode(['inline_keyboard' => []])
  ]);
}

/**
 * REPLY KEYBOARDS (buttons under typing area)
 */
function mainMenuKeyboard() {
  return [
    'keyboard' => [
      ['Deposit', 'Check balance'],
      ['Withdraw', 'Talk to support'],
      ['Cancel']
    ],
    'resize_keyboard' => true,
    'one_time_keyboard' => false,
    'is_persistent' => true
  ];
}

function depositAmountKeyboard() {
  return [
    'keyboard' => [
      ['Talk to support'],
      ['Cancel']
    ],
    'resize_keyboard' => true,
    'one_time_keyboard' => false,
    'is_persistent' => true
  ];
}

function depositMethodKeyboard() {
  return [
    'keyboard' => [
      ['CashApp @2024lion'],
      ['CashApp Sbbkfrenchbulls'],
      ['CashApp Sdarickrod'],
      ['CashApp Sflipmz01'],
      ['Talk to support'],
      ['Cancel']
    ],
    'resize_keyboard' => true,
    'one_time_keyboard' => false,
    'is_persistent' => true
  ];
}

function supportCancelKeyboard() {
  return [
    'keyboard' => [
      ['Talk to support'],
      ['Cancel']
    ],
    'resize_keyboard' => true,
    'one_time_keyboard' => false,
    'is_persistent' => true
  ];
}

/** INLINE MAIN MENU */
function mainMenuInlineKeyboard() {
  return [
    'inline_keyboard' => [
      [
        ['text' => '💰 Deposit', 'callback_data' => 'MENU_DEPOSIT'],
        ['text' => '📊 Check balance', 'callback_data' => 'MENU_BALANCE'],
      ],
      [
        ['text' => '🏧 Withdraw', 'callback_data' => 'MENU_WITHDRAW'],
        ['text' => '🧑‍💬 Talk to support', 'callback_data' => 'MENU_SUPPORT'],
      ],
      [
        ['text' => '❌ Cancel', 'callback_data' => 'MENU_CANCEL']
      ]
    ]
  ];
}


function supportCancelInlineKeyboard() {
  return [
    'inline_keyboard' => [
      [
        ['text' => '🧑‍💬 Talk to support', 'callback_data' => 'MENU_SUPPORT'],
        ['text' => '❌ Cancel', 'callback_data' => 'MENU_CANCEL']
      ]
    ]
  ];
}

function depositAmountInlineKeyboard() {
  return [
    'inline_keyboard' => [
      [
        ['text' => '🧑‍💬 Talk to support', 'callback_data' => 'MENU_SUPPORT'],
        ['text' => '❌ Cancel', 'callback_data' => 'MENU_CANCEL']
      ]
    ]
  ];
}

function depositMethodInlineKeyboard() {
  return [
    'inline_keyboard' => [
      [['text' => 'CashApp @2024lion',        'callback_data' => 'M_CASHAPP_2024LION']],
      [['text' => 'CashApp Sbbkfrenchbulls',  'callback_data' => 'M_CASHAPP_BBK']],
      [['text' => 'CashApp Sdarickrod',       'callback_data' => 'M_CASHAPP_DARICK']],
      [['text' => 'CashApp Sflipmz01',        'callback_data' => 'M_CASHAPP_FLIP']],
      [
        ['text' => '🧑‍💬 Talk to support', 'callback_data' => 'MENU_SUPPORT'],
        ['text' => '❌ Cancel', 'callback_data' => 'MENU_CANCEL']
      ]
    ]
  ];
}

function editMessage($chatId, $messageId, $text, $keyboard = null) {
  $payload = [
    'chat_id' => $chatId,
    'message_id' => $messageId,
    'text' => $text,
    'parse_mode' => 'HTML',
  ];
  if ($keyboard !== null) {
    $payload['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
  }
  return tgRequest('editMessageText', $payload);
}

function answerCallbackQuery($callbackQueryId, $text = null) {
  $payload = ['callback_query_id' => $callbackQueryId];
  if ($text) $payload['text'] = $text;
  return tgRequest('answerCallbackQuery', $payload);
}
?>