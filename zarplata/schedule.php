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
/* Fonts */
body, .schedule-header, .filters-panel, .day-filter-btn, .room-filter-btn, 
.time-filter-select, select, button, .modal-content {
    font-family: 'Nunito', sans-serif;
}

.time-cell, .card-cell.capacity, .money {
    font-family: 'JetBrains Mono', monospace;
}

/* Скрыть пустой page-header */
.page-header {
    display: none;
}

/* Запретить скролл всей страницы */
body {
    overflow: hidden;
}

/* Основной контейнер */
.main-content {
    height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Заголовок */
.schedule-header {
    background-color: var(--bg-card);
    border-radius: 12px;
    padding: 16px 24px;
    margin-bottom: 16px;
    /* box-shadow removed */
    max-width: 100%;
    overflow: hidden;
    flex-shrink: 0;
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
    padding: 12px 16px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 10px;
}

.legend-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

.legend-label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
}

.legend-divider {
    width: 1px;
    height: 24px;
    background: var(--border);
}

/* Панель фильтров */
.filters-panel {
    background-color: var(--bg-card);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    /* box-shadow removed */
    max-width: 100%;
    overflow: hidden;
    flex-shrink: 0;
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
    border: 2px solid var(--border);
    border-radius: 8px;
    background-color: var(--bg-elevated);
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    transition: all 0.2s var(--transition-standard);
    user-select: none;
}

.day-filter-btn:hover,
.room-filter-btn:hover {
    border-color: var(--accent);
    background-color: var(--bg-hover);
}

.day-filter-btn.active,
.room-filter-btn.active {
    background-color: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
}

.time-filter-select {
    min-width: 100px;
    padding-right: 40px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2314b8a6' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 20px;
    appearance: none;
}

.filter-divider {
    width: 1px;
    height: 32px;
    background: var(--border);
    margin: 0 8px;
}

.btn-reset-filters {
    padding: 10px 16px;
    border: 2px solid #f43f5e;
    border-radius: 8px;
    background: transparent;
    color: #f43f5e;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    transition: all 0.2s var(--transition-standard);
}

.btn-reset-filters:hover {
    background-color: #f43f5e;
    color: white;
}

/* Контейнер расписания */
.schedule-container {
    position: relative;
    overflow-x: auto;
    overflow-y: auto;
    background-color: var(--bg-card);
    border-radius: 12px;
    padding: 20px;
    /* box-shadow removed */
    width: 100%;
    box-sizing: border-box;
    flex: 1;
    min-height: 0;
}

.schedule-board {
    display: flex;
    gap: 20px;
    min-width: fit-content;
}

/* Столбец дня - это ТАБЛИЦА */
.day-column {
    background-color: var(--bg-elevated);
    border-radius: 12px;
    min-width: 420px;
    max-width: 420px;
    width: 420px;
    flex-shrink: 0;
    /* box-shadow removed */
    display: flex;
    flex-direction: column;
}

.day-column.hidden {
    display: none;
}

/* Заголовок дня */
.day-header {
    background-color: var(--bg-hover);
    color: white;
    padding: 16px;
    border-radius: 12px 12px 0 0;
    text-align: center;
    font-weight: 700;
    font-size: 1rem;
    border-bottom: 2px solid var(--border);
}

/* Заголовки кабинетов */
.room-headers {
    display: grid;
    grid-template-columns: 60px repeat(3, 120px);
    background: var(--bg-hover);
    border-bottom: 2px solid var(--border);
}

.room-header {
    padding: 12px 8px;
    text-align: center;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--text-secondary);
    border-right: 1px solid rgba(255, 255, 255, 0.08);
}

.room-header:last-child {
    border-right: none;
}

.room-header.time-label {
    background: var(--bg-elevated);
    color: var(--text-muted);
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
    color: var(--text-secondary);
    background: var(--bg-elevated);
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
    background-color: var(--bg-card);
    border-radius: 6px;
    overflow: hidden;
    cursor: move;
    cursor: grab;
    transition: all 0.3s var(--transition-standard);
    /* box-shadow removed */
    border-left: 4px solid;
    width: 100%;
    display: flex;
    flex-direction: column;
}

.lesson-card:hover {
    transform: translateY(-1px);
    /* box-shadow removed */
}

.lesson-card:active {
    cursor: grabbing;
}

/* Визуальные эффекты для drag and drop */
.lesson-card.dragging {
    opacity: 0.4;
    cursor: grabbing;
    transform: rotate(2deg);
}

.room-cell.drag-over {
    background-color: var(--accent-dim);
    border: 2px solid var(--accent);
    border-radius: 6px;
}

.empty-slot.drag-over {
    background-color: rgba(187, 134, 252, 0.2);
    border-color: var(--accent);
    border-style: solid;
}

.lesson-card.Математика {
    border-left-color: #3b82f6;
}

.lesson-card.Физика {
    border-left-color: #f43f5e;
}

.lesson-card.Информатика {
    border-left-color: #22c55e;
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
    color: #22c55e;
}

.card-cell.capacity.full {
    color: #f43f5e;
}

