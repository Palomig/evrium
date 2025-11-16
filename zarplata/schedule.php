<?php
/**
 * Страница расписания (Канбан доска)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить преподавателей с их формулами оплаты
$teachers = dbQuery("
    SELECT t.id, t.name, t.formula_id, pf.name as formula_name
    FROM teachers t
    LEFT JOIN payment_formulas pf ON t.formula_id = pf.id
    WHERE t.active = 1
    ORDER BY t.name
", []);

// Получить все активные шаблоны расписания
$templates = dbQuery(
    "SELECT lt.*, t.name as teacher_name, pf.name as formula_name
     FROM lessons_template lt
     LEFT JOIN teachers t ON lt.teacher_id = t.id
     LEFT JOIN payment_formulas pf ON lt.formula_id = pf.id
     WHERE lt.active = 1
     ORDER BY lt.day_of_week ASC, lt.time_start ASC",
    []
);

define('PAGE_TITLE', 'Расписание');
define('PAGE_SUBTITLE', 'Канбан доска с расписанием занятий');
define('ACTIVE_PAGE', 'schedule');

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* Канбан доска стили */
.kanban-header {
    background-color: var(--md-surface);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--elevation-2);
}

.kanban-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 16px;
}

.kanban-legend {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.legend-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

.legend-label {
    font-weight: 600;
    color: var(--text-medium-emphasis);
    font-size: 0.875rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    color: var(--text-medium-emphasis);
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

.legend-divider {
    width: 1px;
    height: 24px;
    background: rgba(255, 255, 255, 0.12);
}

.filters-panel {
    background-color: var(--md-surface);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: var(--elevation-2);
}

.filters-content {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    gap: 8px;
    align-items: center;
}

.day-filter-btn,
.time-filter-select {
    padding: 10px 16px;
    border: 2px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    background-color: var(--md-surface-3);
    color: var(--text-medium-emphasis);
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    transition: all 0.2s var(--transition-standard);
    user-select: none;
}

.day-filter-btn:hover {
    border-color: var(--md-primary);
    background-color: var(--md-surface-4);
}

.day-filter-btn.active {
    background-color: rgba(187, 134, 252, 0.15);
    border-color: var(--md-primary);
    color: var(--md-primary);
}

.time-filter-select {
    min-width: 100px;
    padding-right: 40px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23BB86FC' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 20px;
    appearance: none;
}

.filter-divider {
    width: 1px;
    height: 32px;
    background: rgba(255, 255, 255, 0.12);
    margin: 0 8px;
}

.btn-reset-filters {
    padding: 10px 16px;
    border: 2px solid var(--md-error);
    border-radius: 8px;
    background: transparent;
    color: var(--md-error);
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    transition: all 0.2s var(--transition-standard);
}

.btn-reset-filters:hover {
    background-color: var(--md-error);
    color: white;
}

.kanban-container {
    position: relative;
    overflow-x: auto;
    overflow-y: hidden;
    background-color: var(--md-surface);
    border-radius: 12px;
    padding: 20px;
    box-shadow: var(--elevation-2);
}

.kanban-board {
    display: flex;
    gap: 20px;
    min-width: fit-content;
}

.kanban-column {
    background-color: var(--md-surface-3);
    border-radius: 12px;
    min-width: 300px;
    max-width: 300px;
    box-shadow: var(--elevation-1);
    display: flex;
    flex-direction: column;
}

.kanban-column.hidden {
    display: none;
}

.kanban-column-header {
    background-color: var(--md-surface-4);
    color: var(--text-high-emphasis);
    padding: 16px;
    border-radius: 12px 12px 0 0;
    text-align: center;
    font-weight: 700;
    font-size: 1rem;
    position: sticky;
    top: 0;
    z-index: 10;
    border-bottom: 2px solid rgba(255, 255, 255, 0.12);
}

.kanban-column-content {
    padding: 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 70vh;
    overflow-y: auto;
}

.lesson-card {
    background-color: var(--md-surface);
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s var(--transition-standard);
    box-shadow: var(--elevation-2);
    border-left: 4px solid;
}

.lesson-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--elevation-3);
}

