<?php
/**
 * Mobile Planner Page - Table View
 * Two sections: Weekdays (15:00-21:00) and Weekends (08:00-21:00)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/student_helpers.php';

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
    }
}

function getTeacherColor($index) {
    $colors = [
        1 => 'rgba(20, 184, 166, 0.8)',
        2 => 'rgba(168, 85, 247, 0.8)',
        3 => 'rgba(59, 130, 246, 0.8)',
        4 => 'rgba(249, 115, 22, 0.8)',
        5 => 'rgba(236, 72, 153, 0.8)',
        6 => 'rgba(234, 179, 8, 0.8)',
        7 => 'rgba(34, 197, 94, 0.8)',
        8 => 'rgba(239, 68, 68, 0.8)',
    ];
    return $colors[$index] ?? $colors[1];
}

function getTeacherBg($index) {
    $colors = [
        1 => 'rgba(20, 184, 166, 0.25)',
        2 => 'rgba(168, 85, 247, 0.25)',
        3 => 'rgba(59, 130, 246, 0.25)',
        4 => 'rgba(249, 115, 22, 0.25)',
        5 => 'rgba(236, 72, 153, 0.25)',
        6 => 'rgba(234, 179, 8, 0.25)',
        7 => 'rgba(34, 197, 94, 0.25)',
        8 => 'rgba(239, 68, 68, 0.25)',
    ];
    return $colors[$index] ?? $colors[1];
}

define('PAGE_TITLE', 'Планировщик');
define('ACTIVE_PAGE', 'planner');

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* Container */
.planner-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - var(--header-height) - var(--bottom-nav-height) - var(--safe-area-bottom));
    overflow: hidden;
}

/* Filters */
.filters-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 8px 12px;
    background: var(--bg-card);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 4px;
}

.filter-label {
    font-size: 10px;
    color: var(--text-muted);
    margin-right: 2px;
}

.day-btn {
    min-width: 28px;
    height: 26px;
    padding: 0 6px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.15s ease;
}

.day-btn.active {
    background: var(--accent-dim);
    color: var(--accent);
    border-color: var(--accent);
}

.room-btn {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.15s ease;
}

.room-btn.active {
    background: var(--accent-dim);
    color: var(--accent);
    border-color: var(--accent);
}

/* Teacher legend */
.teacher-legend {
    display: flex;
    overflow-x: auto;
    gap: 8px;
    padding: 6px 12px;
    background: var(--bg-card);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.teacher-legend::-webkit-scrollbar { display: none; }

.legend-item {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    font-size: 10px;
    color: var(--text-muted);
}

.legend-color {
    width: 10px;
    height: 10px;
    border-radius: 2px;
}

/* Sections wrapper */
.sections-wrapper {
    flex: 1;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    padding: 8px;
}

/* Section */
.planner-section {
    margin-bottom: 16px;
}

.section-title {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    padding: 6px 8px;
    background: var(--bg-elevated);
    border-radius: 6px;
    margin-bottom: 8px;
}

/* Grid wrapper for horizontal scroll */
.grid-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 6px;
}

/* Schedule grid */
.schedule-grid {
    display: grid;
    gap: 1px;
    background: var(--border);
    border-radius: 6px;
    overflow: hidden;
    min-width: max-content;
}

.schedule-grid.weekdays {
    grid-template-columns: 40px repeat(5, 90px);
}

.schedule-grid.weekends {
    grid-template-columns: 40px repeat(2, 90px);
}

/* Grid header */
.grid-header {
    background: var(--bg-card);
    padding: 6px 4px;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
}

.grid-header.time-header {
    color: var(--text-muted);
}

.grid-header.day-header {
    color: var(--text-secondary);
}

.grid-header.hidden {
    display: none;
}

/* Time cell */
.time-cell {
    background: var(--bg-card);
    padding: 4px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    font-weight: 600;
    color: var(--accent);
    text-align: center;
    display: flex;
    align-items: flex-start;
    justify-content: center;
}

