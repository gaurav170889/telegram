<?php

// Telegram Webhook routes
// {token} is a named parameter for the bot token
$router->add('POST', '/webhook/{token}', ['App\Modules\Telegram\Controllers\TelegramWebhookController', 'handle']);
