<?php
/**
 * Mobile Student Payments Page
 * Управление входящими платежами от учеников
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Текущий месяц
$month = $_GET['month'] ?? date('Y-m');
$statusFilter = $_GET['status'] ?? 'all';

// Получаем входящие платежи
$where = ["ip.month = ?"];
$params = [$month];

if ($statusFilter !== 'all') {
    $where[] = "ip.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

$payments = dbQuery(
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

// Статистика
$stats = dbQueryOne(
    "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'matched' THEN 1 ELSE 0 END) as matched,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END), 0) as confirmed_amount,
        COALESCE(SUM(CASE WHEN status IN ('pending', 'matched') THEN amount ELSE 0 END), 0) as pending_amount
     FROM incoming_payments
     WHERE month = ?",
    [$month]
);

// Получаем всех учеников для выбора
$students = dbQuery("SELECT id, name, class FROM students WHERE active = 1 ORDER BY name", []);

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
define('ACTIVE_PAGE', 'student_payments');
define('SHOW_BOTTOM_NAV', false);

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* Month Navigator */
.month-navigator {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: var(--bg-card);
    border-bottom: 1px solid var(--border);
}

.month-nav-btn {
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
}

.month-nav-btn:active {
    background: var(--accent-dim);
    color: var(--accent);
}

.month-nav-center {
    text-align: center;
}

.month-nav-label {
    font-size: 18px;
    font-weight: 600;
}

.month-nav-year {
    font-size: 12px;
    color: var(--text-muted);
}

/* Stats */
.payment-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    padding: 16px;
}

.payment-stat {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 14px;
    border: 1px solid var(--border);
}

.payment-stat-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 20px;
    font-weight: 600;
}

.payment-stat-value.green { color: var(--status-green); }
.payment-stat-value.orange { color: var(--status-orange); }

.payment-stat-label {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Payment Card */
.incoming-payment-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 10px;
    overflow: hidden;
}

