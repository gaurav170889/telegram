<?php

namespace App\Modules\Wallet\Repositories;

use App\Core\Database;
use PDO;

class WalletRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getWalletByBookiePlayerId($bookiePlayerId) {
        $stmt = $this->db->prepare("SELECT * FROM wallets WHERE bookie_player_id = ?");
        $stmt->execute([$bookiePlayerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getWalletByPlayerId($playerId, $bookieId) {
        $stmt = $this->db->prepare("
            SELECT w.* FROM wallets w
            JOIN bookie_players bp ON w.bookie_player_id = bp.id
            WHERE bp.player_id = ? AND bp.bookie_id = ?
        ");
        $stmt->execute([$playerId, $bookieId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getWalletById($id) {
        $stmt = $this->db->prepare("SELECT * FROM wallets WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createWallet($bookiePlayerId) {
        $stmt = $this->db->prepare("
            INSERT INTO wallets (bookie_player_id, current_balance, available_balance, locked_balance) 
            VALUES (?, 0.00, 0.00, 0.00)
        ");
        $stmt->execute([$bookiePlayerId]);
        return $this->db->lastInsertId();
    }

    public function updateBalances($walletId, $current, $available, $locked) {
        $stmt = $this->db->prepare("
            UPDATE wallets 
            SET current_balance = ?, available_balance = ?, locked_balance = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$current, $available, $locked, $walletId]);
    }
}
