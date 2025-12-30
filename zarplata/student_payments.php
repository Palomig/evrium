<?php
/**
 * Страница учёта оплат от учеников
 * Автоматический приём платежей через уведомления Sberbank
 * Новый дизайн: STYLEGUIDE.md (Teal accent, Nunito + JetBrains Mono)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

// Автоматический редирект на мобильную версию
require_once __DIR__ . '/mobile/config/mobile_detect.php';
redirectToMobileIfNeeded('student_payments.php');

requireAuth();
$user = getCurrentUser();

// Текущий месяц
$month = $_GET['month'] ?? date('Y-m');
$statusFilter = $_GET['status'] ?? 'all';
$view = $_GET['view'] ?? 'incoming'; // incoming | manual | payers | setup

// Получаем входящие платежи
$whereConditions = ["ip.month = ?"];
$params = [$month];

if ($statusFilter !== 'all') {
    $whereConditions[] = "ip.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $whereConditions);

$incomingPayments = dbQuery(
    "SELECT ip.*,
            sp.name as payer_name,
            sp.relation as payer_relation,
            s.name as student_name,
            s.class as student_class
     FROM incoming_payments ip
     LEFT JOIN student_payers sp ON ip.payer_id = sp.id
     LEFT JOIN students s ON ip.student_id = s.id
     WHERE $whereClause
     ORDER BY ip.received_at DESC",
    $params
);

// Статистика за месяц
$stats = dbQueryOne(
    "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'matched' THEN 1 ELSE 0 END) as matched,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'ignored' THEN 1 ELSE 0 END) as ignored,
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END), 0) as confirmed_amount,
        COALESCE(SUM(CASE WHEN status IN ('pending', 'matched') THEN amount ELSE 0 END), 0) as pending_amount
     FROM incoming_payments
     WHERE month = ?",
    [$month]
);

// Получаем всех учеников
$students = dbQuery("SELECT id, name, class FROM students WHERE active = 1 ORDER BY name", []);

// Получаем всех плательщиков
$payers = dbQuery(
    "SELECT sp.*, s.name as student_name, s.class as student_class
     FROM student_payers sp
     JOIN students s ON sp.student_id = s.id
     WHERE sp.active = 1
     ORDER BY s.name, sp.name",
    []
);

// API Token для webhook
$apiToken = getSetting('automate_api_token', '');
if (empty($apiToken)) {
    $apiToken = bin2hex(random_bytes(16));
    setSetting('automate_api_token', $apiToken);
}

// Webhook URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$webhookUrl = $protocol . '://' . $host . '/zarplata/api/incoming_payments.php?action=webhook&token=' . $apiToken;

// Форматируем месяц
$monthNames = [
    '01' => 'Январь', '02' => 'Февраль', '03' => 'Март',
    '04' => 'Апрель', '05' => 'Май', '06' => 'Июнь',
    '07' => 'Июль', '08' => 'Август', '09' => 'Сентябрь',
    '10' => 'Октябрь', '11' => 'Ноябрь', '12' => 'Декабрь'
];
$monthParts = explode('-', $month);
$monthLabel = $monthNames[$monthParts[1]] ?? $monthParts[1];
$yearLabel = $monthParts[0];

define('PAGE_TITLE', 'Оплаты учеников');
define('PAGE_SUBTITLE', $monthLabel . ' ' . $yearLabel);
define('ACTIVE_PAGE', 'student_payments');

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* Stats Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
}

.stat-card-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-card-value.green { color: var(--status-green); }
.stat-card-value.orange { color: var(--status-orange); }
.stat-card-value.blue { color: var(--accent); }
.stat-card-value.muted { color: var(--text-muted); }

.stat-card-label {
    font-size: 13px;
    color: var(--text-muted);
}

/* Month Navigator */
.month-nav {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.month-nav-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
}

.month-nav-btn:hover {
    background: var(--accent-dim);
    color: var(--accent);
    border-color: var(--accent);
}