.incoming-payment-main {
    padding: 14px;
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.incoming-payment-left {
    flex: 1;
    min-width: 0;
}

.incoming-payment-sender {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.incoming-payment-meta {
    font-size: 12px;
    color: var(--text-muted);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.incoming-payment-matched {
    margin-top: 8px;
    padding: 8px 10px;
    background: var(--accent-dim);
    border-radius: 8px;
    font-size: 13px;
}

.incoming-payment-matched-label {
    color: var(--text-muted);
    font-size: 11px;
}

.incoming-payment-matched-name {
    font-weight: 600;
    color: var(--accent);
}

.incoming-payment-right {
    text-align: right;
    flex-shrink: 0;
}

.incoming-payment-amount {
    font-family: 'JetBrains Mono', monospace;
    font-size: 18px;
    font-weight: 600;
    color: var(--status-green);
}

.incoming-payment-status {
    margin-top: 4px;
}

.incoming-payment-footer {
    padding: 10px 14px;
    background: var(--bg-elevated);
    border-top: 1px solid var(--border);
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.payment-action-btn {
    padding: 8px 14px;
    border-radius: 8px;
    border: none;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}

.payment-action-btn.match {
    background: var(--accent-dim);
    color: var(--accent);
}

.payment-action-btn.confirm {
    background: var(--status-green-dim);
    color: var(--status-green);
}

.payment-action-btn.ignore {
    background: var(--bg-card);
    color: var(--text-muted);
}

.payment-action-btn svg {
    width: 16px;
    height: 16px;
}

/* Badges */
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

/* Setup Card */
.setup-card {
    background: linear-gradient(135deg, var(--accent-dim), var(--bg-card));
    border: 1px solid var(--accent);
    border-radius: 14px;
    padding: 16px;
    margin: 16px;
}

.setup-card-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.setup-card-title svg {
    width: 20px;
    height: 20px;
    color: var(--accent);
}

.setup-card-text {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 12px;
}

.setup-card-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    background: var(--accent);
    color: white;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
}
</style>

<!-- Month Navigator -->
<div class="month-navigator">
    <a href="?month=<?= date('Y-m', strtotime($month . '-01 -1 month')) ?>&status=<?= $statusFilter ?>" class="month-nav-btn">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>

    <div class="month-nav-center">
        <div class="month-nav-label"><?= $monthLabel ?></div>
        <div class="month-nav-year"><?= $yearLabel ?></div>
    </div>

    <a href="?month=<?= date('Y-m', strtotime($month . '-01 +1 month')) ?>&status=<?= $statusFilter ?>" class="month-nav-btn">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
</div>

<!-- Stats -->
<div class="payment-stats">
    <div class="payment-stat">
        <div class="payment-stat-value green"><?= number_format($stats['confirmed_amount'] ?? 0, 0, '', ' ') ?> ₽</div>
        <div class="payment-stat-label">Подтверждено</div>
    </div>
    <div class="payment-stat">
        <div class="payment-stat-value orange"><?= number_format($stats['pending_amount'] ?? 0, 0, '', ' ') ?> ₽</div>
        <div class="payment-stat-label">Ожидает проверки</div>
    </div>
</div>

<!-- Filters -->
<div class="filter-pills" style="padding: 0 16px;">
    <a href="?month=<?= $month ?>&status=all" class="filter-pill <?= $statusFilter === 'all' ? 'active' : '' ?>">Все</a>
    <a href="?month=<?= $month ?>&status=pending" class="filter-pill <?= $statusFilter === 'pending' ? 'active' : '' ?>">Новые</a>
    <a href="?month=<?= $month ?>&status=matched" class="filter-pill <?= $statusFilter === 'matched' ? 'active' : '' ?>">Сопоставлены</a>
    <a href="?month=<?= $month ?>&status=confirmed" class="filter-pill <?= $statusFilter === 'confirmed' ? 'active' : '' ?>">Подтверждены</a>
</div>

<div class="page-container">
    <?php if (empty($payments)): ?>
        <?php if ($stats['total'] == 0): ?>
            <!-- Setup Card for first-time users -->
            <div class="setup-card">
                <div class="setup-card-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Автоматический учет оплат
                </div>
                <div class="setup-card-text">
                    Настройте автоматический прием уведомлений о переводах от Сбербанка через приложение Automate.
                </div>
                <a href="automate_setup.php" class="setup-card-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Настроить
                </a>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="empty-state-title">Нет платежей</div>
                <p class="empty-state-text">По выбранному фильтру ничего не найдено</p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?php foreach ($payments as $payment):
            $statusClass = [
                'pending' => 'pending',
                'matched' => 'matched',
                'confirmed' => 'confirmed',
                'ignored' => 'ignored'
            ][$payment['status']] ?? 'pending';

            $statusText = [
                'pending' => 'Новый',
                'matched' => 'Сопоставлен',
                'confirmed' => 'Подтвержден',
                'ignored' => 'Игнорирован'
            ][$payment['status']] ?? $payment['status'];
        ?>
            <div class="incoming-payment-card" data-id="<?= $payment['id'] ?>">
                <div class="incoming-payment-main">
                    <div class="incoming-payment-left">
                        <div class="incoming-payment-sender"><?= htmlspecialchars($payment['sender_name']) ?></div>
                        <div class="incoming-payment-meta">
                            <span><?= $payment['bank_name'] ?? 'Банк' ?></span>
                            <span><?= date('d.m H:i', strtotime($payment['received_at'])) ?></span>
                        </div>

                        <?php if ($payment['student_name']): ?>
                            <div class="incoming-payment-matched">
                                <div class="incoming-payment-matched-label">Ученик</div>
                                <div class="incoming-payment-matched-name">
                                    <?= htmlspecialchars($payment['student_name']) ?>
                                    <?php if ($payment['payer_relation']): ?>
                                        <span style="font-weight: normal; color: var(--text-muted);">(<?= htmlspecialchars($payment['payer_relation']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="incoming-payment-right">
                        <div class="incoming-payment-amount"><?= number_format($payment['amount'], 0, '', ' ') ?> ₽</div>
                        <div class="incoming-payment-status">
                            <span class="badge badge-<?= $statusClass ?>"><?= $statusText ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($payment['status'] !== 'confirmed' && $payment['status'] !== 'ignored'): ?>
                    <div class="incoming-payment-footer">
                        <?php if ($payment['status'] === 'pending'): ?>
                            <button class="payment-action-btn match" onclick="openMatchModal(<?= $payment['id'] ?>)">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                </svg>
                                Связать
                            </button>
                        <?php endif; ?>

                        <?php if ($payment['student_id']): ?>
                            <button class="payment-action-btn confirm" onclick="confirmPayment(<?= $payment['id'] ?>)">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Подтвердить
                            </button>
                        <?php endif; ?>

                        <button class="payment-action-btn ignore" onclick="ignorePayment(<?= $payment['id'] ?>)">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- FAB: Payers Management -->
<button class="fab" onclick="openPayersModal()" title="Плательщики">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
    </svg>
</button>

<!-- Match Modal -->
<div class="modal modal-fullscreen" id="matchModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Связать с учеником</h3>
            <button class="modal-close" onclick="closeMatchModal()">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="matchPaymentId">

            <div class="card mb-2">
                <div class="card-body">
                    <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Отправитель</div>
                    <div id="matchSenderName" style="font-size: 16px; font-weight: 600;"></div>
                    <div id="matchAmount" style="font-size: 20px; font-weight: 600; color: var(--status-green); margin-top: 4px;"></div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Ученик</label>
                <select id="matchStudentId" class="form-control" onchange="loadStudentPayers()">
                    <option value="">Выберите ученика</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> <?= $s['class'] ? '(' . $s['class'] . ' кл.)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="payerSelectGroup" style="display: none;">
                <label class="form-label">Плательщик</label>
                <select id="matchPayerId" class="form-control">
                    <option value="">Выберите или создайте нового</option>
                </select>
            </div>

            <div id="newPayerFields" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Имя плательщика</label>
                    <input type="text" id="newPayerName" class="form-control" readonly>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Будет использоваться имя отправителя</div>
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

<!-- Payers Modal -->
<div class="modal modal-fullscreen" id="payersModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Плательщики</h3>
            <button class="modal-close" onclick="closePayersModal()">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="payersModalBody">
            <!-- Будет заполнено JS -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closePayersModal()">Закрыть</button>
            <button class="btn btn-primary" onclick="openAddPayerModal()">Добавить</button>
        </div>
    </div>
</div>

<!-- Add Payer Modal -->
<div class="modal" id="addPayerModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Новый плательщик</h3>
            <button class="modal-close" onclick="closeAddPayerModal()">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Имя плательщика (как в СМС)</label>
                <input type="text" id="addPayerName" class="form-control" required placeholder="ИВАН ИВАНОВИЧ">
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Введите имя точно как оно приходит в уведомлениях Сбербанка</div>
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
const payments = <?= json_encode($payments, JSON_UNESCAPED_UNICODE) ?>;
let currentPaymentId = null;

// Match Modal
function openMatchModal(paymentId) {
    const payment = payments.find(p => p.id == paymentId);
    if (!payment) return;

    currentPaymentId = paymentId;
    document.getElementById('matchPaymentId').value = paymentId;
    document.getElementById('matchSenderName').textContent = payment.sender_name;
    document.getElementById('matchAmount').textContent = Number(payment.amount).toLocaleString('ru-RU') + ' ₽';
    document.getElementById('newPayerName').value = payment.sender_name;

    // Reset form
    document.getElementById('matchStudentId').value = payment.student_id || '';
    document.getElementById('matchPayerId').value = '';
    document.getElementById('payerSelectGroup').style.display = 'none';
    document.getElementById('newPayerFields').style.display = 'none';

    if (payment.student_id) {
        loadStudentPayers();
    }

    document.getElementById('matchModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMatchModal() {
    document.getElementById('matchModal').classList.remove('active');
    document.body.style.overflow = '';
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
        const res = await fetch(`../api/incoming_payments.php?action=payers&student_id=${studentId}`);
        const result = await res.json();

        if (result.success) {
            payerSelect.innerHTML = '<option value="">Создать нового плательщика</option>';
            result.data.forEach(payer => {
                payerSelect.innerHTML += `<option value="${payer.id}">${payer.name} ${payer.relation ? '(' + payer.relation + ')' : ''}</option>`;
            });
            payerGroup.style.display = 'block';

            // Show new payer fields by default
            document.getElementById('newPayerFields').style.display = 'block';
        }
    } catch (e) {
        console.error('Failed to load payers:', e);
    }

    // Update new payer fields visibility based on selection
    payerSelect.onchange = function() {
        document.getElementById('newPayerFields').style.display = this.value ? 'none' : 'block';
    };
}

async function saveMatch() {
    const paymentId = document.getElementById('matchPaymentId').value;
    const studentId = document.getElementById('matchStudentId').value;
    const payerId = document.getElementById('matchPayerId').value;

    if (!studentId) {
        MobileApp.showToast('Выберите ученика', 'error');
        return;
    }

    const data = {
        payment_id: paymentId,
        student_id: studentId
    };

    if (payerId) {
        data.payer_id = payerId;
    } else {
        // Create new payer
        data.create_payer = true;
        data.payer_name = document.getElementById('newPayerName').value;
        data.payer_relation = document.getElementById('newPayerRelation').value;
    }

    try {
        MobileApp.showLoading();
        const res = await fetch('../api/incoming_payments.php?action=match', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();

        if (result.success) {
            MobileApp.showToast('Платеж связан', 'success');
            closeMatchModal();
            setTimeout(() => location.reload(), 500);
        } else {
            MobileApp.showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        MobileApp.showToast('Ошибка сети', 'error');
    } finally {
        MobileApp.hideLoading();
    }
}

async function confirmPayment(paymentId) {
    if (!confirm('Подтвердить платеж и зачесть в оплату?')) return;

    try {
        MobileApp.showLoading();
        const res = await fetch('../api/incoming_payments.php?action=confirm', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ payment_id: paymentId, month: currentMonth })
        });
        const result = await res.json();

        if (result.success) {
            MobileApp.showToast('Платеж подтвержден', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            MobileApp.showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        MobileApp.showToast('Ошибка сети', 'error');
    } finally {
        MobileApp.hideLoading();
    }
}

async function ignorePayment(paymentId) {
    if (!confirm('Игнорировать этот платеж?')) return;

    try {
        MobileApp.showLoading();
        const res = await fetch('../api/incoming_payments.php?action=ignore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ payment_id: paymentId })
        });
        const result = await res.json();

        if (result.success) {
            MobileApp.showToast('Платеж проигнорирован', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            MobileApp.showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        MobileApp.showToast('Ошибка сети', 'error');
    } finally {
        MobileApp.hideLoading();
    }
}

// Payers Modal
async function openPayersModal() {
    const modal = document.getElementById('payersModal');
    const body = document.getElementById('payersModalBody');

    body.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-muted);">Загрузка...</div>';
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    try {
        const res = await fetch('../api/incoming_payments.php?action=payers');
        const result = await res.json();

        if (result.success && result.data.length > 0) {
            let html = '';
            let currentStudent = '';

            result.data.forEach(payer => {
                if (payer.student_name !== currentStudent) {
                    if (currentStudent) html += '</div>';
                    currentStudent = payer.student_name;
                    html += `<div class="card mb-2"><div class="card-header"><span class="card-title">${escapeHtml(currentStudent)}</span></div><div class="card-body" style="padding: 0;">`;
                }

                html += `
                    <div class="list-item">
                        <div class="list-item-content">
                            <div class="list-item-title">${escapeHtml(payer.name)}</div>
                            <div class="list-item-subtitle">${payer.relation || 'Не указано'}</div>
                        </div>
                        <button class="payment-action-btn ignore" onclick="deletePayer(${payer.id})" title="Удалить">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                `;
            });

            html += '</div></div>';
            body.innerHTML = html;
        } else {
            body.innerHTML = `
                <div class="empty-state" style="padding: 40px 0;">
                    <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <div class="empty-state-title">Нет плательщиков</div>
                    <p class="empty-state-text">Добавьте первого плательщика</p>
                </div>
            `;
        }
    } catch (e) {
        body.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--status-rose);">Ошибка загрузки</div>';
    }
}

function closePayersModal() {
    document.getElementById('payersModal').classList.remove('active');
    document.body.style.overflow = '';
}

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
        MobileApp.showToast('Выберите ученика', 'error');
        return;
    }

    if (!name) {
        MobileApp.showToast('Введите имя плательщика', 'error');
        return;
    }

    try {
        MobileApp.showLoading();
        const res = await fetch('../api/incoming_payments.php?action=add_payer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ student_id: studentId, name, relation })
        });
        const result = await res.json();

        if (result.success) {
            MobileApp.showToast('Плательщик добавлен', 'success');
            closeAddPayerModal();
            openPayersModal(); // Refresh list
        } else {
            MobileApp.showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        MobileApp.showToast('Ошибка сети', 'error');
    } finally {
        MobileApp.hideLoading();
    }
}

async function deletePayer(payerId) {
    if (!confirm('Удалить плательщика?')) return;

    try {
        MobileApp.showLoading();
        const res = await fetch('../api/incoming_payments.php?action=delete_payer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: payerId })
        });
        const result = await res.json();

        if (result.success) {
            MobileApp.showToast('Плательщик удален', 'success');
            openPayersModal(); // Refresh list
        } else {
            MobileApp.showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        MobileApp.showToast('Ошибка сети', 'error');
    } finally {
        MobileApp.hideLoading();
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
