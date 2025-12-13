<?php
/**
 * Mobile Schedule Page
 * Свайп между днями + горизонтальный скролл кабинетов
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/student_helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить преподавателей
$teachers = dbQuery("
    SELECT t.id, COALESCE(t.display_name, t.name) as name
    FROM teachers t
    WHERE t.active = 1
    ORDER BY t.name
", []);

// Получить все активные шаблоны расписания
try {
    $templates = dbQuery(
        "SELECT lt.*, COALESCE(t.display_name, t.name) as teacher_name
         FROM lessons_template lt
         LEFT JOIN teachers t ON lt.teacher_id = t.id
         WHERE lt.active = 1
         ORDER BY lt.day_of_week ASC, lt.time_start ASC",
        []
    );
} catch (PDOException $e) {
    $templates = [];
}

// Добавляем поле room если его нет + данные о студентах
foreach ($templates as &$template) {
    if (!isset($template['room'])) {
        $template['room'] = 1;
    }

    // Получаем студентов для урока
    $studentsData = getStudentsForLesson(
        $template['teacher_id'],
        $template['day_of_week'],
        substr($template['time_start'], 0, 5)
    );

    $template['actual_student_count'] = $studentsData['count'];
    $template['students_list'] = array_column($studentsData['students'], 'name');

    if (empty($template['subject'])) {
        $template['subject'] = $studentsData['subject'] ?: 'Математика';
    }
}
unset($template);

// Группируем по дням
$scheduleByDay = [];
$dayNames = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
$dayNamesFull = ['', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];

for ($d = 1; $d <= 7; $d++) {
    $scheduleByDay[$d] = [];
}

foreach ($templates as $t) {
    $day = (int)$t['day_of_week'];
    if ($day >= 1 && $day <= 7) {
        $scheduleByDay[$day][] = $t;
    }
}

// Сегодняшний день недели (1 = Monday)
$todayDayOfWeek = (int)date('N');

// JSON для JavaScript
$templatesJson = json_encode($templates, JSON_UNESCAPED_UNICODE);
$teachersJson = json_encode($teachers, JSON_UNESCAPED_UNICODE);

define('PAGE_TITLE', 'Расписание');
define('ACTIVE_PAGE', 'schedule');

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* Schedule specific styles */
.schedule-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - var(--header-height) - var(--bottom-nav-height) - var(--safe-area-bottom));
    overflow: hidden;
}

/* Day tabs - horizontal scroll */
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

.day-tab.today {
    position: relative;
}

.day-tab.today::after {
    content: '';
    position: absolute;
    bottom: 6px;
    left: 50%;
    transform: translateX(-50%);
    width: 5px;
    height: 5px;
    background: var(--accent);
    border-radius: 50%;
}

/* Day panels container */
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
}

.day-panel.active {
    display: block;
}

/* Room headers */
.room-headers {
    display: flex;
    position: sticky;
    top: 0;
    z-index: 10;
    background: var(--bg-card);
    border-bottom: 1px solid var(--border);
}

.room-header-time {
    width: 60px;
    min-width: 60px;
    padding: 10px 8px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    background: var(--bg-card);
    border-right: 1px solid var(--border);
    position: sticky;
    left: 0;
    z-index: 11;
}

.room-header {
    flex: 1;
    min-width: 140px;
    padding: 10px 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    text-align: center;
    border-right: 1px solid var(--border);
}

.room-header:last-child {
    border-right: none;
}

/* Schedule grid - horizontal scroll */
.schedule-grid-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.schedule-grid {
    min-width: 100%;
    display: table;
}

.schedule-row {
    display: flex;
    border-bottom: 1px solid var(--border);
}

.time-cell {
    width: 60px;
    min-width: 60px;
    padding: 12px 8px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    font-weight: 600;
    color: var(--accent);
    background: var(--bg-card);
    border-right: 1px solid var(--border);
    position: sticky;
    left: 0;
    z-index: 5;
    display: flex;
    align-items: flex-start;
}

.room-cell {
    flex: 1;
    min-width: 140px;
    padding: 8px;
    border-right: 1px solid var(--border);
    min-height: 80px;
}

.room-cell:last-child {
    border-right: none;
}

