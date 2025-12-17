<?php
/**
 * Mobile Payments Page
 * Карточный вид выплат с переключением недель
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Определяем текущую неделю
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$baseDate = new DateTime();
if ($weekOffset !== 0) {
    $baseDate->modify("{$weekOffset} weeks");
}

// Получаем понедельник и воскресенье выбранной недели
$monday = clone $baseDate;
$monday->modify('monday this week');
$sunday = clone $monday;
$sunday->modify('+6 days');

$weekStart = $monday->format('Y-m-d');
$weekEnd = $sunday->format('Y-m-d');

// Форматирование для отображения
$weekLabel = $monday->format('d') . '–' . $sunday->format('d M');
$isCurrentWeek = ($weekOffset === 0);

// Фильтры
$statusFilter = $_GET['status'] ?? 'all';

// Получить преподавателей
$teachers = dbQuery("
    SELECT id, COALESCE(display_name, name) as name
    FROM teachers WHERE active = 1 ORDER BY name
", []);

// Строим запрос с фильтром по неделе
$where = ["(li.lesson_date BETWEEN ? AND ? OR (li.lesson_date IS NULL AND DATE(p.created_at) BETWEEN ? AND ?))"];
$params = [$weekStart, $weekEnd, $weekStart, $weekEnd];

if ($statusFilter !== 'all') {
    $where[] = "p.status = ?";
    $params[] = $statusFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Получить выплаты за неделю
$payments = dbQuery("
    SELECT p.*, COALESCE(t.display_name, t.name) as teacher_name,
           li.lesson_date, li.time_start, li.subject
    FROM payments p
    LEFT JOIN teachers t ON p.teacher_id = t.id
    LEFT JOIN lessons_instance li ON p.lesson_instance_id = li.id
    $whereClause
    ORDER BY COALESCE(li.lesson_date, DATE(p.created_at)) DESC, li.time_start DESC
", $params);

// Статистика за выбранную неделю
$stats = dbQueryOne("
    SELECT
        COALESCE(SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN p.status = 'approved' THEN p.amount ELSE 0 END), 0) as approved,
        COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END), 0) as paid
    FROM payments p
    LEFT JOIN lessons_instance li ON p.lesson_instance_id = li.id
    WHERE (li.lesson_date BETWEEN ? AND ? OR (li.lesson_date IS NULL AND DATE(p.created_at) BETWEEN ? AND ?))
", [$weekStart, $weekEnd, $weekStart, $weekEnd]);

define('PAGE_TITLE', 'Выплаты');
define('ACTIVE_PAGE', 'payments');

require_once __DIR__ . '/templates/header.php';

$statusLabels = [
    'pending' => ['text' => 'Ожидает', 'class' => 'pending'],
    'approved' => ['text' => 'Одобрено', 'class' => 'approved'],
    'paid' => ['text' => 'Выплачено', 'class' => 'paid'],
    'cancelled' => ['text' => 'Отменено', 'class' => 'cancelled']
];

// Функция для сохранения параметров при переходах
function buildUrl($newParams = []) {
    $params = $_GET;
    foreach ($newParams as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return '?' . http_build_query($params);
}
?>

<style>
/* Week Navigator */
.week-navigator {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: var(--bg-card);
    border-bottom: 1px solid var(--border);
}

.week-nav-btn {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--bg-elevated);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
}

.week-nav-btn:active {
    background: var(--accent-dim);
    color: var(--accent);
    border-color: var(--accent);
}

.week-nav-btn svg {
    width: 20px;
    height: 20px;
}

.week-nav-center {
    text-align: center;
}

.week-nav-label {
    font-size: 16px;
    font-weight: 600;
}

