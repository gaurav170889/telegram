<?php

namespace App\Modules\Gateway\Repositories;

use App\Core\Database;
use PDO;

class GatewayRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM gateways ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($name, array $requiredConfig) {
        $stmt = $this->db->prepare("INSERT INTO gateways (name, required_config, is_active) VALUES (?, ?, 1)");
        return $stmt->execute([$name, json_encode($requiredConfig)]);
    }

    public function toggleStatus($id, $currentStatus) {
        $newStatus = ($currentStatus == 1) ? 0 : 1;
        $stmt = $this->db->prepare("UPDATE gateways SET is_active = ? WHERE id = ?");
        return $stmt->execute([$newStatus, $id]);
    }
}