/* Lesson card */
.lesson-card {
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    cursor: pointer;
    transition: border-color 0.15s ease, transform 0.15s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.lesson-card:active {
    border-color: var(--accent);
    transform: scale(0.98);
}

.lesson-card-subject {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.lesson-card-teacher {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.lesson-card-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: auto;
}

.lesson-card-students {
    font-size: 12px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 4px;
}

.lesson-card-students svg {
    width: 14px;
    height: 14px;
}

.lesson-card-type {
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}

.lesson-card-type.group {
    background: var(--lesson-group-dim);
    color: var(--lesson-group);
}

.lesson-card-type.individual {
    background: var(--lesson-individual-dim);
    color: var(--lesson-individual);
}

/* Empty cell */
.empty-cell {
    height: 100%;
    min-height: 64px;
    border: 1px dashed var(--border);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    font-size: 12px;
    cursor: pointer;
    transition: border-color 0.15s ease;
}

.empty-cell:active {
    border-color: var(--accent);
    border-style: solid;
}

/* Empty day message */
.empty-day {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    padding: 48px 24px;
    text-align: center;
}

.empty-day svg {
    width: 64px;
    height: 64px;
    color: var(--text-muted);
    opacity: 0.4;
    margin-bottom: 16px;
}

.empty-day-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
}

.empty-day-text {
    font-size: 14px;
    color: var(--text-secondary);
}

/* Lesson Modal */
.lesson-modal-content {
    padding: 20px;
}

.lesson-detail {
    margin-bottom: 16px;
}

.lesson-detail-label {
    font-size: 12px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 4px;
}

.lesson-detail-value {
    font-size: 16px;
    color: var(--text-primary);
}

.students-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.student-tag {
    padding: 6px 12px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 16px;
    font-size: 13px;
}
</style>

<div class="schedule-container">
    <!-- Day Tabs -->
    <div class="day-tabs" id="dayTabs">
        <?php for ($d = 1; $d <= 7; $d++): ?>
            <button class="day-tab<?= $d === $todayDayOfWeek ? ' today active' : '' ?>"
                    data-day="<?= $d ?>"
                    onclick="switchDay(<?= $d ?>)">
                <?= $dayNames[$d] ?>
            </button>
        <?php endfor; ?>
    </div>

    <!-- Day Panels -->
    <div class="day-panels" id="dayPanels">
        <?php for ($d = 1; $d <= 7; $d++): ?>
            <div class="day-panel<?= $d === $todayDayOfWeek ? ' active' : '' ?>" data-day="<?= $d ?>">
                <?php
                $dayLessons = $scheduleByDay[$d];
                if (empty($dayLessons)):
                ?>
                    <div class="empty-day">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <div class="empty-day-title">Нет уроков</div>
                        <div class="empty-day-text"><?= $dayNamesFull[$d] ?> — выходной</div>
                    </div>
                <?php else:
                    // Группируем по времени
                    $timeSlots = [];
                    foreach ($dayLessons as $lesson) {
                        $time = substr($lesson['time_start'], 0, 5);
                        if (!isset($timeSlots[$time])) {
                            $timeSlots[$time] = [1 => null, 2 => null, 3 => null];
                        }
                        $room = (int)($lesson['room'] ?? 1);
                        if ($room >= 1 && $room <= 3) {
                            $timeSlots[$time][$room] = $lesson;
                        }
                    }
                    ksort($timeSlots);
                ?>
                    <div class="schedule-grid-wrapper">
                        <!-- Room Headers -->
                        <div class="room-headers">
                            <div class="room-header-time">Время</div>
                            <div class="room-header">Каб. 1</div>
                            <div class="room-header">Каб. 2</div>
                            <div class="room-header">Каб. 3</div>
                        </div>

                        <!-- Schedule Grid -->
                        <div class="schedule-grid">
                            <?php foreach ($timeSlots as $time => $rooms): ?>
                                <div class="schedule-row">
                                    <div class="time-cell"><?= $time ?></div>
                                    <?php for ($r = 1; $r <= 3; $r++): ?>
                                        <div class="room-cell">
                                            <?php if ($rooms[$r]):
                                                $lesson = $rooms[$r];
                                            ?>
                                                <div class="lesson-card" onclick="openLesson(<?= $lesson['id'] ?>)">
                                                    <div class="lesson-card-subject"><?= htmlspecialchars($lesson['subject'] ?? 'Урок') ?></div>
                                                    <div class="lesson-card-teacher"><?= htmlspecialchars($lesson['teacher_name']) ?></div>
                                                    <div class="lesson-card-meta">
                                                        <span class="lesson-card-students">
                                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/>
                                                            </svg>
                                                            <?= $lesson['actual_student_count'] ?? $lesson['expected_students'] ?? 0 ?>
                                                        </span>
                                                        <span class="lesson-card-type <?= $lesson['lesson_type'] ?? 'group' ?>">
                                                            <?= ($lesson['lesson_type'] ?? 'group') === 'group' ? 'Гр' : 'Инд' ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="empty-cell" onclick="addLesson(<?= $d ?>, '<?= $time ?>', <?= $r ?>)">
                                                    <span>+</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<!-- FAB Button -->
<button class="fab" onclick="openAddModal()">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
</button>

<!-- Lesson Detail Modal -->
<div class="modal" id="lessonModal">
    <div class="modal-content">
        <div class="modal-handle"></div>
        <div class="modal-header">
            <h3 class="modal-title" id="lessonModalTitle">Урок</h3>
            <button class="modal-close" onclick="closeModal('lessonModal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="lessonModalBody">
            <!-- Content loaded dynamically -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('lessonModal')">Закрыть</button>
            <button class="btn btn-primary" id="editLessonBtn">Редактировать</button>
        </div>
    </div>
</div>

<!-- Add/Edit Lesson Modal -->
<div class="modal modal-fullscreen" id="addLessonModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="addLessonModalTitle">Добавить урок</h3>
            <button class="modal-close" onclick="closeModal('addLessonModal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="lessonForm">
                <input type="hidden" name="id" id="lessonId">

                <div class="form-group">
                    <label class="form-label">Преподаватель</label>
                    <select name="teacher_id" id="teacherSelect" class="form-control" required>
                        <option value="">Выберите преподавателя</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">День недели</label>
                    <select name="day_of_week" id="daySelect" class="form-control" required>
                        <?php for ($d = 1; $d <= 7; $d++): ?>
                            <option value="<?= $d ?>"><?= $dayNamesFull[$d] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 12px;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Начало</label>
                        <input type="time" name="time_start" id="timeStart" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Конец</label>
                        <input type="time" name="time_end" id="timeEnd" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Кабинет</label>
                    <select name="room" id="roomSelect" class="form-control" required>
                        <option value="1">Кабинет 1</option>
                        <option value="2">Кабинет 2</option>
                        <option value="3">Кабинет 3</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Предмет</label>
                    <input type="text" name="subject" id="subjectInput" class="form-control" placeholder="Математика">
                </div>

                <div class="form-group">
                    <label class="form-label">Тип занятия</label>
                    <select name="lesson_type" id="lessonTypeSelect" class="form-control">
                        <option value="group">Групповое</option>
                        <option value="individual">Индивидуальное</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Ожидаемое кол-во учеников</label>
                    <input type="number" name="expected_students" id="expectedStudents" class="form-control" value="1" min="1">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('addLessonModal')">Отмена</button>
            <button class="btn btn-primary" onclick="saveLesson()">Сохранить</button>
        </div>
    </div>
</div>

<script>
// Data
const templates = <?= $templatesJson ?>;
const teachers = <?= $teachersJson ?>;
let currentDay = <?= $todayDayOfWeek ?>;
let currentLesson = null;

// Touch swipe handling - only trigger on horizontal swipes
let touchStartX = 0;
let touchStartY = 0;
let touchEndX = 0;
let touchEndY = 0;

document.getElementById('dayPanels').addEventListener('touchstart', e => {
    touchStartX = e.changedTouches[0].screenX;
    touchStartY = e.changedTouches[0].screenY;
}, { passive: true });

document.getElementById('dayPanels').addEventListener('touchend', e => {
    touchEndX = e.changedTouches[0].screenX;
    touchEndY = e.changedTouches[0].screenY;
    handleSwipe();
}, { passive: true });

function handleSwipe() {
    const diffX = touchStartX - touchEndX;
    const diffY = touchStartY - touchEndY;
    const threshold = 80; // Increased threshold

    // Ignore if vertical movement is greater than horizontal (scrolling)
    if (Math.abs(diffY) > Math.abs(diffX)) return;

    // Ignore small movements
    if (Math.abs(diffX) < threshold) return;

    // Ignore if swipe angle is too steep (more than 30 degrees from horizontal)
    const angle = Math.abs(Math.atan2(diffY, diffX) * 180 / Math.PI);
    if (angle > 30 && angle < 150) return;

    if (diffX > 0 && currentDay < 7) {
        switchDay(currentDay + 1);
    } else if (diffX < 0 && currentDay > 1) {
        switchDay(currentDay - 1);
    }
}

function switchDay(day) {
    currentDay = day;

    // Update tabs
    document.querySelectorAll('.day-tab').forEach(tab => {
        tab.classList.toggle('active', parseInt(tab.dataset.day) === day);
    });

    // Scroll tab into view
    const activeTab = document.querySelector(`.day-tab[data-day="${day}"]`);
    activeTab?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });

    // Update panels
    document.querySelectorAll('.day-panel').forEach(panel => {
        panel.classList.toggle('active', parseInt(panel.dataset.day) === day);
    });
}

