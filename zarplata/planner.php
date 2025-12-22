<?php
/**
 * Планировщик расписания учеников
 * Drag & drop интерфейс для перемещения учеников между днями/временем/кабинетами
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/student_helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить всех активных учеников с расписанием
$students = dbQuery("
    SELECT s.id, s.name, s.class, s.tier, s.teacher_id, s.schedule,
           t.name as teacher_name
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE s.active = 1
    ORDER BY s.name
", []);

// Получить всех преподавателей
$teachers = dbQuery("
    SELECT id, name, display_name
    FROM teachers
    WHERE active = 1
    ORDER BY name
", []);

// Построить структуру данных для отображения
$scheduleGrid = [];
$daysOfWeek = [
    1 => ['name' => 'Понедельник', 'short' => 'Пн'],
    2 => ['name' => 'Вторник', 'short' => 'Вт'],
    3 => ['name' => 'Среда', 'short' => 'Ср'],
    4 => ['name' => 'Четверг', 'short' => 'Чт'],
    5 => ['name' => 'Пятница', 'short' => 'Пт'],
    6 => ['name' => 'Суббота', 'short' => 'Сб'],
    7 => ['name' => 'Воскресенье', 'short' => 'Вс']
];

// Собираем студентов по ячейкам расписания
foreach ($students as $student) {
    if (!$student['schedule']) continue;

    $schedule = json_decode($student['schedule'], true);
    if (!is_array($schedule)) continue;

    foreach ($schedule as $dayKey => $daySchedule) {
        $day = (int)$dayKey;
        if ($day < 1 || $day > 7) continue;

        if (is_array($daySchedule)) {
            if (isset($daySchedule[0]) && is_array($daySchedule[0])) {
                foreach ($daySchedule as $slot) {
                    $time = substr($slot['time'] ?? '00:00', 0, 5);
                    $room = (int)($slot['room'] ?? 1);
                    $subject = $slot['subject'] ?? 'Мат.';

                    $key = "{$day}_{$time}_{$room}";
                    if (!isset($scheduleGrid[$key])) {
                        $scheduleGrid[$key] = [
                            'day' => $day,
                            'time' => $time,
                            'room' => $room,
                            'subject' => $subject,
                            'students' => []
                        ];
                    }
                    $scheduleGrid[$key]['students'][] = [
                        'id' => $student['id'],
                        'name' => $student['name'],
                        'class' => $student['class'],
                        'tier' => $student['tier'] ?? 'C',
                        'teacher_id' => $student['teacher_id'],
                        'teacher_name' => $student['teacher_name']
                    ];
                }
            }
            elseif (isset($daySchedule['time'])) {
                $time = substr($daySchedule['time'], 0, 5);
                $room = (int)($daySchedule['room'] ?? 1);
                $subject = $daySchedule['subject'] ?? 'Мат.';

                $key = "{$day}_{$time}_{$room}";
                if (!isset($scheduleGrid[$key])) {
                    $scheduleGrid[$key] = [
                        'day' => $day,
                        'time' => $time,
                        'room' => $room,
                        'subject' => $subject,
                        'students' => []
                    ];
                }
                $scheduleGrid[$key]['students'][] = [
                    'id' => $student['id'],
                    'name' => $student['name'],
                    'class' => $student['class'],
                    'tier' => $student['tier'] ?? 'C',
                    'teacher_id' => $student['teacher_id'],
                    'teacher_name' => $student['teacher_name']
                ];
            }
        }
        elseif (is_string($daySchedule)) {
            $time = substr($daySchedule, 0, 5);
            $room = 1;

            $key = "{$day}_{$time}_{$room}";
            if (!isset($scheduleGrid[$key])) {
                $scheduleGrid[$key] = [
                    'day' => $day,
                    'time' => $time,
                    'room' => $room,
                    'subject' => 'Математика',
                    'students' => []
                ];
            }
            $scheduleGrid[$key]['students'][] = [
                'id' => $student['id'],
                'name' => $student['name'],
                'class' => $student['class'],
                'tier' => $student['tier'] ?? 'C',
                'teacher_id' => $student['teacher_id'],
                'teacher_name' => $student['teacher_name']
            ];
        }
    }
}

define('PAGE_TITLE', '');
define('PAGE_SUBTITLE', '');
define('ACTIVE_PAGE', 'planner');

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* Fonts */
body, .filters-panel, .day-filter-btn, .room-filter-btn, select, button {
    font-family: 'Nunito', sans-serif;
}

.time-cell {
    font-family: 'JetBrains Mono', monospace;
}

/* Скрыть стандартный page-header */
.page-header {
    display: none !important;
}

/* Запретить скролл всей страницы */
body {
    overflow: hidden;
}

/* ========== СВОРАЧИВАЕМЫЙ SIDEBAR ========== */
.sidebar {
    transition: width 0.3s ease, min-width 0.3s ease;
}

.sidebar.collapsed {
    width: 60px !important;
    min-width: 60px !important;
}

.sidebar.collapsed .logo-text,
.sidebar.collapsed .nav-label,
.sidebar.collapsed .nav-item span {
    display: none;
}

.sidebar.collapsed .logo {
    justify-content: center;
    padding: 16px 8px;
}

.sidebar.collapsed .nav-item {
    justify-content: center;
    padding: 12px;
}

.sidebar.collapsed .nav-icon {
    margin-right: 0;
}

/* Кнопка сворачивания sidebar */
.sidebar-toggle {
    position: absolute;
    top: 12px;
    right: -14px;
    width: 28px;
    height: 28px;
    background: var(--accent);
    border: none;
    border-radius: 50%;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.sidebar-toggle:hover {
    transform: scale(1.1);
    background: var(--accent-hover);
}

.sidebar-toggle .material-icons {
    font-size: 18px;
    transition: transform 0.3s;
}

.sidebar.collapsed .sidebar-toggle .material-icons {
    transform: rotate(180deg);
}

/* ========== АДАПТАЦИЯ MAIN-CONTENT ПРИ СКРЫТОМ SIDEBAR ========== */
.layout {
    transition: all 0.3s ease;
}

/* Основной контейнер - переопределяем стили из teal-theme.css */
.main-content {
    height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
    padding: 12px 8px !important;
    margin-left: 220px !important;
    width: calc(100vw - 220px) !important;
    max-width: calc(100vw - 220px) !important;
    box-sizing: border-box !important;
}

.layout.sidebar-collapsed .main-content {
    margin-left: 60px !important;
    width: calc(100vw - 60px) !important;
    max-width: calc(100vw - 60px) !important;
}

/* Панель фильтров */
.filters-panel {
    background-color: var(--bg-card);
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 12px;
    flex-shrink: 0;
}

.filters-content {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    gap: 4px;
    align-items: center;
}

.filter-label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.8rem;
    margin-right: 4px;
}

