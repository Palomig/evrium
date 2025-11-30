<?php
/**
 * Главная страница (Dashboard)
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
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

define('PAGE_TITLE', 'Dashboard');
define('PAGE_SUBTITLE', 'Обзор системы учёта зарплаты преподавателей');
define('ACTIVE_PAGE', 'dashboard');

require_once __DIR__ . '/templates/header.php';
?>

<!-- Stats Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin-bottom: 32px;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 2rem; font-weight: 300; margin-bottom: 4px;">
                    <?= $todayLessons[0]['completed'] ?? 0 ?> / <?= $todayLessons[0]['total'] ?? 0 ?>
                </div>
                <div style="font-size: 0.875rem; color: var(--text-medium-emphasis);">Уроки сегодня</div>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background-color: rgba(187, 134, 252, 0.12); color: var(--md-primary);">
                <span class="material-icons">today</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 2rem; font-weight: 300; margin-bottom: 4px;">
                    <?= $weekLessons[0]['completed'] ?? 0 ?> / <?= $weekLessons[0]['total'] ?? 0 ?>
                </div>
                <div style="font-size: 0.875rem; color: var(--text-medium-emphasis);">Уроки за неделю</div>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background-color: rgba(3, 218, 198, 0.12); color: var(--md-secondary);">
                <span class="material-icons">event_note</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 2rem; font-weight: 300; margin-bottom: 4px;">
                    <?= formatMoney($pendingPayments['total'] ?? 0) ?>
                </div>
                <div style="font-size: 0.875rem; color: var(--text-medium-emphasis);">Ожидают выплаты</div>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background-color: rgba(255, 152, 0, 0.12); color: var(--md-warning);">
                <span class="material-icons">pending</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="font-size: 2rem; font-weight: 300; margin-bottom: 4px;">
                    <?= formatMoney($monthPayments['total'] ?? 0) ?>
                </div>
                <div style="font-size: 0.875rem; color: var(--text-medium-emphasis);">Выплачено за месяц</div>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background-color: rgba(76, 175, 80, 0.12); color: var(--md-success);">
                <span class="material-icons">account_balance</span>
            </div>
        </div>
    </div>
</div>

<!-- Upcoming Lessons -->
<div class="card mb-4">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0;">Ближайшие уроки</h3>
        <a href="/zarplata/schedule.php" class="btn btn-text">
            Все уроки
            <span class="material-icons" style="font-size: 18px; margin-left: 4px;">arrow_forward</span>
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($upcomingLessons)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-disabled);">
                <div class="material-icons" style="font-size: 64px; margin-bottom: 16px; opacity: 0.3;">event_busy</div>
                <p>Нет запланированных уроков</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table">
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
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Lessons -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0;">Последние уроки</h3>
        <a href="/zarplata/lessons.php" class="btn btn-text">
            Все уроки
            <span class="material-icons" style="font-size: 18px; margin-left: 4px;">arrow_forward</span>
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($recentLessons)): ?>
            <div style="text-align: center; padding: 48px 24px; color: var(--text-disabled);">
                <div class="material-icons" style="font-size: 64px; margin-bottom: 16px; opacity: 0.3;">school</div>
                <p>Нет данных об уроках</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table">
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
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
