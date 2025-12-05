<?php
/**
 * Скрипт для создания выплат за указанную дату
 * ⭐ ЕДИНЫЙ ИСТОЧНИК: students.schedule JSON
 *
 * Использование: create_payments_for_date.php?date=2024-12-05
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/zarplata/config/db.php';
require_once __DIR__ . '/zarplata/config/helpers.php';
require_once __DIR__ . '/zarplata/config/student_helpers.php';

// Определяем дату
$date = $_GET['date'] ?? date('Y-m-d');
$dayOfWeek = (int)date('N', strtotime($date));

$dayNames = [1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'];

echo "<pre>\n";
echo "=== Создание выплат за {$date} ({$dayNames[$dayOfWeek]}, день {$dayOfWeek}) ===\n\n";

// ⭐ ШАГ 1: Получаем ВСЕ уникальные уроки из students.schedule
$allStudents = dbQuery(
    "SELECT id, name, class, schedule, teacher_id FROM students WHERE active = 1 AND schedule IS NOT NULL",
    []
);

// Собираем уникальные слоты: [teacher_id][time] = данные
$uniqueLessons = [];

foreach ($allStudents as $student) {
    $schedule = json_decode($student['schedule'], true);
    if (!is_array($schedule)) continue;

    // Проверяем формат 3: {"4": [{"time": "15:00", "teacher_id": 5, ...}]}
    if (isset($schedule[$dayOfWeek]) && is_array($schedule[$dayOfWeek])) {
        foreach ($schedule[$dayOfWeek] as $slot) {
            if (!isset($slot['time'])) continue;

            $time = substr($slot['time'], 0, 5);
            $teacherId = isset($slot['teacher_id']) ? (int)$slot['teacher_id'] : (int)$student['teacher_id'];

            if (!$teacherId) continue;

            $key = "{$teacherId}_{$time}";
            if (!isset($uniqueLessons[$key])) {
                $uniqueLessons[$key] = [
                    'teacher_id' => $teacherId,
                    'time' => $time,
                    'subject' => $slot['subject'] ?? 'Мат.',
                    'room' => $slot['room'] ?? 1
                ];
            }
        }
    }
}

if (empty($uniqueLessons)) {
    echo "Нет уроков на этот день (по данным students.schedule)\n";
    echo "</pre>";
    exit;
}

// Сортируем по времени
usort($uniqueLessons, fn($a, $b) => strcmp($a['time'], $b['time']));

echo "Найдено уникальных уроков: " . count($uniqueLessons) . "\n\n";

// ⭐ ШАГ 2: Получаем информацию о преподавателях
$teachers = [];
$teacherRows = dbQuery("SELECT id, name, formula_id_group, formula_id_individual, formula_id FROM teachers WHERE active = 1", []);
foreach ($teacherRows as $t) {
    $teachers[$t['id']] = $t;
}

$created = 0;
$skipped = 0;
$errors = 0;

foreach ($uniqueLessons as $lesson) {
    $teacherId = $lesson['teacher_id'];
    $time = $lesson['time'];
    $subject = $lesson['subject'];

    $teacherName = $teachers[$teacherId]['name'] ?? "Преподаватель #{$teacherId}";

    echo "--- Урок {$time} ({$teacherName}) ---\n";

    // Проверяем, есть ли уже выплата
    $existingPayment = dbQueryOne(
        "SELECT id FROM payments
         WHERE teacher_id = ? AND DATE(created_at) = ?
         AND notes LIKE ?",
        [$teacherId, $date, "%{$time}%"]
    );

    if ($existingPayment) {
        echo "  ⚠ Выплата уже существует (ID: {$existingPayment['id']}), пропуск\n";
        $skipped++;
        continue;
    }

    // ⭐ Получаем учеников через единую функцию
    $studentsData = getStudentsForLesson($teacherId, $dayOfWeek, $time);
    $studentCount = $studentsData['count'];

    echo "  Учеников: {$studentCount}\n";
    echo "  Предмет: {$subject}\n";

    if ($studentCount == 0) {
        echo "  ⚠ Нет учеников, пропуск\n";
        $skipped++;
        continue;
    }

    // Определяем формулу (групповая если > 1 ученика)
    $teacher = $teachers[$teacherId] ?? null;
    if (!$teacher) {
        echo "  ⚠ Преподаватель не найден\n";
        $skipped++;
        continue;
    }

    $formulaId = $studentCount > 1
        ? ($teacher['formula_id_group'] ?? $teacher['formula_id'])
        : ($teacher['formula_id_individual'] ?? $teacher['formula_id']);

    if (!$formulaId) {
        echo "  ⚠ Нет формулы для преподавателя\n";
        $skipped++;
        continue;
    }

    $formula = dbQueryOne("SELECT * FROM payment_formulas WHERE id = ? AND active = 1", [$formulaId]);
    if (!$formula) {
        echo "  ⚠ Формула #{$formulaId} не найдена\n";
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
             (teacher_id, amount, payment_type, status, calculation_method, notes, created_at)
             VALUES (?, ?, 'lesson', 'pending', ?, ?, ?)",
            [
                $teacherId,
                $amount,
                "{$studentCount} учеников",
                "Урок {$time}, {$subject}",
                $date . ' ' . $time . ':00'
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
