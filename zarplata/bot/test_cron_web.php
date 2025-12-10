<?php
/**
 * Веб-версия тестирования cron для диагностики
 * Открыть: https://эвриум.рф/zarplata/bot/test_cron_web.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Диагностика Cron Bot</h1>";
echo "<pre style='background:#1a1a1a; color:#e0e0e0; padding:20px; font-size:14px;'>";

echo "=== 1. БАЗОВАЯ ИНФОРМАЦИЯ ===\n";
echo "Время сервера: " . date('Y-m-d H:i:s') . "\n";
echo "Timezone: " . date_default_timezone_get() . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "__DIR__: " . __DIR__ . "\n\n";

echo "=== 2. ПРОВЕРКА ФАЙЛОВ ===\n";
$configPath = __DIR__ . '/config.php';
$helpersPath = __DIR__ . '/../config/student_helpers.php';
$dbPath = __DIR__ . '/../config/db.php';

echo "config.php: " . (file_exists($configPath) ? "✓ существует" : "✗ НЕ НАЙДЕН!") . "\n";
echo "student_helpers.php: " . (file_exists($helpersPath) ? "✓ существует" : "✗ НЕ НАЙДЕН!") . "\n";
echo "db.php: " . (file_exists($dbPath) ? "✓ существует" : "✗ НЕ НАЙДЕН!") . "\n\n";

echo "=== 3. ПОДКЛЮЧЕНИЕ КОНФИГОВ ===\n";
try {
    require_once $configPath;
    echo "✓ config.php загружен\n";
} catch (Exception $e) {
    echo "✗ ОШИБКА config.php: " . $e->getMessage() . "\n";
    exit;
}

try {
    require_once $helpersPath;
    echo "✓ student_helpers.php загружен\n";
} catch (Exception $e) {
    echo "✗ ОШИБКА student_helpers.php: " . $e->getMessage() . "\n";
    exit;
}

echo "\n=== 4. ПРОВЕРКА БАЗЫ ДАННЫХ ===\n";
try {
    $testDb = dbQueryOne("SELECT 1 as test", []);
    echo "✓ Подключение к БД работает\n";
} catch (Exception $e) {
    echo "✗ ОШИБКА БД: " . $e->getMessage() . "\n";
    exit;
}

echo "\n=== 5. ПРОВЕРКА ТОКЕНА БОТА ===\n";
$token = getBotToken();
if (empty($token)) {
    echo "✗ Токен бота ПУСТОЙ!\n";
} else {
    echo "✓ Токен найден (длина: " . strlen($token) . ")\n";
}

echo "\n=== 6. РАСЧЁТ ВРЕМЕННОГО ОКНА ===\n";
$dayOfWeek = (int)date('N');
$dayOfWeekStr = (string)$dayOfWeek;
$today = date('Y-m-d');
$currentTime = date('H:i');
$timeFrom = date('H:i', strtotime('-18 minutes'));
$timeTo = date('H:i', strtotime('-12 minutes'));

echo "День недели: $dayOfWeek ($dayOfWeekStr)\n";
echo "Сегодня: $today\n";
echo "Текущее время: $currentTime\n";
echo "Окно поиска: $timeFrom - $timeTo\n\n";

echo "=== 7. ПОЛУЧЕНИЕ СТУДЕНТОВ ===\n";
$allStudents = dbQuery(
    "SELECT id, name, class, schedule, teacher_id FROM students WHERE active = 1 AND schedule IS NOT NULL",
    []
);
echo "Найдено студентов с расписанием: " . count($allStudents) . "\n\n";

echo "=== 8. ПОИСК УРОКОВ НА СЕГОДНЯ ===\n";
$uniqueLessons = [];
$studentsWithLessonsToday = 0;

foreach ($allStudents as $student) {
    $schedule = json_decode($student['schedule'], true);
    if (!is_array($schedule)) {
        continue;
    }

    // Проверяем ОБА варианта ключа
    $daySchedule = null;
    if (isset($schedule[$dayOfWeek]) && is_array($schedule[$dayOfWeek])) {
        $daySchedule = $schedule[$dayOfWeek];
    } elseif (isset($schedule[$dayOfWeekStr]) && is_array($schedule[$dayOfWeekStr])) {
        $daySchedule = $schedule[$dayOfWeekStr];
    }

    if (!$daySchedule) {
        continue;
    }

    $studentsWithLessonsToday++;

    foreach ($daySchedule as $slot) {
        if (!isset($slot['time'])) continue;

        $time = substr($slot['time'], 0, 5);

        // Правильно обрабатываем teacher_id
        $slotTeacherId = null;
        if (isset($slot['teacher_id']) && $slot['teacher_id'] !== '' && $slot['teacher_id'] !== null) {
            $slotTeacherId = (int)$slot['teacher_id'];
        }
        $teacherId = $slotTeacherId ?: (int)$student['teacher_id'];

        if (!$teacherId) continue;

        $key = "{$teacherId}_{$time}";
        if (!isset($uniqueLessons[$key])) {
            $uniqueLessons[$key] = [
                'teacher_id' => $teacherId,
                'time' => $time,
                'subject' => $slot['subject'] ?? 'Мат.',
                'room' => $slot['room'] ?? 1,
                'students' => []
            ];
        }
        $uniqueLessons[$key]['students'][] = $student['name'];
    }
}

echo "Студентов с уроками сегодня: $studentsWithLessonsToday\n";
echo "Уникальных уроков сегодня: " . count($uniqueLessons) . "\n\n";

echo "=== 9. ВСЕ УРОКИ СЕГОДНЯ ===\n";
// Сортируем по времени
usort($uniqueLessons, fn($a, $b) => strcmp($a['time'], $b['time']));

$teachers = [];
$teacherRows = dbQuery("SELECT id, name, telegram_id FROM teachers WHERE active = 1", []);
foreach ($teacherRows as $t) {
    $teachers[$t['id']] = $t;
}

foreach ($uniqueLessons as $key => $lesson) {
    $t = $teachers[$lesson['teacher_id']] ?? null;
    $teacherName = $t['name'] ?? "ID:{$lesson['teacher_id']}";
    $hasTg = $t && !empty($t['telegram_id']) ? '✓' : '✗';
    $inWindow = ($lesson['time'] >= $timeFrom && $lesson['time'] <= $timeTo) ? '◀ В ОКНЕ!' : '';

    echo "{$lesson['time']} - {$teacherName} [TG:{$hasTg}] - " . count($lesson['students']) . " уч. $inWindow\n";
}

echo "\n=== 10. УРОКИ В ОКНЕ CRON ($timeFrom - $timeTo) ===\n";
$lessonsInWindow = [];
foreach ($uniqueLessons as $lesson) {
    if ($lesson['time'] >= $timeFrom && $lesson['time'] <= $timeTo) {
        $lessonsInWindow[] = $lesson;
    }
}

if (empty($lessonsInWindow)) {
    echo "Нет уроков в текущем окне\n";
    echo "(Это НОРМАЛЬНО если сейчас не время уроков)\n";
} else {
    echo "Найдено " . count($lessonsInWindow) . " уроков в окне!\n";
    foreach ($lessonsInWindow as $lesson) {
        echo "- {$lesson['time']}: " . count($lesson['students']) . " учеников\n";
    }
}

echo "\n=== 11. ПРОВЕРКА AUDIT_LOG (отправленные сегодня) ===\n";
$sentToday = dbQuery(
    "SELECT * FROM audit_log
     WHERE action_type = 'attendance_query_sent'
       AND DATE(created_at) = ?
     ORDER BY created_at DESC",
    [$today]
);
echo "Отправлено сообщений сегодня: " . count($sentToday) . "\n";
foreach ($sentToday as $log) {
    $data = json_decode($log['new_value'], true);
    $time = $data['time'] ?? '?';
    $teacherId = $data['teacher_id'] ?? '?';
    echo "- {$log['created_at']}: урок в {$time}, teacher_id={$teacherId}\n";
}

echo "\n=== 12. ПРОВЕРКА ФАЙЛА ЛОГА cron_debug.log ===\n";
$cronLogPath = __DIR__ . '/cron_debug.log';
if (file_exists($cronLogPath)) {
    $logContent = file_get_contents($cronLogPath);
    $lines = explode("\n", trim($logContent));
    $lastLines = array_slice($lines, -20);
    echo "Последние 20 строк лога:\n";
    echo implode("\n", $lastLines) . "\n";
} else {
    echo "✗ Файл cron_debug.log НЕ СУЩЕСТВУЕТ!\n";
    echo "  Это значит cron НИ РАЗУ не запускался после деплоя!\n";
}

echo "\n=== 13. ТЕСТ ОТПРАВКИ В TELEGRAM ===\n";
echo "(Используйте action=send_test через bot_diagnostic.php)\n";

echo "\n=== ВЫВОД ===\n";
if (!file_exists($cronLogPath)) {
    echo "⚠️ ПРОБЛЕМА: Cron не запускается!\n";
    echo "   Проверьте настройку cron в панели TimeWeb\n";
} elseif (count($sentToday) == 0 && count($lessonsInWindow) > 0) {
    echo "⚠️ ПРОБЛЕМА: Есть уроки в окне, но сообщения не отправлены!\n";
    echo "   Проверьте логи на ошибки\n";
} elseif (count($sentToday) == 0 && count($lessonsInWindow) == 0) {
    echo "✓ Всё в порядке - нет уроков в текущем окне времени\n";
    echo "  Cron отправит сообщение когда наступит время урока (+15 мин)\n";
} else {
    echo "✓ Сообщения отправляются нормально\n";
}

echo "</pre>";
