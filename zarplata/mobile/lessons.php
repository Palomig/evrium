<?php
/**
 * Mobile Lessons — урок + посещаемость
 *
 * Показывает уроки на выбранный день. При нажатии на урок открывается
 * список учеников с тайлами: зелёный = пришёл, серый = нет.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/student_helpers.php';

requireAuth();
$user = getCurrentUser();
$teacherFilter = isTeacherUser() ? (int)getCurrentTeacherId() : 0;

$today = date('Y-m-d');
$date  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : $today;
$dayOfWeek = (int)(new DateTime($date))->format('N');  // 1=Mon … 7=Sun

// ── Получить уроки на выбранный день ──────────────────────────────────────
// Источник истины — планировщик (planner_notes): берём слоты с учениками.
// Для каждого слота находим/создаём lesson_instance. Дополнительно держим
// уже существующие уроки с введёнными данными (посещаемость/доп. ученики).

// Загрузка экземпляров за день (ключ: "teacher_id|HH:MM")
$fetchInstances = function () use ($date, $teacherFilter) {
    $rows = dbQuery(
        "SELECT li.id, li.teacher_id, li.time_start, li.time_end, li.subject,
                li.expected_students, li.actual_students, li.status,
                li.extra_group_count, li.extra_individual_count,
                COALESCE(t.display_name, t.name) as teacher_name
         FROM lessons_instance li
         LEFT JOIN teachers t ON li.teacher_id = t.id
         WHERE li.lesson_date = ?" . ($teacherFilter ? " AND li.teacher_id = " . (int)$teacherFilter : "") . "
         ORDER BY li.time_start ASC",
        [$date]
    );
    $map = [];
    foreach ($rows as $r) {
        $map[$r['teacher_id'] . '|' . substr($r['time_start'], 0, 5)] = $r;
    }
    return $map;
};

// Слоты с учениками из планировщика
$slots = getLessonSlotsForDay($dayOfWeek);
if ($teacherFilter) {
    $slots = array_values(array_filter($slots, fn($s) => $s['teacher_id'] === (int)$teacherFilter));
}

// Шаблоны дня — для метаданных при создании экземпляра (время конца, формула)
$tplByKey = [];
foreach (dbQuery("SELECT * FROM lessons_template WHERE day_of_week = ? AND active = 1", [$dayOfWeek]) as $t) {
    $tplByKey[$t['teacher_id'] . '|' . substr($t['time_start'], 0, 5)] = $t;
}

$existing = $fetchInstances();

// Создаём недостающие экземпляры для слотов с учениками (сегодня и будущее)
if ($date >= $today) {
    $created = false;
    foreach ($slots as $slot) {
        $key = $slot['teacher_id'] . '|' . $slot['time'];
        if (isset($existing[$key])) {
            continue;
        }
        $tpl = $tplByKey[$key] ?? null;
        $timeEnd = $tpl['time_end'] ?? date('H:i:s', strtotime($slot['time'] . ':00') + 3600);
        $sd = getStudentsForLesson($slot['teacher_id'], $dayOfWeek, $slot['time']);

        dbExecute(
            "INSERT INTO lessons_instance
                (template_id, teacher_id, lesson_date, time_start, time_end,
                 lesson_type, subject, expected_students, formula_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')",
            [
                $tpl['id'] ?? null,
                $slot['teacher_id'],
                $date,
                $slot['time'] . ':00',
                $timeEnd,
                $tpl['lesson_type'] ?? 'group',
                $tpl['subject'] ?? ($sd['subject'] ?? null),
                max(1, $slot['count']),
                $tpl['formula_id'] ?? null,
            ]
        );
        $created = true;
    }
    if ($created) {
        $existing = $fetchInstances();
    }
}

// Собираем итоговый список: слоты с учениками + уроки с введёнными данными
$selected = [];
foreach ($slots as $slot) {
    $key = $slot['teacher_id'] . '|' . $slot['time'];
    if (isset($existing[$key])) {
        $selected[$key] = $existing[$key];
    }
}
foreach ($existing as $key => $inst) {
    if (isset($selected[$key])) {
        continue;
    }
    $hasData = (int)$inst['actual_students'] > 0
        || (int)($inst['extra_group_count'] ?? 0) > 0
        || (int)($inst['extra_individual_count'] ?? 0) > 0
        || $inst['status'] === 'completed';
    if ($hasData) {
        $selected[$key] = $inst;
    }
}

$instances = array_values($selected);
usort($instances, fn($a, $b) => strcmp($a['time_start'], $b['time_start']));

// ── Для каждого урока: список студентов + сохранённая посещаемость ─────────
foreach ($instances as &$lesson) {
    $sd = getStudentsForLesson($lesson['teacher_id'], $dayOfWeek, substr($lesson['time_start'], 0, 5));
    $lesson['students_list'] = $sd['students'];  // [{name, class}, ...]
    $lesson['subject']       = $lesson['subject'] ?: ($sd['subject'] ?? 'Урок');

    // Load attendance from DB (student_id → attended)
    $attRows = dbQuery(
        "SELECT al.student_id, al.attended, s.name
         FROM attendance_log al
         JOIN students s ON al.student_id = s.id
         WHERE al.lesson_instance_id = ?",
        [$lesson['id']]
    );

    $attended = [];
    foreach ($attRows as $r) {
        $attended[$r['name']] = (bool)$r['attended'];
    }
    $lesson['attendance'] = $attended;  // name → bool

    // Also preload student IDs for the JS
    $studentIds = dbQuery(
        "SELECT s.id, s.name FROM students s
         WHERE s.active = 1",
        []
    );
    $nameToId = [];
    foreach ($studentIds as $s) {
        $nameToId[$s['name']] = (int)$s['id'];
    }
    $lesson['student_ids'] = $nameToId;
}
unset($lesson);

// ── Format date for display ────────────────────────────────────────────────
$dayNamesRu = ['', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
$monthNamesRu = [
    '', 'января','февраля','марта','апреля','мая','июня',
    'июля','августа','сентября','октября','ноября','декабря'
];
$dateObj  = new DateTime($date);
$dayName  = $dayNamesRu[$dayOfWeek];
$dayNum   = $dateObj->format('j');
$monthNum = (int)$dateObj->format('n');
$dateDisplay = "{$dayName}, {$dayNum} {$monthNamesRu[$monthNum]}";
$isToday  = ($date === $today);

$prevDate = (new DateTime($date))->modify('-1 day')->format('Y-m-d');
$nextDate = (new DateTime($date))->modify('+1 day')->format('Y-m-d');

// VAPID public key from settings
$vapidPublicKey = '';
try {
    $vapidSetting = dbQueryOne("SELECT setting_value FROM settings WHERE setting_key = 'vapid_public_key'", []);
    $vapidPublicKey = $vapidSetting['setting_value'] ?? '';
} catch (Exception $e) { /* no push support */ }

