<?php
/**
 * Главная страница (Dashboard)
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

// Требуем авторизацию
requireAuth();

// Получаем текущего пользователя
$user = getCurrentUser();

// Получаем статистику для dashboard
$currentDate = date('Y-m-d');
$weekStart = getWeekStart($currentDate);
$weekEnd = getWeekEnd($currentDate);
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// Статистика по урокам
$todayLessons = dbQuery(
    "SELECT COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
     FROM lessons_instance
     WHERE lesson_date = ?",
    [$currentDate]
);

$weekLessons = dbQuery(
    "SELECT COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
     FROM lessons_instance
     WHERE lesson_date BETWEEN ? AND ?",
    [$weekStart, $weekEnd]
);

// Статистика по выплатам
$pendingPayments = dbQueryOne(
    "SELECT COUNT(*) as count, SUM(amount) as total
     FROM payments
     WHERE status = 'pending'",
    []
);

$monthPayments = dbQueryOne(
    "SELECT SUM(amount) as total
     FROM payments
     WHERE status != 'cancelled'
     AND DATE_FORMAT(created_at, '%Y-%m') = ?",
    [date('Y-m')]
);

// Активные преподаватели
$activeTeachers = dbQueryOne(
    "SELECT COUNT(*) as count FROM teachers WHERE active = 1",
    []
);

// Последние уроки
$recentLessons = dbQuery(
    "SELECT li.*, t.name as teacher_name,
            CASE WHEN li.substitute_teacher_id IS NOT NULL
                 THEN (SELECT name FROM teachers WHERE id = li.substitute_teacher_id)
                 ELSE NULL
            END as substitute_name
     FROM lessons_instance li
     LEFT JOIN teachers t ON li.teacher_id = t.id
     ORDER BY li.lesson_date DESC, li.time_start DESC
     LIMIT 10",
    []
);

// Ближайшие уроки
$upcomingLessons = dbQuery(
    "SELECT li.*, t.name as teacher_name
     FROM lessons_instance li
     LEFT JOIN teachers t ON li.teacher_id = t.id
     WHERE li.lesson_date >= ? AND li.status = 'scheduled'
     ORDER BY li.lesson_date ASC, li.time_start ASC
     LIMIT 5",
    [$currentDate]
);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Учёт зарплаты</title>

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Roboto Font -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Material Dark Theme CSS -->
    <link rel="stylesheet" href="/zarplata/assets/css/material-dark.css">

    <style>
        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background-color: var(--md-surface);
            border-right: 1px solid rgba(255, 255, 255, 0.12);
            padding: 24px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 24px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .sidebar-logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--md-primary), var(--md-secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-logo-text {
            font-size: 1.25rem;
            font-weight: 500;
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background-color: var(--md-surface-3);
            border-radius: 8px;
        }

        .sidebar-user-avatar {
            width: 40px;
            height: 40px;
            background: var(--md-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--md-on-primary);
            font-weight: 500;
        }

        .sidebar-user-info {
            flex: 1;
        }

        .sidebar-user-name {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .sidebar-user-role {
            font-size: 0.75rem;
            color: var(--text-medium-emphasis);
        }

        .sidebar-nav {
            padding: 16px 0;
        }

        .sidebar-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: var(--text-high-emphasis);
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .sidebar-nav-item:hover {
            background-color: rgba(255, 255, 255, 0.08);
        }

        .sidebar-nav-item.active {
            background-color: rgba(187, 134, 252, 0.12);
            color: var(--md-primary);
            border-right: 3px solid var(--md-primary);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 24px;
            background-color: var(--md-background);
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 300;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 0.875rem;
            color: var(--text-medium-emphasis);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            padding: 24px;
            background-color: var(--md-surface);
            border-radius: 12px;
            box-shadow: var(--elevation-2);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--elevation-3);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-card-icon.primary {
            background-color: rgba(187, 134, 252, 0.12);
            color: var(--md-primary);
        }

        .stat-card-icon.secondary {
            background-color: rgba(3, 218, 198, 0.12);
            color: var(--md-secondary);
        }

        .stat-card-icon.success {
            background-color: rgba(76, 175, 80, 0.12);
            color: var(--md-success);
        }

        .stat-card-icon.warning {
            background-color: rgba(255, 152, 0, 0.12);
            color: var(--md-warning);
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 300;
            margin-bottom: 4px;
        }

        .stat-card-label {
            font-size: 0.875rem;
            color: var(--text-medium-emphasis);
        }

        /* Table */
        .table-container {
            background-color: var(--md-surface);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--elevation-2);
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 500;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: var(--md-surface-2);
        }

        th {
            text-align: left;
            padding: 16px 24px;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-medium-emphasis);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        td {
            padding: 16px 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        tbody tr {
            transition: background-color 0.2s;
        }

        tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.04);
        }

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-success {
            background-color: rgba(76, 175, 80, 0.12);
            color: var(--md-success);
        }

        .badge-warning {
            background-color: rgba(255, 152, 0, 0.12);
            color: var(--md-warning);
        }

        .badge-info {
            background-color: rgba(33, 150, 243, 0.12);
            color: var(--md-info);
        }

        .badge-danger {
            background-color: rgba(207, 102, 121, 0.12);
            color: var(--md-error);
        }

        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-disabled);
        }

        .empty-state .material-icons {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="sidebar-logo-icon">
                        <span class="material-icons">account_balance_wallet</span>
                    </div>
                    <span class="sidebar-logo-text">Зарплаты</span>
                </div>

                <div class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        <?= strtoupper(mb_substr($user['name'], 0, 1)) ?>
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?= e($user['name']) ?></div>
                        <div class="sidebar-user-role"><?= $user['role'] === 'owner' ? 'Владелец' : 'Администратор' ?></div>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="/zarplata/" class="sidebar-nav-item active">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="/zarplata/teachers.php" class="sidebar-nav-item">
                    <span class="material-icons">person</span>
                    <span>Преподаватели</span>
                </a>
                <a href="/zarplata/schedule.php" class="sidebar-nav-item">
                    <span class="material-icons">event</span>
                    <span>Расписание</span>
                </a>
                <a href="/zarplata/lessons.php" class="sidebar-nav-item">
                    <span class="material-icons">school</span>
                    <span>Уроки</span>
                </a>
                <a href="/zarplata/payments.php" class="sidebar-nav-item">
                    <span class="material-icons">payments</span>
                    <span>Выплаты</span>
                </a>
                <a href="/zarplata/reports.php" class="sidebar-nav-item">
                    <span class="material-icons">assessment</span>
                    <span>Отчёты</span>
                </a>
                <a href="/zarplata/formulas.php" class="sidebar-nav-item">
                    <span class="material-icons">functions</span>
                    <span>Формулы оплаты</span>
                </a>
                <a href="/zarplata/settings.php" class="sidebar-nav-item">
                    <span class="material-icons">settings</span>
                    <span>Настройки</span>
                </a>
                <a href="/zarplata/logout.php" class="sidebar-nav-item">
                    <span class="material-icons">logout</span>
                    <span>Выход</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Обзор системы учёта зарплаты преподавателей</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?= $todayLessons[0]['completed'] ?? 0 ?> / <?= $todayLessons[0]['total'] ?? 0 ?></div>
                            <div class="stat-card-label">Уроки сегодня</div>
                        </div>
                        <div class="stat-card-icon primary">
                            <span class="material-icons">today</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?= $weekLessons[0]['completed'] ?? 0 ?> / <?= $weekLessons[0]['total'] ?? 0 ?></div>
                            <div class="stat-card-label">Уроки за неделю</div>
                        </div>
                        <div class="stat-card-icon secondary">
                            <span class="material-icons">event_note</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?= formatMoney($pendingPayments['total'] ?? 0) ?></div>
                            <div class="stat-card-label">Ожидают выплаты</div>
                        </div>
                        <div class="stat-card-icon warning">
                            <span class="material-icons">pending</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?= formatMoney($monthPayments['total'] ?? 0) ?></div>
                            <div class="stat-card-label">Выплачено за месяц</div>
                        </div>
                        <div class="stat-card-icon success">
                            <span class="material-icons">account_balance</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Lessons -->
            <div class="table-container mb-4">
                <div class="table-header">
                    <h2 class="table-title">Ближайшие уроки</h2>
                    <a href="/zarplata/schedule.php" class="btn btn-text">
                        Все уроки
                        <span class="material-icons" style="font-size: 18px; margin-left: 4px;">arrow_forward</span>
                    </a>
                </div>
                <?php if (empty($upcomingLessons)): ?>
                    <div class="empty-state">
                        <div class="material-icons">event_busy</div>
                        <p>Нет запланированных уроков</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Время</th>
                                <th>Преподаватель</th>
                                <th>Предмет</th>
                                <th>Тип</th>
                                <th>Учеников</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingLessons as $lesson): ?>
                                <?php $statusBadge = getLessonStatusBadge($lesson['status']); ?>
                                <tr>
                                    <td><?= formatDate($lesson['lesson_date']) ?></td>
                                    <td><?= formatTime($lesson['time_start']) ?> - <?= formatTime($lesson['time_end']) ?></td>
                                    <td><?= e($lesson['teacher_name']) ?></td>
                                    <td><?= e($lesson['subject'] ?? '—') ?></td>
                                    <td><?= $lesson['lesson_type'] === 'group' ? 'Групповое' : 'Индивидуальное' ?></td>
                                    <td><?= $lesson['expected_students'] ?></td>
                                    <td>
                                        <span class="badge badge-<?= $statusBadge['class'] ?>">
                                            <span class="material-icons" style="font-size: 16px;"><?= $statusBadge['icon'] ?></span>
                                            <?= $statusBadge['text'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Recent Lessons -->
            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">Последние уроки</h2>
                    <a href="/zarplata/lessons.php" class="btn btn-text">
                        Все уроки
                        <span class="material-icons" style="font-size: 18px; margin-left: 4px;">arrow_forward</span>
                    </a>
                </div>
                <?php if (empty($recentLessons)): ?>
                    <div class="empty-state">
                        <div class="material-icons">school</div>
                        <p>Нет данных об уроках</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Время</th>
                                <th>Преподаватель</th>
                                <th>Предмет</th>
                                <th>Тип</th>
                                <th>Учеников</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLessons as $lesson): ?>
                                <?php $statusBadge = getLessonStatusBadge($lesson['status']); ?>
                                <tr>
                                    <td><?= formatDate($lesson['lesson_date']) ?></td>
                                    <td><?= formatTime($lesson['time_start']) ?> - <?= formatTime($lesson['time_end']) ?></td>
                                    <td>
                                        <?= e($lesson['teacher_name']) ?>
                                        <?php if ($lesson['substitute_name']): ?>
                                            <br>
                                            <small style="color: var(--text-medium-emphasis);">
                                                Замена: <?= e($lesson['substitute_name']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($lesson['subject'] ?? '—') ?></td>
                                    <td><?= $lesson['lesson_type'] === 'group' ? 'Групповое' : 'Индивидуальное' ?></td>
                                    <td><?= $lesson['actual_students'] ?: $lesson['expected_students'] ?></td>
                                    <td>
                                        <span class="badge badge-<?= $statusBadge['class'] ?>">
                                            <span class="material-icons" style="font-size: 16px;"><?= $statusBadge['icon'] ?></span>
                                            <?= $statusBadge['text'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
