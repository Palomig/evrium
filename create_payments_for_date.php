<?php
/**
 * Скрипт для создания уроков и выплат за указанную дату
 * ⭐ ЕДИНЫЙ ИСТОЧНИК: students.schedule JSON
 *
 * Создаёт:
 * 1. lessons_instance - запись урока
 * 2. payments - выплата, связанная с уроком
 *
 * Использование: create_payments_for_date.php?date=2024-12-05
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/zarplata/config/db.php';
require_once __DIR__ . '/zarplata/config/helpers.php';
require_once __DIR__ . '/zarplata/config/student_helpers.php';

// Определяем дату и флаги
$date = $_GET['date'] ?? date('Y-m-d');
$clear = isset($_GET['clear']) && $_GET['clear'] == '1';
$deleteOnly = isset($_GET['delete_only']) && $_GET['delete_only'] == '1';
$dayOfWeek = (int)date('N', strtotime($date));

$dayNames = [1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'];

echo "<pre>\n";
echo "=== " . ($deleteOnly ? "Удаление" : "Создание уроков и выплат") . " за {$date} ({$dayNames[$dayOfWeek]}) ===\n\n";

// ⭐ Очистка старых записей если указан clear=1 или delete_only=1
if ($clear || $deleteOnly) {
    echo "🗑 Очистка записей за {$date}...\n";

    // Сначала удаляем payments (они ссылаются на lessons_instance)
    $deletedPayments = dbExecute(
        "DELETE FROM payments WHERE DATE(created_at) = ? OR lesson_instance_id IN
         (SELECT id FROM lessons_instance WHERE lesson_date = ?)",
        [$date, $date]
    );
    echo "  Удалено выплат: " . ($deletedPayments ?: 0) . "\n";

    // Затем удаляем lessons_instance
    $deletedLessons = dbExecute(
        "DELETE FROM lessons_instance WHERE lesson_date = ?",
        [$date]
    );
    echo "  Удалено уроков: " . ($deletedLessons ?: 0) . "\n\n";

    if ($deleteOnly) {
        echo "✓ Готово (режим только удаление)\n";
        echo "</pre>";
        exit;
    }
}

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

    // Проверяем формат: {"4": [{"time": "15:00", "teacher_id": 5, ...}]}
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

// Маппинг предметов
$subjectMap = [
    'Мат.' => 'Математика',
    'Физ.' => 'Физика',
    'Инф.' => 'Информатика'
];

$created = 0;
$skipped = 0;
$errors = 0;

foreach ($uniqueLessons as $lesson) {
    $teacherId = $lesson['teacher_id'];
    $time = $lesson['time'];
    $subject = $subjectMap[$lesson['subject']] ?? $lesson['subject'];

    $teacherName = $teachers[$teacherId]['name'] ?? "Преподаватель #{$teacherId}";

    echo "--- Урок {$time} ({$teacherName}) ---\n";

    // Проверяем, есть ли уже lessons_instance за этот день/время/учителя
    $existingLesson = dbQueryOne(
        "SELECT li.id, p.id as payment_id
         FROM lessons_instance li
         LEFT JOIN payments p ON p.lesson_instance_id = li.id
         WHERE li.teacher_id = ? AND li.lesson_date = ? AND li.time_start = ?",
        [$teacherId, $date, $time . ':00']
    );

    if ($existingLesson) {
        echo "  ⚠ Урок уже существует (ID: {$existingLesson['id']}";
        if ($existingLesson['payment_id']) {
            echo ", выплата: {$existingLesson['payment_id']}";
        }
        echo "), пропуск\n";
        $skipped++;
        continue;
    }

    // ⭐ Получаем учеников через единую функцию
    $studentsData = getStudentsForLesson($teacherId, $dayOfWeek, $time);
    $studentCount = $studentsData['count'];
    $studentNames = array_column($studentsData['students'], 'name');

    echo "  Учеников: {$studentCount}";
    if ($studentCount > 0) {
        echo " (" . implode(', ', $studentNames) . ")";
    }
    echo "\n";
    echo "  Предмет: {$subject}\n";

    if ($studentCount == 0) {
        echo "  ⚠ Нет учеников, пропуск\n";
        $skipped++;
        continue;
    }

    // Определяем тип урока и формулу
    $lessonType = $studentCount > 1 ? 'group' : 'individual';

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
    echo "  Тип: {$lessonType}\n";
    echo "  Формула: {$formula['name']}\n";
    echo "  Сумма: {$amount} ₽\n";

    try {
        // ⭐ ШАГ 1: Создаём lessons_instance
        $timeEnd = date('H:i', strtotime($time) + 3600); // +1 час

        $lessonInstanceId = dbExecute(
            "INSERT INTO lessons_instance
             (teacher_id, lesson_date, time_start, time_end, lesson_type, subject,
              expected_students, actual_students, formula_id, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())",
            [
                $teacherId,
                $date,
                $time . ':00',
                $timeEnd . ':00',
                $lessonType,
                $subject,
                $studentCount,
                $studentCount,
                $formulaId,
                "Ученики: " . implode(', ', $studentNames)
            ]
        );

        if (!$lessonInstanceId) {
            throw new Exception("Не удалось создать lessons_instance");
        }

        echo "  ✓ Урок создан (ID: {$lessonInstanceId})\n";

        // ⭐ ШАГ 2: Создаём payment, связанную с уроком
        $paymentId = dbExecute(
            "INSERT INTO payments
             (teacher_id, lesson_instance_id, amount, payment_type, status,
              calculation_method, notes, created_at)
             VALUES (?, ?, ?, 'lesson', 'pending', ?, ?, ?)",
            [
                $teacherId,
                $lessonInstanceId,
                $amount,
                "{$studentCount} из {$studentCount} учеников",
                "Создано скриптом",
                $date . ' ' . $time . ':00'
            ]
        );

        echo "  ✓ Выплата создана (ID: {$paymentId})\n";
        $created++;

    } catch (Exception $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Результат ===\n";
echo "Создано уроков и выплат: {$created}\n";
echo "Пропущено: {$skipped}\n";
echo "Ошибок: {$errors}\n";
echo "</pre>";
