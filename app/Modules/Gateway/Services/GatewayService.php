<?php

namespace App\Modules\Gateway\Services;

use App\Modules\Gateway\Repositories\GatewayRepository;

class GatewayService {
    private $repository;

    public function __construct() {
        $this->repository = new GatewayRepository();
    }

    public function getAllGateways() {
        return $this->repository->getAll();
    }

    public function addGateway($name, array $configKeys) {
        if (empty($name) || empty($configKeys)) {
            return false;
        }
        return $this->repository->create($name, $configKeys);
    }
    
    public function toggleStatus($id, $currentStatus) {
        return $this->repository->toggleStatus($id, $currentStatus);
    }
}
