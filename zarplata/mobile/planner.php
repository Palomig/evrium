<?php
/**
 * Mobile Planner Page
 * Tap-to-select, tap-to-move interface for student schedules
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

// Сегодняшний день недели (1 = Monday)
$todayDayOfWeek = (int)date('N');

// Цвета преподавателей
$teacherColors = [
    1 => ['bg' => 'rgba(20, 184, 166, 0.35)', 'border' => 'rgba(20, 184, 166, 0.6)'],
    2 => ['bg' => 'rgba(168, 85, 247, 0.35)', 'border' => 'rgba(168, 85, 247, 0.6)'],
    3 => ['bg' => 'rgba(59, 130, 246, 0.35)', 'border' => 'rgba(59, 130, 246, 0.6)'],
    4 => ['bg' => 'rgba(249, 115, 22, 0.35)', 'border' => 'rgba(249, 115, 22, 0.6)'],
    5 => ['bg' => 'rgba(236, 72, 153, 0.35)', 'border' => 'rgba(236, 72, 153, 0.6)'],
    6 => ['bg' => 'rgba(234, 179, 8, 0.35)', 'border' => 'rgba(234, 179, 8, 0.6)'],
    7 => ['bg' => 'rgba(34, 197, 94, 0.35)', 'border' => 'rgba(34, 197, 94, 0.6)'],
    8 => ['bg' => 'rgba(239, 68, 68, 0.35)', 'border' => 'rgba(239, 68, 68, 0.6)'],
];

define('PAGE_TITLE', 'Планировщик');
define('ACTIVE_PAGE', 'planner');

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* Planner container */
.planner-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - var(--header-height) - var(--bottom-nav-height) - var(--safe-area-bottom));
    overflow: hidden;
}

/* Day tabs */
.day-tabs {
    display: flex;
    overflow-x: auto;
    gap: 8px;
    padding: 12px 16px;
    background: var(--bg-card);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.day-tabs::-webkit-scrollbar {
    display: none;
}

.day-tab {
    flex-shrink: 0;
    min-width: 48px;
    padding: 10px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: center;
}

.day-tab.active {
    background: var(--accent-dim);
    color: var(--accent);
    border-color: var(--accent);
}

.day-tab.today::after {
    content: '';
    display: block;
    width: 5px;
    height: 5px;
    background: var(--accent);
    border-radius: 50%;
    margin: 4px auto 0;
}

/* Teacher legend */
.teacher-legend {
    display: flex;
    overflow-x: auto;
    gap: 12px;
    padding: 10px 16px;
    background: var(--bg-card);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.teacher-legend::-webkit-scrollbar {
    display: none;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    font-size: 12px;
    color: var(--text-secondary);
}

.legend-color {
    width: 14px;
    height: 14px;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.2);
}

/* Day panels */
.day-panels {
    flex: 1;
    overflow: hidden;
    position: relative;
}

.day-panel {
    display: none;
    height: 100%;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding: 12px 16px;
}

.day-panel.active {
    display: block;
}

/* Time slots */
.time-slot {
    margin-bottom: 16px;
}

.time-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.time-badge {
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    font-weight: 600;
    color: var(--accent);
    background: var(--accent-dim);
    padding: 4px 10px;
    border-radius: 8px;
}

.room-badge {
    font-size: 12px;
    color: var(--text-muted);
    background: var(--bg-elevated);
    padding: 4px 8px;
    border-radius: 6px;
}

/* Student cards */
.students-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.student-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    border-radius: 10px;
    border: 1px solid var(--border);
    border-left: 3px solid var(--accent);
    cursor: pointer;
    transition: all 0.15s ease;
}

.student-card:active {
    transform: scale(0.98);
}

.student-card.selected {
    border-color: var(--accent) !important;
    box-shadow: 0 0 0 2px var(--accent-dim);
}

.student-tier {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 6px;
    border-radius: 4px;
    flex-shrink: 0;
}

