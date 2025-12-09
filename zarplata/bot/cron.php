<?php
/**
 * Cron задача для автоматического опроса посещаемости
 * ⭐ ЕДИНЫЙ ИСТОЧНИК: students.schedule JSON
 *
 * Версия: 2025-12-09
 *
 * Запускать каждые 5 минут через crontab
 * Команда: php /home/c/cw95865/PALOMATIKA/public_html/zarplata/bot/cron.php
 */

// ═══════════════════════════════════════════════════════════════════════════
// ОТКЛЮЧАЕМ ВЫВОД В STDOUT (чтобы cron не отправлял email)
// ═══════════════════════════════════════════════════════════════════════════
ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../config/student_helpers.php';

// Логируем запуск
error_log("[CRON v2025-12-09] Attendance cron started at " . date('Y-m-d H:i:s'));

// Получаем текущий день недели (1 = Понедельник, 7 = Воскресенье)
$dayOfWeek = (int)date('N');
$dayOfWeekStr = (string)$dayOfWeek; // ⭐ Для JSON ключей
$today = date('Y-m-d');

// Получаем текущее время
$currentTime = date('H:i');

// Вычисляем время 15 минут назад (±3 минуты)
$timeFrom = date('H:i', strtotime('-18 minutes'));
$timeTo = date('H:i', strtotime('-12 minutes'));

error_log("[CRON] Looking for lessons between {$timeFrom} and {$timeTo} on day {$dayOfWeek} ({$dayOfWeekStr})");

// ⭐ ЕДИНЫЙ ИСТОЧНИК: Получаем уроки из students.schedule
$allStudents = dbQuery(
    "SELECT id, name, class, schedule, teacher_id FROM students WHERE active = 1 AND schedule IS NOT NULL",
    []
);

error_log("[CRON] Found " . count($allStudents) . " students with schedule");

// Собираем уникальные уроки на текущий день
$uniqueLessons = [];

foreach ($allStudents as $student) {
    $schedule = json_decode($student['schedule'], true);
    if (!is_array($schedule)) {
        continue;
    }

    // ⭐ Проверяем ОБА варианта ключа: число и строку
    $daySchedule = null;
    if (isset($schedule[$dayOfWeek]) && is_array($schedule[$dayOfWeek])) {
        $daySchedule = $schedule[$dayOfWeek];
    } elseif (isset($schedule[$dayOfWeekStr]) && is_array($schedule[$dayOfWeekStr])) {
        $daySchedule = $schedule[$dayOfWeekStr];
    }

    if (!$daySchedule) {
        continue;
    }

    foreach ($daySchedule as $slot) {
        if (!isset($slot['time'])) continue;

        $time = substr($slot['time'], 0, 5);
        $teacherId = isset($slot['teacher_id']) ? (int)$slot['teacher_id'] : (int)$student['teacher_id'];

        if (!$teacherId) continue;

        // Проверяем, попадает ли время в окно
        if ($time >= $timeFrom && $time <= $timeTo) {
            $key = "{$teacherId}_{$time}";
            if (!isset($uniqueLessons[$key])) {
                $uniqueLessons[$key] = [
                    'teacher_id' => $teacherId,
                    'time' => $time,
                    'subject' => $slot['subject'] ?? 'Мат.',
                    'room' => $slot['room'] ?? 1
                ];
                error_log("[CRON] Found lesson in window: {$key}");
            }
        }
    }
}

if (empty($uniqueLessons)) {
    error_log("[CRON] No lessons found for attendance polling in time window {$timeFrom}-{$timeTo}");
    ob_end_clean();
    exit(0);
}

error_log("[CRON] Found " . count($uniqueLessons) . " lessons for attendance polling");

// Получаем информацию о преподавателях
$teachers = [];
$teacherRows = dbQuery(
    "SELECT id, name, telegram_id, telegram_username, formula_id_group, formula_id_individual, formula_id
     FROM teachers WHERE active = 1",
    []
);
foreach ($teacherRows as $t) {
    $teachers[$t['id']] = $t;
}

// Маппинг предметов
$subjectMap = [
    'Мат.' => 'Математика',
    'Физ.' => 'Физика',
    'Инф.' => 'Информатика'
];

