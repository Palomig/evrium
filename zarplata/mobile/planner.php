<?php
/**
 * Mobile Planner Page - Table View
 * Compact table layout with all days, filters, tap-to-move
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
    padding: 10px 12px;
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
    font-size: 11px;
    color: var(--text-muted);
    margin-right: 4px;
}

.day-btn {
    min-width: 32px;
    height: 28px;
    padding: 0 8px;
    border-radius: 6px;
    font-size: 12px;
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
    width: 28px;
    height: 28px;
    border-radius: 6px;
    font-size: 12px;
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

/* Teacher legend - compact */
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

/* Table wrapper */
.table-wrapper {
    flex: 1;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
}

/* Schedule table */
.schedule-table {
    min-width: 100%;
    border-collapse: collapse;
}

.schedule-table th,
.schedule-table td {
    border: 1px solid var(--border);
    vertical-align: top;
}

/* Header cells */
.schedule-table th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: var(--bg-card);
    padding: 6px 4px;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    text-align: center;
    white-space: nowrap;
}

.schedule-table th.time-col {
    position: sticky;
    left: 0;
    z-index: 20;
    width: 45px;
    min-width: 45px;
    background: var(--bg-card);
}

.schedule-table th.day-col {
    min-width: 80px;
}

/* Time cells */
.schedule-table td.time-cell {
    position: sticky;
    left: 0;
    z-index: 5;
    background: var(--bg-card);
    padding: 4px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    font-weight: 600;
    color: var(--accent);
    text-align: center;
    white-space: nowrap;
}

/* Day cells */
.schedule-table td.day-cell {
    padding: 3px;
    min-width: 80px;
    background: var(--bg-dark);
}

.schedule-table td.day-cell:nth-child(even) {
    background: var(--bg-elevated);
}

/* Room slots */
.room-slots {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.room-slot {
    background: var(--bg-card);
    border-radius: 4px;
    padding: 2px;
    min-height: 24px;
}

.room-slot-header {
    font-size: 9px;
    color: var(--text-muted);
    text-align: center;
    padding: 1px 0;
    border-bottom: 1px solid var(--border);
    margin-bottom: 2px;
}

/* Student cards - compact */
.student-card {
    display: flex;
    align-items: center;
    gap: 3px;
    padding: 3px 4px;
    margin-bottom: 2px;
    border-radius: 3px;
    border-left: 2px solid var(--accent);
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 10px;
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
    font-size: 8px;
    font-weight: 700;
    padding: 1px 3px;
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
    font-size: 9px;
    color: var(--text-muted);
    flex-shrink: 0;
}

/* Hidden elements */
.day-col.hidden,
.day-cell.hidden,
.room-slot.hidden {
    display: none !important;
}

/* Empty slot message */
.empty-slot {
    font-size: 9px;
    color: var(--text-muted);
    text-align: center;
    padding: 4px;
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
}

.move-title {
    font-size: 16px;
    font-weight: 700;
}

.move-close {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--bg-elevated);
    border: none;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.move-info {
    background: var(--bg-elevated);
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 12px;
    font-size: 13px;
}

.move-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    margin-bottom: 12px;
}

.move-day-header {
    font-size: 10px;
    font-weight: 600;
    color: var(--text-muted);
    text-align: center;
    padding: 4px;
}

.move-times {
    max-height: 200px;
    overflow-y: auto;
}

.move-time-row {
    display: grid;
    grid-template-columns: 45px repeat(7, 1fr);
    gap: 3px;
    margin-bottom: 3px;
}

.move-time-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
}

.move-cell {
    display: flex;
    gap: 2px;
}

.move-room-btn {
    flex: 1;
    padding: 6px 2px;
    border-radius: 4px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    font-size: 9px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
}

.move-room-btn:active {
    background: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
}

