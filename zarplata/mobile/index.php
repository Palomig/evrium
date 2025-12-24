<?php
/**
 * Mobile Dashboard
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получаем статистику
$currentDate = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// Уроки сегодня
$todayLessons = dbQueryOne(
    "SELECT COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
     FROM lessons_instance
     WHERE lesson_date = ?",
    [$currentDate]
);

// Ожидающие выплаты
$pendingPayments = dbQueryOne(
    "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
     FROM payments
     WHERE status = 'pending'",
    []
);

// Выплачено за месяц
$monthPaid = dbQueryOne(
    "SELECT COALESCE(SUM(amount), 0) as total
     FROM payments
     WHERE status = 'paid'
     AND created_at >= ? AND created_at <= ?",
    [$monthStart, $monthEnd . ' 23:59:59']
);

// Ближайшие уроки (сегодня и завтра)
$upcomingLessons = dbQuery(
    "SELECT li.*, COALESCE(t.display_name, t.name) as teacher_name
     FROM lessons_instance li
     LEFT JOIN teachers t ON li.teacher_id = t.id
     WHERE li.lesson_date >= ? AND li.status = 'scheduled'
     ORDER BY li.lesson_date ASC, li.time_start ASC
     LIMIT 5",
    [$currentDate]
);

define('PAGE_TITLE', 'Главная');
define('ACTIVE_PAGE', 'dashboard');

require_once __DIR__ . '/templates/header.php';
?>

<div class="page-container">
    <!-- PWA Install Banner -->
    <div class="pwa-install-banner" id="pwa-install-card" style="display: none;">
        <div class="pwa-install-icon">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
        </div>
        <div class="pwa-install-text">
            <strong>Установить приложение</strong>
            <span>Быстрый доступ с главного экрана</span>
        </div>
        <button class="pwa-install-action" id="pwa-install-btn">Установить</button>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= ($todayLessons['completed'] ?? 0) ?>/<?= ($todayLessons['total'] ?? 0) ?></div>
            <div class="stat-label">Уроки сегодня</div>
        </div>
        <div class="stat-card pending">
            <div class="stat-value"><?= number_format($pendingPayments['total'] ?? 0, 0, '', ' ') ?> ₽</div>
            <div class="stat-label">К выплате</div>
        </div>
        <div class="stat-card paid">
            <div class="stat-value"><?= number_format($monthPaid['total'] ?? 0, 0, '', ' ') ?> ₽</div>
            <div class="stat-label">За месяц</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $pendingPayments['count'] ?? 0 ?></div>
            <div class="stat-label">Выплат ждут</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card mb-2">
        <div class="card-body" style="padding: 12px;">
            <div style="display: flex; gap: 10px;">
                <a href="schedule.php" class="btn btn-primary" style="flex: 1; justify-content: center;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Расписание
                </a>
                <a href="payments.php" class="btn btn-secondary" style="flex: 1; justify-content: center;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Выплаты
                </a>
            </div>
        </div>
    </div>

    <!-- Upcoming Lessons -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Ближайшие уроки</span>
            <a href="schedule.php" style="color: var(--accent); font-size: 13px; text-decoration: none;">Все →</a>
        </div>

        <?php if (empty($upcomingLessons)): ?>
            <div class="empty-state">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <div class="empty-state-title">Нет уроков</div>
                <p class="empty-state-text">Запланированных уроков пока нет</p>
            </div>
        <?php else: ?>
            <div class="lesson-list">
                <?php foreach ($upcomingLessons as $lesson): ?>
                    <?php
                    $isToday = $lesson['lesson_date'] === $currentDate;
                    $dateLabel = $isToday ? 'Сегодня' : formatDate($lesson['lesson_date']);
                    ?>
                    <div class="lesson-list-item">
                        <div class="lesson-time">
                            <div class="lesson-time-value"><?= substr($lesson['time_start'], 0, 5) ?></div>
                            <div class="lesson-time-label"><?= $dateLabel ?></div>
                        </div>
                        <div class="lesson-info">
                            <div class="lesson-subject"><?= htmlspecialchars($lesson['subject'] ?? 'Урок') ?></div>
                            <div class="lesson-teacher"><?= htmlspecialchars($lesson['teacher_name']) ?></div>
                        </div>
                        <div class="lesson-students">
                            <span class="badge badge-<?= $lesson['lesson_type'] === 'group' ? 'group' : 'individual' ?>">
                                <?= $lesson['expected_students'] ?> уч.
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.lesson-list {
    border-top: 1px solid var(--border);
}

.lesson-list-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
}

.lesson-list-item:last-child {
    border-bottom: none;
}

.lesson-time {
    text-align: center;
    min-width: 60px;
}

.lesson-time-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 16px;
    font-weight: 600;
    color: var(--accent);
}

.lesson-time-label {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 2px;
}

.lesson-info {
    flex: 1;
    min-width: 0;
}

.lesson-subject {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.lesson-teacher {
    font-size: 13px;
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.lesson-students {
    flex-shrink: 0;
}
</style>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
