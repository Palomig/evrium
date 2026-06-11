<?php
/**
 * Расписание (бывший планировщик)
 * Работает как Google-таблица: блоки уроков с редактируемым заголовком
 * и текстовыми ячейками учеников. Клик по ячейке открывает поле ввода.
 * Данные хранятся в таблице planner_notes (свободный текст + цвет).
 *
 * При первом открытии сетка наполняется из существующего расписания
 * учеников (students.schedule) — имена, заголовки «класс + предмет»
 * и цвета преподавателей переносятся автоматически.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// ===== Таблица заметок (создаётся при первом открытии) =====
dbExecute("
    CREATE TABLE IF NOT EXISTS planner_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        day TINYINT NOT NULL,
        time VARCHAR(5) NOT NULL,
        room TINYINT NOT NULL DEFAULT 1,
        kind ENUM('title','student') NOT NULL DEFAULT 'student',
        position SMALLINT NOT NULL DEFAULT 0,
        content VARCHAR(160) NOT NULL DEFAULT '',
        color TINYINT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_cell (day, time, room)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", []);

// Преподаватели — для легенды и палитры цветов
$teachers = dbQuery("
    SELECT id, name, display_name
    FROM teachers
    WHERE active = 1
    ORDER BY name
", []);

// ===== Сидирование из students.schedule при пустой таблице =====
$notesCount = dbQueryOne("SELECT COUNT(*) AS c FROM planner_notes", []);
if ((int)($notesCount['c'] ?? 0) === 0) {
    $students = dbQuery("
        SELECT s.id, s.name, s.class, s.teacher_id, s.schedule
        FROM students s
        WHERE s.active = 1
        ORDER BY s.name
    ", []);

    $seedGrid = [];
    foreach ($students as $student) {
        if (!$student['schedule']) continue;
        $schedule = json_decode($student['schedule'], true);
        if (!is_array($schedule)) continue;

        foreach ($schedule as $dayKey => $daySchedule) {
            $day = (int)$dayKey;
            if ($day < 1 || $day > 7) continue;

            // Нормализуем все исторические форматы к списку слотов
            $slots = [];
            if (is_array($daySchedule)) {
                if (isset($daySchedule[0]) && is_array($daySchedule[0])) {
                    $slots = $daySchedule;
                } elseif (isset($daySchedule['time'])) {
                    $slots = [$daySchedule];
                }
            } elseif (is_string($daySchedule)) {
                $slots = [['time' => $daySchedule, 'room' => 1, 'subject' => 'Мат.']];
            }

            foreach ($slots as $slot) {
                $time = substr($slot['time'] ?? '00:00', 0, 5);
                $room = (int)($slot['room'] ?? 1);
                $key = "{$day}_{$time}_{$room}";

                if (!isset($seedGrid[$key])) {
                    $seedGrid[$key] = [
                        'day' => $day, 'time' => $time, 'room' => $room,
                        'subject' => $slot['subject'] ?? 'Мат.',
                        'students' => []
                    ];
                }
                $seedGrid[$key]['students'][] = [
                    'name' => $student['name'],
                    'class' => $student['class'],
                    'color' => $student['teacher_id'] ? ((((int)$student['teacher_id']) % 8) ?: 8) : 0
                ];
            }
        }
    }

    foreach ($seedGrid as $cell) {
        // Заголовок блока: «9 Мат.», «8-9 Физ.»
        $classes = array_values(array_unique(array_filter(array_column($cell['students'], 'class'))));
        sort($classes);
        $classLabel = '';
        if (count($classes) === 1) {
            $classLabel = (string)$classes[0];
        } elseif (count($classes) > 1) {
            $classLabel = min($classes) . '-' . max($classes);
        }
        $title = trim($classLabel . ' ' . $cell['subject']);

        // Цвет блока — самый частый цвет учеников
        $colorCounts = array_count_values(array_column($cell['students'], 'color'));
        arsort($colorCounts);
        $blockColor = (int)array_key_first($colorCounts);

        dbExecute(
            "INSERT INTO planner_notes (day, time, room, kind, position, content, color)
             VALUES (?, ?, ?, 'title', 0, ?, ?)",
            [$cell['day'], $cell['time'], $cell['room'], $title, $blockColor]
        );

        $pos = 1;
        foreach ($cell['students'] as $st) {
            dbExecute(
                "INSERT INTO planner_notes (day, time, room, kind, position, content, color)
                 VALUES (?, ?, ?, 'student', ?, ?, ?)",
                [$cell['day'], $cell['time'], $cell['room'], $pos++, $st['name'], $st['color']]
            );
        }
    }
}

// ===== Загружаем все заметки и группируем по блокам =====
$notes = dbQuery("
    SELECT id, day, time, room, kind, position, content, color
    FROM planner_notes
    ORDER BY day, time, room, kind, position, id
", []);

$blocks = [];
foreach ($notes as $note) {
    $key = "{$note['day']}_{$note['time']}_{$note['room']}";
    if (!isset($blocks[$key])) {
        $blocks[$key] = ['title' => null, 'students' => []];
    }
    if ($note['kind'] === 'title') {
        $blocks[$key]['title'] = $note;
    } else {
        $blocks[$key]['students'][] = $note;
    }
}

$daysOfWeek = [
    1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг',
    5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'
];

/**
 * Рендер одного блока урока (день × время × кабинет)
 */