define('PAGE_TITLE', 'Уроки');
define('ACTIVE_PAGE', 'lessons');
define('SHOW_BOTTOM_NAV', true);

$lessonsJson = json_encode($instances, JSON_UNESCAPED_UNICODE);
$vapidJson   = json_encode($vapidPublicKey);

require_once __DIR__ . '/templates/header.php';
?>

<main class="main-content">

<!-- Date navigation -->
<div class="date-nav">
    <a href="lessons.php?date=<?= $prevDate ?>" class="date-nav-btn" aria-label="Назад">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <div class="date-nav-label">
        <span class="date-nav-day"><?= htmlspecialchars($dateDisplay) ?></span>
        <?php if ($isToday): ?>
        <span class="date-nav-today">сегодня</span>
        <?php endif; ?>
    </div>
    <a href="lessons.php?date=<?= $nextDate ?>" class="date-nav-btn" aria-label="Вперёд">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
</div>

<!-- Push notification permission banner -->
<?php if ($vapidPublicKey): ?>
<div id="push-banner" class="push-banner hidden">
    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
    </svg>
    <span>Включить уведомления о начале уроков</span>
    <button id="push-enable-btn" class="push-enable-btn">Включить</button>
</div>
<?php endif; ?>

<!-- Lessons list -->
<?php if (empty($instances)): ?>
<div class="empty-state">
    <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:var(--text-muted);margin-bottom:12px">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
    </svg>
    <p style="color:var(--text-secondary);font-size:15px">Уроков нет</p>