function openLesson(id) {
    const lesson = templates.find(t => t.id == id);
    if (!lesson) return;

    currentLesson = lesson;

    document.getElementById('lessonModalTitle').textContent = lesson.subject || 'Урок';

    const studentsList = (lesson.students_list || []).map(s =>
        `<span class="student-tag">${escapeHtml(s)}</span>`
    ).join('') || '<span class="text-muted">Нет учеников</span>';

    document.getElementById('lessonModalBody').innerHTML = `
        <div class="lesson-detail">
            <div class="lesson-detail-label">Преподаватель</div>
            <div class="lesson-detail-value">${escapeHtml(lesson.teacher_name)}</div>
        </div>
        <div class="lesson-detail">
            <div class="lesson-detail-label">Время</div>
            <div class="lesson-detail-value">${lesson.time_start.substring(0, 5)} — ${lesson.time_end.substring(0, 5)}</div>
        </div>
        <div class="lesson-detail">
            <div class="lesson-detail-label">Кабинет</div>
            <div class="lesson-detail-value">Кабинет ${lesson.room || 1}</div>
        </div>
        <div class="lesson-detail">
            <div class="lesson-detail-label">Тип</div>
            <div class="lesson-detail-value">${lesson.lesson_type === 'individual' ? 'Индивидуальное' : 'Групповое'}</div>
        </div>
        <div class="lesson-detail">
            <div class="lesson-detail-label">Ученики (${lesson.actual_student_count || 0})</div>
            <div class="students-list">${studentsList}</div>
        </div>
    `;

    document.getElementById('editLessonBtn').onclick = () => {
        closeModal('lessonModal');
        editLesson(lesson);
    };

    openModal('lessonModal');
}

