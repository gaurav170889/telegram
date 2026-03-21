USE telegrambot;

-- Subscriptions Table (Contracts for Bookies)
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bookie_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bookie_id) REFERENCES bookies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Manual Payments Table (Revenue tracking from SuperAdmin to Bookies)
CREATE TABLE IF NOT EXISTS manual_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bookie_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    payment_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bookie_id) REFERENCES bookies(id) ON DELETE CASCADE
) ENGINE=InnoDB;
