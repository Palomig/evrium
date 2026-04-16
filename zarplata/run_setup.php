<?php
/**
 * One-time setup: run migrations + generate VAPID keys
 * Protected by a token — delete this file after running!
 *
 * Usage: https://эвриум.рф/zarplata/run_setup.php?token=evrium_setup_2026
 */

define('SETUP_TOKEN', 'evrium_setup_2026');

if (($_GET['token'] ?? '') !== SETUP_TOKEN) {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/web_push.php';

$pdo = getDB();
$log = [];

function run(PDO $pdo, string $sql, string $desc): void {
    global $log;
    try {
        $pdo->exec($sql);
        $log[] = "✓ {$desc}";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // "already exists" errors are OK
        if (strpos($msg, 'already exists') !== false || strpos($msg, 'Duplicate') !== false) {
            $log[] = "- {$desc} (already done)";
        } else {
            $log[] = "✗ {$desc}: {$msg}";
        }
    }
}

// ── 1. push_subscriptions table ───────────────────────────────────────────
run($pdo,
    "CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        teacher_id INT NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh VARCHAR(255) NOT NULL,
        auth VARCHAR(64) NOT NULL,
        user_agent VARCHAR(255),
        lesson_notify BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_endpoint (endpoint(500))
    )",
    'CREATE TABLE push_subscriptions'
);

// ── 2. VAPID settings ─────────────────────────────────────────────────────
$vapidKeys = ['vapid_public_key', 'vapid_private_key', 'vapid_subject'];
foreach ($vapidKeys as $key) {
    run($pdo,
        "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('$key', '')",
        "INSERT settings.$key"
    );
}
run($pdo,
    "UPDATE settings SET setting_value = 'mailto:admin@evrium.ru' WHERE setting_key = 'vapid_subject' AND setting_value = ''",
    'Set vapid_subject default'
);

// ── 3. Generate VAPID keys (only if empty) ────────────────────────────────
$existing = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'vapid_public_key'")->fetchColumn();

if ($existing) {
    $log[] = "- VAPID keys already set (public: " . substr($existing, 0, 16) . "...)";
} else {
    $keys = vapidGenerateKeys();
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->execute([$keys['public'],  'vapid_public_key']);
    $stmt->execute([$keys['private'], 'vapid_private_key']);
    $log[] = "✓ VAPID keys generated";
    $log[] = "  Public key: " . $keys['public'];
}

// ── 4. Summary ────────────────────────────────────────────────────────────
echo implode("\n", $log) . "\n\n";
echo "Done. DELETE this file from server after running:\n";
echo "  rm /path/to/zarplata/run_setup.php\n";
