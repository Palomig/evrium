<?php
/**
 * API для диагностики и тестирования Telegram бота
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../bot/config.php';
require_once __DIR__ . '/../config/student_helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Разрешаем доступ без авторизации для run_cron с секретным ключом
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$secretKey = $_GET['key'] ?? '';

// Для run_cron разрешаем без авторизации (для вызова из cron или вручную)
if ($action === 'run_cron' || $action === 'diagnostic') {
    // Разрешаем без авторизации
} else {
    session_start();
    if (!isLoggedIn()) {
        jsonError('Необходима авторизация', 401);
    }
}

switch ($action) {
    case 'diagnostic':
        runDiagnostic();
        break;

    case 'send_test':
        sendTestMsg();
        break;

    case 'run_cron':
        runCronManually();
        break;

    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Полная диагностика бота
 */
function runDiagnostic() {
    $result = [
        'token' => ['status' => 'unknown', 'message' => ''],
        'bot_info' => null,
        'teachers' => ['total' => 0, 'with_telegram' => 0, 'list' => []],
        'schedule' => ['lessons_count' => 0, 'lessons' => []],
        'sent_today' => ['count' => 0, 'messages' => []],
        'cron_window' => ['current_time' => '', 'window_from' => '', 'window_to' => '', 'lessons_in_window' => 0],
        'next_lesson' => null
    ];

    // 1. Проверка токена
    $token = getBotToken();
    if (empty($token)) {
        $result['token'] = ['status' => 'error', 'message' => 'Токен не настроен в settings'];
    } else {
        $result['token'] = ['status' => 'ok', 'message' => 'Токен найден'];

        // Проверяем через getMe
        $url = "https://api.telegram.org/bot{$token}/getMe";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $result['token'] = ['status' => 'error', 'message' => "cURL ошибка: {$curlError}"];
        } elseif ($httpCode !== 200) {
            $result['token'] = ['status' => 'error', 'message' => "API ошибка (HTTP {$httpCode})"];
        } else {
            $data = json_decode($response, true);
            if ($data && isset($data['ok']) && $data['ok']) {
                $result['bot_info'] = [
                    'username' => '@' . ($data['result']['username'] ?? 'unknown'),
                    'first_name' => $data['result']['first_name'] ?? '',
                    'id' => $data['result']['id'] ?? 0
                ];
            } else {
                $result['token'] = ['status' => 'error', 'message' => 'Неверный токен'];
            }
        }
    }

    // 2. Преподаватели
    $teachers = dbQuery(
        "SELECT id, name, telegram_id, telegram_username FROM teachers WHERE active = 1",
        []
    );

    $result['teachers']['total'] = count($teachers);
    foreach ($teachers as $t) {
        $hasTg = !empty($t['telegram_id']);
        if ($hasTg) {
            $result['teachers']['with_telegram']++;
        }
        $result['teachers']['list'][] = [
            'id' => $t['id'],
            'name' => $t['name'],
            'has_telegram' => $hasTg,
            'telegram_id' => $t['telegram_id'] ?: null,
            'telegram_username' => $t['telegram_username'] ?: null
        ];
    }

    // 3. Расписание на сегодня
    $dayOfWeek = (int)date('N');
    $dayOfWeekStr = (string)$dayOfWeek; // ⭐ Для JSON ключей
    $today = date('Y-m-d');

    $allStudents = dbQuery(
        "SELECT id, name, class, schedule, teacher_id FROM students WHERE active = 1 AND schedule IS NOT NULL",
        []
    );

    $uniqueLessons = [];
    foreach ($allStudents as $student) {
        $schedule = json_decode($student['schedule'], true);
        if (!is_array($schedule)) continue;

        // ⭐ Проверяем ОБА варианта ключа: число и строку
        $daySchedule = null;
        if (isset($schedule[$dayOfWeek]) && is_array($schedule[$dayOfWeek])) {
            $daySchedule = $schedule[$dayOfWeek];
        } elseif (isset($schedule[$dayOfWeekStr]) && is_array($schedule[$dayOfWeekStr])) {
            $daySchedule = $schedule[$dayOfWeekStr];
        }

        if ($daySchedule) {
            foreach ($daySchedule as $slot) {
                if (!isset($slot['time'])) continue;

                $time = substr($slot['time'], 0, 5);
                // ⭐ ИСПРАВЛЕНИЕ: Правильно обрабатываем пустой/нулевой teacher_id
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
                        'students' => []
                    ];
                }
                $uniqueLessons[$key]['students'][] = $student['name'];
            }
        }
    }

    usort($uniqueLessons, fn($a, $b) => strcmp($a['time'], $b['time']));

    // Добавляем имена преподавателей
    $teacherMap = [];
    foreach ($teachers as $t) {
        $teacherMap[$t['id']] = $t;
    }

    foreach ($uniqueLessons as &$lesson) {
        $t = $teacherMap[$lesson['teacher_id']] ?? null;
        $lesson['teacher_name'] = $t['name'] ?? "Преподаватель #{$lesson['teacher_id']}";
        $lesson['teacher_has_telegram'] = $t && !empty($t['telegram_id']);
        $lesson['student_count'] = count($lesson['students']);
    }

    $result['schedule']['lessons_count'] = count($uniqueLessons);
    $result['schedule']['lessons'] = array_values($uniqueLessons);
    $result['schedule']['day_of_week'] = $dayOfWeek;
    $result['schedule']['day_name'] = ['', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'][$dayOfWeek];

    // 4. Отправленные сообщения сегодня
    $sentToday = dbQuery(
        "SELECT * FROM audit_log
         WHERE action_type = 'attendance_query_sent'
           AND DATE(created_at) = ?
         ORDER BY created_at DESC",
        [$today]
    );

    $result['sent_today']['count'] = count($sentToday);
    foreach ($sentToday as $log) {
        $data = json_decode($log['new_value'], true);
        $result['sent_today']['messages'][] = [
            'time_sent' => date('H:i:s', strtotime($log['created_at'])),
            'lesson_time' => $data['time'] ?? '?',
            'teacher_id' => $data['teacher_id'] ?? '?',
            'expected_students' => $data['expected_students'] ?? '?'
        ];
    }

    // 4.1. История сообщений за последнюю неделю
    $weekAgo = date('Y-m-d', strtotime('-7 days'));
    $sentLastWeek = dbQuery(
        "SELECT DATE(created_at) as date, COUNT(*) as count
         FROM audit_log
         WHERE action_type = 'attendance_query_sent'
           AND DATE(created_at) >= ?
         GROUP BY DATE(created_at)
         ORDER BY date DESC",
        [$weekAgo]
    );

    $result['sent_last_week'] = [];
    foreach ($sentLastWeek as $row) {
        $result['sent_last_week'][] = [
            'date' => $row['date'],
            'count' => (int)$row['count']
        ];
    }

    // 5. Окно cron
    $currentTime = date('H:i');
    $timeFrom = date('H:i', strtotime('-18 minutes'));
    $timeTo = date('H:i', strtotime('-12 minutes'));

    $result['cron_window'] = [
        'current_time' => $currentTime,
        'window_from' => $timeFrom,
        'window_to' => $timeTo,
        'lessons_in_window' => 0
    ];

    // Проверяем уроки в окне
    foreach ($uniqueLessons as $lesson) {
        if ($lesson['time'] >= $timeFrom && $lesson['time'] <= $timeTo) {
            $result['cron_window']['lessons_in_window']++;
        }
    }

    // 6. Ближайший урок
    $now = strtotime($currentTime);
    $nextLesson = null;
    $nextDiff = PHP_INT_MAX;

    foreach ($uniqueLessons as $lesson) {
        $lessonTime = strtotime($lesson['time']);
        $diff = $lessonTime - $now;
        if ($diff > -900 && $diff < $nextDiff) {
            $nextDiff = $diff;
            $nextLesson = $lesson;
        }
    }

    if ($nextLesson) {
        $mins = round($nextDiff / 60);
        $result['next_lesson'] = [
            'time' => $nextLesson['time'],
            'teacher_name' => $nextLesson['teacher_name'],
            'minutes_until' => $mins,
            'message_will_be_sent_at' => date('H:i', strtotime($nextLesson['time']) + 900)
        ];
    }

    jsonSuccess($result);
}

