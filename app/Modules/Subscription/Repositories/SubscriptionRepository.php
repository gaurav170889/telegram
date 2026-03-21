<?php

namespace App\Modules\Subscription\Repositories;

use App\Core\Database;
use PDO;

class SubscriptionRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAllWithBookieDetails() {
        $stmt = $this->db->query("
            SELECT s.*, b.name AS bookie_name, u.username AS bookie_username, b.telegram_bot_token
            FROM subscriptions s
            JOIN bookies b ON s.bookie_id = b.id
            LEFT JOIN users u ON u.bookie_id = b.id
            ORDER BY s.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateDates($id, $startDate, $endDate) {
        $stmt = $this->db->prepare("UPDATE subscriptions SET start_date = ?, end_date = ? WHERE id = ?");
        return $stmt->execute([$startDate, $endDate, $id]);
    }
}
