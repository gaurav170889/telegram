<?php

namespace App\Modules\Wallet\Repositories;

use App\Core\Database;
use PDO;

class DepositRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createPendingDeposit($bookieId, $playerId, $amount, $gatewayId) {
        $stmt = $this->db->prepare("
            INSERT INTO deposits (bookie_id, player_id, amount, method_type, gateway_id, status) 
            VALUES (?, ?, ?, 'gateway', ?, 'pending')
        ");
        $stmt->execute([$bookieId, $playerId, $amount, $gatewayId]);
        return $this->db->lastInsertId();
    }

    public function attachReceipt($depositId, $receiptUrl) {
        $stmt = $this->db->prepare("UPDATE deposits SET receipt_url = ? WHERE id = ?");
        return $stmt->execute([$receiptUrl, $depositId]);
    }

    public function getPendingByBookie($bookieId) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT d.*, p.first_name, p.username 
            FROM deposits d 
            JOIN players p ON p.id = d.player_id 
            LEFT JOIN deposit_splits ds ON ds.deposit_id = d.id
            WHERE d.bookie_id = ? 
            AND (d.status NOT IN ('approved', 'rejected') OR ds.status = 'disputed')
            ORDER BY d.created_at ASC
        ");
        $stmt->execute([$bookieId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSplitsByDeposit($depositId) {
        $stmt = $this->db->prepare("
            SELECT ds.*, p.first_name as recipient_name 
            FROM deposit_splits ds 
            LEFT JOIN players p ON ds.recipient_player_id = p.id 
            WHERE ds.deposit_id = ? 
            ORDER BY ds.sequence_no ASC
        ");
        $stmt->execute([$depositId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($depositId, $status) {
        $stmt = $this->db->prepare("UPDATE deposits SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $depositId]);
    }

    public function getById($depositId) {
        $stmt = $this->db->prepare("SELECT * FROM deposits WHERE id = ?");
        $stmt->execute([$depositId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