/**
 * Отправить тестовое сообщение
 */
function sendTestMsg() {
    $token = getBotToken();
    if (empty($token)) {
        jsonError('Токен бота не настроен', 400);
    }

    // Находим первого преподавателя с telegram_id
    $teacher = dbQueryOne(
        "SELECT id, name, telegram_id FROM teachers WHERE active = 1 AND telegram_id IS NOT NULL LIMIT 1",
        []
    );

    if (!$teacher) {
        jsonError('Нет преподавателей с привязанным Telegram', 400);
    }

    $message = "🔧 <b>Тестовое сообщение</b>\n\n" .
               "Это тест работы бота.\n" .
               "Преподаватель: {$teacher['name']}\n" .
               "Время: " . date('H:i:s d.m.Y');

    $result = sendTelegramMessage($teacher['telegram_id'], $message);

    if ($result && isset($result['ok']) && $result['ok']) {
        jsonSuccess([
            'message' => 'Сообщение отправлено',
            'teacher' => $teacher['name'],
            'chat_id' => $teacher['telegram_id']
        ]);
    } else {
        jsonError('Ошибка отправки: ' . json_encode($result), 500);
    }
}

/**
 * Запустить cron вручную
 */
function runCronManually() {
    $token = getBotToken();
    if (empty($token)) {
        jsonError('Токен бота не настроен', 400);
    }

    $dayOfWeek = (int)date('N');
    $dayOfWeekStr = (string)$dayOfWeek; // ⭐ Для JSON ключей
    $today = date('Y-m-d');
    $currentTime = date('H:i');

    // Получаем ВСЕ уроки на сегодня (без фильтра по времени)
    $allStudents = dbQuery(
        "SELECT id, name, class, schedule, teacher_id FROM students WHERE active = 1 AND schedule IS NOT NULL",
        []
    );

    $uniqueLessons = [];
    foreach ($allStudents as $student) {
        $schedule = json_decode($student['schedule'], true);
        if (!is_array($schedule)) continue;

        // ⭐ Проверяем ОБА варианта ключа: число и строку
        $daySchedule = null;
        if (isset($schedule[$dayOfWeek]) && is_array($schedule[$dayOfWeek])) {
            $daySchedule = $schedule[$dayOfWeek];
        } elseif (isset($schedule[$dayOfWeekStr]) && is_array($schedule[$dayOfWeekStr])) {
            $daySchedule = $schedule[$dayOfWeekStr];
        }

        if ($daySchedule) {
            foreach ($daySchedule as $slot) {
                if (!isset($slot['time'])) continue;

                $time = substr($slot['time'], 0, 5);
                // ⭐ ИСПРАВЛЕНИЕ: Правильно обрабатываем пустой/нулевой teacher_id
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
                        'room' => $slot['room'] ?? 1
                    ];
                }
            }
        }
    }

    // Фильтруем уроки которые УЖЕ прошли (время <= текущего)
    $passedLessons = array_filter($uniqueLessons, fn($l) => $l['time'] <= $currentTime);

    if (empty($passedLessons)) {
        jsonError("Нет прошедших уроков сегодня (сейчас {$currentTime})", 400);
    }

    // Получаем преподавателей
    $teachers = [];
    $teacherRows = dbQuery(
        "SELECT id, name, telegram_id, telegram_username, formula_id_group, formula_id_individual, formula_id
         FROM teachers WHERE active = 1",
        []
    );
    foreach ($teacherRows as $t) {
        $teachers[$t['id']] = $t;
    }

    $subjectMap = [
        'Мат.' => 'Математика',
        'Физ.' => 'Физика',
        'Инф.' => 'Информатика'
    ];

    $sent = 0;
    $skipped = 0;
    $errors = [];

    foreach ($passedLessons as $key => $lesson) {
        $teacherId = $lesson['teacher_id'];
        $time = $lesson['time'];
        $subject = $subjectMap[$lesson['subject']] ?? $lesson['subject'];
        $room = $lesson['room'];

        $teacher = $teachers[$teacherId] ?? null;
        if (!$teacher) {
            $errors[] = "Урок {$time}: преподаватель #{$teacherId} не найден";
            continue;
        }

        if (!$teacher['telegram_id']) {
            $skipped++;
            continue;
        }

        // Проверяем, не отправляли ли уже
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
            $skipped++;
            continue;
        }

        // Получаем учеников
        $studentsData = getStudentsForLesson($teacherId, $dayOfWeek, $time);
        $studentCount = $studentsData['count'];
        $studentNames = array_column($studentsData['students'], 'name');

        if ($studentCount == 0) {
            $skipped++;
            continue;
        }

        // Отправляем сообщение
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

        // ВАЖНО: время без двоеточия, иначе explode(':') в webhook сломает парсинг
        $timeForKey = str_replace(':', '-', $time);
        $lessonKey = "{$teacherId}_{$timeForKey}_{$today}";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '✅ Да, все пришли', 'callback_data' => "att_all:{$lessonKey}"]],
                [['text' => '❌ Нет, есть отсутствующие', 'callback_data' => "att_absent:{$lessonKey}"]]
            ]
        ];

        // Логируем ДО отправки
        logAudit(
            'attendance_query_sent',
            'lesson_schedule',
            null,
            null,
            [
                'teacher_id' => $teacherId,
                'telegram_id' => $teacher['telegram_id'],
                'time' => $time,
                'expected_students' => $studentCount,
                'student_names' => $studentNames,
                'subject' => $subject
            ],
            'Отправка опроса о посещаемости (вручную)'
        );

        $result = sendTelegramMessage($teacher['telegram_id'], $message, $keyboard);

        if ($result && isset($result['ok']) && $result['ok']) {
            $sent++;
        } else {
            $errors[] = "Урок {$time} ({$teacher['name']}): ошибка отправки";
        }
    }

    jsonSuccess([
        'total_lessons' => count($passedLessons),
        'sent' => $sent,
        'skipped' => $skipped,
        'errors' => $errors
    ]);
}
