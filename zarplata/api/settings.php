<?php
/**
 * API для управления настройками
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

// Устанавливаем JSON заголовки
header('Content-Type: application/json; charset=utf-8');

// Требуем авторизацию
if (!isLoggedIn()) {
    jsonError('Требуется авторизация', 401);
}

// Получаем действие
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Маршрутизация по действиям
switch ($action) {
    case 'get_all':
        handleGetAll();
        break;
    case 'update_bot':
        handleUpdateBot();
        break;
    case 'update_financial':
        handleUpdateFinancial();
        break;
    case 'update_system':
        handleUpdateSystem();
        break;
    case 'update_payment':
        handleUpdatePayment();
        break;
    case 'change_password':
        handleChangePassword();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Получить все настройки
 */
function handleGetAll() {
    $settings = dbQuery("SELECT * FROM settings ORDER BY setting_key ASC", []);

    // Преобразовать в ассоциативный массив
    $settingsMap = [];
    foreach ($settings as $setting) {
        $settingsMap[$setting['setting_key']] = $setting['setting_value'];
    }

    jsonSuccess($settingsMap);
}

/**
 * Обновить настройки Telegram бота
 */
function handleUpdateBot() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация
    $botToken = trim($data['bot_token'] ?? '');
    $checkInterval = filter_var($data['bot_check_interval'] ?? 5, FILTER_VALIDATE_INT);
    $attendanceDelay = filter_var($data['attendance_delay'] ?? 15, FILTER_VALIDATE_INT);

    if ($checkInterval < 1 || $checkInterval > 60) {
        jsonError('Интервал проверки должен быть от 1 до 60 минут', 400);
    }

    if ($attendanceDelay < 0 || $attendanceDelay > 60) {
        jsonError('Задержка опроса должна быть от 0 до 60 минут', 400);
    }

    // Обновляем настройки
    try {
        updateSetting('bot_token', $botToken);
        updateSetting('bot_check_interval', $checkInterval);
        updateSetting('attendance_delay', $attendanceDelay);

        logAudit('settings_updated', 'settings', null, null, [
            'section' => 'bot'
        ], 'Обновлены настройки Telegram бота');

        jsonSuccess(['message' => 'Настройки бота обновлены']);
    } catch (Exception $e) {
        error_log("Failed to update bot settings: " . $e->getMessage());
        jsonError('Ошибка при обновлении настроек', 500);
    }
}

/**
 * Обновить финансовые настройки
 */
function handleUpdateFinancial() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация
    $ownerSharePercent = filter_var($data['owner_share_percent'] ?? 30, FILTER_VALIDATE_INT);
    $currency = trim($data['currency'] ?? 'RUB');

    if ($ownerSharePercent < 0 || $ownerSharePercent > 100) {
        jsonError('Процент владельца должен быть от 0 до 100', 400);
    }

    if (empty($currency) || strlen($currency) > 10) {
        jsonError('Неверный формат валюты', 400);
    }

    // Обновляем настройки
    try {
        updateSetting('owner_share_percent', $ownerSharePercent);
        updateSetting('currency', $currency);

        logAudit('settings_updated', 'settings', null, null, [
            'section' => 'financial'
        ], 'Обновлены финансовые настройки');

        jsonSuccess(['message' => 'Финансовые настройки обновлены']);
    } catch (Exception $e) {
        error_log("Failed to update financial settings: " . $e->getMessage());
        jsonError('Ошибка при обновлении настроек', 500);
    }
}

/**
 * Обновить системные настройки
 */
function handleUpdateSystem() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация
    $timezone = trim($data['timezone'] ?? 'Europe/Moscow');

    if (empty($timezone)) {
        jsonError('Часовой пояс обязателен', 400);
    }

    // Проверяем валидность часового пояса
    $validTimezones = timezone_identifiers_list();
    if (!in_array($timezone, $validTimezones)) {
        jsonError('Неверный часовой пояс', 400);
    }

    // Обновляем настройки
    try {
        updateSetting('timezone', $timezone);

        logAudit('settings_updated', 'settings', null, null, [
            'section' => 'system'
        ], 'Обновлены системные настройки');

        jsonSuccess(['message' => 'Системные настройки обновлены']);
    } catch (Exception $e) {
        error_log("Failed to update system settings: " . $e->getMessage());
        jsonError('Ошибка при обновлении настроек', 500);
    }
}

/**
 * Обновить настройки оплаты от учеников
 */
function handleUpdatePayment() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация
    $cardNumber = trim($data['payment_card_number'] ?? '');
    $reminderTemplate = trim($data['payment_reminder_template'] ?? '');

    // Форматируем номер карты (убираем лишние пробелы)
    $cardNumber = preg_replace('/\s+/', ' ', $cardNumber);

    if (mb_strlen($reminderTemplate) > 2000) {
        jsonError('Шаблон напоминания слишком длинный (максимум 2000 символов)', 400);
    }

    // Обновляем настройки
    try {
        updateSetting('payment_card_number', $cardNumber);
        updateSetting('payment_reminder_template', $reminderTemplate);

        logAudit('settings_updated', 'settings', null, null, [
            'section' => 'payment'
        ], 'Обновлены настройки оплаты от учеников');

        jsonSuccess(['message' => 'Настройки оплаты обновлены']);
    } catch (Exception $e) {
        error_log("Failed to update payment settings: " . $e->getMessage());
        jsonError('Ошибка при обновлении настроек', 500);
    }
}

/**
 * Изменить пароль
 */
function handleChangePassword() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $currentPassword = $data['current_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';

    // Валидация
    if (empty($currentPassword)) {
        jsonError('Введите текущий пароль', 400);
    }

    if (empty($newPassword)) {
        jsonError('Введите новый пароль', 400);
    }

    if (strlen($newPassword) < 6) {
        jsonError('Новый пароль должен содержать минимум 6 символов', 400);
    }

    if ($newPassword !== $confirmPassword) {
        jsonError('Пароли не совпадают', 400);
    }

    // Получаем текущего пользователя
    $user = getCurrentUser();
    if (!$user) {
        jsonError('Пользователь не найден', 404);
    }

    // Проверяем текущий пароль
    $admin = dbQueryOne(
        "SELECT * FROM users WHERE id = ?",
        [$user['id']]
    );

    if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
        jsonError('Неверный текущий пароль', 400);
    }

    // Обновляем пароль
    try {
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $result = dbExecute(
            "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
            [$newPasswordHash, $user['id']]
        );

        if ($result !== false) {
            logAudit('password_changed', 'user', $user['id'], null, null, 'Пароль изменён');
            jsonSuccess(['message' => 'Пароль успешно изменён']);
        } else {
            jsonError('Не удалось изменить пароль', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to change password: " . $e->getMessage());
        jsonError('Ошибка при изменении пароля', 500);
    }
}

/**
 * Обновить одну настройку
 */
function updateSetting($key, $value) {
    // Проверяем, существует ли настройка
    $existing = dbQueryOne(
        "SELECT * FROM settings WHERE setting_key = ?",
        [$key]
    );

    if ($existing) {
        // Обновляем
        dbExecute(
            "UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
            [$value, $key]
        );
    } else {
        // Создаём
        dbExecute(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)",
            [$key, $value]
        );
    }
}