function renderLessonBlock($day, $time, $room, $blocks) {
    $key = "{$day}_{$time}_{$room}";
    $block = $blocks[$key] ?? ['title' => null, 'students' => []];
    $title = $block['title'];
    $blockColor = $title ? (int)$title['color'] : 0;
    ?>
    <div class="lesson-block bc<?= $blockColor ?>" data-day="<?= $day ?>" data-time="<?= $time ?>" data-room="<?= $room ?>">
        <span class="block-room"><?= $room ?></span>
        <div class="block-title<?= $title && $title['content'] !== '' ? '' : ' is-empty' ?>"
             data-kind="title"
             <?= $title ? 'data-id="' . $title['id'] . '"' : '' ?>><?= $title ? e($title['content']) : '' ?></div>
        <div class="block-cells">
            <?php foreach ($block['students'] as $st): ?>
                <div class="pcell c<?= (int)$st['color'] ?>" data-kind="student" data-id="<?= $st['id'] ?>"><?= e($st['content']) ?></div>
            <?php endforeach; ?>
            <div class="pcell pcell-empty" data-kind="student"></div>
        </div>
    </div>
    <?php
}

define('PAGE_TITLE', 'Расписание');
define('PAGE_SUBTITLE', '');
define('ACTIVE_PAGE', 'planner');

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* Fonts */
body, .filters-panel, .day-filter-btn, .room-filter-btn, button {
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

/* ========== MAIN CONTENT ========== */
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

/* ========== ПАНЕЛЬ ФИЛЬТРОВ ========== */
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

.filter-divider {
    width: 1px;
    height: 28px;
    background: var(--border);
    margin: 0 4px;
}

.student-count {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.student-count strong {
    color: var(--accent);
}

.edit-hint {
    margin-left: auto;
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
}

.edit-hint .material-icons {
    font-size: 16px;
}

/* ========== ЛЕГЕНДА ПРЕПОДАВАТЕЛЕЙ ========== */
.teacher-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 8px 12px;
    background: #1a1f28;
    border-radius: 6px;
    margin-bottom: 0;
    flex-shrink: 0;
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

.tcolor-1 { background: rgba(20, 184, 166, 0.6); }
.tcolor-2 { background: rgba(168, 85, 247, 0.6); }
.tcolor-3 { background: rgba(59, 130, 246, 0.6); }
.tcolor-4 { background: rgba(249, 115, 22, 0.6); }
.tcolor-5 { background: rgba(236, 72, 153, 0.6); }
.tcolor-6 { background: rgba(234, 179, 8, 0.6); }
.tcolor-7 { background: rgba(34, 197, 94, 0.6); }
.tcolor-8 { background: rgba(239, 68, 68, 0.6); }

/* ========== СЕТКА ========== */
.planner-container {
    position: relative;
    overflow: auto;
    background-color: var(--bg-card);
    border-radius: 12px;
    padding: 0 12px 12px 12px;
    flex: 1;
    min-height: 0;
    width: 100%;
    box-sizing: border-box;
}

.planner-wrapper {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    min-width: max-content;
}

.planner-grid {
    display: grid;
    gap: 1px;
    background: var(--border);
    border-radius: 8px;
}

.planner-grid.weekdays {
    grid-template-columns: 56px repeat(5, minmax(216px, 1fr));
}

.planner-grid.weekends {
    grid-template-columns: 56px repeat(2, minmax(216px, 1fr));
}

/* Шапка дней — закреплена при вертикальном скроле */
.grid-header {
    background: var(--bg-hover);
    padding: 10px 6px;
    text-align: center;
    font-weight: 700;
    font-size: 0.8rem;
    color: var(--text-primary);
    position: sticky;
    top: 0;
    z-index: 10;
}

/* Угловая ячейка и колонка времени — закреплены при горизонтальном скроле */
.grid-header.time-header {
    background: var(--bg-elevated);
    color: var(--text-muted);
    left: 0;
    z-index: 15;
}

.grid-header.hidden {
    display: none;
}

.time-cell {
    background: var(--bg-elevated);
    padding: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    position: sticky;
    left: 0;
    z-index: 5;
}

/* Ячейка день×час: 3 кабинета */
.schedule-cell {
    background: #080a0e;
    min-height: 56px;
    padding: 3px;
}

.schedule-cell:nth-child(even) {
    background: #0c0f14;
}

.schedule-cell.hidden {
    display: none;
}

.rooms-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
    height: 100%;
}

/* ========== БЛОК УРОКА ========== */
.lesson-block {
    position: relative;
    background: #12161e;
    border: 1px solid #2d3544;
    border-radius: 5px;
    min-height: 56px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.lesson-block.hidden {
    display: none;
}

.block-room {
    position: absolute;
    top: 2px;
    right: 4px;
    font-size: 0.55rem;
    color: #4b5563;
    font-weight: 700;
    pointer-events: none;
    z-index: 1;
}

/* Заголовок блока — редактируемый */
.block-title {
    min-height: 20px;
    padding: 3px 14px 3px 6px;
    font-size: 0.72rem;
    font-weight: 700;
    text-align: center;
    color: #e5e7eb;
    background: rgba(255, 255, 255, 0.05);
    border-bottom: 1px solid #2d3544;
    cursor: text;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.block-title.is-empty::before {
    content: '·';
    color: #4b5563;
}

.block-title:hover {
    background: rgba(255, 255, 255, 0.09);
}

/* Цвет блока (по заголовку) */
.lesson-block.bc1 .block-title { background: rgba(20, 184, 166, 0.3); }
.lesson-block.bc2 .block-title { background: rgba(168, 85, 247, 0.3); }
.lesson-block.bc3 .block-title { background: rgba(59, 130, 246, 0.3); }
.lesson-block.bc4 .block-title { background: rgba(249, 115, 22, 0.3); }
.lesson-block.bc5 .block-title { background: rgba(236, 72, 153, 0.3); }
.lesson-block.bc6 .block-title { background: rgba(234, 179, 8, 0.3); }
.lesson-block.bc7 .block-title { background: rgba(34, 197, 94, 0.3); }
.lesson-block.bc8 .block-title { background: rgba(239, 68, 68, 0.3); }

/* Сетка ячеек учеников: 2 колонки, как в таблице */
.block-cells {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1px;
    padding: 2px;
    flex: 1;
    align-content: start;
}

/* Ячейка ученика */
.pcell {
    min-height: 20px;
    border-radius: 3px;
    padding: 2px 5px;
    font-size: 0.72rem;
    color: #ecfdf5;
    cursor: text;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid transparent;
    transition: border-color 0.12s, background 0.12s;
}

.pcell:hover {
    border-color: rgba(255, 255, 255, 0.2);
}

.pcell-empty {
    background: transparent;
    border: 1px dashed rgba(255, 255, 255, 0.07);
}

.pcell-empty:hover {
    border-color: rgba(20, 184, 166, 0.5);
}

/* Цвета ячеек — палитра преподавателей (тот же вид, что раньше) */
.pcell.c1 { background: linear-gradient(135deg, rgba(20, 184, 166, 0.35), rgba(20, 184, 166, 0.15)); border-left: 3px solid rgba(20, 184, 166, 0.9); }
.pcell.c2 { background: linear-gradient(135deg, rgba(168, 85, 247, 0.35), rgba(168, 85, 247, 0.15)); border-left: 3px solid rgba(168, 85, 247, 0.9); }
.pcell.c3 { background: linear-gradient(135deg, rgba(59, 130, 246, 0.35), rgba(59, 130, 246, 0.15)); border-left: 3px solid rgba(59, 130, 246, 0.9); }
.pcell.c4 { background: linear-gradient(135deg, rgba(249, 115, 22, 0.35), rgba(249, 115, 22, 0.15)); border-left: 3px solid rgba(249, 115, 22, 0.9); }
.pcell.c5 { background: linear-gradient(135deg, rgba(236, 72, 153, 0.35), rgba(236, 72, 153, 0.15)); border-left: 3px solid rgba(236, 72, 153, 0.9); }
.pcell.c6 { background: linear-gradient(135deg, rgba(234, 179, 8, 0.35), rgba(234, 179, 8, 0.15)); border-left: 3px solid rgba(234, 179, 8, 0.9); }
.pcell.c7 { background: linear-gradient(135deg, rgba(34, 197, 94, 0.35), rgba(34, 197, 94, 0.15)); border-left: 3px solid rgba(34, 197, 94, 0.9); }
.pcell.c8 { background: linear-gradient(135deg, rgba(239, 68, 68, 0.35), rgba(239, 68, 68, 0.15)); border-left: 3px solid rgba(239, 68, 68, 0.9); }

/* Поле ввода внутри ячейки/заголовка */
.pcell input,
.block-title input {
    width: 100%;
    background: transparent;
    border: none;
    outline: none;
    color: inherit;
    font: inherit;
    padding: 0;
    margin: 0;
}

.pcell.editing,
.block-title.editing {
    border-color: var(--accent) !important;
    background: rgba(20, 184, 166, 0.08);
}

/* ========== КОНТЕКСТНОЕ МЕНЮ ========== */
.context-menu {
    display: none;
    position: fixed;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 4px;
    min-width: 190px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    z-index: 10001;
}

.context-menu.active {
    display: block;
}

.context-menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    color: var(--text-primary);
    font-size: 0.85rem;
    transition: all 0.15s;
}

.context-menu-item:hover {
    background: var(--bg-hover);
}

.context-menu-item.danger:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}

.context-menu-item .swatch {
    width: 14px;
    height: 14px;
    border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.25);
    flex-shrink: 0;
}

