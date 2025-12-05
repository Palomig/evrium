<?php
/**
 * Скрипт для создания выплат за указанную дату
 * Использование: php create_payments_for_date.php [YYYY-MM-DD]
 * Или через браузер: create_payments_for_date.php?date=2024-12-04
 */

// Включаем отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/student_helpers.php';

// Определяем дату
$date = $_GET['date'] ?? ($argv[1] ?? date('Y-m-d'));
$dayOfWeek = date('N', strtotime($date));

echo "<pre>\n";
echo "=== Создание выплат за {$date} (день недели: {$dayOfWeek}) ===\n\n";

// Получаем уроки на этот день
$lessons = dbQuery(
    "SELECT lt.*, t.name as teacher_name, t.id as teacher_id,
            t.formula_id_group, t.formula_id_individual, t.formula_id
     FROM lessons_template lt
     JOIN teachers t ON lt.teacher_id = t.id
     WHERE lt.day_of_week = ?
       AND lt.active = 1
       AND t.active = 1
     ORDER BY lt.time_start",
    [$dayOfWeek]
);

if (empty($lessons)) {
    echo "Нет уроков на этот день\n";
    exit;
}

echo "Найдено уроков: " . count($lessons) . "\n\n";

$created = 0;
$skipped = 0;
$errors = 0;

foreach ($lessons as $lesson) {
    $timeStart = substr($lesson['time_start'], 0, 5);

    echo "--- Урок {$timeStart} ({$lesson['teacher_name']}) ---\n";

    // Проверяем, есть ли уже выплата за этот урок сегодня
    $existingPayment = dbQueryOne(
        "SELECT id FROM payments
         WHERE teacher_id = ? AND lesson_template_id = ? AND DATE(created_at) = ?",
        [$lesson['teacher_id'], $lesson['id'], $date]
    );

    if ($existingPayment) {
        echo "  ⚠ Выплата уже существует (ID: {$existingPayment['id']}), пропуск\n";
        $skipped++;
        continue;
    }

    // Получаем учеников динамически
    $studentsData = getStudentsForLesson(
        $lesson['teacher_id'],
        $dayOfWeek,
        $timeStart
    );

    $studentCount = $studentsData['count'];
    $subject = $studentsData['subject'] ?? $lesson['subject'] ?? 'Математика';

    echo "  Учеников: {$studentCount}\n";
    echo "  Предмет: {$subject}\n";

    if ($studentCount == 0) {
        echo "  ⚠ Нет учеников, пропуск\n";
        $skipped++;
        continue;
    }

    // Определяем формулу
    $lessonType = $lesson['lesson_type'] ?? 'group';
    $formulaId = null;

    if ($lessonType === 'individual') {
        $formulaId = $lesson['formula_id_individual'] ?? $lesson['formula_id'] ?? null;
    } else {
        $formulaId = $lesson['formula_id_group'] ?? $lesson['formula_id'] ?? null;
    }

    if (!$formulaId) {
        echo "  ⚠ Нет формулы для преподавателя, пропуск\n";
        $skipped++;
        continue;
    }

    // Получаем формулу
    $formula = dbQueryOne(
        "SELECT * FROM payment_formulas WHERE id = ? AND active = 1",
        [$formulaId]
    );

    if (!$formula) {
        echo "  ⚠ Формула #{$formulaId} не найдена или неактивна\n";
        $skipped++;
        continue;
    }

    // Рассчитываем выплату
    $amount = calculatePayment($formula, $studentCount);
    echo "  Формула: {$formula['name']}\n";
    echo "  Сумма: {$amount} ₽\n";

    // Создаём выплату
    try {
        $result = dbExecute(
            "INSERT INTO payments
             (teacher_id, lesson_template_id, amount, payment_type, status,
              calculation_method, notes, created_at)
             VALUES (?, ?, ?, 'lesson', 'pending', ?, ?, ?)",
            [
                $lesson['teacher_id'],
                $lesson['id'],
                $amount,
                "{$studentCount} из {$studentCount} учеников",
                "Создано скриптом за {$date}",
                $date . ' ' . $lesson['time_start']
            ]
        );

        echo "  ✓ Выплата создана\n";
        $created++;

    } catch (Exception $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Результат ===\n";
echo "Создано: {$created}\n";
echo "Пропущено: {$skipped}\n";
echo "Ошибок: {$errors}\n";
echo "</pre>";

/**
 * Рассчитать выплату по формуле
 */
function calculatePayment($formula, $studentCount) {
    $type = $formula['type'];

    switch ($type) {
        case 'fixed':
            return (int)$formula['fixed_amount'];

        case 'min_plus_per':
            $minPayment = (int)$formula['min_payment'];
            $perStudent = (int)$formula['per_student'];
            $threshold = (int)($formula['threshold'] ?? 1);

            // Считаем студентов сверх порога
            $extraStudents = max(0, $studentCount - $threshold);
            return $minPayment + ($extraStudents * $perStudent);

        case 'expression':
            // Простой eval для выражений типа "max(500, N * 150)"
            $expression = $formula['expression'];
            $N = $studentCount;

            // Заменяем N на значение
            $expression = str_replace('N', $N, $expression);

            // Безопасный eval для простых математических выражений
            try {
                // Разрешаем только математические функции
                $allowedFunctions = ['max', 'min', 'abs', 'round', 'floor', 'ceil'];
                foreach ($allowedFunctions as $func) {
                    if (strpos($expression, $func) !== false) {
                        // OK
                    }
                }
                $result = eval("return {$expression};");
                return (int)$result;
            } catch (Exception $e) {
                error_log("Failed to evaluate expression: {$expression}");
                return 0;
            }

        default:
            return 0;
    }
}
