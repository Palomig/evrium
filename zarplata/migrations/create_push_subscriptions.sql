-- Push subscriptions for Web Push notifications
-- Run once: php /path/to/zarplata/migrations/run_migration.php create_push_subscriptions.sql

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(255) NOT NULL,         -- client public key (base64url)
    auth VARCHAR(64) NOT NULL,            -- auth secret (base64url)
    user_agent VARCHAR(255),
    lesson_notify BOOLEAN DEFAULT 1,      -- notify before lessons
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_endpoint (endpoint(500))
);