.lesson-card.math {
    border-left-color: #5599ff;
}

.lesson-card.physics {
    border-left-color: #ff5555;
}

.lesson-card.informatics {
    border-left-color: #55cc77;
}

.lesson-card.Математика {
    border-left-color: #5599ff;
}

.lesson-card.Физика {
    border-left-color: #ff5555;
}

.lesson-card.Информатика {
    border-left-color: #55cc77;
}

.card-header {
    padding: 12px;
    background-color: rgba(255, 255, 255, 0.03);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.card-time {
    font-weight: 700;
    font-size: 1rem;
    color: var(--md-primary);
    display: flex;
    align-items: center;
    gap: 4px;
}

.card-type-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}

.card-type-badge.group {
    background-color: rgba(3, 218, 198, 0.2);
    color: var(--md-secondary);
}

.card-type-badge.individual {
    background-color: rgba(76, 175, 80, 0.2);
    color: var(--md-success);
}

.card-body {
    padding: 12px;
}

.card-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 0.875rem;
}

.card-row:last-child {
    margin-bottom: 0;
}

.card-row .material-icons {
    font-size: 16px;
    color: var(--text-medium-emphasis);
}

.card-label {
    color: var(--text-medium-emphasis);
    flex: 1;
}

.card-value {
    color: var(--text-high-emphasis);
    font-weight: 500;
}

.card-subject {
    color: var(--md-primary);
    font-weight: 600;
}

.empty-slot {
    min-height: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-disabled);
    font-size: 0.875rem;
    border: 2px dashed rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s var(--transition-standard);
    padding: 16px;
}

.empty-slot:hover {
    border-color: var(--md-primary);
    color: var(--md-primary);
    background-color: rgba(187, 134, 252, 0.05);
}

.empty-slot .material-icons {
    font-size: 32px;
    margin-bottom: 4px;
}

.empty-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    color: var(--text-disabled);
    text-align: center;
}

.empty-column .material-icons {
    font-size: 48px;
    margin-bottom: 12px;
}

/* Скроллбар */
.kanban-container::-webkit-scrollbar,
.kanban-column-content::-webkit-scrollbar {
    height: 10px;
    width: 8px;
}

.kanban-container::-webkit-scrollbar-track,
.kanban-column-content::-webkit-scrollbar-track {
    background: var(--md-background);
    border-radius: 10px;
}

.kanban-container::-webkit-scrollbar-thumb,
.kanban-column-content::-webkit-scrollbar-thumb {
    background: var(--md-surface-4);
    border-radius: 10px;
}

.kanban-container::-webkit-scrollbar-thumb:hover,
.kanban-column-content::-webkit-scrollbar-thumb:hover {
    background: var(--md-surface-5);
}
</style>

<!-- Заголовок с легендой -->
<div class="kanban-header">
    <div class="kanban-header-top">
        <h2 style="margin: 0; font-size: 1.5rem;">📅 Расписание занятий</h2>
        <button class="btn btn-primary" onclick="openTemplateModal()">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Добавить занятие
        </button>
    </div>

    <div class="kanban-legend">
        <div class="legend-group">
            <span class="legend-label">Предметы:</span>
            <div class="legend-item">
                <div class="legend-color" style="background: #5599ff;"></div>
                <span>Математика</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #ff5555;"></div>
                <span>Физика</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #55cc77;"></div>
                <span>Информатика</span>
            </div>
        </div>

        <div class="legend-divider"></div>

        <div class="legend-group">
            <span class="legend-label">Типы:</span>
            <div class="legend-item">
                <span class="card-type-badge group">Групп.</span>
                <span>Групповое</span>
            </div>
            <div class="legend-item">
                <span class="card-type-badge individual">Индив.</span>
                <span>Индивидуальное</span>
            </div>
        </div>
    </div>
</div>

