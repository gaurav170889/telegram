<?php

require_once __DIR__ . '/app/Config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', defined('DISPLAY_ERRORS') ? DISPLAY_ERRORS : '0');

$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir . '/sessions')) {
    mkdir($storageDir . '/sessions', 0777, true);
}
session_save_path($storageDir . '/sessions');

$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$https = $_SERVER['HTTPS'] ?? '';
$isSecure = !empty($https) && (!is_array($https)) && strtolower((string) $https) !== 'off';
$cookiePath = $base . '/';

if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    // PHP < 7.3 has no options array; append SameSite to path for compatibility.
    session_set_cookie_params(0, $cookiePath . '; samesite=Lax', '', $isSecure, true);
}
session_start();

// Simple Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Router;

$router = new Router();

// Load Routes
require_once __DIR__ . '/routes/web.php';
require_once __DIR__ . '/routes/api.php';
require_once __DIR__ . '/routes/telegram.php';

$router->dispatch();
