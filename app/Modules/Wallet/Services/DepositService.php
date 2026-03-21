<?php

namespace App\Modules\Wallet\Services;

use App\Modules\Wallet\Repositories\DepositRepository;

class DepositService {
    private $repository;

    public function __construct() {
        $this->repository = new DepositRepository();
    }

    public function initiateDeposit($bookieId, $playerId, $amount, $gatewayId) {
        return $this->repository->createPendingDeposit($bookieId, $playerId, $amount, $gatewayId);
    }

    public function saveReceipt($depositId, $receiptUrl) {
        return $this->repository->attachReceipt($depositId, $receiptUrl);
    }
    
    public function getDeposit($depositId) {
        return $this->repository->getById($depositId);
    }

    public function updateStatus($depositId, $status) {
        return $this->repository->updateStatus($depositId, $status);
    }
}
