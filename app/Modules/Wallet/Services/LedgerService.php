<?php

namespace App\Modules\Wallet\Services;

use App\Modules\Wallet\Repositories\LedgerRepository;
use App\Modules\Wallet\Repositories\WalletRepository;

class LedgerService {
    private $ledgerRepo;
    private $walletRepo;

    public function __construct() {
        $this->ledgerRepo = new LedgerRepository();
        $this->walletRepo = new WalletRepository();
    }

    /**
     * Core Double-Entry Ledger Logic. This is the ONLY way balances should be modified.
     */
    public function createEntry($walletId, $entryType, $direction, $amount, $refType, $refId, $lockedChange = 0.00, $description = null) {
        $amount = (float) $amount;
        $lockedChange = (float) $lockedChange;

        // 1. Fetch current wallet state
        $wallet = $this->walletRepo->getWalletById($walletId);
        if (!$wallet) return false;

        $openingBalance = (float) $wallet['current_balance'];
        
        // 2. Calculate new balances
        $closingBalance = $openingBalance;
        $newAvailable = (float) $wallet['available_balance'];
        $newLocked = (float) $wallet['locked_balance'];

        if ($direction === 'credit') {
            $closingBalance += $amount;
            $newAvailable += $amount;
        } elseif ($direction === 'debit') {
            $closingBalance -= $amount;
            // Debits usually come from available. If it was a locked withdrawal completing, 
            // the logic before calling this should adjust lock separately, or we handle lock here.
            $newAvailable -= $amount;
        }

        // Apply any manual lock adjustments (e.g., when a withdrawal is strictly locking funds)
        if ($lockedChange != 0) {
            $newLocked += $lockedChange;
            $newAvailable -= $lockedChange; 
        }

        try {
            // 3. Insert Ledger Entry
            $entryId = $this->ledgerRepo->insertEntry(
                $walletId, $entryType, $direction, $amount, 
                $openingBalance, $closingBalance, $refType, $refId, $description
            );

            // 4. Update Wallet 
            $this->walletRepo->updateBalances($walletId, $closingBalance, $newAvailable, $newLocked);

            return $entryId;
        } catch (\Exception $e) {
            // Log error
            return false;
        }
    }
}