</div>
<?php else: ?>

<?php foreach ($instances as $i => $lesson): ?>
<?php
    $time    = substr($lesson['time_start'], 0, 5);
    $timeEnd = $lesson['time_end'] ? substr($lesson['time_end'], 0, 5) : '';
    $statusClass = match($lesson['status']) {
        'completed' => 'lesson-completed',
        'cancelled' => 'lesson-cancelled',
        default     => ''
    };
    $startTs = strtotime($lesson['lesson_date'] ?? $date . ' ' . $lesson['time_start']) ?: strtotime($date . ' ' . $lesson['time_start']);
    $isStarted = time() >= $startTs;
    $extraGroup = (int)($lesson['extra_group_count'] ?? 0);
    $extraIndividual = (int)($lesson['extra_individual_count'] ?? 0);
?>
<div class="lesson-card <?= $statusClass ?>" data-lesson-id="<?= $lesson['id'] ?>">
    <!-- Lesson header (always visible) -->
    <div class="lesson-header" onclick="toggleLesson(this)">
        <div class="lesson-time">
            <span class="lesson-time-start"><?= htmlspecialchars($time) ?></span>
            <?php if ($timeEnd): ?>
            <span class="lesson-time-sep">–</span>
            <span class="lesson-time-end"><?= htmlspecialchars($timeEnd) ?></span>
            <?php endif; ?>
        </div>
        <div class="lesson-info">
            <div class="lesson-teacher"><?= htmlspecialchars($lesson['teacher_name']) ?></div>
            <div class="lesson-subject"><?= htmlspecialchars($lesson['subject']) ?></div>
        </div>
        <div class="lesson-count">
            <span class="lesson-count-actual" id="count-actual-<?= $lesson['id'] ?>"><?= (int)$lesson['actual_students'] ?></span>
            <span class="lesson-count-sep">/</span>
            <span class="lesson-count-expected"><?= (int)($lesson['expected_students'] ?: count($lesson['students_list'])) ?></span>
        </div>
        <div class="lesson-chevron">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </div>

    <!-- Student tiles (expanded area) -->
    <div class="lesson-body" id="lesson-body-<?= $lesson['id'] ?>">
        <?php if (empty($lesson['students_list'])): ?>
        <p class="lesson-no-students">Список учеников не настроен</p>
        <?php else: ?>
        <div class="student-tiles" id="tiles-<?= $lesson['id'] ?>">
            <?php foreach ($lesson['students_list'] as $student):
                $name      = $student['name'];
                $class     = $student['class'] ?? '';
                $studentId = $lesson['student_ids'][$name] ?? 0;
                $wasHere   = $lesson['attendance'][$name] ?? null;
                $tileClass = 'student-tile';
                if ($wasHere === true)  $tileClass .= ' tile-present';
                if ($wasHere === false) $tileClass .= ' tile-absent';
            ?>
            <button class="<?= $tileClass ?>"
                    data-lesson-id="<?= $lesson['id'] ?>"
                    data-student-id="<?= $studentId ?>"
                    data-student-name="<?= htmlspecialchars($name, ENT_QUOTES) ?>"
                    onclick="toggleAttendance(this)">
                <span class="tile-name"><?= htmlspecialchars($name) ?></span>
                <?php if ($class): ?>
                <span class="tile-class"><?= htmlspecialchars($class) ?> кл</span>
                <?php endif; ?>
                <span class="tile-check">✓</span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Доп. ученики -->
        <div class="lesson-extras" data-lesson-id="<?= $lesson['id'] ?>">
            <div class="extras-row">
                <div class="extras-label">
                    Доп. ученик <span class="extras-sub">(групповой)</span>
                    <span class="extras-rate">+200 ₽</span>
                </div>
                <div class="extras-stepper">
                    <button type="button" class="extras-btn" onclick="changeExtra(<?= $lesson['id'] ?>, 'group', -1)" aria-label="Минус">−</button>
                    <span class="extras-count" id="extra-group-<?= $lesson['id'] ?>"><?= $extraGroup ?></span>
                    <button type="button" class="extras-btn" onclick="changeExtra(<?= $lesson['id'] ?>, 'group', 1)" aria-label="Плюс">+</button>
                </div>
            </div>
            <div class="extras-row">
                <div class="extras-label">
                    Доп. ученик <span class="extras-sub">(индивидуальный)</span>
                    <span class="extras-rate">+900 ₽</span>
                </div>
                <div class="extras-stepper">
                    <button type="button" class="extras-btn" onclick="changeExtra(<?= $lesson['id'] ?>, 'indiv', -1)" aria-label="Минус">−</button>
                    <span class="extras-count" id="extra-indiv-<?= $lesson['id'] ?>"><?= $extraIndividual ?></span>
                    <button type="button" class="extras-btn" onclick="changeExtra(<?= $lesson['id'] ?>, 'indiv', 1)" aria-label="Плюс">+</button>
                </div>
            </div>
        </div>

        <div class="lesson-actions">
            <button class="btn-complete"
                    onclick="submitLesson(<?= $lesson['id'] ?>)"
                    id="btn-complete-<?= $lesson['id'] ?>"
                    data-start-ts="<?= (int)$startTs ?>"
                    <?= $isStarted ? '' : 'disabled' ?>>
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <?= $isStarted ? 'Сохранить посещаемость' : 'Откроется в ' . htmlspecialchars($time) ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- Daily report button -->
<div style="padding:0 0 8px">
    <button class="btn-daily-report" onclick="sendDailyReport()">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        Отправить отчёт в Telegram
    </button>
