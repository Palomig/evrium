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
// Проверяем, существует ли столбец formula_id (для обратной совместимости)
try {
    $teachers = dbQuery("
        SELECT t.id, t.name, t.formula_id, pf.name as formula_name
        FROM teachers t
        LEFT JOIN payment_formulas pf ON t.formula_id = pf.id
        WHERE t.active = 1
        ORDER BY t.name
    ", []);
} catch (PDOException $e) {
    // Если столбец formula_id не существует, загружаем без него
    if (strpos($e->getMessage(), 'formula_id') !== false) {
        $teachers = dbQuery("
            SELECT t.id, t.name, NULL as formula_id, NULL as formula_name
            FROM teachers t
            WHERE t.active = 1
            ORDER BY t.name
        ", []);
    } else {
        throw $e;
    }
}

// Получить все активные шаблоны расписания
// Используем formula_id из teachers если есть, иначе из lessons_template
try {
    $templates = dbQuery(
        "SELECT lt.*, t.name as teacher_name,
                COALESCE(t_pf.name, lt_pf.name) as formula_name
         FROM lessons_template lt
         LEFT JOIN teachers t ON lt.teacher_id = t.id
         LEFT JOIN payment_formulas t_pf ON t.formula_id = t_pf.id
         LEFT JOIN payment_formulas lt_pf ON lt.formula_id = lt_pf.id
         WHERE lt.active = 1
         ORDER BY lt.day_of_week ASC, lt.time_start ASC",
        []
    );
} catch (PDOException $e) {
    // Fallback если столбец formula_id не существует в teachers
    $templates = dbQuery(
        "SELECT lt.*, t.name as teacher_name, pf.name as formula_name
         FROM lessons_template lt
         LEFT JOIN teachers t ON lt.teacher_id = t.id
         LEFT JOIN payment_formulas pf ON lt.formula_id = pf.id
         WHERE lt.active = 1
         ORDER BY lt.day_of_week ASC, lt.time_start ASC",
        []
    );
}

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
    padding: 0;
}

.card-table {
    width: 100%;
}

.card-row-tier {
    display: grid;
    grid-template-columns: auto 1fr;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    min-height: 40px;
}

.card-row-info {
    display: grid;
    grid-template-columns: 1fr;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    min-height: 32px;
}

.card-row-info:last-child {
    border-bottom: none;
}

.card-cell {
    padding: 8px 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    text-align: center;
}

.card-cell.tier-cell {
    padding: 8px;
    justify-content: center;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
}

.card-cell.capacity {
    font-weight: 700;
    font-size: 1rem;
}

.card-cell.capacity.available {
    color: #55cc77;
}

.card-cell.capacity.full {
    color: #ff5555;
}

.card-cell.grades {
    color: #88bbff;
    font-size: 0.875rem;
}

.card-cell.teacher {
    color: var(--text-high-emphasis);
    font-size: 0.875rem;
}

.tier-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-block;
    text-transform: uppercase;
}

.tier-S {
    background: #ff9999;
    color: #000;
}

.tier-A {
    background: #ffcc99;
    color: #000;
}

.tier-B {
    background: #ffff99;
    color: #000;
}

.tier-C {
    background: #ccff99;
    color: #000;
}

.tier-D {
    background: #99ff99;
    color: #000;
}

.spoiler-btn {
    width: 100%;
    padding: 10px;
    background: rgba(255, 255, 255, 0.05);
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    color: var(--md-primary);
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    transition: all 0.2s var(--transition-standard);
    text-align: center;
}

.spoiler-btn:hover {
    background: rgba(255, 255, 255, 0.1);
}

