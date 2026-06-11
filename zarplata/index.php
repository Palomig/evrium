<?php
/**
 * Дашборд владельца: рентабельность индивидуальных и групповых уроков.
 *
 * Главный вопрос: на чём фокусироваться — индив. или группы.
 * Модель:
 *  - ставка ученика за урок: индив → price_individual (на уроке);
 *    групповой месячник → price_group / число его занятий в месяц по расписанию;
 *  - ожидаемое за месяц — из планировщика (planner_notes) при полной явке;
 *  - факт — из проведённых уроков (lessons_instance) с учётом явки,
 *    которую отмечают преподаватели; выплата — фактические начисления (payments).
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

// Автоматический редирект на мобильную версию
require_once __DIR__ . '/mobile/config/mobile_detect.php';
redirectToMobileIfNeeded('index.php');

requireDashboardAccess();
$user = getCurrentUser();

// ===== Период: месяц (?month=YYYY-MM) =====
$monthParam = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
$monthStart = $monthParam . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));
$isCurrentMonth = $monthParam === date('Y-m');
$monthNames = [1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',5=>'Май',6=>'Июнь',7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь'];
$monthLabel = $monthNames[(int)date('n', strtotime($monthStart))] . ' ' . date('Y', strtotime($monthStart));

// Сколько раз каждый день недели встречается в месяце
$dowCount = [1=>0,2=>0,3=>0,4=>0,5=>0,6=>0,7=>0];
for ($d = new DateTime($monthStart); $d <= new DateTime($monthEnd); $d->modify('+1 day')) {
    $dowCount[(int)$d->format('N')]++;
}

// ===== Справочники =====
$teachers = dbQuery("SELECT id, name, display_name, formula_id, formula_id_group, formula_id_individual FROM teachers WHERE active = 1 ORDER BY id", []);
$teachersById = [];
foreach ($teachers as $t) { $teachersById[(int)$t['id']] = $t; }

$formulas = dbQuery("SELECT * FROM payment_formulas", []);
$formulasById = [];
foreach ($formulas as $f) { $formulasById[(int)$f['id']] = $f; }

$studentsRows = dbQuery("SELECT id, name, class, price_group, price_individual, payment_type_group, payment_type_individual, is_sick FROM students WHERE active = 1", []);
$studentsByName = [];
foreach ($studentsRows as $s) {
    $studentsByName[mb_strtolower(trim($s['name']))] = $s;
}

/** Выплата преподавателю за урок по формуле */
function dashCalcPayout($teacher, $type, $n, $formulasById) {
    if (!$teacher || $n <= 0) return 0;
    if ($type === 'group') {
        $fid = ($teacher['formula_id_group'] ?? null) ?: ($teacher['formula_id'] ?? null);
    } else {
        $fid = ($teacher['formula_id_individual'] ?? null) ?: ($teacher['formula_id'] ?? null);
    }
    $f = $formulasById[(int)$fid] ?? null;
    if (!$f) return 0;

    if ($f['type'] === 'fixed') {
        return (float)($f['fixed_amount'] ?? 0);
    }
    if ($f['type'] === 'min_plus_per') {
        $min = (float)($f['min_payment'] ?? 0);
        $per = (float)($f['per_student'] ?? 0);
        $thr = (int)($f['threshold'] ?? 2);
        return $n > $thr ? $min + ($n - $thr) * $per : $min;
    }
    if ($f['type'] === 'expression') {
        $expr = str_replace('N', (string)(int)$n, $f['expression'] ?? '0');
        if (preg_match('/^[\d\s+\-*\/().,maxin]+$/i', $expr)) {
            try { return (float)eval("return {$expr};"); } catch (Throwable $e) { return 0; }
        }
    }
    return 0;
}

// ===== Расписание (planner_notes): блоки и ставки учеников =====
$notes = dbQuery(
    "SELECT day, time, teacher_id, kind, content, color, room
     FROM planner_notes
     WHERE (temp_until IS NULL OR temp_until >= CURDATE())
     ORDER BY day, time, position, id",
    []
);

