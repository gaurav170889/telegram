<?php

namespace App\Modules\Auth\Models;

class User {
    public $id;
    public $username;
    public $role;
    public $bookieId;

    public function __construct($data) {
        $this->id = $data['id'] ?? 0;
        $this->username = $data['username'] ?? '';
        $this->role = $data['role'] ?? 'bookie';
        $this->bookieId = $data['bookie_id'] ?? null;
    }

    public function isSuperAdmin() {
        return $this->role === 'superadmin';
    }

    public function isBookie() {
        return $this->role === 'bookie';
    }
}