</div>
<?php endif; ?>

<!-- Toast notification -->
<div id="toast" class="toast hidden"></div>

</main>

<style>
/* Date navigation */
.date-nav {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px 4px;
}
.date-nav-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-secondary);
    text-decoration: none;
    flex-shrink: 0;
    transition: background 0.15s;
}
.date-nav-btn:active { background: var(--bg-hover); }
.date-nav-label {
    flex: 1;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}
.date-nav-day { font-weight: 700; font-size: 15px; }
.date-nav-today { font-size: 11px; color: var(--accent); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }

/* Push banner */
.push-banner {
    margin: 8px 16px;
    background: var(--bg-card);
    border: 1px solid var(--accent);
    border-radius: 12px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--accent);
    font-size: 13px;
    font-weight: 600;
}
.push-banner svg { flex-shrink: 0; }
.push-banner span { flex: 1; }
.push-enable-btn {
    background: var(--accent);
    color: #0c0f14;
    border: none;
    border-radius: 8px;
    padding: 6px 14px;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    white-space: nowrap;
    transition: opacity 0.15s;
}
.push-enable-btn:active { opacity: 0.8; }

/* Empty state */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 60px 24px;
}

/* Lesson card */
.lesson-card {
    margin: 8px 16px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    transition: border-color 0.2s;
}
.lesson-card.lesson-completed { border-color: rgba(34,197,94,0.3); }
.lesson-card.lesson-cancelled { opacity: 0.5; }

.lesson-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    cursor: pointer;
    user-select: none;
    -webkit-user-select: none;
}
.lesson-header:active { background: var(--bg-hover); }

.lesson-time {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 42px;
    flex-shrink: 0;
}
.lesson-time-start { font-size: 16px; font-weight: 700; color: var(--accent); line-height: 1.1; }
.lesson-time-sep   { font-size: 10px; color: var(--text-muted); }
.lesson-time-end   { font-size: 12px; color: var(--text-secondary); }

.lesson-info { flex: 1; min-width: 0; }
.lesson-teacher { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lesson-subject { font-size: 12px; color: var(--text-secondary); margin-top: 1px; }

.lesson-count {
    display: flex;
    align-items: baseline;
    gap: 2px;
    font-size: 13px;
    flex-shrink: 0;
}
.lesson-count-actual   { font-size: 20px; font-weight: 700; color: var(--status-green); }
.lesson-count-sep      { color: var(--text-muted); }
.lesson-count-expected { color: var(--text-secondary); }

.lesson-chevron { color: var(--text-muted); transition: transform 0.25s ease; flex-shrink: 0; }
.lesson-card.expanded .lesson-chevron { transform: rotate(180deg); }

/* Lesson body */
.lesson-body {
    display: none;
    padding: 0 16px 16px;
    border-top: 1px solid var(--border);
}
.lesson-card.expanded .lesson-body { display: block; }
.lesson-no-students { color: var(--text-muted); font-size: 13px; padding: 12px 0; }

/* Student tiles */
.student-tiles {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding-top: 14px;
}
.student-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--bg-elevated);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 10px 14px;
    min-width: 70px;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, transform 0.1s;
    position: relative;
}
.student-tile:active { transform: scale(0.94); }

