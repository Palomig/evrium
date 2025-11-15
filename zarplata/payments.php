<?php
/**
 * Страница выплат
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить все выплаты
$payments = dbQuery(
    "SELECT p.*, t.name as teacher_name,
            li.lesson_date, li.time_start, li.subject
     FROM payments p
     LEFT JOIN teachers t ON p.teacher_id = t.id
     LEFT JOIN lessons_instance li ON p.lesson_instance_id = li.id
     ORDER BY p.created_at DESC
     LIMIT 100",
    []
);

// Статистика
$stats = dbQueryOne(
    "SELECT
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_total,
        SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_total,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_total,
        SUM(CASE WHEN status != 'cancelled' THEN amount ELSE 0 END) as total_amount
     FROM payments",
    []
);

define('PAGE_TITLE', 'Выплаты');
define('PAGE_SUBTITLE', 'Управление начислениями и выплатами');
define('ACTIVE_PAGE', 'payments');

require_once __DIR__ . '/templates/header.php';
?>

<!-- Статистика -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value"><?= formatMoney($stats['pending_total'] ?? 0) ?></div>
                <div class="stat-card-label">Ожидают</div>
            </div>
            <div class="stat-card-icon warning">
                <span class="material-icons">pending</span>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value"><?= formatMoney($stats['approved_total'] ?? 0) ?></div>
                <div class="stat-card-label">Одобрено</div>
            </div>
            <div class="stat-card-icon primary">
                <span class="material-icons">thumb_up</span>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value"><?= formatMoney($stats['paid_total'] ?? 0) ?></div>
                <div class="stat-card-label">Выплачено</div>
            </div>
            <div class="stat-card-icon success">
                <span class="material-icons">check_circle</span>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value"><?= formatMoney($stats['total_amount'] ?? 0) ?></div>
                <div class="stat-card-label">Всего</div>
            </div>
            <div class="stat-card-icon secondary">
                <span class="material-icons">account_balance</span>
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

<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Все выплаты</h2>
        <button class="btn btn-primary" onclick="alert('Функция добавления разовой выплаты будет реализована позже')">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Разовая выплата
        </button>
    </div>

    <?php if (empty($payments)): ?>
        <div class="empty-state">
            <div class="material-icons">payments</div>
            <p>Нет данных о выплатах</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Дата</th>
                    <th>Преподаватель</th>
                    <th>Тип</th>
                    <th>Урок</th>
                    <th>Сумма</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <?php $statusBadge = getPaymentStatusBadge($payment['status']); ?>
                    <tr>
                        <td><?= $payment['id'] ?></td>
                        <td><?= formatDate($payment['created_at']) ?></td>
                        <td><?= e($payment['teacher_name']) ?></td>
                        <td>
                            <?php
                            $typeLabels = [
                                'lesson' => 'Урок',
                                'bonus' => 'Премия',
                                'penalty' => 'Штраф',
                                'adjustment' => 'Корректировка'
                            ];
                            echo $typeLabels[$payment['payment_type']] ?? $payment['payment_type'];
                            ?>
                        </td>
                        <td>
                            <?php if ($payment['lesson_date']): ?>
                                <?= formatDate($payment['lesson_date']) ?> <?= formatTime($payment['time_start']) ?>
                                <?php if ($payment['subject']): ?>
                                    <br><small><?= e($payment['subject']) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><strong><?= formatMoney($payment['amount']) ?></strong></td>
                        <td>
                            <span class="badge badge-<?= $statusBadge['class'] ?>">
                                <span class="material-icons" style="font-size: 14px;"><?= $statusBadge['icon'] ?></span>
                                <?= $statusBadge['text'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($payment['status'] === 'pending'): ?>
                                <button class="btn btn-text" onclick="alert('Одобрить выплату #<?= $payment['id'] ?>')">
                                    <span class="material-icons" style="font-size: 18px;">thumb_up</span>
                                </button>
                            <?php endif; ?>
                            <?php if ($payment['status'] === 'approved'): ?>
                                <button class="btn btn-text" onclick="alert('Отметить как выплачено #<?= $payment['id'] ?>')">
                                    <span class="material-icons" style="font-size: 18px;">check</span>
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-text" onclick="alert('Просмотр выплаты #<?= $payment['id'] ?>')">
                                <span class="material-icons" style="font-size: 18px;">visibility</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
