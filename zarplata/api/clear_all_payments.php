<?php
/**
 * API для полной очистки всех выплат
 * ВНИМАНИЕ: Это действие необратимо!
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Требуем авторизацию
if (!isLoggedIn()) {
    jsonError('Требуется авторизация', 401);
}

// Проверяем роль (только owner может удалять все выплаты)
$user = getCurrentUser();
if ($user['role'] !== 'owner') {
    jsonError('Недостаточно прав. Только владелец может удалять все выплаты.', 403);
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // Подсчитываем количество записей перед удалением
    $countPayments = dbQueryOne("SELECT COUNT(*) as count FROM payments", []);
    $countAudit = dbQueryOne("SELECT COUNT(*) as count FROM audit_log WHERE entity_type = 'payment'", []);

    // Удаляем все записи из таблицы payments
    $pdo->exec("DELETE FROM payments");

    // Сбрасываем AUTO_INCREMENT
    $pdo->exec("ALTER TABLE payments AUTO_INCREMENT = 1");

    // Удаляем записи из audit_log связанные с выплатами
    $pdo->exec("DELETE FROM audit_log WHERE entity_type = 'payment'");

    // Логируем это действие
    logAudit(
        'payments_cleared_all',
        'payment',
        null,
        null,
        [
            'deleted_payments' => $countPayments['count'],
            'deleted_audit_logs' => $countAudit['count']
        ],
        'Удалены ВСЕ выплаты из системы'
    );

    $pdo->commit();

    jsonSuccess([
        'message' => 'Все выплаты успешно удалены',
        'deleted_payments' => $countPayments['count'],
        'deleted_audit_logs' => $countAudit['count']
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Failed to clear all payments: " . $e->getMessage());
    jsonError('Ошибка при удалении выплат: ' . $e->getMessage(), 500);
}
