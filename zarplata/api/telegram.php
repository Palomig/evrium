<?php
/**
 * API для управления Telegram Bot
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonError('Требуется авторизация', 401);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'check_webhook':
        handleCheckWebhook();
        break;
    case 'setup_webhook':
        handleSetupWebhook();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Проверить статус webhook
 */
function handleCheckWebhook() {
    // Получаем токен из настроек
    $setting = dbQueryOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'bot_token'",
        []
    );

    $token = $setting['setting_value'] ?? '';

    if (empty($token)) {
        jsonError('Bot token не настроен. Добавьте токен в настройках.', 400);
    }

    // Запрашиваем информацию о webhook у Telegram
    $url = "https://api.telegram.org/bot{$token}/getWebhookInfo";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        jsonError('Не удалось подключиться к Telegram API. Проверьте токен.', 500);
    }

    $data = json_decode($result, true);

    if (!$data || !$data['ok']) {
        jsonError('Ошибка Telegram API: ' . ($data['description'] ?? 'Unknown error'), 500);
    }

    jsonSuccess($data['result']);
}

/**
 * Настроить webhook
 */
function handleSetupWebhook() {
    // Получаем токен из настроек
    $setting = dbQueryOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'bot_token'",
        []
    );

    $token = $setting['setting_value'] ?? '';

    if (empty($token)) {
        jsonError('Bot token не настроен', 400);
    }

    // URL webhook
    $webhookUrl = 'https://эвриум.рф/zarplata/bot/webhook.php';

    // Настраиваем webhook
    $url = "https://api.telegram.org/bot{$token}/setWebhook";

    $data = [
        'url' => $webhookUrl,
        'max_connections' => 40,
        'drop_pending_updates' => false
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        jsonError('Не удалось настроить webhook', 500);
    }

    $response = json_decode($result, true);

    if (!$response || !$response['ok']) {
        jsonError('Ошибка Telegram API: ' . ($response['description'] ?? 'Unknown error'), 500);
    }

    logAudit('telegram_webhook_setup', 'settings', null, null, [
        'webhook_url' => $webhookUrl
    ], 'Настроен Telegram webhook');

    jsonSuccess([
        'message' => 'Webhook настроен успешно',
        'webhook_url' => $webhookUrl
    ]);
}
