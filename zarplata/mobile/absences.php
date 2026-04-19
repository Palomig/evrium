<?php
/**
 * Mobile Absences — рейтинг пропусков по ученикам.
 *
 * Считаем пропуск как:
 *   - attendance_log.attended = 0 (явно отмечен красным), ИЛИ
 *   - отсутствие строки attendance_log для ученика на уроке, который уже начался
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

// ── Агрегируем: для каждого урока смотрим ожидаемых и считаем пропуски ────
$byStudent = [];  // student_id => ['name', 'class', 'expected' => N, 'missed' => N, 'dates' => [...]]
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
                'missed' => 0,
                'missed_lessons' => [],
            ];
        }
        $byStudent[$sid]['expected']++;
        $key = $lesson['id'] . ':' . $sid;
        if (!isset($attendedSet[$key])) {
            $byStudent[$sid]['missed']++;
            $byStudent[$sid]['missed_lessons'][] = [
                'date' => $lesson['lesson_date'],
                'time' => substr($lesson['time_start'], 0, 5),
            ];
        }
    }
}

// Только те, у кого есть хотя бы один пропуск; сортировка: missed DESC, затем %
$list = array_values(array_filter($byStudent, fn($r) => $r['missed'] > 0));
usort($list, function ($a, $b) {
    if ($b['missed'] !== $a['missed']) return $b['missed'] - $a['missed'];
    $ra = $a['expected'] ? $a['missed'] / $a['expected'] : 0;
    $rb = $b['expected'] ? $b['missed'] / $b['expected'] : 0;
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

<!-- Period tabs -->
<div class="period-tabs">
    <a href="absences.php?period=week"  class="period-tab <?= $period === 'week'  ? 'active' : '' ?>">Неделя</a>
    <a href="absences.php?period=month" class="period-tab <?= $period === 'month' ? 'active' : '' ?>">Месяц</a>
    <a href="absences.php?period=all"   class="period-tab <?= $period === 'all'   ? 'active' : '' ?>">Всё время</a>
</div>

<div class="period-caption"><?= htmlspecialchars($periodLabel) ?> · уроков: <?= count($lessons) ?></div>

<?php if (empty($list)): ?>
<div class="empty-state">
    <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:var(--status-green);margin-bottom:12px">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
    </svg>
    <p style="color:var(--text-secondary);font-size:15px">Все ученики ходят — пропусков нет</p>
</div>
<?php else: ?>

<ul class="absence-list">
    <?php foreach ($list as $row):
        $pct = $row['expected'] ? round(100 * $row['missed'] / $row['expected']) : 0;
    ?>
    <li class="absence-item" onclick="toggleAbsence(this)">
        <div class="absence-row">
            <div class="absence-main">
                <div class="absence-name"><?= htmlspecialchars($row['name']) ?></div>
                <div class="absence-sub">
                    <?php if ($row['class']): ?><?= (int)$row['class'] ?> класс · <?php endif; ?>
                    пропустил <?= (int)$row['missed'] ?> из <?= (int)$row['expected'] ?>
                </div>
            </div>
            <div class="absence-badge">
                <span class="absence-count"><?= (int)$row['missed'] ?></span>
                <span class="absence-pct"><?= $pct ?>%</span>
            </div>
            <svg class="absence-chevron" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
        <div class="absence-details">
            <?php foreach ($row['missed_lessons'] as $ml): ?>
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
.period-tabs {
    display: flex;
    gap: 6px;
    padding: 12px 16px 6px;
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
.absence-item.expanded { border-color: rgba(244,63,94,0.35); }
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
