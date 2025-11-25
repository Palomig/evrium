<?php
/**
 * Страница выплат преподавателям
 * Новый дизайн: STYLEGUIDE.md (Teal accent, Nunito + JetBrains Mono)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить фильтр по преподавателю
$teacherFilter = isset($_GET['teacher']) ? (int)$_GET['teacher'] : 0;

// Получить список всех активных преподавателей
$teachers = dbQuery(
    "SELECT id, name FROM teachers WHERE active = 1 ORDER BY name",
    []
);

// Базовый SQL для выборки уроков с выплатами
$whereClauses = ["li.status = 'completed'"];
$params = [];

if ($teacherFilter > 0) {
    $whereClauses[] = "li.teacher_id = ?";
    $params[] = $teacherFilter;
}

$whereSQL = implode(' AND ', $whereClauses);

// Получить завершённые уроки с выплатами за последние 3 месяца
$lessons = dbQuery(
    "SELECT
        li.id as lesson_id,
        li.lesson_date,
        li.time_start,
        li.time_end,
        li.lesson_type,
        li.subject,
        li.expected_students,
        li.actual_students,
        li.notes,
        t.id as teacher_id,
        t.name as teacher_name,
        p.id as payment_id,
        p.amount,
        p.status as payment_status,
        lt.tier,
        lt.grades,
        lt.students as students_json,
        lt.room
    FROM lessons_instance li
    LEFT JOIN teachers t ON li.teacher_id = t.id
    LEFT JOIN payments p ON li.id = p.lesson_instance_id
    LEFT JOIN lessons_template lt ON li.template_id = lt.id
    WHERE $whereSQL
        AND li.lesson_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    ORDER BY li.lesson_date DESC, li.time_start ASC",
    $params
);

// Группировка данных по месяцам, неделям и дням
$dataByMonth = [];
$monthNames = [
    'January' => 'Январь',
    'February' => 'Февраль',
    'March' => 'Март',
    'April' => 'Апрель',
    'May' => 'Май',
    'June' => 'Июнь',
    'July' => 'Июль',
    'August' => 'Август',
    'September' => 'Сентябрь',
    'October' => 'Октябрь',
    'November' => 'Ноябрь',
    'December' => 'Декабрь'
];

$weekdayNames = [
    'Mon' => 'Пн',
    'Tue' => 'Вт',
    'Wed' => 'Ср',
    'Thu' => 'Чт',
    'Fri' => 'Пт',
    'Sat' => 'Сб',
    'Sun' => 'Вс'
];

foreach ($lessons as $lesson) {
    $date = new DateTime($lesson['lesson_date']);
    $monthKey = $date->format('Y-m');
    $monthNameEn = $date->format('F');
    $monthNameRu = $monthNames[$monthNameEn] ?? $monthNameEn;
    $year = $date->format('Y');
    $monthName = "$monthNameRu $year";
    $weekNumber = $date->format('W');
    $dayKey = $lesson['lesson_date'];
    $dayName = $date->format('d M');
    $dayWeekdayEn = $date->format('D');
    $dayWeekday = $weekdayNames[$dayWeekdayEn] ?? $dayWeekdayEn;

    // Инициализация структуры месяца
    if (!isset($dataByMonth[$monthKey])) {
        $dataByMonth[$monthKey] = [
            'name' => $monthName,
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'paid' => 0,
            'weeks' => [],
            'days' => []
        ];
    }

    // Подсчёт суммы
    $amount = (int)($lesson['amount'] ?? 0);
    $dataByMonth[$monthKey]['total'] += $amount;

    if ($lesson['payment_status'] === 'pending') {
        $dataByMonth[$monthKey]['pending'] += $amount;
    } elseif ($lesson['payment_status'] === 'approved') {
        $dataByMonth[$monthKey]['approved'] += $amount;
    } elseif ($lesson['payment_status'] === 'paid') {
        $dataByMonth[$monthKey]['paid'] += $amount;
    }

    // Инициализация недели
    $weekStart = clone $date;
    $weekStart->modify('monday this week');
    $weekEnd = clone $date;
    $weekEnd->modify('sunday this week');

    if (!isset($dataByMonth[$monthKey]['weeks'][$weekNumber])) {
        $dataByMonth[$monthKey]['weeks'][$weekNumber] = [
            'start' => $weekStart->format('d'),
            'end' => $weekEnd->format('d'),
            'total' => 0,
            'paid' => 0
        ];
    }

    $dataByMonth[$monthKey]['weeks'][$weekNumber]['total'] += $amount;
    if ($lesson['payment_status'] === 'paid') {
        $dataByMonth[$monthKey]['weeks'][$weekNumber]['paid'] += $amount;
    }

    // Инициализация дня
    if (!isset($dataByMonth[$monthKey]['days'][$dayKey])) {
        $dataByMonth[$monthKey]['days'][$dayKey] = [
            'date' => $dayName,
            'weekday' => $dayWeekday,
            'total' => 0,
            'hours' => 0,
            'absences' => 0,
            'individual_count' => 0,
            'group_count' => 0,
            'subjects' => [],
            'lessons' => [],
            'all_approved' => true
        ];
    }

    // Добавление данных дня
    $duration = 0;
    if ($lesson['time_start'] && $lesson['time_end']) {
        $start = new DateTime($lesson['time_start']);
        $end = new DateTime($lesson['time_end']);
        $duration = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
    }

    $dataByMonth[$monthKey]['days'][$dayKey]['total'] += $amount;
    $dataByMonth[$monthKey]['days'][$dayKey]['hours'] += $duration;

    if ($lesson['lesson_type'] === 'individual') {
        $dataByMonth[$monthKey]['days'][$dayKey]['individual_count']++;
    } else {
        $dataByMonth[$monthKey]['days'][$dayKey]['group_count']++;
    }

    if ($lesson['subject'] && !in_array($lesson['subject'], $dataByMonth[$monthKey]['days'][$dayKey]['subjects'])) {
        $dataByMonth[$monthKey]['days'][$dayKey]['subjects'][] = $lesson['subject'];
    }

    if (!in_array($lesson['payment_status'], ['approved', 'paid'])) {
        $dataByMonth[$monthKey]['days'][$dayKey]['all_approved'] = false;
    }

    // Парсинг списка студентов
    $students = [];
    if ($lesson['students_json']) {
        $studentsData = json_decode($lesson['students_json'], true);
        if (is_array($studentsData)) {
            $students = $studentsData;
        }
    }

    // Добавление урока в день
    $dataByMonth[$monthKey]['days'][$dayKey]['lessons'][] = [
        'id' => $lesson['lesson_id'],
        'time' => substr($lesson['time_start'] ?? '', 0, 5),
        'subject' => $lesson['subject'],
        'type' => $lesson['lesson_type'],
        'students' => $students,
        'amount' => $amount,
        'payment_id' => $lesson['payment_id'],
        'payment_status' => $lesson['payment_status'],
        'tier' => $lesson['tier'],
        'duration' => $duration
    ];
}

// Подсчёт процента выплаченного для недель
foreach ($dataByMonth as $monthKey => &$month) {
    foreach ($month['weeks'] as $weekNum => &$week) {
        if ($week['total'] > 0) {
            $week['paid_percent'] = round(($week['paid'] / $week['total']) * 100);
        } else {
            $week['paid_percent'] = 0;
        }
    }
}

// Общая статистика
$totalStats = [
    'pending' => 0,
    'approved' => 0,
    'paid' => 0,
    'total' => 0
];

foreach ($dataByMonth as $month) {
    $totalStats['pending'] += $month['pending'];
    $totalStats['approved'] += $month['approved'];
    $totalStats['paid'] += $month['paid'];
    $totalStats['total'] += $month['total'];
}

// Статистика по преподавателям
$teacherStats = [];
foreach ($teachers as $teacher) {
    $stats = dbQueryOne(
        "SELECT
            COUNT(DISTINCT li.id) as lesson_count,
            SUM(p.amount) as total_amount
        FROM lessons_instance li
        LEFT JOIN payments p ON li.id = p.lesson_instance_id
        WHERE li.teacher_id = ?
            AND li.status = 'completed'
            AND li.lesson_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
        GROUP BY li.teacher_id",
        [$teacher['id']]
    );

    $teacherStats[$teacher['id']] = [
        'name' => $teacher['name'],
        'lesson_count' => $stats['lesson_count'] ?? 0,
        'total_amount' => $stats['total_amount'] ?? 0
    ];
}

// Месяцы в порядке убывания
$dataByMonth = array_reverse($dataByMonth, true);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выплаты преподавателям — Эвриум</title>

    <!-- Google Fonts: Nunito + JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Фоны */
            --bg-dark: #0c0f14;
            --bg-card: #14181f;
            --bg-elevated: #1a1f28;
            --bg-hover: #1f2631;

            /* Текст */
            --text-primary: #f0f2f5;
            --text-secondary: #8b95a5;
            --text-muted: #5a6473;

            /* Границы */
            --border: #252b36;

            /* Teal accent */
            --accent: #14b8a6;
            --accent-dim: rgba(20, 184, 166, 0.15);
            --accent-hover: #0d9488;

            /* Status colors */
            --status-green: #22c55e;
            --status-green-dim: rgba(34, 197, 94, 0.12);
            --status-amber: #f59e0b;
            --status-amber-dim: rgba(245, 158, 11, 0.12);
            --status-blue: #3b82f6;
            --status-blue-dim: rgba(59, 130, 246, 0.12);
            --status-rose: #f43f5e;
            --status-rose-dim: rgba(244, 63, 94, 0.12);

            /* Lesson types */
            --lesson-individual: #06b6d4;
            --lesson-individual-dim: rgba(6, 182, 212, 0.12);
            --lesson-group: #a855f7;
            --lesson-group-dim: rgba(168, 85, 247, 0.12);
        }

        body {
            font-family: 'Nunito', sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.5;
        }

        /* Layout */
        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 220px;
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            padding: 24px 0;
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .logo {
            padding: 0 20px 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--accent) 0%, #0d9488 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            font-size: 16px;
            color: white;
        }

        .logo-text {
            font-weight: 700;
            font-size: 15px;
            letter-spacing: -0.02em;
        }

        .nav-section {
            padding: 0 12px;
            margin-bottom: 24px;
        }

        .nav-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            padding: 0 8px;
            margin-bottom: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .nav-item:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .nav-item.active {
            background: var(--accent-dim);
            color: var(--accent);
        }

        .nav-icon {
            width: 18px;
            height: 18px;
            opacity: 0.7;
        }

        .nav-item.active .nav-icon {
            opacity: 1;
        }

        /* Main content */
        .main {
            flex: 1;
            margin-left: 220px;
            padding: 28px 32px;
            max-width: calc(100vw - 220px);
            overflow-x: hidden;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 28px;
        }

        .page-title {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            border: none;
            font-family: inherit;
            text-decoration: none;
        }

        .btn-secondary {
            background: var(--bg-elevated);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--bg-hover);
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
        }

        /* Teacher Filter */
        .teacher-filter {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .teacher-btn {
            position: relative;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            background: var(--bg-elevated);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            text-decoration: none;
        }

        .teacher-btn:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .teacher-btn.active {
            background: var(--accent-dim);
            color: var(--accent);
            border-color: var(--accent);
        }

        .teacher-tooltip {
            position: absolute;
            bottom: calc(100% + 10px);
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 100;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            white-space: nowrap;
        }

        .teacher-btn:hover .teacher-tooltip {
            opacity: 1;
            visibility: visible;
        }

        .teacher-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: var(--border);
        }

        .tooltip-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }

        .tooltip-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .tooltip-stat {
            text-align: center;
        }

        .tooltip-stat-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .tooltip-stat-label {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Stats Minimal */
        .stats-minimal {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            padding: 20px 0;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 28px;
        }

        .minimal-item {
            padding: 0 40px;
            text-align: center;
            border-right: 1px solid var(--border);
        }

        .minimal-item:last-child {
            border-right: none;
        }

        .minimal-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .minimal-item.pending .minimal-value { color: var(--status-amber); }
        .minimal-item.approved .minimal-value { color: var(--status-blue); }
        .minimal-item.paid .minimal-value { color: var(--status-green); }
        .minimal-item.total .minimal-value { color: var(--text-primary); }

        .minimal-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* Month Section */
        .month-section {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .month-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .month-title-group {
            display: flex;
            align-items: baseline;
            gap: 14px;
        }

        .month-title {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .month-amount {
            font-family: 'JetBrains Mono', monospace;
            font-size: 18px;
            font-weight: 600;
            color: var(--status-green);
        }

        /* Progress Bar */
        .progress-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .progress-label {
            font-size: 12px;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .progress-bar {
            width: 140px;
            height: 6px;
            background: var(--bg-dark);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.4s ease;
            background: linear-gradient(90deg, var(--status-green), #4ade80);
        }

        .progress-percent {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: 600;
            color: var(--status-green);
            min-width: 42px;
        }

        /* Weeks Grid */
        .weeks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
            margin-bottom: 24px;
        }

        .week-card {
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            cursor: pointer;
            transition: all 0.15s ease;
            text-align: center;
        }

        .week-card:hover {
            background: var(--bg-hover);
            border-color: var(--text-muted);
        }

        .week-dates {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .week-amount {
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .week-progress {
            margin-top: 8px;
            height: 3px;
            background: var(--bg-dark);
            border-radius: 2px;
            overflow: hidden;
        }

        .week-progress-fill {
            height: 100%;
            background: var(--status-green);
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        /* Details Table */
        .details-section {
            background: var(--bg-elevated);
            border-radius: 10px;
            overflow: hidden;
        }

        .table-header {
            display: grid;
            grid-template-columns: 100px 1fr 120px 80px 80px 100px 50px;
            gap: 12px;
            padding: 12px 16px;
            background: var(--bg-dark);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
        }

        /* Day Row */
        .day-row {
            border-bottom: 1px solid var(--border);
        }

        .day-row:last-child {
            border-bottom: none;
        }

        .day-header {
            display: grid;
            grid-template-columns: 100px 1fr 120px 80px 80px 100px 50px;
            gap: 12px;
            padding: 14px 16px;
            cursor: pointer;
            transition: background 0.15s ease;
            align-items: center;
        }

        .day-header:hover {
            background: var(--bg-hover);
        }

        .day-date {
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .day-weekday {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .expand-icon {
            width: 16px;
            height: 16px;
            color: var(--text-muted);
            transition: transform 0.2s ease;
        }

        .day-header.expanded .expand-icon {
            transform: rotate(180deg);
        }

        .lessons-count {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .lesson-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-individual {
            background: var(--lesson-individual-dim);
            color: var(--lesson-individual);
        }

        .badge-group {
            background: var(--lesson-group-dim);
            color: var(--lesson-group);
        }

        .day-subject {
            color: var(--text-secondary);
            font-size: 13px;
        }

        .day-hours {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .day-amount {
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            font-weight: 600;
        }

        .approve-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .approve-btn:hover {
            background: var(--status-green-dim);
            border-color: var(--status-green);
            color: var(--status-green);
        }

        .approve-btn.approved {
            background: var(--status-green);
            border-color: var(--status-green);
            color: white;
            pointer-events: none;
        }

        /* Lessons Container */
        .lessons-container {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: var(--bg-dark);
        }

        .lessons-container.expanded {
            max-height: 1000px;
        }

        .lesson-item {
            display: grid;
            grid-template-columns: 100px 1fr 120px 80px 80px 100px 50px;
            gap: 12px;
            padding: 12px 16px 12px 40px;
            border-top: 1px solid var(--border);
            align-items: center;
            font-size: 13px;
        }

        .lesson-time {
            color: var(--text-muted);
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
        }

        .lesson-subject-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .lesson-type-indicator {
            display: inline-flex;
            align-items: center;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }

        .type-individual {
            background: var(--lesson-individual-dim);
            color: var(--lesson-individual);
        }

        .type-group {
            background: var(--lesson-group-dim);
            color: var(--lesson-group);
        }

        .lesson-subject-name {
            color: var(--text-secondary);
        }

        .lesson-amount {
            font-family: 'JetBrains Mono', monospace;
            color: var(--text-secondary);
        }

        .lesson-approve {
            width: 26px;
            height: 26px;
            border-radius: 5px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .lesson-approve:hover {
            background: var(--status-green-dim);
            border-color: var(--status-green);
            color: var(--status-green);
        }

        .lesson-approve.approved {
            background: var(--status-green);
            border-color: var(--status-green);
            color: white;
        }

        /* Empty state */
        .empty-state {
            padding: 48px;
            text-align: center;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <div class="logo-icon">Э</div>
                <div class="logo-text">Эвриум</div>
            </div>

            <nav class="nav-section">
                <div class="nav-label">Основное</div>
                <a href="index.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Главная
                </a>
                <a href="schedule.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Расписание
                </a>
                <a href="students.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                    </svg>
                    Ученики
                </a>
            </nav>

            <nav class="nav-section">
                <div class="nav-label">Финансы</div>
                <a href="payments.php" class="nav-item active">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Выплаты
                </a>
                <a href="reports.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Отчёты
                </a>
            </nav>

            <nav class="nav-section">
                <div class="nav-label">Команда</div>
                <a href="teachers.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Преподаватели
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main">
            <header class="page-header">
                <div>
                    <h1 class="page-title">Выплаты преподавателям</h1>
                    <p class="page-subtitle">Учёт и одобрение выплат за проведённые занятия</p>
                </div>
                <div class="header-actions">
                    <a href="?export=xlsx" class="btn btn-secondary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Экспорт
                    </a>
                    <a href="logout.php" class="btn btn-secondary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Выход
                    </a>
                </div>
            </header>

            <!-- Teacher Filter -->
            <div class="teacher-filter">
                <a href="payments.php" class="teacher-btn <?= $teacherFilter === 0 ? 'active' : '' ?>">
                    Все преподаватели
                </a>
                <?php foreach ($teachers as $teacher): ?>
                    <?php
                    $stats = $teacherStats[$teacher['id']] ?? ['lesson_count' => 0, 'total_amount' => 0];
                    ?>
                    <a href="?teacher=<?= $teacher['id'] ?>" class="teacher-btn <?= $teacherFilter === $teacher['id'] ? 'active' : '' ?>">
                        <?= e($teacher['name']) ?>
                        <div class="teacher-tooltip">
                            <div class="tooltip-name"><?= e($teacher['name']) ?></div>
                            <div class="tooltip-stats">
                                <div class="tooltip-stat">
                                    <div class="tooltip-stat-value"><?= $stats['lesson_count'] ?></div>
                                    <div class="tooltip-stat-label">уроков</div>
                                </div>
                                <div class="tooltip-stat">
                                    <div class="tooltip-stat-value"><?= formatMoney($stats['total_amount']) ?></div>
                                    <div class="tooltip-stat-label">за месяц</div>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Stats Minimal -->
            <div class="stats-minimal">
                <div class="minimal-item pending">
                    <div class="minimal-value"><?= formatMoney($totalStats['pending']) ?></div>
                    <div class="minimal-label">Ожидают</div>
                </div>
                <div class="minimal-item approved">
                    <div class="minimal-value"><?= formatMoney($totalStats['approved']) ?></div>
                    <div class="minimal-label">Одобрено</div>
                </div>
                <div class="minimal-item paid">
                    <div class="minimal-value"><?= formatMoney($totalStats['paid']) ?></div>
                    <div class="minimal-label">Выплачено</div>
                </div>
                <div class="minimal-item total">
                    <div class="minimal-value"><?= formatMoney($totalStats['total']) ?></div>
                    <div class="minimal-label">Всего</div>
                </div>
            </div>

            <?php if (empty($dataByMonth)): ?>
                <div class="empty-state">
                    <p>Нет данных о выплатах за последние 3 месяца</p>
                </div>
            <?php else: ?>
                <!-- Months -->
                <?php foreach ($dataByMonth as $monthKey => $month): ?>
                    <?php
                    $approvedPercent = $month['total'] > 0
                        ? round((($month['approved'] + $month['paid']) / $month['total']) * 100)
                        : 0;
                    ?>
                    <section class="month-section">
                        <div class="month-header">
                            <div class="month-title-group">
                                <h2 class="month-title"><?= e($month['name']) ?></h2>
                                <span class="month-amount"><?= formatMoney($month['total']) ?></span>
                            </div>
                            <div class="progress-container">
                                <span class="progress-label">Одобрено:</span>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $approvedPercent ?>%"></div>
                                </div>
                                <span class="progress-percent"><?= $approvedPercent ?>%</span>
                            </div>
                        </div>

                        <!-- Weeks Grid -->
                        <?php if (!empty($month['weeks'])): ?>
                            <div class="weeks-grid">
                                <?php foreach ($month['weeks'] as $weekNum => $week): ?>
                                    <div class="week-card">
                                        <div class="week-dates"><?= e($week['start']) ?> — <?= e($week['end']) ?></div>
                                        <div class="week-amount"><?= formatMoney($week['total']) ?></div>
                                        <div class="week-progress">
                                            <div class="week-progress-fill" style="width: <?= $week['paid_percent'] ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Details Table -->
                        <div class="details-section">
                            <div class="table-header">
                                <div>Дата</div>
                                <div>Уроки</div>
                                <div>Предмет</div>
                                <div>Часы</div>
                                <div>Неявки</div>
                                <div>Сумма</div>
                                <div></div>
                            </div>

                            <?php foreach ($month['days'] as $dayKey => $day): ?>
                                <div class="day-row">
                                    <div class="day-header" onclick="toggleDay(this)">
                                        <div class="day-date">
                                            <svg class="expand-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                            <span><?= e($day['date']) ?></span>
                                            <span class="day-weekday"><?= e($day['weekday']) ?></span>
                                        </div>
                                        <div class="lessons-count">
                                            <?php if ($day['individual_count'] > 0): ?>
                                                <span class="lesson-type-badge badge-individual">
                                                    <?= $day['individual_count'] ?> инд
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($day['group_count'] > 0): ?>
                                                <span class="lesson-type-badge badge-group">
                                                    <?= $day['group_count'] ?> груп
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="day-subject">
                                            <?= e(implode(', ', array_slice($day['subjects'], 0, 2))) ?>
                                            <?php if (count($day['subjects']) > 2): ?>
                                                +<?= count($day['subjects']) - 2 ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="day-hours"><?= number_format($day['hours'], 1) ?> ч</div>
                                        <div class="day-absences"><?= $day['absences'] ?></div>
                                        <div class="day-amount"><?= formatMoney($day['total']) ?></div>
                                        <button class="approve-btn <?= $day['all_approved'] ? 'approved' : '' ?>" onclick="event.stopPropagation()">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Lessons -->
                                    <div class="lessons-container">
                                        <?php foreach ($day['lessons'] as $lesson): ?>
                                            <div class="lesson-item">
                                                <div class="lesson-time"><?= e($lesson['time']) ?></div>
                                                <div class="lesson-subject-cell">
                                                    <?php if (!empty($lesson['students'])): ?>
                                                        <span style="color: var(--text-secondary)">
                                                            <?= e(implode(', ', array_slice($lesson['students'], 0, 2))) ?>
                                                            <?php if (count($lesson['students']) > 2): ?>
                                                                +<?= count($lesson['students']) - 2 ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="lesson-subject-cell">
                                                    <span class="lesson-type-indicator type-<?= $lesson['type'] ?>">
                                                        <?= $lesson['type'] === 'individual' ? 'инд' : 'груп' ?>
                                                    </span>
                                                    <span class="lesson-subject-name"><?= e($lesson['subject']) ?></span>
                                                </div>
                                                <div class="day-hours"><?= number_format($lesson['duration'], 1) ?> ч</div>
                                                <div class="day-absences">—</div>
                                                <div class="lesson-amount"><?= formatMoney($lesson['amount']) ?></div>
                                                <button
                                                    class="lesson-approve <?= in_array($lesson['payment_status'], ['approved', 'paid']) ? 'approved' : '' ?>"
                                                    onclick="event.stopPropagation()"
                                                    <?= !$lesson['payment_id'] ? 'disabled' : '' ?>
                                                >
                                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Toggle day expansion
        function toggleDay(header) {
            const lessonsContainer = header.nextElementSibling;
            const isExpanded = header.classList.contains('expanded');

            // Close other expanded days
            document.querySelectorAll('.day-header.expanded').forEach(el => {
                if (el !== header) {
                    el.classList.remove('expanded');
                    el.nextElementSibling.classList.remove('expanded');
                }
            });

            if (isExpanded) {
                header.classList.remove('expanded');
                lessonsContainer.classList.remove('expanded');
            } else {
                header.classList.add('expanded');
                lessonsContainer.classList.add('expanded');
            }
        }
    </script>
</body>
</html>
