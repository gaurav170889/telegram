<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'telegrambot');
define('DB_USER', 'root');
define('DB_PASS', 'root');

define('DB_DSN', "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4");

// Future: BOT_TOKEN will be dynamic based on the bookie.
// This acts as a default or for the SuperAdmin bot if needed.
define('BASE_API_URL', 'https://api.telegram.org/bot');
define('APP_URL', 'https://phillis-unverminous-dodie.ngrok-free.dev/telegram');
?>
