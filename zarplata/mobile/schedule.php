<?php
/**
 * Mobile Schedule Page — расписание из планировщика (planner_notes)
 * Чипы преподавателей (выбирается один) + чипы дней.
 * Редактирование: тап по плашке/заголовку — модалка, «+» добавляет ученика,
 * «+ Добавить урок» создаёт новый блок на выбранный день.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/student_helpers.php';

requireAuth();
$user = getCurrentUser();

// Преподаватели (цвет = (id % 8) ?: 8, как в планировщике)
$teachers = dbQuery("
    SELECT id, COALESCE(display_name, name) AS name
    FROM teachers
    WHERE active = 1
    ORDER BY id
", []);

// Учитель видит и редактирует только своё расписание
if (isTeacherUser() && getCurrentTeacherId()) {
    $ownId = getCurrentTeacherId();
    $teachers = array_values(array_filter($teachers, fn($t) => (int)$t['id'] === $ownId));
}

$colorToTeacher = plannerColorTeacherMap();
$activeTeacherIds = array_values($colorToTeacher);
$fallbackTeacherId = $colorToTeacher[1] ?? ($activeTeacherIds[0] ?? 0);

// Все записи расписания (просроченные временные не показываем)
$notes = [];
try {
    $notes = dbQuery(
        "SELECT id, day, time, room, kind, content, color, temp_until, teacher_id
         FROM planner_notes
         WHERE (temp_until IS NULL OR temp_until >= CURDATE())
         ORDER BY day, time, room, kind, position, id",
        []
    );
} catch (PDOException $e) {
    $notes = [];
}

// Цвета легаси-заголовков по старым блокам (для фолбэка)
$legacyTitleColors = [];
foreach ($notes as $n) {
    if ($n['kind'] === 'title') {
        $legacyTitleColors["{$n['day']}_{$n['time']}_{$n['room']}"] = (int)$n['color'];
    }
}

// Группируем: день → преподаватель → "time" → блок
$days = [];
foreach ($notes as $n) {
    // Преподаватель: teacher_id → цвет ячейки → цвет блока → фолбэк
    $tid = (int)($n['teacher_id'] ?? 0);
    if (!$tid || !in_array($tid, $activeTeacherIds, true)) {
        $color = (int)$n['color'];
        if (!$color) {
            $color = $legacyTitleColors["{$n['day']}_{$n['time']}_{$n['room']}"] ?? 0;
        }
        $tid = $colorToTeacher[$color] ?? $fallbackTeacherId;
    }

    $day = (int)$n['day'];
    $time = substr($n['time'], 0, 5);
    if (!isset($days[$day][$tid][$time])) {
        $days[$day][$tid][$time] = [
            'time' => $time,
            'title' => null,
            'title_id' => null,
            'students' => []
        ];
    }
    if ($n['kind'] === 'title') {
        if ($days[$day][$tid][$time]['title'] === null || $days[$day][$tid][$time]['title'] === '') {
            $days[$day][$tid][$time]['title'] = trim($n['content']);
            $days[$day][$tid][$time]['title_id'] = (int)$n['id'];
        }
    } elseif (trim($n['content']) !== '') {
        $days[$day][$tid][$time]['students'][] = [
            'id' => (int)$n['id'],
            'name' => trim($n['content']),
            'temp' => !empty($n['temp_until'])
        ];
    }
}

// Сортируем блоки по времени
foreach ($days as $day => $byTeacher) {
    foreach ($byTeacher as $tid => $blocksList) {
        ksort($blocksList);
        $days[$day][$tid] = $blocksList;
    }
}

$dayNames = [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];
$dayFull = [1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'];
$currentDay = (int)date('N');
$weekHours = range(8, 21);

define('PAGE_TITLE', 'Расписание');
define('ACTIVE_PAGE', 'schedule');

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* Чипы преподавателей */
.teacher-chips {
    display: flex;
    gap: 6px;
    padding: 12px 16px 0;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.teacher-chips::-webkit-scrollbar { display: none; }

.teacher-chip {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
}

.teacher-chip .tdot {
    width: 10px;
    height: 10px;
    border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.25);
}

.teacher-chip.active {
    background: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
}

.scolor-1 { background: rgba(20, 184, 166, 0.7); }
.scolor-2 { background: rgba(168, 85, 247, 0.7); }
.scolor-3 { background: rgba(59, 130, 246, 0.7); }
.scolor-4 { background: rgba(249, 115, 22, 0.7); }
.scolor-5 { background: rgba(236, 72, 153, 0.7); }
.scolor-6 { background: rgba(234, 179, 8, 0.7); }
.scolor-7 { background: rgba(34, 197, 94, 0.7); }
.scolor-8 { background: rgba(239, 68, 68, 0.7); }

/* Переключатель режима */
.schedule-toolbar {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 4px;
    margin: 10px 16px 0;
    padding: 4px;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--bg-card);
}

.schedule-mode-btn {
    min-height: 38px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--text-secondary);
    font-family: inherit;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
}

.schedule-mode-btn.active {
    background: var(--accent);
    color: #04201c;
}

.schedule-settings-btn {
    width: 44px;
    min-height: 38px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg-elevated);
    color: var(--text-secondary);
    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.schedule-settings-btn.active {
    border-color: var(--accent);
    background: var(--accent-dim);
    color: var(--accent);
}

.schedule-day-mode.is-hidden { display: none; }

body.schedule-week-mode {
    overscroll-behavior-y: none;
}

body.schedule-week-mode .mobile-content {
    overflow: hidden;
}

/* Чипы дней */
.day-chips {
    display: flex;
    gap: 6px;
    padding: 10px 16px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.day-chips::-webkit-scrollbar { display: none; }

.day-chip {
    flex-shrink: 0;
    padding: 8px 14px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
}

.day-chip.active {
    background: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent);
}