.day-filter-btn,
.room-filter-btn {
    padding: 6px 12px;
    border: 2px solid var(--border);
    border-radius: 6px;
    background-color: var(--bg-elevated);
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.2s;
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

.toggle-btn {
    padding: 6px 12px;
    border: 2px solid var(--border);
    border-radius: 6px;
    background-color: var(--bg-elevated);
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 4px;
}

.toggle-btn:hover {
    border-color: var(--accent);
}

.toggle-btn.active {
    background-color: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
}

.filter-divider {
    width: 1px;
    height: 28px;
    background: var(--border);
    margin: 0 4px;
}

/* Контейнер расписания - два грида рядом */
.planner-container {
    position: relative;
    overflow: auto;
    background-color: var(--bg-card);
    border-radius: 12px;
    padding: 12px;
    flex: 1;
    min-height: 0;
    width: 100%;
    box-sizing: border-box;
}

.planner-wrapper {
    display: flex;
    gap: 16px;
    width: 100%;
    min-width: 100%;
}

.planner-section {
    flex: 1;
    min-width: 0;
    width: 100%;
}

.planner-section#weekdaysSection {
    flex: 5; /* 5 дней */
}

.planner-section#weekendsSection {
    flex: 2; /* 2 дня */
}

.section-title {
    text-align: center;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--accent);
    margin-bottom: 8px;
    padding: 6px;
    background: var(--bg-elevated);
    border-radius: 6px;
    width: 100%;
    box-sizing: border-box;
}

.planner-grid {
    display: grid;
    gap: 1px;
    background: var(--border);
    border-radius: 8px;
    overflow: hidden;
    width: 100%;
}

.planner-grid.weekdays {
    grid-template-columns: 50px repeat(5, 1fr);
}

.planner-grid.weekends {
    grid-template-columns: 50px repeat(2, 1fr);
}

/* Заголовки */
.grid-header {
    background: var(--bg-hover);
    padding: 10px 6px;
    text-align: center;
    font-weight: 700;
    font-size: 0.8rem;
    color: var(--text-primary);
}

.grid-header.time-header {
    background: var(--bg-elevated);
    color: var(--text-muted);
}

.grid-header.hidden {
    display: none;
}

/* Ячейки времени */
.time-cell {
    background: var(--bg-elevated);
    padding: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
}

/* Ячейки расписания */
.schedule-cell {
    background: #080a0e;
    min-height: 50px;
    padding: 3px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.schedule-cell:nth-child(even) {
    background: #0c0f14;
}

.schedule-cell.hidden {
    display: none;
}

.schedule-cell.drag-over {
    background: var(--accent-dim);
    outline: 2px dashed var(--accent);
}

/* Ячейка с кабинетами */
.rooms-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
    height: 100%;
}

.room-slot {
    background: #1a1f28;
    border: 1px solid #2d3544;
    border-radius: 4px;
    padding: 4px;
    min-height: 50px;
    display: flex;
    flex-direction: column;
    gap: 3px;
    transition: all 0.2s;
}

.room-slot.hidden {
    display: none;
}

.room-slot.drag-over {
    background: var(--accent-dim);
    outline: 2px dashed var(--accent);
}

.room-slot-header {
    font-size: 0.6rem;
    background: #2a3241;
    color: #9ca3af;
    text-align: center;
    padding: 2px;
    border-radius: 2px;
    margin-bottom: 2px;
}

/* Карточка ученика */
.student-card {
    border-radius: 4px;
    padding: 4px 6px;
    font-size: 0.75rem;
    cursor: grab;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}

.student-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.4);
}

.student-card:active {
    cursor: grabbing;
}

.student-card.dragging {
    opacity: 0.5;
    transform: rotate(2deg);
}

/* Цвета преподавателей */
.student-card.teacher-1 {
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.35) 0%, rgba(20, 184, 166, 0.15) 100%);
    border: 1px solid rgba(20, 184, 166, 0.6);
    border-left: 3px solid rgba(20, 184, 166, 0.9);
}
.student-card.teacher-1:hover {
    border-color: #14b8a6;
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.45) 0%, rgba(20, 184, 166, 0.25) 100%);
}

.student-card.teacher-2 {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.35) 0%, rgba(168, 85, 247, 0.15) 100%);
    border: 1px solid rgba(168, 85, 247, 0.6);
    border-left: 3px solid rgba(168, 85, 247, 0.9);
}
.student-card.teacher-2:hover {
    border-color: #a855f7;
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.45) 0%, rgba(168, 85, 247, 0.25) 100%);
}

.student-card.teacher-3 {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.35) 0%, rgba(59, 130, 246, 0.15) 100%);
    border: 1px solid rgba(59, 130, 246, 0.6);
    border-left: 3px solid rgba(59, 130, 246, 0.9);
}
.student-card.teacher-3:hover {
    border-color: #3b82f6;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.45) 0%, rgba(59, 130, 246, 0.25) 100%);
}

.student-card.teacher-4 {
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.35) 0%, rgba(249, 115, 22, 0.15) 100%);
    border: 1px solid rgba(249, 115, 22, 0.6);
    border-left: 3px solid rgba(249, 115, 22, 0.9);
}
.student-card.teacher-4:hover {
    border-color: #f97316;
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.45) 0%, rgba(249, 115, 22, 0.25) 100%);
}

.student-card.teacher-5 {
    background: linear-gradient(135deg, rgba(236, 72, 153, 0.35) 0%, rgba(236, 72, 153, 0.15) 100%);
    border: 1px solid rgba(236, 72, 153, 0.6);
    border-left: 3px solid rgba(236, 72, 153, 0.9);
}
.student-card.teacher-5:hover {
    border-color: #ec4899;
    background: linear-gradient(135deg, rgba(236, 72, 153, 0.45) 0%, rgba(236, 72, 153, 0.25) 100%);
}

.student-card.teacher-6 {
    background: linear-gradient(135deg, rgba(234, 179, 8, 0.35) 0%, rgba(234, 179, 8, 0.15) 100%);
    border: 1px solid rgba(234, 179, 8, 0.6);
    border-left: 3px solid rgba(234, 179, 8, 0.9);
}
.student-card.teacher-6:hover {
    border-color: #eab308;
    background: linear-gradient(135deg, rgba(234, 179, 8, 0.45) 0%, rgba(234, 179, 8, 0.25) 100%);
}

.student-card.teacher-7 {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.35) 0%, rgba(34, 197, 94, 0.15) 100%);
    border: 1px solid rgba(34, 197, 94, 0.6);
    border-left: 3px solid rgba(34, 197, 94, 0.9);
}
.student-card.teacher-7:hover {
    border-color: #22c55e;
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.45) 0%, rgba(34, 197, 94, 0.25) 100%);
}

.student-card.teacher-8 {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.35) 0%, rgba(239, 68, 68, 0.15) 100%);
    border: 1px solid rgba(239, 68, 68, 0.6);
    border-left: 3px solid rgba(239, 68, 68, 0.9);
}
.student-card.teacher-8:hover {
    border-color: #ef4444;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.45) 0%, rgba(239, 68, 68, 0.25) 100%);
}

