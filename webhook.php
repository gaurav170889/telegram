<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram.php';

$raw = file_get_contents("php://input");
$update = json_decode($raw, true);

if (!$update) { http_response_code(200); exit; }

// 1) Idempotency: store update_id once
$updateId = $update['update_id'] ?? null;
if ($updateId) {
  try {
    $stmt = db()->prepare("INSERT INTO bot_updates (update_id, telegram_id, raw_json) VALUES (?, ?, ?)");
    $telegramIdGuess = $update['message']['from']['id'] ?? ($update['callback_query']['from']['id'] ?? null);
    $stmt->execute([$updateId, $telegramIdGuess, $raw]);
  } catch (Exception $e) {
    // Duplicate update_id => already processed
    http_response_code(200);
    exit;
  }
}

$callback = $update['callback_query'] ?? null;

if ($callback) {
  $data = $callback['data'] ?? '';
  $cbId = $callback['id'];
  $chatId = $callback['message']['chat']['id'];
  $messageId = $callback['message']['message_id'];
  $telegramId = $callback['from']['id'];

  answerCallbackQuery($cbId);

  // ensure user (minimum fields)
  ensureUser(['id' => $telegramId]);

  // always save active msg so later typing edits same message
  setActiveMessage($telegramId, $chatId, $messageId);

  if ($data === 'MENU_CANCEL') {
    cancelOpenSessions($telegramId);
    setState($telegramId, 'MAIN_MENU');
    editMessage($chatId, $messageId, "Canceled.\n\nWelcome to AJRo, What would you like to do ?", mainMenuInlineKeyboard());
    http_response_code(200); exit;
  }

  if ($data === 'MENU_SUPPORT') {
    editMessage($chatId, $messageId, "Support: Please type your issue. An agent will contact you.", supportCancelInlineKeyboard());
    http_response_code(200); exit;
  }

  if($data === 'MENU_DEPOSIT') {
      startSession($telegramId, 'DEPOSIT');
      setState($telegramId, 'DEPOSIT_AMOUNT');
    
      $txt = "Welcome to AJRo, What would you like to do ?\n\n\"Deposit\"\n\nHow much is your deposit ?";
      $res = editMessage($chatId, $messageId, $txt, supportCancelInlineKeyboard());
    
      // Fallback: if edit fails for any reason, send new message
      $j = json_decode($res, true);
      if (empty($j['ok'])) {
        $res2 = sendMessage($chatId, $txt, supportCancelInlineKeyboard());
        $j2 = json_decode($res2, true);
        if (!empty($j2['ok']) && !empty($j2['result']['message_id'])) {
          setActiveMessage($telegramId, $chatId, $j2['result']['message_id']);
        }
      }
    
      http_response_code(200); exit;
    }

  // Deposit method selection
  if (strpos($data, 'M_CASHAPP_') === 0) {
      $methodMap = [
        'M_CASHAPP_2024LION' => 'CashApp @2024lion',
        'M_CASHAPP_BBK'      => 'CashApp Sbbkfrenchbulls',
        'M_CASHAPP_DARICK'   => 'CashApp Sdarickrod',
        'M_CASHAPP_FLIP'     => 'CashApp Sflipmz01',
      ];
      $method = $methodMap[$data] ?? null;
      if (!$method) { http_response_code(200); exit; }
    
      $session = getOpenSession($telegramId);
      if (!$session || empty($session['amount'])) {
        setState($telegramId, 'DEPOSIT_AMOUNT');
        editMessage($chatId, $messageId, "How much is your deposit ?", supportCancelInlineKeyboard());
        http_response_code(200); exit;
      }

        updateOpenSession($telegramId, ['method' => $method]);
        setState($telegramId, 'DEPOSIT_INSTRUCTIONS');

      // 1) Remove the method buttons from this message
      removeInlineKeyboard($chatId, $messageId);

     // 2) Edit the same message to show selected option in quotes
    $txt = "How are you depositing ?\n\n\"{$method}\"";
     editMessage($chatId, $messageId, $txt, null);

     // 3) Send NEW message with instructions (no method buttons)
     $amount = $session['amount'];
     $instructions = buildDepositInstructions($amount, $method);
    
     $final = "Please copy/paste the address below for your \${$amount} payment.\n"
         . "Send exact amounts to each location.\n\n"
         . "Please attach this as the note and DO NOT enter text as the note.\n\n"
         . $instructions;

     sendMessage($chatId, $final, supportCancelInlineKeyboard());

     http_response_code(200); exit;
    }

  http_response_code(200); exit;
}

