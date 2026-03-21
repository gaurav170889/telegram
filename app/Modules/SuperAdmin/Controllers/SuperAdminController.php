<?php

namespace App\Modules\SuperAdmin\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;

class SuperAdminController extends Controller {
    public function index() {
        AuthMiddleware::handle('superadmin');
        $this->render('SuperAdmin.Views.dashboard');
    }

    public function bookies() {
        AuthMiddleware::handle('superadmin');
        
        $bookieService = new \App\Modules\Bookie\Services\BookieService();
        $bookiesList = $bookieService->getAllBookiesWithUsers();

        $this->render('SuperAdmin.Views.bookies', ['bookiesList' => $bookiesList]);
    }

    public function addBookie() {
        AuthMiddleware::handle('superadmin');

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $botToken = $_POST['bot_token'] ?? '';
        $botUsername = $_POST['bot_username'] ?? '';
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';

        if (empty($username) || empty($password) || empty($botToken) || empty($botUsername) || empty($startDate) || empty($endDate)) {
            $_SESSION['error'] = "All fields are required, including the Bot Username and Bot Token.";
            $this->redirect('/admin/bookies');
            return;
        }

        $bookieService = new \App\Modules\Bookie\Services\BookieService();
        $success = $bookieService->addBookie($username, $password, $botToken, $botUsername, $startDate, $endDate);

        if ($success) {
            $_SESSION['success'] = "Bookie successfully created with their active contract!";
        } else {
            $_SESSION['error'] = "Failed to create bookie. Username or Bot Token might already exist.";
        }

        $this->redirect('/admin/bookies');
    }

    public function editBookie() {
        AuthMiddleware::handle('superadmin');

        $id = $_POST['id'] ?? '';
        $botToken = $_POST['bot_token'] ?? '';
        $botUsername = $_POST['bot_username'] ?? '';

        if (empty($id) || empty($botToken) || empty($botUsername)) {
            $_SESSION['error'] = "All configured bot fields are required to update a Bookie.";
            $this->redirect('/admin/bookies');
            return;
        }

        $bookieService = new \App\Modules\Bookie\Services\BookieService();
        $success = $bookieService->editBookie($id, $botToken, $botUsername);

        if ($success) {
            $_SESSION['success'] = "Bookie successfully updated. They may need to re-verify their Telegram connection if it changed.";
        } else {
            $_SESSION['error'] = "Failed to update bookie.";
        }

        $this->redirect('/admin/bookies');
    }

    public function toggleBookie() {
        AuthMiddleware::handle('superadmin');
        
        $id = $_POST['id'] ?? null;
        $currentStatus = $_POST['current_status'] ?? null;

        if ($id && $currentStatus) {
            $bookieService = new \App\Modules\Bookie\Services\BookieService();
            $bookieService->toggleStatus($id, $currentStatus);
            $_SESSION['success'] = "Bookie status updated successfully.";
        }

        $this->redirect('/admin/bookies');
    }

    public function refreshWebhook() {
        AuthMiddleware::handle('superadmin');
        
        $id = $_POST['id'] ?? null;

        if ($id) {
            $bookieService = new \App\Modules\Bookie\Services\BookieService();
            $bookie = $bookieService->getBookieById($id);
            
            if ($bookie && $bookie->telegramBotToken) {
                $bookieService->registerWebhook($bookie->telegramBotToken);
                $_SESSION['success'] = "Webhook dynamically refreshed for " . htmlspecialchars($bookie->name ?: $bookie->username);
            } else {
                $_SESSION['error'] = "Could not find Bookie or Bot Token.";
            }
        }

        $this->redirect('/admin/bookies');
    }

    public function gateways() {
        AuthMiddleware::handle('superadmin');
        $gatewayService = new \App\Modules\Gateway\Services\GatewayService();
        $gatewaysList = $gatewayService->getAllGateways();
        $this->render('SuperAdmin.Views.gateways', ['gatewaysList' => $gatewaysList]);
    }

    public function addGateway() {
        AuthMiddleware::handle('superadmin');
        
        $name = $_POST['name'] ?? '';
        
        if (empty($name)) {
            $_SESSION['error'] = "Gateway Name is required.";
            $this->redirect('/admin/gateways');
            return;
        }

        $keysArray = ['account_id'];

        $gatewayService = new \App\Modules\Gateway\Services\GatewayService();
        $success = $gatewayService->addGateway($name, $keysArray);

        if ($success) {
            $_SESSION['success'] = "Payment Gateway '$name' created successfully!";
        } else {
            $_SESSION['error'] = "Failed to create gateway.";
        }

        $this->redirect('/admin/gateways');
    }

    public function subscriptions() {
        AuthMiddleware::handle('superadmin');
        $subscriptionService = new \App\Modules\Subscription\Services\SubscriptionService();
        $subscriptionsList = $subscriptionService->getAllSubscriptions();
        
        $this->render('SuperAdmin.Views.subscriptions', ['subscriptionsList' => $subscriptionsList]);
    }

    public function updateSubscription() {
        AuthMiddleware::handle('superadmin');
        
        $id = $_POST['id'] ?? null;
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;

        if ($id && $startDate && $endDate) {
            $subscriptionService = new \App\Modules\Subscription\Services\SubscriptionService();
            $success = $subscriptionService->updateSubscriptionDates($id, $startDate, $endDate);
            
            if ($success) {
                $_SESSION['success'] = "Contract dates updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update contract dates.";
            }
        } else {
            $_SESSION['error'] = "Invalid input.";
        }

        $this->redirect('/admin/subscriptions');
    }

    public function payments() {
        AuthMiddleware::handle('superadmin');
        
        $bookieService = new \App\Modules\Bookie\Services\BookieService();
        $bookiesList = $bookieService->getAllBookiesWithUsers(); // We need a simple list for the dropdown

        $paymentService = new \App\Modules\Subscription\Services\PaymentService();
        $paymentsList = $paymentService->getAllPayments();

        $this->render('SuperAdmin.Views.payments', [
            'bookiesList' => $bookiesList,
            'paymentsList' => $paymentsList
        ]);
    }

    public function addPayment() {
        AuthMiddleware::handle('superadmin');
        
        $bookieId = $_POST['bookie_id'] ?? null;
        $amount = $_POST['amount'] ?? null;
        $paymentDate = $_POST['payment_date'] ?? null;
        $notes = $_POST['notes'] ?? '';

        if (!$bookieId || !$amount || !$paymentDate) {
            $_SESSION['error'] = "Bookie, Amount, and Date are required fields.";
            $this->redirect('/admin/payments');
            return;
        }

        $paymentService = new \App\Modules\Subscription\Services\PaymentService();
        $success = $paymentService->logPayment($bookieId, $amount, $paymentDate, $notes);

        if ($success) {
            $_SESSION['success'] = "Payment logged successfully!";
        } else {
            $_SESSION['error'] = "Failed to log payment.";
        }

        $this->redirect('/admin/payments');
    }

    public function transactions() {
        AuthMiddleware::handle('superadmin');
        
        $ledgerRepo = new \App\Modules\Wallet\Repositories\LedgerRepository();
        $globalLedger = $ledgerRepo->getGlobalLedger(100);

        $withRepo = new \App\Modules\Wallet\Repositories\WithdrawalRepository();
        $globalQueue = $withRepo->getGlobalQueue();

        $this->render('SuperAdmin.Views.transactions', [
            'globalLedger' => $globalLedger,
            'globalQueue' => $globalQueue
        ]);
    }

    public function revenue() {
        AuthMiddleware::handle('superadmin');
        $this->render('SuperAdmin.Views.revenue');
    }
}
