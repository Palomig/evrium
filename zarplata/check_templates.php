<?php
/**
 * Проверка шаблонов расписания
 */

require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== ПРОВЕРКА ШАБЛОНОВ И ПРЕПОДАВАТЕЛЕЙ ===\n\n";

// 1. Преподаватели
echo "1. ПРЕПОДАВАТЕЛИ\n";
echo str_repeat("-", 70) . "\n";

$teachers = dbQuery("
    SELECT
        t.id,
        t.name,
        t.formula_id,
        pf.name as formula_name,
        pf.type as formula_type
    FROM teachers t
    LEFT JOIN payment_formulas pf ON t.formula_id = pf.id
    WHERE t.active = 1
", []);

foreach ($teachers as $t) {
    echo "ID {$t['id']}: {$t['name']}\n";
    echo "  formula_id: " . ($t['formula_id'] ?: 'НЕТ!') . "\n";
    if ($t['formula_id']) {
        echo "  формула: {$t['formula_name']} ({$t['formula_type']})\n";
    }
    echo "\n";
}

// 2. Шаблоны
echo "\n2. ШАБЛОНЫ РАСПИСАНИЯ (первые 10)\n";
echo str_repeat("-", 70) . "\n";

$templates = dbQuery("
    SELECT
        lt.id,
        lt.teacher_id,
        lt.day_of_week,
        lt.time_start,
        lt.subject,
        lt.lesson_type,
        lt.expected_students,
        lt.formula_id,
        t.name as teacher_name,
        pf.name as formula_name
    FROM lessons_template lt
    LEFT JOIN teachers t ON lt.teacher_id = t.id
    LEFT JOIN payment_formulas pf ON lt.formula_id = pf.id
    WHERE lt.active = 1
    ORDER BY lt.day_of_week, lt.time_start
    LIMIT 10
", []);

$days = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

foreach ($templates as $tpl) {
    echo "Template ID {$tpl['id']}: {$days[$tpl['day_of_week']]} {$tpl['time_start']}\n";
    echo "  Преподаватель: {$tpl['teacher_name']} (ID={$tpl['teacher_id']})\n";
    echo "  Предмет: " . ($tpl['subject'] ?: 'НЕТ!') . "\n";
    echo "  Тип: {$tpl['lesson_type']}\n";
    echo "  Учеников: {$tpl['expected_students']}\n";
    echo "  formula_id шаблона: " . ($tpl['formula_id'] ?: 'НЕТ') . "\n";
    if ($tpl['formula_name']) {
        echo "  формула: {$tpl['formula_name']}\n";
    }
    echo "\n";
}

// 3. Формулы
echo "\n3. ФОРМУЛЫ ВЫПЛАТ\n";
echo str_repeat("-", 70) . "\n";

$formulas = dbQuery("
    SELECT *
    FROM payment_formulas
    WHERE active = 1
", []);

foreach ($formulas as $f) {
    echo "ID {$f['id']}: {$f['name']} ({$f['type']})\n";
    if ($f['type'] === 'min_plus_per') {
        echo "  min={$f['min_payment']}, per_student={$f['per_student']}, threshold={$f['threshold']}\n";
    } elseif ($f['type'] === 'fixed') {
        echo "  fixed_amount={$f['fixed_amount']}\n";
    }
    echo "\n";
}

echo "\n=== ПРОВЕРКА ЗАВЕРШЕНА ===\n";