/* Легенда преподавателей */
.teacher-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 8px 12px;
    background: #1a1f28;
    border-radius: 6px;
    margin-bottom: 12px;
}

.teacher-legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    color: #d1d5db;
}

.teacher-color-box {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.2);
}

.teacher-color-1 { background: rgba(20, 184, 166, 0.6); }
.teacher-color-2 { background: rgba(168, 85, 247, 0.6); }
.teacher-color-3 { background: rgba(59, 130, 246, 0.6); }
.teacher-color-4 { background: rgba(249, 115, 22, 0.6); }
.teacher-color-5 { background: rgba(236, 72, 153, 0.6); }
.teacher-color-6 { background: rgba(234, 179, 8, 0.6); }
.teacher-color-7 { background: rgba(34, 197, 94, 0.6); }
.teacher-color-8 { background: rgba(239, 68, 68, 0.6); }

.student-tier {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 4px;
    border-radius: 3px;
    flex-shrink: 0;
}

.student-tier.hidden {
    display: none;
}

.tier-S { background: #ff9999; color: #000; }
.tier-A { background: #ffcc99; color: #000; }
.tier-B { background: #ffff99; color: #000; }
.tier-C { background: #ccff99; color: #000; }
.tier-D { background: #99ff99; color: #000; }

.student-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #ecfdf5;
}

.student-class {
    color: #5eead4;
    font-weight: 600;
    flex-shrink: 0;
}

/* Уведомления */
.notification {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background-color: var(--bg-card);
    color: var(--text-primary);
    padding: 12px 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 10000;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s;
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

.notification .material-icons {
    font-size: 20px;
}

.notification-success .material-icons {
    color: #22c55e;
}

.notification-error .material-icons {
    color: #f43f5e;
}

/* Scrollbars */
.planner-container::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.planner-container::-webkit-scrollbar-track {
    background: var(--bg-dark);
    border-radius: 4px;
}

.planner-container::-webkit-scrollbar-thumb {
    background: var(--bg-hover);
    border-radius: 4px;
}

/* Счётчик учеников */
.student-count {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.student-count strong {
    color: var(--accent);
}

/* Пустая строка для выравнивания выходных */
.empty-row {
    background: var(--bg-card);
    opacity: 0.3;
    min-height: 50px;
}

/* ========== КНОПКА ДОБАВИТЬ УЧЕНИКА ========== */
.add-student-btn {
    margin-left: auto;
    padding: 8px 16px;
    background: var(--accent);
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    font-family: 'Nunito', sans-serif;
}

.add-student-btn:hover {
    background: var(--accent-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);
}

.add-student-btn .material-icons {
    font-size: 18px;
}

/* ========== МОДАЛЬНОЕ ОКНО ========== */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.modal-overlay.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card);
    border-radius: 16px;
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
}

.modal-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-primary);
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 4px;
    border-radius: 6px;
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.modal-body {
    padding: 24px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.form-group select,
.modal-body select {
    width: 100%;
    padding: 10px 12px;
    background: var(--bg-elevated);
    border: 2px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.9rem;
    font-family: 'Nunito', sans-serif;
    cursor: pointer;
    transition: all 0.2s;
}

.form-group select:hover,
.modal-body select:hover {
    border-color: var(--accent);
}

.form-group select:focus,
.modal-body select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.2);
}

.form-group select:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Schedule section */
.schedule-section {
    margin-top: 20px;
}

.schedule-section > label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 12px;
}

.days-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.day-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: var(--bg-elevated);
    border-radius: 8px;
    border: 1px solid var(--border);
    transition: all 0.2s;
}

.day-row:hover {
    border-color: var(--accent);
}

.day-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    min-width: 130px;
}

.day-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--accent);
    cursor: pointer;
}

.day-name {
    font-size: 0.9rem;
    color: var(--text-primary);
}

.day-options {
    display: flex;
    gap: 8px;
    flex: 1;
    opacity: 0.4;
    transition: opacity 0.2s;
}

.day-options.active {
    opacity: 1;
}

.day-options select {
    padding: 6px 10px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 0.85rem;
    font-family: 'JetBrains Mono', monospace;
}

.time-select {
    width: 90px;
}

.room-select {
    width: 80px;
}

/* Modal footer */
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    background: var(--bg-elevated);
    border-radius: 0 0 16px 16px;
}

.btn-cancel,
.btn-save {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: 'Nunito', sans-serif;
}

.btn-cancel {
    background: transparent;
    border: 2px solid var(--border);
    color: var(--text-secondary);
}

.btn-cancel:hover {
    border-color: var(--text-secondary);
    color: var(--text-primary);
}

.btn-save {
    background: var(--accent);
    border: none;
    color: white;
}

.btn-save:hover {
    background: var(--accent-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);
}

.btn-save:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* ========== КОНТЕКСТНОЕ МЕНЮ ========== */
.context-menu {
    display: none;
    position: fixed;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 4px;
    min-width: 200px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    z-index: 10001;
    animation: contextMenuFadeIn 0.15s ease;
}

.context-menu.active {
    display: block;
}

@keyframes contextMenuFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.context-menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 6px;
    cursor: pointer;
    color: var(--text-primary);
    font-size: 0.9rem;
    transition: all 0.15s;
}

.context-menu-item:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}

.context-menu-item .material-icons {
    font-size: 18px;
    color: #f87171;
}

/* ========== UNDO TOAST ========== */
.undo-toast {
    display: none;
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: #1f2937;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 16px;
    align-items: center;
    gap: 12px;
    z-index: 10002;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    transition: transform 0.3s ease;
}

.undo-toast.show {
    display: flex;
    transform: translateX(-50%) translateY(0);
}

.undo-text {
    color: var(--text-primary);
    font-size: 0.9rem;
}