.week-nav-sublabel {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

.week-nav-today {
    display: inline-block;
    margin-top: 6px;
    padding: 4px 12px;
    background: var(--accent-dim);
    color: var(--accent);
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
}

/* Payment cards */
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

.payment-action-btn.approve { color: var(--status-green); }
.payment-action-btn.pay { color: var(--status-blue); }
.payment-action-btn.cancel { color: var(--status-rose); }

.payment-action-btn svg {
    width: 18px;
    height: 18px;
}

/* Day group - collapsible */
.day-group {
    margin-bottom: 12px;
}

.day-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.day-group-header:active {
    background: var(--bg-elevated);
}

.day-group-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.day-group-date {
    font-weight: 600;
    font-size: 15px;
}

.day-group-count {
    font-size: 12px;
    color: var(--text-muted);
    background: var(--bg-elevated);
    padding: 2px 8px;
    border-radius: 10px;
}

.day-group-amount {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 600;
    color: var(--status-green);
}

.day-group-toggle {
    color: var(--text-muted);
    transition: transform 0.2s ease;
}

.day-group.expanded .day-group-toggle {
    transform: rotate(180deg);
}

.day-group-content {
    display: none;
    padding: 8px 0 0 0;
}

.day-group.expanded .day-group-content {
    display: block;
}

.day-group.expanded .day-group-header {
    border-radius: 12px 12px 0 0;
    border-bottom: none;
}

/* Payment card inside day group */
.day-group-content .payment-card {
    margin-bottom: 1px;
    border-radius: 0;
    border-left: 1px solid var(--border);
    border-right: 1px solid var(--border);
    border-top: none;
    border-bottom: 1px solid var(--border);
}

.day-group-content .payment-card:last-child {
    border-radius: 0 0 12px 12px;
}

.day-group-content .payment-card:first-child {
    border-top: 1px solid var(--border);
}
</style>

<!-- Week Navigator -->
<div class="week-navigator">
    <a href="<?= buildUrl(['week' => $weekOffset - 1]) ?>" class="week-nav-btn">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>

    <div class="week-nav-center">
        <div class="week-nav-label"><?= $weekLabel ?></div>
        <div class="week-nav-sublabel">
            <?php if ($isCurrentWeek): ?>
                Текущая неделя
            <?php else: ?>
                <?= $monday->format('Y') ?>
            <?php endif; ?>
        </div>
        <?php if (!$isCurrentWeek): ?>
            <a href="<?= buildUrl(['week' => null]) ?>" class="week-nav-today">Сегодня</a>
        <?php endif; ?>
    </div>

    <a href="<?= buildUrl(['week' => $weekOffset + 1]) ?>" class="week-nav-btn">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
</div>

<div class="page-container">
    <!-- Stats for selected week -->
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

    <!-- Status Filters -->
    <div class="filter-pills">
        <a href="<?= buildUrl(['status' => 'all']) ?>" class="filter-pill <?= $statusFilter === 'all' ? 'active' : '' ?>">Все</a>
        <a href="<?= buildUrl(['status' => 'pending']) ?>" class="filter-pill <?= $statusFilter === 'pending' ? 'active' : '' ?>">Ожидает</a>
        <a href="<?= buildUrl(['status' => 'approved']) ?>" class="filter-pill <?= $statusFilter === 'approved' ? 'active' : '' ?>">Одобрено</a>
        <a href="<?= buildUrl(['status' => 'paid']) ?>" class="filter-pill <?= $statusFilter === 'paid' ? 'active' : '' ?>">Выплачено</a>
    </div>

    <!-- Payments List grouped by day -->
    <div class="payment-list" style="margin-top: 16px;">
        <?php if (empty($payments)): ?>
            <div class="empty-state">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <div class="empty-state-title">Нет выплат</div>
                <p class="empty-state-text">За эту неделю выплат нет</p>
            </div>
        <?php else: ?>
            <?php
            // Группируем выплаты по дням
            $paymentsByDay = [];
            $dayNames = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

            foreach ($payments as $payment) {
                $paymentDate = $payment['lesson_date'] ?? date('Y-m-d', strtotime($payment['created_at']));
                if (!isset($paymentsByDay[$paymentDate])) {
                    $paymentsByDay[$paymentDate] = [];
                }
                $paymentsByDay[$paymentDate][] = $payment;
            }

            foreach ($paymentsByDay as $date => $dayPayments):
                $dateObj = new DateTime($date);
                $dayOfWeek = $dayNames[(int)$dateObj->format('w')];
                $dayTotal = array_sum(array_column($dayPayments, 'amount'));
                $isToday = $date === date('Y-m-d');
            ?>
                <div class="day-group<?= $isToday ? ' expanded' : '' ?>" data-date="<?= $date ?>">
                    <div class="day-group-header" onclick="toggleDayGroup(this.parentElement)">
                        <div class="day-group-header-left">
                            <span class="day-group-date"><?= $dayOfWeek ?>, <?= $dateObj->format('d.m') ?></span>
                            <span class="day-group-count"><?= count($dayPayments) ?> уроков</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span class="day-group-amount"><?= number_format($dayTotal, 0, '', ' ') ?> ₽</span>
                            <svg class="day-group-toggle" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    <div class="day-group-content">
                        <?php foreach ($dayPayments as $payment):
                            $status = $statusLabels[$payment['status']] ?? $statusLabels['pending'];
                        ?>
                            <div class="payment-card" data-id="<?= $payment['id'] ?>" data-lesson-id="<?= $payment['lesson_instance_id'] ?? '' ?>">
                                <div class="payment-card-main" onclick="openPaymentDetails(<?= $payment['id'] ?>)">
                                    <div>
                                        <div class="payment-teacher"><?= htmlspecialchars($payment['teacher_name']) ?></div>
                                        <div class="payment-date">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <?php if ($payment['time_start']): ?>
                                                <?= substr($payment['time_start'], 0, 5) ?>
                                                — <?= htmlspecialchars($payment['subject'] ?? 'Урок') ?>
                                            <?php else: ?>
                                                Ручная выплата
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
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Данные о выплатах для редактирования
const paymentsData = <?= json_encode($payments, JSON_UNESCAPED_UNICODE) ?>;

// Раскрытие/скрытие дня
function toggleDayGroup(el) {
    el.classList.toggle('expanded');
}

// Обновление статуса выплаты
async function updatePayment(id, status) {
    event.stopPropagation(); // Предотвращаем открытие деталей

    const statusNames = {
        'approved': 'одобрить',
        'paid': 'отметить выплаченной'
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

// Открытие деталей выплаты для редактирования
function openPaymentDetails(id) {
    const payment = paymentsData.find(p => p.id == id);
    if (!payment) return;

    // Если есть связанный урок, перейти на страницу расписания для редактирования
    if (payment.lesson_instance_id) {
        // Для мобильной версии показываем модалку с информацией
        showPaymentModal(payment);
    } else {
        showPaymentModal(payment);
    }
}

function showPaymentModal(payment) {
    const statusLabels = {
        'pending': 'Ожидает',
        'approved': 'Одобрено',
        'paid': 'Выплачено',
        'cancelled': 'Отменено'
    };

    const typeLabels = {
        'lesson': 'За урок',
        'bonus': 'Бонус',
        'penalty': 'Штраф',
        'adjustment': 'Корректировка'
    };

    const html = `
        <div class="modal active" id="paymentModal" onclick="if(event.target === this) closePaymentModal()">
            <div class="modal-content">
                <div class="modal-handle"></div>
                <div class="modal-header">
                    <h3 class="modal-title">Детали выплаты</h3>
                    <button class="modal-close" onclick="closePaymentModal()">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <div style="margin-bottom: 16px;">
                        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Преподаватель</div>
                        <div style="font-size: 16px; font-weight: 600;">${escapeHtml(payment.teacher_name)}</div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Сумма</div>
                        <div style="font-size: 24px; font-weight: 700; color: var(--status-green); font-family: 'JetBrains Mono', monospace;">
                            ${Number(payment.amount).toLocaleString('ru-RU')} ₽
                        </div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Статус</div>
                        <div style="font-size: 16px;">${statusLabels[payment.status] || payment.status}</div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Тип</div>
                        <div style="font-size: 16px;">${typeLabels[payment.payment_type] || payment.payment_type}</div>
                    </div>
                    ${payment.time_start ? `
                        <div style="margin-bottom: 16px;">
                            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Урок</div>
                            <div style="font-size: 16px;">${payment.time_start.substring(0, 5)} — ${escapeHtml(payment.subject || 'Урок')}</div>
                        </div>
                    ` : ''}
                    ${payment.notes ? `
                        <div style="margin-bottom: 16px;">
                            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">Заметки</div>
                            <div style="font-size: 14px; color: var(--text-secondary);">${escapeHtml(payment.notes)}</div>
                        </div>
                    ` : ''}
                </div>
                <div class="modal-footer" style="display: flex; gap: 8px; justify-content: flex-end;">
                    ${payment.status === 'pending' ? `
                        <button class="btn btn-primary" onclick="updatePayment(${payment.id}, 'approved'); closePaymentModal();">
                            Одобрить
                        </button>
                    ` : ''}
                    ${payment.status === 'approved' ? `
                        <button class="btn btn-primary" onclick="updatePayment(${payment.id}, 'paid'); closePaymentModal();">
                            Отметить выплаченной
                        </button>
                    ` : ''}
                    <button class="btn btn-secondary" onclick="closePaymentModal()">Закрыть</button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);
    document.body.style.overflow = 'hidden';
}

function closePaymentModal() {
    const modal = document.getElementById('paymentModal');
    if (modal) {
        modal.remove();
        document.body.style.overflow = '';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
