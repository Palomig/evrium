<?php
/**
 * Evrium CRM - Teacher Dashboard
 * Панель преподавателя
 */

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

requireLogin();

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
$userName = getCurrentUserName();

// Получение статистики
$stats = dbQueryOne("
    SELECT
        COUNT(DISTINCT s.id) as total_students,
        COUNT(DISTINCT l.id) as total_lessons,
        SUM(p.amount) as total_revenue,
        SUM(CASE WHEN s.status = 'задолженность' THEN 1 ELSE 0 END) as students_with_debt
    FROM students s
    LEFT JOIN lessons l ON s.id = l.student_id AND l.teacher_id = ?
    LEFT JOIN payments p ON s.id = p.student_id AND p.teacher_id = ?
    WHERE s.teacher_id = ?
", [$userId, $userId, $userId]);

// Получение последних учеников
$recentStudents = dbQuery("
    SELECT s.*, COUNT(l.id) as lesson_count
    FROM students s
    LEFT JOIN lessons l ON s.id = l.student_id
    WHERE s.teacher_id = ?
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT 5
", [$userId]);

// Получение ближайших уроков (если есть таблица расписания)
$upcomingLessons = dbQuery("
    SELECT l.*, s.name as student_name, s.class
    FROM lessons l
    INNER JOIN students s ON l.student_id = s.id
    WHERE l.teacher_id = ? AND l.date >= CURDATE()
    ORDER BY l.date ASC
    LIMIT 5
", [$userId]);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления - Evrium CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/crm/assets/css/dashboard.css">
    <style>
        :root {
            --bg-dark: #000000;
            --bg-secondary: #1d1d1f;
            --bg-tertiary: #2d2d2f;
            --text-light: #f5f5f7;
            --text-secondary: #a1a1a6;
            --accent: #0071e3;
            --accent-hover: #0077ed;
            --success: #30d158;
            --warning: #ffd60a;
            --danger: #ff453a;
            --border: #424245;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-light);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border);
            padding: 20px;
            overflow-y: auto;
        }

        .sidebar .logo {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--success));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 32px;
            text-align: center;
        }

        .sidebar .nav-link {
            color: var(--text-secondary);
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 4px;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: var(--bg-tertiary);
            color: var(--text-light);
        }

        .main-content {
            margin-left: 260px;
            padding: 32px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: var(--accent);
        }

        .stat-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .stat-card .icon.blue { background: rgba(0, 113, 227, 0.15); color: var(--accent); }
        .stat-card .icon.green { background: rgba(48, 209, 88, 0.15); color: var(--success); }
        .stat-card .icon.yellow { background: rgba(255, 214, 10, 0.15); color: var(--warning); }
        .stat-card .icon.red { background: rgba(255, 69, 58, 0.15); color: var(--danger); }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-card .label {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .table {
            color: var(--text-light);
        }

        .table thead {
            border-bottom: 1px solid var(--border);
        }

        .table tbody tr {
            border-bottom: 1px solid var(--border);
        }

        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }

        .btn-primary {
            background: var(--accent);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: var(--bg-tertiary);
            border-radius: 12px;
            cursor: pointer;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">Evrium</div>

        <nav>
            <a href="/crm/dashboard.php" class="nav-link active">
                <i class="bi bi-speedometer2"></i> Панель управления
            </a>
            <a href="/crm/students.php" class="nav-link">
                <i class="bi bi-people"></i> Ученики
            </a>
            <a href="/crm/lessons.php" class="nav-link">
                <i class="bi bi-book"></i> Уроки
            </a>
            <a href="/crm/payments.php" class="nav-link">
                <i class="bi bi-cash-stack"></i> Оплаты
            </a>
            <a href="/crm/skills.php" class="nav-link">
                <i class="bi bi-graph-up"></i> Навыки
            </a>
            <a href="/crm/reports.php" class="nav-link">
                <i class="bi bi-file-earmark-text"></i> Отчёты
            </a>
            <a href="/crm/materials.php" class="nav-link">
                <i class="bi bi-folder"></i> Материалы
            </a>

            <?php if (isSuperAdmin()): ?>
            <hr style="border-color: var(--border); margin: 20px 0;">
            <a href="/crm/admin/dashboard.php" class="nav-link">
                <i class="bi bi-shield-check"></i> Админ-панель
            </a>
            <?php endif; ?>

            <hr style="border-color: var(--border); margin: 20px 0;">
            <a href="/crm/settings.php" class="nav-link">
                <i class="bi bi-gear"></i> Настройки
            </a>
            <a href="/crm/logout.php" class="nav-link">
                <i class="bi bi-box-arrow-right"></i> Выход
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div>
                <h1 style="font-size: 32px; font-weight: 700; margin-bottom: 4px;">Добро пожаловать, <?= e($userName) ?>!</h1>
                <p style="color: var(--text-secondary);">Вот что происходит сегодня</p>
            </div>

            <div class="user-menu">
                <div class="user-avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
                <div>
                    <div style="font-weight: 600;"><?= e($userName) ?></div>
                    <div style="font-size: 12px; color: var(--text-secondary);">
                        <?= $userRole === 'superadmin' ? 'Супер-админ' : 'Преподаватель' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon blue"><i class="bi bi-people"></i></div>
                <div class="value"><?= $stats['total_students'] ?? 0 ?></div>
                <div class="label">Всего учеников</div>
            </div>

            <div class="stat-card">
                <div class="icon green"><i class="bi bi-book"></i></div>
                <div class="value"><?= $stats['total_lessons'] ?? 0 ?></div>
                <div class="label">Проведено уроков</div>
            </div>

            <div class="stat-card">
                <div class="icon yellow"><i class="bi bi-cash-stack"></i></div>
                <div class="value"><?= formatMoney($stats['total_revenue'] ?? 0) ?></div>
                <div class="label">Общий доход</div>
            </div>

            <div class="stat-card">
                <div class="icon red"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="value"><?= $stats['students_with_debt'] ?? 0 ?></div>
                <div class="label">Задолженности</div>
            </div>
        </div>

        <!-- Recent Students -->
        <div class="card">
            <div class="card-title">Последние ученики</div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Имя</th>
                            <th>Класс</th>
                            <th>Телефон</th>
                            <th>Баланс</th>
                            <th>Статус</th>
                            <th>Уроков</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentStudents)): ?>
                        <tr>
                            <td colspan="7" class="text-center" style="color: var(--text-secondary); padding: 40px;">
                                <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 16px;"></i>
                                Пока нет учеников
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($recentStudents as $student): ?>
                            <tr>
                                <td><?= e($student['name']) ?></td>
                                <td><?= e($student['class']) ?> класс</td>
                                <td><?= e($student['phone'] ?? '-') ?></td>
                                <td><?= formatMoney($student['balance']) ?></td>
                                <td><?= getStatusBadge($student['status']) ?></td>
                                <td><?= $student['lesson_count'] ?></td>
                                <td>
                                    <a href="/crm/student.php?id=<?= $student['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-3">
                <a href="/crm/students.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Добавить ученика
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