.student-tile.tile-present {
    background: var(--status-green-dim);
    border-color: var(--status-green);
}
.student-tile.tile-absent {
    background: rgba(244,63,94,0.1);
    border-color: var(--status-rose);
}

.tile-name {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-primary);
    text-align: center;
}
.tile-class {
    font-size: 10px;
    color: var(--text-muted);
    margin-top: 2px;
}
.tile-check {
    position: absolute;
    top: 4px;
    right: 6px;
    font-size: 11px;
    color: var(--status-green);
    opacity: 0;
    transition: opacity 0.15s;
}
.student-tile.tile-present .tile-check { opacity: 1; }

/* Extras (доп. ученики) */
.lesson-extras {
    margin-top: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.extras-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px 12px;
}
.extras-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.3;
    flex: 1;
    min-width: 0;
}
.extras-sub {
    color: var(--text-secondary);
    font-weight: 500;
}
.extras-rate {
    display: block;
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 500;
    margin-top: 2px;
}
.extras-stepper {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.extras-btn {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-primary);
    border-radius: 8px;
    font-size: 18px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
    user-select: none;
    -webkit-user-select: none;
}
.extras-btn:active { background: var(--bg-hover); transform: scale(0.92); }
.extras-btn:disabled { opacity: 0.4; cursor: default; }
.extras-count {
    min-width: 22px;
    text-align: center;
    font-size: 15px;
    font-weight: 700;
    color: var(--text-primary);
}

/* Lesson actions */
.lesson-actions {
    margin-top: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.btn-complete {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--status-green-dim);
    border: 1px solid var(--status-green);
    color: var(--status-green);
    border-radius: 10px;
    padding: 9px 16px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s, opacity 0.15s;
}
.btn-complete:active { opacity: 0.7; }
.btn-complete:disabled {
    background: var(--bg-elevated);
    border-color: var(--border);
    color: var(--text-muted);
    cursor: default;
}
.btn-complete:disabled:active { opacity: 1; }
.lesson-done-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    font-weight: 600;
    color: var(--status-green);
}

/* Daily report button */
.btn-daily-report {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    border-radius: 12px;
    padding: 13px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    margin: 4px 0 0;
    transition: background 0.15s;
}
.btn-daily-report:active { background: var(--bg-hover); }

/* Toast */
.toast {
    position: fixed;
    bottom: calc(80px + env(safe-area-inset-bottom, 0px));
    left: 50%;
    transform: translateX(-50%);
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    z-index: 500;
    white-space: nowrap;
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
    transition: opacity 0.3s;
}
.toast.hidden { display: none; }

.main-content {
    margin-top: var(--header-height);
    padding-bottom: calc(var(--bottom-nav-height) + 24px + env(safe-area-inset-bottom, 0px));
}

/* Анимация свайпа между днями */
.main-content { transition: transform .2s ease, opacity .2s ease; will-change: transform; }
.main-content.swipe-leave-left  { transform: translateX(-32px); opacity: 0; }
.main-content.swipe-leave-right { transform: translateX(32px);  opacity: 0; }
.main-content.swipe-enter-left  { animation: dayEnterLeft  .26s ease; }
.main-content.swipe-enter-right { animation: dayEnterRight .26s ease; }
@keyframes dayEnterLeft  { from { transform: translateX(-32px); opacity: 0; } to { transform: none; opacity: 1; } }
@keyframes dayEnterRight { from { transform: translateX(32px);  opacity: 0; } to { transform: none; opacity: 1; } }
@media (prefers-reduced-motion: reduce) {
    .main-content, .main-content.swipe-enter-left, .main-content.swipe-enter-right { transition: none; animation: none; }
}
</style>