// Преподаватель записи (фолбэк по цвету для легаси)
$colorMap = [];
foreach ($teachers as $t) { $colorMap[(((int)$t['id']) % 8) ?: 8] = (int)$t['id']; }
$fallbackTid = $colorMap[1] ?? ((int)($teachers[0]['id'] ?? 0));

$blocks = []; // key day_time_teacher => ['day','time','teacher_id','students'=>[names]]
foreach ($notes as $n) {
    if ($n['kind'] !== 'student' || trim($n['content']) === '') continue;
    $tid = (int)($n['teacher_id'] ?? 0);
    if (!$tid || !isset($teachersById[$tid])) {
        $tid = $colorMap[(int)$n['color']] ?? $fallbackTid;
    }
    $key = "{$n['day']}_{$n['time']}_{$tid}";
    if (!isset($blocks[$key])) {
        $blocks[$key] = ['day' => (int)$n['day'], 'time' => substr($n['time'], 0, 5), 'teacher_id' => $tid, 'students' => []];
    }
    $blocks[$key]['students'][] = trim($n['content']);
}

// Сколько занятий в неделю у каждого имени (для пересчёта месячной цены в ставку за урок)
$weeklyByName = [];
foreach ($blocks as $b) {
    $isGroup = count($b['students']) > 1;
    foreach ($b['students'] as $name) {
        $k = mb_strtolower($name);
        $weeklyByName[$k][$isGroup ? 'group' : 'individual'] = ($weeklyByName[$k][$isGroup ? 'group' : 'individual'] ?? 0) + 1;
    }
}

$unmatchedNames = [];

/** Ставка ученика за один урок данного типа */
function dashStudentRate($name, $type, $studentsByName, $weeklyByName, &$unmatchedNames) {
    $k = mb_strtolower(trim($name));
    $s = $studentsByName[$k] ?? null;
    $weekly = max(1, (int)($weeklyByName[$k][$type] ?? 1));
    $lessonsPerMonth = $weekly * 4;

    if (!$s) {
        $unmatchedNames[$name] = true;
        return $type === 'group' ? 5000 / $lessonsPerMonth : 1500;
    }
    if ($type === 'group') {
        $price = (float)($s['price_group'] ?? 5000);
        $pt = $s['payment_type_group'] ?? 'monthly';
        return $pt === 'monthly' ? $price / $lessonsPerMonth : $price;
    }
    $price = (float)($s['price_individual'] ?? 1500);
    $pt = $s['payment_type_individual'] ?? 'per_lesson';
    return $pt === 'monthly' ? $price / $lessonsPerMonth : $price;
}

// Экономика каждого блока (полная явка)
foreach ($blocks as $key => &$b) {
    $type = count($b['students']) > 1 ? 'group' : 'individual';
    $revenue = 0;
    foreach ($b['students'] as $name) {
        $revenue += dashStudentRate($name, $type, $studentsByName, $weeklyByName, $unmatchedNames);
    }
    $b['type'] = $type;
    $b['revenue_full'] = $revenue;
    $b['payout_full'] = dashCalcPayout($teachersById[$b['teacher_id']] ?? null, $type, count($b['students']), $formulasById);
}
unset($b);

// ===== ОЖИДАЕМОЕ за месяц (по расписанию, полная явка) =====
$expected = ['individual' => ['lessons'=>0,'revenue'=>0,'payout'=>0], 'group' => ['lessons'=>0,'revenue'=>0,'payout'=>0]];
foreach ($blocks as $b) {
    $times = $dowCount[$b['day']] ?? 0;
    $expected[$b['type']]['lessons'] += $times;
    $expected[$b['type']]['revenue'] += $b['revenue_full'] * $times;
    $expected[$b['type']]['payout'] += $b['payout_full'] * $times;
}

// ===== ФАКТ за месяц (проведённые уроки + явка + реальные выплаты) =====
$instances = dbQuery(
    "SELECT li.id, li.lesson_date, li.time_start, li.teacher_id, li.lesson_type,
            li.expected_students, li.actual_students, li.status,
            COALESCE(p.paid, 0) AS payout
     FROM lessons_instance li
     LEFT JOIN (
         SELECT lesson_instance_id, SUM(amount) AS paid
         FROM payments WHERE status != 'cancelled' GROUP BY lesson_instance_id
     ) p ON p.lesson_instance_id = li.id
     WHERE li.lesson_date BETWEEN ? AND ? AND li.status = 'completed'",
    [$monthStart, $monthEnd]
);

