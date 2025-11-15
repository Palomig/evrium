<?php
/**
 * API для отчётов и аналитики
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

// Устанавливаем JSON заголовки
header('Content-Type: application/json; charset=utf-8');

// Требуем авторизацию
if (!isLoggedIn()) {
    jsonError('Требуется авторизация', 401);
}

// Получаем действие
$action = $_GET['action'] ?? '';

// Маршрутизация по действиям
switch ($action) {
    case 'summary':
        handleSummary();
        break;
    case 'by_teacher':
        handleByTeacher();
        break;
    case 'by_period':
        handleByPeriod();
        break;
    case 'daily_chart':
        handleDailyChart();
        break;
    case 'teacher_chart':
        handleTeacherChart();
        break;
    case 'export_excel':
        handleExportExcel();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Общая сводка
 */
function handleSummary() {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-t');

    // Общая статистика
    $summary = dbQueryOne(
        "SELECT
            COUNT(DISTINCT li.id) as total_lessons,
            SUM(CASE WHEN li.status = 'completed' THEN 1 ELSE 0 END) as completed_lessons,
            SUM(CASE WHEN li.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_lessons,
            SUM(CASE WHEN li.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_lessons,
            SUM(CASE WHEN li.status = 'completed' THEN li.actual_students ELSE 0 END) as total_students
         FROM lessons_instance li
         WHERE li.lesson_date BETWEEN ? AND ?",
        [$dateFrom, $dateTo]
    );

    // Финансовая статистика
    $financial = dbQueryOne(
        "SELECT
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_total,
            SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_total,
            SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_total,
            SUM(CASE WHEN status != 'cancelled' THEN amount ELSE 0 END) as total_amount
         FROM payments
         WHERE created_at BETWEEN ? AND ?",
        [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
    );

    // Топ преподавателей
    $topTeachers = dbQuery(
        "SELECT t.name, COUNT(li.id) as lessons_count,
                SUM(CASE WHEN li.status = 'completed' THEN li.actual_students ELSE 0 END) as students_taught
         FROM teachers t
         LEFT JOIN lessons_instance li ON t.id = li.teacher_id
            AND li.lesson_date BETWEEN ? AND ?
         GROUP BY t.id, t.name
         ORDER BY lessons_count DESC
         LIMIT 5",
        [$dateFrom, $dateTo]
    );

    jsonSuccess([
        'summary' => $summary,
        'financial' => $financial,
        'top_teachers' => $topTeachers,
        'period' => [
            'from' => $dateFrom,
            'to' => $dateTo
        ]
    ]);
}

/**
 * Отчёт по преподавателю
 */
function handleByTeacher() {
    $teacherId = filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT);
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-t');

    if (!$teacherId) {
        jsonError('Не указан ID преподавателя', 400);
    }

    // Информация о преподавателе
    $teacher = dbQueryOne(
        "SELECT * FROM teachers WHERE id = ?",
        [$teacherId]
    );

    if (!$teacher) {
        jsonError('Преподаватель не найден', 404);
    }

    // Статистика уроков
    $lessons = dbQueryOne(
        "SELECT
            COUNT(*) as total_lessons,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_lessons,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_lessons,
            SUM(CASE WHEN status = 'completed' THEN actual_students ELSE 0 END) as total_students,
            AVG(CASE WHEN status = 'completed' THEN actual_students ELSE NULL END) as avg_students
         FROM lessons_instance
         WHERE teacher_id = ? AND lesson_date BETWEEN ? AND ?",
        [$teacherId, $dateFrom, $dateTo]
    );

    // Выплаты
    $payments = dbQueryOne(
        "SELECT
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid,
            SUM(CASE WHEN status != 'cancelled' THEN amount ELSE 0 END) as total
         FROM payments
         WHERE teacher_id = ? AND created_at BETWEEN ? AND ?",
        [$teacherId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
    );

    // Детали по урокам
    $lessonDetails = dbQuery(
        "SELECT li.*, pf.name as formula_name,
                p.amount as payment_amount, p.status as payment_status
         FROM lessons_instance li
         LEFT JOIN payment_formulas pf ON li.formula_id = pf.id
         LEFT JOIN payments p ON li.id = p.lesson_instance_id
         WHERE li.teacher_id = ? AND li.lesson_date BETWEEN ? AND ?
         ORDER BY li.lesson_date DESC, li.time_start DESC
         LIMIT 50",
        [$teacherId, $dateFrom, $dateTo]
    );

    jsonSuccess([
        'teacher' => $teacher,
        'lessons' => $lessons,
        'payments' => $payments,
        'lesson_details' => $lessonDetails,
        'period' => [
            'from' => $dateFrom,
            'to' => $dateTo
        ]
    ]);
}

/**
 * Отчёт по периоду с группировкой
 */
function handleByPeriod() {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-t');
    $groupBy = $_GET['group_by'] ?? 'day'; // day, week, month

    $groupFormat = match($groupBy) {
        'week' => '%Y-%u',
        'month' => '%Y-%m',
        default => '%Y-%m-%d'
    };

    // Статистика по периодам
    $periodStats = dbQuery(
        "SELECT
            DATE_FORMAT(li.lesson_date, ?) as period,
            COUNT(*) as lessons_count,
            SUM(CASE WHEN li.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN li.status = 'completed' THEN li.actual_students ELSE 0 END) as students_count
         FROM lessons_instance li
         WHERE li.lesson_date BETWEEN ? AND ?
         GROUP BY period
         ORDER BY period ASC",
        [$groupFormat, $dateFrom, $dateTo]
    );

    // Выплаты по периодам
    $paymentStats = dbQuery(
        "SELECT
            DATE_FORMAT(p.created_at, ?) as period,
            SUM(CASE WHEN p.status != 'cancelled' THEN p.amount ELSE 0 END) as total_amount,
            SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) as paid_amount
         FROM payments p
         WHERE p.created_at BETWEEN ? AND ?
         GROUP BY period
         ORDER BY period ASC",
        [$groupFormat, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
    );

    jsonSuccess([
        'period_stats' => $periodStats,
        'payment_stats' => $paymentStats,
        'group_by' => $groupBy,
        'period' => [
            'from' => $dateFrom,
            'to' => $dateTo
        ]
    ]);
}

/**
 * Данные для графика по дням
 */
function handleDailyChart() {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-t');

    $dailyData = dbQuery(
        "SELECT
            DATE_FORMAT(li.lesson_date, '%Y-%m-%d') as date,
            COUNT(*) as lessons_count,
            SUM(CASE WHEN li.status = 'completed' THEN li.actual_students ELSE 0 END) as students_count,
            COALESCE(SUM(p.amount), 0) as revenue
         FROM lessons_instance li
         LEFT JOIN payments p ON li.id = p.lesson_instance_id AND p.status != 'cancelled'
         WHERE li.lesson_date BETWEEN ? AND ?
         GROUP BY date
         ORDER BY date ASC",
        [$dateFrom, $dateTo]
    );

    jsonSuccess($dailyData);
}

/**
 * Данные для графика по преподавателям
 */
function handleTeacherChart() {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-t');

    $teacherData = dbQuery(
        "SELECT
            t.name,
            COUNT(li.id) as lessons_count,
            SUM(CASE WHEN li.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN li.status = 'completed' THEN li.actual_students ELSE 0 END) as students_taught,
            COALESCE(SUM(CASE WHEN p.status != 'cancelled' THEN p.amount ELSE 0 END), 0) as total_earned
         FROM teachers t
         LEFT JOIN lessons_instance li ON t.id = li.teacher_id
            AND li.lesson_date BETWEEN ? AND ?
         LEFT JOIN payments p ON li.id = p.lesson_instance_id
         WHERE t.active = 1
         GROUP BY t.id, t.name
         ORDER BY total_earned DESC",
        [$dateFrom, $dateTo]
    );

    jsonSuccess($teacherData);
}

/**
 * Экспорт в Excel
 */
function handleExportExcel() {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-t');
    $teacherId = filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT);

    // Получаем данные
    $query = "SELECT
                li.id,
                li.lesson_date,
                li.time_start,
                li.time_end,
                t.name as teacher_name,
                li.subject,
                li.lesson_type,
                li.expected_students,
                li.actual_students,
                li.status,
                pf.name as formula_name,
                p.amount,
                p.status as payment_status
              FROM lessons_instance li
              LEFT JOIN teachers t ON li.teacher_id = t.id
              LEFT JOIN payment_formulas pf ON li.formula_id = pf.id
              LEFT JOIN payments p ON li.id = p.lesson_instance_id
              WHERE li.lesson_date BETWEEN ? AND ?";

    $params = [$dateFrom, $dateTo];

    if ($teacherId) {
        $query .= " AND li.teacher_id = ?";
        $params[] = $teacherId;
    }

    $query .= " ORDER BY li.lesson_date DESC, li.time_start DESC";

    $data = dbQuery($query, $params);

    // Генерируем CSV (упрощённо, вместо Excel)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $dateFrom . '_' . $dateTo . '.csv"');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM для правильного отображения в Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Заголовки
    fputcsv($output, [
        'ID', 'Дата', 'Время начала', 'Время конца', 'Преподаватель',
        'Предмет', 'Тип', 'Ожидалось учеников', 'Пришло учеников',
        'Статус урока', 'Формула', 'Сумма', 'Статус выплаты'
    ], ';');

    // Данные
    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'],
            $row['lesson_date'],
            $row['time_start'],
            $row['time_end'],
            $row['teacher_name'],
            $row['subject'] ?? '',
            $row['lesson_type'] === 'group' ? 'Групповое' : 'Индивидуальное',
            $row['expected_students'],
            $row['actual_students'] ?? 0,
            $row['status'],
            $row['formula_name'] ?? '',
            $row['amount'] ?? 0,
            $row['payment_status'] ?? ''
        ], ';');
    }

    fclose($output);
    exit;
}
