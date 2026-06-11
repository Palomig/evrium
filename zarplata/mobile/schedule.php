<?php
/**
 * Mobile Schedule Page — расписание из планировщика (planner_notes)
 * Дни-чипы сверху, блоки уроков карточками. Только просмотр,
 * редактирование — в десктопном планировщике.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Преподаватели для легенды (цвет = (id % 8) ?: 8, как в планировщике)
$teachers = dbQuery("
    SELECT id, COALESCE(display_name, name) AS name
    FROM teachers
    WHERE active = 1
    ORDER BY name
", []);

// Все записи расписания (просроченные временные не показываем)
$notes = [];
try {
    $notes = dbQuery(
        "SELECT day, time, room, kind, content, color, temp_until
         FROM planner_notes
         WHERE (temp_until IS NULL OR temp_until >= CURDATE())
         ORDER BY day, time, room, kind, position, id",
        []
    );
} catch (PDOException $e) {
    $notes = [];
}

// Группируем: день → "time_room" → блок
$days = [];
foreach ($notes as $n) {
    $day = (int)$n['day'];
    $key = $n['time'] . '_' . $n['room'];
    if (!isset($days[$day][$key])) {
        $days[$day][$key] = [
            'time' => substr($n['time'], 0, 5),
            'room' => (int)$n['room'],
            'title' => null,
            'title_color' => 0,
            'students' => []
        ];
    }
    if ($n['kind'] === 'title') {
        $days[$day][$key]['title'] = trim($n['content']);
        $days[$day][$key]['title_color'] = (int)$n['color'];
    } elseif (trim($n['content']) !== '') {
        $days[$day][$key]['students'][] = [
            'name' => trim($n['content']),
            'color' => (int)$n['color'],
            'temp' => !empty($n['temp_until'])
        ];
    }
}

// Блоки без учеников и без заголовка не показываем; сортируем по времени
foreach ($days as $day => $blocks) {
    $blocks = array_filter($blocks, fn($b) => !empty($b['students']) || ($b['title'] !== null && $b['title'] !== ''));
    usort($blocks, fn($a, $b) => [$a['time'], $a['room']] <=> [$b['time'], $b['room']]);
    $days[$day] = $blocks;
}

$dayNames = [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];
$dayFull = [1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'];
$currentDay = (int)date('N');

define('PAGE_TITLE', 'Расписание');
define('ACTIVE_PAGE', 'schedule');

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* Чипы дней */
.day-chips {
    display: flex;
    gap: 6px;
    padding: 12px 16px;
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

/* Легенда */
.sched-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 0 16px 8px;
    font-size: 12px;
    color: var(--text-secondary);
}

.sched-legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.sched-color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.2);
}

.scolor-1 { background: rgba(20, 184, 166, 0.6); }
.scolor-2 { background: rgba(168, 85, 247, 0.6); }
.scolor-3 { background: rgba(59, 130, 246, 0.6); }
.scolor-4 { background: rgba(249, 115, 22, 0.6); }
.scolor-5 { background: rgba(236, 72, 153, 0.6); }
.scolor-6 { background: rgba(234, 179, 8, 0.6); }
.scolor-7 { background: rgba(34, 197, 94, 0.6); }
.scolor-8 { background: rgba(239, 68, 68, 0.6); }
.scolor-temp { background: rgba(245, 158, 11, 0.6); border-style: dashed; }

/* День */
.day-pane { display: none; padding: 0 16px 16px; }
.day-pane.active { display: block; }

.day-empty {
    text-align: center;
    padding: 48px 16px;
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
}

.lesson-room {
    font-size: 11px;
    color: var(--text-muted);
    background: rgba(255, 255, 255, 0.07);
    padding: 2px 8px;
    border-radius: 999px;
    white-space: nowrap;
}

.lesson-students {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 10px 14px;
}

.student-chip {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 13px;
    color: #ecfdf5;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid transparent;
    border-left: 3px solid rgba(255, 255, 255, 0.2);
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

.edit-note {
    padding: 4px 16px 12px;
    font-size: 11px;
    color: var(--text-muted);
    text-align: center;
}
</style>

<!-- Чипы дней -->
<div class="day-chips">
    <?php foreach ($dayNames as $d => $short): ?>
        <button class="day-chip <?= $d === $currentDay ? 'active' : '' ?>" data-day="<?= $d ?>" onclick="showDay(<?= $d ?>)">
            <?= $short ?><?= $d === $currentDay ? ' · сегодня' : '' ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Легенда преподавателей -->
<div class="sched-legend">
    <?php foreach ($teachers as $teacher):
        $colorIndex = ($teacher['id'] % 8) ?: 8;
    ?>
        <span class="sched-legend-item"><span class="sched-color scolor-<?= $colorIndex ?>"></span><?= e($teacher['name']) ?></span>
    <?php endforeach; ?>
    <span class="sched-legend-item"><span class="sched-color scolor-temp"></span>временно</span>
</div>

<!-- Дни -->
<?php foreach ($dayNames as $d => $short): ?>
    <div class="day-pane <?= $d === $currentDay ? 'active' : '' ?>" data-day="<?= $d ?>">
        <?php if (empty($days[$d])): ?>
            <div class="day-empty"><?= $dayFull[$d] ?>: занятий нет</div>
        <?php else: ?>
            <?php foreach ($days[$d] as $block): ?>
                <div class="lesson-card bc<?= $block['title_color'] ?>">
                    <div class="lesson-card-header">
                        <span class="lesson-time"><?= $block['time'] ?></span>
                        <span class="lesson-title"><?= e($block['title'] ?: 'Урок') ?></span>
                        <span class="lesson-room">каб. <?= $block['room'] ?></span>
                    </div>
                    <?php if (!empty($block['students'])): ?>
                        <div class="lesson-students">
                            <?php foreach ($block['students'] as $st): ?>
                                <span class="student-chip c<?= $st['color'] ?><?= $st['temp'] ? ' temp' : '' ?>">
                                    <?= e($st['name']) ?><?= $st['temp'] ? ' ⏳' : '' ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<div class="edit-note">Редактирование расписания — в полной версии на компьютере</div>

<script>
function showDay(day) {
    document.querySelectorAll('.day-chip').forEach(chip => {
        chip.classList.toggle('active', parseInt(chip.dataset.day) === day);
    });
    document.querySelectorAll('.day-pane').forEach(pane => {
        pane.classList.toggle('active', parseInt(pane.dataset.day) === day);
    });
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