.month-nav-label {
    font-size: 20px;
    font-weight: 600;
}

/* Tabs */
.tabs {
    display: flex;
    gap: 4px;
    background: var(--bg-elevated);
    padding: 4px;
    border-radius: 10px;
    margin-bottom: 24px;
    width: fit-content;
}

.tab {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
}

.tab:hover {
    color: var(--text-primary);
}

.tab.active {
    background: var(--accent);
    color: white;
}

/* Filter Pills */
.filter-row {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
}

.filter-pill {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
}

.filter-pill:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.filter-pill.active {
    background: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
}

/* Payment Table */
.payments-table {
    width: 100%;
    border-collapse: collapse;
}

.payments-table th {
    text-align: left;
    padding: 12px 16px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
    background: var(--bg-elevated);
}

.payments-table td {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.payments-table tr:hover {
    background: var(--bg-elevated);
}

.payment-sender {
    font-weight: 600;
    margin-bottom: 2px;
}

.payment-bank {
    font-size: 12px;
    color: var(--text-muted);
}

.payment-amount {
    font-family: 'JetBrains Mono', monospace;
    font-size: 16px;
    font-weight: 600;
    color: var(--status-green);
}

.payment-student {
    display: flex;
    align-items: center;
    gap: 8px;
}

.payment-student-name {
    font-weight: 500;
}

.payment-student-relation {
    font-size: 12px;
    color: var(--text-muted);
}

.payment-time {
    font-size: 13px;
    color: var(--text-secondary);
}

.payment-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
}

.action-btn.match {
    background: var(--accent-dim);
    color: var(--accent);
}

.action-btn.confirm {
    background: var(--status-green-dim);
    color: var(--status-green);
}

.action-btn.ignore {
    background: var(--bg-elevated);
    color: var(--text-muted);
}

.action-btn:hover {
    filter: brightness(1.1);
}

.action-btn svg {
    width: 14px;
    height: 14px;
}

/* Badge */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.badge-pending {
    background: var(--status-orange-dim);
    color: var(--status-orange);
}

.badge-matched {
    background: var(--accent-dim);
    color: var(--accent);
}

.badge-confirmed {
    background: var(--status-green-dim);
    color: var(--status-green);
}

.badge-ignored {
    background: var(--bg-elevated);
    color: var(--text-muted);
}

/* Payers Table */
.payers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}

.payer-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
}

.payer-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.payer-name {
    font-weight: 600;
    font-size: 15px;
}

.payer-relation {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

.payer-student {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    background: var(--bg-elevated);
    border-radius: 8px;
}

.payer-student-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--accent-dim);
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
}

.payer-student-name {
    font-weight: 500;
}

.payer-student-class {
    font-size: 12px;
    color: var(--text-muted);
}

/* Setup Section */
.setup-section {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 20px;
}

.setup-section-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
}

.setup-section-number {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--accent);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
}

.setup-section-title {
    font-weight: 600;
}

.setup-section-body {
    padding: 20px;
}

.setup-text {
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 12px;
}

.token-box {
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    word-break: break-all;
    position: relative;
}

.token-label {
    font-size: 10px;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-bottom: 8px;
    font-family: 'Nunito', sans-serif;
}

.token-value {
    color: var(--accent);
}

.copy-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 6px 12px;
    background: var(--accent);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
}

