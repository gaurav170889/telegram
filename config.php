<?php
if (!function_exists('envv')) {
    function envv($key, $default = null) {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}

define('APP_ENV', envv('APP_ENV', 'local'));
define('DISPLAY_ERRORS', envv('DISPLAY_ERRORS', APP_ENV === 'production' ? '0' : '1'));

define('DB_HOST', envv('DB_HOST', '127.0.0.1'));
define('DB_NAME', envv('DB_NAME', 'telegrambot'));
define('DB_USER', envv('DB_USER', 'root'));
define('DB_PASS', envv('DB_PASS', 'root'));

define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4');

define('BASE_API_URL', envv('BASE_API_URL', 'https://api.telegram.org/bot'));
define('APP_URL', rtrim(envv('APP_URL', 'http://localhost/telegram'), '/'));
define('TELEGRAM_VERIFY_SSL', envv('TELEGRAM_VERIFY_SSL', APP_ENV === 'production' ? '1' : '0'));
?>