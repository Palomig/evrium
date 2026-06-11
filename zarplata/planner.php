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
require_once __DIR__ . '/config/planner_snapshots.php';
require_once __DIR__ . '/config/student_helpers.php';

requireAuth();
$user = getCurrentUser();

// ===== Режим просмотра версии из истории (только чтение) =====
$snapshotView = null;
if (!empty($_GET['snapshot'])) {
    ensurePlannerSnapshotsTable();
    $snapshotView = dbQueryOne("SELECT * FROM planner_snapshots WHERE id = ?", [(int)$_GET['snapshot']]);
}

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
        temp_until DATE NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_cell (day, time, room)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", []);

// Миграция: колонка temp_until для «временных» учеников (добавлен на 1 урок).
// dbQuery глотает исключения, поэтому проверяем колонку через SHOW COLUMNS.
$tempColumn = dbQuery("SHOW COLUMNS FROM planner_notes LIKE 'temp_until'", []);
if (empty($tempColumn)) {
    dbExecute("ALTER TABLE planner_notes ADD COLUMN temp_until DATE NULL", []);
}

// Миграция: колонка teacher_id (столбцы планировщика = преподаватели).
// Бэкофилл из цветов: цвет ячейки → преподаватель, фолбэк — цвет блока → c1.
$teacherColumn = dbQuery("SHOW COLUMNS FROM planner_notes LIKE 'teacher_id'", []);
if (empty($teacherColumn)) {
    dbExecute("ALTER TABLE planner_notes ADD COLUMN teacher_id INT NULL", []);
}
$nullTeacherCount = dbQueryOne("SELECT COUNT(*) AS c FROM planner_notes WHERE teacher_id IS NULL", []);
if ((int)($nullTeacherCount['c'] ?? 0) > 0) {
    $colorMap = plannerColorTeacherMap();
    $defaultTeacher = $colorMap[1] ?? (reset($colorMap) ?: null);

    $allNotes = dbQuery("SELECT id, day, time, room, kind, color FROM planner_notes WHERE teacher_id IS NULL", []);
    $blocksTmp = [];
    foreach ($allNotes as $r) {
        $blocksTmp["{$r['day']}_{$r['time']}_{$r['room']}"][] = $r;
    }
    foreach ($blocksTmp as $rows) {
        // Цвет блока: цвет заголовка, иначе самый частый цвет учеников
        $titleColor = 0;
        $cellColors = [];
        foreach ($rows as $r) {
            if ($r['kind'] === 'title') {
                $titleColor = (int)$r['color'];
            } elseif ((int)$r['color']) {
                $cellColors[] = (int)$r['color'];
            }
        }
        $blockColor = $titleColor;
        if (!$blockColor && $cellColors) {
            $counts = array_count_values($cellColors);
            arsort($counts);
            $blockColor = (int)array_key_first($counts);
        }
        $blockTeacher = $colorMap[$blockColor] ?? $defaultTeacher;

        foreach ($rows as $r) {
            $cellColor = (int)$r['color'];
            $noteTeacher = ($cellColor && isset($colorMap[$cellColor])) ? $colorMap[$cellColor] : $blockTeacher;
            if ($noteTeacher) {
                dbExecute("UPDATE planner_notes SET teacher_id = ? WHERE id = ?", [$noteTeacher, $r['id']]);
            }
        }
    }
}

// Удаляем временных учеников, чей урок уже прошёл (не в режиме просмотра версии)
$deletedTemp = 0;
if (!$snapshotView) {
    $deletedTemp = dbExecute("DELETE FROM planner_notes WHERE temp_until IS NOT NULL AND temp_until < CURDATE()", []);
}

