<?php
/**
 * Mobile Payments Page
 * Карточный вид выплат
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Фильтры
$statusFilter = $_GET['status'] ?? 'all';
$teacherFilter = $_GET['teacher_id'] ?? '';

// Получить преподавателей
$teachers = dbQuery("
    SELECT id, COALESCE(display_name, name) as name
    FROM teachers WHERE active = 1 ORDER BY name
", []);

// Строим запрос
$where = [];
$params = [];

if ($statusFilter !== 'all') {
    $where[] = "p.status = ?";
    $params[] = $statusFilter;
}

if ($teacherFilter) {
    $where[] = "p.teacher_id = ?";
    $params[] = $teacherFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Получить выплаты
$payments = dbQuery("
    SELECT p.*, COALESCE(t.display_name, t.name) as teacher_name,
           li.lesson_date, li.time_start, li.subject
    FROM payments p
    LEFT JOIN teachers t ON p.teacher_id = t.id
    LEFT JOIN lessons_instance li ON p.lesson_instance_id = li.id
    $whereClause
    ORDER BY p.created_at DESC
    LIMIT 50
", $params);

// Статистика
$stats = dbQueryOne("
    SELECT
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) as approved,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as paid
    FROM payments
    WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
", []);

define('PAGE_TITLE', 'Выплаты');
define('ACTIVE_PAGE', 'payments');

require_once __DIR__ . '/templates/header.php';

$statusLabels = [
    'pending' => ['text' => 'Ожидает', 'class' => 'pending'],
    'approved' => ['text' => 'Одобрено', 'class' => 'approved'],
    'paid' => ['text' => 'Выплачено', 'class' => 'paid'],
    'cancelled' => ['text' => 'Отменено', 'class' => 'cancelled']
];
?>

<style>
.payment-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    margin-bottom: 12px;
    overflow: hidden;
}

.payment-card-main {
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.payment-teacher {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 4px;
}

.payment-date {
    font-size: 13px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 6px;
}

.payment-date svg {
    width: 14px;
    height: 14px;
    opacity: 0.6;
}

.payment-amount {
    text-align: right;
}

.payment-amount-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 18px;
    font-weight: 600;
    color: var(--status-green);
}

.payment-card-footer {
    padding: 12px 16px;
    background: var(--bg-elevated);
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.payment-type {
    font-size: 12px;
    color: var(--text-muted);
}

.payment-actions {
    display: flex;
    gap: 8px;
}

.payment-action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: none;
    background: var(--bg-card);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
}

.payment-action-btn:active {
    background: var(--accent-dim);
    color: var(--accent);
}

.payment-action-btn.approve {
    color: var(--status-green);
}

.payment-action-btn.pay {
    color: var(--status-blue);
}

.payment-action-btn.cancel {
    color: var(--status-rose);
}

.payment-action-btn svg {
    width: 18px;
    height: 18px;
}
</style>

<div class="page-container">
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-value"><?= number_format($stats['pending'], 0, '', ' ') ?> ₽</div>
            <div class="stat-label">Ожидает</div>
        </div>
        <div class="stat-card approved">
            <div class="stat-value"><?= number_format($stats['approved'], 0, '', ' ') ?> ₽</div>
            <div class="stat-label">Одобрено</div>
        </div>
        <div class="stat-card paid">
            <div class="stat-value"><?= number_format($stats['paid'], 0, '', ' ') ?> ₽</div>
            <div class="stat-label">Выплачено</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-pills">
        <a href="?status=all" class="filter-pill <?= $statusFilter === 'all' ? 'active' : '' ?>">Все</a>
        <a href="?status=pending" class="filter-pill <?= $statusFilter === 'pending' ? 'active' : '' ?>">Ожидает</a>
        <a href="?status=approved" class="filter-pill <?= $statusFilter === 'approved' ? 'active' : '' ?>">Одобрено</a>
        <a href="?status=paid" class="filter-pill <?= $statusFilter === 'paid' ? 'active' : '' ?>">Выплачено</a>
    </div>

    <!-- Payments List -->
    <div class="payment-list" style="margin-top: 16px;">
        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <div class="empty-state-title">Нет выплат</div>
                <p class="empty-state-text">Выплаты появятся после проведения уроков</p>
            </div>
        <?php else: ?>
            <?php foreach ($payments as $payment): ?>
                <?php $status = $statusLabels[$payment['status']] ?? $statusLabels['pending']; ?>
                <div class="payment-card" data-id="<?= $payment['id'] ?>">
                    <div class="payment-card-main">
                        <div>
                            <div class="payment-teacher"><?= htmlspecialchars($payment['teacher_name']) ?></div>
                            <div class="payment-date">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <?php if ($payment['lesson_date']): ?>
                                    <?= date('d.m', strtotime($payment['lesson_date'])) ?>
                                    <?= substr($payment['time_start'], 0, 5) ?>
                                    — <?= htmlspecialchars($payment['subject'] ?? 'Урок') ?>
                                <?php else: ?>
                                    <?= date('d.m.Y', strtotime($payment['created_at'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="payment-amount">
                            <div class="payment-amount-value"><?= number_format($payment['amount'], 0, '', ' ') ?> ₽</div>
                            <span class="badge badge-<?= $status['class'] ?>"><?= $status['text'] ?></span>
                        </div>
                    </div>
                    <div class="payment-card-footer">
                        <span class="payment-type">
                            <?php
                            $types = ['lesson' => 'За урок', 'bonus' => 'Бонус', 'penalty' => 'Штраф', 'adjustment' => 'Корректировка'];
                            echo $types[$payment['payment_type']] ?? 'Выплата';
                            ?>
                        </span>
                        <div class="payment-actions">
                            <?php if ($payment['status'] === 'pending'): ?>
                                <button class="payment-action-btn approve" onclick="updatePayment(<?= $payment['id'] ?>, 'approved')" title="Одобрить">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            <?php endif; ?>
                            <?php if ($payment['status'] === 'approved'): ?>
                                <button class="payment-action-btn pay" onclick="updatePayment(<?= $payment['id'] ?>, 'paid')" title="Выплатить">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </button>
                            <?php endif; ?>
                            <?php if ($payment['status'] !== 'cancelled' && $payment['status'] !== 'paid'): ?>
                                <button class="payment-action-btn cancel" onclick="updatePayment(<?= $payment['id'] ?>, 'cancelled')" title="Отменить">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
async function updatePayment(id, status) {
    const statusNames = {
        'approved': 'одобрить',
        'paid': 'отметить выплаченной',
        'cancelled': 'отменить'
    };

    if (!confirm(`Вы уверены, что хотите ${statusNames[status]} эту выплату?`)) {
        return;
    }

    try {
        MobileApp.showLoading();

        const response = await fetch('../api/payments.php?action=update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, status })
        });

        const result = await response.json();

        if (result.success) {
            MobileApp.showToast('Статус обновлён', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            MobileApp.showToast(result.error || 'Ошибка', 'error');
        }
    } catch (error) {
        MobileApp.showToast('Ошибка сети', 'error');
    } finally {
        MobileApp.hideLoading();
    }
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