$fact = ['individual' => ['lessons'=>0,'revenue'=>0,'payout'=>0,'expected_st'=>0,'actual_st'=>0],
         'group' => ['lessons'=>0,'revenue'=>0,'payout'=>0,'expected_st'=>0,'actual_st'=>0]];
$byTeacher = []; // teacher_id => lessons/revenue/payout

foreach ($instances as $li) {
    $type = ($li['lesson_type'] ?? 'group') === 'individual' ? 'individual' : 'group';
    $tid = (int)$li['teacher_id'];
    $dow = (int)date('N', strtotime($li['lesson_date']));
    $time = substr($li['time_start'], 0, 5);

    $expSt = max(0, (int)$li['expected_students']);
    $actSt = max(0, (int)$li['actual_students']);

    // Выручка: полная ставка блока × доля явки (индив — бинарно)
    $block = $blocks["{$dow}_{$time}_{$tid}"] ?? null;
    if ($block) {
        $full = $block['revenue_full'];
        $denom = max(1, count($block['students']));
        $ratio = $expSt > 0 ? min(1, $actSt / max($expSt, 1)) : ($actSt > 0 ? 1 : 0);
        $revenue = $block['type'] === 'individual'
            ? ($actSt > 0 ? $full : 0)
            : $full * ($actSt > 0 ? min(1, $actSt / $denom) : 0);
    } else {
        // Разовый урок вне сетки — грубая оценка
        $revenue = $actSt * ($type === 'individual' ? 1500 : 625);
    }

    // Выплата: фактическое начисление, фолбэк — формула от явки
    $payout = (float)$li['payout'];
    if ($payout <= 0) {
        $payout = dashCalcPayout($teachersById[$tid] ?? null, $type, $actSt, $formulasById);
    }

    $fact[$type]['lessons']++;
    $fact[$type]['revenue'] += $revenue;
    $fact[$type]['payout'] += $payout;
    $fact[$type]['expected_st'] += $expSt;
    $fact[$type]['actual_st'] += $actSt;

    if (!isset($byTeacher[$tid])) $byTeacher[$tid] = ['lessons'=>0,'revenue'=>0,'payout'=>0];
    $byTeacher[$tid]['lessons']++;
    $byTeacher[$tid]['revenue'] += $revenue;
    $byTeacher[$tid]['payout'] += $payout;
}

// Уроки без отметки (прошли, но преподаватель не ответил боту) — за период
$unmarked = dbQuery(
    "SELECT li.lesson_date, li.time_start, li.teacher_id
     FROM lessons_instance li
     WHERE li.lesson_date BETWEEN ? AND ?
       AND li.status = 'scheduled'
       AND CONCAT(li.lesson_date, ' ', li.time_start) < NOW()
     ORDER BY li.lesson_date DESC, li.time_start DESC",
    [$monthStart, min($monthEnd, date('Y-m-d'))]
);

// Болеющие
$sickStudents = array_values(array_filter($studentsRows, fn($s) => !empty($s['is_sick'])));

// Ученики по классам
$classCounts = [];
foreach ($studentsRows as $s) {
    $cls = $s['class'] ? (string)(int)$s['class'] : 'без класса';
    $classCounts[$cls] = ($classCounts[$cls] ?? 0) + 1;
}
uksort($classCounts, function($a, $b) {
    if ($a === 'без класса') return 1;
    if ($b === 'без класса') return -1;
    return (int)$a <=> (int)$b;
});
$maxClassCount = max(1, max($classCounts ?: [1]));

