<?php

namespace App\Modules\Wallet\Repositories;

use App\Core\Database;
use PDO;

class LedgerRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function insertEntry($walletId, $entryType, $direction, $amount, $openingBalance, $closingBalance, $refType, $refId, $description = null) {
        $stmt = $this->db->prepare("
            INSERT INTO wallet_ledger 
            (wallet_id, entry_type, direction, amount, opening_balance, closing_balance, reference_type, reference_id, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $walletId, $entryType, $direction, $amount, 
            $openingBalance, $closingBalance, $refType, $refId, $description
        ]);
        return $this->db->lastInsertId();
    }

    public function getHistoryByWallet($walletId, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT * FROM wallet_ledger 
            WHERE wallet_id = ? 
            ORDER BY created_at DESC, id DESC 
            LIMIT ?
        ");
        
        // PDO needs limit as int
        $stmt->bindValue(1, $walletId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLedgerByBookie($bookieId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT wl.*, p.username, p.first_name, COALESCE(d.receipt_url, dsr.receipt_url) as receipt_url, w_ref.amount as with_amount
            FROM wallet_ledger wl
            JOIN wallets w ON w.id = wl.wallet_id
            JOIN bookie_players bp ON bp.id = w.bookie_player_id
            JOIN players p ON p.id = bp.player_id
            LEFT JOIN deposits d ON wl.reference_type = 'deposit' AND wl.reference_id = d.id
            LEFT JOIN withdrawals w_ref ON wl.reference_type = 'withdrawal' AND wl.reference_id = w_ref.id
            LEFT JOIN deposit_split_receipts dsr ON wl.reference_type = 'split' AND wl.reference_id = dsr.deposit_split_id

            WHERE bp.bookie_id = ?
            ORDER BY wl.created_at DESC, wl.id DESC
            LIMIT ?
        ");
        
        $stmt->bindValue(1, $bookieId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGlobalLedger($limit = 100) {
        $stmt = $this->db->prepare("
            SELECT wl.*, p.username, p.first_name, b.name as bookie_name, COALESCE(d.receipt_url, dsr.receipt_url) as receipt_url, w_ref.amount as with_amount
            FROM wallet_ledger wl
            JOIN wallets w ON w.id = wl.wallet_id
            JOIN bookie_players bp ON bp.id = w.bookie_player_id
            JOIN players p ON p.id = bp.player_id
            JOIN bookies b ON b.id = bp.bookie_id
            LEFT JOIN deposits d ON wl.reference_type = 'deposit' AND wl.reference_id = d.id
            LEFT JOIN withdrawals w_ref ON wl.reference_type = 'withdrawal' AND wl.reference_id = w_ref.id
            LEFT JOIN deposit_split_receipts dsr ON wl.reference_type = 'split' AND wl.reference_id = dsr.deposit_split_id

            ORDER BY wl.created_at DESC, wl.id DESC
            LIMIT ?
        ");
        
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAggregatedStats($bookieId, $startDate, $endDate) {
        $stmt = $this->db->prepare("
            SELECT 
                SUM(CASE WHEN wl.entry_type = 'deposit_finalized' THEN wl.amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN wl.entry_type IN ('withdrawal_manual_clear', 'withdrawal_auto_settled', 'split_auto_accepted') THEN wl.amount ELSE 0 END) as total_payouts,
                COUNT(wl.id) as transaction_count
            FROM wallet_ledger wl
            JOIN wallets w ON w.id = wl.wallet_id
            JOIN bookie_players bp ON bp.id = w.bookie_player_id
            WHERE bp.bookie_id = ? AND wl.created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$bookieId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPlayerBreakdown($bookieId, $startDate, $endDate) {
        $stmt = $this->db->prepare("
            SELECT 
                p.username, p.first_name,
                SUM(CASE WHEN wl.entry_type = 'deposit_finalized' THEN wl.amount ELSE 0 END) as revenue,
                SUM(CASE WHEN wl.entry_type IN ('withdrawal_manual_clear', 'withdrawal_auto_settled', 'split_auto_accepted') THEN wl.amount ELSE 0 END) as payouts
            FROM wallet_ledger wl
            JOIN wallets w ON w.id = wl.wallet_id
            JOIN bookie_players bp ON bp.id = w.bookie_player_id
            JOIN players p ON p.id = bp.player_id
            WHERE bp.bookie_id = ? AND wl.created_at BETWEEN ? AND ?
            GROUP BY p.id
            ORDER BY revenue DESC
        ");
        $stmt->execute([$bookieId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMethodBreakdown($bookieId, $startDate, $endDate) {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(g.name, pm.method_name, ds.payment_method, 'System') as method_name,
                SUM(wl.amount) as volume
            FROM wallet_ledger wl
            JOIN wallets w ON w.id = wl.wallet_id
            JOIN bookie_players bp ON bp.id = w.bookie_player_id
            LEFT JOIN deposits d ON wl.reference_type = 'deposit' AND wl.reference_id = d.id
            LEFT JOIN gateways g ON d.gateway_id = g.id
            LEFT JOIN withdrawals w_req ON wl.reference_type = 'withdrawal' AND wl.reference_id = w_req.id
            LEFT JOIN payout_methods pm ON w_req.method_id = pm.id
            LEFT JOIN deposit_splits ds ON wl.reference_type = 'split' AND wl.reference_id = ds.id
            WHERE bp.bookie_id = ? AND wl.created_at BETWEEN ? AND ?
            AND wl.entry_type IN ('deposit_finalized', 'withdrawal_manual_clear', 'withdrawal_auto_settled', 'split_auto_accepted')
            GROUP BY COALESCE(g.name, pm.method_name, ds.payment_method, 'System')
            ORDER BY volume DESC
        ");
        $stmt->execute([$bookieId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