/* Schedule cell */
.schedule-cell {
    background: var(--bg-dark);
    padding: 3px;
    min-height: 50px;
}

.schedule-cell.hidden {
    display: none;
}

/* Room slots */
.room-slots {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
}

.room-slot {
    background: var(--bg-card);
    border-radius: 3px;
    padding: 2px;
    min-height: 30px;
}

.room-slot.hidden {
    display: none;
}

.room-slot-header {
    font-size: 7px;
    color: var(--text-muted);
    text-align: center;
    padding: 1px 0;
    background: var(--bg-elevated);
    border-radius: 2px;
    margin-bottom: 2px;
}

/* Student cards */
.student-card {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 2px 3px;
    margin-bottom: 2px;
    border-radius: 3px;
    border-left: 2px solid var(--accent);
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 9px;
}

.student-card:last-child {
    margin-bottom: 0;
}

.student-card:active {
    opacity: 0.7;
}

.student-card.selected {
    box-shadow: 0 0 0 2px var(--accent);
}

.student-tier {
    font-size: 7px;
    font-weight: 700;
    padding: 1px 2px;
    border-radius: 2px;
    flex-shrink: 0;
}

.tier-S { background: #ff9999; color: #000; }
.tier-A { background: #ffcc99; color: #000; }
.tier-B { background: #ffff99; color: #000; }
.tier-C { background: #ccff99; color: #000; }
.tier-D { background: #99ff99; color: #000; }

.student-name {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-primary);
}

.student-class {
    font-size: 8px;
    color: var(--text-muted);
    flex-shrink: 0;
}

/* Move modal */
.move-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 200;
    align-items: flex-end;
}

.move-modal.active {
    display: flex;
}

.move-sheet {
    width: 100%;
    max-height: 70vh;
    background: var(--bg-card);
    border-radius: 16px 16px 0 0;
    padding: 16px;
    padding-bottom: calc(16px + var(--safe-area-bottom));
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.move-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    position: sticky;
    top: 0;
    background: var(--bg-card);
    padding: 4px 0;
    z-index: 5;
}

.move-title {
    font-size: 16px;
    font-weight: 700;
}

.move-close {
    min-width: 80px;
    height: 44px;
    border-radius: 8px;
    background: var(--status-rose);
    border: none;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
    z-index: 10;
    pointer-events: auto;
}

.move-close:active {
    transform: scale(0.95);
    opacity: 0.8;
}

.move-info {
    background: var(--bg-elevated);
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 12px;
    font-size: 13px;
}

.move-section-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 12px;
    text-align: center;
}

.move-step {
    margin-bottom: 12px;
}

.move-step-back {
    font-size: 13px;
    color: var(--accent);
    margin-bottom: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Day selection grid - large buttons */
.move-days-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.move-day-btn {
    padding: 16px 8px;
    border-radius: 12px;
    background: var(--bg-elevated);
    border: 2px solid var(--border);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: center;
}

.move-day-btn .day-short {
    font-size: 16px;
    font-weight: 700;
    display: block;
}

.move-day-btn .day-full {
    font-size: 10px;
    color: var(--text-muted);
    display: block;
    margin-top: 2px;
}

.move-day-btn:active {
    background: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
}

.move-day-btn.current {
    opacity: 0.4;
    border-style: dashed;
}

/* Time/room selection grid */
.move-times-grid {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-height: 50vh;
    overflow-y: auto;
}

.move-time-row {
    display: grid;
    grid-template-columns: 50px 1fr 1fr 1fr;
    gap: 6px;
    align-items: center;
}

.move-time-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    font-weight: 600;
    color: var(--accent);
}

.move-room-btn {
    padding: 14px 8px;
    border-radius: 10px;
    background: var(--bg-elevated);
    border: 2px solid var(--border);
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: center;
}

.move-room-btn:active {
    background: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
}

.move-room-btn.current {
    opacity: 0.3;
    pointer-events: none;
    border-style: dashed;
}

/* Toast */
.toast {
    position: fixed;
    bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + 16px);
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: var(--bg-elevated);
    color: var(--text-primary);
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 13px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    z-index: 300;
    opacity: 0;
    transition: all 0.3s ease;
}

.toast.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}

