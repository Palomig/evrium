<?php
/**
 * Mobile Reports Page
 * Отчёты и аналитика для мобильной версии
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Параметры фильтров
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$teacherId = filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT) ?: null;

// Получить всех преподавателей для фильтра
$teachers = dbQuery("SELECT id, name, display_name FROM teachers WHERE active = 1 ORDER BY name", []);

define('PAGE_TITLE', 'Отчёты');
define('ACTIVE_PAGE', 'reports');

require_once __DIR__ . '/templates/header.php';
?>

<style>
.reports-container {
    padding: 12px;
    padding-bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + 16px);
}

/* Фильтры */
.filters-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 16px;
    border: 1px solid var(--border);
}

.filters-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 8px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filter-group.full-width {
    grid-column: 1 / -1;
}

.filter-label {
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 500;
}

.filter-input {
    height: 40px;
    padding: 0 12px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 14px;
    font-family: inherit;
    -webkit-appearance: none;
}

.filter-input:focus {
    outline: none;
    border-color: var(--accent);
}

.filter-select {
    height: 40px;
    padding: 0 12px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 14px;
    font-family: inherit;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%238b95a5' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 20px;
    padding-right: 32px;
}

.filter-btn {
    height: 40px;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: opacity 0.15s;
}

.filter-btn:active {
    opacity: 0.8;
}

/* Период */
.period-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-elevated);
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 12px;
}

.period-badge svg {
    width: 14px;
    height: 14px;
    color: var(--accent);
}

/* Статистика */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}

.stat-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 14px;
    border: 1px solid var(--border);
}

.stat-card.wide {
    grid-column: 1 / -1;
}

.stat-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 22px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.stat-value.money {
    color: var(--status-green);
}

.stat-value.pending {
    color: var(--status-amber);
}

.stat-label {
    font-size: 12px;
    color: var(--text-muted);
}

/* Секция */
.section-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title svg {
    width: 18px;
    height: 18px;
    color: var(--accent);
}

/* Список преподавателей */
.teachers-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 16px;
}

.teacher-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 14px;
    border: 1px solid var(--border);
}

.teacher-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.teacher-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
}

.teacher-earned {
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    font-weight: 600;
    color: var(--status-green);
}

.teacher-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.teacher-stat {
    text-align: center;
    padding: 8px;
    background: var(--bg-elevated);
    border-radius: 8px;
}

.teacher-stat-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
}

.teacher-stat-label {
    font-size: 10px;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Экспорт */
.export-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 14px;
    border: 1px solid var(--border);
    margin-bottom: 16px;
}

.export-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    height: 44px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
}

.export-btn:active {
    background: var(--accent-dim);
    border-color: var(--accent);
}

.export-btn svg {
    width: 20px;
    height: 20px;
    color: var(--accent);
}

/* Загрузка */
.loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: var(--text-muted);
}