.context-menu-divider {
    height: 1px;
    background: var(--border);
    margin: 4px 0;
}

/* ========== УВЕДОМЛЕНИЯ ========== */
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
    pointer-events: none;
}

.notification.show {
    opacity: 1;
    transform: translateY(0);
}

.notification-success { border-left: 4px solid #22c55e; }
.notification-error { border-left: 4px solid #f43f5e; }
.notification-success .material-icons { color: #22c55e; }
.notification-error .material-icons { color: #f43f5e; }
.notification .material-icons { font-size: 20px; }

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

        <span class="student-count">Учеников: <strong id="studentCount">0</strong></span>

        <span class="edit-hint">
            <span class="material-icons">edit</span>
            Клик — редактировать · ПКМ — цвет/удалить
        </span>
    </div>
</div>

<!-- Легенда преподавателей -->
<div class="teacher-legend">
    <span style="color: #9ca3af; font-size: 0.75rem; margin-right: 4px;">Преподаватели:</span>
    <?php foreach ($teachers as $teacher):
        $colorIndex = ($teacher['id'] % 8) ?: 8;
    ?>
        <div class="teacher-legend-item">
            <div class="teacher-color-box tcolor-<?= $colorIndex ?>"></div>
            <span><?= e($teacher['display_name'] ?: $teacher['name']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<!-- Сетка расписания -->
<div class="planner-container">
    <div class="planner-wrapper">
        <!-- Будни (Пн-Пт): 15:00-21:00 -->
        <div class="planner-section" id="weekdaysSection">
            <div class="planner-grid weekdays" id="weekdaysGrid">
                <div class="grid-header time-header">Время</div>
                <?php for ($d = 1; $d <= 5; $d++): ?>
                    <div class="grid-header day-header" data-day="<?= $d ?>"><?= $daysOfWeek[$d] ?></div>
                <?php endfor; ?>

                <?php for ($hour = 15; $hour <= 21; $hour++):
                    $time = sprintf('%02d:00', $hour);
                ?>
                    <div class="time-cell"><?= $time ?></div>
                    <?php for ($dayNum = 1; $dayNum <= 5; $dayNum++): ?>
                        <div class="schedule-cell" data-day="<?= $dayNum ?>" data-time="<?= $time ?>">
                            <div class="rooms-container">
                                <?php for ($room = 1; $room <= 3; $room++) renderLessonBlock($dayNum, $time, $room, $blocks); ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Выходные (Сб-Вс): 08:00-21:00 -->
        <div class="planner-section" id="weekendsSection">
            <div class="planner-grid weekends" id="weekendsGrid">
                <div class="grid-header time-header">Время</div>
                <?php for ($d = 6; $d <= 7; $d++): ?>
                    <div class="grid-header day-header" data-day="<?= $d ?>"><?= $daysOfWeek[$d] ?></div>
                <?php endfor; ?>

                <?php for ($hour = 8; $hour <= 21; $hour++):
                    $time = sprintf('%02d:00', $hour);
                ?>
                    <div class="time-cell"><?= $time ?></div>
                    <?php for ($dayNum = 6; $dayNum <= 7; $dayNum++): ?>
                        <div class="schedule-cell" data-day="<?= $dayNum ?>" data-time="<?= $time ?>">
                            <div class="rooms-container">
                                <?php for ($room = 1; $room <= 3; $room++) renderLessonBlock($dayNum, $time, $room, $blocks); ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<!-- Контекстное меню (наполняется JS) -->
<div id="contextMenu" class="context-menu"></div>

<!-- Уведомления -->
<div id="notification" class="notification">
    <span class="material-icons">check_circle</span>
    <span id="notification-text"></span>
</div>

<script>
const teachersData = <?= json_encode($teachers, JSON_UNESCAPED_UNICODE) ?>;

// ========== SIDEBAR TOGGLE ==========

function applyLayoutStyles() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    if (!mainContent || !sidebar) return;

    const sidebarWidth = sidebar.classList.contains('collapsed') ? 60 : 220;
    mainContent.style.marginLeft = sidebarWidth + 'px';
    mainContent.style.width = 'calc(100vw - ' + sidebarWidth + 'px)';
    mainContent.style.maxWidth = 'calc(100vw - ' + sidebarWidth + 'px)';
}

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const layout = document.querySelector('.layout');
    sidebar.classList.toggle('collapsed');
    layout.classList.toggle('sidebar-collapsed');
    localStorage.setItem('plannerSidebarCollapsed', sidebar.classList.contains('collapsed'));
    applyLayoutStyles();
}

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const layout = document.querySelector('.layout');

    if (sidebar && layout) {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'sidebar-toggle';
        toggleBtn.innerHTML = '<span class="material-icons">chevron_left</span>';
        toggleBtn.onclick = toggleSidebar;
        sidebar.appendChild(toggleBtn);

        if (localStorage.getItem('plannerSidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            layout.classList.add('sidebar-collapsed');
        }
    }

    applyLayoutStyles();
    restoreFilters();
    updateGridColumns();
    updateStudentCount();
});

// ========== РЕДАКТИРОВАНИЕ ЯЧЕЕК ==========

let editingEl = null;

document.addEventListener('click', function(e) {
    const cell = e.target.closest('.pcell, .block-title');
    if (cell && !cell.querySelector('input')) {
        beginEdit(cell);
    }
});

function beginEdit(cell) {
    if (editingEl && editingEl !== cell) {
        // Сохраняем предыдущую ячейку
        const input = editingEl.querySelector('input');
        if (input) input.blur();
    }

    const original = cell.dataset.id ? cell.textContent : '';
    let cancelled = false;

    const input = document.createElement('input');
    input.type = 'text';
    input.maxLength = 160;
    input.value = original.trim();

    cell.classList.add('editing');
    cell.classList.remove('is-empty');
    cell.textContent = '';
    cell.appendChild(input);
    editingEl = cell;

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            input.blur();
        } else if (e.key === 'Escape') {
            cancelled = true;
            input.blur();
        } else if (e.key === 'Tab') {
            e.preventDefault();
            const next = nextEditable(cell);
            input.blur();
            if (next) beginEdit(next);
        }
        e.stopPropagation();
    });

    input.addEventListener('blur', function() {
        cell.classList.remove('editing');
        editingEl = null;
        if (cancelled) {
            renderCellContent(cell, original.trim());
            return;
        }
        commitEdit(cell, input.value.trim(), original.trim());
    });

    input.focus();
    input.select();
}