.toast.success { border-left: 3px solid var(--status-green); }
.toast.error { border-left: 3px solid var(--status-rose); }
</style>

<div class="planner-container">
    <!-- Filters -->
    <div class="filters-bar">
        <div class="filter-group">
            <span class="filter-label">Дни:</span>
            <?php for ($d = 1; $d <= 7; $d++): ?>
                <button class="day-btn active" data-day="<?= $d ?>" onclick="toggleDay(<?= $d ?>)">
                    <?= $daysOfWeek[$d]['short'] ?>
                </button>
            <?php endfor; ?>
        </div>
        <div class="filter-group">
            <span class="filter-label">Каб:</span>
            <?php for ($r = 1; $r <= 3; $r++): ?>
                <button class="room-btn active" data-room="<?= $r ?>" onclick="toggleRoom(<?= $r ?>)"><?= $r ?></button>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Teacher legend -->
    <div class="teacher-legend">
        <?php foreach ($teachers as $teacher):
            $colorIndex = ($teacher['id'] % 8) ?: 8;
        ?>
            <div class="legend-item">
                <div class="legend-color" style="background: <?= getTeacherColor($colorIndex) ?>;"></div>
                <span><?= e($teacher['display_name'] ?: $teacher['name']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Sections -->
    <div class="sections-wrapper">
        <!-- Weekdays section: 15:00-21:00 -->
        <div class="planner-section" id="weekdaysSection">
            <div class="section-title">Будни (15:00 - 21:00)</div>
            <div class="grid-wrapper">
            <div class="schedule-grid weekdays" id="weekdaysGrid">
                <!-- Headers -->
                <div class="grid-header time-header"></div>
                <?php for ($d = 1; $d <= 5; $d++): ?>
                    <div class="grid-header day-header" data-day="<?= $d ?>"><?= $daysOfWeek[$d]['short'] ?></div>
                <?php endfor; ?>

                <!-- Time rows: 15:00-21:00 -->
                <?php for ($hour = 15; $hour <= 21; $hour++):
                    $time = sprintf('%02d:00', $hour);
                ?>
                    <div class="time-cell"><?= $time ?></div>
                    <?php for ($d = 1; $d <= 5; $d++): ?>
                        <div class="schedule-cell" data-day="<?= $d ?>">
                            <div class="room-slots">
                                <?php for ($room = 1; $room <= 3; $room++):
                                    $key = "{$d}_{$time}_{$room}";
                                    $cellData = $scheduleGrid[$key] ?? null;
                                ?>
                                    <div class="room-slot" data-room="<?= $room ?>" data-day="<?= $d ?>" data-time="<?= $time ?>">
                                        <div class="room-slot-header"><?= $room ?></div>
                                        <?php if ($cellData && !empty($cellData['students'])): ?>
                                            <?php foreach ($cellData['students'] as $student):
                                                $colorIndex = ($student['teacher_id'] % 8) ?: 8;
                                            ?>
                                                <div class="student-card"
                                                     style="background: <?= getTeacherBg($colorIndex) ?>; border-left-color: <?= getTeacherColor($colorIndex) ?>;"
                                                     data-student-id="<?= $student['id'] ?>"
                                                     data-student-name="<?= e($student['name']) ?>"
                                                     data-day="<?= $d ?>"
                                                     data-time="<?= $time ?>"
                                                     data-room="<?= $room ?>"
                                                     data-teacher-id="<?= $student['teacher_id'] ?>"
                                                     onclick="selectStudent(this)">
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

        <!-- Weekends section: 08:00-21:00 -->
        <div class="planner-section" id="weekendsSection">
            <div class="section-title">Выходные (08:00 - 21:00)</div>
            <div class="grid-wrapper">
            <div class="schedule-grid weekends" id="weekendsGrid">
                <!-- Headers -->
                <div class="grid-header time-header"></div>
                <?php for ($d = 6; $d <= 7; $d++): ?>
                    <div class="grid-header day-header" data-day="<?= $d ?>"><?= $daysOfWeek[$d]['short'] ?></div>
                <?php endfor; ?>

                <!-- Time rows: 08:00-21:00 -->
                <?php for ($hour = 8; $hour <= 21; $hour++):
                    $time = sprintf('%02d:00', $hour);
                ?>
                    <div class="time-cell"><?= $time ?></div>
                    <?php for ($d = 6; $d <= 7; $d++): ?>
                        <div class="schedule-cell" data-day="<?= $d ?>">
                            <div class="room-slots">
                                <?php for ($room = 1; $room <= 3; $room++):
                                    $key = "{$d}_{$time}_{$room}";
                                    $cellData = $scheduleGrid[$key] ?? null;
                                ?>
                                    <div class="room-slot" data-room="<?= $room ?>" data-day="<?= $d ?>" data-time="<?= $time ?>">
                                        <div class="room-slot-header"><?= $room ?></div>
                                        <?php if ($cellData && !empty($cellData['students'])): ?>
                                            <?php foreach ($cellData['students'] as $student):
                                                $colorIndex = ($student['teacher_id'] % 8) ?: 8;
                                            ?>
                                                <div class="student-card"
                                                     style="background: <?= getTeacherBg($colorIndex) ?>; border-left-color: <?= getTeacherColor($colorIndex) ?>;"
                                                     data-student-id="<?= $student['id'] ?>"
                                                     data-student-name="<?= e($student['name']) ?>"
                                                     data-day="<?= $d ?>"
                                                     data-time="<?= $time ?>"
                                                     data-room="<?= $room ?>"
                                                     data-teacher-id="<?= $student['teacher_id'] ?>"
                                                     onclick="selectStudent(this)">
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
</div>

<!-- Move modal - Step by step -->
<div class="move-modal" id="moveModal" onclick="if(event.target===this)closeModal()">
    <div class="move-sheet">
        <div class="move-header">
            <span class="move-title" id="moveTitle">Переместить</span>
            <button type="button" class="move-close" id="closeModalBtn">Закрыть</button>
        </div>
        <div class="move-info" id="moveInfo"></div>

        <!-- Step 1: Select day -->
        <div class="move-step" id="moveStep1">
            <div class="move-section-title">Выберите день</div>
            <div class="move-days-grid" id="moveDaysGrid"></div>
        </div>

        <!-- Step 2: Select time & room -->
        <div class="move-step" id="moveStep2" style="display:none;">
            <div class="move-step-back" onclick="backToStep1()">← Назад</div>
            <div class="move-section-title" id="moveStep2Title">Выберите время и кабинет</div>
            <div class="move-times-grid" id="moveTimesGrid"></div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
const daysOfWeek = <?= json_encode($daysOfWeek, JSON_UNESCAPED_UNICODE) ?>;
let selectedStudent = null;

// Toggle day visibility
function toggleDay(day) {
    const btn = document.querySelector(`.day-btn[data-day="${day}"]`);
    btn.classList.toggle('active');

    const isActive = btn.classList.contains('active');

    // Hide/show day header and cells
    document.querySelectorAll(`.day-header[data-day="${day}"], .schedule-cell[data-day="${day}"]`).forEach(el => {
        el.classList.toggle('hidden', !isActive);
    });

    // Update grid columns
    updateGridColumns();

    // Show/hide sections
    updateSectionVisibility();

    saveFilters();
}

// Toggle room visibility
function toggleRoom(room) {
    const btn = document.querySelector(`.room-btn[data-room="${room}"]`);
    btn.classList.toggle('active');

    const isActive = btn.classList.contains('active');
    document.querySelectorAll(`.room-slot[data-room="${room}"]`).forEach(el => {
        el.classList.toggle('hidden', !isActive);
    });

    saveFilters();
}

// Update grid columns based on visible days
function updateGridColumns() {
    const activeWeekdays = document.querySelectorAll('.day-btn.active[data-day="1"], .day-btn.active[data-day="2"], .day-btn.active[data-day="3"], .day-btn.active[data-day="4"], .day-btn.active[data-day="5"]').length;
    const activeWeekends = document.querySelectorAll('.day-btn.active[data-day="6"], .day-btn.active[data-day="7"]').length;

    const weekdaysGrid = document.getElementById('weekdaysGrid');
    const weekendsGrid = document.getElementById('weekendsGrid');

    if (weekdaysGrid && activeWeekdays > 0) {
        weekdaysGrid.style.gridTemplateColumns = `40px repeat(${activeWeekdays}, 1fr)`;
    }

    if (weekendsGrid && activeWeekends > 0) {
        weekendsGrid.style.gridTemplateColumns = `40px repeat(${activeWeekends}, 1fr)`;
    }
}

// Show/hide sections based on active days
function updateSectionVisibility() {
    const hasWeekdays = document.querySelectorAll('.day-btn.active[data-day="1"], .day-btn.active[data-day="2"], .day-btn.active[data-day="3"], .day-btn.active[data-day="4"], .day-btn.active[data-day="5"]').length > 0;
    const hasWeekends = document.querySelectorAll('.day-btn.active[data-day="6"], .day-btn.active[data-day="7"]').length > 0;

    document.getElementById('weekdaysSection').style.display = hasWeekdays ? '' : 'none';
    document.getElementById('weekendsSection').style.display = hasWeekends ? '' : 'none';
}

// Save filters to localStorage
function saveFilters() {
    const days = Array.from(document.querySelectorAll('.day-btn.active')).map(b => b.dataset.day);
    const rooms = Array.from(document.querySelectorAll('.room-btn.active')).map(b => b.dataset.room);
    localStorage.setItem('mobilePlannerFilters', JSON.stringify({ days, rooms }));
}

// Load filters from localStorage
function loadFilters() {
    try {
        const saved = JSON.parse(localStorage.getItem('mobilePlannerFilters'));
        if (saved && saved.days && saved.days.length > 0) {
            // Reset all
            document.querySelectorAll('.day-btn').forEach(b => {
                b.classList.remove('active');
            });
            document.querySelectorAll('.room-btn').forEach(b => {
                b.classList.remove('active');
            });

            // Apply saved days
            saved.days.forEach(d => {
                const btn = document.querySelector(`.day-btn[data-day="${d}"]`);
                if (btn) btn.classList.add('active');
            });

            // Apply saved rooms
            saved.rooms.forEach(r => {
                const btn = document.querySelector(`.room-btn[data-room="${r}"]`);
                if (btn) btn.classList.add('active');
            });

            // Update visibility
            document.querySelectorAll('.day-header, .schedule-cell').forEach(el => {
                const day = el.dataset.day;
                const isActive = saved.days.includes(day);
                el.classList.toggle('hidden', !isActive);
            });

            document.querySelectorAll('.room-slot').forEach(el => {
                const room = el.dataset.room;
                const isActive = saved.rooms.includes(room);
                el.classList.toggle('hidden', !isActive);
            });

            updateGridColumns();
            updateSectionVisibility();
        }
    } catch(e) {}
}

// Select student
function selectStudent(card) {
    document.querySelectorAll('.student-card.selected').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');

    selectedStudent = {
        id: card.dataset.studentId,
        name: card.dataset.studentName,
        day: parseInt(card.dataset.day),
        time: card.dataset.time,
        room: parseInt(card.dataset.room),
        teacherId: card.dataset.teacherId,
        element: card
    };

    showMoveModal();
}

let selectedMoveDay = null;

// Show move modal - Step 1: Day selection
function showMoveModal() {
    if (!selectedStudent) return;

    document.getElementById('moveInfo').innerHTML =
        `<strong>${selectedStudent.name}</strong><br>
         ${daysOfWeek[selectedStudent.day].name}, ${selectedStudent.time}, Каб. ${selectedStudent.room}`;

    // Reset to step 1
    document.getElementById('moveStep1').style.display = '';
    document.getElementById('moveStep2').style.display = 'none';
    selectedMoveDay = null;

    // Build day selection grid
    let daysHtml = '';
    for (let d = 1; d <= 7; d++) {
        const isCurrent = d === selectedStudent.day;
        daysHtml += `
            <button class="move-day-btn ${isCurrent ? 'current' : ''}" onclick="selectMoveDay(${d})">
                <span class="day-short">${daysOfWeek[d].short}</span>
                <span class="day-full">${daysOfWeek[d].name}</span>
            </button>
        `;
    }
    document.getElementById('moveDaysGrid').innerHTML = daysHtml;

    document.getElementById('moveModal').classList.add('active');
}

// Step 1 → Step 2: After selecting a day
function selectMoveDay(day) {
    selectedMoveDay = day;

    document.getElementById('moveStep1').style.display = 'none';
    document.getElementById('moveStep2').style.display = '';
    document.getElementById('moveStep2Title').textContent =
        `${daysOfWeek[day].name} — выберите время и кабинет`;

    // Determine time range based on day
    const isWeekend = day >= 6;
    const startHour = isWeekend ? 8 : 15;
    const endHour = 21;

    // Build time/room grid
    let timesHtml = '';
    for (let h = startHour; h <= endHour; h++) {
        const time = String(h).padStart(2, '0') + ':00';
        timesHtml += `<div class="move-time-row">`;
        timesHtml += `<div class="move-time-label">${time}</div>`;

        for (let r = 1; r <= 3; r++) {
            const isCurrent = day === selectedStudent.day &&
                             time === selectedStudent.time &&
                             r === selectedStudent.room;
            timesHtml += `
                <button class="move-room-btn ${isCurrent ? 'current' : ''}"
                        onclick="moveStudent(${day},'${time}',${r})">
                    Каб. ${r}
                </button>
            `;
        }
        timesHtml += '</div>';
    }
    document.getElementById('moveTimesGrid').innerHTML = timesHtml;
}

// Back to step 1
function backToStep1() {
    document.getElementById('moveStep1').style.display = '';
    document.getElementById('moveStep2').style.display = 'none';
    selectedMoveDay = null;
}

// Close modal
function closeModal() {
    console.log('closeModal called');
    try {
        const modal = document.getElementById('moveModal');
        console.log('Modal element:', modal);
        console.log('Modal classes before:', modal.className);
        modal.classList.remove('active');
        console.log('Modal classes after:', modal.className);

        document.querySelectorAll('.student-card.selected').forEach(c => c.classList.remove('selected'));
        selectedStudent = null;
        selectedMoveDay = null;
    } catch(e) {
        console.error('Error in closeModal:', e);
        alert('Error: ' + e.message);
    }
}

// Move student
async function moveStudent(toDay, toTime, toRoom) {
    if (!selectedStudent) return;

    try {
        const response = await fetch('/zarplata/api/planner.php?action=move_student', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                student_id: selectedStudent.id,
                from_day: selectedStudent.day,
                from_time: selectedStudent.time,
                from_room: selectedStudent.room,
                to_day: toDay,
                to_time: toTime,
                to_room: toRoom,
                teacher_id: selectedStudent.teacherId
            })
        });

        const result = await response.json();

        if (result.success) {
            showToast('Ученик перемещён', 'success');
            closeModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Ошибка', 'error');
        }
    } catch (error) {
        showToast('Ошибка сети', 'error');
    }
}

// Toast
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type + ' show';
    setTimeout(() => toast.classList.remove('show'), 2500);
}

// Init
loadFilters();

// Explicit event listeners for mobile touch
const closeBtn = document.getElementById('closeModalBtn');
closeBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    closeModal();
});
closeBtn.addEventListener('touchstart', function(e) {
    e.stopPropagation();
});
closeBtn.addEventListener('touchend', function(e) {
    e.preventDefault();
    e.stopPropagation();
    closeModal();
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