// Производные
function dashMargin($x) { return $x['revenue'] - $x['payout']; }
$factIndMargin = dashMargin($fact['individual']);
$factGrpMargin = dashMargin($fact['group']);
$perLessonInd = $fact['individual']['lessons'] ? $factIndMargin / $fact['individual']['lessons'] : 0;
$perLessonGrp = $fact['group']['lessons'] ? $factGrpMargin / $fact['group']['lessons'] : 0;
$attendInd = $fact['individual']['expected_st'] ? round(100 * $fact['individual']['actual_st'] / $fact['individual']['expected_st']) : null;
$attendGrp = $fact['group']['expected_st'] ? round(100 * $fact['group']['actual_st'] / $fact['group']['expected_st']) : null;
$expIndMargin = dashMargin($expected['individual']);
$expGrpMargin = dashMargin($expected['group']);
$totalFactMargin = $factIndMargin + $factGrpMargin;
$totalExpMargin = $expIndMargin + $expGrpMargin;

define('PAGE_TITLE', 'Дашборд');
define('PAGE_SUBTITLE', 'Рентабельность уроков · ' . $monthLabel);
define('ACTIVE_PAGE', 'dashboard');

require_once __DIR__ . '/templates/header.php';

function rub($n) { return number_format(round($n), 0, '', ' ') . ' ₽'; }
?>

<style>
.dash-month-nav {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}
.dash-month-nav a {
    padding: 6px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-secondary);
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
}
.dash-month-nav a:hover { border-color: var(--accent); color: var(--accent); }
.dash-month-label { font-weight: 800; font-size: 16px; }

