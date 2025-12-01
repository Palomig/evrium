<?php
/**
 * Страница диагностики данных для проверки уроков и выплат
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

requireAuth();

header('Content-Type: text/plain; charset=utf-8');

echo "=== ДИАГНОСТИКА ДАННЫХ ZARPLATA ===\n\n";

// 1. Проверка lessons_instance
echo "1. ПРОВЕРКА LESSONS_INSTANCE\n";
echo str_repeat("-", 50) . "\n";

$totalLessons = dbQueryOne("SELECT COUNT(*) as cnt FROM lessons_instance", []);
echo "Всего уроков в базе: " . $totalLessons['cnt'] . "\n\n";

if ($totalLessons['cnt'] > 0) {
    // Статистика по статусам
    echo "Статистика по статусам:\n";
    $statuses = dbQuery("
        SELECT status, COUNT(*) as cnt
        FROM lessons_instance
        GROUP BY status
        ORDER BY cnt DESC
    ", []);
    foreach ($statuses as $s) {
        echo "  - {$s['status']}: {$s['cnt']}\n";
    }
    echo "\n";

    // Даты уроков
    echo "Диапазон дат:\n";
    $dateRange = dbQueryOne("
        SELECT
            MIN(lesson_date) as min_date,
            MAX(lesson_date) as max_date
        FROM lessons_instance
    ", []);
    echo "  - Первый урок: {$dateRange['min_date']}\n";
    echo "  - Последний урок: {$dateRange['max_date']}\n\n";

    // Уроки за последние 3 месяца
    echo "Уроки за последние 3 месяца:\n";
    $recent = dbQuery("
        SELECT
            DATE_FORMAT(lesson_date, '%Y-%m') as month,
            status,
            COUNT(*) as cnt
        FROM lessons_instance
        WHERE lesson_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        GROUP BY month, status
        ORDER BY month DESC, status
    ", []);

    if (empty($recent)) {
        echo "  ⚠️ НЕТ УРОКОВ за последние 3 месяца!\n";
        echo "  Текущая дата: " . date('Y-m-d') . "\n";
        echo "  Минимальная дата для фильтра: " . date('Y-m-d', strtotime('-3 months')) . "\n\n";
    } else {
        foreach ($recent as $r) {
            echo "  - {$r['month']}: {$r['status']} = {$r['cnt']}\n";
        }
        echo "\n";
    }

    // Последние 10 уроков
    echo "Последние 10 уроков:\n";
    $lastLessons = dbQuery("
        SELECT
            id,
            lesson_date,
            time_start,
            status,
            subject,
            teacher_id
        FROM lessons_instance
        ORDER BY lesson_date DESC, time_start DESC
        LIMIT 10
    ", []);
    foreach ($lastLessons as $l) {
        echo "  - ID {$l['id']}: {$l['lesson_date']} {$l['time_start']} | {$l['status']} | {$l['subject']} | teacher={$l['teacher_id']}\n";
    }
    echo "\n";
}

// 2. Проверка lessons_template
echo "\n2. ПРОВЕРКА LESSONS_TEMPLATE\n";
echo str_repeat("-", 50) . "\n";

$totalTemplates = dbQueryOne("SELECT COUNT(*) as cnt FROM lessons_template WHERE active = 1", []);
echo "Активных шаблонов: " . $totalTemplates['cnt'] . "\n\n";

if ($totalTemplates['cnt'] > 0) {
    echo "Шаблоны по дням недели:\n";
    $templatesByDay = dbQuery("
        SELECT
            day_of_week,
            COUNT(*) as cnt
        FROM lessons_template
        WHERE active = 1
        GROUP BY day_of_week
        ORDER BY day_of_week
    ", []);

    $days = ['', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
    foreach ($templatesByDay as $t) {
        $dayName = $days[$t['day_of_week']] ?? "День {$t['day_of_week']}";
        echo "  - {$dayName}: {$t['cnt']}\n";
    }
    echo "\n";
}

// 3. Проверка teachers
echo "\n3. ПРОВЕРКА TEACHERS\n";
echo str_repeat("-", 50) . "\n";

$teachers = dbQuery("SELECT id, name, active FROM teachers ORDER BY name", []);
echo "Всего преподавателей: " . count($teachers) . "\n";
foreach ($teachers as $t) {
    $status = $t['active'] ? '✓' : '✗';
    echo "  {$status} ID {$t['id']}: {$t['name']}\n";
}
echo "\n";

// 4. Проверка payment_formulas
echo "\n4. ПРОВЕРКА PAYMENT_FORMULAS\n";
echo str_repeat("-", 50) . "\n";

$formulas = dbQuery("SELECT id, name, type, active FROM payment_formulas ORDER BY name", []);
echo "Всего формул: " . count($formulas) . "\n";
foreach ($formulas as $f) {
    $status = $f['active'] ? '✓' : '✗';
    echo "  {$status} ID {$f['id']}: {$f['name']} ({$f['type']})\n";
}
echo "\n";

// 5. Проверка payments
echo "\n5. ПРОВЕРКА PAYMENTS\n";
echo str_repeat("-", 50) . "\n";

$totalPayments = dbQueryOne("SELECT COUNT(*) as cnt FROM payments", []);
echo "Всего записей о выплатах: " . $totalPayments['cnt'] . "\n\n";

if ($totalPayments['cnt'] > 0) {
    echo "Статистика по статусам выплат:\n";
    $paymentStatuses = dbQuery("
        SELECT status, COUNT(*) as cnt, SUM(amount) as total
        FROM payments
        GROUP BY status
        ORDER BY cnt DESC
    ", []);
    foreach ($paymentStatuses as $p) {
        echo "  - {$p['status']}: {$p['cnt']} записей, сумма {$p['total']}₽\n";
    }
}

echo "\n";
echo "=== ДИАГНОСТИКА ЗАВЕРШЕНА ===\n";
