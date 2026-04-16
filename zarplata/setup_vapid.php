<?php
/**
 * One-time VAPID key setup script.
 *
 * Run from CLI: php zarplata/setup_vapid.php
 * Or visit https://эвриум.рф/zarplata/setup_vapid.php (protected by admin check)
 *
 * Generates a new VAPID key pair and saves it to the settings table.
 * Safe to run multiple times — only regenerates if keys are empty OR --force flag given.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/web_push.php';

$isCli   = (PHP_SAPI === 'cli');
$isForce = in_array('--force', $argv ?? []);

if (!$isCli) {
    // Minimal web protection: only allow from localhost or with secret token
    $secret = trim(dbQueryOne("SELECT setting_value FROM settings WHERE setting_key = 'admin_secret'", [])['setting_value'] ?? '');
    $provided = $_GET['secret'] ?? '';
    if (!$secret || !hash_equals($secret, $provided)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

// Check if keys already set
$existingPub = dbQueryOne("SELECT setting_value FROM settings WHERE setting_key = 'vapid_public_key'", []);
$existingPub = $existingPub['setting_value'] ?? '';

if ($existingPub && !$isForce) {
    $msg = 'VAPID keys already set. Use --force to regenerate.';
    echo $isCli ? $msg . PHP_EOL : json_encode(['message' => $msg, 'public' => $existingPub]);
    exit;
}

// Generate new keys
$keys = vapidGenerateKeys();

// Ensure settings rows exist
dbExecute("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('vapid_public_key',  '')", []);
dbExecute("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('vapid_private_key', '')", []);
dbExecute("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('vapid_subject', 'mailto:admin@evrium.ru')", []);

// Save
dbExecute("UPDATE settings SET setting_value = ? WHERE setting_key = 'vapid_public_key'",  [$keys['public']]);
dbExecute("UPDATE settings SET setting_value = ? WHERE setting_key = 'vapid_private_key'", [$keys['private']]);

if ($isCli) {
    echo "VAPID keys generated and saved to settings table." . PHP_EOL;
    echo "Public key:  " . $keys['public'] . PHP_EOL;
    echo "Private key: " . substr($keys['private'], 0, 10) . "..." . PHP_EOL;
} else {
    echo json_encode(['success' => true, 'public' => $keys['public']]);
}