.move-room-btn.current {
    opacity: 0.3;
    pointer-events: none;
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

    <!-- Table -->
    <div class="table-wrapper">
        <table class="schedule-table">
            <thead>
                <tr>
                    <th class="time-col">Время</th>
                    <?php for ($d = 1; $d <= 7; $d++): ?>
                        <th class="day-col" data-day="<?= $d ?>"><?= $daysOfWeek[$d]['short'] ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                // Все часы от 8 до 21
                for ($hour = 8; $hour <= 21; $hour++):
                    $time = sprintf('%02d:00', $hour);
                ?>
                <tr>
                    <td class="time-cell"><?= $time ?></td>
                    <?php for ($d = 1; $d <= 7; $d++): ?>
                        <td class="day-cell" data-day="<?= $d ?>">
                            <div class="room-slots">
                                <?php for ($room = 1; $room <= 3; $room++):
                                    $key = "{$d}_{$time}_{$room}";
                                    $cellData = $scheduleGrid[$key] ?? null;
                                ?>
                                    <div class="room-slot" data-room="<?= $room ?>" data-day="<?= $d ?>" data-time="<?= $time ?>">
                                        <div class="room-slot-header">К<?= $room ?></div>
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
                        </td>
                    <?php endfor; ?>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Move modal -->
<div class="move-modal" id="moveModal" onclick="if(event.target===this)closeModal()">
    <div class="move-sheet">
        <div class="move-header">
            <span class="move-title">Переместить</span>
            <button class="move-close" onclick="closeModal()">✕</button>
        </div>
        <div class="move-info" id="moveInfo"></div>
        <div class="move-times" id="moveTimes"></div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<?php
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
?>

<script>
const daysOfWeek = <?= json_encode($daysOfWeek, JSON_UNESCAPED_UNICODE) ?>;
let selectedStudent = null;

// Toggle day visibility
function toggleDay(day) {
    const btn = document.querySelector(`.day-btn[data-day="${day}"]`);
    btn.classList.toggle('active');

    const isActive = btn.classList.contains('active');
    document.querySelectorAll(`.day-col[data-day="${day}"], .day-cell[data-day="${day}"]`).forEach(el => {
        el.classList.toggle('hidden', !isActive);
    });

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
        if (saved) {
            // Reset all
            document.querySelectorAll('.day-btn, .room-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.day-col, .day-cell').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.room-slot').forEach(el => el.classList.add('hidden'));

            // Apply saved
            saved.days.forEach(d => {
                document.querySelector(`.day-btn[data-day="${d}"]`)?.classList.add('active');
                document.querySelectorAll(`.day-col[data-day="${d}"], .day-cell[data-day="${d}"]`).forEach(el => {
                    el.classList.remove('hidden');
                });
            });

            saved.rooms.forEach(r => {
                document.querySelector(`.room-btn[data-room="${r}"]`)?.classList.add('active');
                document.querySelectorAll(`.room-slot[data-room="${r}"]`).forEach(el => {
                    el.classList.remove('hidden');
                });
            });
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

// Show move modal
function showMoveModal() {
    if (!selectedStudent) return;

    document.getElementById('moveInfo').innerHTML =
        `<strong>${selectedStudent.name}</strong><br>
         ${daysOfWeek[selectedStudent.day].name}, ${selectedStudent.time}, Каб. ${selectedStudent.room}`;

    // Build compact grid
    let html = '<div style="display:grid;grid-template-columns:45px repeat(7,1fr);gap:2px;font-size:10px;">';

    // Day headers
    html += '<div></div>';
    for (let d = 1; d <= 7; d++) {
        html += `<div style="text-align:center;color:var(--text-muted);font-weight:600;">${daysOfWeek[d].short}</div>`;
    }

    // Time rows
    for (let h = 8; h <= 21; h++) {
        const time = String(h).padStart(2, '0') + ':00';
        html += `<div style="color:var(--accent);font-family:monospace;display:flex;align-items:center;justify-content:center;">${time}</div>`;

        for (let d = 1; d <= 7; d++) {
            html += '<div style="display:flex;gap:1px;">';
            for (let r = 1; r <= 3; r++) {
                const isCurrent = d === selectedStudent.day && time === selectedStudent.time && r === selectedStudent.room;
                html += `<button class="move-room-btn ${isCurrent ? 'current' : ''}"
                         onclick="moveStudent(${d},'${time}',${r})">${r}</button>`;
            }
            html += '</div>';
        }
    }

    html += '</div>';
    document.getElementById('moveTimes').innerHTML = html;
    document.getElementById('moveModal').classList.add('active');
}

// Close modal
function closeModal() {
    document.getElementById('moveModal').classList.remove('active');
    document.querySelectorAll('.student-card.selected').forEach(c => c.classList.remove('selected'));
    selectedStudent = null;
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
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