.card-cell.grades {
    color: #88bbff;
    font-size: 0.75rem;
}

.card-cell.teacher {
    color: var(--text-primary);
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
    color: #22c55e;
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
    color: var(--text-secondary);
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
    color: var(--text-muted);
    font-size: 0.75rem;
    border: 2px dashed var(--border);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s var(--transition-standard);
}

.empty-slot:hover {
    border-color: var(--accent);
    color: var(--accent);
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
    background: var(--bg-dark);
    border-radius: 10px;
}

.schedule-container::-webkit-scrollbar-thumb,
.day-content::-webkit-scrollbar-thumb,
.students-list::-webkit-scrollbar-thumb {
    background: var(--bg-hover);
    border-radius: 10px;
}

.schedule-container::-webkit-scrollbar-thumb:hover,
.day-content::-webkit-scrollbar-thumb:hover,
.students-list::-webkit-scrollbar-thumb:hover {
    background: var(--bg-hover);
}

/* Уведомления */
.notification {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background-color: var(--bg-card);
    color: var(--text-primary);
    padding: 16px 24px;
    border-radius: 8px;
    /* box-shadow removed */
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 10000;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s var(--transition-standard);
}

.notification.show {
    opacity: 1;
    transform: translateY(0);
}

.notification-success {
    border-left: 4px solid #22c55e;
}

.notification-error {
    border-left: 4px solid #f43f5e;
}

.notification-info {
    border-left: 4px solid var(--accent);
}

.notification .material-icons {
    font-size: 24px;
}

.notification-success .material-icons {
    color: #22c55e;
}

.notification-error .material-icons {
    color: #f43f5e;
}

.notification-info .material-icons {
    color: var(--accent);
}

}

/* Scrollbars */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-dark);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: var(--bg-hover);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}

/* ===========================
   Modal Window Styles (Teal Theme)
   =========================== */

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(4px);
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
    animation: modalFadeIn 0.2s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    max-width: 640px;
    width: 92%;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4);
    animation: modalSlideUp 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
}

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(24px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
    letter-spacing: -0.02em;
}

.modal-close {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}

.modal-close:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
    border-color: var(--text-muted);
}

.modal-close .material-icons {
    font-size: 20px;
}

#template-form {
    padding: 24px;
    overflow-y: auto;
    overflow-x: hidden;
    flex: 1 1 auto;
    min-height: 0;
}

#template-form::-webkit-scrollbar {
    width: 6px;
}

#template-form::-webkit-scrollbar-track {
    background: transparent;
}

#template-form::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}

#template-form::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}

.modal-actions {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    flex-shrink: 0;
}

.form-group {
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
}

.form-group small {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: var(--text-muted);
}

.form-row {
    display: flex;
    gap: 16px;
}

.form-row .form-group {
    flex: 1;
}

.modal input[type="text"],
.modal input[type="number"],
.modal input[type="tel"],
.modal input[type="email"],
.modal select,
.modal textarea {
    width: 100%;
    padding: 12px 14px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 14px;
    font-family: 'Nunito', sans-serif;
    transition: all 0.15s ease;
}

.modal input::placeholder,
.modal textarea::placeholder {
    color: var(--text-muted);
}

.modal input:hover,
.modal select:hover,
.modal textarea:hover {
    border-color: var(--text-muted);
    background: var(--bg-hover);
}

.modal input:focus,
.modal select:focus,
.modal textarea:focus {
    outline: none;
    border-color: var(--accent);
    background: var(--bg-hover);
    box-shadow: 0 0 0 3px var(--accent-dim);
}

.modal select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%238b95a5' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 40px;
    cursor: pointer;
}

.modal select:focus {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%2314b8a6' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
}

.modal textarea {
    resize: vertical;
    min-height: 80px;
    line-height: 1.5;
}

.time-buttons,
.subject-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.time-btn,
.subject-btn {
    padding: 10px 16px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-secondary);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.time-btn {
    min-width: 52px;
    font-family: 'JetBrains Mono', monospace;
}

.time-btn:hover,
.subject-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
    border-color: var(--text-muted);
}

.time-btn.active,
.subject-btn.active {
    background: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    border: none;
}

.btn-primary {
    background: var(--accent);
    color: white;
}

.btn-primary:hover {
    background: var(--accent-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);
}

.btn-primary:active {
    transform: translateY(0);
}

.btn-text {
    background: transparent;
    color: var(--text-secondary);
    padding: 8px 12px;
}

.btn-text:hover {
    background: var(--bg-elevated);
    color: var(--text-primary);
}

.btn-danger {
    background: rgba(244, 63, 94, 0.15);
    color: #f43f5e;
    border: 1px solid #f43f5e;
}

.btn-danger:hover {
    background: #f43f5e;
    color: white;
}

@media (max-width: 640px) {
    .modal-content {
        width: 95%;
        max-height: 95vh;
        border-radius: 12px;
    }

    .modal-header {
        padding: 16px 20px;
    }

    #template-form {
        padding: 20px;
    }

    .modal-actions {
        padding: 12px 20px;
        flex-direction: column;
    }

    .modal-actions .btn {
        width: 100%;
    }

    .form-row {
        flex-direction: column;
        gap: 0;
    }

    .time-buttons {
        justify-content: center;
    }
}

