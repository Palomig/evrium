<?php
/**
 * API для генерации выплат за конкретную дату
 * ⭐ ЕДИНЫЙ ИСТОЧНИК: students.schedule JSON
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/student_helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Проверяем авторизацию
session_start();
if (!isLoggedIn()) {
    jsonError('Необходима авторизация', 401);
}

// Получаем данные
$input = json_decode(file_get_contents('php://input'), true);
$date = $input['date'] ?? null;
$clear = $input['clear'] ?? false;

if (!$date) {
    jsonError('Не указана дата', 400);
}

// Валидируем формат даты
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonError('Неверный формат даты', 400);
}

$dayOfWeek = (int)date('N', strtotime($date));
$dayNames = [1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'];

$details = [];
$created = 0;
$skipped = 0;
$errors = 0;

try {
    // Очистка старых записей если указан clear
    if ($clear) {
        // Сначала удаляем payments (они ссылаются на lessons_instance)
        $deletedPayments = dbExecute(
            "DELETE FROM payments WHERE DATE(created_at) = ? OR lesson_instance_id IN
             (SELECT id FROM lessons_instance WHERE lesson_date = ?)",
            [$date, $date]
        );

        // Затем удаляем lessons_instance
        $deletedLessons = dbExecute(
            "DELETE FROM lessons_instance WHERE lesson_date = ?",
            [$date]
        );

        $details[] = "🗑 Удалено: выплат {$deletedPayments}, уроков {$deletedLessons}";
    }

    // ШАГ 1: Получаем все уникальные уроки из students.schedule
    $allStudents = dbQuery(
        "SELECT id, name, class, schedule, teacher_id FROM students WHERE active = 1 AND schedule IS NOT NULL",
        []
    );

    // Собираем уникальные слоты: [teacher_id][time] = данные
    $uniqueLessons = [];

    foreach ($allStudents as $student) {
        $schedule = json_decode($student['schedule'], true);
        if (!is_array($schedule)) continue;

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
        jsonSuccess([
            'date' => $date,
            'day' => $dayNames[$dayOfWeek],
            'created' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => ["⚠ Нет уроков на {$dayNames[$dayOfWeek]}"]
        ]);
    }

    // Сортируем по времени
    usort($uniqueLessons, fn($a, $b) => strcmp($a['time'], $b['time']));

    $details[] = "📋 Найдено уроков: " . count($uniqueLessons);

    // ШАГ 2: Получаем информацию о преподавателях
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

    foreach ($uniqueLessons as $lesson) {
        $teacherId = $lesson['teacher_id'];
        $time = $lesson['time'];
        $subject = $subjectMap[$lesson['subject']] ?? $lesson['subject'];

        $teacherName = $teachers[$teacherId]['name'] ?? "Преподаватель #{$teacherId}";

        // Проверяем, есть ли уже lessons_instance за этот день/время/учителя
        $existingLesson = dbQueryOne(
            "SELECT li.id, p.id as payment_id
             FROM lessons_instance li
             LEFT JOIN payments p ON p.lesson_instance_id = li.id
             WHERE li.teacher_id = ? AND li.lesson_date = ? AND li.time_start = ?",
            [$teacherId, $date, $time . ':00']
        );

        if ($existingLesson) {
            $details[] = "⚠ {$time} ({$teacherName}): уже существует";
            $skipped++;
            continue;
        }

        // Получаем учеников через единую функцию
        $studentsData = getStudentsForLesson($teacherId, $dayOfWeek, $time);
        $studentCount = $studentsData['count'];
        $studentNames = array_column($studentsData['students'], 'name');

        if ($studentCount == 0) {
            $details[] = "⚠ {$time} ({$teacherName}): нет учеников";
            $skipped++;
            continue;
        }

        // Определяем тип урока и формулу
        $lessonType = $studentCount > 1 ? 'group' : 'individual';

        $teacher = $teachers[$teacherId] ?? null;
        if (!$teacher) {
            $details[] = "⚠ {$time}: преподаватель не найден";
            $skipped++;
            continue;
        }

        $formulaId = $studentCount > 1
            ? ($teacher['formula_id_group'] ?? $teacher['formula_id'])
            : ($teacher['formula_id_individual'] ?? $teacher['formula_id']);

        if (!$formulaId) {
            $details[] = "⚠ {$time} ({$teacherName}): нет формулы";
            $skipped++;
            continue;
        }

        $formula = dbQueryOne("SELECT * FROM payment_formulas WHERE id = ? AND active = 1", [$formulaId]);
        if (!$formula) {
            $details[] = "⚠ {$time} ({$teacherName}): формула не найдена";
            $skipped++;
            continue;
        }

        // Рассчитываем выплату
        $amount = calculatePayment($formula, $studentCount);

        try {
            // Создаём lessons_instance
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
                throw new Exception("Не удалось создать урок");
            }

            // Создаём payment, связанную с уроком
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
                    "Создано вручную",
                    $date . ' ' . $time . ':00'
                ]
            );

            $details[] = "✓ {$time} ({$teacherName}): {$studentCount} уч., {$amount} ₽";
            $created++;

        } catch (Exception $e) {
            $details[] = "✗ {$time} ({$teacherName}): " . $e->getMessage();
            $errors++;
        }
    }

    jsonSuccess([
        'date' => $date,
        'day' => $dayNames[$dayOfWeek],
        'created' => $created,
        'skipped' => $skipped,
        'errors' => $errors,
        'details' => $details
    ]);

} catch (Exception $e) {
    jsonError('Ошибка: ' . $e->getMessage(), 500);
}
