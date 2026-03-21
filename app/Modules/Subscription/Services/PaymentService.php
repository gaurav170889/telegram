<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Subscription\Repositories\ManualPaymentRepository;

class PaymentService {
    private $repository;

    public function __construct() {
        $this->repository = new ManualPaymentRepository();
    }

    public function logPayment($bookieId, $amount, $paymentDate, $notes) {
        if (empty($bookieId) || empty($amount) || empty($paymentDate)) {
            return false;
        }
        
        // Ensure amount is float/decimal format
        $amount = (float) $amount;
        
        return $this->repository->addPayment($bookieId, $amount, $paymentDate, $notes);
    }

    public function getAllPayments() {
        return $this->repository->getAllPayments();
    }
}