<script>
const LESSON_DATE = <?= json_encode($date) ?>;
const VAPID_PUBLIC_KEY = <?= $vapidJson ?>;
const PREV_DATE_URL = <?= json_encode('lessons.php?date=' . $prevDate) ?>;
const NEXT_DATE_URL = <?= json_encode('lessons.php?date=' . $nextDate) ?>;

// ── Свайп влево/вправо — переключение между днями ─────────────────────────
(function initDaySwipe() {
    let startX = 0, startY = 0, startT = 0, tracking = false;

    document.addEventListener('touchstart', (e) => {
        if (e.touches.length !== 1) { tracking = false; return; }
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

        // Горизонтальный жест: достаточно длинный, преобладает над вертикалью, быстрый
        if (dt > 700) return;
        if (Math.abs(dx) < 70) return;
        if (Math.abs(dx) < Math.abs(dy) * 1.8) return;

        if (dx < 0) {
            navigateDay(NEXT_DATE_URL, 'left');  // свайп влево → следующий день
        } else {
            navigateDay(PREV_DATE_URL, 'right'); // свайп вправо → предыдущий день
        }
    }, { passive: true });

    function navigateDay(url, leaveDir) {
        // Новая страница «въедет» с противоположной стороны
        try { sessionStorage.setItem('dayEnterDir', leaveDir === 'left' ? 'right' : 'left'); } catch (e) {}
        const mc = document.querySelector('.main-content');
        if (mc) mc.classList.add(leaveDir === 'left' ? 'swipe-leave-left' : 'swipe-leave-right');
        setTimeout(() => { window.location.href = url; }, 170);
    }

    // Анимация въезда после перехода свайпом
    let enterDir = null;
    try { enterDir = sessionStorage.getItem('dayEnterDir'); sessionStorage.removeItem('dayEnterDir'); } catch (e) {}
    if (enterDir) {
        const mc = document.querySelector('.main-content');
        if (mc) {
            const cls = enterDir === 'right' ? 'swipe-enter-right' : 'swipe-enter-left';
            mc.classList.add(cls);
            mc.addEventListener('animationend', () => mc.classList.remove(cls), { once: true });
        }
    }
})();

// ── Expand / collapse lesson ──────────────────────────────────────────────
function toggleLesson(header) {
    const card = header.closest('.lesson-card');
    card.classList.toggle('expanded');
}