.undo-btn {
    background: var(--accent);
    border: none;
    border-radius: 6px;
    padding: 6px 14px;
    color: white;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.undo-btn:hover {
    background: var(--accent-hover);
}

.undo-hint {
    color: var(--text-muted);
    font-size: 0.75rem;
}
</style>

<!-- Панель фильтров -->
<div class="filters-panel">
    <div class="filters-content">
        <div class="filter-group">
            <span class="filter-label">Будни:</span>
            <button class="day-filter-btn active" data-day="1" onclick="toggleDayFilter(this)">Пн</button>
            <button class="day-filter-btn active" data-day="2" onclick="toggleDayFilter(this)">Вт</button>
            <button class="day-filter-btn active" data-day="3" onclick="toggleDayFilter(this)">Ср</button>
            <button class="day-filter-btn active" data-day="4" onclick="toggleDayFilter(this)">Чт</button>
            <button class="day-filter-btn active" data-day="5" onclick="toggleDayFilter(this)">Пт</button>
        </div>

        <div class="filter-group">
            <span class="filter-label">Выходные:</span>
            <button class="day-filter-btn active" data-day="6" onclick="toggleDayFilter(this)">Сб</button>
            <button class="day-filter-btn active" data-day="7" onclick="toggleDayFilter(this)">Вс</button>
        </div>

        <div class="filter-divider"></div>

        <div class="filter-group">
            <span class="filter-label">Каб:</span>
            <button class="room-filter-btn active" data-room="1" onclick="toggleRoomFilter(this)">1</button>
            <button class="room-filter-btn active" data-room="2" onclick="toggleRoomFilter(this)">2</button>
            <button class="room-filter-btn active" data-room="3" onclick="toggleRoomFilter(this)">3</button>
        </div>

        <div class="filter-divider"></div>

        <div class="filter-group">
            <button class="toggle-btn active" id="tierToggle" onclick="toggleTierDisplay()">
                <span class="material-icons" style="font-size: 16px;">label</span>
                Тиры
            </button>
        </div>

        <div class="filter-divider"></div>

        <span class="student-count">Учеников: <strong id="studentCount"><?= count($students) ?></strong></span>

        <button class="add-student-btn" onclick="openAddStudentModal()">
            <span class="material-icons">person_add</span>
            Добавить ученика
        </button>
    </div>
</div>

<!-- Легенда преподавателей -->
<div class="teacher-legend">
    <span style="color: #9ca3af; font-size: 0.75rem; margin-right: 4px;">Преподаватели:</span>
    <?php foreach ($teachers as $teacher):
        $colorIndex = ($teacher['id'] % 8) ?: 8;
    ?>
        <div class="teacher-legend-item">
            <div class="teacher-color-box teacher-color-<?= $colorIndex ?>"></div>
            <span><?= e($teacher['display_name'] ?: $teacher['name']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<!-- Сетка планировщика - две секции -->
<div class="planner-container">
    <div class="planner-wrapper">
        <!-- Секция будних дней (Пн-Пт): 15:00-21:00 -->
        <div class="planner-section" id="weekdaysSection">
            <div class="section-title">Будни (15:00 - 21:00)</div>
            <div class="planner-grid weekdays" id="weekdaysGrid">
                <!-- Заголовки дней -->
                <div class="grid-header time-header">Время</div>
                <?php for ($d = 1; $d <= 5; $d++): ?>
                    <div class="grid-header day-header" data-day="<?= $d ?>"><?= $daysOfWeek[$d]['name'] ?></div>
                <?php endfor; ?>

                <!-- Строки времени: 15:00-21:00 (7 строк) -->
                <?php for ($hour = 15; $hour <= 21; $hour++):
                    $time = sprintf('%02d:00', $hour);
                ?>
                    <div class="time-cell"><?= $time ?></div>

                    <?php for ($dayNum = 1; $dayNum <= 5; $dayNum++): ?>
                        <div class="schedule-cell" data-day="<?= $dayNum ?>" data-time="<?= $time ?>">
                            <div class="rooms-container">
                                <?php for ($room = 1; $room <= 3; $room++):
                                    $key = "{$dayNum}_{$time}_{$room}";
                                    $cellData = $scheduleGrid[$key] ?? null;
                                ?>
                                    <div class="room-slot" data-room="<?= $room ?>" data-day="<?= $dayNum ?>" data-time="<?= $time ?>">
                                        <div class="room-slot-header"><?= $room ?></div>
                                        <?php if ($cellData && !empty($cellData['students'])): ?>
                                            <?php foreach ($cellData['students'] as $student):
                                                $teacherColorIndex = ($student['teacher_id'] % 8) ?: 8;
                                            ?>
                                                <div class="student-card teacher-<?= $teacherColorIndex ?>"
                                                     draggable="true"
                                                     data-student-id="<?= $student['id'] ?>"
                                                     data-student-name="<?= e($student['name']) ?>"
                                                     data-student-class="<?= $student['class'] ?>"
                                                     data-student-tier="<?= $student['tier'] ?>"
                                                     data-teacher-id="<?= $student['teacher_id'] ?>"
                                                     data-day="<?= $dayNum ?>"
                                                     data-time="<?= $time ?>"
                                                     data-room="<?= $room ?>">
                                                    <span class="student-tier tier-<?= $student['tier'] ?>"><?= $student['tier'] ?></span>
                                                    <span class="student-name"><?= e($student['name']) ?></span>
                                                    <span class="student-class"><?= $student['class'] ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Секция выходных (Сб-Вс): 08:00-21:00 -->
        <div class="planner-section" id="weekendsSection">
            <div class="section-title">Выходные (08:00 - 21:00)</div>
            <div class="planner-grid weekends" id="weekendsGrid">
                <!-- Заголовки дней -->
                <div class="grid-header time-header">Время</div>
                <?php for ($d = 6; $d <= 7; $d++): ?>
                    <div class="grid-header day-header" data-day="<?= $d ?>"><?= $daysOfWeek[$d]['name'] ?></div>
                <?php endfor; ?>

                <!-- Строки времени: 08:00-21:00 (14 строк) -->
                <?php for ($hour = 8; $hour <= 21; $hour++):
                    $time = sprintf('%02d:00', $hour);
                ?>
                    <div class="time-cell"><?= $time ?></div>

                    <?php for ($dayNum = 6; $dayNum <= 7; $dayNum++): ?>
                        <div class="schedule-cell" data-day="<?= $dayNum ?>" data-time="<?= $time ?>">
                            <div class="rooms-container">
                                <?php for ($room = 1; $room <= 3; $room++):
                                    $key = "{$dayNum}_{$time}_{$room}";
                                    $cellData = $scheduleGrid[$key] ?? null;
                                ?>
                                    <div class="room-slot" data-room="<?= $room ?>" data-day="<?= $dayNum ?>" data-time="<?= $time ?>">
                                        <div class="room-slot-header"><?= $room ?></div>
                                        <?php if ($cellData && !empty($cellData['students'])): ?>
                                            <?php foreach ($cellData['students'] as $student):
                                                $teacherColorIndex = ($student['teacher_id'] % 8) ?: 8;
                                            ?>
                                                <div class="student-card teacher-<?= $teacherColorIndex ?>"
                                                     draggable="true"
                                                     data-student-id="<?= $student['id'] ?>"
                                                     data-student-name="<?= e($student['name']) ?>"
                                                     data-student-class="<?= $student['class'] ?>"
                                                     data-student-tier="<?= $student['tier'] ?>"
                                                     data-teacher-id="<?= $student['teacher_id'] ?>"
                                                     data-day="<?= $dayNum ?>"
                                                     data-time="<?= $time ?>"
                                                     data-room="<?= $room ?>">
                                                    <span class="student-tier tier-<?= $student['tier'] ?>"><?= $student['tier'] ?></span>
                                                    <span class="student-name"><?= e($student['name']) ?></span>
                                                    <span class="student-class"><?= $student['class'] ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно добавления ученика -->
<div id="addStudentModal" class="modal-overlay" onclick="closeAddStudentModal(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3>Добавить ученика в расписание</h3>
            <button class="modal-close" onclick="closeAddStudentModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="modalClass">Класс</label>
                    <select id="modalClass" onchange="loadStudentsByClass()">
                        <option value="">Выберите класс</option>
                        <?php for ($c = 2; $c <= 11; $c++): ?>
                            <option value="<?= $c ?>"><?= $c ?> класс</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modalStudent">Ученик</label>
                    <select id="modalStudent" disabled>
                        <option value="">Сначала выберите класс</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="modalSubject">Предмет</label>
                <select id="modalSubject">
                    <option value="Мат.">Математика</option>
                    <option value="Физ.">Физика</option>
                    <option value="Инф.">Информатика</option>
                    <option value="Рус.">Русский язык</option>
                    <option value="Англ.">Английский язык</option>
                </select>
            </div>

            <div class="schedule-section">
                <label>Расписание</label>
                <div class="days-list">
                    <?php
                    $dayNames = [
                        1 => 'Понедельник',
                        2 => 'Вторник',
                        3 => 'Среда',
                        4 => 'Четверг',
                        5 => 'Пятница',
                        6 => 'Суббота',
                        7 => 'Воскресенье'
                    ];
                    foreach ($dayNames as $dayNum => $dayName):
                        $isWeekend = $dayNum >= 6;
                        $startHour = $isWeekend ? 8 : 15;
                        $endHour = 21;
                    ?>
                    <div class="day-row">
                        <label class="day-checkbox">
                            <input type="checkbox" name="day" value="<?= $dayNum ?>" onchange="toggleDayRow(this)">
                            <span class="day-name"><?= $dayName ?></span>
                        </label>
                        <div class="day-options" data-day="<?= $dayNum ?>">
                            <select class="time-select" name="time_<?= $dayNum ?>" disabled>
                                <?php for ($h = $startHour; $h <= $endHour; $h++): ?>
                                    <option value="<?= sprintf('%02d:00', $h) ?>"><?= sprintf('%02d:00', $h) ?></option>
                                <?php endfor; ?>
                            </select>
                            <select class="room-select" name="room_<?= $dayNum ?>" disabled>
                                <option value="1">Каб. 1</option>
                                <option value="2">Каб. 2</option>
                                <option value="3">Каб. 3</option>
                            </select>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeAddStudentModal()">Отмена</button>
            <button class="btn-save" onclick="saveStudentSchedule()">Сохранить</button>
        </div>
    </div>
</div>

<!-- Контекстное меню -->
<div id="contextMenu" class="context-menu">
    <div class="context-menu-item" onclick="deleteStudentSlot()">
        <span class="material-icons">delete</span>
        <span>Удалить из расписания</span>
    </div>
</div>

<!-- Undo Toast -->
<div id="undoToast" class="undo-toast">
    <span class="undo-text">Ученик удалён из расписания</span>
    <button class="undo-btn" onclick="undoDelete()">Отменить</button>
    <span class="undo-hint">(Ctrl+Z)</span>
</div>

<!-- Уведомления -->
<div id="notification" class="notification">
    <span class="material-icons">check_circle</span>
    <span id="notification-text"></span>
</div>

<script>
// Данные из PHP
const scheduleData = <?= json_encode($scheduleGrid, JSON_UNESCAPED_UNICODE) ?>;
const studentsData = <?= json_encode($students, JSON_UNESCAPED_UNICODE) ?>;
const teachersData = <?= json_encode($teachers, JSON_UNESCAPED_UNICODE) ?>;

// ========== SIDEBAR TOGGLE ==========

// Применяем стили напрямую к элементам (обход CSS конфликтов)
function applyLayoutStyles() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const layout = document.querySelector('.layout');

    if (!mainContent || !sidebar) return;

    const isCollapsed = sidebar.classList.contains('collapsed');
    const sidebarWidth = isCollapsed ? 60 : 220;

    mainContent.style.marginLeft = sidebarWidth + 'px';
    mainContent.style.width = 'calc(100vw - ' + sidebarWidth + 'px)';
    mainContent.style.maxWidth = 'calc(100vw - ' + sidebarWidth + 'px)';
    mainContent.style.padding = '12px 8px';
    mainContent.style.boxSizing = 'border-box';
    mainContent.style.height = '100vh';
    mainContent.style.overflow = 'hidden';
    mainContent.style.display = 'flex';
    mainContent.style.flexDirection = 'column';
}

// Добавляем кнопку сворачивания sidebar
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const layout = document.querySelector('.layout');

    if (sidebar && layout) {
        // Создаём кнопку (sidebar остаётся position: fixed!)
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'sidebar-toggle';
        toggleBtn.innerHTML = '<span class="material-icons">chevron_left</span>';
        toggleBtn.onclick = toggleSidebar;
        sidebar.appendChild(toggleBtn);

        // Восстанавливаем состояние
        if (localStorage.getItem('plannerSidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            layout.classList.add('sidebar-collapsed');
        }
    }

    // Применяем стили сразу
    applyLayoutStyles();

    initDragAndDrop();
    restoreFilters();
    updateGridColumns();
});

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const layout = document.querySelector('.layout');

    sidebar.classList.toggle('collapsed');
    layout.classList.toggle('sidebar-collapsed');

    localStorage.setItem('plannerSidebarCollapsed', sidebar.classList.contains('collapsed'));

    // Применяем стили после переключения
    applyLayoutStyles();
}

