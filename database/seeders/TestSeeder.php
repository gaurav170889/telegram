<?php

require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Core/Database.php';

use App\Core\Database;

$db = Database::getInstance();

// 1. Create a Bookie
$db->exec("INSERT INTO bookies (name, telegram_bot_token, status, onboarding_completed) VALUES ('Elite Bookie', '123456789:ABCDEF', 'onboarding', 0)");
$bookieId = $db->lastInsertId();

// 2. Create a User for this Bookie
$username = 'bookie1';
$password = password_hash('pass123', PASSWORD_BCRYPT);
$db->exec("INSERT INTO users (username, password, role, bookie_id) VALUES ('$username', '$password', 'bookie', $bookieId)");

// 3. Create a SuperAdmin
$admin_pass = password_hash('admin123', PASSWORD_BCRYPT);
$db->exec("INSERT INTO users (username, password, role) VALUES ('admin', '$admin_pass', 'superadmin')");

echo "Seed data created successfully!\n";
