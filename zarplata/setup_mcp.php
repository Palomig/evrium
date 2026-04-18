<?php
/**
 * One-time setup: сохранить MCP-секрет в settings.
 *
 * Запускается ОДИН раз после деплоя. После успеха можно удалить.
 *
 * URL:
 *   https://эвриум.рф/zarplata/setup_mcp.php?setup_token=<HARDCODED>&secret=<NEW_SECRET>
 *
 * Безопасность:
 *   - Защищено хардкод-токеном (setup_token)
 *   - Если секрет уже задан в settings, нужен &force=1 для перезаписи
 */

require_once __DIR__ . '/config/db.php';

define('SETUP_TOKEN', 'bd80d800b26cbaf967c7172de681825c');

header('Content-Type: text/plain; charset=utf-8');

$providedToken = $_GET['setup_token'] ?? '';
if (!hash_equals(SETUP_TOKEN, $providedToken)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$newSecret = trim((string) ($_GET['secret'] ?? ''));
if ($newSecret === '' || strlen($newSecret) < 32) {
    http_response_code(400);
    echo "Missing or too short `secret` query param (min 32 chars)\n";
    exit;
}

$force = ($_GET['force'] ?? '') === '1';

$existing = (string) getSetting('mcp_secret', '');
if ($existing !== '' && !$force) {
    http_response_code(409);
    echo "MCP secret already set. Add &force=1 to overwrite.\n";
    exit;
}

$ok = setSetting('mcp_secret', $newSecret);
if (!$ok) {
    http_response_code(500);
    echo "Failed to save secret to settings table.\n";
    exit;
}

echo "OK — MCP secret saved (" . strlen($newSecret) . " chars).\n";
echo "Please DELETE this file after confirming MCP works:\n";
echo "  rm /home/c/cw95865/PALOMATIKA/public_html/zarplata/setup_mcp.php\n";
