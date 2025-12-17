<?php
/**
 * Cron задача для уведомлений о болеющих учениках
 *
 * Запускать ОДИН РАЗ В ДЕНЬ в 10:00
 * Команда: php /home/c/cw95865/PALOMATIKA/public_html/zarplata/bot/cron_sick.php
 *
 * Crontab: 0 10 * * * php /home/c/cw95865/PALOMATIKA/public_html/zarplata/bot/cron_sick.php
 *
 * Логика:
 * 1. Находит всех болеющих учеников (is_sick = 1)
 * 2. Проверяет, есть ли у них урок ЗАВТРА
 * 3. Отправляет администратору уведомление с кнопками "Придёт" / "Всё ещё болеет"
 */

// Отключаем вывод в stdout
ob_start();

// Отладочный лог
$debugLogFile = __DIR__ . '/cron_sick_debug.log';
$debugMsg = date('Y-m-d H:i:s') . " - Sick reminder cron started\n";
file_put_contents($debugLogFile, $debugMsg, FILE_APPEND);

require_once __DIR__ . '/config.php';

// Логируем запуск
error_log("[CRON SICK] Started at " . date('Y-m-d H:i:s'));

// Получаем chat_id администратора из настроек
$adminSetting = dbQueryOne(
    "SELECT setting_value FROM settings WHERE setting_key = 'admin_telegram_chat_id'",
    []
);

if (!$adminSetting || !$adminSetting['setting_value']) {
    error_log("[CRON SICK] Admin chat_id not configured in settings");
    file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ERROR: admin_telegram_chat_id not set\n", FILE_APPEND);
    ob_end_clean();
    exit(1);
}

$adminChatId = $adminSetting['setting_value'];
error_log("[CRON SICK] Admin chat_id: $adminChatId");

// Вычисляем завтрашний день недели
$tomorrow = new DateTime('tomorrow');
$tomorrowDayOfWeek = (int)$tomorrow->format('N'); // 1 = Monday, 7 = Sunday
$tomorrowDate = $tomorrow->format('Y-m-d');

error_log("[CRON SICK] Tomorrow: $tomorrowDate (day of week: $tomorrowDayOfWeek)");
file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Tomorrow: $tomorrowDate (day $tomorrowDayOfWeek)\n", FILE_APPEND);

// Получаем всех болеющих учеников
try {
    $sickStudents = dbQuery(
        "SELECT id, name, class, schedule, teacher_id FROM students WHERE is_sick = 1 AND active = 1",
        []
    );
} catch (PDOException $e) {
    // Если поля is_sick нет в базе
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        error_log("[CRON SICK] Column is_sick not found. Run migration add_sick_status.sql");
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ERROR: is_sick column not found\n", FILE_APPEND);
        ob_end_clean();
        exit(1);
    }
    throw $e;
}

error_log("[CRON SICK] Found " . count($sickStudents) . " sick students");
file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Found " . count($sickStudents) . " sick students\n", FILE_APPEND);

if (empty($sickStudents)) {
    error_log("[CRON SICK] No sick students, exiting");
    file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - No sick students, exiting\n", FILE_APPEND);
    ob_end_clean();
    exit(0);
}

// Получаем информацию о преподавателях
$teachers = [];
$teacherRows = dbQuery("SELECT id, name, display_name FROM teachers WHERE active = 1", []);
foreach ($teacherRows as $t) {
    $teachers[$t['id']] = $t;
}

// Названия дней недели
$dayNames = ['', 'понедельник', 'вторник', 'среду', 'четверг', 'пятницу', 'субботу', 'воскресенье'];

// Проверяем каждого болеющего ученика
$notificationsSent = 0;

