<?php

namespace App\Modules\Wallet\Repositories;

use App\Core\Database;
use PDO;

class WithdrawalRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createWithdrawal($bookieId, $playerId, $amount, $methodId) {
        $stmt = $this->db->prepare("
            INSERT INTO withdrawals (bookie_id, player_id, amount, amount_requested, amount_remaining, method_id, status)
            VALUES (?, ?, ?, ?, ?, ?, 'queued')
        ");
        $stmt->execute([$bookieId, $playerId, $amount, $amount, $amount, $methodId]);
        return $this->db->lastInsertId();
    }

    public function getQueuedByBookie($bookieId) {
        $stmt = $this->db->prepare("
            SELECT w.*, p.first_name, p.username, pm.method_name, pm.handle
            FROM withdrawals w 
            JOIN players p ON p.id = w.player_id 
            JOIN payout_methods pm ON pm.id = w.method_id 
            WHERE w.bookie_id = ? AND w.status IN ('queued', 'partially_paid', 'partial_dispute') 
            ORDER BY w.created_at ASC
        ");
        $stmt->execute([$bookieId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGlobalQueue() {
        $stmt = $this->db->query("
            SELECT w.*, p.first_name, p.username, pm.method_name, pm.handle, b.name as bookie_name
            FROM withdrawals w 
            JOIN players p ON p.id = w.player_id 
            JOIN payout_methods pm ON pm.id = w.method_id 
            JOIN bookies b ON b.id = w.bookie_id
            WHERE w.status IN ('queued', 'partially_paid', 'partial_dispute') 
            ORDER BY w.created_at ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Payout methods
    public function getPlayerMethods($playerId) {
        $stmt = $this->db->prepare("SELECT * FROM payout_methods WHERE player_id = ?");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addPlayerMethod($playerId, $methodName, $handle) {
        $stmt = $this->db->prepare("INSERT INTO payout_methods (player_id, method_name, handle) VALUES (?, ?, ?)");
        $stmt->execute([$playerId, $methodName, $handle]);
        return $this->db->lastInsertId();
    }
}