// ========== DRAG AND DROP ==========

let draggedCard = null;
let sourceSlot = null;

function initDragAndDrop() {
    const cards = document.querySelectorAll('.student-card');
    const slots = document.querySelectorAll('.room-slot');

    cards.forEach(card => {
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });

    slots.forEach(slot => {
        slot.addEventListener('dragover', handleDragOver);
        slot.addEventListener('dragleave', handleDragLeave);
        slot.addEventListener('drop', handleDrop);
    });
}

function handleDragStart(e) {
    draggedCard = e.target;
    sourceSlot = e.target.closest('.room-slot');

    e.target.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', JSON.stringify({
        studentId: e.target.dataset.studentId,
        studentName: e.target.dataset.studentName,
        studentClass: e.target.dataset.studentClass,
        studentTier: e.target.dataset.studentTier,
        teacherId: e.target.dataset.teacherId,
        fromDay: e.target.dataset.day,
        fromTime: e.target.dataset.time,
        fromRoom: e.target.dataset.room
    }));
}

function handleDragEnd(e) {
    e.target.classList.remove('dragging');
    draggedCard = null;
    sourceSlot = null;

    document.querySelectorAll('.drag-over').forEach(el => {
        el.classList.remove('drag-over');
    });
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';

    const slot = e.target.closest('.room-slot');
    if (slot && slot !== sourceSlot) {
        slot.classList.add('drag-over');
    }
}

function handleDragLeave(e) {
    const slot = e.target.closest('.room-slot');
    if (slot) {
        slot.classList.remove('drag-over');
    }
}

