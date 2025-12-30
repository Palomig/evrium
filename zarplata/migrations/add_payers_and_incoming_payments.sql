-- Migration: Add payers and incoming payments tables
-- For automatic payment tracking from Sberbank notifications
-- Created: 2025-12-29

-- Table for payers (family members linked to students)
CREATE TABLE IF NOT EXISTS student_payers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,           -- Name from Sberbank: "СТАНИСЛАВ ОЛЕГОВИЧ"
    phone VARCHAR(20) NULL,               -- Optional phone
    relation VARCHAR(50) NULL,            -- мама, папа, бабушка, etc.
    notes TEXT NULL,
    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_payer_name (name)
);

-- Table for incoming payments (from notifications)
CREATE TABLE IF NOT EXISTS incoming_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payer_id INT NULL,                    -- Linked payer (NULL if not matched)
    student_id INT NULL,                  -- Linked student (NULL if not matched)
    sender_name VARCHAR(200) NOT NULL,    -- Raw name from notification
    amount INT NOT NULL,                  -- Amount in rubles
    bank_name VARCHAR(100) NULL,          -- Source bank (Альфа-Банк, Сбербанк, etc.)
    raw_notification TEXT NOT NULL,       -- Full notification text for reference
    status ENUM('pending', 'matched', 'confirmed', 'ignored') DEFAULT 'pending',
    month VARCHAR(7) NULL,                -- Associated month (2024-12)
    match_confidence INT DEFAULT 0,       -- 0-100% confidence of auto-match
    matched_by VARCHAR(50) NULL,          -- 'auto' or 'manual' or user ID
    notes TEXT NULL,
    received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payer_id) REFERENCES student_payers(id) ON DELETE SET NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_sender_name (sender_name),
    INDEX idx_status (status),
    INDEX idx_month (month),
    INDEX idx_received_at (received_at)
);

-- Add auto_payment_id column to student_payments (links to incoming_payments)
-- NOTE: Run these separately. If column/constraint already exists, skip that line.

-- Step 1: Add column (skip if already exists)
-- ALTER TABLE student_payments ADD COLUMN auto_payment_id INT NULL;

-- Step 2: Add foreign key (skip if already exists)
-- ALTER TABLE student_payments ADD CONSTRAINT fk_auto_payment
--     FOREIGN KEY (auto_payment_id) REFERENCES incoming_payments(id) ON DELETE SET NULL;

-- API token for Automate app (stored in settings)
INSERT INTO settings (setting_key, setting_value, description)
VALUES ('automate_api_token', '', 'API token for Automate app notifications')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Enable/disable auto-matching
INSERT INTO settings (setting_key, setting_value, description)
VALUES ('auto_match_payments', '1', 'Enable automatic matching of payments to payers')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
