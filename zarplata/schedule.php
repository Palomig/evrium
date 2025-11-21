<?php
/**
 * Страница расписания (Табличная структура с кабинетами)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить преподавателей с их формулами оплаты
try {
    $teachers = dbQuery("
        SELECT t.id, t.name, t.formula_id, pf.name as formula_name
        FROM teachers t
        LEFT JOIN payment_formulas pf ON t.formula_id = pf.id
        WHERE t.active = 1
        ORDER BY t.name
    ", []);
} catch (PDOException $e) {
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

// Получить все активные шаблоны расписания с кабинетами
try {
    $templates = dbQuery(
        "SELECT lt.*, COALESCE(t.display_name, t.name) as teacher_name,
                COALESCE(t_pf.name, lt_pf.name) as formula_name
         FROM lessons_template lt
         LEFT JOIN teachers t ON lt.teacher_id = t.id
         LEFT JOIN payment_formulas t_pf ON t.formula_id_group = t_pf.id
         LEFT JOIN payment_formulas lt_pf ON lt.formula_id = lt_pf.id
         WHERE lt.active = 1
         ORDER BY lt.day_of_week ASC, lt.time_start ASC",
        []
    );
} catch (PDOException $e) {
    $templates = dbQuery(
        "SELECT lt.*, COALESCE(t.display_name, t.name) as teacher_name, pf.name as formula_name
         FROM lessons_template lt
         LEFT JOIN teachers t ON lt.teacher_id = t.id
         LEFT JOIN payment_formulas pf ON lt.formula_id = pf.id
         WHERE lt.active = 1
         ORDER BY lt.day_of_week ASC, lt.time_start ASC",
        []
    );
}

// Добавляем поле room (кабинет) если его нет
// И определяем классы учеников для каждого урока
foreach ($templates as &$template) {
    if (!isset($template['room'])) {
        $template['room'] = 1; // По умолчанию кабинет 1
    }

    // Получаем классы учеников из базы данных
    $studentClasses = [];
    if ($template['students']) {
        $studentsNames = json_decode($template['students'], true);
        if (is_array($studentsNames) && !empty($studentsNames)) {
            // Получаем классы учеников по именам (БЕЗ фильтра по teacher_id,
            // так как теперь у ученика может быть несколько преподавателей)
            $placeholders = str_repeat('?,', count($studentsNames) - 1) . '?';

            $classesResult = dbQuery(
                "SELECT DISTINCT class FROM students
                 WHERE name IN ($placeholders) AND class IS NOT NULL AND active = 1
                 ORDER BY class ASC",
                $studentsNames
            );

            foreach ($classesResult as $row) {
                if ($row['class']) {
                    $studentClasses[] = $row['class'];
                }
            }
        }
    }

    // Добавляем строку с классами (уникальные, через запятую)
    $template['student_classes'] = !empty($studentClasses) ? implode(', ', array_unique($studentClasses)) : '';
}

define('PAGE_TITLE', '');
define('PAGE_SUBTITLE', '');
define('ACTIVE_PAGE', 'schedule');

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* Скрыть пустой page-header */
.page-header {
    display: none;
}

/* Заголовок */
.schedule-header {
    background-color: var(--md-surface);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--elevation-2);
    max-width: 100%;
    overflow: hidden;
}

.schedule-header-top {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    margin-bottom: 20px;
    gap: 16px;
    flex-wrap: wrap;
}

