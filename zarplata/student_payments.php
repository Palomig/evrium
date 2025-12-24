<?php
/**
 * Страница оплаты от учеников
 * Учёт платежей за занятия от родителей
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

// Автоматический редирект на мобильную версию
require_once __DIR__ . '/mobile/config/mobile_detect.php';
redirectToMobileIfNeeded('student_payments.php');

requireAuth();
$user = getCurrentUser();

// Текущий месяц по умолчанию
$currentMonth = $_GET['month'] ?? date('Y-m');

// Генерация списка месяцев (6 месяцев назад + 2 вперёд)
$months = [];
$monthNames = [
    '01' => 'Январь', '02' => 'Февраль', '03' => 'Март',
    '04' => 'Апрель', '05' => 'Май', '06' => 'Июнь',
    '07' => 'Июль', '08' => 'Август', '09' => 'Сентябрь',
    '10' => 'Октябрь', '11' => 'Ноябрь', '12' => 'Декабрь'
];

for ($i = -6; $i <= 2; $i++) {
    $date = new DateTime();
    $date->modify("$i months");
    $monthKey = $date->format('Y-m');
    $monthNum = $date->format('m');
    $year = $date->format('Y');
    $months[$monthKey] = $monthNames[$monthNum] . ' ' . $year;
}

define('PAGE_TITLE', 'Оплата от учеников');
define('PAGE_SUBTITLE', 'Учёт платежей за занятия');
define('ACTIVE_PAGE', 'student_payments');

require_once __DIR__ . '/templates/header.php';
?>

<!-- Статистика -->
<div class="stats-row" id="stats-row">
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(20, 184, 166, 0.15); color: #14b8a6;">
            <span class="material-icons">people</span>
        </div>
        <div class="stat-value" id="stat-total">—</div>
        <div class="stat-label">ВСЕГО УЧЕНИКОВ</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.15); color: #22c55e;">
            <span class="material-icons">check_circle</span>
        </div>
        <div class="stat-value" id="stat-paid">—</div>
        <div class="stat-label">ОПЛАТИЛИ</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
            <span class="material-icons">pending</span>
        </div>
        <div class="stat-value" id="stat-unpaid">—</div>
        <div class="stat-label">НЕ ОПЛАТИЛИ</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(168, 85, 247, 0.15); color: #a855f7;">
            <span class="material-icons">account_balance_wallet</span>
        </div>
        <div class="stat-value" id="stat-amount">—</div>
        <div class="stat-label">ПОЛУЧЕНО</div>
    </div>
</div>

<!-- Выбор месяца -->
<div class="month-selector">
    <button class="month-nav-btn" onclick="changeMonth(-1)" title="Предыдущий месяц">
        <span class="material-icons">chevron_left</span>
    </button>

    <select id="month-select" class="month-select" onchange="loadData()">
        <?php foreach ($months as $key => $name): ?>
            <option value="<?= $key ?>" <?= $key === $currentMonth ? 'selected' : '' ?>><?= $name ?></option>
        <?php endforeach; ?>
    </select>

    <button class="month-nav-btn" onclick="changeMonth(1)" title="Следующий месяц">
        <span class="material-icons">chevron_right</span>
    </button>
</div>

<!-- Фильтры -->
<div class="filters-panel">
    <div class="filters-content">
        <div class="filter-group">
            <span class="filter-label">Статус:</span>
            <button class="filter-btn active" data-filter="all" onclick="setFilter('all')">Все</button>
            <button class="filter-btn" data-filter="unpaid" onclick="setFilter('unpaid')">Не оплачено</button>
            <button class="filter-btn" data-filter="paid" onclick="setFilter('paid')">Оплачено</button>
        </div>
        <div class="filter-group">
            <span class="filter-label">Тип:</span>
            <button class="filter-btn active" data-type="all" onclick="setTypeFilter('all')">Все</button>
            <button class="filter-btn" data-type="group" onclick="setTypeFilter('group')">Группа</button>
            <button class="filter-btn" data-type="individual" onclick="setTypeFilter('individual')">Индивидуальные</button>
        </div>
        <div class="filter-group search-group">
            <span class="material-icons">search</span>
            <input type="text" id="search-input" placeholder="Поиск по имени..." oninput="filterTable()">
        </div>
    </div>
</div>

<!-- Таблица учеников -->
<div class="table-container">
    <table id="students-table">
        <thead>
            <tr>
                <th>Ученик</th>
                <th>Класс</th>
                <th>Тип</th>
                <th>Тариф</th>
                <th>Уроков</th>
                <th>К оплате</th>
                <th>Статус</th>
                <th>Контакты</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody id="students-tbody">
            <tr>
                <td colspan="9" style="text-align: center; padding: 40px;">
                    <span class="material-icons" style="font-size: 48px; color: var(--text-muted);">hourglass_empty</span>
                    <p style="margin-top: 16px; color: var(--text-muted);">Загрузка данных...</p>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Итоговая строка -->
<div class="summary-row" id="summary-row">
    <div class="summary-item">
        <span class="summary-label">Ожидается:</span>
        <span class="summary-value" id="summary-expected">0 ₽</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Оплачено:</span>
        <span class="summary-value success" id="summary-paid">0 ₽</span>
    </div>
    <div class="summary-item">
        <span class="summary-label">Остаток:</span>
        <span class="summary-value warning" id="summary-remaining">0 ₽</span>
    </div>
</div>

<!-- Модальное окно оплаты -->
<div id="payment-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Отметить оплату</h2>
            <button class="modal-close" onclick="closePaymentModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <form id="payment-form" onsubmit="savePayment(event)">
            <input type="hidden" id="payment-student-id" name="student_id">
            <input type="hidden" id="payment-month" name="month">

            <div class="modal-body">
                <div class="student-info-card" id="modal-student-info">
                    <!-- Заполняется динамически -->
                </div>

                <div class="form-group">
                    <label class="form-label">Сумма оплаты *</label>
                    <input type="number" class="form-control" id="payment-amount" name="amount" required min="1" step="100">
                </div>

                <div class="form-group">
                    <label class="form-label">Способ оплаты</label>
                    <div class="payment-method-group">
                        <button type="button" class="method-btn active" data-method="card" onclick="selectMethod('card')">
                            <span class="material-icons">credit_card</span>
                            Безнал
                        </button>
                        <button type="button" class="method-btn" data-method="cash" onclick="selectMethod('cash')">
                            <span class="material-icons">payments</span>
                            Наличные
                        </button>
                    </div>
                    <input type="hidden" id="payment-method" name="payment_method" value="card">
                </div>

                <div class="form-group" id="lessons-count-group" style="display: none;">
                    <label class="form-label">Количество уроков</label>
                    <input type="number" class="form-control" id="payment-lessons" name="lessons_count" min="0">
                </div>

                <div class="form-group">
                    <label class="form-label">Примечание</label>
                    <textarea class="form-control" id="payment-notes" name="notes" rows="2" placeholder="Дополнительная информация..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closePaymentModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons">check</span>
                    Сохранить
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно напоминания -->
<div id="reminder-modal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Напомнить об оплате</h2>
            <button class="modal-close" onclick="closeReminderModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body">
            <div class="reminder-preview" id="reminder-preview">
                <!-- Текст напоминания -->
            </div>

            <div class="reminder-actions">
                <a href="#" id="reminder-whatsapp-btn" class="messenger-action-btn whatsapp" target="_blank">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    Отправить в WhatsApp
                </a>
                <a href="#" id="reminder-telegram-btn" class="messenger-action-btn telegram" target="_blank">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161l-1.84 8.673c-.139.623-.5.775-.99.483l-2.738-2.018-1.32 1.27c-.146.146-.27.27-.552.27l.197-2.8 5.094-4.602c.222-.197-.048-.307-.344-.11l-6.3 3.965-2.71-.85c-.59-.185-.602-.59.124-.874l10.6-4.086c.49-.183.92.11.76.874z"/>
                    </svg>
                    Отправить в Telegram
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Статистика */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 12px;
    min-height: 160px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon .material-icons {
    font-size: 24px;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    font-family: 'JetBrains Mono', monospace;
    line-height: 1;
}