.type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}
.type-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px;
}
.type-card.ind { border-top: 3px solid var(--lesson-individual, #06b6d4); }
.type-card.grp { border-top: 3px solid var(--lesson-group, #a855f7); }
.type-card h3 { margin: 0 0 4px; font-size: 15px; }
.type-card .type-sub { color: var(--text-muted); font-size: 12px; margin-bottom: 14px; }
.type-margin {
    font-family: 'JetBrains Mono', monospace;
    font-size: 30px;
    font-weight: 700;
    margin-bottom: 2px;
}
.type-margin.pos { color: var(--status-green, #22c55e); }
.type-margin.neg { color: var(--status-rose, #f43f5e); }
.type-perlesson { color: var(--text-secondary); font-size: 13px; margin-bottom: 14px; }
.type-rows { display: grid; gap: 6px; font-size: 13px; }
.type-rows div { display: flex; justify-content: space-between; }
.type-rows .lbl { color: var(--text-secondary); }
.type-rows .val { font-family: 'JetBrains Mono', monospace; font-weight: 600; }

.verdict {
    background: var(--accent-dim, rgba(20,184,166,0.12));
    border: 1px solid var(--accent, #14b8a6);
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 24px;
    font-size: 14px;
    font-weight: 600;
}

.dash-section { margin-bottom: 24px; }
.dash-section h2 { font-size: 16px; font-weight: 800; margin-bottom: 12px; }

.dash-two-cols {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 16px;
}

.dash-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px 20px;
}

.dash-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.dash-table th { text-align: left; color: var(--text-muted); font-size: 11px; text-transform: uppercase; padding: 6px 10px; }
.dash-table td { padding: 8px 10px; border-top: 1px solid var(--border); font-variant-numeric: tabular-nums; }
.dash-table td.num { font-family: 'JetBrains Mono', monospace; font-weight: 600; }

.class-bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 7px; font-size: 13px; }
.class-bar-label { width: 80px; color: var(--text-secondary); flex-shrink: 0; }
.class-bar-track { flex: 1; background: var(--bg-elevated); border-radius: 5px; height: 18px; overflow: hidden; }
.class-bar-fill { height: 100%; background: linear-gradient(90deg, rgba(20,184,166,0.8), rgba(20,184,166,0.45)); border-radius: 5px; }
.class-bar-count { width: 30px; text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 600; }

.exp-fact-row { display: flex; justify-content: space-between; font-size: 13px; padding: 7px 0; border-bottom: 1px solid var(--border); }
.exp-fact-row:last-child { border-bottom: none; }
.exp-fact-row .val { font-family: 'JetBrains Mono', monospace; font-weight: 600; }

.action-item { display: flex; align-items: center; gap: 8px; padding: 7px 0; font-size: 13px; border-bottom: 1px solid var(--border); }
.action-item:last-child { border-bottom: none; }
.action-ok { color: var(--status-green, #22c55e); }

.model-note { color: var(--text-muted); font-size: 12px; margin-top: 18px; line-height: 1.5; }
</style>

<!-- Переключатель месяца -->
<div class="dash-month-nav">
    <a href="?month=<?= $prevMonth ?>">←</a>
    <span class="dash-month-label"><?= e($monthLabel) ?></span>
    <?php if (!$isCurrentMonth): ?><a href="?month=<?= $nextMonth ?>">→</a><a href="index.php">Сегодня</a><?php endif; ?>
</div>

<!-- ГЛАВНОЕ: индивидуальные vs групповые (факт с учётом явки) -->
<div class="type-grid">
    <div class="type-card ind">
        <h3>🧑‍🎓 Индивидуальные</h3>
        <div class="type-sub">факт за месяц, с учётом явки</div>
        <div class="type-margin <?= $factIndMargin >= 0 ? 'pos' : 'neg' ?>"><?= rub($factIndMargin) ?></div>
        <div class="type-perlesson">маржа · <?= rub($perLessonInd) ?>/урок</div>
        <div class="type-rows">
            <div><span class="lbl">Проведено уроков</span><span class="val"><?= $fact['individual']['lessons'] ?></span></div>
            <div><span class="lbl">Явка</span><span class="val"><?= $attendInd !== null ? $attendInd . '%' : '—' ?></span></div>
            <div><span class="lbl">Выручка</span><span class="val"><?= rub($fact['individual']['revenue']) ?></span></div>
            <div><span class="lbl">Выплачено преподавателям</span><span class="val"><?= rub($fact['individual']['payout']) ?></span></div>
        </div>
    </div>

    <div class="type-card grp">
        <h3>👥 Групповые</h3>
        <div class="type-sub">факт за месяц, с учётом явки</div>
        <div class="type-margin <?= $factGrpMargin >= 0 ? 'pos' : 'neg' ?>"><?= rub($factGrpMargin) ?></div>
        <div class="type-perlesson">маржа · <?= rub($perLessonGrp) ?>/урок</div>
        <div class="type-rows">
            <div><span class="lbl">Проведено уроков</span><span class="val"><?= $fact['group']['lessons'] ?></span></div>
            <div><span class="lbl">Явка</span><span class="val"><?= $attendGrp !== null ? $attendGrp . '%' : '—' ?></span></div>
            <div><span class="lbl">Выручка</span><span class="val"><?= rub($fact['group']['revenue']) ?></span></div>
            <div><span class="lbl">Выплачено преподавателям</span><span class="val"><?= rub($fact['group']['payout']) ?></span></div>
        </div>
    </div>
</div>

<?php if ($fact['individual']['lessons'] || $fact['group']['lessons']): ?>
<div class="verdict">
    <?php if ($perLessonGrp > $perLessonInd): ?>
        💡 Групповые рентабельнее: <?= rub($perLessonGrp) ?> маржи с урока против <?= rub($perLessonInd) ?> у индивидуальных<?= $attendGrp !== null && $attendGrp < 80 ? ' — но явка в группах ' . $attendGrp . '%, есть резерв' : '' ?>.
    <?php elseif ($perLessonInd > $perLessonGrp): ?>
        💡 Индивидуальные рентабельнее: <?= rub($perLessonInd) ?> маржи с урока против <?= rub($perLessonGrp) ?> у групповых.
    <?php else: ?>
        💡 Маржа с урока у типов одинаковая.
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="dash-two-cols dash-section">
    <!-- Ожидаемое vs фактическое -->
    <div class="dash-card">
        <h2 style="font-size: 15px; margin-bottom: 12px;">Ожидаемое vs фактическое (маржа)</h2>
        <div class="exp-fact-row"><span>Индивидуальные: ожидание месяца</span><span class="val"><?= rub($expIndMargin) ?></span></div>
        <div class="exp-fact-row"><span>Индивидуальные: факт<?= $isCurrentMonth ? ' на сегодня' : '' ?></span><span class="val"><?= rub($factIndMargin) ?></span></div>
        <div class="exp-fact-row"><span>Групповые: ожидание месяца</span><span class="val"><?= rub($expGrpMargin) ?></span></div>
        <div class="exp-fact-row"><span>Групповые: факт<?= $isCurrentMonth ? ' на сегодня' : '' ?></span><span class="val"><?= rub($factGrpMargin) ?></span></div>
        <div class="exp-fact-row" style="font-weight: 700;"><span>Итого: ожидание / факт</span><span class="val"><?= rub($totalExpMargin) ?> / <?= rub($totalFactMargin) ?></span></div>
    </div>

    <!-- По преподавателям -->
    <div class="dash-card">
        <h2 style="font-size: 15px; margin-bottom: 12px;">По преподавателям (факт)</h2>
        <table class="dash-table">
            <thead><tr><th>Преподаватель</th><th>Уроков</th><th>Выручка</th><th>Выплата</th><th>Маржа</th></tr></thead>
            <tbody>
                <?php foreach ($teachers as $t):
                    $row = $byTeacher[(int)$t['id']] ?? ['lessons'=>0,'revenue'=>0,'payout'=>0];
                ?>
                <tr>
                    <td><?= e($t['display_name'] ?: $t['name']) ?></td>
                    <td class="num"><?= $row['lessons'] ?></td>
                    <td class="num"><?= rub($row['revenue']) ?></td>
                    <td class="num"><?= rub($row['payout']) ?></td>
                    <td class="num" style="color: <?= ($row['revenue'] - $row['payout']) >= 0 ? 'var(--status-green, #22c55e)' : 'var(--status-rose, #f43f5e)' ?>;">
                        <?= rub($row['revenue'] - $row['payout']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="dash-two-cols dash-section">
    <!-- Ученики по классам -->
    <div class="dash-card">
        <h2 style="font-size: 15px; margin-bottom: 12px;">Ученики по классам (<?= count($studentsRows) ?> активных)</h2>
        <?php foreach ($classCounts as $cls => $cnt): ?>
            <div class="class-bar-row">
                <span class="class-bar-label"><?= $cls === 'без класса' ? 'без класса' : $cls . ' класс' ?></span>
                <div class="class-bar-track"><div class="class-bar-fill" style="width: <?= round(100 * $cnt / $maxClassCount) ?>%;"></div></div>
                <span class="class-bar-count"><?= $cnt ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Требует внимания -->
    <div class="dash-card">
        <h2 style="font-size: 15px; margin-bottom: 12px;">Требует внимания</h2>
        <?php if (empty($unmarked) && empty($sickStudents) && empty($unmatchedNames)): ?>
            <div class="action-item action-ok">✓ Всё в порядке: посещаемость отмечена, больных нет</div>
        <?php else: ?>
            <?php if (!empty($unmarked)): ?>
                <div class="action-item">⚠ Уроков без отметки посещаемости: <strong><?= count($unmarked) ?></strong>
                    <span style="color: var(--text-muted);">(последний: <?= date('d.m', strtotime($unmarked[0]['lesson_date'])) ?> <?= substr($unmarked[0]['time_start'], 0, 5) ?>)</span>
                </div>
            <?php endif; ?>
            <?php if (!empty($sickStudents)): ?>
                <div class="action-item">🤒 Болеют: <?= e(implode(', ', array_map(fn($s) => $s['name'], $sickStudents))) ?></div>
            <?php endif; ?>
            <?php if (!empty($unmatchedNames)): ?>
                <div class="action-item">❓ В расписании есть имена без карточки ученика (цены взяты по умолчанию):
                    <?= e(implode(', ', array_slice(array_keys($unmatchedNames), 0, 8))) ?><?= count($unmatchedNames) > 8 ? '…' : '' ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="model-note">
    Модель расчёта: ставка ученика за урок — индивидуальные по price за урок; групповые месячники — цена месяца ÷ занятий в месяц по расписанию.
    Фактическая выручка урока = ставки блока × доля явки (по отметкам преподавателей в боте). Выплата — фактические начисления; если начисления нет — расчёт по формуле от явки.
    Ожидаемое — по сетке расписания при полной явке.
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
