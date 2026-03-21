<?php

namespace App\Modules\Wallet\Services;

use App\Modules\Wallet\Repositories\WalletRepository;

class WalletService {
    private $walletRepo;
    private $ledgerService;

    public function __construct() {
        $this->walletRepo = new WalletRepository();
        $this->ledgerService = new LedgerService();
    }

    public function getWalletForBookiePlayer($bookiePlayerId) {
        $wallet = $this->walletRepo->getWalletByBookiePlayerId($bookiePlayerId);
        if (!$wallet) {
            $walletId = $this->walletRepo->createWallet($bookiePlayerId);
            $wallet = $this->walletRepo->getWalletById($walletId);
        }
        return $wallet;
    }

    /**
     * Credit a successful deposit to the user's available balance.
     */
    public function creditDeposit($walletId, $amount, $depositId) {
        return $this->ledgerService->createEntry(
            $walletId,
            'deposit_approved',
            'credit',
            $amount,
            'deposit',
            $depositId
        );
    }

    /**
     * Lock funds when a user requests a withdrawal. 
     * Decreases available balance, increases locked balance. Ledger total balance stays same.
     */
    public function lockForWithdrawal($walletId, $amount, $withdrawalId) {
        $wallet = $this->walletRepo->getWalletById($walletId);
        if (!$wallet || $wallet['available_balance'] < $amount) {
            return false; // Insufficient funds
        }

        // To keep ledger immutable, a lock is technically moving funds internally.
        // We record a 'withdrawal_requested' debit, but we use the lockedChange offset
        // so that the total balance stays the same until it's actually paid.
        // Actually, standard double entry for "holding" money in one account is typically just
        // updating state. Let's record a 0 amount ledger entry, but pass $lockedChange.
        
        return $this->ledgerService->createEntry(
            $walletId,
            'withdrawal_requested',
            'debit',
            0, // No funds leave the account yet
            'withdrawal',
            $withdrawalId,
            $amount // Moves $amount from available to locked
        );
    }

    /**
     * Complete a withdrawal. 
     * Decreases locked balance AND total balance.
     */
    public function completeWithdrawal($walletId, $amount, $withdrawalId) {
        // Decrease locked balance by -$amount, Decrease total balance (debit) by $amount
        return $this->ledgerService->createEntry(
            $walletId,
            'withdrawal_completed',
            'debit',
            $amount, // Total balance drops
            'withdrawal',
            $withdrawalId,
            -$amount // Free up the lock
        );
    }
}