// Преподаватели — столбцы планировщика (порядок стабильный, по id)
$teachers = dbQuery("
    SELECT id, name, display_name
    FROM teachers
    WHERE active = 1
    ORDER BY id
", []);

// Колонки-преподаватели: цвет и короткая метка
$teacherCols = [];
foreach ($teachers as $t) {
    $label = $t['display_name'] ?: $t['name'];
    $teacherCols[] = [
        'id' => (int)$t['id'],
        'color' => (((int)$t['id']) % 8) ?: 8,
        'label' => $label,
        'badge' => mb_strtoupper(mb_substr($label, 0, 1))
    ];
}

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

// Базовая версия истории (если её ещё нет) и фиксация авточистки временных
if (!$snapshotView) {
    ensurePlannerSnapshotsTable();
    $hasVersions = dbQueryOne("SELECT id FROM planner_snapshots LIMIT 1", []);
    if (!$hasVersions || $deletedTemp > 0) {
        plannerRecordVersion();
    }
}

// ===== Загружаем все заметки и группируем по блокам (день × время × преподаватель) =====
if ($snapshotView) {
    $notes = json_decode($snapshotView['data'], true) ?: [];
} else {
    $notes = dbQuery("
        SELECT id, day, time, room, kind, position, content, color, temp_until, teacher_id
        FROM planner_notes
        ORDER BY day, time, room, kind, position, id
    ", []);
}

// Карта цвет → преподаватель и список активных id (для записей без teacher_id —
// старые снапшоты и недомигрированные строки)
$colorMap = [];
$activeTeacherIds = [];
foreach ($teacherCols as $tc) {
    $colorMap[$tc['color']] = $tc['id'];
    $activeTeacherIds[] = $tc['id'];
}
$fallbackTeacherId = $colorMap[1] ?? ($activeTeacherIds[0] ?? 0);

// Первый проход: цвета заголовков по старым блокам (день_время_кабинет) — для фолбэка
$legacyTitleColors = [];
foreach ($notes as $note) {
    if (($note['kind'] ?? '') === 'title') {
        $legacyTitleColors["{$note['day']}_{$note['time']}_{$note['room']}"] = (int)($note['color'] ?? 0);
    }
}

// Определить преподавателя записи: teacher_id → цвет ячейки → цвет блока → фолбэк
function plannerResolveNoteTeacher($note, $colorMap, $activeTeacherIds, $legacyTitleColors, $fallbackTeacherId) {
    $tid = (int)($note['teacher_id'] ?? 0);
    if ($tid && in_array($tid, $activeTeacherIds, true)) {
        return $tid;
    }
    $color = (int)($note['color'] ?? 0);
    if (!$color) {
        $color = $legacyTitleColors["{$note['day']}_{$note['time']}_{$note['room']}"] ?? 0;
    }
    return $colorMap[$color] ?? $fallbackTeacherId;
}

$blocks = [];
foreach ($notes as $note) {
    $noteTeacher = plannerResolveNoteTeacher($note, $colorMap, $activeTeacherIds, $legacyTitleColors, $fallbackTeacherId);
    $key = "{$note['day']}_{$note['time']}_{$noteTeacher}";
    if (!isset($blocks[$key])) {
        $blocks[$key] = ['title' => null, 'students' => []];
    }
    if ($note['kind'] === 'title') {
        // Если заголовков несколько (слияние старых кабинетов) — берём первый непустой
        if ($blocks[$key]['title'] === null || trim($blocks[$key]['title']['content']) === '') {
            $blocks[$key]['title'] = $note;
        }
    } else {
        $blocks[$key]['students'][] = $note;
    }
}

$daysOfWeek = [
    1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг',
    5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'
];

/**
 * Рендер одного блока урока (день × время × преподаватель)
 */
function renderLessonBlock($day, $time, $teacherCol, $blocks) {
    $teacherId = $teacherCol['id'];
    $color = $teacherCol['color'];
    $key = "{$day}_{$time}_{$teacherId}";
    $block = $blocks[$key] ?? ['title' => null, 'students' => []];
    $title = $block['title'];
    ?>
    <div class="lesson-block bc<?= $color ?>" data-day="<?= $day ?>" data-time="<?= $time ?>" data-teacher="<?= $teacherId ?>" data-color="<?= $color ?>">
        <span class="block-room" title="<?= e($teacherCol['label']) ?>"><?= e($teacherCol['badge']) ?></span>
        <div class="block-title<?= $title && $title['content'] !== '' ? '' : ' is-empty' ?>"
             data-kind="title"
             <?= $title ? 'data-id="' . $title['id'] . '"' : '' ?>><?= $title ? e($title['content']) : '' ?></div>
        <div class="block-cells">
            <?php foreach ($block['students'] as $st):
                $isTemp = !empty($st['temp_until']);
            ?>
                <div class="pcell c<?= $color ?><?= $isTemp ? ' temp' : '' ?>"
                     data-kind="student"
                     data-id="<?= $st['id'] ?>"
                     data-temp="<?= $isTemp ? 1 : 0 ?>"
                     data-name="<?= e($st['content']) ?>"><?= e($st['content']) ?><?php if ($isTemp): ?><span class="temp-badge" title="Временно — до <?= $st['temp_until'] ?>">⏳</span><?php endif; ?></div>
            <?php endforeach; ?>
        </div>
        <button class="add-cell-btn" type="button" title="Добавить ученика">+</button>
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

/* Кнопка истории */
.history-btn {
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
    gap: 5px;
}

.history-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.history-btn .material-icons {
    font-size: 16px;
}

/* Баннер просмотра версии */
.snapshot-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    margin-bottom: 12px;
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.4);
    border-radius: 10px;
    color: #fbbf24;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.snapshot-banner .material-icons {
    font-size: 18px;
}

.snapshot-banner .snapshot-back {
    margin-left: auto;
    color: var(--accent);
    font-weight: 600;
    text-decoration: none;
    padding: 4px 12px;
    border: 1px solid var(--accent);
    border-radius: 6px;
    transition: all 0.15s;
}

.snapshot-banner .snapshot-back:hover {
    background: var(--accent-dim);
}

/* Read-only режим (просмотр версии) */
body.readonly .add-cell-btn {
    display: none;
}

body.readonly .pcell,
body.readonly .block-title {
    cursor: default;
}

/* Список версий в модалке истории */
.history-list {
    max-height: 420px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.history-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
    text-decoration: none;
    color: var(--text-primary);
}

.history-item:hover {
    border-color: var(--accent);
    background: var(--accent-dim);
}

.history-item .material-icons {
    font-size: 18px;
    color: var(--text-muted);
}

.history-item-date {
    font-weight: 700;
    font-size: 0.9rem;
}

.history-item-range {
    margin-left: auto;
    font-size: 0.75rem;
    color: var(--text-muted);
    font-family: 'JetBrains Mono', monospace;
}

.history-empty {
    text-align: center;
    padding: 32px;
    color: var(--text-muted);
    font-size: 0.9rem;
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

/* Утренние строки будней: пустые скрыты, пока не включено «Утро».
   Строки с уроками видны всегда. */
body:not(.show-morning) .morning-row:not(.has-lessons) {
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

/* Ячейка день×час: колонка на каждого преподавателя */
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
    gap: 2px;
    height: 100%;
}

.room-filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.teacher-dot {
    width: 10px;
    height: 10px;
    border-radius: 3px;
    display: inline-block;
    border: 1px solid rgba(255,255,255,0.25);
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

/* Пустой заголовок — явное приглашение создать урок */
.block-title.is-empty::before {
    content: '+ урок';
    color: #4b5563;
    font-weight: 600;
}

.block-title:hover {
    background: rgba(255, 255, 255, 0.09);
}

.block-title.is-empty:hover::before {
    color: var(--accent);
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

.pcell {
    cursor: pointer;
}

.pcell:hover {
    border-color: rgba(255, 255, 255, 0.2);
}

/* Временный ученик (только на ближайший урок) — янтарный, поверх цвета преподавателя */
.pcell.temp {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.4), rgba(245, 158, 11, 0.16)) !important;
    border: 1px dashed rgba(245, 158, 11, 0.75) !important;
    border-left: 3px solid #f59e0b !important;
    color: #fef3c7;
}

.temp-badge {
    font-size: 0.6rem;
    margin-left: 3px;
}

/* Кнопка добавления ячейки в блок */
.add-cell-btn {
    margin: 0 2px 2px;
    padding: 1px 0;
    background: transparent;
    border: 1px dashed rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    color: #4b5563;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1.2;
    cursor: pointer;
    transition: all 0.15s;
}

.add-cell-btn:hover {
    border-color: rgba(20, 184, 166, 0.6);
    color: var(--accent);
    background: rgba(20, 184, 166, 0.08);
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

/* Поле ввода внутри ячейки/заголовка.
   min-width: 0 и size=1 обязательны: дефолтная внутренняя ширина input
   (~20 символов) распирает грид-колонки, т.к. обёртка сетки max-content */
.pcell,
.block-title {
    min-width: 0;
}

.pcell input,
.block-title input {
    width: 100%;
    min-width: 0;
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

/* ========== МОДАЛКА УЧЕНИКА ========== */
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
    border-radius: 14px;
    width: 100%;
    max-width: 380px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    animation: modalSlideIn 0.25s ease;
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-16px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
}

.modal-header h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-primary);
}

.modal-header .modal-slot-info {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-family: 'JetBrains Mono', monospace;
}

.modal-body {
    padding: 20px;
}

.modal-body label.field-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 6px;
}

