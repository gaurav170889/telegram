<?php

namespace App\Middleware;

use App\Modules\Auth\Services\AuthService;
use App\Modules\Bookie\Services\BookieService;

class OnboardingMiddleware {
    public static function handle() {
        $authService = new AuthService();
        $user = $authService->getCurrentUser();

        if ($user && $user->role === 'bookie' && $user->bookieId) {
            $bookieService = new BookieService();
            $bookie = $bookieService->getBookieById($user->bookieId);
            
            if ($bookie && !$bookie->onboardingCompleted) {
                 if (strpos($_SERVER['REQUEST_URI'], '/onboarding') === false) {
                    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
                    $target = ($base === '/' || $base === '.') ? '/onboarding' : $base . '/onboarding';
                    header("Location: $target");
                    exit;
                 }
            }
        }
    }
}