foreach ($sickStudents as $student) {
    $studentId = $student['id'];
    $studentName = $student['name'];
    $studentClass = $student['class'];
    $schedule = $student['schedule'] ? json_decode($student['schedule'], true) : null;

    if (!$schedule || !is_array($schedule)) {
        error_log("[CRON SICK] Student $studentId ($studentName) has no schedule");
        continue;
    }

    // Проверяем, есть ли урок завтра
    $tomorrowLessons = [];

    // Проверяем оба формата ключей: число и строку
    $daySchedule = null;
    if (isset($schedule[$tomorrowDayOfWeek]) && is_array($schedule[$tomorrowDayOfWeek])) {
        $daySchedule = $schedule[$tomorrowDayOfWeek];
    } elseif (isset($schedule[(string)$tomorrowDayOfWeek]) && is_array($schedule[(string)$tomorrowDayOfWeek])) {
        $daySchedule = $schedule[(string)$tomorrowDayOfWeek];
    }

    if (!$daySchedule) {
        error_log("[CRON SICK] Student $studentId ($studentName) has no lessons tomorrow");
        continue;
    }

    // Собираем информацию об уроках
    foreach ($daySchedule as $slot) {
        if (!isset($slot['time'])) continue;

        $time = substr($slot['time'], 0, 5);
        $teacherId = $slot['teacher_id'] ?? $student['teacher_id'];
        $teacherName = '';

        if ($teacherId && isset($teachers[$teacherId])) {
            $t = $teachers[$teacherId];
            $teacherName = !empty($t['display_name']) ? $t['display_name'] : $t['name'];
        }

        $subject = $slot['subject'] ?? 'Мат.';
        $room = $slot['room'] ?? 1;

        $tomorrowLessons[] = [
            'time' => $time,
            'teacher' => $teacherName,
            'subject' => $subject,
            'room' => $room
        ];
    }

    if (empty($tomorrowLessons)) {
        continue;
    }

    // Проверяем, не отправляли ли уже уведомление сегодня для этого ученика
    $today = date('Y-m-d');
    $existingNotification = dbQueryOne(
        "SELECT id FROM audit_log
         WHERE action_type = 'sick_reminder_sent'
           AND entity_type = 'student'
           AND entity_id = ?
           AND DATE(created_at) = ?
         LIMIT 1",
        [$studentId, $today]
    );

    if ($existingNotification) {
        error_log("[CRON SICK] Already sent notification for student $studentId today, skipping");
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Already sent for $studentName today\n", FILE_APPEND);
        continue;
    }

    // Формируем сообщение
    $dayName = $dayNames[$tomorrowDayOfWeek];
    $classStr = $studentClass ? " ({$studentClass} класс)" : "";

    $message = "🤒 <b>Напоминание о болеющем ученике</b>\n\n";
    $message .= "👤 <b>{$studentName}</b>{$classStr}\n\n";
    $message .= "📅 Завтра ({$dayName}) у ученика запланированы занятия:\n";

    foreach ($tomorrowLessons as $lesson) {
        $message .= "   • {$lesson['time']} - {$lesson['subject']}";
        if ($lesson['teacher']) {
            $message .= " ({$lesson['teacher']})";
        }
        $message .= ", каб. {$lesson['room']}\n";
    }

    $message .= "\n❓ <b>Ученик придёт на занятия или всё ещё болеет?</b>";

    // Inline кнопки
    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '✅ Придёт',
                    'callback_data' => "sick_recovered:{$studentId}"
                ],
                [
                    'text' => '🤒 Всё ещё болеет',
                    'callback_data' => "sick_still:{$studentId}"
                ]
            ]
        ]
    ];

    // Логируем в audit_log ДО отправки (предотвращает дубликаты)
    // КРИТИЧНО: если запись не удалась, пропускаем отправку чтобы избежать спама!
    try {
        dbExecute(
            "INSERT INTO audit_log (action_type, entity_type, entity_id, new_value, notes, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                'sick_reminder_sent',
                'student',
                $studentId,
                json_encode([
                    'student_name' => $studentName,
                    'tomorrow_date' => $tomorrowDate,
                    'lessons_count' => count($tomorrowLessons)
                ], JSON_UNESCAPED_UNICODE),
                'Отправка напоминания о болеющем ученике'
            ]
        );
    } catch (Exception $e) {
        error_log("[CRON SICK] Failed to log to audit_log: " . $e->getMessage());
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ❌ SKIP: audit_log failed, preventing spam\n", FILE_APPEND);
        continue; // ВАЖНО: пропускаем отправку, чтобы не создать спам
    }

    // Отправляем сообщение
    file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Sending notification for $studentName\n", FILE_APPEND);

    try {
        $result = sendTelegramMessage($adminChatId, $message, $keyboard);

        if ($result && isset($result['ok']) && $result['ok']) {
            $notificationsSent++;
            error_log("[CRON SICK] Sent notification for student $studentId ($studentName)");
            file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ✅ Sent for $studentName\n", FILE_APPEND);
        } else {
            $errorMsg = isset($result['description']) ? $result['description'] : 'Unknown error';
            error_log("[CRON SICK] Failed to send notification for student $studentId: $errorMsg");
            file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ❌ FAILED for $studentName: $errorMsg\n", FILE_APPEND);
        }
    } catch (Exception $e) {
        error_log("[CRON SICK] Exception sending notification: " . $e->getMessage());
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ❌ EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

error_log("[CRON SICK] Finished. Sent $notificationsSent notifications");
file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Finished. Sent $notificationsSent notifications\n", FILE_APPEND);

ob_end_clean();
exit(0);