.tier-S { background: #ff9999; color: #000; }
.tier-A { background: #ffcc99; color: #000; }
.tier-B { background: #ffff99; color: #000; }
.tier-C { background: #ccff99; color: #000; }
.tier-D { background: #99ff99; color: #000; }

.student-info {
    flex: 1;
    min-width: 0;
}

.student-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.student-class {
    font-size: 12px;
    color: var(--text-secondary);
}

/* Empty state */
.empty-day {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 200px;
    color: var(--text-muted);
    font-size: 14px;
}

.empty-day svg {
    width: 48px;
    height: 48px;
    margin-bottom: 12px;
    opacity: 0.5;
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
    justify-content: center;
}

.move-modal.active {
    display: flex;
}

.move-sheet {
    width: 100%;
    max-height: 80vh;
    background: var(--bg-card);
    border-radius: 20px 20px 0 0;
    padding: 20px;
    padding-bottom: calc(20px + var(--safe-area-bottom));
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.move-sheet-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.move-sheet-title {
    font-size: 18px;
    font-weight: 700;
}

.move-sheet-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--bg-elevated);
    border: none;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.move-student-info {
    background: var(--bg-elevated);
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: 14px;
}

.move-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.move-day-group {
    margin-bottom: 12px;
}

.move-day-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.move-time-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.move-option {
    padding: 10px 16px;
    border-radius: 10px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    font-size: 13px;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.15s ease;
}

.move-option:active {
    background: var(--accent-dim);
    border-color: var(--accent);
}

.move-option .time {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 600;
}

.move-option .room {
    font-size: 11px;
    color: var(--text-muted);
    margin-left: 4px;
}

/* Toast notification */
.toast {
    position: fixed;
    bottom: calc(var(--bottom-nav-height) + var(--safe-area-bottom) + 20px);
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: var(--bg-elevated);
    color: var(--text-primary);
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    z-index: 300;
    opacity: 0;
    transition: all 0.3s ease;
}

.toast.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}

.toast.success {
    border-left: 3px solid var(--status-green);
}

.toast.error {
    border-left: 3px solid var(--status-rose);
}
</style>

<div class="planner-container">
    <!-- Day tabs -->
    <div class="day-tabs">
        <?php for ($d = 1; $d <= 7; $d++): ?>
            <button class="day-tab <?= $d === $todayDayOfWeek ? 'active today' : '' ?>"
                    data-day="<?= $d ?>"
                    onclick="switchDay(<?= $d ?>)">
                <?= $daysOfWeek[$d]['short'] ?>
            </button>
        <?php endfor; ?>
    </div>

    <!-- Teacher legend -->
    <div class="teacher-legend">
        <?php foreach ($teachers as $teacher):
            $colorIndex = ($teacher['id'] % 8) ?: 8;
            $colors = $teacherColors[$colorIndex];
        ?>
            <div class="legend-item">
                <div class="legend-color" style="background: <?= $colors['bg'] ?>; border-color: <?= $colors['border'] ?>;"></div>
                <span><?= e($teacher['display_name'] ?: $teacher['name']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Day panels -->
    <div class="day-panels">
        <?php for ($d = 1; $d <= 7; $d++):
            // Определяем диапазон времени
            $startHour = ($d >= 6) ? 8 : 15;
            $endHour = 21;

            // Собираем уроки для этого дня
            $dayLessons = [];
            foreach ($scheduleGrid as $key => $cell) {
                if ($cell['day'] === $d) {
                    $dayLessons[] = $cell;
                }
            }

            // Сортируем по времени и кабинету
            usort($dayLessons, function($a, $b) {
                $timeCompare = strcmp($a['time'], $b['time']);
                if ($timeCompare !== 0) return $timeCompare;
                return $a['room'] - $b['room'];
            });
        ?>
            <div class="day-panel <?= $d === $todayDayOfWeek ? 'active' : '' ?>" data-day="<?= $d ?>">
                <?php if (empty($dayLessons)): ?>
                    <div class="empty-day">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>Нет занятий</span>
                    </div>
                <?php else: ?>
                    <?php foreach ($dayLessons as $lesson): ?>
                        <div class="time-slot">
                            <div class="time-header">
                                <span class="time-badge"><?= $lesson['time'] ?></span>
                                <span class="room-badge">Каб. <?= $lesson['room'] ?></span>
                            </div>
                            <div class="students-list">
                                <?php foreach ($lesson['students'] as $student):
                                    $colorIndex = ($student['teacher_id'] % 8) ?: 8;
                                    $colors = $teacherColors[$colorIndex];
                                ?>
                                    <div class="student-card"
                                         style="background: <?= $colors['bg'] ?>; border-color: <?= $colors['border'] ?>; border-left-color: <?= $colors['border'] ?>;"
                                         data-student-id="<?= $student['id'] ?>"
                                         data-student-name="<?= e($student['name']) ?>"
                                         data-day="<?= $d ?>"
                                         data-time="<?= $lesson['time'] ?>"
                                         data-room="<?= $lesson['room'] ?>"
                                         data-teacher-id="<?= $student['teacher_id'] ?>"
                                         onclick="selectStudent(this)">
                                        <span class="student-tier tier-<?= $student['tier'] ?>"><?= $student['tier'] ?></span>
                                        <div class="student-info">
                                            <div class="student-name"><?= e($student['name']) ?></div>
                                            <div class="student-class"><?= $student['class'] ?> класс</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Move modal -->
<div class="move-modal" id="moveModal">
    <div class="move-sheet">
        <div class="move-sheet-header">
            <span class="move-sheet-title">Переместить</span>
            <button class="move-sheet-close" onclick="closeModal()">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="move-student-info" id="moveStudentInfo"></div>
        <div class="move-options" id="moveOptions"></div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
// State
let selectedStudent = null;
const scheduleGrid = <?= json_encode($scheduleGrid, JSON_UNESCAPED_UNICODE) ?>;
const daysOfWeek = <?= json_encode($daysOfWeek, JSON_UNESCAPED_UNICODE) ?>;

// Switch day tab
function switchDay(day) {
    // Update tabs
    document.querySelectorAll('.day-tab').forEach(tab => {
        tab.classList.toggle('active', parseInt(tab.dataset.day) === day);
    });

    // Update panels
    document.querySelectorAll('.day-panel').forEach(panel => {
        panel.classList.toggle('active', parseInt(panel.dataset.day) === day);
    });
}

// Select student
function selectStudent(card) {
    // Deselect previous
    document.querySelectorAll('.student-card.selected').forEach(c => {
        c.classList.remove('selected');
    });

    // Select this one
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

    const modal = document.getElementById('moveModal');
    const info = document.getElementById('moveStudentInfo');
    const options = document.getElementById('moveOptions');

    info.innerHTML = `<strong>${selectedStudent.name}</strong><br>
        Сейчас: ${daysOfWeek[selectedStudent.day].name}, ${selectedStudent.time}, Каб. ${selectedStudent.room}`;

    // Build move options - all days and times
    let html = '';

    for (let d = 1; d <= 7; d++) {
        const startHour = d >= 6 ? 8 : 15;
        const endHour = 21;

        html += `<div class="move-day-group">
            <div class="move-day-label">${daysOfWeek[d].name}</div>
            <div class="move-time-options">`;

        for (let h = startHour; h <= endHour; h++) {
            const time = String(h).padStart(2, '0') + ':00';

            for (let room = 1; room <= 3; room++) {
                // Skip current position
                if (d === selectedStudent.day && time === selectedStudent.time && room === selectedStudent.room) {
                    continue;
                }

                html += `<button class="move-option" onclick="moveStudent(${d}, '${time}', ${room})">
                    <span class="time">${time}</span>
                    <span class="room">К${room}</span>
                </button>`;
            }
        }

        html += '</div></div>';
    }

    options.innerHTML = html;
    modal.classList.add('active');
}

// Close modal
function closeModal() {
    document.getElementById('moveModal').classList.remove('active');
    document.querySelectorAll('.student-card.selected').forEach(c => {
        c.classList.remove('selected');
    });
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
            // Reload page to update UI
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.error || 'Ошибка', 'error');
        }
    } catch (error) {
        console.error('Move error:', error);
        showToast('Ошибка сети', 'error');
    }
}

// Show toast
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type + ' show';

    setTimeout(() => {
        toast.classList.remove('show');
    }, 2500);
}

// Close modal on backdrop click
document.getElementById('moveModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