// ── Toggle student attendance ─────────────────────────────────────────────
async function toggleAttendance(tile) {
    const lessonId    = parseInt(tile.dataset.lessonId);
    const studentId   = parseInt(tile.dataset.studentId) || 0;
    const studentName = tile.dataset.studentName || '';

    const wasPresent = tile.classList.contains('tile-present');
    const nowPresent = !wasPresent;

    // Optimistic UI
    tile.classList.remove('tile-present', 'tile-absent');
    tile.classList.add(nowPresent ? 'tile-present' : 'tile-absent');
    updateCount(lessonId);

    try {
        const res = await fetch('api/attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lesson_instance_id: lessonId, student_id: studentId, student_name: studentName, attended: nowPresent }),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
    } catch (e) {
        // Rollback
        tile.classList.remove('tile-present', 'tile-absent');
        tile.classList.add(wasPresent ? 'tile-present' : 'tile-absent');
        updateCount(lessonId);
        showToast('Ошибка сохранения');
    }
}

function updateCount(lessonId) {
    const tilesEl = document.getElementById('tiles-' + lessonId);
    if (!tilesEl) return;
    const present = tilesEl.querySelectorAll('.tile-present').length;
    const el = document.getElementById('count-actual-' + lessonId);
    if (el) el.textContent = present;
}

// ── Extra students stepper ────────────────────────────────────────────────
function changeExtra(lessonId, kind, delta) {
    const el = document.getElementById('extra-' + kind + '-' + lessonId);
    if (!el) return;
    const current = parseInt(el.textContent) || 0;
    const next = Math.max(0, current + delta);
    el.textContent = next;
}

function getExtras(lessonId) {
    const g = document.getElementById('extra-group-' + lessonId);
    const i = document.getElementById('extra-indiv-' + lessonId);
    return {
        group: g ? (parseInt(g.textContent) || 0) : 0,
        indiv: i ? (parseInt(i.textContent) || 0) : 0,
    };
}

// ── Submit attendance + extras (recalculates payment) ─────────────────────
async function submitLesson(lessonId) {
    const btn = document.getElementById('btn-complete-' + lessonId);
    if (btn && btn.disabled) return;

    // Mark all grey (unset) tiles as absent first, so they get persisted
    const tilesEl = document.getElementById('tiles-' + lessonId);
    const pending = [];
    if (tilesEl) {
        const greyTiles = tilesEl.querySelectorAll('.student-tile:not(.tile-present):not(.tile-absent)');
        for (const tile of greyTiles) {
            const studentId   = parseInt(tile.dataset.studentId) || 0;
            const studentName = tile.dataset.studentName || '';
            tile.classList.add('tile-absent');
            pending.push(fetch('api/attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lesson_instance_id: lessonId, student_id: studentId, student_name: studentName, attended: false }),
            }).catch(() => {}));
        }
    }

    if (btn) btn.disabled = true;

    try {
        // Wait for absent-marks to land so server sees correct attendance count
        if (pending.length) await Promise.all(pending);

        const extras = getExtras(lessonId);
        const res = await fetch('api/lesson_submit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                lesson_instance_id: lessonId,
                extra_group: extras.group,
                extra_individual: extras.indiv,
            }),
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.error || 'Ошибка');
            if (btn) btn.disabled = false;
            return;
        }

        const card = document.querySelector('.lesson-card[data-lesson-id="' + lessonId + '"]');
        if (card) {
            card.classList.remove('lesson-completed', 'lesson-cancelled');
            card.classList.add(data.status === 'cancelled' ? 'lesson-cancelled' : 'lesson-completed');
        }
        updateCount(lessonId);

        const total = data.total_amount || 0;
        const msg = data.status === 'cancelled'
            ? 'Отменено (0 ₽)'
            : 'Сохранено ✓ ' + total + ' ₽';
        showToast(msg);
    } catch (e) {
        showToast('Ошибка сохранения');
    } finally {
        if (btn) btn.disabled = false;
    }
}

// ── Daily Telegram report ─────────────────────────────────────────────────
async function sendDailyReport() {
    showToast('Отправляем отчёт…');
    try {
        const res  = await fetch('api/daily_report.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ date: LESSON_DATE }),
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.sent > 0 ? `Отчёт отправлен (${data.sent} учителям)` : 'Нет Telegram ID у учителей');
        } else {
            showToast('Ошибка: ' + (data.error ?? 'unknown'));
        }
    } catch (e) {
        showToast('Ошибка отправки');
    }
}

// ── Push notifications ────────────────────────────────────────────────────
(async function initPush() {
    if (!VAPID_PUBLIC_KEY || !('serviceWorker' in navigator) || !('PushManager' in window)) return;
    if (Notification.permission === 'granted') return;  // already subscribed
    if (Notification.permission === 'denied') return;   // blocked

    document.getElementById('push-banner')?.classList.remove('hidden');
})();

document.getElementById('push-enable-btn')?.addEventListener('click', async () => {
    try {
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') {
            showToast('Уведомления заблокированы');
            return;
        }

        const reg = await navigator.serviceWorker.ready;
        const existing = await reg.pushManager.getSubscription();
        if (existing) {
            await savePushSubscription(existing);
            document.getElementById('push-banner')?.classList.add('hidden');
            showToast('Уведомления включены');
            return;
        }

        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
        });

        await savePushSubscription(sub);
        document.getElementById('push-banner')?.classList.add('hidden');
        showToast('Уведомления включены');
    } catch (e) {
        console.error('Push subscribe failed:', e);
        showToast('Не удалось подключить уведомления');
    }
});

async function savePushSubscription(sub) {
    const json = sub.toJSON();
    await fetch('api/push_subscribe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action:   'subscribe',
            endpoint: json.endpoint,
            p256dh:   json.keys?.p256dh   ?? '',
            auth:     json.keys?.auth     ?? '',
        }),
    });
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw     = atob(base64);
    return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}

// ── Toast helper ──────────────────────────────────────────────────────────
let _toastTimer;
function showToast(msg) {
    const el = document.getElementById('toast');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => el.classList.add('hidden'), 2800);
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
