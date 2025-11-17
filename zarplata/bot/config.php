<?php
/**
 * Конфигурация Telegram Bot
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// Получить токен бота из настроек
function getBotToken() {
    $setting = dbQueryOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'telegram_bot_token'",
        []
    );

    return $setting['setting_value'] ?? '';
}

// API URL Telegram
function getTelegramApiUrl($method) {
    $token = getBotToken();
    return "https://api.telegram.org/bot{$token}/{$method}";
}

// Отправить сообщение в Telegram
function sendTelegramMessage($chatId, $text, $replyMarkup = null) {
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
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Telegram API error: $result");
        return false;
    }

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

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
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

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}

// Найти преподавателя по telegram_id
function getTeacherByTelegramId($telegramId) {
    return dbQueryOne(
        "SELECT * FROM teachers WHERE telegram_id = ? AND active = 1",
        [$telegramId]
    );
}

// Установить telegram_id для преподавателя
function setTeacherTelegramId($teacherId, $telegramId, $telegramUsername) {
    return dbExecute(
        "UPDATE teachers SET telegram_id = ?, telegram_username = ?, updated_at = NOW()
         WHERE id = ?",
        [$telegramId, $telegramUsername, $teacherId]
    );
}