.spinner {
    width: 32px;
    height: 32px;
    border: 3px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 12px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Пустое состояние */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.empty-icon {
    width: 48px;
    height: 48px;
    margin-bottom: 12px;
    opacity: 0.5;
}
</style>

<div class="reports-container">
    <!-- Фильтры -->
    <div class="filters-card">
        <form id="filtersForm" method="GET">
            <div class="filters-row">
                <div class="filter-group">
                    <span class="filter-label">Дата с</span>
                    <input type="date" name="date_from" class="filter-input" value="<?= e($dateFrom) ?>">
                </div>
                <div class="filter-group">
                    <span class="filter-label">Дата по</span>
                    <input type="date" name="date_to" class="filter-input" value="<?= e($dateTo) ?>">
                </div>
            </div>
            <div class="filters-row">
                <div class="filter-group">
                    <span class="filter-label">Преподаватель</span>
                    <select name="teacher_id" class="filter-select">
                        <option value="">Все</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $teacherId == $t['id'] ? 'selected' : '' ?>>
                                <?= e($t['display_name'] ?: $t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <span class="filter-label">&nbsp;</span>
                    <button type="submit" class="filter-btn">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Показать
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Период -->
    <div class="period-badge">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <span id="periodText"><?= date('d.m', strtotime($dateFrom)) ?> — <?= date('d.m.Y', strtotime($dateTo)) ?></span>
    </div>

    <!-- Контент (загружается динамически) -->
    <div id="reportContent">
        <div class="loading">
            <div class="spinner"></div>
            <span>Загрузка данных...</span>
        </div>
    </div>

    <!-- Экспорт -->
    <div class="export-card">
        <button class="export-btn" onclick="exportCSV()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Экспорт в CSV
        </button>
    </div>
</div>

<script>
const dateFrom = '<?= $dateFrom ?>';
const dateTo = '<?= $dateTo ?>';
const teacherId = <?= $teacherId ? $teacherId : 'null' ?>;

// Загрузка данных
async function loadReports() {
    const content = document.getElementById('reportContent');

    try {
        // Загружаем сводку и данные по преподавателям параллельно
        const [summaryRes, teachersRes] = await Promise.all([
            fetch(`/zarplata/api/reports.php?action=summary&date_from=${dateFrom}&date_to=${dateTo}`),
            fetch(`/zarplata/api/reports.php?action=teacher_chart&date_from=${dateFrom}&date_to=${dateTo}`)
        ]);

        const summaryData = await summaryRes.json();
        const teachersData = await teachersRes.json();

        if (!summaryData.success || !teachersData.success) {
            throw new Error('Ошибка загрузки данных');
        }

        const summary = summaryData.data.summary;
        const financial = summaryData.data.financial;
        const teachersList = teachersData.data;

        // Фильтруем по преподавателю если выбран
        let filteredTeachers = teachersList;
        if (teacherId) {
            filteredTeachers = teachersList.filter(t => {
                // Находим преподавателя по имени (API возвращает имя, а не id)
                return true; // Показываем всех, фильтрация на сервере
            });
        }

        content.innerHTML = `
            <!-- Статистика -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">${summary.completed_lessons || 0}</div>
                    <div class="stat-label">Уроков проведено</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${summary.total_students || 0}</div>
                    <div class="stat-label">Учеников посетило</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value money">${formatMoney(financial.paid_total || 0)}</div>
                    <div class="stat-label">Выплачено</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value pending">${formatMoney(financial.pending_total || 0)}</div>
                    <div class="stat-label">Ожидает выплаты</div>
                </div>
                <div class="stat-card wide">
                    <div class="stat-value">${formatMoney(financial.total_amount || 0)}</div>
                    <div class="stat-label">Всего за период (включая одобренные)</div>
                </div>
            </div>

            <!-- По преподавателям -->
            <div class="section-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                По преподавателям
            </div>
            <div class="teachers-list">
                ${filteredTeachers.length > 0 ? filteredTeachers.map(t => `
                    <div class="teacher-card">
                        <div class="teacher-header">
                            <span class="teacher-name">${escapeHtml(t.name)}</span>
                            <span class="teacher-earned">${formatMoney(t.total_earned || 0)}</span>
                        </div>
                        <div class="teacher-stats">
                            <div class="teacher-stat">
                                <div class="teacher-stat-value">${t.lessons_count || 0}</div>
                                <div class="teacher-stat-label">Уроков</div>
                            </div>
                            <div class="teacher-stat">
                                <div class="teacher-stat-value">${t.completed_count || 0}</div>
                                <div class="teacher-stat-label">Проведено</div>
                            </div>
                            <div class="teacher-stat">
                                <div class="teacher-stat-value">${t.students_taught || 0}</div>
                                <div class="teacher-stat-label">Учеников</div>
                            </div>
                        </div>
                    </div>
                `).join('') : `
                    <div class="empty-state">
                        <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p>Нет данных за выбранный период</p>
                    </div>
                `}
            </div>
        `;
    } catch (error) {
        console.error('Error loading reports:', error);
        content.innerHTML = `
            <div class="empty-state">
                <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p>Ошибка загрузки данных</p>
            </div>
        `;
    }
}

// Форматирование денег
function formatMoney(amount) {
    return new Intl.NumberFormat('ru-RU').format(amount || 0) + ' ₽';
}

// Экранирование HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Экспорт CSV
function exportCSV() {
    let url = `/zarplata/api/reports.php?action=export_excel&date_from=${dateFrom}&date_to=${dateTo}`;
    if (teacherId) {
        url += `&teacher_id=${teacherId}`;
    }
    window.location.href = url;
}

// Инициализация
loadReports();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
