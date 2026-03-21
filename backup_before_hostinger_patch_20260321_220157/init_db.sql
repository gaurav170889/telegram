USE telegrambot;

-- Bookies Table
CREATE TABLE IF NOT EXISTS bookies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    telegram_id VARCHAR(255) NULL,
    telegram_bot_token VARCHAR(255) UNIQUE NOT NULL,
    status ENUM('active', 'suspended', 'disabled', 'onboarding') DEFAULT 'onboarding',
    telegram_verified BOOLEAN DEFAULT false,
    onboarding_completed BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Gateways (Global Catalog managed by SuperAdmin)
CREATE TABLE IF NOT EXISTS gateways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    required_config JSON NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Bookie Gateway Configuration
CREATE TABLE IF NOT EXISTS bookie_gateways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bookie_id INT NOT NULL,
    gateway_id INT NOT NULL,
    config_values JSON NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'inactive',
    FOREIGN KEY (bookie_id) REFERENCES bookies(id) ON DELETE CASCADE,
    FOREIGN KEY (gateway_id) REFERENCES gateways(id) ON DELETE CASCADE,
    UNIQUE(bookie_id, gateway_id)
) ENGINE=InnoDB;

-- Players Table
CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT UNIQUE NOT NULL,
    username VARCHAR(255),
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Bookie_Players
CREATE TABLE IF NOT EXISTS bookie_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bookie_id INT NOT NULL,
    player_id INT NOT NULL,
    wallet_balance DECIMAL(15, 2) DEFAULT 0.00,
    status ENUM('active', 'suspended') DEFAULT 'active',
    current_state VARCHAR(100) DEFAULT 'MAIN_MENU',
    temp_payload JSON NULL,
    FOREIGN KEY (bookie_id) REFERENCES bookies(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE(bookie_id, player_id)
) ENGINE=InnoDB;

-- Payment Methods (Manual)
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bookie_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    details JSON NOT NULL,
    is_active BOOLEAN DEFAULT true,
    FOREIGN KEY (bookie_id) REFERENCES bookies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Deposits
CREATE TABLE IF NOT EXISTS deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bookie_id INT NOT NULL,
    player_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    transaction_id VARCHAR(255),
    receipt_url TEXT,
    method_type ENUM('manual', 'gateway') NOT NULL,
    gateway_id INT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    fraud_score INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bookie_id) REFERENCES bookies(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (gateway_id) REFERENCES gateways(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Withdrawals
CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bookie_id INT NOT NULL,
    player_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    method_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed', 'queued', 'partially_paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bookie_id) REFERENCES bookies(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Payout Methods (Player saved handles)
CREATE TABLE IF NOT EXISTS payout_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    method_name VARCHAR(100) NOT NULL,
    handle VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Wallets
CREATE TABLE IF NOT EXISTS wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bookie_player_id INT NOT NULL UNIQUE,
    current_balance DECIMAL(15, 2) DEFAULT 0.00,
    available_balance DECIMAL(15, 2) DEFAULT 0.00,
    locked_balance DECIMAL(15, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bookie_player_id) REFERENCES bookie_players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Wallet Ledger (Immutable Double-Entry)
CREATE TABLE IF NOT EXISTS wallet_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL,
    entry_type ENUM('deposit_approved', 'withdrawal_requested', 'withdrawal_matched', 'withdrawal_completed', 'manual_adjustment', 'reversal') NOT NULL,
    direction ENUM('credit', 'debit') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    opening_balance DECIMAL(15, 2) NOT NULL,
    closing_balance DECIMAL(15, 2) NOT NULL,
    reference_type ENUM('deposit', 'withdrawal', 'manual') NOT NULL,
    reference_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Ledger Allocations (Queue Matching Mapping)
CREATE TABLE IF NOT EXISTS ledger_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deposit_id INT NOT NULL,
    withdrawal_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deposit_id) REFERENCES deposits(id) ON DELETE CASCADE,
    FOREIGN KEY (withdrawal_id) REFERENCES withdrawals(id) ON DELETE CASCADE
) ENGINE=InnoDB;
