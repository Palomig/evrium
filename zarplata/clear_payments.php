<?php
/**
 * Скрипт для очистки всех выплат из базы данных
 * ВНИМАНИЕ: Это действие необратимо!
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// Требуем авторизацию (можно закомментировать для прямого выполнения)
// requireAuth();

echo "=== Очистка таблицы payments ===\n\n";

try {
    // Начинаем транзакцию
    $pdo = getDB();
    $pdo->beginTransaction();

    // Удаляем все записи из таблицы payments
    $result1 = $pdo->exec("DELETE FROM payments");
    echo "✓ Удалено записей из payments: $result1\n";

    // Сбрасываем AUTO_INCREMENT
    $pdo->exec("ALTER TABLE payments AUTO_INCREMENT = 1");
    echo "✓ AUTO_INCREMENT сброшен\n";

    // Удаляем записи из audit_log связанные с выплатами
    $result2 = $pdo->exec("DELETE FROM audit_log WHERE entity_type = 'payment'");
    echo "✓ Удалено записей из audit_log: $result2\n";

    // Подтверждаем транзакцию
    $pdo->commit();

    echo "\n=== Все выплаты успешно удалены! ===\n";
    echo "Обновите страницу отчётов, чтобы увидеть изменения.\n";

} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\n❌ Ошибка при удалении данных:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
