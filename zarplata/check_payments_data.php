<?php
/**
 * Проверка данных для страницы выплат
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

requireAuth();

header('Content-Type: text/plain; charset=utf-8');

echo "=== ПРОВЕРКА ДАННЫХ ДЛЯ СТРАНИЦЫ ВЫПЛАТ ===\n\n";

// 1. Уроки за последние 3 месяца
echo "1. УРОКИ ЗА ПОСЛЕДНИЕ 3 МЕСЯЦА\n";
echo str_repeat("-", 50) . "\n";

$lessons = dbQuery("
    SELECT
        DATE_FORMAT(lesson_date, '%Y-%m') as month,
        status,
        COUNT(*) as cnt
    FROM lessons_instance
    WHERE lesson_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY month, status
    ORDER BY month DESC, status
", []);

foreach ($lessons as $l) {
    echo "{$l['month']}: {$l['status']} = {$l['cnt']} уроков\n";
}

// 2. Выплаты
echo "\n\n2. ВЫПЛАТЫ\n";
echo str_repeat("-", 50) . "\n";

$payments = dbQuery("
    SELECT
        p.id,
        p.lesson_instance_id,
        p.amount,
        p.status,
        li.lesson_date,
        li.subject,
        t.name as teacher_name
    FROM payments p
    LEFT JOIN lessons_instance li ON p.lesson_instance_id = li.id
    LEFT JOIN teachers t ON p.teacher_id = t.id
    ORDER BY p.id DESC
", []);

echo "Всего выплат: " . count($payments) . "\n\n";
foreach ($payments as $p) {
    echo "ID {$p['id']}: {$p['amount']}₽ | {$p['status']} | lesson_id={$p['lesson_instance_id']} | {$p['lesson_date']} | {$p['subject']} | {$p['teacher_name']}\n";
}

// 3. JOIN как в payments.php
echo "\n\n3. ДАННЫЕ ДЛЯ PAYMENTS.PHP (как в реальном запросе)\n";
echo str_repeat("-", 50) . "\n";

$realData = dbQuery("
    SELECT
        li.id as lesson_id,
        li.lesson_date,
        li.time_start,
        li.status as lesson_status,
        li.subject,
        li.expected_students,
        t.name as teacher_name,
        p.id as payment_id,
        p.amount as payment_amount,
        p.status as payment_status,
        COALESCE(li.formula_id, t.formula_id) as formula_id,
        pf.type as formula_type,
        pf.min_payment,
        pf.per_student,
        pf.threshold
    FROM lessons_instance li
    LEFT JOIN teachers t ON li.teacher_id = t.id
    LEFT JOIN payments p ON li.id = p.lesson_instance_id
    LEFT JOIN lessons_template lt ON li.template_id = lt.id
    LEFT JOIN payment_formulas pf ON COALESCE(li.formula_id, t.formula_id) = pf.id
    WHERE (li.status = 'completed' OR li.status = 'scheduled')
        AND li.lesson_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    ORDER BY li.lesson_date DESC, li.time_start ASC
    LIMIT 20
", []);

echo "Найдено записей: " . count($realData) . "\n\n";
foreach ($realData as $d) {
    echo "{$d['lesson_date']} {$d['time_start']} | {$d['lesson_status']} | {$d['subject']} | {$d['teacher_name']}\n";
    echo "  payment_id: {$d['payment_id']}, amount: {$d['payment_amount']}, status: {$d['payment_status']}\n";
    echo "  formula: {$d['formula_type']} (id={$d['formula_id']}), expected_students: {$d['expected_students']}\n";
    if ($d['formula_type'] === 'min_plus_per') {
        echo "  min={$d['min_payment']}, per={$d['per_student']}, threshold={$d['threshold']}\n";
    }
    echo "\n";
}

// 4. Проверка текущей недели
echo "\n4. ТЕКУЩАЯ НЕДЕЛЯ\n";
echo str_repeat("-", 50) . "\n";

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

echo "Сегодня: $today\n";
echo "Неделя: $weekStart — $weekEnd\n\n";

$thisWeek = dbQuery("
    SELECT
        li.lesson_date,
        li.time_start,
        li.subject,
        li.status,
        p.amount,
        t.name as teacher_name
    FROM lessons_instance li
    LEFT JOIN teachers t ON li.teacher_id = t.id
    LEFT JOIN payments p ON li.id = p.lesson_instance_id
    WHERE li.lesson_date BETWEEN ? AND ?
    ORDER BY li.lesson_date, li.time_start
", [$weekStart, $weekEnd]);

echo "Уроков на этой неделе: " . count($thisWeek) . "\n\n";
$totalAmount = 0;
foreach ($thisWeek as $lesson) {
    $totalAmount += (int)($lesson['amount'] ?? 0);
    echo "{$lesson['lesson_date']} {$lesson['time_start']} | {$lesson['status']} | {$lesson['subject']} | {$lesson['teacher_name']} | {$lesson['amount']}₽\n";
}
echo "\nИтого за неделю: {$totalAmount}₽\n";

echo "\n=== ПРОВЕРКА ЗАВЕРШЕНА ===\n";
