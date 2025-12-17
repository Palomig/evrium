<?php
/**
 * Миграция: Добавить предмет "Мат." к расписанию всех учеников
 *
 * Для учеников, у которых в расписании не указан предмет,
 * устанавливается "Мат." (Математика) по умолчанию.
 */

require_once __DIR__ . '/../config/db.php';

echo "=== Миграция: Добавление предмета в расписание учеников ===\n\n";

// Получаем всех учеников с расписанием
$students = dbQuery("SELECT id, name, schedule FROM students WHERE schedule IS NOT NULL AND schedule != ''");

$updated = 0;
$skipped = 0;
$errors = 0;

foreach ($students as $student) {
    $schedule = json_decode($student['schedule'], true);

    if (!$schedule || !is_array($schedule)) {
        echo "⚠ Пропуск {$student['name']} (ID: {$student['id']}): некорректный JSON\n";
        $skipped++;
        continue;
    }

    $needsUpdate = false;

    // Проходим по всем дням
    foreach ($schedule as $day => &$lessons) {
        // Если lessons - массив уроков
        if (is_array($lessons)) {
            foreach ($lessons as &$lesson) {
                if (is_array($lesson) && !isset($lesson['subject'])) {
                    $lesson['subject'] = 'Мат.';
                    $needsUpdate = true;
                }
            }
        }
    }
    unset($lessons, $lesson);

    if ($needsUpdate) {
        $newSchedule = json_encode($schedule, JSON_UNESCAPED_UNICODE);

        try {
            dbExecute(
                "UPDATE students SET schedule = ? WHERE id = ?",
                [$newSchedule, $student['id']]
            );
            echo "✓ Обновлен: {$student['name']} (ID: {$student['id']})\n";
            $updated++;
        } catch (Exception $e) {
            echo "✗ Ошибка для {$student['name']}: {$e->getMessage()}\n";
            $errors++;
        }
    } else {
        echo "- Пропуск {$student['name']} (ID: {$student['id']}): предмет уже указан\n";
        $skipped++;
    }
}

echo "\n=== Результат ===\n";
echo "Обновлено: $updated\n";
echo "Пропущено: $skipped\n";
echo "Ошибок: $errors\n";