async function handleDrop(e) {
    e.preventDefault();

    const slot = e.target.closest('.room-slot');
    if (!slot) return;

    slot.classList.remove('drag-over');

    const data = JSON.parse(e.dataTransfer.getData('text/plain'));
    const toDay = slot.dataset.day;
    const toTime = slot.dataset.time;
    const toRoom = slot.dataset.room;

    if (data.fromDay === toDay && data.fromTime === toTime && data.fromRoom === toRoom) {
        return;
    }

    // Сохраняем ссылку на карточку ДО async запроса (draggedCard обнулится в handleDragEnd)
    const cardToMove = document.querySelector(
        `.student-card[data-student-id="${data.studentId}"][data-day="${data.fromDay}"][data-time="${data.fromTime}"][data-room="${data.fromRoom}"]`
    );

    try {
        const response = await fetch('/zarplata/api/planner.php?action=move_student', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                student_id: data.studentId,
                from_day: data.fromDay,
                from_time: data.fromTime,
                from_room: data.fromRoom,
                to_day: toDay,
                to_time: toTime,
                to_room: toRoom,
                teacher_id: data.teacherId
            })
        });

        const result = await response.json();

        if (result.success) {
            // Находим исходный слот ДО перемещения карточки
            const sourceSlotEl = document.querySelector(
                `.room-slot[data-day="${data.fromDay}"][data-time="${data.fromTime}"][data-room="${data.fromRoom}"]`
            );

            if (cardToMove) {
                cardToMove.dataset.day = toDay;
                cardToMove.dataset.time = toTime;
                cardToMove.dataset.room = toRoom;
                slot.appendChild(cardToMove);
            }

            // Проверяем, остались ли ученики в исходном слоте
            if (sourceSlotEl) {
                const remainingCards = sourceSlotEl.querySelectorAll('.student-card');
                if (remainingCards.length === 0) {
                    // Удаляем пустой слот
                    const parentCell = sourceSlotEl.closest('.schedule-cell');
                    sourceSlotEl.remove();

                    // Проверяем, остались ли слоты в ячейке
                    if (parentCell) {
                        const remainingSlots = parentCell.querySelectorAll('.room-slot');
                        if (remainingSlots.length === 0) {
                            // Если ячейка пустая, показываем сообщение
                            const emptyMsg = document.createElement('div');
                            emptyMsg.className = 'empty-cell-message';
                            emptyMsg.textContent = 'Нет занятий';
                            emptyMsg.style.cssText = 'color: #6b7280; font-size: 12px; text-align: center; padding: 8px;';
                            parentCell.appendChild(emptyMsg);
                        }
                    }
                }
            }

            showNotification('Ученик перемещён', 'success');
        } else {
            showNotification(result.error || 'Ошибка перемещения', 'error');
        }
    } catch (error) {
        console.error('Move error:', error);
        showNotification('Ошибка сети', 'error');
    }
}

// ========== ФИЛЬТРЫ ==========

function toggleDayFilter(button) {
    button.classList.toggle('active');
    updateVisibleDays();
    updateGridColumns();
    saveFilters();
}

function updateVisibleDays() {
    const activeDays = Array.from(document.querySelectorAll('.day-filter-btn.active'))
        .map(btn => parseInt(btn.dataset.day));

    // Скрываем/показываем секции
    const weekdaysSection = document.getElementById('weekdaysSection');
    const weekendsSection = document.getElementById('weekendsSection');

    const hasWeekdays = activeDays.some(d => d >= 1 && d <= 5);
    const hasWeekends = activeDays.some(d => d >= 6 && d <= 7);

    // Если нет активных - показываем всё
    if (activeDays.length === 0) {
        weekdaysSection.style.display = '';
        weekendsSection.style.display = '';
    } else {
        weekdaysSection.style.display = hasWeekdays ? '' : 'none';
        weekendsSection.style.display = hasWeekends ? '' : 'none';
    }

    // Скрываем/показываем заголовки дней внутри секций
    document.querySelectorAll('.grid-header.day-header').forEach(header => {
        const day = parseInt(header.dataset.day);
        if (activeDays.length === 0 || activeDays.includes(day)) {
            header.classList.remove('hidden');
        } else {
            header.classList.add('hidden');
        }
    });

    // Скрываем/показываем ячейки
    document.querySelectorAll('.schedule-cell').forEach(cell => {
        const day = parseInt(cell.dataset.day);
        if (activeDays.length === 0 || activeDays.includes(day)) {
            cell.classList.remove('hidden');
        } else {
            cell.classList.add('hidden');
        }
    });
}

function updateGridColumns() {
    // Будние дни
    const activeWeekdays = Array.from(document.querySelectorAll('.day-filter-btn.active[data-day="1"], .day-filter-btn.active[data-day="2"], .day-filter-btn.active[data-day="3"], .day-filter-btn.active[data-day="4"], .day-filter-btn.active[data-day="5"]'));
    const visibleWeekdays = activeWeekdays.length === 0 ? 5 : activeWeekdays.length;

    const weekdaysGrid = document.getElementById('weekdaysGrid');
    if (weekdaysGrid && visibleWeekdays > 0) {
        weekdaysGrid.style.gridTemplateColumns = `50px repeat(${visibleWeekdays}, 1fr)`;
    }

    // Выходные
    const activeWeekends = Array.from(document.querySelectorAll('.day-filter-btn.active[data-day="6"], .day-filter-btn.active[data-day="7"]'));
    const visibleWeekends = activeWeekends.length === 0 ? 2 : activeWeekends.length;

    const weekendsGrid = document.getElementById('weekendsGrid');
    if (weekendsGrid && visibleWeekends > 0) {
        weekendsGrid.style.gridTemplateColumns = `50px repeat(${visibleWeekends}, 1fr)`;
    }
}

function toggleRoomFilter(button) {
    button.classList.toggle('active');
    updateVisibleRooms();
    saveFilters();
}

function updateVisibleRooms() {
    const activeRooms = Array.from(document.querySelectorAll('.room-filter-btn.active'))
        .map(btn => parseInt(btn.dataset.room));

    document.querySelectorAll('.room-slot').forEach(slot => {
        const room = parseInt(slot.dataset.room);
        if (activeRooms.length === 0 || activeRooms.includes(room)) {
            slot.classList.remove('hidden');
        } else {
            slot.classList.add('hidden');
        }
    });

    const visibleCount = activeRooms.length === 0 ? 3 : activeRooms.length;
    document.querySelectorAll('.rooms-container').forEach(container => {
        container.style.gridTemplateColumns = `repeat(${visibleCount}, 1fr)`;
    });
}

function toggleTierDisplay() {
    const btn = document.getElementById('tierToggle');
    btn.classList.toggle('active');

    const showTier = btn.classList.contains('active');
    document.querySelectorAll('.student-tier').forEach(tier => {
        tier.classList.toggle('hidden', !showTier);
    });

    saveFilters();
}

// ========== СОХРАНЕНИЕ ФИЛЬТРОВ ==========