.schedule-legend {
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

/* Панель фильтров */
.filters-panel {
    background-color: var(--md-surface);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: var(--elevation-2);
    max-width: 100%;
    overflow: hidden;
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
.room-filter-btn,
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

.day-filter-btn:hover,
.room-filter-btn:hover {
    border-color: var(--md-primary);
    background-color: var(--md-surface-4);
}

.day-filter-btn.active,
.room-filter-btn.active {
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

/* Контейнер расписания */
.schedule-container {
    position: relative;
    overflow-x: auto;
    overflow-y: auto;
    background-color: var(--md-surface);
    border-radius: 12px;
    padding: 20px;
    box-shadow: var(--elevation-2);
    max-height: calc(100vh - 400px);
    width: 100%;
    box-sizing: border-box;
}

.schedule-board {
    display: flex;
    gap: 20px;
    min-width: fit-content;
}

/* Столбец дня - это ТАБЛИЦА */
.day-column {
    background-color: var(--md-surface-3);
    border-radius: 12px;
    min-width: 420px;
    max-width: 420px;
    width: 420px;
    flex-shrink: 0;
    box-shadow: var(--elevation-1);
    display: flex;
    flex-direction: column;
}

.day-column.hidden {
    display: none;
}

/* Заголовок дня */
.day-header {
    background-color: var(--md-surface-4);
    color: white;
    padding: 16px;
    border-radius: 12px 12px 0 0;
    text-align: center;
    font-weight: 700;
    font-size: 1rem;
    border-bottom: 2px solid rgba(255, 255, 255, 0.12);
}

/* Заголовки кабинетов */
.room-headers {
    display: grid;
    grid-template-columns: 60px repeat(3, 120px);
    background: var(--md-surface-4);
    border-bottom: 2px solid rgba(255, 255, 255, 0.12);
}

.room-header {
    padding: 12px 8px;
    text-align: center;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--text-medium-emphasis);
    border-right: 1px solid rgba(255, 255, 255, 0.08);
}

.room-header:last-child {
    border-right: none;
}

.room-header.time-label {
    background: var(--md-surface-3);
    color: var(--text-disabled);
    font-size: 0.75rem;
}

.room-header.hidden {
    display: none;
}

/* Контент дня */
.day-content {
    padding: 0;
    flex: 1;
    max-height: 70vh;
    overflow-y: auto;
}

/* Строка времени */
.time-row {
    display: grid;
    grid-template-columns: 60px repeat(3, 120px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    min-height: 60px;
}

.time-row:last-child {
    border-bottom: none;
}

/* Ячейка времени */
.time-cell {
    padding: 10px 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-medium-emphasis);
    background: var(--md-surface-3);
    border-right: 1px solid rgba(255, 255, 255, 0.08);
}

/* Ячейка кабинета */
.room-cell {
    padding: 6px;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: stretch;
}

.room-cell:last-child {
    border-right: none;
}

.room-cell.hidden {
    display: none;
}

/* Карточка урока - КОМПАКТНАЯ */
.lesson-card {
    background-color: var(--md-surface);
    border-radius: 6px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s var(--transition-standard);
    box-shadow: var(--elevation-1);
    border-left: 4px solid;
    width: 100%;
    display: flex;
    flex-direction: column;
}

.lesson-card:hover {
    transform: translateY(-1px);
    box-shadow: var(--elevation-2);
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

.card-body {
    padding: 0;
    flex: 1;
}

.card-table {
    width: 100%;
}

.card-row-tier {
    display: grid;
    grid-template-columns: auto 1fr;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    min-height: 32px;
}

.card-row-info {
    display: grid;
    grid-template-columns: 1fr;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    min-height: 28px;
}

.card-row-info:last-child {
    border-bottom: none;
}

.card-cell {
    padding: 6px 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    text-align: center;
}

.card-cell.tier-cell {
    padding: 6px;
    justify-content: center;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
}

.card-cell.capacity {
    font-weight: 700;
    font-size: 0.85rem;
}

.card-cell.capacity.available {
    color: #55cc77;
}

.card-cell.capacity.full {
    color: #ff5555;
}

.card-cell.grades {
    color: #88bbff;
    font-size: 0.75rem;
}

.card-cell.teacher {
    color: var(--text-high-emphasis);
    font-size: 0.75rem;
}

.tier-badge {
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 700;
    display: inline-block;
}

.tier-S { background: #ff9999; color: #000; }
.tier-A { background: #ffcc99; color: #000; }
.tier-B { background: #ffff99; color: #000; }
.tier-C { background: #ccff99; color: #000; }
.tier-D { background: #99ff99; color: #000; }

.spoiler-btn {
    width: 100%;
    padding: 6px;
    background: rgba(255, 255, 255, 0.05);
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    color: #55cc77;
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    transition: all 0.2s var(--transition-standard);
    text-align: center;
}

.spoiler-btn:hover {
    background: rgba(85, 204, 119, 0.1);
}

.students-list {
    display: none;
    padding: 8px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    max-height: 100px;
    overflow-y: auto;
}

.students-list.show {
    display: block;
}

.student-name {
    font-size: 0.7rem;
    color: var(--text-medium-emphasis);
    padding: 2px 6px;
    border-left: 2px solid rgba(187, 134, 252, 0.3);
    margin-bottom: 2px;
}

.student-name:last-child {
    margin-bottom: 0;
}

/* Пустой слот */
.empty-slot {
    width: 100%;
    height: 100%;
    min-height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-disabled);
    font-size: 0.75rem;
    border: 2px dashed rgba(255, 255, 255, 0.12);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s var(--transition-standard);
}

.empty-slot:hover {
    border-color: var(--md-primary);
    color: var(--md-primary);
    background-color: rgba(187, 134, 252, 0.05);
}

.empty-slot .material-icons {
    font-size: 24px;
}

/* Скроллбары */
.schedule-container::-webkit-scrollbar,
.day-content::-webkit-scrollbar,
.students-list::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}

.schedule-container::-webkit-scrollbar-track,
.day-content::-webkit-scrollbar-track,
.students-list::-webkit-scrollbar-track {
    background: var(--md-background);
    border-radius: 10px;
}

.schedule-container::-webkit-scrollbar-thumb,
.day-content::-webkit-scrollbar-thumb,
.students-list::-webkit-scrollbar-thumb {
    background: var(--md-surface-4);
    border-radius: 10px;
}

.schedule-container::-webkit-scrollbar-thumb:hover,
.day-content::-webkit-scrollbar-thumb:hover,
.students-list::-webkit-scrollbar-thumb:hover {
    background: var(--md-surface-5);
}

}
</style>

<!-- Заголовок -->
<div class="schedule-header">
    <div class="schedule-header-top">
        <button class="btn btn-primary" onclick="openTemplateModal()">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Добавить занятие
        </button>
    </div>

    <div class="schedule-legend">
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
            <span class="legend-label">Тиры:</span>
            <span class="tier-badge tier-S">S</span>
            <span class="tier-badge tier-A">A</span>
            <span class="tier-badge tier-B">B</span>
            <span class="tier-badge tier-C">C</span>
            <span class="tier-badge tier-D">D</span>
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
            <span class="legend-label">Преподаватель:</span>
            <select id="teacherFilter" class="time-filter-select" onchange="applyTeacherFilter()" style="min-width: 200px;">
                <option value="">Все преподаватели</option>
                <?php foreach ($teachers as $teacher): ?>
                    <option value="<?= $teacher['id'] ?>"><?= e($teacher['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-divider"></div>

        <div class="filter-group">
            <span class="legend-label">Кабинеты:</span>
            <button class="room-filter-btn active" data-room="1" onclick="toggleRoomFilter(this)">1</button>
            <button class="room-filter-btn active" data-room="2" onclick="toggleRoomFilter(this)">2</button>
            <button class="room-filter-btn active" data-room="3" onclick="toggleRoomFilter(this)">3</button>
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

<!-- Таблица расписания -->
<div class="schedule-container">
    <div class="schedule-board" id="scheduleBoard">
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

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="template-room">Кабинет *</label>
                    <select id="template-room" name="room" required>
                        <option value="">Выберите кабинет</option>
                        <option value="1">Кабинет 1</option>
                        <option value="2">Кабинет 2</option>
                        <option value="3">Кабинет 3</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <!-- Пустое место для симметрии -->
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
                <button type="button" class="btn btn-danger" id="delete-template-btn" onclick="deleteTemplate()" style="display: none;">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">delete_outline</span>
                    Удалить
                </button>
                <button type="submit" class="btn btn-primary" id="save-template-btn">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                    Сохранить
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Данные из PHP
const templatesData = <?= json_encode($templates, JSON_UNESCAPED_UNICODE) ?>;
const teachersData = <?= json_encode($teachers, JSON_UNESCAPED_UNICODE) ?>;

console.log('=== SCHEDULE PAGE LOADED ===');
console.log('Total templates loaded:', templatesData.length);
console.log('Templates data:', templatesData);

const daysOfWeek = [
    { id: 1, name: 'Понедельник', short: 'Пн' },
    { id: 2, name: 'Вторник', short: 'Вт' },
    { id: 3, name: 'Среда', short: 'Ср' },
    { id: 4, name: 'Четверг', short: 'Чт' },
    { id: 5, name: 'Пятница', short: 'Пт' },
    { id: 6, name: 'Суббота', short: 'Сб' },
    { id: 7, name: 'Воскресенье', short: 'Вс' }
];

const rooms = [1, 2, 3];

// Получить временные слоты для дня
function getTimeSlots(dayLessons) {
    if (dayLessons.length === 0) return [];

    const times = dayLessons.map(l => l.time_start.substring(0, 5)).sort();
    const uniqueTimes = [...new Set(times)]; // Убираем дубликаты
    console.log('getTimeSlots - all times:', times, 'unique:', uniqueTimes);

    const firstTime = uniqueTimes[0];
    const lastTime = uniqueTimes[uniqueTimes.length - 1];
    console.log('getTimeSlots - range:', firstTime, 'to', lastTime);

    const allTimes = [];
    for (let h = 8; h <= 21; h++) {
        const time = String(h).padStart(2, '0') + ':00';
        if (time >= firstTime && time <= lastTime) {
            allTimes.push(time);
        }
    }

    console.log('getTimeSlots - generated slots:', allTimes);
    return allTimes;
}

// Отрисовать расписание
function renderSchedule() {
    const board = document.getElementById('scheduleBoard');
    board.innerHTML = '';

    daysOfWeek.forEach(day => {
        const dayColumn = document.createElement('div');
        dayColumn.className = 'day-column';
        dayColumn.dataset.day = day.id;

        // Заголовок дня
        const header = document.createElement('div');
        header.className = 'day-header';
        header.textContent = day.name;

        // Заголовки кабинетов
        const roomHeaders = document.createElement('div');
        roomHeaders.className = 'room-headers';
        roomHeaders.innerHTML = `
            <div class="room-header time-label">Время</div>
            <div class="room-header" data-room="1">Кабинет 1</div>
            <div class="room-header" data-room="2">Кабинет 2</div>
            <div class="room-header" data-room="3">Кабинет 3</div>
        `;

        // Контент дня
        const content = document.createElement('div');
        content.className = 'day-content';

        // Получить уроки для этого дня
        const dayLessons = templatesData.filter(t => parseInt(t.day_of_week) === day.id);
        console.log(`Day ${day.name} (${day.id}): found ${dayLessons.length} lessons`, dayLessons.map(l => `${l.time_start} teacher:${l.teacher_id} room:${l.room}`));
        const timeSlots = getTimeSlots(dayLessons);

        if (timeSlots.length > 0) {
            // Есть уроки - показываем заголовки кабинетов и таблицу
            dayColumn.appendChild(header);
            dayColumn.appendChild(roomHeaders);

            timeSlots.forEach(time => {
                const timeRow = document.createElement('div');
                timeRow.className = 'time-row';
                timeRow.dataset.time = time;

                // Ячейка времени
                const timeCell = document.createElement('div');
                timeCell.className = 'time-cell';
                timeCell.textContent = time;
                timeRow.appendChild(timeCell);

                // Ячейки кабинетов
                rooms.forEach(roomNum => {
                    const roomCell = document.createElement('div');
                    roomCell.className = 'room-cell';
                    roomCell.dataset.room = roomNum;

                    // Найти урок для этого времени и кабинета
                    const lessonInRoom = dayLessons.find(l =>
                        l.time_start.substring(0, 5) === time && parseInt(l.room) === roomNum
                    );
                    console.log(`  Time ${time}, Room ${roomNum}: ${lessonInRoom ? 'found lesson teacher:' + lessonInRoom.teacher_id : 'empty'}`);

                    if (lessonInRoom) {
                        const card = createLessonCard(lessonInRoom);
                        roomCell.appendChild(card);
                    } else {
                        const emptySlot = document.createElement('div');
                        emptySlot.className = 'empty-slot';
                        emptySlot.innerHTML = '<span class="material-icons">add_circle_outline</span>';
                        emptySlot.onclick = () => openTemplateModal(day.id, time, roomNum);
                        roomCell.appendChild(emptySlot);
                    }

                    timeRow.appendChild(roomCell);
                });

                content.appendChild(timeRow);
            });
        } else {
            // Нет уроков в этот день - показываем только заголовок и сообщение
            dayColumn.appendChild(header);

            const emptyMsg = document.createElement('div');
            emptyMsg.style.padding = '40px 20px';
            emptyMsg.style.textAlign = 'center';
            emptyMsg.style.color = 'var(--text-disabled)';
            emptyMsg.textContent = 'Нет занятий';
            content.appendChild(emptyMsg);
        }

        dayColumn.appendChild(content);
        board.appendChild(dayColumn);
    });
}

// Создать карточку урока
function createLessonCard(lesson) {
    const card = document.createElement('div');
    card.className = `lesson-card ${lesson.subject || ''}`;
    card.dataset.teacherId = lesson.teacher_id;
    card.onclick = () => viewTemplate(lesson);

    // Парсим учеников
    let students = [];
    if (lesson.students) {
        try {
            students = typeof lesson.students === 'string' ? JSON.parse(lesson.students) : lesson.students;
        } catch (e) {
            students = lesson.students.split('\n').filter(s => s.trim());
        }
    }

    const currentStudents = students.length || 0;
    const maxStudents = lesson.expected_students || 6;
    const isFull = currentStudents >= maxStudents;
    const capacityClass = isFull ? 'full' : 'available';
    const tier = lesson.tier || 'C';
    // Используем student_classes из базы данных (классы реальных учеников)
    const studentClasses = lesson.student_classes || '';

    card.innerHTML = `
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
                ${studentClasses ? `
                <div class="card-row-info">
                    <div class="card-cell grades">${escapeHtml(studentClasses)} кл.</div>
                </div>
                ` : ''}
                <div class="card-row-info">
                    <div class="card-cell teacher">${escapeHtml(lesson.teacher_name || '—')}</div>
                </div>
            </div>
        </div>
    `;

    return card;
}

// Функции для фильтров
function toggleDayFilter(button) {
    button.classList.toggle('active');
    updateVisibleDays();
    saveFilters();
}

function updateVisibleDays() {
    const activeDays = Array.from(document.querySelectorAll('.day-filter-btn.active'))
        .map(btn => parseInt(btn.dataset.day));

    document.querySelectorAll('.day-column').forEach(col => {
        const day = parseInt(col.dataset.day);
        if (activeDays.length === 0 || activeDays.includes(day)) {
            col.classList.remove('hidden');
        } else {
            col.classList.add('hidden');
        }
    });
}

function toggleRoomFilter(button) {
    button.classList.toggle('active');
    updateVisibleRooms();
    saveFilters();
}

function updateVisibleRooms() {
    const activeRooms = Array.from(document.querySelectorAll('.room-filter-btn.active'))
        .map(btn => parseInt(btn.dataset.room));

    // Обновляем заголовки кабинетов
    document.querySelectorAll('.room-header[data-room]').forEach(header => {
        const room = parseInt(header.dataset.room);
        if (activeRooms.length === 0 || activeRooms.includes(room)) {
            header.classList.remove('hidden');
        } else {
            header.classList.add('hidden');
        }
    });

    // Обновляем ячейки кабинетов
    document.querySelectorAll('.room-cell').forEach(cell => {
        const room = parseInt(cell.dataset.room);
        if (activeRooms.length === 0 || activeRooms.includes(room)) {
            cell.classList.remove('hidden');
        } else {
            cell.classList.add('hidden');
        }
    });

    // Обновляем сетку
    const visibleCount = activeRooms.length === 0 ? 3 : activeRooms.length;
    const gridTemplate = `60px repeat(${visibleCount}, 120px)`;

    document.querySelectorAll('.room-headers, .time-row').forEach(elem => {
        elem.style.gridTemplateColumns = gridTemplate;
    });

    // Обновляем ширину столбцов
    const columnWidth = 60 + (visibleCount * 120);
    document.querySelectorAll('.day-column').forEach(col => {
        col.style.minWidth = `${columnWidth}px`;
        col.style.maxWidth = `${columnWidth}px`;
        col.style.width = `${columnWidth}px`;
    });
}

function applyTimeRange() {
    const timeFrom = document.getElementById('timeFrom').value;
    const timeTo = document.getElementById('timeTo').value;

    document.querySelectorAll('.time-row').forEach(row => {
        const rowTime = row.dataset.time;
        let shouldShow = true;

        if (timeFrom && rowTime < timeFrom) shouldShow = false;
        if (timeTo && rowTime > timeTo) shouldShow = false;

        row.style.display = shouldShow ? 'grid' : 'none';
    });

    saveFilters();
}

function applyTeacherFilter() {
    const selectedTeacherId = document.getElementById('teacherFilter').value;

    document.querySelectorAll('.lesson-card').forEach(card => {
        const teacherId = card.dataset.teacherId;

        if (!selectedTeacherId || teacherId === selectedTeacherId) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });

    saveFilters();
}

function resetFilters() {
    document.querySelectorAll('.day-filter-btn, .room-filter-btn').forEach(btn => {
        btn.classList.add('active');
    });
    updateVisibleDays();
    updateVisibleRooms();

    document.getElementById('timeFrom').value = '';
    document.getElementById('timeTo').value = '';
    document.getElementById('teacherFilter').value = '';

    document.querySelectorAll('.time-row').forEach(row => {
        row.style.display = 'grid';
    });

    document.querySelectorAll('.lesson-card').forEach(card => {
        card.style.display = 'block';
    });

    saveFilters();
}

// ========== СОХРАНЕНИЕ И ВОССТАНОВЛЕНИЕ ФИЛЬТРОВ ==========

function saveFilters() {
    const filters = {
        days: Array.from(document.querySelectorAll('.day-filter-btn.active'))
            .map(btn => btn.dataset.day),
        rooms: Array.from(document.querySelectorAll('.room-filter-btn.active'))
            .map(btn => btn.dataset.room),
        timeFrom: document.getElementById('timeFrom').value,
        timeTo: document.getElementById('timeTo').value,
        teacher: document.getElementById('teacherFilter').value
    };

    localStorage.setItem('scheduleFilters', JSON.stringify(filters));
}

function restoreFilters() {
    const savedFilters = localStorage.getItem('scheduleFilters');

    if (!savedFilters) {
        return; // Нет сохраненных фильтров
    }

    try {
        const filters = JSON.parse(savedFilters);

        // Восстанавливаем дни
        if (filters.days && filters.days.length > 0) {
            document.querySelectorAll('.day-filter-btn').forEach(btn => {
                if (filters.days.includes(btn.dataset.day)) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            updateVisibleDays();
        }

        // Восстанавливаем кабинеты
        if (filters.rooms && filters.rooms.length > 0) {
            document.querySelectorAll('.room-filter-btn').forEach(btn => {
                if (filters.rooms.includes(btn.dataset.room)) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            updateVisibleRooms();
        }

        // Восстанавливаем время
        if (filters.timeFrom) {
            document.getElementById('timeFrom').value = filters.timeFrom;
        }
        if (filters.timeTo) {
            document.getElementById('timeTo').value = filters.timeTo;
        }
        applyTimeRange();

        // Восстанавливаем преподавателя
        if (filters.teacher) {
            document.getElementById('teacherFilter').value = filters.teacher;
            applyTeacherFilter();
        }

    } catch (e) {
        console.error('Ошибка восстановления фильтров:', e);
        localStorage.removeItem('scheduleFilters');
    }
}

function toggleStudents(button, lessonId) {
    const list = document.getElementById(`students-${lessonId}`);
    if (list) {
        const isShown = list.classList.contains('show');
        list.classList.toggle('show');

        const count = list.children.length;
        button.textContent = isShown ? `👥 (${count})` : `👥 Скрыть`;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    renderSchedule();
    // Восстанавливаем сохраненные фильтры после рендеринга
    restoreFilters();
});
</script>

<script src="/zarplata/assets/js/schedule.js"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
