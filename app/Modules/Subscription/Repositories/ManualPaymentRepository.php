<?php

namespace App\Modules\Subscription\Repositories;

use App\Core\Database;
use PDO;

class ManualPaymentRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function addPayment($bookieId, $amount, $paymentDate, $notes) {
        $stmt = $this->db->prepare("
            INSERT INTO manual_payments (bookie_id, amount, payment_date, notes) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$bookieId, $amount, $paymentDate, $notes]);
    }

    public function getAllPayments() {
        $stmt = $this->db->query("
            SELECT mp.*, b.name AS bookie_name, u.username AS bookie_username 
            FROM manual_payments mp
            JOIN bookies b ON mp.bookie_id = b.id
            LEFT JOIN users u ON u.bookie_id = b.id
            ORDER BY mp.payment_date DESC, mp.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
