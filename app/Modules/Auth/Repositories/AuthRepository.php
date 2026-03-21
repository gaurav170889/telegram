<?php

namespace App\Modules\Auth\Repositories;

use App\Core\Database;
use App\Modules\Auth\Models\User;
use PDO;

class AuthRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $data = $stmt->fetch();

        return $data ? new User($data) : null;
    }

    public function createBookieUser($username, $password, $bookieId) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("
            INSERT INTO users (username, password, role, bookie_id) 
            VALUES (?, ?, 'bookie', ?)
        ");
        return $stmt->execute([$username, $hashed, $bookieId]);
    }
}
