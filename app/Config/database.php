<?php

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'telegrambot');
define('DB_USER', 'root');
define('DB_PASS', 'root');

define('DB_DSN', "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4");

define('BASE_API_URL', 'https://api.telegram.org/bot');
define('APP_URL', 'https://your-ngrok-or-domain.com/telegram');
