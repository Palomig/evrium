<?php
/**
 * Диагностика Telegram бота
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../config/student_helpers.php';

echo "<pre>\n";
echo "=== Диагностика Telegram бота ===\n";
echo "Дата: " . date('Y-m-d H:i:s') . "\n";
echo "День недели: " . date('N') . " (" . ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'][date('N')] . ")\n\n";

// 1. Проверка токена бота
echo "=== 1. Токен бота ===\n";
$token = getBotToken();
if (empty($token)) {
    echo "❌ ОШИБКА: Токен бота не настроен!\n";
    echo "   Добавьте токен в таблицу settings (setting_key = 'bot_token')\n";
} else {
    $maskedToken = substr($token, 0, 10) . '...' . substr($token, -5);
    echo "✅ Токен найден: {$maskedToken}\n";

    // Проверяем токен через getMe
    $url = "https://api.telegram.org/bot{$token}/getMe";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo "❌ cURL ошибка: {$curlError}\n";
    } elseif ($httpCode !== 200) {
        echo "❌ API ошибка (HTTP {$httpCode}): {$result}\n";
    } else {
        $data = json_decode($result, true);
        if ($data && isset($data['ok']) && $data['ok']) {
            echo "✅ Бот активен: @" . ($data['result']['username'] ?? 'unknown') . "\n";
        } else {
            echo "❌ Неверный токен или бот не активен\n";
        }
    }
}

// 2. Проверка преподавателей
echo "\n=== 2. Преподаватели с Telegram ===\n";
$teachers = dbQuery(
    "SELECT id, name, telegram_id, telegram_username FROM teachers WHERE active = 1",
    []
);

$teachersWithTelegram = 0;
foreach ($teachers as $t) {
    $hasTg = !empty($t['telegram_id']);
    $icon = $hasTg ? '✅' : '❌';
    echo "{$icon} {$t['name']}";
    if ($hasTg) {
        echo " (telegram_id: {$t['telegram_id']}";
        if ($t['telegram_username']) {
            echo ", @{$t['telegram_username']}";
        }
        echo ")";
        $teachersWithTelegram++;
    } else {
        echo " - НЕТ telegram_id";
    }
    echo "\n";
}
echo "Итого с Telegram: {$teachersWithTelegram} из " . count($teachers) . "\n";

// 3. Проверка расписания на сегодня
echo "\n=== 3. Расписание на сегодня ===\n";
$dayOfWeek = (int)date('N');
$today = date('Y-m-d');

$allStudents = dbQuery(
    "SELECT id, name, class, schedule, teacher_id FROM students WHERE active = 1 AND schedule IS NOT NULL",
    []
);

$uniqueLessons = [];
foreach ($allStudents as $student) {
    $schedule = json_decode($student['schedule'], true);
    if (!is_array($schedule)) continue;

    if (isset($schedule[$dayOfWeek]) && is_array($schedule[$dayOfWeek])) {
        foreach ($schedule[$dayOfWeek] as $slot) {
            if (!isset($slot['time'])) continue;

            $time = substr($slot['time'], 0, 5);
            $teacherId = isset($slot['teacher_id']) ? (int)$slot['teacher_id'] : (int)$student['teacher_id'];

            if (!$teacherId) continue;

            $key = "{$teacherId}_{$time}";
            if (!isset($uniqueLessons[$key])) {
                $uniqueLessons[$key] = [
                    'teacher_id' => $teacherId,
                    'time' => $time,
                    'subject' => $slot['subject'] ?? 'Мат.',
                    'students' => []
                ];
            }
            $uniqueLessons[$key]['students'][] = $student['name'];
        }
    }
}

// Сортируем по времени
usort($uniqueLessons, fn($a, $b) => strcmp($a['time'], $b['time']));

if (empty($uniqueLessons)) {
    echo "❌ Нет уроков на сегодня (день {$dayOfWeek})\n";
} else {
    echo "Найдено уроков: " . count($uniqueLessons) . "\n\n";

    // Получаем имена преподавателей
    $teacherNames = [];
    foreach ($teachers as $t) {
        $teacherNames[$t['id']] = $t;
    }

    foreach ($uniqueLessons as $lesson) {
        $tid = $lesson['teacher_id'];
        $teacher = $teacherNames[$tid] ?? null;
        $teacherName = $teacher['name'] ?? "Преподаватель #{$tid}";
        $hasTg = $teacher && !empty($teacher['telegram_id']);
        $tgIcon = $hasTg ? '✅' : '❌';

        $studentCount = count($lesson['students']);

        echo "{$lesson['time']} - {$teacherName} {$tgIcon}\n";
        echo "   Предмет: {$lesson['subject']}, учеников: {$studentCount}\n";
        echo "   Ученики: " . implode(', ', $lesson['students']) . "\n";
    }
}

// 4. Проверка аудит-логов на сегодня
echo "\n=== 4. Отправленные сообщения сегодня ===\n";
$sentToday = dbQuery(
    "SELECT * FROM audit_log
     WHERE action_type = 'attendance_query_sent'
       AND DATE(created_at) = ?
     ORDER BY created_at DESC",
    [$today]
);

if (empty($sentToday)) {
    echo "❌ Сегодня не было отправлено ни одного сообщения\n";
} else {
    echo "Отправлено сообщений: " . count($sentToday) . "\n\n";
    foreach ($sentToday as $log) {
        $data = json_decode($log['new_value'], true);
        $time = date('H:i:s', strtotime($log['created_at']));
        $lessonTime = $data['time'] ?? '?';
        echo "{$time} - Урок в {$lessonTime}, преподаватель ID: " . ($data['teacher_id'] ?? '?') . "\n";
    }
}

// 5. Текущее время и окно поиска
echo "\n=== 5. Текущее время и окно поиска cron ===\n";
$currentTime = date('H:i');
$timeFrom = date('H:i', strtotime('-18 minutes'));
$timeTo = date('H:i', strtotime('-12 minutes'));

echo "Текущее время: {$currentTime}\n";
echo "Окно поиска уроков: {$timeFrom} - {$timeTo}\n";
echo "(cron ищет уроки, которые начались 12-18 минут назад)\n";

// Проверяем, какие уроки попадут в окно
$lessonsInWindow = [];
foreach ($uniqueLessons as $lesson) {
    if ($lesson['time'] >= $timeFrom && $lesson['time'] <= $timeTo) {
        $lessonsInWindow[] = $lesson;
    }
}

if (empty($lessonsInWindow)) {
    echo "\n⚠️ Сейчас нет уроков в окне поиска\n";

    // Найдем ближайший урок
    $now = strtotime($currentTime);
    $nextLesson = null;
    $nextDiff = PHP_INT_MAX;

    foreach ($uniqueLessons as $lesson) {
        $lessonTime = strtotime($lesson['time']);
        $diff = $lessonTime - $now;
        if ($diff > -900 && $diff < $nextDiff) { // -15 минут до текущего
            $nextDiff = $diff;
            $nextLesson = $lesson;
        }
    }

    if ($nextLesson) {
        $mins = round($nextDiff / 60);
        if ($mins > 0) {
            echo "📍 Ближайший урок в {$nextLesson['time']} (через {$mins} мин)\n";
            echo "   Сообщение будет отправлено примерно в " . date('H:i', strtotime($nextLesson['time']) + 900) . "\n";
        } else {
            $mins = abs($mins);
            echo "📍 Последний урок был в {$nextLesson['time']} ({$mins} мин назад)\n";
        }
    }
} else {
    echo "\n✅ Уроки в текущем окне:\n";
    foreach ($lessonsInWindow as $lesson) {
        echo "   {$lesson['time']} - преподаватель ID {$lesson['teacher_id']}\n";
    }
}

// 6. Тест отправки сообщения
echo "\n=== 6. Тест отправки сообщения ===\n";
if (!empty($token) && $teachersWithTelegram > 0) {
    // Находим первого преподавателя с telegram_id
    $testTeacher = null;
    foreach ($teachers as $t) {
        if (!empty($t['telegram_id'])) {
            $testTeacher = $t;
            break;
        }
    }

    if ($testTeacher) {
        echo "Тестовый преподаватель: {$testTeacher['name']} (chat_id: {$testTeacher['telegram_id']})\n";
        echo "\nДля отправки тестового сообщения добавьте параметр ?send_test=1\n";

        if (isset($_GET['send_test']) && $_GET['send_test'] == '1') {
            echo "\n🚀 Отправка тестового сообщения...\n";

            $result = sendTelegramMessage(
                $testTeacher['telegram_id'],
                "🔧 <b>Тестовое сообщение</b>\n\nЭто тест работы бота.\nВремя: " . date('H:i:s')
            );

            if ($result && isset($result['ok']) && $result['ok']) {
                echo "✅ Сообщение успешно отправлено!\n";
            } else {
                echo "❌ Ошибка отправки: " . json_encode($result) . "\n";
            }
        }
    }
} else {
    echo "⚠️ Невозможно провести тест: нет токена или преподавателей с Telegram\n";
}

echo "\n</pre>";