.students-list {
    display: none;
    padding: 12px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.students-list.show {
    display: block;
}

.student-name {
    font-size: 0.875rem;
    color: var(--text-medium-emphasis);
    padding: 4px 8px;
    border-left: 2px solid rgba(187, 134, 252, 0.3);
    margin-bottom: 4px;
}

.student-name:last-child {
    margin-bottom: 0;
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
                    <label for="template-students">Макс. учеников *</label>
                    <input type="number" id="template-students" name="expected_students" min="1" max="10" value="6" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="template-tier">Уровень группы (Тир) *</label>
                    <select id="template-tier" name="tier" required>
                        <option value="S">S - Высший</option>
                        <option value="A">A - Высокий</option>
                        <option value="B">B - Средний</option>
                        <option value="C" selected>C - Базовый</option>
                        <option value="D">D - Начальный</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="template-grades">Классы (через запятую)</label>
                    <input type="text" id="template-grades" name="grades" placeholder="6, 7, 8">
                    <small>Например: 6, 7 или 9, 10, 11</small>
                </div>
            </div>

            <div class="form-group">
                <label for="template-student-list">Список учеников (каждый с новой строки)</label>
                <textarea id="template-student-list" name="students" rows="4" placeholder="Иван Петров&#10;Мария Сидорова&#10;Дмитрий Козлов"></textarea>
                <small>Введите имена учеников, каждое имя на отдельной строке</small>
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

    const timeStart = lesson.time_start.substring(0, 5);
    const typeBadge = lesson.lesson_type === 'group' ? 'group' : 'individual';
    const typeText = lesson.lesson_type === 'group' ? 'Групп.' : 'Индив.';

    // Парсим учеников из JSON или текста
    let students = [];
    if (lesson.students) {
        try {
            students = typeof lesson.students === 'string' ? JSON.parse(lesson.students) : lesson.students;
        } catch (e) {
            // Если не JSON, пытаемся разбить по переводам строк
            students = lesson.students.split('\n').filter(s => s.trim());
        }
    }

    // Текущее количество учеников
    const currentStudents = students.length || 0;
    const maxStudents = lesson.expected_students || 6;
    const isFull = currentStudents >= maxStudents;
    const capacityClass = isFull ? 'full' : 'available';

    // Тир (по умолчанию C если не указан)
    const tier = lesson.tier || 'C';

    // Классы
    const grades = lesson.grades || '';

    card.innerHTML = `
        <div class="card-header">
            <div class="card-time">
                <span class="material-icons" style="font-size: 18px;">schedule</span>
                ${timeStart}
            </div>
            <span class="card-type-badge ${typeBadge}">${typeText}</span>
        </div>
        <div class="card-body">
            <div class="card-table">
                <div class="card-row-tier">
                    <div class="card-cell tier-cell">
                        <span class="tier-badge tier-${tier}">${tier}</span>
                    </div>
                    <div class="card-cell capacity ${capacityClass}">
                        ${currentStudents}/${maxStudents}
                    </div>
                </div>
                ${grades ? `
                <div class="card-row-info">
                    <div class="card-cell grades">${escapeHtml(grades)} кл.</div>
                </div>
                ` : ''}
                <div class="card-row-info">
                    <div class="card-cell teacher">${escapeHtml(lesson.teacher_name || '—')}</div>
                </div>
            </div>
        </div>
        ${students.length > 0 ? `
        <button class="spoiler-btn" onclick="event.stopPropagation(); toggleStudents(this, ${lesson.id})">
            👥 Ученики (${students.length})
        </button>
        <div class="students-list" id="students-${lesson.id}">
            ${students.map(s => `<div class="student-name">• ${escapeHtml(s)}</div>`).join('')}
        </div>
        ` : ''}
    `;

    // Добавляем обработчик клика для редактирования (но не на кнопку спойлера)
    card.addEventListener('click', (e) => {
        if (!e.target.classList.contains('spoiler-btn') && !e.target.closest('.spoiler-btn')) {
            editTemplate(lesson.id);
        }
    });

    return card;
}

// Функция для раскрытия/скрытия списка учеников
function toggleStudents(button, lessonId) {
    const list = document.getElementById(`students-${lessonId}`);
    if (list) {
        const isShown = list.classList.contains('show');
        list.classList.toggle('show');

        const studentCount = list.children.length;
        button.textContent = isShown
            ? `👥 Ученики (${studentCount})`
            : `👥 Скрыть (${studentCount})`;
    }
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
