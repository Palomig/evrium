-- Add VAPID settings keys (values populated by setup_vapid.php)
INSERT IGNORE INTO settings (setting_key, setting_value, description)
VALUES
    ('vapid_public_key',  '', 'VAPID public key for Web Push (base64url)'),
    ('vapid_private_key', '', 'VAPID private key for Web Push (base64url)'),
    ('vapid_subject',     'mailto:admin@evrium.ru', 'VAPID subject (mailto: or URL)');