<!-- Панель фильтров -->
<div class="filters-panel">
    <div class="filters-content">
        <div class="filter-group">
            <button class="day-filter-btn active" data-day="1" onclick="toggleDayFilter(this)">Пн</button>
            <button class="day-filter-btn active" data-day="2" onclick="toggleDayFilter(this)">Вт</button>
            <button class="day-filter-btn active" data-day="3" onclick="toggleDayFilter(this)">Ср</button>
            <button class="day-filter-btn active" data-day="4" onclick="toggleDayFilter(this)">Чт</button>
            <button class="day-filter-btn active" data-day="5" onclick="toggleDayFilter(this)">Пт</button>
            <button class="day-filter-btn active" data-day="6" onclick="toggleDayFilter(this)">Сб</button>
            <button class="day-filter-btn active" data-day="7" onclick="toggleDayFilter(this)">Вс</button>
        </div>

        <div class="filter-divider"></div>

        <div class="filter-group">
            <span class="legend-label">от</span>
            <select id="timeFrom" class="time-filter-select" onchange="applyTimeRange()">
                <option value="">Все</option>
                <?php for ($h = 8; $h <= 20; $h++): ?>
                    <option value="<?= sprintf('%02d:00', $h) ?>"><?= sprintf('%02d:00', $h) ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="filter-group">
            <span class="legend-label">до</span>
            <select id="timeTo" class="time-filter-select" onchange="applyTimeRange()">
                <option value="">Все</option>
                <?php for ($h = 8; $h <= 21; $h++): ?>
                    <option value="<?= sprintf('%02d:00', $h) ?>"><?= sprintf('%02d:00', $h) ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <button class="btn-reset-filters" onclick="resetFilters()">
            <span class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 4px;">refresh</span>
            Сбросить
        </button>
    </div>
</div>

<!-- Канбан доска -->
<div class="kanban-container">
    <div class="kanban-board" id="kanbanBoard">
        <!-- Генерируется JavaScript -->
    </div>
</div>

<!-- Модальное окно добавления/редактирования урока -->
<div id="template-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Добавить урок в расписание</h3>
            <button class="modal-close" onclick="closeTemplateModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <form id="template-form" onsubmit="saveTemplate(event)">
            <input type="hidden" id="template-id" name="id">

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="template-teacher">Преподаватель *</label>
                    <select id="template-teacher" name="teacher_id" required>
                        <option value="">Выберите преподавателя</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>"><?= e($teacher['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="template-day">День недели *</label>
                    <select id="template-day" name="day_of_week" required>
                        <option value="">Выберите день</option>
                        <option value="1">Понедельник</option>
                        <option value="2">Вторник</option>
                        <option value="3">Среда</option>
                        <option value="4">Четверг</option>
                        <option value="5">Пятница</option>
                        <option value="6">Суббота</option>
                        <option value="7">Воскресенье</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Время начала урока *</label>
                <input type="hidden" id="template-time-start" name="time_start" required>
                <input type="hidden" id="template-time-end" name="time_end" required>
                <div class="time-buttons">
                    <?php for ($hour = 8; $hour <= 21; $hour++): ?>
                        <button type="button" class="time-btn" data-hour="<?= $hour ?>" onclick="selectTime(<?= $hour ?>)">
                            <?= sprintf('%02d', $hour) ?>
                        </button>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Предмет *</label>
                <input type="hidden" id="template-subject" name="subject" required>
                <div class="subject-buttons">
                    <button type="button" class="subject-btn" data-subject="Математика" onclick="selectSubject('Математика')">
                        Математика
                    </button>
                    <button type="button" class="subject-btn" data-subject="Физика" onclick="selectSubject('Физика')">
                        Физика
                    </button>
                    <button type="button" class="subject-btn" data-subject="Информатика" onclick="selectSubject('Информатика')">
                        Информатика
                    </button>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="template-type">Тип урока *</label>
                    <select id="template-type" name="lesson_type" required>
                        <option value="group">Групповое</option>
                        <option value="individual">Индивидуальное</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="template-students">Количество учеников *</label>
                    <input type="number" id="template-students" name="expected_students" min="1" value="1" required>
                </div>
            </div>

            <!-- Скрытое поле для formula_id (подставляется автоматически из данных преподавателя) -->
            <input type="hidden" id="template-formula" name="formula_id">

            <!-- Информация о формуле оплаты -->
            <div class="form-group" id="formula-info-group" style="display: none;">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <span class="material-icons" style="font-size: 18px; color: var(--md-secondary);">payments</span>
                    Формула оплаты
                </label>
                <div style="padding: 12px; background-color: rgba(3, 218, 198, 0.1); border-left: 3px solid var(--md-secondary); border-radius: 4px;">
                    <p id="formula-info-text" style="margin: 0; color: var(--text-high-emphasis); font-size: 0.875rem;"></p>
                    <p style="margin: 4px 0 0 0; color: var(--text-medium-emphasis); font-size: 0.75rem;">
                        Назначается автоматически из профиля преподавателя
                    </p>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-text" onclick="closeTemplateModal()">Отмена</button>
                <button type="submit" class="btn btn-primary" id="save-template-btn">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                    Сохранить
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Данные шаблонов из PHP
const templatesData = <?= json_encode($templates, JSON_UNESCAPED_UNICODE) ?>;

