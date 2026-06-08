<?php
/**
 * ВРЕМЕННЫЙ скрипт: задаёт settings.leads_chat_id (получатель заявок с сайта)
 * и шлёт контрольное сообщение. Удалить после использования. Доступ — ?key=...
 *
 *   /api/set_leads_chat.php?key=...&chat_id=245710727
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

const SET_KEY = '423f69bffa1ce730';
if (($_GET['key'] ?? '') !== SET_KEY) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$chatId = preg_replace('/[^\d-]/', '', (string)($_GET['chat_id'] ?? ''));
if ($chatId === '') {
    echo json_encode(['ok' => false, 'error' => 'chat_id_required']);
    exit;
}

require_once __DIR__ . '/../zarplata/config/db.php';

$out = ['chat_id' => $chatId];

// Записать настройку.
$out['set_leads_chat_id'] = setSetting('leads_chat_id', $chatId);
$out['read_back'] = getSetting('leads_chat_id');

// Контрольное сообщение тем же ботом.
$token = getSetting('bot_token');
if ($token) {
    $payload = http_build_query([
        'chat_id' => $chatId,
        'text' => '✅ Готово! Сюда теперь приходят заявки с сайта эвриум.рф.',
        'parse_mode' => 'HTML',
    ]);
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $payload,
        'timeout' => 8,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents("https://api.telegram.org/bot{$token}/sendMessage", false, $ctx);
    $out['sendMessage'] = json_decode((string)$raw, true) ?? $raw;
} else {
    $out['sendMessage'] = 'skipped: no bot_token';
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
