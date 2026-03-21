<?php

namespace App\Modules\Bookie\Models;

class Bookie {
    public $id;
    public $name;
    public $telegramBotToken;
    public $telegramBotUsername;
    public $telegramId;
    public $telegramVerificationCode;
    public $status;
    public $telegramVerified;
    public $onboardingCompleted;

    public function __construct($data) {
        $this->id = $chatId = $data['id'] ?? 0;
        $this->name = $data['name'] ?? '';
        $this->telegramBotToken = $data['telegram_bot_token'] ?? '';
        $this->telegramBotUsername = $data['telegram_bot_username'] ?? '';
        $this->telegramId = $data['telegram_id'] ?? null;
        $this->telegramVerificationCode = $data['telegram_verification_code'] ?? null;
        $this->status = $data['status'] ?? 'onboarding';
        $this->telegramVerified = (bool)($data['telegram_verified'] ?? false);
        $this->onboardingCompleted = (bool)($data['onboarding_completed'] ?? false);
    }
}
