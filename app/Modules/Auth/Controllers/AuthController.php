<?php

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Modules\Auth\Services\AuthService;

class AuthController extends Controller {
    public function showLogin() {
        $this->render('Auth.Views.login');
    }

    public function login() {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $authService = new AuthService();
        if ($authService->login($username, $password)) {
            $user = $authService->getCurrentUser();
            if ($user->role === 'superadmin') {
                $this->redirect('/admin');
            } else {
                $this->redirect('/dashboard');
            }
        } else {
            $this->render('Auth.Views.login', ['error' => 'Invalid credentials']);
        }
    }

    public function logout() {
        (new AuthService())->logout();
        $this->redirect('/login');
    }
}