function saveFilters() {
    const filters = {
        days: Array.from(document.querySelectorAll('.day-filter-btn.active'))
            .map(btn => btn.dataset.day),
        rooms: Array.from(document.querySelectorAll('.room-filter-btn.active'))
            .map(btn => btn.dataset.room),
        showTier: document.getElementById('tierToggle').classList.contains('active')
    };
    localStorage.setItem('plannerFilters', JSON.stringify(filters));
}

function restoreFilters() {
    const saved = localStorage.getItem('plannerFilters');
    if (!saved) return;

    try {
        const filters = JSON.parse(saved);

        // Восстанавливаем дни
        if (filters.days && filters.days.length > 0 && filters.days.length < 7) {
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
        if (filters.rooms && filters.rooms.length > 0 && filters.rooms.length < 3) {
            document.querySelectorAll('.room-filter-btn').forEach(btn => {
                if (filters.rooms.includes(btn.dataset.room)) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            updateVisibleRooms();
        }

        // Восстанавливаем тиры
        if (filters.showTier === false) {
            toggleTierDisplay();
        }
    } catch (e) {
        console.error('Error restoring filters:', e);
    }
}

// ========== УВЕДОМЛЕНИЯ ==========

function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    const text = document.getElementById('notification-text');

    notification.className = `notification notification-${type}`;
    notification.querySelector('.material-icons').textContent =
        type === 'success' ? 'check_circle' : 'error';
    text.textContent = message;

    notification.classList.add('show');

    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// ========== МОДАЛЬНОЕ ОКНО ДОБАВЛЕНИЯ УЧЕНИКА ==========

function openAddStudentModal() {
    const modal = document.getElementById('addStudentModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Сбрасываем форму
    resetAddStudentForm();
}

function closeAddStudentModal(event) {
    // Если кликнули на overlay (не на контент), закрываем
    if (event && event.target !== event.currentTarget) return;

    const modal = document.getElementById('addStudentModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

function resetAddStudentForm() {
    document.getElementById('modalClass').value = '';
    document.getElementById('modalStudent').value = '';
    document.getElementById('modalStudent').disabled = true;
    document.getElementById('modalStudent').innerHTML = '<option value="">Сначала выберите класс</option>';
    document.getElementById('modalSubject').value = 'Мат.';

    // Сбрасываем все чекбоксы дней
    document.querySelectorAll('.day-checkbox input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });

    // Деактивируем все селекты времени и кабинета
    document.querySelectorAll('.day-options').forEach(opts => {
        opts.classList.remove('active');
        opts.querySelectorAll('select').forEach(sel => sel.disabled = true);
    });
}

async function loadStudentsByClass() {
    const classSelect = document.getElementById('modalClass');
    const studentSelect = document.getElementById('modalStudent');
    const selectedClass = classSelect.value;

    if (!selectedClass) {
        studentSelect.disabled = true;
        studentSelect.innerHTML = '<option value="">Сначала выберите класс</option>';
        return;
    }

    studentSelect.disabled = true;
    studentSelect.innerHTML = '<option value="">Загрузка...</option>';

    try {
        const response = await fetch(`/zarplata/api/planner.php?action=get_students_by_class&class=${selectedClass}`);
        const result = await response.json();

        if (result.success && result.data.students) {
            const students = result.data.students;

            if (students.length === 0) {
                studentSelect.innerHTML = '<option value="">Нет учеников в этом классе</option>';
                return;
            }

            studentSelect.innerHTML = '<option value="">Выберите ученика</option>';
            students.forEach(student => {
                const option = document.createElement('option');
                option.value = student.id;
                option.textContent = student.name;
                option.dataset.teacherId = student.teacher_id;
                option.dataset.tier = student.tier || 'C';
                studentSelect.appendChild(option);
            });

            studentSelect.disabled = false;
        } else {
            studentSelect.innerHTML = '<option value="">Ошибка загрузки</option>';
            showNotification(result.error || 'Ошибка загрузки учеников', 'error');
        }
    } catch (error) {
        console.error('Error loading students:', error);
        studentSelect.innerHTML = '<option value="">Ошибка сети</option>';
        showNotification('Ошибка сети', 'error');
    }
}

function toggleDayRow(checkbox) {
    const dayRow = checkbox.closest('.day-row');
    const dayOptions = dayRow.querySelector('.day-options');
    const selects = dayOptions.querySelectorAll('select');

    if (checkbox.checked) {
        dayOptions.classList.add('active');
        selects.forEach(sel => sel.disabled = false);
    } else {
        dayOptions.classList.remove('active');
        selects.forEach(sel => sel.disabled = true);
    }
}

async function saveStudentSchedule() {
    const studentSelect = document.getElementById('modalStudent');
    const studentId = studentSelect.value;
    const subject = document.getElementById('modalSubject').value;

    if (!studentId) {
        showNotification('Выберите ученика', 'error');
        return;
    }

    // Собираем выбранные дни
    const schedule = [];
    document.querySelectorAll('.day-checkbox input[type="checkbox"]:checked').forEach(cb => {
        const dayNum = cb.value;
        const dayOptions = cb.closest('.day-row').querySelector('.day-options');
        const time = dayOptions.querySelector('.time-select').value;
        const room = dayOptions.querySelector('.room-select').value;

        schedule.push({
            day: parseInt(dayNum),
            time: time,
            room: parseInt(room)
        });
    });

    if (schedule.length === 0) {
        showNotification('Выберите хотя бы один день', 'error');
        return;
    }

    // Получаем teacher_id из выбранного ученика
    const selectedOption = studentSelect.options[studentSelect.selectedIndex];
    const teacherId = selectedOption.dataset.teacherId;
    const tier = selectedOption.dataset.tier;
    const studentName = selectedOption.textContent;
    const studentClass = document.getElementById('modalClass').value;

    // Отправляем на сервер
    const saveBtn = document.querySelector('.btn-save');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Сохранение...';

    try {
        const response = await fetch('/zarplata/api/planner.php?action=add_student_schedule', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                student_id: studentId,
                subject: subject,
                schedule: schedule
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Ученик добавлен в расписание', 'success');
            closeAddStudentModal();

            // Добавляем карточки ученика на страницу
            schedule.forEach(slot => {
                addStudentCardToGrid(studentId, studentName, studentClass, tier, teacherId, slot.day, slot.time, slot.room, subject);
            });

            // Обновляем счётчик
            updateStudentCount();

        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Save error:', error);
        showNotification('Ошибка сети', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Сохранить';
    }
}

function addStudentCardToGrid(studentId, studentName, studentClass, tier, teacherId, day, time, room, subject) {
    // Находим нужный слот
    const roomSlot = document.querySelector(
        `.room-slot[data-day="${day}"][data-time="${time}"][data-room="${room}"]`
    );

    if (!roomSlot) {
        console.warn(`Slot not found: day=${day}, time=${time}, room=${room}`);
        return;
    }

    // Проверяем, нет ли уже такой карточки
    const existingCard = roomSlot.querySelector(`.student-card[data-student-id="${studentId}"]`);
    if (existingCard) {
        return; // Уже есть
    }

    // Создаём карточку
    const teacherColorIndex = (parseInt(teacherId) % 8) || 8;
    const card = document.createElement('div');
    card.className = `student-card teacher-${teacherColorIndex}`;
    card.draggable = true;
    card.dataset.studentId = studentId;
    card.dataset.studentName = studentName;
    card.dataset.studentClass = studentClass;
    card.dataset.studentTier = tier;
    card.dataset.teacherId = teacherId;
    card.dataset.day = day;
    card.dataset.time = time;
    card.dataset.room = room;

    const showTier = document.getElementById('tierToggle').classList.contains('active');

    card.innerHTML = `
        <span class="student-tier tier-${tier}${showTier ? '' : ' hidden'}">${tier}</span>
        <span class="student-name">${studentName}</span>
        <span class="student-class">${studentClass}</span>
    `;

    // Добавляем события drag & drop
    card.addEventListener('dragstart', handleDragStart);
    card.addEventListener('dragend', handleDragEnd);

    roomSlot.appendChild(card);
}

function updateStudentCount() {
    const countEl = document.getElementById('studentCount');
    const uniqueStudents = new Set();
    document.querySelectorAll('.student-card').forEach(card => {
        uniqueStudents.add(card.dataset.studentId);
    });
    countEl.textContent = uniqueStudents.size;
}

// Закрытие модалки по Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('addStudentModal');
        if (modal.classList.contains('active')) {
            closeAddStudentModal();
        }
        // Закрываем контекстное меню
        hideContextMenu();
    }
});

// ========== КОНТЕКСТНОЕ МЕНЮ ==========

let contextMenuTarget = null; // Карточка на которой открыто меню
let lastDeletedStudent = null; // Данные последнего удалённого ученика для undo
let undoTimeout = null;

// Добавляем контекстное меню на все карточки
document.addEventListener('DOMContentLoaded', function() {
    initContextMenu();
});

function initContextMenu() {
    document.querySelectorAll('.student-card').forEach(card => {
        card.addEventListener('contextmenu', handleContextMenu);
    });
}

function handleContextMenu(e) {
    e.preventDefault();

    const card = e.target.closest('.student-card');
    if (!card) return;

    contextMenuTarget = card;

    const contextMenu = document.getElementById('contextMenu');

    // Позиционируем меню
    let x = e.clientX;
    let y = e.clientY;

    // Показываем меню чтобы получить его размеры
    contextMenu.classList.add('active');

    // Проверяем не выходит ли за границы экрана
    const menuRect = contextMenu.getBoundingClientRect();
    if (x + menuRect.width > window.innerWidth) {
        x = window.innerWidth - menuRect.width - 10;
    }
    if (y + menuRect.height > window.innerHeight) {
        y = window.innerHeight - menuRect.height - 10;
    }

    contextMenu.style.left = x + 'px';
    contextMenu.style.top = y + 'px';
}

function hideContextMenu() {
    const contextMenu = document.getElementById('contextMenu');
    contextMenu.classList.remove('active');
    contextMenuTarget = null;
}

// Закрытие меню при клике вне его
document.addEventListener('click', function(e) {
    if (!e.target.closest('.context-menu')) {
        hideContextMenu();
    }
});

// Удаление ученика из слота
async function deleteStudentSlot() {
    if (!contextMenuTarget) return;

    const card = contextMenuTarget;
    const studentId = card.dataset.studentId;
    const studentName = card.dataset.studentName;
    const studentClass = card.dataset.studentClass;
    const studentTier = card.dataset.studentTier;
    const teacherId = card.dataset.teacherId;
    const day = card.dataset.day;
    const time = card.dataset.time;
    const room = card.dataset.room;

    hideContextMenu();

    // Сохраняем данные для undo
    lastDeletedStudent = {
        studentId,
        studentName,
        studentClass,
        studentTier,
        teacherId,
        day,
        time,
        room,
        parentSlot: card.parentElement
    };

    // Удаляем карточку из DOM
    card.remove();

    // Отправляем запрос на сервер
    try {
        const response = await fetch('/zarplata/api/planner.php?action=remove_student_slot', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                student_id: studentId,
                day: day,
                time: time,
                room: room
            })
        });

        const result = await response.json();

        if (result.success) {
            // Показываем undo toast
            showUndoToast();
            updateStudentCount();
        } else {
            // Ошибка - возвращаем карточку
            restoreDeletedCard();
            showNotification(result.error || 'Ошибка удаления', 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
        restoreDeletedCard();
        showNotification('Ошибка сети', 'error');
    }
}

function showUndoToast() {
    const toast = document.getElementById('undoToast');
    toast.classList.add('show');

    // Автоматически скрываем через 5 секунд
    if (undoTimeout) clearTimeout(undoTimeout);
    undoTimeout = setTimeout(() => {
        hideUndoToast();
        lastDeletedStudent = null; // Очищаем данные, undo больше невозможен
    }, 5000);
}

function hideUndoToast() {
    const toast = document.getElementById('undoToast');
    toast.classList.remove('show');
}

async function undoDelete() {
    if (!lastDeletedStudent) return;

    hideUndoToast();
    if (undoTimeout) clearTimeout(undoTimeout);

    const data = lastDeletedStudent;

    // Восстанавливаем карточку в DOM
    restoreDeletedCard();

    // Отправляем запрос на восстановление
    try {
        const response = await fetch('/zarplata/api/planner.php?action=add_student_schedule', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                student_id: data.studentId,
                subject: 'Мат.', // По умолчанию
                schedule: [{
                    day: parseInt(data.day),
                    time: data.time,
                    room: parseInt(data.room)
                }]
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Отменено', 'success');
            updateStudentCount();
        } else {
            showNotification(result.error || 'Ошибка восстановления', 'error');
        }
    } catch (error) {
        console.error('Undo error:', error);
        showNotification('Ошибка сети', 'error');
    }

    lastDeletedStudent = null;
}

function restoreDeletedCard() {
    if (!lastDeletedStudent) return;

    const data = lastDeletedStudent;
    addStudentCardToGrid(
        data.studentId,
        data.studentName,
        data.studentClass,
        data.studentTier,
        data.teacherId,
        data.day,
        data.time,
        data.room,
        'Мат.'
    );
}

// Ctrl+Z для undo
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
        if (lastDeletedStudent) {
            e.preventDefault();
            undoDelete();
        }
    }
});

// Добавляем контекстное меню на новые карточки
const originalAddStudentCardToGrid = addStudentCardToGrid;
addStudentCardToGrid = function(studentId, studentName, studentClass, tier, teacherId, day, time, room, subject) {
    originalAddStudentCardToGrid(studentId, studentName, studentClass, tier, teacherId, day, time, room, subject);

    // Добавляем контекстное меню на новую карточку
    const newCard = document.querySelector(
        `.student-card[data-student-id="${studentId}"][data-day="${day}"][data-time="${time}"][data-room="${room}"]`
    );
    if (newCard) {
        newCard.addEventListener('contextmenu', handleContextMenu);
    }
};
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