.modal-body input[type="text"] {
    width: 100%;
    padding: 10px 12px;
    background: var(--bg-elevated);
    border: 2px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.95rem;
    font-family: 'Nunito', sans-serif;
    box-sizing: border-box;
}

.modal-body input[type="text"]:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.2);
}

.temp-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 16px;
    padding: 10px 12px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: border-color 0.15s;
}

.temp-checkbox:hover {
    border-color: var(--accent);
}

.temp-checkbox input[type="checkbox"] {
    width: 17px;
    height: 17px;
    margin-top: 1px;
    accent-color: var(--accent);
    cursor: pointer;
    flex-shrink: 0;
}

.temp-checkbox .temp-label {
    font-size: 0.85rem;
    color: var(--text-primary);
    font-weight: 600;
}

.temp-checkbox .temp-hint {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 2px;
}

.modal-footer {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    border-top: 1px solid var(--border);
}

.modal-footer .btn-delete {
    margin-right: auto;
    padding: 9px 14px;
    background: transparent;
    border: 1px solid rgba(239, 68, 68, 0.5);
    border-radius: 8px;
    color: #f87171;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}

.modal-footer .btn-delete:hover {
    background: rgba(239, 68, 68, 0.15);
}

.modal-footer .btn-cancel,
.modal-footer .btn-save {
    padding: 9px 16px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    font-family: 'Nunito', sans-serif;
}

.modal-footer .btn-cancel {
    background: transparent;
    border: 2px solid var(--border);
    color: var(--text-secondary);
}

.modal-footer .btn-cancel:hover {
    border-color: var(--text-secondary);
    color: var(--text-primary);
}

.modal-footer .btn-save {
    background: var(--accent);
    border: none;
    color: white;
}

.modal-footer .btn-save:hover {
    background: var(--accent-hover);
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

<?php if ($snapshotView): ?>
<!-- Баннер просмотра версии -->
<div class="snapshot-banner">
    <span class="material-icons">history</span>
    <span>Версия от <strong><?= date('d.m.Y H:i', strtotime($snapshotView['updated_at'])) ?></strong> — только просмотр</span>
    <a href="planner.php" class="snapshot-back">Вернуться к расписанию</a>
</div>
<?php endif; ?>

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

        <div class="filter-group">
            <button class="day-filter-btn" id="morningToggle" onclick="toggleMorning()" title="Показать утренние часы будней (08:00–14:00) для добавления уроков">
                ☀ Утро
            </button>
        </div>

        <div class="filter-divider"></div>

        <div class="filter-group">
            <span class="filter-label">Преп:</span>
            <?php foreach ($teacherCols as $tc): ?>
                <button class="room-filter-btn active" data-teacher="<?= $tc['id'] ?>" onclick="toggleTeacherFilter(this)">
                    <span class="teacher-dot tcolor-<?= $tc['color'] ?>"></span><?= e($tc['label']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="filter-divider"></div>

        <span class="student-count">Учеников: <strong id="studentCount">0</strong></span>

        <button class="history-btn" onclick="openHistoryModal()">
            <span class="material-icons">history</span>
            История
        </button>

        <span class="edit-hint">
            <span class="material-icons">edit</span>
            Клик — редактировать · ПКМ — удалить
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

                <?php for ($hour = 8; $hour <= 21; $hour++):
                    $time = sprintf('%02d:00', $hour);

                    // Утренние строки будней (до 15:00): скрыты, если пустые
                    $rowClass = '';
                    if ($hour < 15) {
                        $rowHasLessons = false;
                        foreach ($teacherCols as $tcCheck) {
                            for ($dCheck = 1; $dCheck <= 5; $dCheck++) {
                                $b = $blocks["{$dCheck}_{$time}_{$tcCheck['id']}"] ?? null;
                                if ($b && (($b['title'] && trim($b['title']['content']) !== '') || !empty($b['students']))) {
                                    $rowHasLessons = true;
                                    break 2;
                                }
                            }
                        }
                        $rowClass = ' morning-row' . ($rowHasLessons ? ' has-lessons' : '');
                    }
                ?>
                    <div class="time-cell<?= $rowClass ?>"><?= $time ?></div>
                    <?php for ($dayNum = 1; $dayNum <= 5; $dayNum++): ?>
                        <div class="schedule-cell<?= $rowClass ?>" data-day="<?= $dayNum ?>" data-time="<?= $time ?>">
                            <div class="rooms-container" style="grid-template-columns: repeat(<?= count($teacherCols) ?>, 1fr);">
                                <?php foreach ($teacherCols as $tc) renderLessonBlock($dayNum, $time, $tc, $blocks); ?>
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
                            <div class="rooms-container" style="grid-template-columns: repeat(<?= count($teacherCols) ?>, 1fr);">
                                <?php foreach ($teacherCols as $tc) renderLessonBlock($dayNum, $time, $tc, $blocks); ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<!-- Модалка ученика -->
<div id="cellModal" class="modal-overlay">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 id="cellModalTitle">Ученик</h3>
            <span class="modal-slot-info" id="cellModalSlot"></span>
        </div>
        <div class="modal-body">
            <label class="field-label" for="cellModalName">Имя ученика</label>
            <input type="text" id="cellModalName" maxlength="160" placeholder="Например: Артём" autocomplete="off">

            <label class="temp-checkbox">
                <input type="checkbox" id="cellModalTemp">
                <span>
                    <span class="temp-label">⏳ Временно</span>
                    <div class="temp-hint">Только на ближайший урок — после него запись удалится автоматически</div>
                </span>
            </label>
        </div>
        <div class="modal-footer">
            <button class="btn-delete" id="cellModalDelete" onclick="deleteCellModal()">Удалить</button>
            <button class="btn-cancel" onclick="closeCellModal()">Отмена</button>
            <button class="btn-save" id="cellModalSave" onclick="saveCellModal()">Сохранить</button>
        </div>
    </div>
</div>

<!-- Модалка истории версий -->
<div id="historyModal" class="modal-overlay">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3>История изменений</h3>
            <span class="modal-slot-info">версии создаются при правках</span>
        </div>
        <div class="modal-body">
            <div class="history-list" id="historyList">
                <div class="history-empty">Загрузка…</div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeHistoryModal()">Закрыть</button>
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
const READ_ONLY = <?= $snapshotView ? 'true' : 'false' ?>;

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

    if (localStorage.getItem('plannerShowMorning') === '1') {
        document.body.classList.add('show-morning');
        document.getElementById('morningToggle').classList.add('active');
    }

    if (READ_ONLY) {
        document.body.classList.add('readonly');
    }
});

// ========== УТРЕННИЕ ЧАСЫ БУДНЕЙ ==========

function toggleMorning() {
    const show = !document.body.classList.contains('show-morning');
    document.body.classList.toggle('show-morning', show);
    document.getElementById('morningToggle').classList.toggle('active', show);
    localStorage.setItem('plannerShowMorning', show ? '1' : '0');
}

// ========== РЕДАКТИРОВАНИЕ ЗАГОЛОВКА БЛОКА (инлайн) ==========

let editingEl = null;

document.addEventListener('click', function(e) {
    if (READ_ONLY) return;

    // Заголовок блока — инлайн-редактирование
    const title = e.target.closest('.block-title');
    if (title && !title.querySelector('input')) {
        beginEdit(title);
        return;
    }

    // Плашка ученика — модалка
    const cell = e.target.closest('.pcell');
    if (cell) {
        openCellModal(cell.closest('.lesson-block'), cell);
        return;
    }

    // Кнопка «+» — модалка для новой ячейки
    const addBtn = e.target.closest('.add-cell-btn');
    if (addBtn) {
        openCellModal(addBtn.closest('.lesson-block'), null);
    }
});

function beginEdit(cell) {
    if (editingEl && editingEl !== cell) {
        const prev = editingEl.querySelector('input');
        if (prev) prev.blur();
    }

    const original = cell.dataset.id ? cell.textContent : '';
    let cancelled = false;

    const input = document.createElement('input');
    input.type = 'text';
    input.maxLength = 160;
    input.size = 1;
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
        }
        e.stopPropagation();
    });

    input.addEventListener('blur', function() {
        cell.classList.remove('editing');
        editingEl = null;
        if (cancelled) {
            renderTitleContent(cell, original.trim());
            return;
        }
        commitTitleEdit(cell, input.value.trim(), original.trim());
    });

    input.focus();
    input.select();
}