// Данные преподавателей с формулами
const teachersData = <?= json_encode($teachers, JSON_UNESCAPED_UNICODE) ?>;

// Дни недели
const daysOfWeek = [
    { id: 1, name: 'Понедельник', short: 'Пн' },
    { id: 2, name: 'Вторник', short: 'Вт' },
    { id: 3, name: 'Среда', short: 'Ср' },
    { id: 4, name: 'Четверг', short: 'Чт' },
    { id: 5, name: 'Пятница', short: 'Пт' },
    { id: 6, name: 'Суббота', short: 'Сб' },
    { id: 7, name: 'Воскресенье', short: 'Вс' }
];

// Обработчик изменения преподавателя - автоматическая подстановка формулы
document.addEventListener('DOMContentLoaded', () => {
    const teacherSelect = document.getElementById('template-teacher');
    const formulaInput = document.getElementById('template-formula');
    const formulaInfoGroup = document.getElementById('formula-info-group');
    const formulaInfoText = document.getElementById('formula-info-text');

    if (teacherSelect) {
        teacherSelect.addEventListener('change', function() {
            const teacherId = parseInt(this.value);

            if (!teacherId) {
                // Преподаватель не выбран - скрыть информацию о формуле
                formulaInfoGroup.style.display = 'none';
                formulaInput.value = '';
                return;
            }

            // Найти преподавателя в данных
            const teacher = teachersData.find(t => t.id === teacherId);

            if (teacher) {
                if (teacher.formula_id) {
                    // У преподавателя есть формула - подставить
                    formulaInput.value = teacher.formula_id;
                    formulaInfoText.textContent = teacher.formula_name || 'Формула назначена';
                    formulaInfoGroup.style.display = 'block';
                } else {
                    // У преподавателя нет формулы
                    formulaInput.value = '';
                    formulaInfoText.textContent = 'У преподавателя не назначена формула оплаты';
                    formulaInfoGroup.style.display = 'block';
                }
            }
        });
    }
});

// Отрисовка канбан доски
function renderKanban() {
    const board = document.getElementById('kanbanBoard');
    board.innerHTML = '';

    daysOfWeek.forEach(day => {
        const column = document.createElement('div');
        column.className = 'kanban-column';
        column.dataset.day = day.id;

        const header = document.createElement('div');
        header.className = 'kanban-column-header';
        header.textContent = day.name;

        const content = document.createElement('div');
        content.className = 'kanban-column-content';

        // Получить уроки для этого дня
        const dayLessons = templatesData.filter(t => parseInt(t.day_of_week) === day.id)
            .sort((a, b) => a.time_start.localeCompare(b.time_start));

        if (dayLessons.length === 0) {
            const emptyState = document.createElement('div');
            emptyState.className = 'empty-column';
            emptyState.innerHTML = `
                <span class="material-icons">event_busy</span>
                <p>Нет занятий</p>
            `;
            content.appendChild(emptyState);
        } else {
            dayLessons.forEach(lesson => {
                const card = createLessonCard(lesson);
                content.appendChild(card);
            });
        }

        // Добавить пустой слот для добавления нового урока
        const emptySlot = document.createElement('div');
        emptySlot.className = 'empty-slot';
        emptySlot.innerHTML = `
            <span class="material-icons">add_circle_outline</span>
            <span>Добавить урок</span>
        `;
        emptySlot.onclick = () => openTemplateModal(day.id);
        content.appendChild(emptySlot);

        column.appendChild(header);
        column.appendChild(content);
        board.appendChild(column);
    });
}

