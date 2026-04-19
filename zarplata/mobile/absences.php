<?php
/**
 * Mobile Attendance — статистика посещений и пропусков по ученикам.
 *
 * Две вкладки:
 *   - Явки (view=present)  — сколько раз ученик был на уроке (attended=1).
 *   - Пропуски (view=absent) — сколько раз не пришёл: attendance_log.attended=0
 *     ИЛИ отсутствие строки attendance_log на уже начавшемся уроке
 *     (серый тайл = не отмечен = не пришёл).
 *
 * Периоды: week (−7 дней), month (−30 дней), all (с 2000 года).
 * Учитываются только уроки, время начала которых уже наступило,
 * и только те, что не были отменены (status != 'cancelled').
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/student_helpers.php';

requireAuth();

$allowedViews = ['absent', 'present'];
$view = $_GET['view'] ?? 'absent';
if (!in_array($view, $allowedViews, true)) {
    $view = 'absent';
}
$isPresent = $view === 'present';

$allowedPeriods = ['week', 'month', 'all'];
$period = $_GET['period'] ?? 'week';
if (!in_array($period, $allowedPeriods, true)) {
    $period = 'week';
}

$today = date('Y-m-d');
switch ($period) {
    case 'month':
        $fromDate = date('Y-m-d', strtotime('-30 days'));
        $periodLabel = 'Последние 30 дней';
        break;
    case 'all':
        $fromDate = '2000-01-01';
        $periodLabel = 'За всё время';
        break;
    default:
        $fromDate = date('Y-m-d', strtotime('-7 days'));
        $periodLabel = 'Последние 7 дней';
}

// ── Уроки в диапазоне, только уже начавшиеся и не отменённые ──────────────
$lessons = dbQuery(
    "SELECT id, teacher_id, lesson_date, time_start, status
     FROM lessons_instance
     WHERE lesson_date >= ? AND lesson_date <= ? AND status != 'cancelled'
     ORDER BY lesson_date DESC, time_start DESC",
    [$fromDate, $today]
);
$now = time();
$lessons = array_values(array_filter($lessons, function ($l) use ($now) {
    $ts = strtotime($l['lesson_date'] . ' ' . $l['time_start']);
    return $ts !== false && $ts <= $now;
}));

// ── attended=1 отметки: множество "lessonId:studentId" ────────────────────
$attendedSet = [];
if (!empty($lessons)) {
    $lessonIds = array_column($lessons, 'id');
    $placeholders = implode(',', array_fill(0, count($lessonIds), '?'));
    $rows = dbQuery(
        "SELECT lesson_instance_id, student_id FROM attendance_log
         WHERE attended = 1 AND lesson_instance_id IN ($placeholders)",
        $lessonIds
    );
    foreach ($rows as $r) {
        $attendedSet[$r['lesson_instance_id'] . ':' . $r['student_id']] = true;
    }
}

// ── name → id/class (один запрос, повторно используется) ──────────────────
$studentMap = [];  // name => ['id' => int, 'class' => ?int]
$allActive = dbQuery(
    "SELECT id, name, class FROM students WHERE active = 1",
    []
);
foreach ($allActive as $s) {
    $studentMap[$s['name']] = [
        'id' => (int)$s['id'],
        'class' => $s['class'] !== null && $s['class'] !== '' ? (int)$s['class'] : null,
    ];
}

// ── Агрегируем: для каждого урока считаем явки и пропуски по каждому ученику
$byStudent = [];  // student_id => ['name', 'class', 'expected', 'attended', 'missed', 'attended_lessons', 'missed_lessons']
foreach ($lessons as $lesson) {
    $dow = (int) (new DateTime($lesson['lesson_date']))->format('N');
    $sd  = getStudentsForLesson(
        (int)$lesson['teacher_id'],
        $dow,
        substr($lesson['time_start'], 0, 5)
    );
    foreach ($sd['students'] as $st) {
        $name = $st['name'];
        if (!isset($studentMap[$name])) continue;  // неактивный/удалён
        $sid = $studentMap[$name]['id'];
        if (!isset($byStudent[$sid])) {
            $byStudent[$sid] = [
                'id' => $sid,
                'name' => $name,
                'class' => $studentMap[$name]['class'],
                'expected' => 0,
                'attended' => 0,
                'missed' => 0,
                'attended_lessons' => [],
                'missed_lessons' => [],
            ];
        }
        $byStudent[$sid]['expected']++;
        $key = $lesson['id'] . ':' . $sid;
        $slot = [
            'date' => $lesson['lesson_date'],
            'time' => substr($lesson['time_start'], 0, 5),
        ];
        if (isset($attendedSet[$key])) {
            $byStudent[$sid]['attended']++;
            $byStudent[$sid]['attended_lessons'][] = $slot;
        } else {
            $byStudent[$sid]['missed']++;
            $byStudent[$sid]['missed_lessons'][] = $slot;
        }
    }
}

// Фильтр и сортировка зависят от вкладки
$statKey = $isPresent ? 'attended' : 'missed';
$list = array_values(array_filter($byStudent, fn($r) => $r[$statKey] > 0));
usort($list, function ($a, $b) use ($statKey) {
    if ($b[$statKey] !== $a[$statKey]) return $b[$statKey] - $a[$statKey];
    $ra = $a['expected'] ? $a[$statKey] / $a['expected'] : 0;
    $rb = $b['expected'] ? $b[$statKey] / $b['expected'] : 0;
    return $rb <=> $ra;
});

$monthNamesRu = [
    '', 'янв','фев','мар','апр','мая','июн',
    'июл','авг','сен','окт','ноя','дек'
];

function formatMissedDate(string $iso, array $monthNamesRu): string {
    $d = new DateTime($iso);
    $m = (int)$d->format('n');
    return $d->format('j') . ' ' . $monthNamesRu[$m];
}

define('PAGE_TITLE', 'Посещаемость');
define('ACTIVE_PAGE', 'absences');
define('SHOW_BOTTOM_NAV', true);

require_once __DIR__ . '/templates/header.php';
?>

<main class="main-content">

<!-- View tabs: Явки / Пропуски -->
<div class="view-tabs">
    <a href="absences.php?view=present&period=<?= htmlspecialchars($period) ?>" class="view-tab <?= $isPresent ? 'active active-present' : '' ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        Явки
    </a>
    <a href="absences.php?view=absent&period=<?= htmlspecialchars($period) ?>" class="view-tab <?= !$isPresent ? 'active active-absent' : '' ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        Пропуски
    </a>
</div>

<!-- Period tabs -->
<div class="period-tabs">
    <a href="absences.php?view=<?= htmlspecialchars($view) ?>&period=week"  class="period-tab <?= $period === 'week'  ? 'active' : '' ?>">Неделя</a>
    <a href="absences.php?view=<?= htmlspecialchars($view) ?>&period=month" class="period-tab <?= $period === 'month' ? 'active' : '' ?>">Месяц</a>
    <a href="absences.php?view=<?= htmlspecialchars($view) ?>&period=all"   class="period-tab <?= $period === 'all'   ? 'active' : '' ?>">Всё время</a>
</div>

<div class="period-caption"><?= htmlspecialchars($periodLabel) ?> · уроков: <?= count($lessons) ?></div>

<?php if (empty($list)): ?>
<div class="empty-state">
    <?php if ($isPresent): ?>
    <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:var(--text-muted);margin-bottom:12px">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <p style="color:var(--text-secondary);font-size:15px">Нет отмеченных явок за период</p>
    <?php else: ?>
    <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:var(--status-green);margin-bottom:12px">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
    </svg>
    <p style="color:var(--text-secondary);font-size:15px">Все ученики ходят — пропусков нет</p>
    <?php endif; ?>
</div>
<?php else: ?>

<ul class="absence-list">
    <?php foreach ($list as $row):
        $count = $isPresent ? (int)$row['attended'] : (int)$row['missed'];
        $pct = $row['expected'] ? round(100 * $count / $row['expected']) : 0;
        $slots = $isPresent ? $row['attended_lessons'] : $row['missed_lessons'];
        $verb = $isPresent ? 'пришёл' : 'пропустил';
    ?>
    <li class="absence-item <?= $isPresent ? 'kind-present' : 'kind-absent' ?>" onclick="toggleAbsence(this)">
        <div class="absence-row">
            <div class="absence-main">
                <div class="absence-name"><?= htmlspecialchars($row['name']) ?></div>
                <div class="absence-sub">
                    <?php if ($row['class']): ?><?= (int)$row['class'] ?> класс · <?php endif; ?>
                    <?= $verb ?> <?= $count ?> из <?= (int)$row['expected'] ?>
                </div>
            </div>
            <div class="absence-badge">
                <span class="absence-count"><?= $count ?></span>
                <span class="absence-pct"><?= $pct ?>%</span>
            </div>
            <svg class="absence-chevron" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
        <div class="absence-details">
            <?php foreach ($slots as $ml): ?>
            <span class="absence-date-chip">
                <?= htmlspecialchars(formatMissedDate($ml['date'], $monthNamesRu)) ?>
                <span class="chip-time"><?= htmlspecialchars($ml['time']) ?></span>
            </span>
            <?php endforeach; ?>
        </div>
    </li>
    <?php endforeach; ?>
</ul>

<?php endif; ?>

</main>

<style>
.view-tabs {
    display: flex;
    gap: 6px;
    padding: 12px 16px 4px;
}
.view-tab {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    border-radius: 12px;
    padding: 11px 10px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.view-tab:not(.active):active { background: var(--bg-hover); }
.view-tab.active-present {
    background: var(--status-green-dim);
    border-color: var(--status-green);
    color: var(--status-green);
}
.view-tab.active-absent {
    background: rgba(244,63,94,0.12);
    border-color: var(--status-rose);
    color: var(--status-rose);
}

.period-tabs {
    display: flex;
    gap: 6px;
    padding: 6px 16px 6px;
}
.period-tab {
    flex: 1;
    text-align: center;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    border-radius: 10px;
    padding: 9px 10px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.period-tab.active {
    background: var(--accent);
    border-color: var(--accent);
    color: #0c0f14;
}
.period-tab:not(.active):active { background: var(--bg-hover); }

.period-caption {
    padding: 2px 16px 12px;
    font-size: 12px;
    color: var(--text-muted);
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 60px 24px;
}

.absence-list {
    list-style: none;
    margin: 0;
    padding: 0 16px 8px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.absence-item {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 12px 14px;
    cursor: pointer;
    user-select: none;
    -webkit-user-select: none;
    transition: border-color 0.2s;
}
.absence-item.kind-absent.expanded  { border-color: rgba(244,63,94,0.35); }
.absence-item.kind-present.expanded { border-color: rgba(34,197,94,0.35); }
.absence-item:active { background: var(--bg-hover); }

.absence-row {
    display: flex;
    align-items: center;
    gap: 12px;
}
.absence-main { flex: 1; min-width: 0; }
.absence-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.absence-sub {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 2px;
}
.absence-badge {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
    flex-shrink: 0;
}
.absence-count {
    font-size: 22px;
    font-weight: 700;
    color: var(--status-rose);
    line-height: 1;
}
.kind-present .absence-count { color: var(--status-green); }
.absence-pct {
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 600;
}
.absence-chevron {
    color: var(--text-muted);
    transition: transform 0.2s ease;
    flex-shrink: 0;
}
.absence-item.expanded .absence-chevron { transform: rotate(180deg); }

.absence-details {
    display: none;
    flex-wrap: wrap;
    gap: 6px;
    padding-top: 12px;
    margin-top: 10px;
    border-top: 1px solid var(--border);
}
.absence-item.expanded .absence-details { display: flex; }
.absence-date-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 5px 9px;
    font-size: 12px;
    color: var(--text-primary);
    font-weight: 600;
}
.absence-date-chip .chip-time {
    color: var(--text-muted);
    font-weight: 500;
    font-size: 11px;
}

.main-content {
    margin-top: var(--header-height);
    padding-bottom: calc(var(--bottom-nav-height) + 24px + env(safe-area-inset-bottom, 0px));
}
</style>

<script>
function toggleAbsence(el) {
    el.classList.toggle('expanded');
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