/* День */
.day-pane { display: none; padding: 0 16px 16px; }
.day-pane.active { display: block; }

.teacher-pane { display: none; }
.teacher-pane.active { display: block; }

.week-pane { display: none; padding: 10px 0 0; }
.week-pane.active { display: block; }

.day-empty {
    text-align: center;
    padding: 32px 16px;
    color: var(--text-muted);
    font-size: 14px;
}

/* Карточка блока урока */
.lesson-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 10px;
    overflow: hidden;
}

.lesson-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: rgba(255, 255, 255, 0.04);
    border-bottom: 1px solid var(--border);
}

.lesson-card.bc1 .lesson-card-header { background: rgba(20, 184, 166, 0.22); }
.lesson-card.bc2 .lesson-card-header { background: rgba(168, 85, 247, 0.22); }
.lesson-card.bc3 .lesson-card-header { background: rgba(59, 130, 246, 0.22); }
.lesson-card.bc4 .lesson-card-header { background: rgba(249, 115, 22, 0.22); }
.lesson-card.bc5 .lesson-card-header { background: rgba(236, 72, 153, 0.22); }
.lesson-card.bc6 .lesson-card-header { background: rgba(234, 179, 8, 0.22); }
.lesson-card.bc7 .lesson-card-header { background: rgba(34, 197, 94, 0.22); }
.lesson-card.bc8 .lesson-card-header { background: rgba(239, 68, 68, 0.22); }

.week-scroll {
    position: relative;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    padding: 0 0 10px;
    --week-bottom-clearance: calc(var(--bottom-nav-height, 64px) + var(--safe-area-bottom, 0px));
    max-height: calc(100dvh - 190px - var(--week-bottom-clearance));
    scroll-padding-bottom: 10px;
    overscroll-behavior: contain;
    --week-zoom: 1;
    --week-time-col: calc(54px * var(--week-zoom));
    --week-day-col: calc(118px * var(--week-zoom));
    --week-row-height: calc(92px * var(--week-zoom));
    --week-card-height: calc(82px * var(--week-zoom));
}

.week-grid {
    display: grid;
    grid-template-columns: var(--week-time-col) repeat(7, var(--week-day-col));
    width: max-content;
    min-width: calc(var(--week-time-col) + (7 * var(--week-day-col)));
    border: 1px solid var(--border);
    border-right: 0;
    border-bottom: 0;
    background: var(--bg-card);
}

.week-scroll[data-visible-days="1"] .week-grid { grid-template-columns: var(--week-time-col) repeat(1, var(--week-day-col)); min-width: calc(var(--week-time-col) + (1 * var(--week-day-col))); }
.week-scroll[data-visible-days="2"] .week-grid { grid-template-columns: var(--week-time-col) repeat(2, var(--week-day-col)); min-width: calc(var(--week-time-col) + (2 * var(--week-day-col))); }
.week-scroll[data-visible-days="3"] .week-grid { grid-template-columns: var(--week-time-col) repeat(3, var(--week-day-col)); min-width: calc(var(--week-time-col) + (3 * var(--week-day-col))); }
.week-scroll[data-visible-days="4"] .week-grid { grid-template-columns: var(--week-time-col) repeat(4, var(--week-day-col)); min-width: calc(var(--week-time-col) + (4 * var(--week-day-col))); }
.week-scroll[data-visible-days="5"] .week-grid { grid-template-columns: var(--week-time-col) repeat(5, var(--week-day-col)); min-width: calc(var(--week-time-col) + (5 * var(--week-day-col))); }
.week-scroll[data-visible-days="6"] .week-grid { grid-template-columns: var(--week-time-col) repeat(6, var(--week-day-col)); min-width: calc(var(--week-time-col) + (6 * var(--week-day-col))); }
.week-scroll[data-visible-days="7"] .week-grid { grid-template-columns: var(--week-time-col) repeat(7, var(--week-day-col)); min-width: calc(var(--week-time-col) + (7 * var(--week-day-col))); }

.week-head,
.week-time,
.week-cell {
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.week-head {
    position: sticky;
    top: 0;
    z-index: 2;
    min-height: calc(38px * var(--week-zoom));
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-elevated);
    color: var(--text-primary);
    font-size: calc(12px * var(--week-zoom));
    font-weight: 800;
}

.week-corner {
    left: 0;
    z-index: 4;
}

.week-day-head {
    z-index: 3;
}

.week-time {
    position: sticky;
    left: 0;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: var(--week-row-height);
    background: var(--bg-elevated);
    color: var(--text-secondary);
    font-family: 'JetBrains Mono', monospace;
    font-size: calc(12px * var(--week-zoom));
    font-weight: 700;
    box-shadow: 8px 0 14px rgba(0, 0, 0, 0.18);
}

.week-cell {
    min-height: var(--week-row-height);
    padding: calc(4px * var(--week-zoom));
}

.week-cell .lesson-card {
    min-height: var(--week-card-height);
    margin: 0;
    border-radius: 8px;
}

.week-cell .lesson-card-header {
    gap: calc(6px * var(--week-zoom));
    padding: calc(6px * var(--week-zoom)) calc(8px * var(--week-zoom));
}

.week-cell .lesson-time {
    display: none;
}

.week-cell .lesson-title {
    min-height: calc(16px * var(--week-zoom));
    font-size: calc(12px * var(--week-zoom));
    text-align: center;
}

.week-cell .lesson-students {
    gap: calc(4px * var(--week-zoom));
    padding: calc(6px * var(--week-zoom));
}

