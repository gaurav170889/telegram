<?php
if (!function_exists('envv')) {
    function envv($key, $default = null) {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}

$localConfig = [];
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $loadedConfig = require $localConfigPath;
    if (is_array($loadedConfig)) {
        $localConfig = $loadedConfig;
    }
}

if (!function_exists('app_cfg')) {
    function app_cfg($key, $default = null) {
        global $localConfig;

        $envValue = getenv($key);
        if ($envValue !== false && $envValue !== '') {
            return $envValue;
        }

        if (isset($localConfig[$key]) && $localConfig[$key] !== '') {
            return $localConfig[$key];
        }

        return $default;
    }
}

if (!function_exists('decode_secret')) {
    function decode_secret($value) {
        if (!is_string($value)) {
            return $value;
        }

        if (strpos($value, 'base64:') === 0) {
            $decoded = base64_decode(substr($value, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $value;
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

define('APP_ENV', app_cfg('APP_ENV', 'local'));
define('DISPLAY_ERRORS', app_cfg('DISPLAY_ERRORS', APP_ENV === 'production' ? '0' : '1'));

define('DB_HOST', app_cfg('DB_HOST', '127.0.0.1'));
define('DB_NAME', app_cfg('DB_NAME', 'telegrambot'));
define('DB_USER', app_cfg('DB_USER', 'root'));
define('DB_PASS', decode_secret(app_cfg('DB_PASS', 'root')));

define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4');

define('BASE_API_URL', app_cfg('BASE_API_URL', 'https://api.telegram.org/bot'));
$appUrl = app_cfg('APP_URL', '');
if ($appUrl === '') {
    $appUrl = detect_app_url('http://localhost/telegram');
}
define('APP_URL', rtrim($appUrl, '/'));
define('TELEGRAM_VERIFY_SSL', app_cfg('TELEGRAM_VERIFY_SSL', APP_ENV === 'production' ? '1' : '0'));
?>