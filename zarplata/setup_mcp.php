<?php
/**
 * DISABLED — бутстрап-скрипт отработал и отключён.
 *
 * Для ротации `mcp_secret` подключись напрямую к БД и обнови
 * строку settings.setting_key = 'mcp_secret'.
 */
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo "Gone — setup script is permanently disabled.\n";
exit;