.week-cell .student-chip {
    max-width: 100%;
    padding: calc(4px * var(--week-zoom)) calc(6px * var(--week-zoom));
    border-radius: 6px;
    font-size: calc(11px * var(--week-zoom));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.week-cell .add-student-chip {
    width: 100%;
    padding: calc(4px * var(--week-zoom));
    border-radius: 6px;
    font-size: calc(12px * var(--week-zoom));
}

.week-empty-slot {
    width: 100%;
    height: var(--week-card-height);
    border: 1px dashed rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    background: transparent;
    color: var(--text-muted);
    font-family: inherit;
    font-size: calc(14px * var(--week-zoom));
}

.week-empty-slot:active {
    border-color: var(--accent);
    color: var(--accent);
}

.week-pane.hide-morning .morning-slot {
    display: none;
}

.week-time.week-row-alt,
.week-cell.week-row-alt {
    background: #171c24;
}

.week-cell.week-row-alt .lesson-card,
.week-cell.week-row-alt .week-empty-slot {
    background-color: #18202a;
}

.week-day-hidden {
    display: none;
}

.settings-section-title {
    margin: 0 0 8px;
    color: var(--text-secondary);
    font-size: 13px;
    font-weight: 800;
}

.settings-day-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.settings-day-btn {
    min-height: 38px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg-elevated);
    color: var(--text-secondary);
    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
}

.settings-day-btn.active {
    border-color: var(--accent);
    background: var(--accent-dim);
    color: var(--accent);
}

.lesson-time {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 700;
    font-size: 15px;
    color: var(--text-primary);
}

.lesson-title {
    font-weight: 700;
    font-size: 14px;
    color: var(--text-primary);
    flex: 1;
    min-height: 18px;
}

.lesson-title.is-empty {
    color: var(--text-muted);
    font-weight: 500;
}

.lesson-students {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 10px 14px;
}

.student-chip {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 14px;
    color: #ecfdf5;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid transparent;
    border-left: 3px solid rgba(255, 255, 255, 0.2);
    cursor: pointer;
}

.student-chip.c1 { background: linear-gradient(135deg, rgba(20, 184, 166, 0.35), rgba(20, 184, 166, 0.15)); border-left-color: rgba(20, 184, 166, 0.9); }
.student-chip.c2 { background: linear-gradient(135deg, rgba(168, 85, 247, 0.35), rgba(168, 85, 247, 0.15)); border-left-color: rgba(168, 85, 247, 0.9); }
.student-chip.c3 { background: linear-gradient(135deg, rgba(59, 130, 246, 0.35), rgba(59, 130, 246, 0.15)); border-left-color: rgba(59, 130, 246, 0.9); }
.student-chip.c4 { background: linear-gradient(135deg, rgba(249, 115, 22, 0.35), rgba(249, 115, 22, 0.15)); border-left-color: rgba(249, 115, 22, 0.9); }
.student-chip.c5 { background: linear-gradient(135deg, rgba(236, 72, 153, 0.35), rgba(236, 72, 153, 0.15)); border-left-color: rgba(236, 72, 153, 0.9); }
.student-chip.c6 { background: linear-gradient(135deg, rgba(234, 179, 8, 0.35), rgba(234, 179, 8, 0.15)); border-left-color: rgba(234, 179, 8, 0.9); }
.student-chip.c7 { background: linear-gradient(135deg, rgba(34, 197, 94, 0.35), rgba(34, 197, 94, 0.15)); border-left-color: rgba(34, 197, 94, 0.9); }
.student-chip.c8 { background: linear-gradient(135deg, rgba(239, 68, 68, 0.35), rgba(239, 68, 68, 0.15)); border-left-color: rgba(239, 68, 68, 0.9); }

.student-chip.temp {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.4), rgba(245, 158, 11, 0.16)) !important;
    border: 1px dashed rgba(245, 158, 11, 0.75) !important;
    border-left: 3px solid #f59e0b !important;
    color: #fef3c7;
}

/* Кнопка + в карточке */
.add-student-chip {
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-muted);
    background: transparent;
    border: 1px dashed rgba(255, 255, 255, 0.15);
    cursor: pointer;
}

.add-student-chip:active {
    border-color: var(--accent);
    color: var(--accent);
}

/* Кнопка добавления урока */
.add-lesson-btn {
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    border: 1px dashed var(--border);
    background: transparent;
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
}

.add-lesson-btn:active {
    border-color: var(--accent);
    color: var(--accent);
}

/* ===== Модалка ===== */
.sched-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 10000;
    align-items: flex-end;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.sched-modal-overlay.active { display: flex; }

.sched-modal {
    background: var(--bg-card);
    border-radius: 16px 16px 0 0;
    width: 100%;
    max-width: 480px;
    padding-bottom: env(safe-area-inset-bottom, 0);
    animation: sheetUp 0.25s ease;
}

@keyframes sheetUp {
    from { transform: translateY(40px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.sched-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
}

.sched-modal-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
}

.sched-modal-slot {
    font-size: 12px;
    color: var(--text-muted);
    font-family: 'JetBrains Mono', monospace;
}

.sched-modal-body { padding: 16px 20px; }

.sched-modal-body label.fld {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 12px 0 6px;
}

.sched-modal-body label.fld:first-child { margin-top: 0; }

.sched-modal-body input[type="text"],
.sched-modal-body select {
    width: 100%;
    padding: 12px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 16px;
    box-sizing: border-box;
    font-family: inherit;
}

.sched-temp-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    padding: 12px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
}

.sched-temp-row input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: var(--accent);
    flex-shrink: 0;
}

