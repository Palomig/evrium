<?php
/**
 * API для журнала аудита
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
$action = $_GET['action'] ?? '';

// Маршрутизация по действиям
switch ($action) {
    case 'get_details':
        handleGetDetails();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Получить детали аудит-записи
 */
function handleGetDetails() {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID записи', 400);
    }

    $log = dbQueryOne(
        "SELECT al.*, u.name as user_name
         FROM audit_log al
         LEFT JOIN users u ON al.user_id = u.id
         WHERE al.id = ?",
        [$id]
    );

    if (!$log) {
        jsonError('Запись не найдена', 404);
    }

    jsonSuccess($log);
}
