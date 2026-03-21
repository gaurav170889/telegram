<?php

namespace App\Modules\Bookie\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Bookie\Services\BookieService;
use App\Modules\Gateway\Services\GatewayService;

class OnboardingController extends Controller {
    public function show() {
        AuthMiddleware::handle('bookie');
        
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        $bookieService = new BookieService();
        $bookie = $bookieService->getBookieById($user->bookieId);

        if ($bookie->onboardingCompleted) {
            $this->redirect('/dashboard');
        }

        $gatewayService = new GatewayService();
        $gateways = $gatewayService->getAllGateways(); // Fetch active gateways

        // Reverse Bind OTP Generation
        if (!$bookie->telegramVerified && empty($bookie->telegramVerificationCode)) {
            $code = sprintf("%06d", mt_rand(100000, 999999));
            \App\Core\Database::getInstance()->prepare("UPDATE bookies SET telegram_verification_code = ? WHERE id = ?")->execute([$code, $bookie->id]);
            $bookie->telegramVerificationCode = $code;
        }

        $this->render('Bookie.Views.onboarding', [
            'bookie' => $bookie, 
            'gateways' => $gateways,
            'isVerified' => (bool)$bookie->telegramVerified
        ]);
    }

    public function verifyTelegram() {
        AuthMiddleware::handle('bookie');
        
        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        $bookieService = new BookieService();
        $bookie = $bookieService->getBookieById($user->bookieId);

        if ($bookie->telegramVerified) {
            $_SESSION['success'] = "Telegram connection verified successfully!";
            $this->redirect('/onboarding');
            return;
        }

        $_SESSION['error'] = "Not verified yet! Please message the bot your 6-digit code first.";
        
        $this->redirect('/onboarding');
    }

    public function submit() {
        AuthMiddleware::handle('bookie');

        $authService = new AuthService();
        $user = $authService->getCurrentUser();
        $bookieService = new BookieService();

        $data = [
            'name' => $_POST['name'] ?? '',
            'gateways' => $_POST['gateways'] ?? []
        ];

        if ($bookieService->completeOnboarding($user->bookieId, $data)) {
            $this->redirect('/dashboard');
        } else {
            $bookie = $bookieService->getBookieById($user->bookieId);
            $gatewayService = new GatewayService();
            $gateways = $gatewayService->getAllGateways();
            
            $this->render('Bookie.Views.onboarding', [
                'bookie' => $bookie,
                'gateways' => $gateways,
                'isVerified' => (bool)$bookie->telegramVerified,
                'error' => 'Please provide a valid business name and configure at least one payment gateway.'
            ]);
        }
    }
}
