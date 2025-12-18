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
    background: var(--bg-card);
    min-height: 50px;
    padding: 3px;
    display: flex;
    flex-direction: column;
    gap: 2px;
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
    background: var(--bg-elevated);
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
    color: var(--text-muted);
    text-align: center;
    padding: 1px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 2px;
}

/* Карточка ученика */
.student-card {
    background: linear-gradient(135deg, #1a2a3a 0%, #0d1a26 100%);
    border: 1px solid var(--accent);
    border-left: 3px solid var(--accent);
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
    border-color: var(--accent-hover);
    background: linear-gradient(135deg, #1f3344 0%, #122533 100%);
}

.student-card:active {
    cursor: grabbing;
}

.student-card.dragging {
    opacity: 0.5;
    transform: rotate(2deg);
}

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
    color: var(--text-primary);
}

.student-class {
    color: var(--accent);
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
    </div>
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
                                            <?php foreach ($cellData['students'] as $student): ?>
                                                <div class="student-card"
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
                                            <?php foreach ($cellData['students'] as $student): ?>
                                                <div class="student-card"
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
            if (cardToMove) {
                cardToMove.dataset.day = toDay;
                cardToMove.dataset.time = toTime;
                cardToMove.dataset.room = toRoom;
                slot.appendChild(cardToMove);
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
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