function nextEditable(cell) {
    const block = cell.closest('.lesson-block');
    if (!block) return null;
    const cells = Array.from(block.querySelectorAll('.pcell'));
    if (cell.classList.contains('block-title')) {
        return cells[0] || null;
    }
    const idx = cells.indexOf(cell);
    return cells[idx + 1] || null;
}

function renderCellContent(cell, text) {
    cell.textContent = text;
    if (cell.dataset.kind === 'title') {
        cell.classList.toggle('is-empty', text === '');
    } else if (!cell.dataset.id && text === '') {
        cell.classList.add('pcell-empty');
    }
}

async function commitEdit(cell, value, original) {
    // Ничего не изменилось
    if (value === original) {
        renderCellContent(cell, original);
        return;
    }

    // Пустая новая ячейка — просто закрываем
    if (value === '' && !cell.dataset.id) {
        renderCellContent(cell, '');
        return;
    }

    const block = cell.closest('.lesson-block');
    const payload = {
        id: cell.dataset.id ? parseInt(cell.dataset.id) : null,
        day: parseInt(block.dataset.day),
        time: block.dataset.time,
        room: parseInt(block.dataset.room),
        kind: cell.dataset.kind || 'student',
        content: value
    };

    try {
        const response = await fetch('/zarplata/api/planner.php?action=save_note', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();

        if (!result.success) {
            showNotification(result.error || 'Ошибка сохранения', 'error');
            renderCellContent(cell, original);
            return;
        }

        if (value === '') {
            // Удаление записи
            if (cell.dataset.kind === 'title') {
                delete cell.dataset.id;
                renderCellContent(cell, '');
            } else {
                cell.remove();
            }
        } else {
            if (result.data && result.data.id) {
                cell.dataset.id = result.data.id;
            }
            renderCellContent(cell, value);

            // Если заполнили пустую ячейку — добавляем новую пустую в конец блока
            if (cell.classList.contains('pcell-empty')) {
                cell.classList.remove('pcell-empty');
                const emptyCell = document.createElement('div');
                emptyCell.className = 'pcell pcell-empty';
                emptyCell.dataset.kind = 'student';
                block.querySelector('.block-cells').appendChild(emptyCell);
            }
        }

        updateStudentCount();
    } catch (err) {
        console.error('Save error:', err);
        showNotification('Ошибка сети', 'error');
        renderCellContent(cell, original);
    }
}

// ========== КОНТЕКСТНОЕ МЕНЮ (цвет + удаление) ==========

let contextTarget = null;

document.addEventListener('contextmenu', function(e) {
    const cell = e.target.closest('.pcell, .block-title');
    if (!cell || !cell.dataset.id) return;

    e.preventDefault();
    contextTarget = cell;

    const menu = document.getElementById('contextMenu');
    let html = '';

    teachersData.forEach(t => {
        const colorIndex = (t.id % 8) || 8;
        const name = t.display_name || t.name;
        html += `<div class="context-menu-item" onclick="setCellColor(${colorIndex})">
                    <span class="swatch tcolor-${colorIndex}"></span>${escapeHtml(name)}
                 </div>`;
    });
    html += `<div class="context-menu-item" onclick="setCellColor(0)">
                <span class="swatch" style="background: rgba(255,255,255,0.08);"></span>Без цвета
             </div>`;
    html += '<div class="context-menu-divider"></div>';
    html += `<div class="context-menu-item danger" onclick="deleteCell()">
                <span class="material-icons" style="font-size: 16px;">delete</span>Удалить
             </div>`;

    menu.innerHTML = html;
    menu.classList.add('active');

    let x = e.clientX, y = e.clientY;
    const rect = menu.getBoundingClientRect();
    if (x + rect.width > window.innerWidth) x = window.innerWidth - rect.width - 10;
    if (y + rect.height > window.innerHeight) y = window.innerHeight - rect.height - 10;
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.context-menu')) hideContextMenu();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') hideContextMenu();
});

