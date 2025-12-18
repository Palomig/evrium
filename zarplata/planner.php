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

// Получить все шаблоны уроков
$templates = dbQuery("
    SELECT lt.*, t.name as teacher_name
    FROM lessons_template lt
    LEFT JOIN teachers t ON lt.teacher_id = t.id
    WHERE lt.active = 1
    ORDER BY lt.day_of_week, lt.time_start
", []);

// Построить структуру данных для отображения
// Ключ: "день_время_кабинет" => [студенты]
$scheduleGrid = [];
$daysOfWeek = [
    1 => 'Понедельник',
    2 => 'Вторник',
    3 => 'Среда',
    4 => 'Четверг',
    5 => 'Пятница',
    6 => 'Суббота',
    7 => 'Воскресенье'
];

// Временные слоты
$weekdaySlots = range(15, 21); // 15:00 - 21:00 для пн-пт
$weekendSlots = range(8, 21);   // 08:00 - 21:00 для сб-вс

// Собираем студентов по ячейкам расписания
foreach ($students as $student) {
    if (!$student['schedule']) continue;

    $schedule = json_decode($student['schedule'], true);
    if (!is_array($schedule)) continue;

    foreach ($schedule as $dayKey => $daySchedule) {
        $day = (int)$dayKey;
        if ($day < 1 || $day > 7) continue;

        // Обрабатываем разные форматы расписания
        if (is_array($daySchedule)) {
            // Формат 3: [{"time": "17:00", "room": 1, "subject": "Мат."}, ...]
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
            // Формат 1: {"day": "Monday", "time": "17:00"}
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
        // Формат 2: "17:00" (просто время)
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

define('PAGE_TITLE', 'Планировщик');
define('PAGE_SUBTITLE', 'Drag & drop расписание учеников');
define('ACTIVE_PAGE', 'planner');

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* Fonts */
body, .planner-header, .filters-panel, .day-filter-btn, .room-filter-btn,
select, button {
    font-family: 'Nunito', sans-serif;
}

.time-cell {
    font-family: 'JetBrains Mono', monospace;
}

/* Скрыть стандартный page-header если он пустой */
.page-header:empty {
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

/* Заголовок планировщика */
.planner-header {
    background-color: var(--bg-card);
    border-radius: 12px;
    padding: 16px 24px;
    margin-bottom: 16px;
    flex-shrink: 0;
}

.planner-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.planner-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.planner-subtitle {
    color: var(--text-secondary);
    font-size: 0.875rem;
    margin-top: 4px;
}

/* Панель фильтров */
.filters-panel {
    background-color: var(--bg-card);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 16px;
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

.filter-label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.room-filter-btn {
    padding: 8px 16px;
    border: 2px solid var(--border);
    border-radius: 8px;
    background-color: var(--bg-elevated);
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.2s;
}

.room-filter-btn:hover {
    border-color: var(--accent);
    background-color: var(--bg-hover);
}

.room-filter-btn.active {
    background-color: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
}

.toggle-btn {
    padding: 8px 16px;
    border: 2px solid var(--border);
    border-radius: 8px;
    background-color: var(--bg-elevated);
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
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
    height: 32px;
    background: var(--border);
    margin: 0 8px;
}

/* Контейнер расписания */
.planner-container {
    position: relative;
    overflow: auto;
    background-color: var(--bg-card);
    border-radius: 12px;
    padding: 16px;
    flex: 1;
    min-height: 0;
}

.planner-grid {
    display: grid;
    grid-template-columns: 60px repeat(7, minmax(180px, 1fr));
    gap: 1px;
    background: var(--border);
    border-radius: 8px;
    overflow: hidden;
}

/* Заголовки */
.grid-header {
    background: var(--bg-hover);
    padding: 12px 8px;
    text-align: center;
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--text-primary);
}

.grid-header.time-header {
    background: var(--bg-elevated);
    color: var(--text-muted);
}

/* Ячейки времени */
.time-cell {
    background: var(--bg-elevated);
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-secondary);
}

/* Ячейки расписания */
.schedule-cell {
    background: var(--bg-card);
    min-height: 60px;
    padding: 4px;
    display: flex;
    flex-direction: column;
    gap: 2px;
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
    gap: 2px;
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
    font-size: 0.65rem;
    color: var(--text-muted);
    text-align: center;
    padding: 2px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 2px;
}

/* Карточка ученика */
.student-card {
    background: var(--bg-card);
    border-radius: 4px;
    padding: 4px 6px;
    font-size: 0.7rem;
    cursor: grab;
    transition: all 0.2s;
    border-left: 3px solid var(--accent);
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    overflow: hidden;
}

.student-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.student-card:active {
    cursor: grabbing;
}

.student-card.dragging {
    opacity: 0.5;
    transform: rotate(2deg);
}

.student-tier {
    font-size: 0.6rem;
    font-weight: 700;
    padding: 1px 4px;
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

/* Пустой слот */
.empty-slot {
    min-height: 30px;
    border: 1px dashed var(--border);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    font-size: 0.65rem;
    cursor: pointer;
    transition: all 0.2s;
}

.empty-slot:hover {
    border-color: var(--accent);
    color: var(--accent);
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
    display: flex;
    align-items: center;
    gap: 12px;
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
    font-size: 24px;
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

/* Легенда тиров */
.tier-legend {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 8px 12px;
    background: var(--bg-elevated);
    border-radius: 8px;
}

.tier-legend-item {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

/* Адаптивность для широких экранов */
@media (min-width: 1600px) {
    .planner-grid {
        grid-template-columns: 70px repeat(7, minmax(220px, 1fr));
    }
}
</style>

<!-- Заголовок -->
<div class="planner-header">
    <div class="planner-header-top">
        <div>
            <h1 class="planner-title">Планировщик расписания</h1>
            <p class="planner-subtitle">Перетаскивайте учеников между днями и временем</p>
        </div>
        <div class="tier-legend">
            <span class="filter-label">Тиры:</span>
            <div class="tier-legend-item"><span class="student-tier tier-S">S</span></div>
            <div class="tier-legend-item"><span class="student-tier tier-A">A</span></div>
            <div class="tier-legend-item"><span class="student-tier tier-B">B</span></div>
            <div class="tier-legend-item"><span class="student-tier tier-C">C</span></div>
            <div class="tier-legend-item"><span class="student-tier tier-D">D</span></div>
        </div>
    </div>
</div>

<!-- Панель фильтров -->
<div class="filters-panel">
    <div class="filters-content">
        <div class="filter-group">
            <span class="filter-label">Кабинеты:</span>
            <button class="room-filter-btn active" data-room="1" onclick="toggleRoomFilter(this)">1</button>
            <button class="room-filter-btn active" data-room="2" onclick="toggleRoomFilter(this)">2</button>
            <button class="room-filter-btn active" data-room="3" onclick="toggleRoomFilter(this)">3</button>
        </div>

        <div class="filter-divider"></div>

        <div class="filter-group">
            <button class="toggle-btn active" id="tierToggle" onclick="toggleTierDisplay()">
                <span class="material-icons" style="font-size: 18px;">label</span>
                Показать тиры
            </button>
        </div>

        <div class="filter-divider"></div>

        <div class="filter-group">
            <span class="filter-label">Ученики: <strong id="studentCount"><?= count($students) ?></strong></span>
        </div>
    </div>
</div>

<!-- Сетка планировщика -->
<div class="planner-container">
    <div class="planner-grid" id="plannerGrid">
        <!-- Заголовки дней -->
        <div class="grid-header time-header">Время</div>
        <?php foreach ($daysOfWeek as $dayNum => $dayName): ?>
            <div class="grid-header" data-day="<?= $dayNum ?>"><?= $dayName ?></div>
        <?php endforeach; ?>

        <!-- Строки времени -->
        <?php
        // Генерируем все временные слоты (08:00 - 21:00)
        for ($hour = 8; $hour <= 21; $hour++):
            $time = sprintf('%02d:00', $hour);
        ?>
            <div class="time-cell"><?= $time ?></div>

            <?php foreach ($daysOfWeek as $dayNum => $dayName):
                // Определяем доступные часы для дня
                $isWeekend = ($dayNum >= 6);
                $minHour = $isWeekend ? 8 : 15;
                $isDisabled = ($hour < $minHour);
            ?>
                <div class="schedule-cell" data-day="<?= $dayNum ?>" data-time="<?= $time ?>" <?= $isDisabled ? 'style="opacity: 0.3; pointer-events: none;"' : '' ?>>
                    <?php if (!$isDisabled): ?>
                    <div class="rooms-container">
                        <?php for ($room = 1; $room <= 3; $room++):
                            $key = "{$dayNum}_{$time}_{$room}";
                            $cellData = $scheduleGrid[$key] ?? null;
                        ?>
                            <div class="room-slot" data-room="<?= $room ?>" data-day="<?= $dayNum ?>" data-time="<?= $time ?>">
                                <div class="room-slot-header">Каб. <?= $room ?></div>
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
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endfor; ?>
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

console.log('Planner loaded:', {
    scheduleData,
    studentsData: studentsData.length,
    teachersData: teachersData.length
});

// ========== DRAG AND DROP ==========

let draggedCard = null;
let sourceSlot = null;

// Инициализация drag and drop
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

    // Убираем все индикаторы
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

    // Если перетащили в тот же слот - ничего не делаем
    if (data.fromDay === toDay && data.fromTime === toTime && data.fromRoom === toRoom) {
        return;
    }

    console.log('Moving student:', {
        studentId: data.studentId,
        from: `${data.fromDay}_${data.fromTime}_${data.fromRoom}`,
        to: `${toDay}_${toTime}_${toRoom}`
    });

    // Отправляем на сервер
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
            // Перемещаем карточку в DOM
            if (draggedCard) {
                // Обновляем data-атрибуты
                draggedCard.dataset.day = toDay;
                draggedCard.dataset.time = toTime;
                draggedCard.dataset.room = toRoom;

                // Перемещаем в новый слот
                slot.appendChild(draggedCard);
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

    // Обновляем grid-template-columns для rooms-container
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

    btn.innerHTML = showTier
        ? '<span class="material-icons" style="font-size: 18px;">label</span> Показать тиры'
        : '<span class="material-icons" style="font-size: 18px;">label_off</span> Скрыть тиры';

    saveFilters();
}

// ========== СОХРАНЕНИЕ ФИЛЬТРОВ ==========

function saveFilters() {
    const filters = {
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

        // Восстанавливаем отображение тиров
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

// ========== ИНИЦИАЛИЗАЦИЯ ==========

document.addEventListener('DOMContentLoaded', function() {
    initDragAndDrop();
    restoreFilters();
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
