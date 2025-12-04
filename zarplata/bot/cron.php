<?php
/**
 * Cron задача для автоматического опроса посещаемости
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
error_log("Attendance cron started at " . date('Y-m-d H:i:s'));

// Получаем текущий день недели (1 = Понедельник, 7 = Воскресенье)
$dayOfWeek = date('N');

// Получаем текущее время
$currentTime = date('H:i:s');

// Вычисляем время 15 минут назад
$time15MinAgo = date('H:i:s', strtotime('-15 minutes'));

// Получаем уроки, которые начались примерно 15 минут назад (±3 минуты)
$timeFrom = date('H:i:s', strtotime('-18 minutes'));
$timeTo = date('H:i:s', strtotime('-12 minutes'));

// Находим уроки, для которых нужно спросить о посещаемости
$lessons = dbQuery(
    "SELECT lt.*, t.name as teacher_name, t.telegram_id, t.telegram_username
     FROM lessons_template lt
     JOIN teachers t ON lt.teacher_id = t.id
     WHERE lt.day_of_week = ?
       AND lt.time_start BETWEEN ? AND ?
       AND lt.active = 1
       AND t.active = 1
       AND t.telegram_id IS NOT NULL",
    [$dayOfWeek, $timeFrom, $timeTo]
);

if (empty($lessons)) {
    error_log("No lessons found for attendance polling");
    exit(0);
}

error_log("Found " . count($lessons) . " lessons for attendance polling");

// Для каждого урока проверяем, не спрашивали ли уже сегодня
foreach ($lessons as $lesson) {
    $today = date('Y-m-d');

    // ВАЖНО: Проверяем, не отправляли ли уже сообщение сегодня (через audit_log)
    // Это предотвращает дубликаты, даже если преподаватель ещё не ответил
    $existingQuery = dbQueryOne(
        "SELECT id FROM audit_log
         WHERE action_type = 'attendance_query_sent'
           AND entity_type = 'lesson_template'
           AND entity_id = ?
           AND DATE(created_at) = ?
         LIMIT 1",
        [$lesson['id'], $today]
    );

    if ($existingQuery) {
        error_log("Lesson {$lesson['id']} - query already sent today (audit_log ID: {$existingQuery['id']}), skipping");
        continue;
    }

    // Дополнительная проверка: есть ли уже payment за сегодня
    $existingPayment = dbQueryOne(
        "SELECT id FROM payments
         WHERE teacher_id = ? AND lesson_template_id = ?
           AND DATE(created_at) = ?
         LIMIT 1",
        [$lesson['teacher_id'], $lesson['id'], $today]
    );

    if ($existingPayment) {
        error_log("Lesson {$lesson['id']} already has payment for today (ID: {$existingPayment['id']}), skipping");
        continue;
    }

    // Отправляем опрос преподавателю
    sendAttendanceQuery($lesson);
}

error_log("Attendance cron finished");

// Очищаем буфер вывода (не отправляем на email)
ob_end_clean();
exit(0);

/**
 * Отправить опрос о посещаемости
 */
function sendAttendanceQuery($lesson) {
    if (!$lesson['telegram_id']) {
        error_log("Teacher {$lesson['teacher_id']} has no telegram_id, skipping");
        return;
    }

    // ⭐ ДИНАМИЧЕСКИЙ РАСЧЁТ: Получаем реальное количество учеников из таблицы students
    $studentsData = getStudentsForLesson(
        $lesson['teacher_id'],
        $lesson['day_of_week'],
        substr($lesson['time_start'], 0, 5)
    );
    $dynamicStudentCount = $studentsData['count'];

    // Если динамический расчёт дал 0, используем expected_students как fallback
    $expected = $dynamicStudentCount > 0 ? $dynamicStudentCount : (int)$lesson['expected_students'];

    error_log("Lesson {$lesson['id']}: dynamic students = {$dynamicStudentCount}, expected_students = {$lesson['expected_students']}, using = {$expected}");

    // ⭐ КРИТИЧЕСКОЕ ИЗМЕНЕНИЕ: Логируем ДО отправки, чтобы предотвратить дубликаты
    // Даже если отправка не удастся, повторная попытка не будет предпринята
    logAudit(
        'attendance_query_sent',
        'lesson_template',
        $lesson['id'],
        null,
        [
            'teacher_id' => $lesson['teacher_id'],
            'telegram_id' => $lesson['telegram_id'],
            'expected_students' => $expected,
            'dynamic_count' => $dynamicStudentCount,
            'template_expected' => $lesson['expected_students']
        ],
        'Попытка отправки опроса о посещаемости в Telegram'
    );

    error_log("Logged audit entry for lesson {$lesson['id']} BEFORE sending message");

    $chatId = $lesson['telegram_id'];

    // Формируем сообщение
    $subject = $lesson['subject'] ? "<b>{$lesson['subject']}</b>" : "<b>Урок</b>";
    $timeStart = date('H:i', strtotime($lesson['time_start']));
    $timeEnd = date('H:i', strtotime($lesson['time_end']));
    // $expected уже рассчитан выше
    $room = $lesson['room'] ?? '-';
    $tier = $lesson['tier'] ?? '';

    $message = "📊 <b>Отметка посещаемости</b>\n\n";
    $message .= "📚 {$subject}";

    if ($tier) {
        $message .= " [Tier {$tier}]";
    }

    $message .= "\n";
    $message .= "🕐 <b>{$timeStart} - {$timeEnd}</b>\n";

    if ($room) {
        $message .= "🏫 Кабинет {$room}\n";
    }

    $message .= "👥 Ожидалось: <b>{$expected}</b> " . plural($expected, 'ученик', 'ученика', 'учеников') . "\n\n";
    $message .= "❓ <b>Все ученики пришли на урок?</b>";

    // Inline кнопки
    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => '✅ Да, все пришли',
                    'callback_data' => "attendance_all_present:{$lesson['id']}"
                ]
            ],
            [
                [
                    'text' => '❌ Нет, есть отсутствующие',
                    'callback_data' => "attendance_some_absent:{$lesson['id']}"
                ]
            ]
        ]
    ];

    // Отправляем сообщение
    $result = sendTelegramMessage($chatId, $message, $keyboard);

    if ($result) {
        error_log("✅ Attendance query successfully sent to teacher {$lesson['teacher_id']} for lesson {$lesson['id']}");
    } else {
        error_log("❌ Failed to send attendance query to teacher {$lesson['teacher_id']} for lesson {$lesson['id']}");
        // Примечание: audit_log уже записан выше, поэтому повторная попытка не будет предпринята
    }
}

// Функция plural() уже определена в /config/helpers.php (загружается через bot/config.php)
