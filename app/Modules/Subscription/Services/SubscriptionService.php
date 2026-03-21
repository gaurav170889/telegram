<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Subscription\Repositories\SubscriptionRepository;

class SubscriptionService {
    private $repository;

    public function __construct() {
        $this->repository = new SubscriptionRepository();
    }

    public function getAllSubscriptions() {
        return $this->repository->getAllWithBookieDetails();
    }

    public function updateSubscriptionDates($id, $startDate, $endDate) {
        if (empty($startDate) || empty($endDate)) {
            return false; // Basic validation
        }
        return $this->repository->updateDates($id, $startDate, $endDate);
    }
}