.stat-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-secondary);
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

/* Выбор месяца */
.month-selector {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 24px;
}

.month-nav-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.month-nav-btn:hover {
    background: var(--bg-hover);
    color: var(--accent);
    border-color: var(--accent);
}

.month-select {
    padding: 12px 40px 12px 20px;
    border-radius: 10px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 600;
    font-family: 'Nunito', sans-serif;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%2314b8a6' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    min-width: 200px;
}

.month-select:focus {
    outline: none;
    border-color: var(--accent);
}

/* Фильтры */
.filters-panel {
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
}

.filters-content {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.filter-btn {
    padding: 8px 16px;
    border-radius: 8px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.filter-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.filter-btn.active {
    background: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
}

.search-group {
    margin-left: auto;
    position: relative;
}

.search-group .material-icons {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 20px;
}

.search-group input {
    padding: 8px 16px 8px 40px;
    border-radius: 8px;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    color: var(--text-primary);
    font-size: 0.875rem;
    min-width: 250px;
}

.search-group input:focus {
    outline: none;
    border-color: var(--accent);
}

/* Таблица */
.table-container {
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 24px;
}

#students-table {
    width: 100%;
    border-collapse: collapse;
}

#students-table th,
#students-table td {
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

#students-table th {
    background: var(--bg-dark);
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
}

