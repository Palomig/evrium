<?php
/**
 * Mobile Earnings — заработок учителей за неделю / месяц.
 *
 * Повторяет логику /week и /month из Telegram-бота:
 *   - неделя: ПН-ВС текущей недели
 *   - месяц:  1-е число → сегодня
 *
 * Источник — SUM(payments.amount) по DATE(created_at). Считаем всё, кроме
 * status = 'cancelled'. Показываем по каждому учителю отдельный блок
 * с разбивкой по дням и итогом, а в самом конце — сводный итог.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();

$allowedPeriods = ['week', 'month'];
$period = $_GET['period'] ?? 'week';
if (!in_array($period, $allowedPeriods, true)) {
    $period = 'week';
}

if ($period === 'month') {
    $fromDate = date('Y-m-01');
    $toDate   = date('Y-m-d');
    $periodLabel = 'Месяц: ' . date('d.m', strtotime($fromDate)) . ' – ' . date('d.m.Y', strtotime($toDate));
} else {
    $fromDate = date('Y-m-d', strtotime('monday this week'));
    $toDate   = date('Y-m-d', strtotime('sunday this week'));
    $periodLabel = 'Неделя: ' . date('d.m', strtotime($fromDate)) . ' – ' . date('d.m.Y', strtotime($toDate));
}

// Все учителя — чтобы показать даже тех, у кого 0 в этом периоде
$teachers = dbQuery(
    "SELECT id, COALESCE(display_name, name) AS name FROM teachers ORDER BY id ASC",
    []
);

// Дневные суммы по всем учителям за период одним запросом
$rows = dbQuery(
    "SELECT teacher_id, DATE(created_at) AS d,
            SUM(amount) AS total, COUNT(*) AS lessons
     FROM payments
     WHERE DATE(created_at) BETWEEN ? AND ?
       AND status != 'cancelled'
     GROUP BY teacher_id, DATE(created_at)
     ORDER BY teacher_id, d ASC",
    [$fromDate, $toDate]
);

// teacher_id => ['days' => [['d'=>..., 'total'=>..., 'lessons'=>...], ...],
//                'total' => N, 'lessons' => N]
$byTeacher = [];
foreach ($rows as $r) {
    $tid = (int)$r['teacher_id'];
    if (!isset($byTeacher[$tid])) {
        $byTeacher[$tid] = ['days' => [], 'total' => 0, 'lessons' => 0];
    }
    $byTeacher[$tid]['days'][] = [
        'date'    => $r['d'],
        'total'   => (float)$r['total'],
        'lessons' => (int)$r['lessons'],
    ];
    $byTeacher[$tid]['total']   += (float)$r['total'];
    $byTeacher[$tid]['lessons'] += (int)$r['lessons'];
}

$grandTotal   = 0.0;
$grandLessons = 0;
foreach ($byTeacher as $t) {
    $grandTotal   += $t['total'];
    $grandLessons += $t['lessons'];
}

$dayNamesRu = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

function fmtMoney(float $v): string {
    return number_format($v, 0, ',', ' ') . ' ₽';
}

define('PAGE_TITLE', 'Зарплата');
define('ACTIVE_PAGE', 'earnings');
define('SHOW_BOTTOM_NAV', true);

require_once __DIR__ . '/templates/header.php';
?>

<main class="main-content">

<!-- Period tabs -->
<div class="period-tabs">
    <a href="earnings.php?period=week"  class="period-tab <?= $period === 'week'  ? 'active' : '' ?>">Неделя</a>
    <a href="earnings.php?period=month" class="period-tab <?= $period === 'month' ? 'active' : '' ?>">Месяц</a>
</div>

<div class="period-caption"><?= htmlspecialchars($periodLabel) ?></div>

<!-- Grand total -->
<div class="earnings-grand">
    <div class="earnings-grand-label">Итого по всем</div>
    <div class="earnings-grand-value"><?= htmlspecialchars(fmtMoney($grandTotal)) ?></div>
    <div class="earnings-grand-sub">Уроков: <?= $grandLessons ?></div>
</div>

<!-- Per-teacher -->
<?php foreach ($teachers as $t):
    $tid = (int)$t['id'];
    $block = $byTeacher[$tid] ?? ['days' => [], 'total' => 0, 'lessons' => 0];
    $hasData = !empty($block['days']);
?>
<div class="earnings-card <?= $hasData ? '' : 'earnings-card-empty' ?>" <?= $hasData ? 'onclick="toggleEarn(this)"' : '' ?>>
    <div class="earnings-head">
        <div class="earnings-head-main">
            <div class="earnings-teacher"><?= htmlspecialchars($t['name']) ?></div>
            <div class="earnings-sub">
                <?php if ($hasData): ?>
                Уроков: <?= (int)$block['lessons'] ?>
                <?php else: ?>
                Нет выплат за период
                <?php endif; ?>
            </div>
        </div>
        <div class="earnings-amount"><?= htmlspecialchars(fmtMoney($block['total'])) ?></div>
        <?php if ($hasData): ?>
        <svg class="earnings-chevron" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
        <?php endif; ?>
    </div>
    <?php if ($hasData): ?>
    <div class="earnings-days">
        <?php foreach ($block['days'] as $d):
            $ts = strtotime($d['date']);
            $dow = (int)date('N', $ts);
        ?>
        <div class="earnings-day">
            <div class="earnings-day-label">
                <span class="earnings-dow"><?= $dayNamesRu[$dow] ?></span>
                <span class="earnings-date"><?= date('d.m', $ts) ?></span>
            </div>
            <div class="earnings-day-meta">
                <?= (int)$d['lessons'] ?> ур.
            </div>
            <div class="earnings-day-amount"><?= htmlspecialchars(fmtMoney((float)$d['total'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

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
    padding: 2px 16px 10px;
    font-size: 12px;
    color: var(--text-muted);
}

.earnings-grand {
    margin: 4px 16px 12px;
    background: linear-gradient(135deg, rgba(20,184,166,0.14), rgba(20,184,166,0.04));
    border: 1px solid rgba(20,184,166,0.35);
    border-radius: 14px;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.earnings-grand-label { font-size: 12px; color: var(--text-secondary); font-weight: 600; }
.earnings-grand-value { font-size: 26px; font-weight: 800; color: var(--accent); line-height: 1.1; }
.earnings-grand-sub   { font-size: 12px; color: var(--text-muted); }

.earnings-card {
    margin: 8px 16px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    cursor: pointer;
    user-select: none;
    -webkit-user-select: none;
    transition: border-color 0.2s;
}
.earnings-card:not(.earnings-card-empty):active { background: var(--bg-hover); }
.earnings-card-empty { opacity: 0.55; cursor: default; }

.earnings-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
}
.earnings-head-main { flex: 1; min-width: 0; }
.earnings-teacher {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.earnings-sub {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 2px;
}
.earnings-amount {
    font-size: 17px;
    font-weight: 700;
    color: var(--text-primary);
    flex-shrink: 0;
}
.earnings-chevron {
    color: var(--text-muted);
    transition: transform 0.2s ease;
    flex-shrink: 0;
}
.earnings-card.expanded .earnings-chevron { transform: rotate(180deg); }

.earnings-days {
    display: none;
    flex-direction: column;
    border-top: 1px solid var(--border);
}
.earnings-card.expanded .earnings-days { display: flex; }
.earnings-day {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-top: 1px solid var(--border);
}
.earnings-day:first-child { border-top: none; }
.earnings-day-label {
    flex: 1;
    display: flex;
    align-items: baseline;
    gap: 8px;
    min-width: 0;
}
.earnings-dow { font-weight: 700; font-size: 13px; color: var(--accent); }
.earnings-date { font-size: 13px; color: var(--text-primary); }
.earnings-day-meta {
    font-size: 12px;
    color: var(--text-muted);
    flex-shrink: 0;
}
.earnings-day-amount {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
    min-width: 80px;
    text-align: right;
}

.main-content {
    margin-top: var(--header-height);
    padding-bottom: calc(var(--bottom-nav-height) + 24px + env(safe-area-inset-bottom, 0px));
}
</style>

<script>
function toggleEarn(el) {
    el.classList.toggle('expanded');
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
