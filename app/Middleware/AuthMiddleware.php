<?php

namespace App\Middleware;

use App\Modules\Auth\Services\AuthService;

class AuthMiddleware {
    public static function handle($role = null) {
        $authService = new AuthService();
        $user = $authService->getCurrentUser();

        if (!$user) {
            $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            $loginPath = ($base === '/' || $base === '.') ? '/login' : $base . '/login';
            header("Location: $loginPath");
            exit;
        }

        if ($role && $user->role !== $role && $user->role !== 'superadmin') {
            http_response_code(403);
            die('Unauthorized');
        }
    }
}