.sched-temp-row .t-label { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.sched-temp-row .t-hint { font-size: 11px; color: var(--text-muted); }

.sched-modal-footer {
    display: flex;
    gap: 10px;
    padding: 14px 20px 20px;
    border-top: 1px solid var(--border);
}

.sched-modal-footer .m-delete {
    margin-right: auto;
    padding: 12px 16px;
    background: transparent;
    border: 1px solid rgba(239, 68, 68, 0.5);
    border-radius: 10px;
    color: #f87171;
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
}

.sched-modal-footer .m-cancel {
    padding: 12px 16px;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
}

.sched-modal-footer .m-save {
    padding: 12px 22px;
    background: var(--accent);
    border: none;
    border-radius: 10px;
    color: #04201c;
    font-size: 14px;
    font-weight: 700;
    font-family: inherit;
}

/* Тост */
.sched-toast {
    position: fixed;
    bottom: calc(var(--bottom-nav-height, 64px) + 16px);
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: var(--bg-elevated);
    color: var(--text-primary);
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 14px;
    z-index: 10001;
    opacity: 0;
    transition: all 0.25s;
    pointer-events: none;
    border-left: 3px solid #22c55e;
    white-space: nowrap;
}

.sched-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.sched-toast.err { border-left-color: #f43f5e; }

/* Анимация свайпа между днями */
.day-pane.pane-enter-right { animation: paneFromRight .26s ease; }
.day-pane.pane-enter-left  { animation: paneFromLeft  .26s ease; }
@keyframes paneFromRight { from { transform: translateX(32px);  opacity: 0; } to { transform: none; opacity: 1; } }
@keyframes paneFromLeft  { from { transform: translateX(-32px); opacity: 0; } to { transform: none; opacity: 1; } }
@media (prefers-reduced-motion: reduce) {
    .day-pane.pane-enter-right, .day-pane.pane-enter-left { animation: none; }
}
</style>

<!-- Чипы преподавателей (выбирается один) -->
<div class="teacher-chips">
    <?php foreach ($teachers as $i => $teacher):
        $colorIndex = ($teacher['id'] % 8) ?: 8;
    ?>
        <button class="teacher-chip" data-teacher="<?= $teacher['id'] ?>" data-color="<?= $colorIndex ?>" data-name="<?= e($teacher['name']) ?>" onclick="selectTeacher(<?= $teacher['id'] ?>)">
            <span class="tdot scolor-<?= $colorIndex ?>"></span><?= e($teacher['name']) ?>
        </button>
    <?php endforeach; ?>
</div>

<div class="schedule-toolbar" role="group" aria-label="Режим расписания">
    <button class="schedule-mode-btn active" type="button" data-mode="day" onclick="setScheduleMode('day')">День</button>
    <button class="schedule-mode-btn" type="button" data-mode="week" onclick="setScheduleMode('week')">Вся неделя</button>
    <button class="schedule-settings-btn" type="button" onclick="openScheduleSettings()" aria-label="Настройки расписания">⚙</button>
</div>

<div id="scheduleDayMode" class="schedule-day-mode">
<!-- Чипы дней -->
<div class="day-chips">
    <?php foreach ($dayNames as $d => $short): ?>
        <button class="day-chip <?= $d === $currentDay ? 'active' : '' ?>" data-day="<?= $d ?>" onclick="showDay(<?= $d ?>)">
            <?= $short ?><?= $d === $currentDay ? ' · сегодня' : '' ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Дни × преподаватели -->
<?php foreach ($dayNames as $d => $short): ?>
    <div class="day-pane <?= $d === $currentDay ? 'active' : '' ?>" data-day="<?= $d ?>">
        <?php foreach ($teachers as $teacher):
            $tid = (int)$teacher['id'];
            $colorIndex = ($tid % 8) ?: 8;
            $blocksList = $days[$d][$tid] ?? [];
        ?>
            <div class="teacher-pane" data-teacher="<?= $tid ?>">
                <?php if (empty($blocksList)): ?>
                    <div class="day-empty"><?= $dayFull[$d] ?>: занятий нет</div>
                <?php else: ?>
                    <?php foreach ($blocksList as $block): ?>
                        <div class="lesson-card bc<?= $colorIndex ?>" data-day="<?= $d ?>" data-time="<?= $block['time'] ?>" data-teacher="<?= $tid ?>" data-color="<?= $colorIndex ?>">
                            <div class="lesson-card-header">
                                <span class="lesson-time"><?= $block['time'] ?></span>
                                <span class="lesson-title<?= ($block['title'] ?? '') === '' || $block['title'] === null ? ' is-empty' : '' ?>"
                                      <?= $block['title_id'] ? 'data-id="' . $block['title_id'] . '"' : '' ?>
                                      onclick="openTitleModal(this)"><?= $block['title'] !== null && $block['title'] !== '' ? e($block['title']) : 'без названия' ?></span>
                            </div>
                            <div class="lesson-students">
                                <?php foreach ($block['students'] as $st): ?>
                                    <span class="student-chip c<?= $colorIndex ?><?= $st['temp'] ? ' temp' : '' ?>"
                                          data-id="<?= $st['id'] ?>"
                                          data-name="<?= e($st['name']) ?>"
                                          data-temp="<?= $st['temp'] ? 1 : 0 ?>"
                                          onclick="openStudentModal(this)"><?= e($st['name']) ?><?= $st['temp'] ? ' ⏳' : '' ?></span>
                                <?php endforeach; ?>
                                <button class="add-student-chip" onclick="openStudentModalNew(this)">+</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <button class="add-lesson-btn" data-day="<?= $d ?>" data-teacher="<?= $tid ?>" onclick="openLessonModal(this)">+ Добавить урок</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
</div>

<!-- Вся неделя × преподаватели -->
<?php foreach ($teachers as $teacher):
    $tid = (int)$teacher['id'];
    $colorIndex = ($tid % 8) ?: 8;
?>
    <div class="week-pane" data-teacher="<?= $tid ?>">
        <div class="week-scroll">
            <div class="week-grid">
                <div class="week-head week-corner"></div>
                <?php foreach ($dayNames as $d => $short): ?>
                    <div class="week-head week-day-head" data-week-day="<?= $d ?>"><?= e($short) ?></div>
                <?php endforeach; ?>

                <?php foreach ($weekHours as $h):
                    $time = sprintf('%02d:00', $h);
                    $rowToneClass = $h % 2 === 0 ? ' week-row-base' : ' week-row-alt';
                ?>
                    <div class="week-time week-row<?= $rowToneClass ?><?= $h < 14 ? ' morning-slot' : '' ?>"><?= $time ?></div>
                    <?php foreach ($dayNames as $d => $short):
                        $block = $days[$d][$tid][$time] ?? null;
                    ?>
                        <div class="week-cell week-row<?= $rowToneClass ?><?= $h < 14 ? ' morning-slot' : '' ?>" data-week-day="<?= $d ?>">
                            <?php if ($block): ?>
                                <div class="lesson-card bc<?= $colorIndex ?>" data-day="<?= $d ?>" data-time="<?= $time ?>" data-teacher="<?= $tid ?>" data-color="<?= $colorIndex ?>">
                                    <div class="lesson-card-header">
                                        <span class="lesson-time"><?= $time ?></span>
                                        <span class="lesson-title<?= ($block['title'] ?? '') === '' || $block['title'] === null ? ' is-empty' : '' ?>"
                                              <?= $block['title_id'] ? 'data-id="' . $block['title_id'] . '"' : '' ?>
                                              onclick="openTitleModal(this)"><?= $block['title'] !== null && $block['title'] !== '' ? e($block['title']) : 'без названия' ?></span>
                                    </div>
                                    <div class="lesson-students">
                                        <?php foreach ($block['students'] as $st): ?>
                                            <span class="student-chip c<?= $colorIndex ?><?= $st['temp'] ? ' temp' : '' ?>"
                                                  data-id="<?= $st['id'] ?>"
                                                  data-name="<?= e($st['name']) ?>"
                                                  data-temp="<?= $st['temp'] ? 1 : 0 ?>"
                                                  onclick="openStudentModal(this)"><?= e($st['name']) ?><?= $st['temp'] ? ' ⏳' : '' ?></span>
                                        <?php endforeach; ?>
                                        <button class="add-student-chip" onclick="openStudentModalNew(this)">+</button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <button class="week-empty-slot" type="button" data-day="<?= $d ?>" data-teacher="<?= $tid ?>" data-time="<?= $time ?>" onclick="openLessonModal(this)">+</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Модалка ученика / заголовка -->
<div id="schedModal" class="sched-modal-overlay">
    <div class="sched-modal" onclick="event.stopPropagation()">
        <div class="sched-modal-header">
            <h3 id="schedModalTitle">Ученик</h3>
            <span class="sched-modal-slot" id="schedModalSlot"></span>
        </div>
        <div class="sched-modal-body">
            <label class="fld" id="schedModalNameLabel">Имя ученика</label>
            <input type="text" id="schedModalName" maxlength="160" autocomplete="off">
            <div class="sched-temp-row" id="schedModalTempRow">
                <input type="checkbox" id="schedModalTemp">
                <div>
                    <div class="t-label">⏳ Временно</div>
                    <div class="t-hint">Только на ближайший урок — потом удалится</div>
                </div>
            </div>
        </div>
        <div class="sched-modal-footer">
            <button class="m-delete" id="schedModalDelete" onclick="deleteFromModal()">Удалить</button>
            <button class="m-cancel" onclick="closeSchedModal()">Отмена</button>
            <button class="m-save" id="schedModalSave" onclick="saveFromModal()">Сохранить</button>
        </div>
    </div>
</div>

<!-- Модалка нового урока -->
<div id="lessonModal" class="sched-modal-overlay">
    <div class="sched-modal" onclick="event.stopPropagation()">
        <div class="sched-modal-header">
            <h3>Новый урок</h3>
            <span class="sched-modal-slot" id="lessonModalSlot"></span>
        </div>
        <div class="sched-modal-body">
            <label class="fld">Время</label>
            <select id="lessonModalTime">
                <?php for ($h = 8; $h <= 21; $h++): ?>
                    <option value="<?= sprintf('%02d:00', $h) ?>"><?= sprintf('%02d:00', $h) ?></option>
                <?php endfor; ?>
            </select>
            <label class="fld">Название (класс + предмет)</label>
            <input type="text" id="lessonModalTitle" maxlength="160" placeholder="Например: 9 Мат." autocomplete="off">
        </div>
        <div class="sched-modal-footer">
            <button class="m-cancel" onclick="closeLessonModal()">Отмена</button>
            <button class="m-save" onclick="saveLessonModal()">Создать</button>
        </div>
    </div>
</div>

<!-- Настройки недельного вида -->
<div id="scheduleSettingsModal" class="sched-modal-overlay">
    <div class="sched-modal" onclick="event.stopPropagation()">
        <div class="sched-modal-header">
            <h3>Настройки</h3>
            <span class="sched-modal-slot">Вся неделя</span>
        </div>
        <div class="sched-modal-body">
            <div class="sched-temp-row">
                <input type="checkbox" id="settingsMorningToggle" onchange="setMorningSlots(this.checked)">
                <div>
                    <div class="t-label">Показывать утро</div>
                    <div class="t-hint">08:00-13:00 в недельной сетке</div>
                </div>
            </div>

            <div style="margin-top: 16px;">
                <p class="settings-section-title">Дни недели</p>
                <div class="settings-day-grid">
                    <?php foreach ($dayNames as $d => $short): ?>
                        <button class="settings-day-btn" type="button" data-settings-day="<?= $d ?>" onclick="toggleVisibleWeekDay(<?= $d ?>)"><?= e($short) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="sched-modal-footer">
            <button class="m-cancel" onclick="closeScheduleSettings()">Готово</button>
        </div>
    </div>
</div>

<div id="schedToast" class="sched-toast"></div>

<script>
const DAY_SHORT = {1: 'Пн', 2: 'Вт', 3: 'Ср', 4: 'Чт', 5: 'Пт', 6: 'Сб', 7: 'Вс'};
const WEEK_ZOOM_MIN = 0.7;
const WEEK_ZOOM_MAX = 1.45;
const ALL_WEEK_DAYS = [1, 2, 3, 4, 5, 6, 7];

// ===== Выбор преподавателя (один) =====

function selectTeacher(id) {
    const modeButton = document.querySelector('.schedule-mode-btn.active');
    const isWeekMode = modeButton ? modeButton.dataset.mode === 'week' : localStorage.getItem('mobileScheduleMode') === 'week';

    document.querySelectorAll('.teacher-chip').forEach(chip => {
        chip.classList.toggle('active', parseInt(chip.dataset.teacher) === id);
    });
    document.querySelectorAll('.teacher-pane').forEach(pane => {
        pane.classList.toggle('active', parseInt(pane.dataset.teacher) === id);
    });
    document.querySelectorAll('.week-pane').forEach(pane => {
        pane.classList.toggle('active', isWeekMode && parseInt(pane.dataset.teacher) === id);
    });
    localStorage.setItem('mobileScheduleTeacher', id);
}

function currentTeacher() {
    const chip = document.querySelector('.teacher-chip.active');
    return chip ? parseInt(chip.dataset.teacher) : null;
}

function setScheduleMode(mode) {
    const nextMode = mode === 'week' ? 'week' : 'day';
    document.querySelectorAll('.schedule-mode-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.mode === nextMode);
    });
    const dayMode = document.getElementById('scheduleDayMode');
    if (dayMode) dayMode.classList.toggle('is-hidden', nextMode === 'week');
    document.querySelectorAll('.week-pane').forEach(pane => {
        const isCurrentTeacher = parseInt(pane.dataset.teacher) === currentTeacher();
        pane.classList.toggle('active', nextMode === 'week' && isCurrentTeacher);
    });
    document.body.classList.toggle('schedule-week-mode', nextMode === 'week');
    localStorage.setItem('mobileScheduleMode', nextMode);
}

function setMorningSlots(forceValue = null) {
    const current = localStorage.getItem('mobileScheduleShowMorning') === '1';
    const showMorning = forceValue === null ? !current : Boolean(forceValue);

    const morningInput = document.getElementById('settingsMorningToggle');
    if (morningInput) morningInput.checked = showMorning;
    document.querySelectorAll('.week-pane').forEach(pane => {
        pane.classList.toggle('hide-morning', !showMorning);
    });
    localStorage.setItem('mobileScheduleShowMorning', showMorning ? '1' : '0');
    syncScheduleSettingsButton();
}

function getVisibleWeekDays() {
    const raw = localStorage.getItem('mobileScheduleWeekDays');
    if (!raw) return [...ALL_WEEK_DAYS];

    const parsed = raw.split(',')
        .map(day => parseInt(day, 10))
        .filter(day => ALL_WEEK_DAYS.includes(day));

    return parsed.length ? [...new Set(parsed)] : [...ALL_WEEK_DAYS];
}

function setVisibleWeekDays(days) {
    const visibleDays = days.filter(day => ALL_WEEK_DAYS.includes(day));
    const safeDays = visibleDays.length ? visibleDays : [ALL_WEEK_DAYS[0]];
    const visibleSet = new Set(safeDays);

    document.querySelectorAll('[data-week-day]').forEach(el => {
        el.classList.toggle('week-day-hidden', !visibleSet.has(parseInt(el.dataset.weekDay, 10)));
    });
    document.querySelectorAll('.settings-day-btn').forEach(btn => {
        btn.classList.toggle('active', visibleSet.has(parseInt(btn.dataset.settingsDay, 10)));
    });
    document.querySelectorAll('.week-scroll').forEach(scroll => {
        scroll.dataset.visibleDays = String(safeDays.length);
    });

    localStorage.setItem('mobileScheduleWeekDays', safeDays.join(','));
    syncScheduleSettingsButton();
}

function toggleVisibleWeekDay(day) {
    const visibleDays = getVisibleWeekDays();
    const nextDays = visibleDays.includes(day)
        ? visibleDays.filter(item => item !== day)
        : [...visibleDays, day].sort((a, b) => a - b);

    if (!nextDays.length) {
        toast('Нужен хотя бы один день', true);
        return;
    }

    setVisibleWeekDays(nextDays);
}

function syncScheduleSettingsButton() {
    const showMorning = localStorage.getItem('mobileScheduleShowMorning') === '1';
    const visibleDays = getVisibleWeekDays();
    const hasCustomDays = visibleDays.length !== ALL_WEEK_DAYS.length;

    document.querySelectorAll('.schedule-settings-btn').forEach(btn => {
        const isActive = showMorning || hasCustomDays;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-pressed', String(isActive));
    });
}

function openScheduleSettings() {
    const modal = document.getElementById('scheduleSettingsModal');
    if (!modal) return;
    setMorningSlots(localStorage.getItem('mobileScheduleShowMorning') === '1');
    setVisibleWeekDays(getVisibleWeekDays());
    modal.classList.add('active');
}

function closeScheduleSettings() {
    document.getElementById('scheduleSettingsModal').classList.remove('active');
}

document.getElementById('scheduleSettingsModal').addEventListener('click', function(e) {
    if (e.target === this) closeScheduleSettings();
});

function clampWeekZoom(value) {
    return Math.min(WEEK_ZOOM_MAX, Math.max(WEEK_ZOOM_MIN, value));
}

function applyWeekZoom(value) {
    const zoom = clampWeekZoom(Number(value) || 1);
    document.querySelectorAll('.week-scroll').forEach(el => {
        el.style.setProperty('--week-zoom', zoom.toFixed(2));
    });
    localStorage.setItem('mobileScheduleWeekZoom', zoom.toFixed(2));
}

function touchDistance(touches) {
    const dx = touches[0].clientX - touches[1].clientX;
    const dy = touches[0].clientY - touches[1].clientY;
    return Math.hypot(dx, dy);
}

function initWeekPinchZoom() {
    let startDistance = 0;
    let startZoom = 1;

    document.querySelectorAll('.week-scroll').forEach(scroll => {
        scroll.addEventListener('touchstart', (e) => {
            if (e.touches.length !== 2) return;
            startDistance = touchDistance(e.touches);
            startZoom = parseFloat(localStorage.getItem('mobileScheduleWeekZoom') || '1') || 1;
        }, { passive: true });

        scroll.addEventListener('touchmove', (e) => {
            if (e.touches.length !== 2 || !startDistance) return;
            e.preventDefault();
            applyWeekZoom(startZoom * (touchDistance(e.touches) / startDistance));
        }, { passive: false });

        scroll.addEventListener('touchend', (e) => {
            if (e.touches.length < 2) startDistance = 0;
        }, { passive: true });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const chips = document.querySelectorAll('.teacher-chip');
    if (!chips.length) return;
    const saved = parseInt(localStorage.getItem('mobileScheduleTeacher') || '0');
    const exists = Array.from(chips).some(c => parseInt(c.dataset.teacher) === saved);
    selectTeacher(exists ? saved : parseInt(chips[0].dataset.teacher));
    setScheduleMode(localStorage.getItem('mobileScheduleMode') || 'day');
    setMorningSlots(localStorage.getItem('mobileScheduleShowMorning') === '1');
    setVisibleWeekDays(getVisibleWeekDays());
    applyWeekZoom(localStorage.getItem('mobileScheduleWeekZoom') || '1');
    initWeekPinchZoom();
});

// ===== Дни =====

function showDay(day) {
    const prevPane = document.querySelector('.day-pane.active');
    const prevDay = prevPane ? parseInt(prevPane.dataset.day) : day;
    const forward = day > prevDay; // переход к более позднему дню

    document.querySelectorAll('.day-chip').forEach(chip => {
        const isActive = parseInt(chip.dataset.day) === day;
        chip.classList.toggle('active', isActive);
        if (isActive) chip.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
    });
    document.querySelectorAll('.day-pane').forEach(pane => {
        const isActive = parseInt(pane.dataset.day) === day;
        pane.classList.toggle('active', isActive);
        if (isActive && day !== prevDay) {
            const cls = forward ? 'pane-enter-right' : 'pane-enter-left';
            pane.classList.remove('pane-enter-left', 'pane-enter-right');
            void pane.offsetWidth; // перезапуск анимации
            pane.classList.add(cls);
            pane.addEventListener('animationend', () => pane.classList.remove(cls), { once: true });
        }
    });
}

// ── Свайп влево/вправо — переключение между днями ─────────────────────────
(function initDaySwipe() {
    let startX = 0, startY = 0, startT = 0, tracking = false;

    document.addEventListener('touchstart', (e) => {
        if (e.touches.length !== 1) { tracking = false; return; }
        if (localStorage.getItem('mobileScheduleMode') === 'week') { tracking = false; return; }
        // Игнорируем жесты на горизонтальных лентах чипов и при открытой модалке
        if (e.target.closest('.day-chips, .teacher-chips, .week-scroll, .sched-modal-overlay')) { tracking = false; return; }
        if (document.querySelector('.sched-modal-overlay.active')) { tracking = false; return; }
        const t = e.touches[0];
        startX = t.clientX;
        startY = t.clientY;
        startT = Date.now();
        tracking = true;
    }, { passive: true });

    document.addEventListener('touchend', (e) => {
        if (!tracking) return;
        tracking = false;
        const t = e.changedTouches[0];
        const dx = t.clientX - startX;
        const dy = t.clientY - startY;
        const dt = Date.now() - startT;

        // Горизонтальный жест: длинный, преобладает над вертикалью, быстрый
        if (dt > 700) return;
        if (Math.abs(dx) < 70) return;
        if (Math.abs(dx) < Math.abs(dy) * 1.8) return;

        const active = document.querySelector('.day-chip.active');
        const cur = active ? parseInt(active.dataset.day) : <?= $currentDay ?>;
        const next = dx < 0 ? cur + 1 : cur - 1; // влево → след. день, вправо → пред.
        if (next >= 1 && next <= 7) {
            showDay(next);
        }
    }, { passive: true });
})();

// ===== Модалка ученика / заголовка =====

let mTarget = null;   // элемент (плашка или заголовок), null для нового
let mCard = null;     // карточка урока
let mKind = 'student';

function modalSlotText(card) {
    const chip = document.querySelector('.teacher-chip.active');
    const tname = chip ? chip.dataset.name : '';
    return `${DAY_SHORT[card.dataset.day]} · ${card.dataset.time} · ${tname}`;
}

function openStudentModal(el) {
    mTarget = el;
    mCard = el.closest('.lesson-card');
    mKind = 'student';

    document.getElementById('schedModalTitle').textContent = 'Ученик';
    document.getElementById('schedModalNameLabel').textContent = 'Имя ученика';
    document.getElementById('schedModalSlot').textContent = modalSlotText(mCard);
    document.getElementById('schedModalName').value = el.dataset.name || '';
    document.getElementById('schedModalTemp').checked = el.dataset.temp === '1';
    document.getElementById('schedModalTempRow').style.display = '';
    document.getElementById('schedModalDelete').style.display = '';
    document.getElementById('schedModal').classList.add('active');
}

function openStudentModalNew(btn) {
    mTarget = null;
    mCard = btn.closest('.lesson-card');
    mKind = 'student';

    document.getElementById('schedModalTitle').textContent = 'Добавить ученика';
    document.getElementById('schedModalNameLabel').textContent = 'Имя ученика';
    document.getElementById('schedModalSlot').textContent = modalSlotText(mCard);
    document.getElementById('schedModalName').value = '';
    document.getElementById('schedModalTemp').checked = false;
    document.getElementById('schedModalTempRow').style.display = '';
    document.getElementById('schedModalDelete').style.display = 'none';
    document.getElementById('schedModal').classList.add('active');
    document.getElementById('schedModalName').focus();
}

function openTitleModal(el) {
    mTarget = el;
    mCard = el.closest('.lesson-card');
    mKind = 'title';

    document.getElementById('schedModalTitle').textContent = 'Название урока';
    document.getElementById('schedModalNameLabel').textContent = 'Название (класс + предмет)';
    document.getElementById('schedModalSlot').textContent = modalSlotText(mCard);
    document.getElementById('schedModalName').value = el.classList.contains('is-empty') ? '' : (el.textContent || '').trim();
    document.getElementById('schedModalTempRow').style.display = 'none';
    document.getElementById('schedModalDelete').style.display = el.dataset.id ? '' : 'none';
    document.getElementById('schedModal').classList.add('active');
}

function closeSchedModal() {
    document.getElementById('schedModal').classList.remove('active');
    mTarget = null;
    mCard = null;
}

document.getElementById('schedModal').addEventListener('click', function(e) {
    if (e.target === this) closeSchedModal();
});

async function apiSaveNote(payload) {
    const response = await fetch('/zarplata/api/planner.php?action=save_note', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    return response.json();
}

async function saveFromModal() {
    if (!mCard) return;

    const name = document.getElementById('schedModalName').value.trim();
    const temp = document.getElementById('schedModalTemp').checked;

    if (!name && mKind === 'student') {
        toast('Введите имя', true);
        return;
    }

    const payload = {
        id: mTarget && mTarget.dataset.id ? parseInt(mTarget.dataset.id) : null,
        day: parseInt(mCard.dataset.day),
        time: mCard.dataset.time,
        teacher_id: parseInt(mCard.dataset.teacher),
        kind: mKind,
        content: name,
        temp: mKind === 'student' ? temp : false
    };

    try {
        const result = await apiSaveNote(payload);
        if (!result.success) {
            toast(result.error || 'Ошибка сохранения', true);
            return;
        }

        const color = mCard.dataset.color || '1';

        if (mKind === 'title') {
            if (name === '') {
                delete mTarget.dataset.id;
                mTarget.textContent = 'без названия';
                mTarget.classList.add('is-empty');
            } else {
                if (result.data && result.data.id) mTarget.dataset.id = result.data.id;
                mTarget.textContent = name;
                mTarget.classList.remove('is-empty');
            }
        } else if (mTarget) {
            renderChip(mTarget, name, temp);
        } else {
            const chip = document.createElement('span');
            chip.className = 'student-chip c' + color;
            chip.onclick = function() { openStudentModal(chip); };
            if (result.data && result.data.id) chip.dataset.id = result.data.id;
            renderChip(chip, name, temp);
            mCard.querySelector('.lesson-students').insertBefore(chip, mCard.querySelector('.add-student-chip'));
        }

        toast('Сохранено');
        closeSchedModal();
    } catch (err) {
        toast('Ошибка сети', true);
    }
}

function renderChip(chip, name, temp) {
    chip.dataset.name = name;
    chip.dataset.temp = temp ? '1' : '0';
    chip.classList.toggle('temp', temp);
    chip.textContent = name + (temp ? ' ⏳' : '');
}

function reloadScheduleAfterChange() {
    setTimeout(() => location.reload(), 350);
}

async function deleteFromModal() {
    if (!mTarget || !mTarget.dataset.id || !mCard) return closeSchedModal();

    const payload = {
        id: parseInt(mTarget.dataset.id),
        day: parseInt(mCard.dataset.day),
        time: mCard.dataset.time,
        teacher_id: parseInt(mCard.dataset.teacher),
        kind: mKind,
        content: ''
    };

    try {
        const result = await apiSaveNote(payload);
        if (!result.success) {
            toast(result.error || 'Ошибка удаления', true);
            return;
        }

        if (mKind === 'title') {
            delete mTarget.dataset.id;
            mTarget.textContent = 'без названия';
            mTarget.classList.add('is-empty');
        } else {
            mTarget.remove();
        }
        toast('Удалено');
        closeSchedModal();
        reloadScheduleAfterChange();
    } catch (err) {
        toast('Ошибка сети', true);
    }
}

// ===== Новый урок =====

let lessonDay = null;
let lessonTeacher = null;

function openLessonModal(btn) {
    lessonDay = parseInt(btn.dataset.day);
    lessonTeacher = parseInt(btn.dataset.teacher);

    const chip = document.querySelector('.teacher-chip.active');
    document.getElementById('lessonModalSlot').textContent = `${DAY_SHORT[lessonDay]} · ${chip ? chip.dataset.name : ''}`;
    if (btn.dataset.time) {
        document.getElementById('lessonModalTime').value = btn.dataset.time;
    }
    document.getElementById('lessonModalTitle').value = '';
    document.getElementById('lessonModal').classList.add('active');
}

function closeLessonModal() {
    document.getElementById('lessonModal').classList.remove('active');
}

document.getElementById('lessonModal').addEventListener('click', function(e) {
    if (e.target === this) closeLessonModal();
});

async function saveLessonModal() {
    const time = document.getElementById('lessonModalTime').value;
    const title = document.getElementById('lessonModalTitle').value.trim() || 'Урок';

    try {
        const result = await apiSaveNote({
            id: null,
            day: lessonDay,
            time: time,
            teacher_id: lessonTeacher,
            kind: 'title',
            content: title
        });

        if (!result.success) {
            toast(result.error || 'Ошибка', true);
            return;
        }

        toast('Урок создан');
        // Проще перезагрузить — карточка встанет на своё место по времени
        setTimeout(() => location.reload(), 400);
    } catch (err) {
        toast('Ошибка сети', true);
    }
}

// ===== Тост =====

let toastTimer = null;
function toast(message, isError = false) {
    const el = document.getElementById('schedToast');
    el.textContent = message;
    el.classList.toggle('err', isError);
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 2200);
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