function editLesson(lesson) {
    document.getElementById('addLessonModalTitle').textContent = 'Редактировать урок';
    document.getElementById('lessonId').value = lesson.id;
    document.getElementById('teacherSelect').value = lesson.teacher_id;
    document.getElementById('daySelect').value = lesson.day_of_week;
    document.getElementById('timeStart').value = lesson.time_start.substring(0, 5);
    document.getElementById('timeEnd').value = lesson.time_end.substring(0, 5);
    document.getElementById('roomSelect').value = lesson.room || 1;
    document.getElementById('subjectInput').value = lesson.subject || '';
    document.getElementById('lessonTypeSelect').value = lesson.lesson_type || 'group';
    document.getElementById('expectedStudents').value = lesson.expected_students || 1;

    openModal('addLessonModal');
}

function addLesson(day, time, room) {
    document.getElementById('addLessonModalTitle').textContent = 'Добавить урок';
    document.getElementById('lessonForm').reset();
    document.getElementById('lessonId').value = '';
    document.getElementById('daySelect').value = day;
    document.getElementById('timeStart').value = time;
    document.getElementById('roomSelect').value = room;

    // Default end time +1.5 hours
    const [h, m] = time.split(':').map(Number);
    const endMinutes = h * 60 + m + 90;
    const endH = Math.floor(endMinutes / 60) % 24;
    const endM = endMinutes % 60;
    document.getElementById('timeEnd').value =
        String(endH).padStart(2, '0') + ':' + String(endM).padStart(2, '0');

    openModal('addLessonModal');
}

function openAddModal() {
    document.getElementById('addLessonModalTitle').textContent = 'Добавить урок';
    document.getElementById('lessonForm').reset();
    document.getElementById('lessonId').value = '';
    document.getElementById('daySelect').value = currentDay;

    openModal('addLessonModal');
}

async function saveLesson() {
    const form = document.getElementById('lessonForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    const id = data.id;
    const action = id ? 'update_template' : 'add_template';

    try {
        MobileApp.showLoading();

        const response = await fetch(`../api/schedule.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            MobileApp.showToast(id ? 'Урок обновлён' : 'Урок добавлен', 'success');
            closeModal('addLessonModal');
            setTimeout(() => location.reload(), 500);
        } else {
            MobileApp.showToast(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        MobileApp.showToast('Ошибка сети', 'error');
    } finally {
        MobileApp.hideLoading();
    }
}

function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals on overlay click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
