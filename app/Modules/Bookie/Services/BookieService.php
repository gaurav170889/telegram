<?php

namespace App\Modules\Bookie\Services;

use App\Modules\Bookie\Repositories\BookieRepository;
use App\Modules\Bookie\Models\Bookie;

class BookieService {
    private $repository;

    public function __construct() {
        $this->repository = new BookieRepository();
    }

    public function getBookieByToken($token) {
        return $this->repository->findByToken($token);
    }

    public function getBookieById($id) {
        return $this->repository->findById($id);
    }

    public function completeOnboarding($id, $data) {
        if (empty($data['name'])) {
            return false;
        }

        $validGateways = [];
        if (isset($data['gateways']) && is_array($data['gateways'])) {
            foreach ($data['gateways'] as $gatewayId => $configFields) {
                $hasValue = false;
                foreach ($configFields as $key => $value) {
                    if (trim($value) !== '') {
                        $hasValue = true;
                        break;
                    }
                }
                
                if ($hasValue) {
                    $validGateways[$gatewayId] = $configFields;
                }
            }
        }

        if (empty($validGateways)) {
            return false; // Require at least one configured payment gateway
        }

        return $this->repository->updateOnboardingWithGateways($id, $data['name'], $validGateways);
    }

    public function getAllBookiesWithUsers() {
        return $this->repository->getAllWithUsers();
    }

    public function addBookie($username, $password, $botToken, $botUsername, $startDate, $endDate) {
        // 1. Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // 2. Transact save
        $saved = $this->repository->createBookieWithContract($username, $hashedPassword, $botToken, $botUsername, $startDate, $endDate);

        if ($saved) {
            $this->registerWebhook($botToken);
        }

        return $saved;
    }

    public function registerWebhook($botToken) {
        if (!defined('APP_URL') || !defined('BASE_API_URL')) {
            return false;
        }

        $webhookUrl = rtrim(APP_URL, '/') . '/webhook/' . $botToken;
        $telegramApiUrl = BASE_API_URL . $botToken . '/setWebhook?url=' . urlencode($webhookUrl);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $telegramApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    public function toggleStatus($id, $currentStatus) {
        $newStatus = ($currentStatus === 'disabled') ? 'active' : 'disabled';
        return $this->repository->updateStatus($id, $newStatus);
    }

    public function editBookie($id, $botToken, $botUsername) {
        $saved = $this->repository->updateBookie($id, $botToken, $botUsername);
        if ($saved) {
            // Require re-verification if these sensitive config variables change
            $this->repository->updateTelegramVerified($id, false);
            // Also nullify the telegram_id to force reverse binding again
            \App\Core\Database::getInstance()->prepare("UPDATE bookies SET telegram_id = NULL WHERE id = ?")->execute([$id]);

            $this->registerWebhook($botToken);
        }
        return $saved;
    }

    public function bindTelegramId($bookieId, $telegramId) {
        return $this->repository->bindTelegramId($bookieId, $telegramId);
    }

    public function verifyTelegram($id) {
        return $this->repository->updateTelegramVerified($id, true);
    }
}