#students-table tbody tr:hover {
    background: var(--bg-hover);
}

#students-table tbody tr.paid {
    background: rgba(34, 197, 94, 0.05);
}

/* Бейджи */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-success {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
}

.badge-warning {
    background: rgba(234, 179, 8, 0.15);
    color: #eab308;
}

.badge-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.badge-info {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

.badge-purple {
    background: rgba(168, 85, 247, 0.15);
    color: #a855f7;
}

/* Контакты */
.contacts-cell {
    display: flex;
    gap: 6px;
}

.contact-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s;
}

.contact-btn.telegram {
    background: rgba(0, 136, 204, 0.15);
    color: #0088cc;
}

.contact-btn.telegram:hover {
    background: rgba(0, 136, 204, 0.25);
    transform: scale(1.1);
}

.contact-btn.whatsapp {
    background: rgba(37, 211, 102, 0.15);
    color: #25d366;
}

.contact-btn.whatsapp:hover {
    background: rgba(37, 211, 102, 0.25);
    transform: scale(1.1);
}

/* Действия */
.actions-cell {
    display: flex;
    gap: 6px;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.action-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.action-btn.primary {
    background: var(--accent);
    border-color: var(--accent);
    color: white;
}

.action-btn.primary:hover {
    background: var(--accent-hover);
}

.action-btn.success {
    background: rgba(34, 197, 94, 0.15);
    border-color: #22c55e;
    color: #22c55e;
}

.action-btn.danger:hover {
    border-color: #ef4444;
    color: #ef4444;
}

/* Итоговая строка */
.summary-row {
    display: flex;
    justify-content: flex-end;
    gap: 32px;
    padding: 20px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 12px;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.summary-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.summary-value {
    font-size: 1.25rem;
    font-weight: 700;
    font-family: 'JetBrains Mono', monospace;
    color: var(--text-primary);
}

.summary-value.success {
    color: #22c55e;
}

.summary-value.warning {
    color: #eab308;
}

/* Модальное окно */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-elevated);
    border-radius: 16px;
    max-width: 500px;
    width: 90%;
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

.modal-header h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-close {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: transparent;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* Форма */
.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-secondary);
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border-radius: 10px;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    color: var(--text-primary);
    font-size: 0.95rem;
    font-family: 'Nunito', sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
}

/* Карточка ученика в модалке */
.student-info-card {
    background: var(--bg-dark);
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 20px;
}

.student-info-card .student-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 8px;
}

.student-info-card .student-details {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

/* Выбор способа оплаты */
.payment-method-group {
    display: flex;
    gap: 12px;
}

.method-btn {
    flex: 1;
    padding: 14px 16px;
    border-radius: 10px;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.2s;
}

.method-btn:hover {
    border-color: var(--accent);
}

.method-btn.active {
    background: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
}

/* Напоминание */
.reminder-preview {
    background: var(--bg-dark);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    white-space: pre-wrap;
    font-size: 0.95rem;
    line-height: 1.6;
}

.reminder-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.messenger-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 14px 20px;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.messenger-action-btn.whatsapp {
    background: #25d366;
    color: white;
}

.messenger-action-btn.whatsapp:hover {
    background: #1ebe5d;
}

.messenger-action-btn.telegram {
    background: #0088cc;
    color: white;
}

.messenger-action-btn.telegram:hover {
    background: #0077b5;
}

/* Кнопки */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.btn-primary {
    background: var(--accent);
    color: white;
}

.btn-primary:hover {
    background: var(--accent-hover);
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-secondary);
}

