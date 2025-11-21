<?php
/**
 * Конфигурация Telegram Bot
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// Получить токен бота из настроек
function getBotToken() {
    try {
        $setting = dbQueryOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'bot_token'",
            []
        );

        $token = $setting['setting_value'] ?? '';

        if (empty($token)) {
            error_log("[Telegram Bot] Bot token is empty in database!");
        }

        return $token;
    } catch (Exception $e) {
        error_log("[Telegram Bot] Failed to get bot token: " . $e->getMessage());
        return '';
    }
}

// API URL Telegram
function getTelegramApiUrl($method) {
    $token = getBotToken();
    return "https://api.telegram.org/bot{$token}/{$method}";
}

// Отправить сообщение в Telegram
function sendTelegramMessage($chatId, $text, $replyMarkup = null) {
    $token = getBotToken();

    if (empty($token)) {
        error_log("[Telegram Bot] Cannot send message: bot token is empty");
        return false;
    }

    $url = getTelegramApiUrl('sendMessage');

    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($replyMarkup) {
        $data['reply_markup'] = json_encode($replyMarkup);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("[Telegram Bot] cURL error: $curlError");
        return false;
    }

    if ($httpCode !== 200) {
        error_log("[Telegram Bot] API error (HTTP $httpCode): $result");
        return false;
    }

    error_log("[Telegram Bot] Message sent successfully to chat $chatId");
    return json_decode($result, true);
}

// Ответить на callback query
function answerCallbackQuery($callbackQueryId, $text = '', $showAlert = false) {
    $url = getTelegramApiUrl('answerCallbackQuery');

    $data = [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => $showAlert
    ];

    error_log("[Telegram Bot] answerCallbackQuery: " . json_encode($data));

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($result, true);

    if ($httpCode !== 200 || !$decoded || !isset($decoded['ok']) || !$decoded['ok']) {
        error_log("[Telegram Bot] answerCallbackQuery FAILED: HTTP $httpCode, Response: $result");
    }

    return $decoded;
}

// Редактировать сообщение
function editTelegramMessage($chatId, $messageId, $text, $replyMarkup = null) {
    $url = getTelegramApiUrl('editMessageText');

    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($replyMarkup) {
        $data['reply_markup'] = json_encode($replyMarkup);
    }

    error_log("[Telegram Bot] editTelegramMessage: chat=$chatId, message=$messageId, text_length=" . strlen($text));

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($result, true);

    if ($httpCode !== 200 || !$decoded || !isset($decoded['ok']) || !$decoded['ok']) {
        error_log("[Telegram Bot] editTelegramMessage FAILED: HTTP $httpCode, Response: $result");
    }

    return $decoded;
}

// Найти преподавателя по telegram_id
function getTeacherByTelegramId($telegramId) {
    try {
        return dbQueryOne(
            "SELECT * FROM teachers WHERE telegram_id = ? AND active = 1",
            [$telegramId]
        );
    } catch (Exception $e) {
        error_log("[Telegram Bot] Failed to get teacher: " . $e->getMessage());
        return null;
    }
}

// Установить telegram_id для преподавателя
function setTeacherTelegramId($teacherId, $telegramId, $telegramUsername) {
    try {
        return dbExecute(
            "UPDATE teachers SET telegram_id = ?, telegram_username = ?, updated_at = NOW()
             WHERE id = ?",
            [$telegramId, $telegramUsername, $teacherId]
        );
    } catch (Exception $e) {
        error_log("[Telegram Bot] Failed to set telegram_id: " . $e->getMessage());
        return false;
    }
}

// Получить главное меню с кнопками
function getMainMenuKeyboard() {
    return [
        'keyboard' => [
            [
                ['text' => '📅 Сегодня'],
                ['text' => '📊 Неделя']
            ],
            [
                ['text' => '🗓 Расписание'],
                ['text' => 'ℹ️ Помощь']
            ]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
}

// Функция plural() уже определена в /config/helpers.php
