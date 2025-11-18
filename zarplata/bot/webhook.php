<?php
/**
 * Telegram Bot Webhook Handler
 * Принимает обновления от Telegram и обрабатывает команды
 */

error_log("[Telegram Bot] Webhook called at " . date('Y-m-d H:i:s'));

try {
    require_once __DIR__ . '/config.php';
} catch (Exception $e) {
    error_log("[Telegram Bot] Failed to load config: " . $e->getMessage());
    http_response_code(200);
    exit;
}

// Логирование входящих запросов
$input = file_get_contents('php://input');
error_log("[Telegram Bot] Received data: " . substr($input, 0, 500));

// Парсим JSON от Telegram
$update = json_decode($input, true);

if (!$update) {
    error_log("[Telegram Bot] Invalid JSON from Telegram");
    http_response_code(200);
    exit;
}

error_log("[Telegram Bot] Update parsed successfully");

try {
    // Обработка текстовых сообщений
    if (isset($update['message'])) {
        error_log("[Telegram Bot] Processing message from chat " . $update['message']['chat']['id']);
        handleMessage($update['message']);
    }

    // Обработка callback query (inline кнопки)
    if (isset($update['callback_query'])) {
        error_log("[Telegram Bot] Processing callback query");
        handleCallbackQuery($update['callback_query']);
    }

} catch (Exception $e) {
    error_log("[Telegram Bot] Error: " . $e->getMessage());
    error_log("[Telegram Bot] Stack trace: " . $e->getTraceAsString());
}

http_response_code(200);
exit;

/**
 * Обработка текстовых сообщений
 */
function handleMessage($message) {
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

        case '🗓 Расписание':
            handleCommand($chatId, $telegramId, $username, '/schedule');
            return;

        case 'ℹ️ Помощь':
            handleCommand($chatId, $telegramId, $username, '/help');
            return;
    }

    // Обычные сообщения
    $keyboard = function_exists('getMainMenuKeyboard') ? getMainMenuKeyboard() : null;
    sendTelegramMessage($chatId, "Используйте кнопки меню или команды:\n/start - Регистрация\n/today - Заработок сегодня\n/week - Заработок за неделю", $keyboard);
}

/**
 * Обработка команд
 */
function handleCommand($chatId, $telegramId, $username, $text) {
    $parts = explode(' ', $text);
    $command = strtolower($parts[0]);

    error_log("[Telegram Bot] Handling command: $command");

    switch ($command) {
        case '/start':
            try {
                require_once __DIR__ . '/handlers/StartCommand.php';
                handleStartCommand($chatId, $telegramId, $username);
            } catch (Exception $e) {
                error_log("[Telegram Bot] Error in /start: " . $e->getMessage());
                sendTelegramMessage($chatId, "Произошла ошибка. Попробуйте позже.");
            }
            break;

        case '/today':
            try {
                require_once __DIR__ . '/handlers/TodayCommand.php';
                handleTodayCommand($chatId, $telegramId);
            } catch (Exception $e) {
                error_log("[Telegram Bot] Error in /today: " . $e->getMessage());
                sendTelegramMessage($chatId, "Произошла ошибка. Попробуйте позже.");
            }
            break;

        case '/week':
            try {
                require_once __DIR__ . '/handlers/WeekCommand.php';
                handleWeekCommand($chatId, $telegramId);
            } catch (Exception $e) {
                error_log("[Telegram Bot] Error in /week: " . $e->getMessage());
                sendTelegramMessage($chatId, "Произошла ошибка. Попробуйте позже.");
            }
            break;

        case '/schedule':
            try {
                require_once __DIR__ . '/handlers/ScheduleCommand.php';
                handleScheduleCommand($chatId, $telegramId);
            } catch (Exception $e) {
                error_log("[Telegram Bot] Error in /schedule: " . $e->getMessage());
                sendTelegramMessage($chatId, "Произошла ошибка. Попробуйте позже.");
            }
            break;

        case '/help':
            $keyboard = function_exists('getMainMenuKeyboard') ? getMainMenuKeyboard() : null;
            sendTelegramMessage($chatId,
                "📚 <b>Доступные команды:</b>\n\n" .
                "📅 <b>Сегодня</b> - Заработок за сегодня\n" .
                "📊 <b>Неделя</b> - Заработок за неделю\n" .
                "🗓 <b>Расписание</b> - Расписание на сегодня\n" .
                "ℹ️ <b>Помощь</b> - Эта справка\n\n" .
                "Используйте кнопки меню ниже для быстрого доступа к командам.",
                $keyboard
            );
            break;

        default:
            sendTelegramMessage($chatId, "Неизвестная команда. Используйте /help для списка команд.");
    }
}

/**
 * Обработка callback query (нажатия на inline кнопки)
 */
function handleCallbackQuery($callbackQuery) {
    $chatId = $callbackQuery['message']['chat']['id'];
    $messageId = $callbackQuery['message']['message_id'];
    $telegramId = $callbackQuery['from']['id'];
    $data = $callbackQuery['data'];
    $callbackQueryId = $callbackQuery['id'];

    // Парсим данные кнопки: action:param1:param2
    $parts = explode(':', $data);
    $action = $parts[0];

    switch ($action) {
        case 'attendance_all_present':
            // Все пришли
            require_once __DIR__ . '/handlers/AttendanceHandler.php';
            handleAllPresent($chatId, $messageId, $telegramId, $parts[1], $callbackQueryId);
            break;

        case 'attendance_some_absent':
            // Некоторые отсутствуют
            require_once __DIR__ . '/handlers/AttendanceHandler.php';
            handleSomeAbsent($chatId, $messageId, $telegramId, $parts[1], $callbackQueryId);
            break;

        case 'attendance_count':
            // Указано количество присутствующих
            require_once __DIR__ . '/handlers/AttendanceHandler.php';
            handleAttendanceCount($chatId, $messageId, $telegramId, $parts[1], $parts[2], $callbackQueryId);
            break;

        default:
            answerCallbackQuery($callbackQueryId, "Неизвестное действие");
    }
}
