<?php

namespace App\Modules\Telegram\Repositories;

use App\Core\Database;
use PDO;

class PlayerRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function ensurePlayer($data) {
        $stmt = $this->db->prepare("SELECT id FROM players WHERE telegram_id = ?");
        $stmt->execute([$data['id']]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            $stmt = $this->db->prepare("
                INSERT INTO players (telegram_id, username, first_name, last_name) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['id'],
                $data['username'] ?? null,
                $data['first_name'] ?? null,
                $data['last_name'] ?? null
            ]);
            $id = $this->db->lastInsertId();
        }
        return $id;
    }

    public function ensureMapping($bookieId, $playerId) {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO bookie_players (bookie_id, player_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$bookieId, $playerId]);

        // Fetch and return the ID (whether just inserted or already existed)
        $stmt2 = $this->db->prepare("SELECT id FROM bookie_players WHERE bookie_id = ? AND player_id = ?");
        $stmt2->execute([$bookieId, $playerId]);
        return $stmt2->fetchColumn();
    }

    public function getState($bookieId, $playerId) {
        $stmt = $this->db->prepare("
            SELECT current_state FROM bookie_players 
            WHERE bookie_id = ? AND player_id = ?
        ");
        $stmt->execute([$bookieId, $playerId]);
        return $stmt->fetchColumn() ?: 'MAIN_MENU';
    }

    public function setState($bookieId, $playerId, $state, $payload = null) {
        $stmt = $this->db->prepare("
            UPDATE bookie_players 
            SET current_state = ?, temp_payload = ? 
            WHERE bookie_id = ? AND player_id = ?
        ");
        $stmt->execute([$state, json_encode($payload), $bookieId, $playerId]);
    }
    
    public function getPayload($bookieId, $playerId) {
        $stmt = $this->db->prepare("SELECT temp_payload FROM bookie_players WHERE bookie_id = ? AND player_id = ?");
        $stmt->execute([$bookieId, $playerId]);
        $row = $stmt->fetchColumn();
        return $row ? json_decode($row, true) : null;
    }
}