.btn-outline:hover {
    border-color: var(--text-secondary);
    color: var(--text-primary);
}

/* Адаптивность */
@media (max-width: 1024px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: 1fr;
    }

    .filters-content {
        flex-direction: column;
        align-items: stretch;
    }

    .search-group {
        margin-left: 0;
    }

    .search-group input {
        width: 100%;
    }

    .summary-row {
        flex-direction: column;
        gap: 16px;
    }
}
</style>

<script>
// Глобальные переменные
let studentsData = [];
let currentFilter = 'all';
let currentTypeFilter = 'all';

// Загрузка данных при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    loadData();
});

// Загрузить данные за выбранный месяц
async function loadData() {
    const month = document.getElementById('month-select').value;

    try {
        // Загружаем список и статистику параллельно
        const [listResponse, statsResponse] = await Promise.all([
            fetch(`/zarplata/api/student_payments.php?action=list&month=${month}`),
            fetch(`/zarplata/api/student_payments.php?action=stats&month=${month}`)
        ]);

        const listResult = await listResponse.json();
        const statsResult = await statsResponse.json();

        if (listResult.success) {
            studentsData = listResult.data;
            renderTable();
        }

        if (statsResult.success) {
            updateStats(statsResult.data);
        }
    } catch (error) {
        console.error('Error loading data:', error);
        showNotification('Ошибка загрузки данных', 'error');
    }
}

// Обновить статистику
function updateStats(stats) {
    document.getElementById('stat-total').textContent = stats.total_students;
    document.getElementById('stat-paid').textContent = stats.paid_students;
    document.getElementById('stat-unpaid').textContent = stats.unpaid_students;
    document.getElementById('stat-amount').textContent = formatMoney(stats.total_paid);

    document.getElementById('summary-expected').textContent = formatMoney(stats.expected_total);
    document.getElementById('summary-paid').textContent = formatMoney(stats.total_paid);
    document.getElementById('summary-remaining').textContent = formatMoney(stats.remaining);
}