function renderTitleContent(cell, text) {
    cell.textContent = text;
    cell.classList.toggle('is-empty', text === '');
}

async function commitTitleEdit(cell, value, original) {
    if (value === original) {
        renderTitleContent(cell, original);
        return;
    }

    if (value === '' && !cell.dataset.id) {
        renderTitleContent(cell, '');
        return;
    }

    const block = cell.closest('.lesson-block');
    const payload = {
        id: cell.dataset.id ? parseInt(cell.dataset.id) : null,
        day: parseInt(block.dataset.day),
        time: block.dataset.time,
        teacher_id: parseInt(block.dataset.teacher),
        kind: 'title',
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
            renderTitleContent(cell, original);
            return;
        }

        if (value === '') {
            delete cell.dataset.id;
        } else if (result.data && result.data.id) {
            cell.dataset.id = result.data.id;
        }
        renderTitleContent(cell, value);
    } catch (err) {
        console.error('Save error:', err);
        showNotification('Ошибка сети', 'error');
        renderTitleContent(cell, original);
    }
}

// ========== МОДАЛКА УЧЕНИКА ==========

const DAY_SHORT = {1: 'Пн', 2: 'Вт', 3: 'Ср', 4: 'Чт', 5: 'Пт', 6: 'Сб', 7: 'Вс'};
let modalCell = null;
let modalBlock = null;

