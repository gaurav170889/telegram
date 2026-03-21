<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir . '/sessions')) {
    mkdir($storageDir . '/sessions', 0777, true);
}
session_save_path($storageDir . '/sessions');

$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
session_set_cookie_params(0, $base . '/');
session_start();

require_once __DIR__ . '/app/Config/database.php';

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
    } else {
        // Log autoloader failure if needed
        // error_log("Autoloader failed to find: $file");
    }
});

use App\Core\Router;

$router = new Router();

// Load Routes
require_once __DIR__ . '/routes/web.php';
require_once __DIR__ . '/routes/api.php';
require_once __DIR__ . '/routes/telegram.php';

$router->dispatch();
