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

// Получить список преподавателей для селекта
$teachers = dbQuery("SELECT id, name FROM teachers WHERE active = 1 ORDER BY name", []);

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
        <button class="btn btn-primary" onclick="openPaymentModal()">
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
                                <button class="btn btn-text" onclick="approvePayment(<?= $payment['id'] ?>)" title="Одобрить">
                                    <span class="material-icons" style="font-size: 18px;">thumb_up</span>
                                </button>
                            <?php endif; ?>
                            <?php if ($payment['status'] === 'approved'): ?>
                                <button class="btn btn-text" onclick="openMarkPaidModal(<?= $payment['id'] ?>)" title="Отметить выплаченной">
                                    <span class="material-icons" style="font-size: 18px;">check</span>
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-text" onclick="viewPayment(<?= $payment['id'] ?>)" title="Просмотр">
                                <span class="material-icons" style="font-size: 18px;">visibility</span>
                            </button>
                            <?php if ($payment['status'] !== 'paid'): ?>
                                <button class="btn btn-text" onclick="cancelPayment(<?= $payment['id'] ?>)" title="Отменить">
                                    <span class="material-icons" style="font-size: 18px; color: var(--md-warning);">cancel</span>
                                </button>
                            <?php endif; ?>
                            <?php if ($payment['status'] !== 'paid' && $payment['payment_type'] !== 'lesson'): ?>
                                <button class="btn btn-text" onclick="deletePayment(<?= $payment['id'] ?>)" title="Удалить">
                                    <span class="material-icons" style="font-size: 18px; color: var(--md-error);">delete</span>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Модальное окно добавления разовой выплаты -->
<div id="payment-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Разовая выплата</h3>
            <button class="modal-close" onclick="closePaymentModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <form id="payment-form" onsubmit="savePayment(event)">
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="payment-teacher">Преподаватель *</label>
                    <select id="payment-teacher" name="teacher_id" required>
                        <option value="">Выберите преподавателя</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>"><?= e($teacher['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="payment-type">Тип выплаты *</label>
                    <select id="payment-type" name="payment_type" required>
                        <option value="bonus">Премия</option>
                        <option value="penalty">Штраф</option>
                        <option value="adjustment">Корректировка</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="payment-amount">Сумма (₽) *</label>
                    <input type="number" id="payment-amount" name="amount" step="0.01" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="payment-date">Дата</label>
                    <input type="date" id="payment-date" name="payment_date">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="payment-comment">Комментарий</label>
                    <textarea id="payment-comment" name="comment" rows="3"></textarea>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-text" onclick="closePaymentModal()">Отмена</button>
                <button type="submit" class="btn btn-primary" id="save-payment-btn">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                    Сохранить
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно просмотра выплаты -->
<div id="view-payment-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Детали выплаты</h3>
            <button class="modal-close" onclick="closeViewModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div id="view-payment-content" style="padding: 24px;">
            <p style="text-align: center;">Загрузка...</p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-primary" onclick="closeViewModal()">Закрыть</button>
        </div>
    </div>
</div>

<!-- Модальное окно отметки как выплаченной -->
<div id="mark-paid-modal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Отметить выплаченной</h3>
            <button class="modal-close" onclick="closeMarkPaidModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <form onsubmit="saveMarkPaid(event)">
            <input type="hidden" id="mark-paid-id">
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="mark-paid-date">Дата выплаты</label>
                    <input type="date" id="mark-paid-date" name="payment_date" required>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-text" onclick="closeMarkPaidModal()">Отмена</button>
                <button type="submit" class="btn btn-primary" id="mark-paid-btn">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">check_circle</span>
                    Отметить выплаченной
                </button>
            </div>
        </form>
    </div>
</div>

<script src="/zarplata/assets/js/payments.js"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
