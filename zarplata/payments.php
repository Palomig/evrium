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
// Показываем ВСЕ уроки (не только completed), чтобы видеть потенциальную зарплату
$whereClauses = ["(li.status = 'completed' OR li.status = 'scheduled')"];
$params = [];

if ($teacherFilter > 0) {
    $whereClauses[] = "li.teacher_id = ?";
    $params[] = $teacherFilter;
}

$whereSQL = implode(' AND ', $whereClauses);

// Получить уроки (завершенные и запланированные) с выплатами за последние 3 месяца
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
        li.status,
        t.id as teacher_id,
        t.name as teacher_name,
        p.id as payment_id,
        p.amount,
        p.status as payment_status,
        lt.tier,
        lt.grades,
        lt.students as students_json,
        lt.room,
        COALESCE(li.formula_id, t.formula_id) as formula_id,
        pf.type as formula_type,
        pf.min_payment,
        pf.per_student,
        pf.threshold,
        pf.fixed_amount,
        pf.expression
    FROM lessons_instance li
    LEFT JOIN teachers t ON li.teacher_id = t.id
    LEFT JOIN payments p ON li.id = p.lesson_instance_id
    LEFT JOIN lessons_template lt ON li.template_id = lt.id
    LEFT JOIN payment_formulas pf ON COALESCE(li.formula_id, t.formula_id) = pf.id
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
    // Для completed уроков берем реальную сумму из payments
    // Для scheduled уроков рассчитываем потенциальную зарплату
    if ($lesson['status'] === 'completed' && $lesson['amount']) {
        $amount = (int)$lesson['amount'];
    } elseif ($lesson['status'] === 'scheduled') {
        // Рассчитываем потенциальную зарплату на основе формулы
        // Используем РЕАЛЬНОЕ количество студентов из JSON, а не expected_students
        $studentsCount = $lesson['expected_students'] ?? 1;

        // Парсим students_json чтобы получить точное количество
        if ($lesson['students_json']) {
            $studentsData = json_decode($lesson['students_json'], true);
            if (is_array($studentsData)) {
                $studentsCount = count($studentsData);
            }
        }

        $amount = 0;

        if ($lesson['formula_type'] === 'min_plus_per') {
            $minPayment = $lesson['min_payment'] ?? 0;
            $perStudent = $lesson['per_student'] ?? 0;
            $threshold = $lesson['threshold'] ?? 2;

            $amount = $minPayment;
            if ($studentsCount > $threshold) {
                $amount += ($studentsCount - $threshold) * $perStudent;
            }
        } elseif ($lesson['formula_type'] === 'fixed') {
            $amount = $lesson['fixed_amount'] ?? 0;
        } elseif ($lesson['formula_type'] === 'expression') {
            // Простая обработка expression (если нужно, можно улучшить)
            // Заменяем N на количество учеников
            $expression = str_replace('N', $studentsCount, $lesson['expression'] ?? '0');
            try {
                $amount = @eval("return $expression;");
            } catch (Exception $e) {
                $amount = 0;
            }
        }
    } else {
        $amount = (int)($lesson['amount'] ?? 0);
    }

    $dataByMonth[$monthKey]['total'] += $amount;

    // Для scheduled уроков считаем как pending
    if ($lesson['status'] === 'scheduled') {
        $dataByMonth[$monthKey]['pending'] += $amount;
    } elseif ($lesson['payment_status'] === 'pending') {
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
        'lesson_status' => $lesson['status'], // scheduled или completed
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

// Получить последние события из журнала аудита (связанные с выплатами)
$auditLogs = dbQuery(
    "SELECT
        al.id,
        al.action_type,
        al.entity_type,
        al.entity_id,
        al.old_value,
        al.new_value,
        al.notes,
        al.created_at,
        u.name as user_name
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.entity_type IN ('payment', 'lesson', 'adjustment')
    ORDER BY al.created_at DESC
    LIMIT 50",
    []
);

// Page settings for header template
define('PAGE_TITLE', 'Выплаты преподавателям');
define('PAGE_SUBTITLE', 'Учёт и одобрение выплат за проведённые занятия');
define('ACTIVE_PAGE', 'payments');

require_once __DIR__ . '/templates/header.php';

?>
<style>
/* Page-specific CSS for payments page */
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

        /* Scheduled lessons */
        .lesson-scheduled {
            opacity: 0.6;
            background: rgba(251, 191, 36, 0.03);
        }

        .lesson-scheduled:hover {
            opacity: 0.8;
        }

        .amount-estimated {
            color: var(--md-warning);
            font-style: italic;
        }

        /* Empty state */
        .empty-state {
            padding: 48px;
            text-align: center;
            color: var(--text-muted);
        }
    </style>
</style>

<!-- Add header actions to page-header -->
<div class="header-actions" style="position: absolute; top: 28px; right: 32px; display: flex; gap: 12px;">
                    <button onclick="openAdjustmentModal()" class="btn btn-primary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Корректировка
                    </button>
                    <a href="?export=xlsx" class="btn btn-secondary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Экспорт
                    </a>
                    <button onclick="openJournalModal()" class="btn btn-secondary">
                        <span class="material-icons" style="font-size: 16px;">history</span>
                        Журнал событий
                    </button>
                </div>

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
                                            <div class="lesson-item <?= $lesson['lesson_status'] === 'scheduled' ? 'lesson-scheduled' : '' ?>">
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
                                                    <?php if ($lesson['lesson_status'] === 'scheduled'): ?>
                                                        <span style="font-size: 10px; color: var(--md-warning); margin-left: 4px;">запл.</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="day-hours"><?= number_format($lesson['duration'], 1) ?> ч</div>
                                                <div class="day-absences">—</div>
                                                <div class="lesson-amount <?= $lesson['lesson_status'] === 'scheduled' ? 'amount-estimated' : '' ?>">
                                                    <?= formatMoney($lesson['amount']) ?>
                                                    <?php if ($lesson['lesson_status'] === 'scheduled'): ?>
                                                        <span style="font-size: 10px; color: var(--text-disabled); margin-left: 2px;">~</span>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- Модальное окно журнала событий -->
    <div id="journalModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: var(--bg-card); border-radius: 16px; max-width: 1200px; max-height: 80vh; width: 90%; overflow: hidden; display: flex; flex-direction: column;">
            <!-- Header -->
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 24px 28px; border-bottom: 1px solid var(--border);">
                <div>
                    <h2 style="font-size: 24px; font-weight: 700; color: var(--text-high-emphasis); margin: 0;">
                        Журнал событий
                    </h2>
                    <p style="font-size: 14px; color: var(--text-medium-emphasis); margin: 4px 0 0 0;">
                        История действий с выплатами и уроками
                    </p>
                </div>
                <button onclick="closeJournalModal()" class="btn-icon" style="background: var(--bg-hover); border: none; border-radius: 8px; padding: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s;">
                    <span class="material-icons" style="font-size: 24px; color: var(--text-medium-emphasis);">close</span>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body" style="padding: 24px 28px; overflow-y: auto; flex: 1;">
                <?php if (empty($auditLogs)): ?>
                    <div class="empty-state">
                        <p>Журнал событий пуст</p>
                    </div>
                <?php else: ?>
                    <div class="audit-log-table" style="background: var(--bg-elevated); border-radius: 12px; overflow: hidden;">
                        <div class="audit-header" style="display: grid; grid-template-columns: 180px 120px 1fr 150px 120px; gap: 16px; padding: 16px 20px; background: var(--bg-hover); border-bottom: 1px solid var(--border); font-size: 12px; font-weight: 600; color: var(--text-medium-emphasis); text-transform: uppercase; letter-spacing: 0.5px;">
                            <div>Дата и время</div>
                            <div>Действие</div>
                            <div>Описание</div>
                            <div>Пользователь</div>
                            <div>Тип</div>
                        </div>

                        <div class="audit-log-entries">
                            <?php foreach ($auditLogs as $log): ?>
                                <?php
                                // Форматируем дату
                                $date = new DateTime($log['created_at']);
                                $dateFormatted = $date->format('d.m.Y H:i');

                                // Определяем иконку и цвет по типу действия
                                $actionIcons = [
                                    'payment_created' => ['icon' => 'add_circle', 'color' => '#10b981'],
                                    'payment_updated' => ['icon' => 'edit', 'color' => '#f59e0b'],
                                    'payment_deleted' => ['icon' => 'delete', 'color' => '#ef4444'],
                                    'payment_approved' => ['icon' => 'check_circle', 'color' => '#10b981'],
                                    'payment_paid' => ['icon' => 'payments', 'color' => '#14b8a6'],
                                    'adjustment_created' => ['icon' => 'tune', 'color' => '#8b5cf6'],
                                    'lesson_completed' => ['icon' => 'done', 'color' => '#10b981'],
                                    'payments_cleared_all' => ['icon' => 'delete_sweep', 'color' => '#ef4444']
                                ];

                                $actionInfo = $actionIcons[$log['action_type']] ?? ['icon' => 'info', 'color' => '#6366f1'];

                                // Определяем читаемое название действия
                                $actionLabels = [
                                    'payment_created' => 'Создание',
                                    'payment_updated' => 'Изменение',
                                    'payment_deleted' => 'Удаление',
                                    'payment_approved' => 'Одобрение',
                                    'payment_paid' => 'Выплата',
                                    'adjustment_created' => 'Корректировка',
                                    'lesson_completed' => 'Урок завершён',
                                    'payments_cleared_all' => 'Полная очистка'
                                ];

                                $actionLabel = $actionLabels[$log['action_type']] ?? $log['action_type'];

                                // Определяем тип сущности
                                $entityLabels = [
                                    'payment' => 'Выплата',
                                    'lesson' => 'Урок',
                                    'adjustment' => 'Корректировка'
                                ];

                                $entityLabel = $entityLabels[$log['entity_type']] ?? $log['entity_type'];
                                ?>

                                <div class="audit-entry" style="display: grid; grid-template-columns: 180px 120px 1fr 150px 120px; gap: 16px; padding: 16px 20px; border-bottom: 1px solid var(--border); transition: background 0.2s; cursor: default;">
                                    <div style="font-size: 13px; color: var(--text-medium-emphasis); font-family: 'JetBrains Mono', monospace;">
                                        <?= e($dateFormatted) ?>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span class="material-icons" style="font-size: 18px; color: <?= $actionInfo['color'] ?>;">
                                            <?= $actionInfo['icon'] ?>
                                        </span>
                                        <span style="font-size: 13px; font-weight: 500; color: var(--text-high-emphasis);">
                                            <?= e($actionLabel) ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 13px; color: var(--text-medium-emphasis);">
                                        <?= e($log['notes'] ?: '—') ?>
                                        <?php if ($log['entity_id']): ?>
                                            <span style="color: var(--text-disabled); font-size: 12px;">(ID: <?= $log['entity_id'] ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 13px; color: var(--text-medium-emphasis);">
                                        <?= e($log['user_name'] ?: 'Система') ?>
                                    </div>
                                    <div>
                                        <span style="display: inline-block; padding: 4px 10px; background: rgba(99, 102, 241, 0.1); color: #818cf8; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <?= e($entityLabel) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .audit-entry:hover {
            background: var(--bg-hover);
        }
        .audit-entry:last-child {
            border-bottom: none;
        }
        .btn-icon:hover {
            background: var(--bg-dark) !important;
        }
    </style>

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

        // Open journal modal
        function openJournalModal() {
            const modal = document.getElementById('journalModal');
            modal.style.display = 'flex';
        }

        // Close journal modal
        function closeJournalModal() {
            const modal = document.getElementById('journalModal');
            modal.style.display = 'none';
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('journalModal');
            if (event.target === modal) {
                closeJournalModal();
            }
        });
    </script>

