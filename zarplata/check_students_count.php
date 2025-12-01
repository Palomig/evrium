<?php
/**
 * Проверка списков студентов в шаблонах и уроках
 */

require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== ПРОВЕРКА СТУДЕНТОВ В ШАБЛОНАХ И УРОКАХ ===\n\n";

// 1. Проверяем шаблоны на понедельник 15:00
echo "1. ШАБЛОН ПОНЕДЕЛЬНИК 15:00\n";
echo str_repeat("-", 70) . "\n";

$template = dbQueryOne("
    SELECT
        id,
        teacher_id,
        day_of_week,
        time_start,
        subject,
        lesson_type,
        expected_students,
        students,
        formula_id
    FROM lessons_template
    WHERE day_of_week = 1 AND time_start = '15:00:00' AND active = 1
", []);

if ($template) {
    echo "Template ID: {$template['id']}\n";
    echo "Тип: {$template['lesson_type']}\n";
    echo "Предмет: {$template['subject']}\n";
    echo "expected_students: {$template['expected_students']}\n";
    echo "students (JSON): {$template['students']}\n";

    $students = json_decode($template['students'], true);
    if (is_array($students)) {
        echo "Реальное количество учеников: " . count($students) . "\n";
        echo "Список учеников:\n";
        foreach ($students as $i => $student) {
            echo "  " . ($i + 1) . ". {$student}\n";
        }
    }
} else {
    echo "Шаблон не найден!\n";
}

// 2. Урок на 2025-12-01 15:00
echo "\n\n2. УРОК 2025-12-01 15:00\n";
echo str_repeat("-", 70) . "\n";

$lesson = dbQueryOne("
    SELECT
        li.id,
        li.template_id,
        li.lesson_date,
        li.time_start,
        li.subject,
        li.lesson_type,
        li.expected_students,
        li.actual_students,
        li.status,
        li.formula_id,
        lt.students as template_students
    FROM lessons_instance li
    LEFT JOIN lessons_template lt ON li.template_id = lt.id
    WHERE li.lesson_date = '2025-12-01' AND li.time_start = '15:00:00'
", []);

if ($lesson) {
    echo "Lesson ID: {$lesson['id']}\n";
    echo "Template ID: {$lesson['template_id']}\n";
    echo "Статус: {$lesson['status']}\n";
    echo "expected_students: {$lesson['expected_students']}\n";
    echo "actual_students: {$lesson['actual_students']}\n";

    echo "\nСтуденты из шаблона:\n";
    $students = json_decode($lesson['template_students'], true);
    if (is_array($students)) {
        echo "Количество: " . count($students) . "\n";
        foreach ($students as $i => $student) {
            echo "  " . ($i + 1) . ". {$student}\n";
        }
    }
} else {
    echo "Урок не найден!\n";
}

// 3. Все групповые шаблоны
echo "\n\n3. ВСЕ ГРУППОВЫЕ ШАБЛОНЫ\n";
echo str_repeat("-", 70) . "\n";

$templates = dbQuery("
    SELECT
        id,
        day_of_week,
        time_start,
        subject,
        expected_students,
        students
    FROM lessons_template
    WHERE lesson_type = 'group' AND active = 1
    ORDER BY day_of_week, time_start
", []);

$days = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

foreach ($templates as $t) {
    $students = json_decode($t['students'], true);
    $realCount = is_array($students) ? count($students) : 0;

    echo "{$days[$t['day_of_week']]} {$t['time_start']} | {$t['subject']} | ";
    echo "expected={$t['expected_students']}, реально={$realCount}";

    if ($t['expected_students'] != $realCount) {
        echo " ⚠️ НЕСООТВЕТСТВИЕ!";
    }
    echo "\n";
}

echo "\n=== ПРОВЕРКА ЗАВЕРШЕНА ===\n";
