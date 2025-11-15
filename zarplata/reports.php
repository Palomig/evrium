<?php
/**
 * Страница отчётов
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить статистику по преподавателям
$teacherStats = dbQuery(
    "SELECT * FROM teacher_stats ORDER BY total_earned DESC",
    []
);

// Статистика за текущий месяц
$currentMonth = date('Y-m');
$monthStats = dbQueryOne(
    "SELECT
        COUNT(DISTINCT li.id) as lessons_count,
        SUM(CASE WHEN li.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN li.status = 'completed' THEN li.actual_students ELSE 0 END) as total_students,
        COALESCE(SUM(p.amount), 0) as total_paid
     FROM lessons_instance li
     LEFT JOIN payments p ON li.id = p.lesson_instance_id AND p.status != 'cancelled'
     WHERE DATE_FORMAT(li.lesson_date, '%Y-%m') = ?",
    [$currentMonth]
);

define('PAGE_TITLE', 'Отчёты');
define('PAGE_SUBTITLE', 'Аналитика и статистика');
define('ACTIVE_PAGE', 'reports');

require_once __DIR__ . '/templates/header.php';
?>

<!-- Статистика за месяц -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value"><?= $monthStats['lessons_count'] ?? 0 ?></div>
                <div class="stat-card-label">Уроков за месяц</div>
            </div>
            <div class="stat-card-icon primary">
                <span class="material-icons">school</span>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value"><?= $monthStats['completed_count'] ?? 0 ?></div>
                <div class="stat-card-label">Завершено</div>
            </div>
            <div class="stat-card-icon success">
                <span class="material-icons">check_circle</span>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value"><?= $monthStats['total_students'] ?? 0 ?></div>
                <div class="stat-card-label">Учеников обучено</div>
            </div>
            <div class="stat-card-icon info">
                <span class="material-icons">groups</span>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value"><?= formatMoney($monthStats['total_paid'] ?? 0) ?></div>
                <div class="stat-card-label">Начислено за месяц</div>
            </div>
            <div class="stat-card-icon secondary">
                <span class="material-icons">payments</span>
            </div>
        </div>
    </div>
</div>

<style>
    .stats-grid {
        display: grid;
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

    .stat-card-icon.info {
        background-color: rgba(33, 150, 243, 0.12);
        color: var(--md-info);
    }

    .stat-card-value {
        font-size: 1.5rem;
        font-weight: 300;
        margin-bottom: 4px;
    }

    .stat-card-label {
        font-size: 0.875rem;
        color: var(--text-medium-emphasis);
    }
</style>

<!-- Статистика по преподавателям -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Статистика по преподавателям</h2>
        <div>
            <button class="btn btn-outline" onclick="alert('Функция экспорта в Excel будет реализована позже')">
                <span class="material-icons" style="margin-right: 8px; font-size: 18px;">download</span>
                Экспорт в Excel
            </button>
            <button class="btn btn-outline" onclick="alert('Функция генерации PDF будет реализована позже')" style="margin-left: 8px;">
                <span class="material-icons" style="margin-right: 8px; font-size: 18px;">picture_as_pdf</span>
                Генерация PDF
            </button>
        </div>
    </div>

    <?php if (empty($teacherStats)): ?>
        <div class="empty-state">
            <div class="material-icons">assessment</div>
            <p>Нет данных для отчёта</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Преподаватель</th>
                    <th>Уроков всего</th>
                    <th>Завершено</th>
                    <th>Всего заработано</th>
                    <th>Выплачено</th>
                    <th>К выплате</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teacherStats as $stat): ?>
                    <tr>
                        <td><strong><?= e($stat['teacher_name']) ?></strong></td>
                        <td><?= $stat['total_lessons'] ?? 0 ?></td>
                        <td>
                            <?= $stat['completed_lessons'] ?? 0 ?>
                            <?php if ($stat['total_lessons'] > 0): ?>
                                <br>
                                <small style="color: var(--text-medium-emphasis);">
                                    <?= round(($stat['completed_lessons'] / $stat['total_lessons']) * 100) ?>%
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= formatMoney($stat['total_earned'] ?? 0) ?></strong></td>
                        <td><?= formatMoney($stat['total_paid'] ?? 0) ?></td>
                        <td>
                            <strong style="color: var(--md-warning);">
                                <?= formatMoney($stat['pending_amount'] ?? 0) ?>
                            </strong>
                        </td>
                        <td>
                            <button class="btn btn-text" onclick="alert('Детальный отчёт по преподавателю #<?= $stat['teacher_id'] ?>')">
                                <span class="material-icons" style="font-size: 18px;">visibility</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background-color: var(--md-surface-2); font-weight: 500;">
                <tr>
                    <td>ИТОГО</td>
                    <td><?= array_sum(array_column($teacherStats, 'total_lessons')) ?></td>
                    <td><?= array_sum(array_column($teacherStats, 'completed_lessons')) ?></td>
                    <td><strong><?= formatMoney(array_sum(array_column($teacherStats, 'total_earned'))) ?></strong></td>
                    <td><?= formatMoney(array_sum(array_column($teacherStats, 'total_paid'))) ?></td>
                    <td>
                        <strong style="color: var(--md-warning);">
                            <?= formatMoney(array_sum(array_column($teacherStats, 'pending_amount'))) ?>
                        </strong>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>

<!-- Фильтры отчётов -->
<div class="card mt-4">
    <div class="card-header">
        <h3 style="margin: 0;">
            <span class="material-icons" style="vertical-align: middle;">filter_list</span>
            Фильтры отчётов
        </h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
            <div class="form-group">
                <label class="form-label">Период</label>
                <select class="form-control">
                    <option>Текущий месяц</option>
                    <option>Предыдущий месяц</option>
                    <option>Текущая неделя</option>
                    <option>Произвольный период</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Преподаватель</label>
                <select class="form-control">
                    <option>Все преподаватели</option>
                    <?php foreach ($teacherStats as $stat): ?>
                        <option value="<?= $stat['teacher_id'] ?>">
                            <?= e($stat['teacher_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Статус выплат</label>
                <select class="form-control">
                    <option>Все статусы</option>
                    <option>Ожидают</option>
                    <option>Одобрено</option>
                    <option>Выплачено</option>
                </select>
            </div>

            <div class="form-group" style="display: flex; align-items: flex-end;">
                <button class="btn btn-primary btn-block" onclick="alert('Функция фильтрации будет реализована позже')">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">search</span>
                    Применить фильтры
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
