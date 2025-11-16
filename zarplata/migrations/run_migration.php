<?php
/**
 * Скрипт для выполнения миграции
 * Запускать из командной строки: php run_migration.php
 */

require_once __DIR__ . '/../config/db.php';

echo "=== Миграция: Добавление formula_id к преподавателям ===\n\n";

try {
    $pdo = getDB();

    // Читаем SQL файл
    $sql = file_get_contents(__DIR__ . '/add_formula_to_teachers.sql');

    // Удаляем комментарии и разбиваем на отдельные запросы
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );

    $pdo->beginTransaction();

    $executed = 0;
    foreach ($statements as $statement) {
        if (empty($statement)) continue;

        echo "Выполняется: " . substr($statement, 0, 60) . "...\n";
        $pdo->exec($statement);
        $executed++;
    }

    $pdo->commit();

    echo "\n✓ Миграция успешно применена!\n";
    echo "✓ Выполнено запросов: $executed\n\n";

    // Проверка результата
    $teachers = $pdo->query("
        SELECT t.id, t.name, pf.name as formula_name
        FROM teachers t
        LEFT JOIN payment_formulas pf ON t.formula_id = pf.id
        WHERE t.active = 1
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "Примеры преподавателей с формулами:\n";
    echo "----------------------------------------\n";
    foreach ($teachers as $teacher) {
        $formula = $teacher['formula_name'] ?? 'не назначена';
        echo sprintf("- %s: %s\n", $teacher['name'], $formula);
    }
    echo "\n";

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\n✗ Ошибка при выполнении миграции:\n";
    echo $e->getMessage() . "\n\n";

    // Проверить, не была ли миграция уже применена
    try {
        $result = $pdo->query("SHOW COLUMNS FROM teachers LIKE 'formula_id'")->fetch();
        if ($result) {
            echo "ℹ Похоже, миграция уже была применена ранее.\n\n";
        }
    } catch (Exception $e2) {
        // Игнорируем
    }

    exit(1);
}