// Для каждого урока проверяем и отправляем сообщение
foreach ($uniqueLessons as $key => $lesson) {
    $teacherId = $lesson['teacher_id'];
    $time = $lesson['time'];
    $subject = $subjectMap[$lesson['subject']] ?? $lesson['subject'];
    $room = $lesson['room'];

    $teacher = $teachers[$teacherId] ?? null;
    if (!$teacher) {
        error_log("[CRON] Teacher {$teacherId} not found, skipping");
        continue;
    }

    if (!$teacher['telegram_id']) {
        error_log("[CRON] Teacher {$teacherId} ({$teacher['name']}) has no telegram_id, skipping");
        continue;
    }

    // Проверяем, не отправляли ли уже сообщение сегодня
    $existingQuery = dbQueryOne(
        "SELECT id FROM audit_log
         WHERE action_type = 'attendance_query_sent'
           AND entity_type = 'lesson_schedule'
           AND new_value LIKE ?
           AND DATE(created_at) = ?
         LIMIT 1",
        ["%teacher_id\":{$teacherId}%time\":\"{$time}%", $today]
    );

    if ($existingQuery) {
        error_log("[CRON] Lesson {$key} - query already sent today (audit_log ID: {$existingQuery['id']}), skipping");
        continue;
    }

    // Получаем учеников для этого урока
    $studentsData = getStudentsForLesson($teacherId, $dayOfWeek, $time);
    $studentCount = $studentsData['count'];
    $studentNames = array_column($studentsData['students'], 'name');

    if ($studentCount == 0) {
        error_log("[CRON] Lesson {$key} has 0 students, skipping");
        continue;
    }

    error_log("[CRON] Sending query for lesson {$key}: {$studentCount} students ({$teacher['name']}, {$time})");

    // Отправляем опрос
    sendAttendanceQuery($teacher, $lesson, $studentCount, $studentNames, $subject);
}

error_log("[CRON] Attendance cron finished");

// Очищаем буфер вывода
ob_end_clean();
exit(0);

/**
 * Отправить опрос о посещаемости
 */
function sendAttendanceQuery($teacher, $lesson, $studentCount, $studentNames, $subject) {
    global $today, $dayOfWeek;

    $teacherId = $teacher['id'];
    $chatId = $teacher['telegram_id'];
    $time = $lesson['time'];
    $room = $lesson['room'];

    // Логируем ДО отправки (предотвращает дубликаты)
    logAudit(
        'attendance_query_sent',
        'lesson_schedule',
        null,
        null,
        [
            'teacher_id' => $teacherId,
            'telegram_id' => $chatId,
            'time' => $time,
            'expected_students' => $studentCount,
            'student_names' => $studentNames,
            'subject' => $subject
        ],
        'Отправка опроса о посещаемости'
    );

    // Формируем сообщение
    $timeEnd = date('H:i', strtotime($time) + 3600);

    $message = "📊 <b>Отметка посещаемости</b>\n\n";
    $message .= "📚 <b>{$subject}</b>\n";
    $message .= "🕐 <b>{$time} - {$timeEnd}</b>\n";
    $message .= "🏫 Кабинет {$room}\n";
    $message .= "👥 Ожидалось: <b>{$studentCount}</b> " . plural($studentCount, 'ученик', 'ученика', 'учеников') . "\n";

    if (!empty($studentNames)) {
        $message .= "📝 " . implode(', ', $studentNames) . "\n";
    }

    $message .= "\n❓ <b>Все ученики пришли на урок?</b>";

    // Создаём уникальный идентификатор урока для callback
    $lessonKey = "{$teacherId}_{$time}_{$today}";

    // Inline кнопки
    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '✅ Да, все пришли',
                    'callback_data' => "att_all:{$lessonKey}"
                ]
            ],
            [
                [
                    'text' => '❌ Нет, есть отсутствующие',
                    'callback_data' => "att_absent:{$lessonKey}"
                ]
            ]
        ]
    ];

    // Отправляем сообщение
    $result = sendTelegramMessage($chatId, $message, $keyboard);

    if ($result) {
        error_log("✅ Attendance query sent to {$teacher['name']} for lesson at {$time}");
    } else {
        error_log("❌ Failed to send attendance query to {$teacher['name']} for lesson at {$time}");
    }
}