// Отрисовать таблицу
function renderTable() {
    const tbody = document.getElementById('students-tbody');
    const searchQuery = document.getElementById('search-input').value.toLowerCase();

    // Фильтрация
    let filtered = studentsData.filter(student => {
        // Фильтр по статусу
        if (currentFilter === 'paid' && !student.is_paid) return false;
        if (currentFilter === 'unpaid' && student.is_paid) return false;

        // Фильтр по типу
        if (currentTypeFilter !== 'all' && student.lesson_type !== currentTypeFilter) return false;

        // Поиск
        if (searchQuery && !student.name.toLowerCase().includes(searchQuery)) return false;

        return true;
    });

    if (filtered.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 40px;">
                    <span class="material-icons" style="font-size: 48px; color: var(--text-muted);">search_off</span>
                    <p style="margin-top: 16px; color: var(--text-muted);">Ученики не найдены</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = filtered.map(student => {
        const lessonTypeBadge = student.lesson_type === 'individual'
            ? '<span class="badge badge-purple"><span class="material-icons" style="font-size: 12px;">person</span> Соло</span>'
            : '<span class="badge badge-info"><span class="material-icons" style="font-size: 12px;">group</span> Группа</span>';

        const paymentTypeSuffix = student.payment_type === 'monthly' ? '/мес' : '/урок';

        const statusBadge = student.is_paid
            ? `<span class="badge badge-success"><span class="material-icons" style="font-size: 12px;">check_circle</span> ${formatMoney(student.paid_amount)}</span>`
            : '<span class="badge badge-danger"><span class="material-icons" style="font-size: 12px;">pending</span> Не оплачено</span>';

        // Контакты родителя
        let contactsHtml = '<div class="contacts-cell">';
        if (student.parent_telegram) {
            contactsHtml += `<a href="https://t.me/${student.parent_telegram}" target="_blank" class="contact-btn telegram" title="Telegram родителя">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161l-1.84 8.673c-.139.623-.5.775-.99.483l-2.738-2.018-1.32 1.27c-.146.146-.27.27-.552.27l.197-2.8 5.094-4.602c.222-.197-.048-.307-.344-.11l-6.3 3.965-2.71-.85c-.59-.185-.602-.59.124-.874l10.6-4.086c.49-.183.92.11.76.874z"/>
                </svg>
            </a>`;
        }
        if (student.parent_whatsapp) {
            const phone = student.parent_whatsapp.replace(/[^0-9]/g, '');
            contactsHtml += `<a href="https://wa.me/${phone}" target="_blank" class="contact-btn whatsapp" title="WhatsApp родителя">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
            </a>`;
        }
        if (!student.parent_telegram && !student.parent_whatsapp) {
            contactsHtml += '<span style="color: var(--text-muted);">—</span>';
        }
        contactsHtml += '</div>';

        // Действия
        let actionsHtml = '<div class="actions-cell">';
        if (student.is_paid) {
            actionsHtml += `<button class="action-btn success" onclick="openPaymentModal(${student.id})" title="Изменить оплату">
                <span class="material-icons">edit</span>
            </button>`;
            actionsHtml += `<button class="action-btn danger" onclick="deletePayment(${student.id})" title="Удалить оплату">
                <span class="material-icons">delete</span>
            </button>`;
        } else {
            actionsHtml += `<button class="action-btn primary" onclick="openPaymentModal(${student.id})" title="Отметить оплату">
                <span class="material-icons">add</span>
            </button>`;
            if (student.parent_telegram || student.parent_whatsapp) {
                actionsHtml += `<button class="action-btn" onclick="openReminderModal(${student.id})" title="Напомнить">
                    <span class="material-icons">notifications</span>
                </button>`;
            }
        }
        actionsHtml += '</div>';

        return `
            <tr class="${student.is_paid ? 'paid' : ''}" data-student-id="${student.id}">
                <td><strong>${escapeHtml(student.name)}</strong></td>
                <td>${student.class ? student.class + ' класс' : '—'}</td>
                <td>${lessonTypeBadge}</td>
                <td><strong>${formatMoney(student.price)}</strong>${paymentTypeSuffix}</td>
                <td>${student.lessons_count}</td>
                <td><strong>${formatMoney(student.expected_amount)}</strong></td>
                <td>${statusBadge}</td>
                <td>${contactsHtml}</td>
                <td>${actionsHtml}</td>
            </tr>
        `;
    }).join('');
}

// Изменить месяц
function changeMonth(delta) {
    const select = document.getElementById('month-select');
    const options = Array.from(select.options);
    const currentIndex = options.findIndex(opt => opt.selected);
    const newIndex = currentIndex + delta;

    if (newIndex >= 0 && newIndex < options.length) {
        select.selectedIndex = newIndex;
        loadData();
    }
}

// Установить фильтр
function setFilter(filter) {
    currentFilter = filter;
    document.querySelectorAll('[data-filter]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === filter);
    });
    renderTable();
}

// Установить фильтр по типу
function setTypeFilter(type) {
    currentTypeFilter = type;
    document.querySelectorAll('[data-type]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.type === type);
    });
    renderTable();
}

// Фильтрация таблицы
function filterTable() {
    renderTable();
}