// 2) Extract message
$message = $update['message'] ?? null;
if (!$message) { http_response_code(200); exit; }

$chatId = $message['chat']['id'];
$from = $message['from'];
$telegramId = $from['id'];
$text = trim($message['text'] ?? '');

ensureUser($from);

// Commands
if ($text === '/start') {
  setState($telegramId, 'MAIN_MENU');

  $res = sendMessage($chatId, "Welcome to AJRo, What would you like to do ?", mainMenuInlineKeyboard());
  $j = json_decode($res, true);
  if (!empty($j['ok']) && !empty($j['result']['message_id'])) {
    setActiveMessage($telegramId, $chatId, $j['result']['message_id']);
  }

  http_response_code(200); exit;
}

// Global buttons
if (strcasecmp($text, 'Cancel') === 0) {
  cancelOpenSessions($telegramId);
  setState($telegramId, 'MAIN_MENU');
  sendMessage($chatId, "Canceled. Welcome to AJRo, What would you like to do ?", mainMenuKeyboard());
  http_response_code(200); exit;
}

if (strcasecmp($text, 'Talk to support') === 0) {
  // You can forward to admin channel, or show support info
  sendMessage($chatId, "Support: Please describe your issue. An agent will contact you.");
  http_response_code(200); exit;
}

$state = getState($telegramId);

// State routing
switch ($state) {
  case 'MAIN_MENU':
    if (strcasecmp($text, 'Deposit') === 0) {
      startSession($telegramId, 'DEPOSIT');
      setState($telegramId, 'DEPOSIT_AMOUNT');
      sendMessage($chatId, "How much is your deposit ?", depositAmountKeyboard());
    } elseif (strcasecmp($text, 'Check balance') === 0) {
      // Placeholder
      sendMessage($chatId, "Balance feature not connected yet.", mainMenuKeyboard());
    } elseif (strcasecmp($text, 'Withdraw') === 0) {
      sendMessage($chatId, "Withdraw feature not connected yet.", mainMenuKeyboard());
    } else {
      sendMessage($chatId, "Welcome to AJRo, What would you like to do ?", mainMenuKeyboard());
    }
    break;

 case 'DEPOSIT_AMOUNT':
  $amount = normalizeAmount($text);

  if ($amount === null || $amount <= 0) {
    // keep same message (active) showing amount prompt
    $active = getActiveMessage($telegramId);
    if (!empty($active['active_chat_id']) && !empty($active['active_message_id'])) {
      editMessage($active['active_chat_id'], $active['active_message_id'],
        "Welcome to AJRo, What would you like to do ?\n\n\"Deposit\"\n\nHow much is your deposit ?",
        supportCancelInlineKeyboard()
      );
    } else {
      sendMessage($chatId, "How much is your deposit ?", supportCancelInlineKeyboard());
    }
    break;
  }

  // Save amount
  updateOpenSession($telegramId, ['amount' => $amount]);
  setState($telegramId, 'DEPOSIT_METHOD');

  // 1) Remove Talk to support / Cancel from the amount-question message
  $active = getActiveMessage($telegramId);
  if (!empty($active['active_chat_id']) && !empty($active['active_message_id'])) {
    removeInlineKeyboard($active['active_chat_id'], $active['active_message_id']);
  }

  // 2) Send NEW message for selecting deposit method
  $res = sendMessage($chatId, "How are you depositing ?", depositMethodInlineKeyboard());
  $j = json_decode($res, true);
  if (!empty($j['ok']) && !empty($j['result']['message_id'])) {
    // Now THIS becomes the active message for method selection
    setActiveMessage($telegramId, $chatId, $j['result']['message_id']);
  }

  break;

  case 'DEPOSIT_METHOD':
    $method = parseMethod($text);
    if (!$method) {
      sendMessage($chatId, "Please select one of the deposit methods using the buttons.", depositMethodKeyboard());
      break;
    }
    updateOpenSession($telegramId, ['method' => $method]);
    setState($telegramId, 'DEPOSIT_INSTRUCTIONS');

    $session = getOpenSession($telegramId);
    $amount = $session['amount'];

    $instructions = buildDepositInstructions($amount, $method);

    sendMessage(
      $chatId,
      "how are you doong  deposit ?\n\"{$method}\"\n\n" . $instructions,
      supportCancelKeyboard()
    );
    break;

  case 'DEPOSIT_INSTRUCTIONS':
    // If user types anything here, you can keep them here or return to menu
    sendMessage($chatId, "If you need help, tap Talk to support or Cancel.", supportCancelKeyboard());
    break;

  default:
    setState($telegramId, 'MAIN_MENU');
    sendMessage($chatId, "Welcome to AJRo, What would you like to do ?", mainMenuKeyboard());
}

