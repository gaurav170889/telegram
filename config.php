<?php
if (!function_exists('envv')) {
    function envv($key, $default = null) {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}

if (!function_exists('detect_app_url')) {
    function detect_app_url($default = 'http://localhost/telegram') {
        if (PHP_SAPI === 'cli' || empty($_SERVER)) {
            return rtrim($default, '/');
        }

        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if ($forwardedProto !== '') {
            $protoParts = explode(',', $forwardedProto);
            $scheme = strtolower(trim($protoParts[0])) === 'https' ? 'https' : 'http';
        } else {
            $https = $_SERVER['HTTPS'] ?? '';
            $scheme = (!empty($https) && strtolower((string) $https) !== 'off') ? 'https' : 'http';
        }

        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '') {
            $hostParts = explode(',', $host);
            $host = trim($hostParts[0]);
        }

        if ($host === '') {
            return rtrim($default, '/');
        }

        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        if ($scriptDir === '/' || $scriptDir === '.') {
            $scriptDir = '';
        }

        return rtrim($scheme . '://' . $host . $scriptDir, '/');
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
$appUrl = envv('APP_URL', '');
if ($appUrl === '') {
    $appUrl = detect_app_url('http://localhost/telegram');
}
define('APP_URL', rtrim($appUrl, '/'));
define('TELEGRAM_VERIFY_SSL', envv('TELEGRAM_VERIFY_SSL', APP_ENV === 'production' ? '1' : '0'));
?>