/* ===========================
   Lesson Info Modal (NEW DESIGN)
   =========================== */

.lesson-info-modal {
    background: #252a34;
    border-radius: 20px;
    max-width: 440px;
    width: 90%;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    animation: modalAppear 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
}

@keyframes modalAppear {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Цветовая полоска сверху */
.lesson-color-bar {
    height: 4px;
    width: 100%;
}

/* Шапка */
.lesson-info-header {
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.lesson-subject-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
}

.lesson-subject-badge .material-icons {
    font-size: 18px;
}

.lesson-close-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #e5e7eb;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.lesson-close-btn:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: scale(1.05);
}

.lesson-close-btn .material-icons {
    font-size: 20px;
}

/* Тип урока */
.lesson-type-section {
    padding: 0 20px 20px 20px;
}

.lesson-type-label {
    font-size: 11px;
    font-weight: 600;
    color: #9ca3af;
    letter-spacing: 0.05em;
    margin-bottom: 4px;
}

.lesson-type-value {
    font-size: 24px;
    font-weight: 700;
    color: #ffffff;
}

/* Информационная сетка 2×2 */
.lesson-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    padding: 0 20px 20px 20px;
}

.lesson-info-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.lesson-info-icon {
    font-size: 20px;
    color: #14b8a6;
}

.lesson-info-label {
    font-size: 11px;
    font-weight: 600;
    color: #9ca3af;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.lesson-info-value {
    font-size: 14px;
    font-weight: 600;
    color: #ffffff;
}

/* Секция учеников */
.lesson-students-section {
    padding: 0 20px 20px 20px;
}

.lesson-students-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    font-size: 16px;
    font-weight: 600;
    color: #ffffff;
}

.lesson-students-header .material-icons {
    font-size: 22px;
    color: #14b8a6;
}

.lesson-students-badge {
    margin-left: auto;
    background: rgba(20, 184, 166, 0.15);
    color: #14b8a6;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
}

.lesson-students-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.lesson-student-card {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    padding: 10px;
}

.lesson-student-class {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 700;
    color: #ffffff;
    flex-shrink: 0;
}

.lesson-student-name {
    font-size: 13px;
    font-weight: 500;
    color: #e5e7eb;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lesson-no-students {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 32px;
    color: #6b7280;
}

.lesson-no-students .material-icons {
    font-size: 48px;
    opacity: 0.3;
}

/* Футер */
.lesson-info-footer {
    padding: 16px 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.lesson-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.lesson-btn .material-icons {
    font-size: 18px;
}

.lesson-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: #d1d5db;
}

.lesson-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
}

.lesson-btn-primary {
    background: linear-gradient(135deg, #14b8a6, #0d9488);
    color: #ffffff;
}

.lesson-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px -10px rgba(20, 184, 166, 0.5);
}

@media (max-width: 480px) {
    .lesson-info-modal {
        max-width: 95%;
    }

    .lesson-students-grid {
        grid-template-columns: 1fr;
    }

    .lesson-info-footer {
        flex-direction: column;
    }

    .lesson-btn {
        width: 100%;
    }
}
</style>

<!-- Заголовок -->
<div class="schedule-header">
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
            <button class="day-filter-btn" data-day="1" onclick="toggleDayFilter(this)">Пн</button>
            <button class="day-filter-btn" data-day="2" onclick="toggleDayFilter(this)">Вт</button>
            <button class="day-filter-btn" data-day="3" onclick="toggleDayFilter(this)">Ср</button>
            <button class="day-filter-btn" data-day="4" onclick="toggleDayFilter(this)">Чт</button>
            <button class="day-filter-btn" data-day="5" onclick="toggleDayFilter(this)">Пт</button>
            <button class="day-filter-btn" data-day="6" onclick="toggleDayFilter(this)">Сб</button>
            <button class="day-filter-btn" data-day="7" onclick="toggleDayFilter(this)">Вс</button>
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
                    <span class="material-icons" style="font-size: 18px; color: var(--accent);">payments</span>
                    Формула оплаты
                </label>
                <div style="padding: 12px; background-color: rgba(3, 218, 198, 0.1); border-left: 3px solid var(--accent); border-radius: 4px;">
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

    // ВАЖНО: Инициализируем drag and drop СРАЗУ после рендеринга карточек
    if (typeof initDragAndDrop === 'function') {
        initDragAndDrop();
    }
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
    // Убираем active со всех кнопок фильтров
    document.querySelectorAll('.day-filter-btn, .room-filter-btn').forEach(btn => {
        btn.classList.remove('active');
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
        // Если нет сохраненных фильтров, показываем все дни и кабинеты
        updateVisibleDays();
        updateVisibleRooms();
        return;
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

// renderSchedule и restoreFilters вызываются из schedule.js после его загрузки
</script>

<script src="/zarplata/assets/js/schedule.js"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