function openCellModal(block, cell) {
    if (!block) return;
    modalBlock = block;
    modalCell = cell;

    const nameInput = document.getElementById('cellModalName');
    const tempCheckbox = document.getElementById('cellModalTemp');
    const deleteBtn = document.getElementById('cellModalDelete');

    const blockTeacher = teachersData.find(t => parseInt(t.id) === parseInt(block.dataset.teacher));
    const teacherLabel = blockTeacher ? (blockTeacher.display_name || blockTeacher.name) : '';

    document.getElementById('cellModalTitle').textContent = cell ? 'Ученик' : 'Добавить ученика';
    document.getElementById('cellModalSlot').textContent =
        `${DAY_SHORT[block.dataset.day]} · ${block.dataset.time} · ${teacherLabel}`;

    nameInput.value = cell ? (cell.dataset.name || '') : '';
    tempCheckbox.checked = cell ? cell.dataset.temp === '1' : false;
    deleteBtn.style.display = cell ? '' : 'none';

    document.getElementById('cellModal').classList.add('active');
    nameInput.focus();
    nameInput.select();
}

function closeCellModal() {
    document.getElementById('cellModal').classList.remove('active');
    modalCell = null;
    modalBlock = null;
}

document.getElementById('cellModal').addEventListener('click', function(e) {
    if (e.target === this) closeCellModal();
});

