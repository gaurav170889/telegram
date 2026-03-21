<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Repositories\AuthRepository;
use App\Modules\Auth\Models\User;
use App\Core\Database;

class AuthService {
    private $repository;

    public function __construct() {
        $this->repository = new AuthRepository();
    }

    public function login($username, $password) {
        $stmt = Database::getInstance()->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user_data = $stmt->fetch();

        if ($user_data && password_verify($password, $user_data['password'])) {
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['role'] = $user_data['role'];
            $_SESSION['bookie_id'] = $user_data['bookie_id'];
            return true;
        }
        return false;
    }

    public function logout() {
        session_destroy();
    }

    public function getCurrentUser() {

        if (isset($_SESSION['user_id'])) {
            return new User([
                'id' => $_SESSION['user_id'],
                'username' => '', // fetch if needed
                'role' => $_SESSION['role'],
                'bookie_id' => $_SESSION['bookie_id']
            ]);
        }
        return null;
    }
}
