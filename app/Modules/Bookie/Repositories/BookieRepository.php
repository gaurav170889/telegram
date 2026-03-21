<?php

namespace App\Modules\Bookie\Repositories;

use App\Core\Database;
use App\Modules\Bookie\Models\Bookie;
use PDO;

class BookieRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findByToken($token) {
        $stmt = $this->db->prepare("SELECT * FROM bookies WHERE telegram_bot_token = ?");
        $stmt->execute([$token]);
        $data = $stmt->fetch();

        return $data ? new Bookie($data) : null;
    }

    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM bookies WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        return $data ? new Bookie($data) : null;
    }

    public function updateOnboardingWithGateways($id, $name, $validGateways) {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE bookies 
                SET name = ?, onboarding_completed = 1, status = 'active' 
                WHERE id = ?
            ");
            $stmt->execute([$name, $id]);

            $stmtClear = $this->db->prepare("DELETE FROM bookie_gateways WHERE bookie_id = ?");
            $stmtClear->execute([$id]);

            $stmtInsert = $this->db->prepare("INSERT INTO bookie_gateways (bookie_id, gateway_id, config_values, status) VALUES (?, ?, ?, 'active')");
            foreach ($validGateways as $gatewayId => $configFields) {
                foreach($configFields as $k => $v) {
                    $configFields[$k] = trim($v);
                }
                $stmtInsert->execute([$id, $gatewayId, json_encode($configFields)]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function getAllWithUsers() {
        $stmt = $this->db->query("
            SELECT b.*, u.username 
            FROM bookies b 
            LEFT JOIN users u ON u.bookie_id = b.id
            ORDER BY b.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createBookieWithContract($username, $hashedPassword, $botToken, $botUsername, $startDate, $endDate) {
        try {
            $this->db->beginTransaction();

            // 1. Insert Bookie
            $stmt1 = $this->db->prepare("INSERT INTO bookies (name, telegram_bot_token, telegram_bot_username, status) VALUES ('', ?, ?, 'onboarding')");
            $stmt1->execute([$botToken, $botUsername]);
            $bookieId = $this->db->lastInsertId();

            // 2. Insert User linking to the new bookie
            $stmt2 = $this->db->prepare("INSERT INTO users (username, password, role, bookie_id) VALUES (?, ?, 'bookie', ?)");
            $stmt2->execute([$username, $hashedPassword, $bookieId]);

            // 3. Insert Subscription Contract
            $stmt3 = $this->db->prepare("INSERT INTO subscriptions (bookie_id, start_date, end_date, status) VALUES (?, ?, ?, 'active')");
            $stmt3->execute([$bookieId, $startDate, $endDate]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function updateStatus($id, $newStatus) {
        $stmt = $this->db->prepare("UPDATE bookies SET status = ? WHERE id = ?");
        return $stmt->execute([$newStatus, $id]);
    }

    public function updateBookie($id, $botToken, $botUsername) {
        $stmt = $this->db->prepare("UPDATE bookies SET telegram_bot_token = ?, telegram_bot_username = ? WHERE id = ?");
        return $stmt->execute([$botToken, $botUsername, $id]);
    }

    public function updateTelegramVerified($id, $isVerified) {
        $stmt = $this->db->prepare("UPDATE bookies SET telegram_verified = ? WHERE id = ?");
        return $stmt->execute([$isVerified ? 1 : 0, $id]);
    }

    public function bindTelegramId($bookieId, $telegramId) {
        $stmt = $this->db->prepare("UPDATE bookies SET telegram_id = ?, telegram_verified = 1 WHERE id = ?");
        return $stmt->execute([$telegramId, $bookieId]);
    }
}
