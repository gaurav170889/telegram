<?php

// Auth Routes
$router->add('GET', '/login', ['App\Modules\Auth\Controllers\AuthController', 'showLogin']);
$router->add('POST', '/login', ['App\Modules\Auth\Controllers\AuthController', 'login']);
$router->add('GET', '/logout', ['App\Modules\Auth\Controllers\AuthController', 'logout']);

// Onboarding Routes
$router->add('GET', '/onboarding', ['App\Modules\Bookie\Controllers\OnboardingController', 'show']);
$router->add('GET', '/onboarding/verify', ['App\Modules\Bookie\Controllers\OnboardingController', 'verifyTelegram']);
$router->add('POST', '/onboarding', ['App\Modules\Bookie\Controllers\OnboardingController', 'submit']);

// Dashboard Routes
$router->add('GET', '/dashboard', ['App\Modules\Bookie\Controllers\BookieDashboardController', 'index']);
$router->add('GET', '/bookie/receipt', ['App\Modules\Bookie\Controllers\BookieDashboardController', 'viewReceipt']);
$router->add('POST', '/bookie/deposit/approve', ['App\Modules\Bookie\Controllers\BookieDashboardController', 'handleApproveDeposit']);
$router->add('POST', '/bookie/deposit/reject', ['App\Modules\Bookie\Controllers\BookieDashboardController', 'handleRejectDeposit']);
$router->add('POST', '/bookie/deposit/reverse', ['App\Modules\Bookie\Controllers\BookieDashboardController', 'handleReverseDeposit']);
$router->add('GET', '/bookie/reports', ['App\Modules\Bookie\Controllers\BookieDashboardController', 'reports']);
$router->add('POST', '/bookie/withdrawal/manual_pay', ['App\Modules\Bookie\Controllers\BookieDashboardController', 'handleManualSettlement']);
$router->add('POST', '/bookie/dispute/resolve', ['App\Modules\Bookie\Controllers\BookieDashboardController', 'handleResolveDispute']);

$router->add('GET', '/admin', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'index']);
$router->add('GET', '/admin/bookies', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'bookies']);
$router->add('POST', '/admin/bookies/add', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'addBookie']);
$router->add('POST', '/admin/bookies/edit', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'editBookie']);
$router->add('POST', '/admin/bookies/toggle', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'toggleBookie']);
$router->add('POST', '/admin/bookies/refresh-webhook', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'refreshWebhook']);
$router->add('GET', '/admin/subscriptions', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'subscriptions']);
$router->add('POST', '/admin/subscriptions/update', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'updateSubscription']);

// Payment Gateway Management Routes
$router->add('GET', '/admin/gateways', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'gateways']);
$router->add('POST', '/admin/gateways/add', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'addGateway']);

// Manual Payments Routes
$router->add('GET', '/admin/payments', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'payments']);
$router->add('POST', '/admin/payments/add', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'addPayment']);

$router->add('GET', '/admin/transactions', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'transactions']);

$router->add('GET', '/admin/revenue', ['App\Modules\SuperAdmin\Controllers\SuperAdminController', 'revenue']);

$router->add('GET', '/', function() {
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $target = ($base === '/' || $base === '.') ? '/login' : $base . '/login';
    header("Location: $target");
    exit;
});