.copy-btn:hover {
    filter: brightness(1.1);
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-overlay.active {
    display: flex;
}

.modal {
    background: var(--bg-card);
    border-radius: 16px;
    width: 100%;
    max-width: 480px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 18px;
    font-weight: 600;
}

.modal-close {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: var(--bg-elevated);
    color: var(--text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* Form */
.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.form-control {
    width: 100%;
    padding: 12px 14px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    color: var(--text-primary);
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
}

.form-hint {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 6px;
}

/* Buttons */
.btn {
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.15s ease;
}

.btn-primary {
    background: var(--accent);
    color: white;
}

.btn-primary:hover {
    filter: brightness(1.1);
}

.btn-secondary {
    background: var(--bg-elevated);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--accent);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state-icon {
    width: 64px;
    height: 64px;
    color: var(--text-muted);
    margin-bottom: 16px;
}

.empty-state-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
}

.empty-state-text {
    color: var(--text-muted);
}
</style>

<!-- Month Navigation -->
<div class="month-nav">
    <a href="?month=<?= date('Y-m', strtotime($month . '-01 -1 month')) ?>&view=<?= $view ?>&status=<?= $statusFilter ?>" class="month-nav-btn">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div class="month-nav-label"><?= $monthLabel ?> <?= $yearLabel ?></div>
    <a href="?month=<?= date('Y-m', strtotime($month . '-01 +1 month')) ?>&view=<?= $view ?>&status=<?= $statusFilter ?>" class="month-nav-btn">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
</div>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-card-value green"><?= number_format($stats['confirmed_amount'] ?? 0, 0, '', ' ') ?> ₽</div>
        <div class="stat-card-label">Подтверждено</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value orange"><?= number_format($stats['pending_amount'] ?? 0, 0, '', ' ') ?> ₽</div>
        <div class="stat-card-label">Ожидает проверки</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value blue"><?= ($stats['pending'] ?? 0) + ($stats['matched'] ?? 0) ?></div>
        <div class="stat-card-label">Требуют внимания</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value muted"><?= $stats['confirmed'] ?? 0 ?></div>
        <div class="stat-card-label">Обработано</div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs">
    <a href="?month=<?= $month ?>&view=incoming&status=<?= $statusFilter ?>" class="tab <?= $view === 'incoming' ? 'active' : '' ?>">Входящие платежи</a>
    <a href="?month=<?= $month ?>&view=payers" class="tab <?= $view === 'payers' ? 'active' : '' ?>">Плательщики</a>
    <a href="?month=<?= $month ?>&view=setup" class="tab <?= $view === 'setup' ? 'active' : '' ?>">Настройка</a>
</div>

<?php if ($view === 'incoming'): ?>
    <!-- Filter Pills -->
    <div class="filter-row">
        <a href="?month=<?= $month ?>&view=incoming&status=all" class="filter-pill <?= $statusFilter === 'all' ? 'active' : '' ?>">Все</a>
        <a href="?month=<?= $month ?>&view=incoming&status=pending" class="filter-pill <?= $statusFilter === 'pending' ? 'active' : '' ?>">Новые (<?= $stats['pending'] ?? 0 ?>)</a>
        <a href="?month=<?= $month ?>&view=incoming&status=matched" class="filter-pill <?= $statusFilter === 'matched' ? 'active' : '' ?>">Сопоставлены (<?= $stats['matched'] ?? 0 ?>)</a>
        <a href="?month=<?= $month ?>&view=incoming&status=confirmed" class="filter-pill <?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Подтверждены (<?= $stats['confirmed'] ?? 0 ?>)</a>
    </div>

    <?php if (empty($incomingPayments)): ?>
        <div class="card">
            <div class="empty-state">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <div class="empty-state-title">Нет входящих платежей</div>
                <p class="empty-state-text">Платежи появятся здесь автоматически после настройки MacroDroid</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>Отправитель</th>
                        <th>Сумма</th>
                        <th>Ученик</th>
                        <th>Время</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incomingPayments as $payment): ?>
                        <tr data-id="<?= $payment['id'] ?>">
                            <td>
                                <div class="payment-sender"><?= e($payment['sender_name']) ?></div>
                                <div class="payment-bank"><?= e($payment['bank_name'] ?? 'Банк') ?></div>
                            </td>
                            <td>
                                <div class="payment-amount"><?= number_format($payment['amount'], 0, '', ' ') ?> ₽</div>
                            </td>
                            <td>
                                <?php if ($payment['student_name']): ?>
                                    <div class="payment-student">
                                        <div>
                                            <div class="payment-student-name"><?= e($payment['student_name']) ?></div>
                                            <?php if ($payment['payer_relation']): ?>
                                                <div class="payment-student-relation"><?= e($payment['payer_relation']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">Не определён</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="payment-time"><?= date('d.m.Y H:i', strtotime($payment['received_at'])) ?></div>
                            </td>
                            <td>
                                <?php
                                $statusClass = [
                                    'pending' => 'pending',
                                    'matched' => 'matched',
                                    'confirmed' => 'confirmed',
                                    'ignored' => 'ignored'
                                ][$payment['status']] ?? 'pending';
                                $statusText = [
                                    'pending' => 'Новый',
                                    'matched' => 'Сопоставлен',
                                    'confirmed' => 'Подтверждён',
                                    'ignored' => 'Игнорирован'
                                ][$payment['status']] ?? $payment['status'];
                                ?>
                                <span class="badge badge-<?= $statusClass ?>"><?= $statusText ?></span>
                            </td>
                            <td>
                                <div class="payment-actions">
                                    <?php if ($payment['status'] === 'pending'): ?>
                                        <button class="action-btn match" onclick="openMatchModal(<?= $payment['id'] ?>)">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                            </svg>
                                            Связать
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($payment['student_id'] && $payment['status'] !== 'confirmed' && $payment['status'] !== 'ignored'): ?>
                                        <button class="action-btn confirm" onclick="confirmPayment(<?= $payment['id'] ?>)">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Подтвердить
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($payment['status'] !== 'confirmed' && $payment['status'] !== 'ignored'): ?>
                                        <button class="action-btn ignore" onclick="ignorePayment(<?= $payment['id'] ?>)">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php elseif ($view === 'payers'): ?>
    <!-- Payers Section -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">Плательщики (<?= count($payers) ?>)</h3>
        <button class="btn btn-primary" onclick="openAddPayerModal()">
            <span class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 6px;">add</span>
            Добавить
        </button>
    </div>

    <?php if (empty($payers)): ?>
        <div class="card">
            <div class="empty-state">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <div class="empty-state-title">Нет плательщиков</div>
                <p class="empty-state-text">Добавьте плательщиков для автоматического сопоставления платежей</p>
            </div>
        </div>
    <?php else: ?>
        <div class="payers-grid">
            <?php foreach ($payers as $payer): ?>
                <div class="payer-card" data-id="<?= $payer['id'] ?>">
                    <div class="payer-card-header">
                        <div>
                            <div class="payer-name"><?= e($payer['name']) ?></div>
                            <div class="payer-relation"><?= $payer['relation'] ? e($payer['relation']) : 'Не указано' ?></div>
                        </div>
                        <button class="action-btn ignore" onclick="deletePayer(<?= $payer['id'] ?>)" title="Удалить">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                    <div class="payer-student">
                        <div class="payer-student-icon">
                            <span class="material-icons" style="font-size: 18px;">school</span>
                        </div>
                        <div>
                            <div class="payer-student-name"><?= e($payer['student_name']) ?></div>
                            <?php if ($payer['student_class']): ?>
                                <div class="payer-student-class"><?= $payer['student_class'] ?> класс</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($view === 'setup'): ?>
    <!-- Setup Section -->
    <div class="setup-section">
        <div class="setup-section-header">
            <div class="setup-section-number">1</div>
            <div class="setup-section-title">Установите MacroDroid</div>
        </div>
        <div class="setup-section-body">
            <p class="setup-text">Скачайте и установите приложение MacroDroid из Google Play. Это бесплатное приложение для автоматизации Android.</p>
            <a href="https://play.google.com/store/apps/details?id=com.arlosoft.macrodroid" target="_blank" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">
                <span class="material-icons">android</span>
                Открыть Google Play
            </a>
        </div>
    </div>

    <div class="setup-section">
        <div class="setup-section-header">
            <div class="setup-section-number">2</div>
            <div class="setup-section-title">Разрешите доступ к уведомлениям</div>
        </div>
        <div class="setup-section-body">
            <p class="setup-text">Перейдите в настройки Android и разрешите MacroDroid читать уведомления:</p>
            <p class="setup-text"><strong>Настройки → Приложения → Специальный доступ → Доступ к уведомлениям → MacroDroid ✓</strong></p>
        </div>
    </div>

    <div class="setup-section">
        <div class="setup-section-header">
            <div class="setup-section-number">3</div>
            <div class="setup-section-title">Создайте макрос</div>
        </div>
        <div class="setup-section-body">
            <p class="setup-text"><strong>Триггер:</strong> Устройство → Уведомление получено → Приложение: Сбербанк</p>
            <p class="setup-text"><strong>Действие:</strong> Приложения → HTTP запрос → POST</p>

            <div class="token-box" style="margin: 16px 0;">
                <div class="token-label">URL для MacroDroid</div>
                <div class="token-value" id="webhookUrl"><?= e($webhookUrl) ?></div>
                <button class="copy-btn" onclick="copyToClipboard('webhookUrl')">Копировать</button>
            </div>

            <p class="setup-text"><strong>Тело запроса:</strong></p>
            <div class="token-box">
                <div class="token-value">{"notification": "[not_title] [not_text]"}</div>
                <button class="copy-btn" onclick="copyText('{\"notification\": \"[not_title] [not_text]\"}')">Копировать</button>
            </div>

            <p class="setup-text" style="margin-top: 16px;"><strong>Content-Type:</strong> application/json</p>
        </div>
    </div>

    <div class="setup-section">
        <div class="setup-section-header">
            <div class="setup-section-number">4</div>
            <div class="setup-section-title">Добавьте плательщиков</div>
        </div>
        <div class="setup-section-body">
            <p class="setup-text">Для автоматического сопоставления платежей добавьте плательщиков на вкладке "Плательщики". Имя должно совпадать с именем отправителя в уведомлении Сбербанка (например: ИВАН ИВАНОВИЧ).</p>
            <a href="?month=<?= $month ?>&view=payers" class="btn btn-primary">Перейти к плательщикам</a>
        </div>
    </div>

    <div class="setup-section">
        <div class="setup-section-header">
            <div class="setup-section-number" style="background: var(--status-orange);">
                <span class="material-icons" style="font-size: 16px;">security</span>
            </div>
            <div class="setup-section-title">Безопасность</div>
        </div>
        <div class="setup-section-body">
            <p class="setup-text">API токен защищает ваш webhook. При необходимости сгенерируйте новый токен (потребуется обновить URL в MacroDroid).</p>

            <div class="token-box" style="margin: 16px 0;">
                <div class="token-label">Текущий токен</div>
                <div class="token-value"><?= e($apiToken) ?></div>
            </div>

            <button class="btn btn-secondary" onclick="regenerateToken()">Сгенерировать новый токен</button>
        </div>
    </div>
<?php endif; ?>

<!-- Match Modal -->
<div class="modal-overlay" id="matchModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Связать с учеником</h3>
            <button class="modal-close" onclick="closeMatchModal()">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="matchPaymentId">

            <div style="background: var(--bg-elevated); border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Отправитель</div>
                <div id="matchSenderName" style="font-size: 16px; font-weight: 600;"></div>
                <div id="matchAmount" style="font-size: 20px; font-weight: 600; color: var(--status-green); margin-top: 4px;"></div>
            </div>

            <div class="form-group">
                <label class="form-label">Ученик</label>
                <select id="matchStudentId" class="form-control" onchange="loadStudentPayers()">
                    <option value="">Выберите ученика</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> <?= $s['class'] ? '(' . $s['class'] . ' кл.)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="payerSelectGroup" style="display: none;">
                <label class="form-label">Плательщик</label>
                <select id="matchPayerId" class="form-control">
                    <option value="">Создать нового плательщика</option>
                </select>
            </div>

            <div id="newPayerFields" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Имя плательщика</label>
                    <input type="text" id="newPayerName" class="form-control" readonly>
                    <div class="form-hint">Будет использоваться имя отправителя</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Кто это?</label>
                    <select id="newPayerRelation" class="form-control">
                        <option value="">Не указано</option>
                        <option value="мама">Мама</option>
                        <option value="папа">Папа</option>
                        <option value="бабушка">Бабушка</option>
                        <option value="дедушка">Дедушка</option>
                        <option value="сам ученик">Сам ученик</option>
                        <option value="другое">Другое</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeMatchModal()">Отмена</button>
            <button class="btn btn-primary" onclick="saveMatch()">Связать</button>
        </div>
    </div>
</div>

<!-- Add Payer Modal -->
<div class="modal-overlay" id="addPayerModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Новый плательщик</h3>
            <button class="modal-close" onclick="closeAddPayerModal()">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Ученик</label>
                <select id="addPayerStudentId" class="form-control" required>
                    <option value="">Выберите ученика</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Имя плательщика (как в СМС)</label>
                <input type="text" id="addPayerName" class="form-control" required placeholder="ИВАН ИВАНОВИЧ">
                <div class="form-hint">Введите имя точно как оно приходит в уведомлениях Сбербанка</div>
            </div>
            <div class="form-group">
                <label class="form-label">Кто это?</label>
                <select id="addPayerRelation" class="form-control">
                    <option value="">Не указано</option>
                    <option value="мама">Мама</option>
                    <option value="папа">Папа</option>
                    <option value="бабушка">Бабушка</option>
                    <option value="дедушка">Дедушка</option>
                    <option value="сам ученик">Сам ученик</option>
                    <option value="другое">Другое</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeAddPayerModal()">Отмена</button>
            <button class="btn btn-primary" onclick="savePayer()">Сохранить</button>
        </div>
    </div>
</div>

<script>
const currentMonth = '<?= $month ?>';
const payments = <?= json_encode($incomingPayments, JSON_UNESCAPED_UNICODE) ?>;

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 14px 20px;
        background: ${type === 'success' ? 'var(--status-green)' : type === 'error' ? 'var(--status-rose)' : 'var(--accent)'};
        color: white;
        border-radius: 8px;
        font-weight: 500;
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Copy to clipboard
function copyToClipboard(elementId) {
    const text = document.getElementById(elementId).textContent;
    navigator.clipboard.writeText(text).then(() => showToast('Скопировано', 'success'));
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => showToast('Скопировано', 'success'));
}

// Match Modal
function openMatchModal(paymentId) {
    const payment = payments.find(p => p.id == paymentId);
    if (!payment) return;

    document.getElementById('matchPaymentId').value = paymentId;
    document.getElementById('matchSenderName').textContent = payment.sender_name;
    document.getElementById('matchAmount').textContent = Number(payment.amount).toLocaleString('ru-RU') + ' ₽';
    document.getElementById('newPayerName').value = payment.sender_name;

    document.getElementById('matchStudentId').value = payment.student_id || '';
    document.getElementById('matchPayerId').value = '';
    document.getElementById('payerSelectGroup').style.display = 'none';
    document.getElementById('newPayerFields').style.display = 'none';

    if (payment.student_id) loadStudentPayers();

    document.getElementById('matchModal').classList.add('active');
}

function closeMatchModal() {
    document.getElementById('matchModal').classList.remove('active');
}

async function loadStudentPayers() {
    const studentId = document.getElementById('matchStudentId').value;
    const payerSelect = document.getElementById('matchPayerId');
    const payerGroup = document.getElementById('payerSelectGroup');

    if (!studentId) {
        payerGroup.style.display = 'none';
        return;
    }

    try {
        const res = await fetch(`api/incoming_payments.php?action=payers&student_id=${studentId}`);
        const result = await res.json();

        if (result.success) {
            payerSelect.innerHTML = '<option value="">Создать нового плательщика</option>';
            result.data.forEach(payer => {
                payerSelect.innerHTML += `<option value="${payer.id}">${payer.name} ${payer.relation ? '(' + payer.relation + ')' : ''}</option>`;
            });
            payerGroup.style.display = 'block';
            document.getElementById('newPayerFields').style.display = 'block';
        }
    } catch (e) {
        console.error('Failed to load payers:', e);
    }

    payerSelect.onchange = function() {
        document.getElementById('newPayerFields').style.display = this.value ? 'none' : 'block';
    };
}

async function saveMatch() {
    const paymentId = document.getElementById('matchPaymentId').value;
    const studentId = document.getElementById('matchStudentId').value;
    const payerId = document.getElementById('matchPayerId').value;

    if (!studentId) {
        showToast('Выберите ученика', 'error');
        return;
    }

    const data = { payment_id: paymentId, student_id: studentId };

    if (payerId) {
        data.payer_id = payerId;
    } else {
        data.create_payer = true;
        data.payer_name = document.getElementById('newPayerName').value;
        data.payer_relation = document.getElementById('newPayerRelation').value;
    }

    try {
        const res = await fetch('api/incoming_payments.php?action=match', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();

        if (result.success) {
            showToast('Платеж связан', 'success');
            closeMatchModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        showToast('Ошибка сети', 'error');
    }
}

async function confirmPayment(paymentId) {
    if (!confirm('Подтвердить платеж и зачесть в оплату?')) return;

    try {
        const res = await fetch('api/incoming_payments.php?action=confirm', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ payment_id: paymentId, month: currentMonth })
        });
        const result = await res.json();

        if (result.success) {
            showToast('Платеж подтвержден', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        showToast('Ошибка сети', 'error');
    }
}

async function ignorePayment(paymentId) {
    if (!confirm('Игнорировать этот платеж?')) return;

    try {
        const res = await fetch('api/incoming_payments.php?action=ignore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ payment_id: paymentId })
        });
        const result = await res.json();

        if (result.success) {
            showToast('Платеж проигнорирован', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        showToast('Ошибка сети', 'error');
    }
}

// Add Payer Modal
function openAddPayerModal() {
    document.getElementById('addPayerStudentId').value = '';
    document.getElementById('addPayerName').value = '';
    document.getElementById('addPayerRelation').value = '';
    document.getElementById('addPayerModal').classList.add('active');
}

function closeAddPayerModal() {
    document.getElementById('addPayerModal').classList.remove('active');
}

async function savePayer() {
    const studentId = document.getElementById('addPayerStudentId').value;
    const name = document.getElementById('addPayerName').value.trim();
    const relation = document.getElementById('addPayerRelation').value;

    if (!studentId) {
        showToast('Выберите ученика', 'error');
        return;
    }

    if (!name) {
        showToast('Введите имя плательщика', 'error');
        return;
    }

    try {
        const res = await fetch('api/incoming_payments.php?action=add_payer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ student_id: studentId, name, relation })
        });
        const result = await res.json();

        if (result.success) {
            showToast('Плательщик добавлен', 'success');
            closeAddPayerModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        showToast('Ошибка сети', 'error');
    }
}

async function deletePayer(payerId) {
    if (!confirm('Удалить плательщика?')) return;

    try {
        const res = await fetch('api/incoming_payments.php?action=delete_payer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: payerId })
        });
        const result = await res.json();

        if (result.success) {
            showToast('Плательщик удален', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        showToast('Ошибка сети', 'error');
    }
}

async function regenerateToken() {
    if (!confirm('Сгенерировать новый токен? Потребуется обновить URL в MacroDroid.')) return;

    try {
        const res = await fetch('api/incoming_payments.php?action=regenerate_token', { method: 'POST' });
        const result = await res.json();

        if (result.success) {
            showToast('Токен обновлен', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        showToast('Ошибка сети', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
