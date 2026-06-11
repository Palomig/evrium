<?php
/**
 * Веб-диагностика cron-уведомлений об уроках (dry-run, без отправки).
 * Источник уроков: расписание-планировщик (planner_notes).
 * Открыть: https://эвриум.рф/zarplata/bot/test_cron_web.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/student_helpers.php';

// Только для авторизованных (диагностика раскрывает имена учеников)
if (!isLoggedIn()) {
    header('Location: /zarplata/login.php');
    exit;
}

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Диагностика Cron Bot (источник: планировщик)</h1>";
echo "<pre style='background:#1a1a1a; color:#e0e0e0; padding:20px; font-size:14px;'>";

echo "=== 1. БАЗОВАЯ ИНФОРМАЦИЯ ===\n";
echo "Время сервера: " . date('Y-m-d H:i:s') . "\n";
echo "Timezone: " . date_default_timezone_get() . "\n";
echo "PHP Version: " . phpversion() . "\n\n";

echo "=== 2. БАЗА ДАННЫХ И ТОКЕН ===\n";
$testDb = dbQueryOne("SELECT 1 AS test", []);
echo ($testDb ? "✓" : "✗") . " Подключение к БД\n";
$token = getBotToken();
echo (!empty($token) ? "✓ Токен бота найден (длина: " . strlen($token) . ")" : "✗ Токен бота ПУСТОЙ!") . "\n\n";

$dayOfWeek = (int)date('N');
$today = date('Y-m-d');
$currentTime = date('H:i');

echo "=== 3. ТЕКУЩИЙ МОМЕНТ ===\n";
echo "День недели: {$dayOfWeek}\n";
echo "Дата: {$today}\n";
echo "Время: {$currentTime}\n\n";

echo "=== 4. ПРЕПОДАВАТЕЛИ И ЦВЕТА ===\n";
$teacherRows = dbQuery("SELECT id, name, telegram_id FROM teachers WHERE active = 1", []);
$colorToTeacher = plannerColorTeacherMap();
foreach ($teacherRows as $t) {
    $colorIndex = ($t['id'] % 8) ?: 8;
    $tg = $t['telegram_id'] ? "tg ✓" : "tg ✗ (не получит уведомления!)";
    echo "  #{$t['id']} {$t['name']} → цвет c{$colorIndex}, {$tg}\n";
}
echo "\n";

echo "=== 5. УРОКИ ИЗ ПЛАНИРОВЩИКА НА СЕГОДНЯ ===\n";
$dayNotes = dbQuery(
    "SELECT time, room, kind, content, color FROM planner_notes
     WHERE day = ?
       AND (temp_until IS NULL OR temp_until >= CURDATE())
     ORDER BY time, room, kind, position, id",
    [$dayOfWeek]
);
echo "Записей в planner_notes на день {$dayOfWeek}: " . count($dayNotes) . "\n\n";

$blockTitles = [];
foreach ($dayNotes as $n) {
    if ($n['kind'] === 'title') {
        $blockTitles[$n['time'] . '_' . $n['room']] = $n;
    }
}

$uniqueLessons = [];
foreach ($dayNotes as $n) {
    if ($n['kind'] !== 'student' || trim($n['content']) === '') continue;

    $time = substr($n['time'], 0, 5);
    $title = $blockTitles[$n['time'] . '_' . $n['room']] ?? null;

    $color = (int)$n['color'];
    if (!$color && $title) $color = (int)$title['color'];
    if (!$color) $color = 1;

    $teacherId = $colorToTeacher[$color] ?? null;
    if (!$teacherId) {
        echo "  ⚠ Ячейка «" . htmlspecialchars($n['content']) . "» ({$time}, каб. {$n['room']}): цвет c{$color} не соответствует ни одному преподавателю — будет пропущена\n";
        continue;
    }

    $subject = 'Мат.';
    if ($title && preg_match('/^\s*(?:\d+(?:-\d+)?)?\s*(.+)$/u', trim($title['content']), $m)) {
        $subject = trim($m[1]) !== '' ? trim($m[1]) : 'Мат.';
    }

    $key = "{$teacherId}_{$time}";
    if (!isset($uniqueLessons[$key])) {
        $uniqueLessons[$key] = [
            'teacher_id' => $teacherId,
            'time' => $time,
            'subject' => $subject,
            'room' => (int)$n['room']
        ];
    }
}

uasort($uniqueLessons, fn($a, $b) => strcmp($a['time'], $b['time']));

if (empty($uniqueLessons)) {
    echo "  Уроков на сегодня в планировщике нет.\n";
} else {
    foreach ($uniqueLessons as $key => $lesson) {
        $started = $lesson['time'] <= $currentTime ? "✓ начался (уведомление будет отправлено кроном)" : "⏳ ещё не начался";

        // Уже отправляли сегодня?
        $sent = dbQueryOne(
            "SELECT id, created_at FROM audit_log
             WHERE action_type = 'attendance_query_sent'
               AND entity_type = 'lesson_schedule'
               AND new_value LIKE ?
               AND DATE(created_at) = ?
             LIMIT 1",
            ["%teacher_id\":{$lesson['teacher_id']}%time\":\"{$lesson['time']}%", $today]
        );

        $studentsData = getStudentsForLesson($lesson['teacher_id'], $dayOfWeek, $lesson['time']);
        $names = implode(', ', array_column($studentsData['students'], 'name'));

        echo "\n  ── Урок {$lesson['time']} (преп. #{$lesson['teacher_id']}, каб. {$lesson['room']}) ──\n";
        echo "  Предмет: {$studentsData['subject']}, классы: {$studentsData['classes']}\n";
        echo "  Учеников: {$studentsData['count']} — " . htmlspecialchars($names) . "\n";
        echo "  Статус: {$started}\n";
        echo "  Отправка сегодня: " . ($sent ? "✓ уже отправлено в {$sent['created_at']} (audit #{$sent['id']})" : "ещё не отправлялось") . "\n";
    }
}

echo "\n=== ГОТОВО (dry-run, ничего не отправлено) ===\n";
echo "</pre>";
