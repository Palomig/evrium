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

// ⭐ ОТЛАДКА: Пишем в файл чтобы видеть что cron запускается
$debugLogFile = __DIR__ . '/cron_debug.log';
$debugMsg = date('Y-m-d H:i:s') . " - Cron started\n";
file_put_contents($debugLogFile, $debugMsg, FILE_APPEND);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../config/student_helpers.php';
require_once __DIR__ . '/../config/auth.php';  // ⭐ Нужен для logAudit()

// Логируем запуск
error_log("[CRON v2025-12-10] Attendance cron started at " . date('Y-m-d H:i:s'));
file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Config loaded OK\n", FILE_APPEND);

// Получаем текущий день недели (1 = Понедельник, 7 = Воскресенье)
$dayOfWeek = (int)date('N');
$dayOfWeekStr = (string)$dayOfWeek; // ⭐ Для JSON ключей
$today = date('Y-m-d');

// Получаем текущее время
$currentTime = date('H:i');

// ⭐ НОВАЯ ЛОГИКА: Все уроки которые УЖЕ начались (без временного окна!)
// Дубликаты отфильтруются через audit_log
// Это гарантирует что ни один урок не будет пропущен

error_log("[CRON] Looking for all lessons that started before {$currentTime} on day {$dayOfWeek}");

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

        // ⭐ ИСПРАВЛЕНИЕ: Правильно обрабатываем пустой/нулевой teacher_id
        // teacher_id может быть: числом, строкой "5", пустой строкой "", null или отсутствовать
        $slotTeacherId = null;
        if (isset($slot['teacher_id']) && $slot['teacher_id'] !== '' && $slot['teacher_id'] !== null) {
            $slotTeacherId = (int)$slot['teacher_id'];
        }

        // Если teacher_id не указан в слоте, используем teacher_id из колонки students
        $teacherId = $slotTeacherId ?: (int)$student['teacher_id'];

        if (!$teacherId) continue;

        // ⭐ НОВАЯ ЛОГИКА: Проверяем только что урок УЖЕ начался (время <= текущего)
        if ($time <= $currentTime) {
            $key = "{$teacherId}_{$time}";
            if (!isset($uniqueLessons[$key])) {
                $uniqueLessons[$key] = [
                    'teacher_id' => $teacherId,
                    'time' => $time,
                    'subject' => $slot['subject'] ?? 'Мат.',
                    'room' => $slot['room'] ?? 1
                ];
                error_log("[CRON] Found started lesson: {$key}");
            }
        }
    }
}

if (empty($uniqueLessons)) {
    error_log("[CRON] No started lessons found for today");
    file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - No started lessons today (current: {$currentTime}), exiting\n", FILE_APPEND);
    ob_end_clean();
    exit(0);
}

// ⭐ Сортируем уроки по времени (чтобы обрабатывать в правильном порядке)
uasort($uniqueLessons, fn($a, $b) => strcmp($a['time'], $b['time']));

file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Found " . count($uniqueLessons) . " started lessons\n", FILE_APPEND);

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
file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Starting lesson loop...\n", FILE_APPEND);

foreach ($uniqueLessons as $key => $lesson) {
    file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Processing lesson {$key}\n", FILE_APPEND);

    $teacherId = $lesson['teacher_id'];
    $time = $lesson['time'];
    $subject = $subjectMap[$lesson['subject']] ?? $lesson['subject'];
    $room = $lesson['room'];

    $teacher = $teachers[$teacherId] ?? null;
    if (!$teacher) {
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ❌ Teacher {$teacherId} not found, skipping\n", FILE_APPEND);
        continue;
    }

    if (!$teacher['telegram_id']) {
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ❌ Teacher {$teacherId} has no telegram_id, skipping\n", FILE_APPEND);
        continue;
    }

    // Проверяем, не отправляли ли уже сообщение сегодня
    file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Checking audit_log for {$key}...\n", FILE_APPEND);

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
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ❌ Already sent (audit #{$existingQuery['id']}), skipping\n", FILE_APPEND);
        continue;
    }

    // Получаем учеников для этого урока
    file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Getting students for {$key}...\n", FILE_APPEND);

    $studentsData = getStudentsForLesson($teacherId, $dayOfWeek, $time);
    $studentCount = $studentsData['count'];
    $studentNames = array_column($studentsData['students'], 'name');

    if ($studentCount == 0) {
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ❌ 0 students found, skipping\n", FILE_APPEND);
        continue;
    }

    file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ✅ Sending to {$teacher['name']} for {$time} ({$studentCount} students)\n", FILE_APPEND);

    // Отправляем опрос
    sendAttendanceQuery($teacher, $lesson, $studentCount, $studentNames, $subject);
}

error_log("[CRON] Attendance cron finished");
file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - Cron finished successfully\n", FILE_APPEND);

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
    // ВАЖНО: время без двоеточия, иначе explode(':') в webhook сломает парсинг
    $timeForKey = str_replace(':', '-', $time);
    $lessonKey = "{$teacherId}_{$timeForKey}_{$today}";

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

    // ⭐ ОТЛАДКА: Пишем результат в файл
    global $debugLogFile;
    if ($result) {
        error_log("✅ Attendance query sent to {$teacher['name']} for lesson at {$time}");
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ✅ Sent to {$teacher['name']} at {$time}\n", FILE_APPEND);
    } else {
        error_log("❌ Failed to send attendance query to {$teacher['name']} for lesson at {$time}");
        file_put_contents($debugLogFile, date('Y-m-d H:i:s') . " - ❌ FAILED to send to {$teacher['name']} at {$time}\n", FILE_APPEND);
    }
}