function hideContextMenu() {
    document.getElementById('contextMenu').classList.remove('active');
    contextTarget = null;
}

function escapeHtml(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

async function setCellColor(color) {
    if (!contextTarget || !contextTarget.dataset.id) return hideContextMenu();
    const cell = contextTarget;
    hideContextMenu();

    try {
        const response = await fetch('/zarplata/api/planner.php?action=set_note_color', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(cell.dataset.id), color: color })
        });
        const result = await response.json();

        if (result.success) {
            if (cell.dataset.kind === 'title') {
                const block = cell.closest('.lesson-block');
                block.className = block.className.replace(/\bbc\d+\b/g, '').trim() + ' bc' + color;
            } else {
                cell.className = cell.className.replace(/\bc\d+\b/g, '').trim() + ' c' + color;
            }
        } else {
            showNotification(result.error || 'Ошибка', 'error');
        }
    } catch (err) {
        showNotification('Ошибка сети', 'error');
    }
}

async function deleteCell() {
    if (!contextTarget || !contextTarget.dataset.id) return hideContextMenu();
    const cell = contextTarget;
    hideContextMenu();

    const block = cell.closest('.lesson-block');
    const payload = {
        id: parseInt(cell.dataset.id),
        day: parseInt(block.dataset.day),
        time: block.dataset.time,
        room: parseInt(block.dataset.room),
        kind: cell.dataset.kind || 'student',
        content: ''
    };

    try {
        const response = await fetch('/zarplata/api/planner.php?action=save_note', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();

        if (result.success) {
            if (cell.dataset.kind === 'title') {
                delete cell.dataset.id;
                renderCellContent(cell, '');
            } else {
                cell.remove();
            }
            updateStudentCount();
        } else {
            showNotification(result.error || 'Ошибка удаления', 'error');
        }
    } catch (err) {
        showNotification('Ошибка сети', 'error');
    }
}

// ========== СЧЁТЧИК УЧЕНИКОВ ==========

function updateStudentCount() {
    const unique = new Set();
    document.querySelectorAll('.pcell[data-id]').forEach(cell => {
        const name = cell.textContent.trim().toLowerCase();
        if (name) unique.add(name);
    });
    document.getElementById('studentCount').textContent = unique.size;
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

    const weekdaysSection = document.getElementById('weekdaysSection');
    const weekendsSection = document.getElementById('weekendsSection');
    const hasWeekdays = activeDays.some(d => d >= 1 && d <= 5);
    const hasWeekends = activeDays.some(d => d >= 6 && d <= 7);

    if (activeDays.length === 0) {
        weekdaysSection.style.display = '';
        weekendsSection.style.display = '';
    } else {
        weekdaysSection.style.display = hasWeekdays ? '' : 'none';
        weekendsSection.style.display = hasWeekends ? '' : 'none';
    }

    document.querySelectorAll('.grid-header.day-header').forEach(header => {
        const day = parseInt(header.dataset.day);
        header.classList.toggle('hidden', activeDays.length > 0 && !activeDays.includes(day));
    });

    document.querySelectorAll('.schedule-cell').forEach(cell => {
        const day = parseInt(cell.dataset.day);
        cell.classList.toggle('hidden', activeDays.length > 0 && !activeDays.includes(day));
    });
}

function updateGridColumns() {
    const activeWeekdays = document.querySelectorAll('.day-filter-btn.active[data-day="1"], .day-filter-btn.active[data-day="2"], .day-filter-btn.active[data-day="3"], .day-filter-btn.active[data-day="4"], .day-filter-btn.active[data-day="5"]').length;
    const visibleWeekdays = activeWeekdays === 0 ? 5 : activeWeekdays;
    const weekdaysGrid = document.getElementById('weekdaysGrid');
    if (weekdaysGrid) {
        weekdaysGrid.style.gridTemplateColumns = `56px repeat(${visibleWeekdays}, minmax(216px, 1fr))`;
    }

    const activeWeekends = document.querySelectorAll('.day-filter-btn.active[data-day="6"], .day-filter-btn.active[data-day="7"]').length;
    const visibleWeekends = activeWeekends === 0 ? 2 : activeWeekends;
    const weekendsGrid = document.getElementById('weekendsGrid');
    if (weekendsGrid) {
        weekendsGrid.style.gridTemplateColumns = `56px repeat(${visibleWeekends}, minmax(216px, 1fr))`;
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

    document.querySelectorAll('.lesson-block').forEach(block => {
        const room = parseInt(block.dataset.room);
        block.classList.toggle('hidden', activeRooms.length > 0 && !activeRooms.includes(room));
    });

    const visibleCount = activeRooms.length === 0 ? 3 : activeRooms.length;
    document.querySelectorAll('.rooms-container').forEach(container => {
        container.style.gridTemplateColumns = `repeat(${visibleCount}, 1fr)`;
    });
}

function saveFilters() {
    const filters = {
        days: Array.from(document.querySelectorAll('.day-filter-btn.active')).map(btn => btn.dataset.day),
        rooms: Array.from(document.querySelectorAll('.room-filter-btn.active')).map(btn => btn.dataset.room)
    };
    localStorage.setItem('plannerFilters', JSON.stringify(filters));
}

function restoreFilters() {
    const saved = localStorage.getItem('plannerFilters');
    if (!saved) return;

    try {
        const filters = JSON.parse(saved);

        if (filters.days && filters.days.length > 0 && filters.days.length < 7) {
            document.querySelectorAll('.day-filter-btn').forEach(btn => {
                btn.classList.toggle('active', filters.days.includes(btn.dataset.day));
            });
            updateVisibleDays();
        }

        if (filters.rooms && filters.rooms.length > 0 && filters.rooms.length < 3) {
            document.querySelectorAll('.room-filter-btn').forEach(btn => {
                btn.classList.toggle('active', filters.rooms.includes(btn.dataset.room));
            });
            updateVisibleRooms();
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
    setTimeout(() => notification.classList.remove('show'), 3000);
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
