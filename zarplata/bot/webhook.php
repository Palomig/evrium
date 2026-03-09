<?php
/**
 * Telegram Bot Webhook Handler
 * КРИТИЧЕСКИ ВАЖНО: Всегда возвращает 200, чтобы Telegram не делал retry
 */

// ═══════════════════════════════════════════════════════════════════════════
// ПЕРВЫМ ДЕЛОМ - отправляем 200 и закрываем соединение с Telegram
// ═══════════════════════════════════════════════════════════════════════════
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);

// Сбрасываем все буферы вывода
while (ob_get_level()) {
    ob_end_flush();
}
flush();

// Для PHP-FPM: завершаем HTTP-соединение немедленно
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ═══════════════════════════════════════════════════════════════════════════
// Теперь Telegram получил HTTP 200 и НЕ будет повторять запрос
// Вся дальнейшая обработка идёт в фоне
// ═══════════════════════════════════════════════════════════════════════════

// Читаем входящие данные ДО подключения конфига (чтобы не потерять php://input)
$input = file_get_contents('php://input');

// Главный try-catch — ловим ВСЕ ошибки
try {
    require_once __DIR__ . '/config.php';

    error_log("[Telegram Bot] Webhook called at " . date('Y-m-d H:i:s'));
    error_log("[Telegram Bot] Received: " . substr($input, 0, 500));

    $update = json_decode($input, true);

    if (!$update) {
        error_log("[Telegram Bot] Invalid JSON from Telegram");
        exit;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Защита от дублей по update_id
    // ═══════════════════════════════════════════════════════════════════════
    $updateId = $update['update_id'] ?? null;

    if ($updateId) {
        // Проверяем, не обрабатывали ли уже этот update
        try {
            $existing = dbQueryOne(
                "SELECT id FROM telegram_updates WHERE update_id = ?",
                [$updateId]
            );

            if ($existing) {
                error_log("[Telegram Bot] Duplicate update_id: $updateId, skipping");
                exit;
            }

            // Записываем update_id (с игнорированием дубликатов на случай race condition)
            dbExecute(
                "INSERT IGNORE INTO telegram_updates (update_id, created_at) VALUES (?, NOW())",
                [$updateId]
            );
        } catch (Exception $e) {
            // Если таблица не существует — просто логируем и продолжаем
            error_log("[Telegram Bot] telegram_updates table error: " . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Обработка входящих сообщений
    // ═══════════════════════════════════════════════════════════════════════

    if (isset($update['message'])) {
        error_log("[Telegram Bot] Processing message from chat " . $update['message']['chat']['id']);
        handleMessage($update['message']);
    }

    if (isset($update['callback_query'])) {
        error_log("[Telegram Bot] Processing callback query");
        handleCallbackQuery($update['callback_query']);
    }

} catch (Throwable $e) {
    // Ловим ВСЕ ошибки (Exception и Error) — логируем, но НЕ падаем
    error_log("[Telegram Bot] CRITICAL ERROR: " . $e->getMessage());
    error_log("[Telegram Bot] File: " . $e->getFile() . ":" . $e->getLine());
    error_log("[Telegram Bot] Trace: " . $e->getTraceAsString());
}

exit;

// ═══════════════════════════════════════════════════════════════════════════
// ФУНКЦИИ ОБРАБОТКИ
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Обработка текстовых сообщений
 */
function handleMessage($message) {
    try {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $telegramId = $message['from']['id'];
        $username = $message['from']['username'] ?? '';

        error_log("[Telegram Bot] Message text: $text from user $telegramId");

        // Команды начинаются с /
        if (strpos($text, '/') === 0) {
            handleCommand($chatId, $telegramId, $username, $text);
            return;
        }

        // Обработка кнопок меню
        switch ($text) {
            case '📅 Сегодня':
                handleCommand($chatId, $telegramId, $username, '/today');
                return;

            case '📊 Неделя':
                handleCommand($chatId, $telegramId, $username, '/week');
                return;

            case '📆 Месяц':
                handleCommand($chatId, $telegramId, $username, '/month');
                return;

            case '🗓 Расписание':
                handleCommand($chatId, $telegramId, $username, '/schedule');
                return;

            case 'ℹ️ Помощь':
                handleCommand($chatId, $telegramId, $username, '/help');
                return;
        }

        // Обычные сообщения
        $keyboard = function_exists('getMainMenuKeyboard') ? getMainMenuKeyboard() : null;
        sendTelegramMessage($chatId, "Используйте кнопки меню или команды:\n/start - Регистрация\n/today - Заработок сегодня\n/week - Заработок за неделю\n/month - Заработок за месяц", $keyboard);

    } catch (Throwable $e) {
        error_log("[Telegram Bot] Error in handleMessage: " . $e->getMessage());
    }
}

/**
 * Обработка команд
 */
function handleCommand($chatId, $telegramId, $username, $text) {
    try {
        $parts = explode(' ', $text);
        $command = strtolower($parts[0]);

        error_log("[Telegram Bot] Handling command: $command");

        switch ($command) {
            case '/start':
                require_once __DIR__ . '/handlers/StartCommand.php';
                handleStartCommand($chatId, $telegramId, $username);
                break;

            case '/today':
                require_once __DIR__ . '/handlers/TodayCommand.php';
                handleTodayCommand($chatId, $telegramId);
                break;

            case '/week':
                require_once __DIR__ . '/handlers/WeekCommand.php';
                handleWeekCommand($chatId, $telegramId);
                break;

            case '/month':
                require_once __DIR__ . '/handlers/MonthCommand.php';
                handleMonthCommand($chatId, $telegramId);
                break;

            case '/schedule':
                require_once __DIR__ . '/handlers/ScheduleCommand.php';
                handleScheduleCommand($chatId, $telegramId);
                break;

            case '/help':
                $keyboard = function_exists('getMainMenuKeyboard') ? getMainMenuKeyboard() : null;
                sendTelegramMessage($chatId,
                    "📚 <b>Доступные команды:</b>\n\n" .
                    "📅 <b>Сегодня</b> - Заработок за сегодня\n" .
                    "📊 <b>Неделя</b> - Заработок за неделю\n" .
                    "📆 <b>Месяц</b> - Заработок за текущий месяц\n" .
                    "🗓 <b>Расписание</b> - Расписание на сегодня\n" .
                    "ℹ️ <b>Помощь</b> - Эта справка\n\n" .
                    "Используйте кнопки меню ниже для быстрого доступа к командам.",
                    $keyboard
                );
                break;

            default:
                sendTelegramMessage($chatId, "Неизвестная команда. Используйте /help для списка команд.");
        }

    } catch (Throwable $e) {
        error_log("[Telegram Bot] Error in handleCommand: " . $e->getMessage());
        sendTelegramMessage($chatId, "Произошла ошибка. Попробуйте позже.");
    }
}

/**
 * Обработка callback query (нажатия на inline кнопки)
 */
function handleCallbackQuery($callbackQuery) {
    $callbackQueryId = $callbackQuery['id'];

    try {
        $chatId = $callbackQuery['message']['chat']['id'];
        $messageId = $callbackQuery['message']['message_id'];
        $telegramId = $callbackQuery['from']['id'];
        $data = $callbackQuery['data'];

        error_log("[Telegram Bot] Callback query received: $data from user $telegramId");

        // Парсим данные кнопки: action:param1:param2
        $parts = explode(':', $data);
        $action = $parts[0];

        error_log("[Telegram Bot] Callback action: $action");

        switch ($action) {
            case 'attendance_all_present':
                require_once __DIR__ . '/handlers/AttendanceHandler.php';
                handleAllPresent($chatId, $messageId, $telegramId, $parts[1], $callbackQueryId);
                break;

            case 'attendance_some_absent':
                require_once __DIR__ . '/handlers/AttendanceHandler.php';
                handleSomeAbsent($chatId, $messageId, $telegramId, $parts[1], $callbackQueryId);
                break;

            case 'attendance_count':
                require_once __DIR__ . '/handlers/AttendanceHandler.php';
                handleAttendanceCount($chatId, $messageId, $telegramId, $parts[1], $parts[2], $callbackQueryId);
                break;

            // ⭐ Новый формат callback из students.schedule
            case 'att_all':
                require_once __DIR__ . '/handlers/AttendanceHandler.php';
                handleAttAllPresent($chatId, $messageId, $telegramId, $parts[1], $callbackQueryId);
                break;

            case 'att_absent':
                require_once __DIR__ . '/handlers/AttendanceHandler.php';
                handleAttSomeAbsent($chatId, $messageId, $telegramId, $parts[1], $callbackQueryId);
                break;

            case 'att_count':
                require_once __DIR__ . '/handlers/AttendanceHandler.php';
                handleAttCount($chatId, $messageId, $telegramId, $parts[1], $parts[2], $callbackQueryId);
                break;

            // Обработка уведомлений о болеющих учениках
            case 'sick_recovered':
                handleSickRecovered($chatId, $messageId, $parts[1], $callbackQueryId);
                break;

            case 'sick_still':
                handleSickStill($chatId, $messageId, $parts[1], $callbackQueryId);
                break;

            default:
                error_log("[Telegram Bot] Unknown callback action: $action");
                answerCallbackQuery($callbackQueryId, "Неизвестное действие");
        }

    } catch (Throwable $e) {
        error_log("[Telegram Bot] Error in handleCallbackQuery: " . $e->getMessage());
        error_log("[Telegram Bot] Trace: " . $e->getTraceAsString());

        // Пытаемся хотя бы ответить на callback, чтобы убрать "loading" в Telegram
        try {
            answerCallbackQuery($callbackQueryId, "Произошла ошибка", true);
        } catch (Throwable $e2) {
            error_log("[Telegram Bot] Failed to answer callback: " . $e2->getMessage());
        }
    }
}

/**
 * Обработка: ученик выздоровел (кнопка "Придёт")
 */
function handleSickRecovered($chatId, $messageId, $studentId, $callbackQueryId) {
    try {
        $studentId = filter_var($studentId, FILTER_VALIDATE_INT);

        if (!$studentId) {
            answerCallbackQuery($callbackQueryId, "Неверный ID ученика", true);
            return;
        }

        // Получаем данные ученика
        $student = dbQueryOne("SELECT id, name, class, is_sick FROM students WHERE id = ?", [$studentId]);

        if (!$student) {
            answerCallbackQuery($callbackQueryId, "Ученик не найден", true);
            return;
        }

        // Снимаем статус "болеет"
        dbExecute(
            "UPDATE students SET is_sick = 0, updated_at = NOW() WHERE id = ?",
            [$studentId]
        );

        // Логируем в audit_log
        try {
            dbExecute(
                "INSERT INTO audit_log (action_type, entity_type, entity_id, old_value, new_value, notes, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    'student_recovered',
                    'student',
                    $studentId,
                    json_encode(['is_sick' => 1]),
                    json_encode(['is_sick' => 0]),
                    'Ученик выздоровел (через Telegram)'
                ]
            );
        } catch (Exception $e) {
            error_log("[Telegram Bot] Failed to log recovery: " . $e->getMessage());
        }

        $studentName = $student['name'];
        $classStr = $student['class'] ? " ({$student['class']} класс)" : "";

        // Обновляем сообщение
        $newText = "✅ <b>Ученик выздоровел!</b>\n\n";
        $newText .= "👤 <b>{$studentName}</b>{$classStr}\n\n";
        $newText .= "Статус \"болеет\" снят. Ученик придёт на занятия.";

        editTelegramMessage($chatId, $messageId, $newText, null);

        answerCallbackQuery($callbackQueryId, "Статус обновлён: ученик придёт");

        error_log("[Telegram Bot] Student $studentId marked as recovered");

    } catch (Throwable $e) {
        error_log("[Telegram Bot] Error in handleSickRecovered: " . $e->getMessage());
        answerCallbackQuery($callbackQueryId, "Произошла ошибка", true);
    }
}

/**
 * Обработка: ученик всё ещё болеет (кнопка "Всё ещё болеет")
 */
function handleSickStill($chatId, $messageId, $studentId, $callbackQueryId) {
    try {
        $studentId = filter_var($studentId, FILTER_VALIDATE_INT);

        if (!$studentId) {
            answerCallbackQuery($callbackQueryId, "Неверный ID ученика", true);
            return;
        }

        // Получаем данные ученика
        $student = dbQueryOne("SELECT id, name, class FROM students WHERE id = ?", [$studentId]);

        if (!$student) {
            answerCallbackQuery($callbackQueryId, "Ученик не найден", true);
            return;
        }

        // Логируем подтверждение болезни
        try {
            dbExecute(
                "INSERT INTO audit_log (action_type, entity_type, entity_id, new_value, notes, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [
                    'sick_confirmed',
                    'student',
                    $studentId,
                    json_encode(['is_sick' => 1]),
                    'Подтверждено: ученик всё ещё болеет (через Telegram)'
                ]
            );
        } catch (Exception $e) {
            error_log("[Telegram Bot] Failed to log sick confirmation: " . $e->getMessage());
        }

        $studentName = $student['name'];
        $classStr = $student['class'] ? " ({$student['class']} класс)" : "";

        // Обновляем сообщение
        $newText = "🤒 <b>Ученик всё ещё болеет</b>\n\n";
        $newText .= "👤 <b>{$studentName}</b>{$classStr}\n\n";
        $newText .= "Статус сохранён. Напоминание придёт перед следующим занятием.";

        editTelegramMessage($chatId, $messageId, $newText, null);

        answerCallbackQuery($callbackQueryId, "Понятно, ученик всё ещё болеет");

        error_log("[Telegram Bot] Student $studentId confirmed still sick");

    } catch (Throwable $e) {
        error_log("[Telegram Bot] Error in handleSickStill: " . $e->getMessage());
        answerCallbackQuery($callbackQueryId, "Произошла ошибка", true);
    }
}