document.getElementById('cellModalName').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        saveCellModal();
    }
});

function renderStudentCell(cell, name, temp) {
    cell.textContent = name;
    cell.dataset.name = name;
    cell.dataset.temp = temp ? '1' : '0';
    cell.classList.toggle('temp', temp);
    if (temp) {
        const badge = document.createElement('span');
        badge.className = 'temp-badge';
        badge.title = 'Временно — только на ближайший урок';
        badge.textContent = '⏳';
        cell.appendChild(badge);
    }
}

async function saveCellModal() {
    if (!modalBlock) return;

    const name = document.getElementById('cellModalName').value.trim();
    const temp = document.getElementById('cellModalTemp').checked;

    if (!name) {
        showNotification('Введите имя ученика', 'error');
        return;
    }

    const block = modalBlock;
    const cell = modalCell;
    const saveBtn = document.getElementById('cellModalSave');
    saveBtn.disabled = true;

    // Цвет плашки = цвет преподавателя-колонки
    const inheritedColor = parseInt(block.dataset.color) || 1;

    const payload = {
        id: cell && cell.dataset.id ? parseInt(cell.dataset.id) : null,
        day: parseInt(block.dataset.day),
        time: block.dataset.time,
        teacher_id: parseInt(block.dataset.teacher),
        kind: 'student',
        content: name,
        temp: temp,
        color: inheritedColor
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
            return;
        }

        if (cell) {
            renderStudentCell(cell, name, temp);
        } else {
            const newCell = document.createElement('div');
            newCell.className = 'pcell c' + inheritedColor;
            newCell.dataset.kind = 'student';
            if (result.data && result.data.id) {
                newCell.dataset.id = result.data.id;
            }
            renderStudentCell(newCell, name, temp);
            block.querySelector('.block-cells').appendChild(newCell);
        }

        closeCellModal();
        updateStudentCount();
    } catch (err) {
        console.error('Save error:', err);
        showNotification('Ошибка сети', 'error');
    } finally {
        saveBtn.disabled = false;
    }
}

