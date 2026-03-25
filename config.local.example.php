<?php
return [
    'APP_ENV' => 'production',
    'DISPLAY_ERRORS' => '0',
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'telegrambot',
    'DB_USER' => 'root',
    // Use plain value or base64-encoded format: base64:cm9vdA==
    'DB_PASS' => 'base64:cm9vdA==',
    // If APP_URL is omitted, runtime auto-detection is used.
    'APP_URL' => 'https://your-domain.com/telegram',
    'BASE_API_URL' => 'https://api.telegram.org/bot',
    'TELEGRAM_VERIFY_SSL' => '1',
];
