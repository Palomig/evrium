-- Migration: Add email_message_id for duplicate detection and email parser settings
-- Created: 2025-12-30
-- Purpose: Enable detection of duplicate emails by unique Message-ID

-- Add email_message_id column to incoming_payments for duplicate detection
ALTER TABLE incoming_payments
ADD COLUMN email_message_id VARCHAR(255) NULL AFTER raw_notification;

-- Add index for faster duplicate lookup
ALTER TABLE incoming_payments
ADD INDEX idx_email_message_id (email_message_id);

-- Add email parser settings if not exist
INSERT INTO settings (setting_key, setting_value, description)
VALUES ('email_sender', '', 'Email sender address for filtering (FROM)')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO settings (setting_key, setting_value, description)
VALUES ('email_subject_filter', 'ZARPLATAPROJECT', 'Subject filter keyword for payment emails')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO settings (setting_key, setting_value, description)
VALUES ('email_search_days', '60', 'Number of days to search emails (for duplicate detection)')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