// Открыть модальное окно оплаты
function openPaymentModal(studentId) {
    const student = studentsData.find(s => s.id === studentId);
    if (!student) return;

    const month = document.getElementById('month-select').value;
    const paymentTypeSuffix = student.payment_type === 'monthly' ? '/мес' : '/урок';

    document.getElementById('payment-student-id').value = studentId;
    document.getElementById('payment-month').value = month;
    document.getElementById('payment-amount').value = student.is_paid ? student.paid_amount : student.expected_amount;
    document.getElementById('payment-notes').value = student.payment_notes || '';

    // Показывать поле "Количество уроков" только для поурочной оплаты
    const lessonsGroup = document.getElementById('lessons-count-group');
    if (student.payment_type === 'per_lesson') {
        lessonsGroup.style.display = 'block';
        document.getElementById('payment-lessons').value = student.paid_lessons || student.lessons_count;
    } else {
        lessonsGroup.style.display = 'none';
    }

    // Способ оплаты
    selectMethod(student.payment_method || 'card');

    // Информация об ученике
    document.getElementById('modal-student-info').innerHTML = `
        <div class="student-name">${escapeHtml(student.name)}</div>
        <div class="student-details">
            <span>${student.class ? student.class + ' класс' : 'Класс не указан'}</span>
            <span>${student.lesson_type === 'individual' ? 'Индивидуальные' : 'Групповые'}</span>
            <span>${formatMoney(student.price)}${paymentTypeSuffix}</span>
            <span>${student.lessons_count} уроков</span>
        </div>
    `;

    document.getElementById('modal-title').textContent = student.is_paid ? 'Изменить оплату' : 'Отметить оплату';
    document.getElementById('payment-modal').classList.add('active');
}

// Закрыть модальное окно оплаты
function closePaymentModal() {
    document.getElementById('payment-modal').classList.remove('active');
}

// Выбрать способ оплаты
function selectMethod(method) {
    document.querySelectorAll('.method-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.method === method);
    });
    document.getElementById('payment-method').value = method;
}

// Сохранить оплату
async function savePayment(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
        const response = await fetch('/zarplata/api/student_payments.php?action=add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Оплата сохранена', 'success');
            closePaymentModal();
            loadData();
        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Error saving payment:', error);
        showNotification('Ошибка сохранения', 'error');
    }
}

// Удалить оплату
async function deletePayment(studentId) {
    if (!confirm('Удалить запись об оплате?')) return;

    const month = document.getElementById('month-select').value;

    try {
        const response = await fetch('/zarplata/api/student_payments.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ student_id: studentId, month: month })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Оплата удалена', 'success');
            loadData();
        } else {
            showNotification(result.error || 'Ошибка удаления', 'error');
        }
    } catch (error) {
        console.error('Error deleting payment:', error);
        showNotification('Ошибка удаления', 'error');
    }
}

// Открыть модальное окно напоминания
async function openReminderModal(studentId) {
    const month = document.getElementById('month-select').value;
    const student = studentsData.find(s => s.id === studentId);

    try {
        const response = await fetch(`/zarplata/api/student_payments.php?action=get_reminder&student_id=${studentId}&month=${month}`);
        const result = await response.json();

        if (result.success) {
            const data = result.data;

            document.getElementById('reminder-preview').textContent = data.message;

            // WhatsApp
            if (data.parent_whatsapp) {
                const phone = data.parent_whatsapp.replace(/[^0-9]/g, '');
                const whatsappUrl = `https://wa.me/${phone}?text=${encodeURIComponent(data.message)}`;
                document.getElementById('reminder-whatsapp-btn').href = whatsappUrl;
                document.getElementById('reminder-whatsapp-btn').style.display = 'flex';
            } else {
                document.getElementById('reminder-whatsapp-btn').style.display = 'none';
            }

            // Telegram
            if (data.parent_telegram) {
                const telegramUrl = `https://t.me/${data.parent_telegram}`;
                document.getElementById('reminder-telegram-btn').href = telegramUrl;
                document.getElementById('reminder-telegram-btn').style.display = 'flex';
            } else {
                document.getElementById('reminder-telegram-btn').style.display = 'none';
            }

            document.getElementById('reminder-modal').classList.add('active');
        } else {
            showNotification(result.error || 'Ошибка загрузки', 'error');
        }
    } catch (error) {
        console.error('Error loading reminder:', error);
        showNotification('Ошибка загрузки', 'error');
    }
}

// Закрыть модальное окно напоминания
function closeReminderModal() {
    document.getElementById('reminder-modal').classList.remove('active');
}

// Утилиты
function formatMoney(amount) {
    return new Intl.NumberFormat('ru-RU').format(amount) + ' ₽';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span class="material-icons">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}</span>
        <span>${escapeHtml(message)}</span>
    `;

    document.body.appendChild(notification);

    setTimeout(() => notification.classList.add('show'), 10);

    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