<!-- Модальное окно корректировки выплат -->
<div id="adjustment-modal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Корректировка выплаты</h3>
            <button class="modal-close" onclick="closeAdjustmentModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <form id="adjustment-form" onsubmit="saveAdjustment(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label for="adjustment-teacher">Преподаватель *</label>
                    <select id="adjustment-teacher" name="teacher_id" required>
                        <option value="">Выберите преподавателя</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>"><?= e($teacher['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="adjustment-type">Тип корректировки *</label>
                    <select id="adjustment-type" name="payment_type" required>
                        <option value="bonus">Премия</option>
                        <option value="penalty">Штраф</option>
                        <option value="adjustment">Корректировка</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="adjustment-amount">Сумма (₽) *</label>
                    <input
                        type="number"
                        id="adjustment-amount"
                        name="amount"
                        required
                        placeholder="Введите сумму"
                        step="1"
                    >
                    <small>Для штрафа введите отрицательное значение (например: -500)</small>
                </div>

                <div class="form-group">
                    <label for="adjustment-date">Дата *</label>
                    <input
                        type="date"
                        id="adjustment-date"
                        name="date"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="adjustment-notes">Примечание</label>
                    <textarea
                        id="adjustment-notes"
                        name="notes"
                        rows="3"
                        placeholder="Опишите причину корректировки"
                    ></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeAdjustmentModal()">
                    Отмена
                </button>
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Сохранить
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Модальное окно */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        animation: fadeIn 0.2s;
    }

    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background: #1f2937;
        border-radius: 16px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        animation: modalAppear 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    }

    @keyframes modalAppear {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(10px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: #ffffff;
    }

    .modal-close {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: #e5e7eb;
        cursor: pointer;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: scale(1.05);
    }

    .modal-close .material-icons {
        font-size: 20px;
    }

    .modal-body {
        padding: 24px;
        overflow-y: auto;
        overflow-x: hidden;
        flex: 1 1 auto;
        min-height: 0;
    }

    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-shrink: 0;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #e5e7eb;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px 14px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        color: #ffffff;
        font-size: 14px;
        font-family: 'Nunito', sans-serif;
        transition: all 0.2s;
        box-sizing: border-box;
    }

    .form-group input::placeholder,
    .form-group textarea::placeholder {
        color: #6b7280;
    }

    .form-group input:hover,
    .form-group select:hover,
    .form-group textarea:hover {
        border-color: rgba(255, 255, 255, 0.2);
        background: rgba(255, 255, 255, 0.08);
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #14b8a6;
        background: rgba(255, 255, 255, 0.08);
        box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.15);
    }

    .form-group small {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        color: #9ca3af;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 80px;
        line-height: 1.5;
    }

    .form-group select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%2314b8a6' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 40px;
        cursor: pointer;
    }

    .form-group select option {
        background: #1f2937;
        color: #ffffff;
        padding: 10px;
    }

    .btn-outline {
        background: rgba(255, 255, 255, 0.08);
        color: #d1d5db;
        border: none;
        padding: 12px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-outline:hover {
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
    }
</style>

<script>
    function openAdjustmentModal() {
        const modal = document.getElementById('adjustment-modal');
        modal.classList.add('active');

        // Установить сегодняшнюю дату
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('adjustment-date').value = today;
    }

    function closeAdjustmentModal() {
        const modal = document.getElementById('adjustment-modal');
        modal.classList.remove('active');
        document.getElementById('adjustment-form').reset();
    }

    async function saveAdjustment(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const data = {
            teacher_id: parseInt(formData.get('teacher_id')),
            payment_type: formData.get('payment_type'),
            amount: parseInt(formData.get('amount')),
            date: formData.get('date'),
            notes: formData.get('notes') || null
        };

        try {
            const response = await fetch('/zarplata/api/payments.php?action=add_adjustment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                alert('Корректировка успешно добавлена!');
                closeAdjustmentModal();
                window.location.reload();
            } else {
                alert(result.error || 'Ошибка при сохранении корректировки');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ошибка при сохранении корректировки');
        }
    }

    // Закрыть модалку при клике вне её
    window.onclick = function(event) {
        const modal = document.getElementById('adjustment-modal');
        if (event.target === modal) {
            closeAdjustmentModal();
        }
    }
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