async function deleteCellModal() {
    if (!modalCell || !modalCell.dataset.id) return closeCellModal();

    const cell = modalCell;
    const block = modalBlock;

    const payload = {
        id: parseInt(cell.dataset.id),
        day: parseInt(block.dataset.day),
        time: block.dataset.time,
        teacher_id: parseInt(block.dataset.teacher),
        kind: 'student',
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
            cell.remove();
            closeCellModal();
            updateStudentCount();
        } else {
            showNotification(result.error || 'Ошибка удаления', 'error');
        }
    } catch (err) {
        showNotification('Ошибка сети', 'error');
    }
}

// ========== КОНТЕКСТНОЕ МЕНЮ (удаление) ==========
// Цвет больше не выбирается вручную — он определяется колонкой преподавателя

let contextTarget = null;

document.addEventListener('contextmenu', function(e) {
    if (READ_ONLY) return;
    const cell = e.target.closest('.pcell, .block-title');
    if (!cell || !cell.dataset.id) return;

    e.preventDefault();
    contextTarget = cell;

    const menu = document.getElementById('contextMenu');
    menu.innerHTML = `<div class="context-menu-item danger" onclick="deleteCell()">
                <span class="material-icons" style="font-size: 16px;">delete</span>Удалить
             </div>`;
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
    if (e.key === 'Escape') {
        hideContextMenu();
        closeCellModal();
        closeHistoryModal();
    }
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

async function deleteCell() {
    if (!contextTarget || !contextTarget.dataset.id) return hideContextMenu();
    const cell = contextTarget;
    hideContextMenu();

    const block = cell.closest('.lesson-block');
    const payload = {
        id: parseInt(cell.dataset.id),
        day: parseInt(block.dataset.day),
        time: block.dataset.time,
        teacher_id: parseInt(block.dataset.teacher),
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
                renderTitleContent(cell, '');
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

// ========== ИСТОРИЯ ВЕРСИЙ ==========

async function openHistoryModal() {
    document.getElementById('historyModal').classList.add('active');
    const list = document.getElementById('historyList');
    list.innerHTML = '<div class="history-empty">Загрузка…</div>';

    try {
        const response = await fetch('/zarplata/api/planner.php?action=list_snapshots');
        const result = await response.json();

        if (!result.success || !result.data.snapshots || result.data.snapshots.length === 0) {
            list.innerHTML = '<div class="history-empty">Версий пока нет — они создаются автоматически при изменениях расписания</div>';
            return;
        }

        list.innerHTML = '';
        result.data.snapshots.forEach(s => {
            const updated = new Date(s.updated_at.replace(' ', 'T'));
            const created = new Date(s.created_at.replace(' ', 'T'));
            const dateStr = updated.toLocaleDateString('ru-RU', {day: 'numeric', month: 'long'});
            const timeStr = updated.toLocaleTimeString('ru-RU', {hour: '2-digit', minute: '2-digit'});
            const createdTime = created.toLocaleTimeString('ru-RU', {hour: '2-digit', minute: '2-digit'});
            const range = createdTime !== timeStr ? `${createdTime}–${timeStr}` : timeStr;

            const item = document.createElement('a');
            item.className = 'history-item';
            item.href = 'planner.php?snapshot=' + s.id;
            item.innerHTML = `
                <span class="material-icons">schedule</span>
                <span class="history-item-date">${dateStr}, ${timeStr}</span>
                <span class="history-item-range">${range}</span>
            `;
            list.appendChild(item);
        });
    } catch (err) {
        list.innerHTML = '<div class="history-empty">Ошибка загрузки истории</div>';
    }
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.remove('active');
}

document.getElementById('historyModal').addEventListener('click', function(e) {
    if (e.target === this) closeHistoryModal();
});

// ========== СЧЁТЧИК УЧЕНИКОВ ==========

function updateStudentCount() {
    const unique = new Set();
    document.querySelectorAll('.pcell[data-id]').forEach(cell => {
        const name = (cell.dataset.name || cell.textContent).trim().toLowerCase();
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

const TEACHER_COUNT = <?= max(count($teacherCols), 1) ?>;

function toggleTeacherFilter(button) {
    button.classList.toggle('active');
    updateVisibleTeachers();
    saveFilters();
}

function updateVisibleTeachers() {
    const activeTeachers = Array.from(document.querySelectorAll('.room-filter-btn.active'))
        .map(btn => parseInt(btn.dataset.teacher));

    document.querySelectorAll('.lesson-block').forEach(block => {
        const teacher = parseInt(block.dataset.teacher);
        block.classList.toggle('hidden', activeTeachers.length > 0 && !activeTeachers.includes(teacher));
    });

    const visibleCount = activeTeachers.length === 0 ? TEACHER_COUNT : activeTeachers.length;
    document.querySelectorAll('.rooms-container').forEach(container => {
        container.style.gridTemplateColumns = `repeat(${visibleCount}, 1fr)`;
    });
}

function saveFilters() {
    const filters = {
        days: Array.from(document.querySelectorAll('.day-filter-btn.active')).map(btn => btn.dataset.day),
        teachers: Array.from(document.querySelectorAll('.room-filter-btn.active')).map(btn => btn.dataset.teacher)
    };
    localStorage.setItem('plannerFiltersV2', JSON.stringify(filters));
}

function restoreFilters() {
    const saved = localStorage.getItem('plannerFiltersV2');
    if (!saved) return;

    try {
        const filters = JSON.parse(saved);

        if (filters.days && filters.days.length > 0 && filters.days.length < 7) {
            document.querySelectorAll('.day-filter-btn').forEach(btn => {
                btn.classList.toggle('active', filters.days.includes(btn.dataset.day));
            });
            updateVisibleDays();
        }

        if (filters.teachers && filters.teachers.length > 0 && filters.teachers.length < TEACHER_COUNT) {
            document.querySelectorAll('.room-filter-btn').forEach(btn => {
                btn.classList.toggle('active', filters.teachers.includes(btn.dataset.teacher));
            });
            updateVisibleTeachers();
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
