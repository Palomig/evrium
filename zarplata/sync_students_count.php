<?php
/**
 * Утилита для синхронизации expected_students с реальным количеством в JSON
 * Запуск: php zarplata/sync_students_count.php
 */

require_once __DIR__ . '/config/db.php';

echo "=== СИНХРОНИЗАЦИЯ КОЛИЧЕСТВА СТУДЕНТОВ ===\n\n";

// Получить все шаблоны
$templates = dbQuery(
    "SELECT id, teacher_id, day_of_week, time_start, subject, expected_students, students
     FROM lessons_template
     WHERE active = 1",
    []
);

$updated = 0;
$skipped = 0;
$total = count($templates);

echo "Найдено шаблонов: $total\n\n";

foreach ($templates as $template) {
    $templateId = $template['id'];
    $expectedStudents = (int)$template['expected_students'];
    $studentsJson = $template['students'];

    // Парсим JSON
    $students = [];
    if ($studentsJson) {
        $studentsData = json_decode($studentsJson, true);
        if (is_array($studentsData)) {
            $students = $studentsData;
        }
    }

    $realCount = count($students);

    // День недели
    $days = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    $dayName = $days[$template['day_of_week']] ?? '?';

    echo sprintf(
        "ID=%d | %s %s | %s | expected=%d, реально=%d",
        $templateId,
        $dayName,
        substr($template['time_start'], 0, 5),
        $template['subject'] ?: '(без предмета)',
        $expectedStudents,
        $realCount
    );

    // Если количество не совпадает - обновляем
    if ($expectedStudents !== $realCount) {
        echo " ⚠️  ОБНОВЛЕНИЕ...\n";

        $result = dbExecute(
            "UPDATE lessons_template SET expected_students = ? WHERE id = ?",
            [$realCount, $templateId]
        );

        if ($result !== false) {
            echo "   ✅ Обновлено: $expectedStudents → $realCount\n";
            $updated++;
        } else {
            echo "   ❌ ОШИБКА при обновлении\n";
        }
    } else {
        echo " ✓ OK\n";
        $skipped++;
    }
}

echo "\n=== ИТОГИ ===\n";
echo "Всего шаблонов: $total\n";
echo "Обновлено: $updated\n";
echo "Без изменений: $skipped\n";
echo "\n=== СИНХРОНИЗАЦИЯ ЗАВЕРШЕНА ===\n";