// Создать карточку урока
function createLessonCard(lesson) {
    const card = document.createElement('div');
    card.className = `lesson-card ${lesson.subject || ''}`;
    card.dataset.time = lesson.time_start;
    card.onclick = () => editTemplate(lesson.id);

    const timeStart = lesson.time_start.substring(0, 5);
    const timeEnd = lesson.time_end.substring(0, 5);
    const typeBadge = lesson.lesson_type === 'group' ? 'group' : 'individual';
    const typeText = lesson.lesson_type === 'group' ? 'Групп.' : 'Индив.';

    card.innerHTML = `
        <div class="card-header">
            <div class="card-time">
                <span class="material-icons" style="font-size: 18px;">schedule</span>
                ${timeStart}
            </div>
            <span class="card-type-badge ${typeBadge}">${typeText}</span>
        </div>
        <div class="card-body">
            <div class="card-row">
                <span class="material-icons">subject</span>
                <span class="card-subject">${escapeHtml(lesson.subject || '—')}</span>
            </div>
            <div class="card-row">
                <span class="material-icons">person</span>
                <span class="card-value">${escapeHtml(lesson.teacher_name || '—')}</span>
            </div>
            <div class="card-row">
                <span class="material-icons">group</span>
                <span class="card-label">Учеников:</span>
                <span class="card-value">${lesson.expected_students}</span>
            </div>
            ${lesson.formula_name ? `
            <div class="card-row">
                <span class="material-icons">payments</span>
                <span class="card-value" style="font-size: 0.8rem;">${escapeHtml(lesson.formula_name)}</span>
            </div>
            ` : ''}
        </div>
    `;

    return card;
}

// Фильтр по дням
function toggleDayFilter(button) {
    button.classList.toggle('active');
    updateVisibleDays();
}

function updateVisibleDays() {
    const activeDays = Array.from(document.querySelectorAll('.day-filter-btn.active'))
        .map(btn => parseInt(btn.dataset.day));

    document.querySelectorAll('.kanban-column').forEach(col => {
        const day = parseInt(col.dataset.day);
        if (activeDays.length === 0 || activeDays.includes(day)) {
            col.classList.remove('hidden');
        } else {
            col.classList.add('hidden');
        }
    });
}

// Фильтр по времени
function applyTimeRange() {
    const timeFrom = document.getElementById('timeFrom').value;
    const timeTo = document.getElementById('timeTo').value;

    document.querySelectorAll('.lesson-card').forEach(card => {
        const cardTime = card.dataset.time;
        let shouldShow = true;

        if (timeFrom && cardTime < timeFrom) {
            shouldShow = false;
        }
        if (timeTo && cardTime > timeTo) {
            shouldShow = false;
        }

        card.style.display = shouldShow ? 'block' : 'none';
    });
}

// Сбросить фильтры
function resetFilters() {
    // Сбросить дни
    document.querySelectorAll('.day-filter-btn').forEach(btn => {
        btn.classList.add('active');
    });
    updateVisibleDays();

    // Сбросить время
    document.getElementById('timeFrom').value = '';
    document.getElementById('timeTo').value = '';

    // Показать все карточки
    document.querySelectorAll('.lesson-card').forEach(card => {
        card.style.display = 'block';
    });
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    renderKanban();
});
</script>

<script src="/zarplata/assets/js/schedule.js"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
