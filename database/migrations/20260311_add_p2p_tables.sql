-- Add p2p split payment system tables and extend existing ones

-- Deposits Extension
ALTER TABLE deposits 
ADD COLUMN deposit_type ENUM('direct_bookie', 'split_p2p') DEFAULT 'direct_bookie' AFTER amount,
MODIFY COLUMN status ENUM('pending', 'split_assigned', 'receipts_in_progress', 'receipts_submitted', 'under_review_window', 'approved', 'partially_disputed', 'disputed', 'rejected') DEFAULT 'pending';

-- Deposit Splits Table
CREATE TABLE IF NOT EXISTS deposit_splits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deposit_id INT NOT NULL,
    sequence_no INT NOT NULL,
    recipient_type ENUM('withdraw_player', 'bookie') NOT NULL,
    recipient_player_id INT NULL,
    recipient_bookie_id INT NULL,
    payment_method VARCHAR(255),
    payment_handle VARCHAR(255),
    amount DECIMAL(15, 2) NOT NULL,
    status ENUM('assigned', 'awaiting_receipt', 'receipt_uploaded', 'recipient_notified', 'under_review_window', 'accepted_no_dispute', 'disputed', 'resolved_confirmed', 'resolved_rejected') DEFAULT 'assigned',
    dispute_window_expires_at TIMESTAMP NULL,
    recipient_notified_at TIMESTAMP NULL,
    recipient_disputed_at TIMESTAMP NULL,
    auto_accepted_at TIMESTAMP NULL,
    split_reference VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deposit_id) REFERENCES deposits(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_player_id) REFERENCES players(id) ON DELETE SET NULL,
    FOREIGN KEY (recipient_bookie_id) REFERENCES bookies(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Deposit Split Receipts Table
CREATE TABLE IF NOT EXISTS deposit_split_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deposit_split_id INT NOT NULL,
    receipt_url TEXT NOT NULL,
    transaction_id VARCHAR(255),
    uploaded_by_player_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deposit_split_id) REFERENCES deposit_splits(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Withdrawals Extension
ALTER TABLE withdrawals
ADD COLUMN amount_requested DECIMAL(15, 2) NOT NULL DEFAULT 0.00 AFTER amount,
ADD COLUMN amount_submitted DECIMAL(15, 2) NOT NULL DEFAULT 0.00 AFTER amount_requested,
ADD COLUMN amount_confirmed DECIMAL(15, 2) NOT NULL DEFAULT 0.00 AFTER amount_submitted,
ADD COLUMN amount_disputed DECIMAL(15, 2) NOT NULL DEFAULT 0.00 AFTER amount_confirmed,
ADD COLUMN amount_remaining DECIMAL(15, 2) NOT NULL DEFAULT 0.00 AFTER amount_disputed,
MODIFY COLUMN status ENUM('queued', 'partially_paid', 'paid', 'partial_dispute', 'disputed', 'completed', 'pending', 'approved', 'rejected') DEFAULT 'pending';

-- Withdrawal Reservations Table
CREATE TABLE IF NOT EXISTS withdrawal_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    withdrawal_id INT NOT NULL,
    deposit_split_id INT NOT NULL,
    reserved_amount DECIMAL(15, 2) NOT NULL,
    status ENUM('active', 'completed', 'released', 'expired') DEFAULT 'active',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (withdrawal_id) REFERENCES withdrawals(id) ON DELETE CASCADE,
    FOREIGN KEY (deposit_split_id) REFERENCES deposit_splits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Ledger Allocations Extension
ALTER TABLE ledger_allocations
ADD COLUMN deposit_split_id INT NULL AFTER deposit_id,
ADD COLUMN allocation_type ENUM('queue_settlement', 'bookie_remainder', 'manual_clear') DEFAULT 'queue_settlement' AFTER amount,
ADD COLUMN status VARCHAR(50) DEFAULT 'active' AFTER allocation_type,
ADD FOREIGN KEY (deposit_split_id) REFERENCES deposit_splits(id) ON DELETE SET NULL;

-- Wallet Ledger Extension
ALTER TABLE wallet_ledger
MODIFY COLUMN entry_type ENUM(
    'deposit_approved', 'withdrawal_requested', 'withdrawal_matched', 'withdrawal_completed', 
    'manual_adjustment', 'reversal',
    'deposit_split_assigned', 'split_receipt_uploaded', 'split_auto_accepted', 
    'split_disputed', 'withdrawal_reserved', 'withdrawal_split_settled', 
    'withdrawal_manual_clear', 'deposit_finalized'
) NOT NULL;