http_response_code(200);
exit;


// ---------- Functions ----------

function ensureUser(array $from) {
  $pdo = db();
  $telegramId = $from['id'];

  $stmt = $pdo->prepare("SELECT telegram_id FROM bot_users WHERE telegram_id=?");
  $stmt->execute([$telegramId]);
  $exists = $stmt->fetchColumn();

  if (!$exists) {
    $stmt = $pdo->prepare("INSERT INTO bot_users (telegram_id, username, first_name, last_name, current_state) VALUES (?,?,?,?, 'MAIN_MENU')");
    $stmt->execute([
      $telegramId,
      $from['username'] ?? null,
      $from['first_name'] ?? null,
      $from['last_name'] ?? null
    ]);
  }
}

function getState($telegramId) {
  $stmt = db()->prepare("SELECT current_state FROM bot_users WHERE telegram_id=?");
  $stmt->execute([$telegramId]);
  return $stmt->fetchColumn() ?: 'MAIN_MENU';
}

function setState($telegramId, $state) {
  $stmt = db()->prepare("UPDATE bot_users SET current_state=? WHERE telegram_id=?");
  $stmt->execute([$state, $telegramId]);
}

function startSession($telegramId, $type) {
  cancelOpenSessions($telegramId); // optional: allow only one OPEN session
  $stmt = db()->prepare("INSERT INTO bot_sessions (telegram_id, type, status) VALUES (?, ?, 'OPEN')");
  $stmt->execute([$telegramId, $type]);
}

function cancelOpenSessions($telegramId) {
  $stmt = db()->prepare("UPDATE bot_sessions SET status='CANCELED' WHERE telegram_id=? AND status='OPEN'");
  $stmt->execute([$telegramId]);
}

function getOpenSession($telegramId) {
  $stmt = db()->prepare("SELECT * FROM bot_sessions WHERE telegram_id=? AND status='OPEN' ORDER BY id DESC LIMIT 1");
  $stmt->execute([$telegramId]);
  return $stmt->fetch();
}

function updateOpenSession($telegramId, array $fields) {
  $session = getOpenSession($telegramId);
  if (!$session) return;

  $setParts = [];
  $vals = [];
  foreach ($fields as $k => $v) {
    $setParts[] = "$k=?";
    $vals[] = $v;
  }
  $vals[] = $session['id'];

  $sql = "UPDATE bot_sessions SET " . implode(',', $setParts) . " WHERE id=?";
  $stmt = db()->prepare($sql);
  $stmt->execute($vals);
}


function setActiveMessage($telegramId, $chatId, $messageId) {
  $stmt = db()->prepare("UPDATE bot_users SET active_chat_id=?, active_message_id=? WHERE telegram_id=?");
  $stmt->execute([$chatId, $messageId, $telegramId]);
}

function getActiveMessage($telegramId) {
  $stmt = db()->prepare("SELECT active_chat_id, active_message_id FROM bot_users WHERE telegram_id=?");
  $stmt->execute([$telegramId]);
  return $stmt->fetch() ?: ['active_chat_id'=>null,'active_message_id'=>null];
}

function normalizeAmount(string $text) : ?float {
  $t = preg_replace('/[^\d.]/', '', $text);
  if ($t === '' || !is_numeric($t)) return null;
  return (float)$t;
}

function parseMethod(string $text) : ?string {
  $allowed = [
    'CashApp @2024lion',
    'CashApp Sbbkfrenchbulls',
    'CashApp Sdarickrod',
    'CashApp Sflipmz01',
  ];
  foreach ($allowed as $a) {
    if (strcasecmp($text, $a) === 0) return $a;
  }
  return null;
}

function buildDepositInstructions($amount, $method) : string {
  // You can make this dynamic later: rules table in DB.
  // For your example: $5000 split into $3000 + $2000
  if ((int)$amount === 5000 && $method === 'CashApp Sflipmz01') {
    return "Please copy/paste the address below for your \${$amount} payment.\n".
           "Send exact amounts to each location.\n\n".
           "Please attach this as the note and DO NOT enter text as the note.\n\n".
           "\$Payway33 \$3000\n".
           "\$bbkfrenchbulls \$2000";
  }

  // Default generic message (until you define mapping rules)
  return "Please send your exact deposit amount: \${$amount}.\n".
         "Method selected: {$method}\n".
         "If you need the split addresses for this amount, contact support.";
}

?>