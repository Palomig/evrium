<?php
/**
 * Telegram Bot Webhook Handler
 * Принимает обновления от Telegram и обрабатывает команды
 */

require_once __DIR__ . '/config.php';

// Логирование входящих запросов
$input = file_get_contents('php://input');
error_log("Telegram webhook received: " . $input);

// Парсим JSON от Telegram
$update = json_decode($input, true);

if (!$update) {
    error_log("Invalid JSON from Telegram");
    http_response_code(200);
    exit;
}

try {
    // Обработка текстовых сообщений
    if (isset($update['message'])) {
        handleMessage($update['message']);
    }

    // Обработка callback query (inline кнопки)
    if (isset($update['callback_query'])) {
        handleCallbackQuery($update['callback_query']);
    }

} catch (Exception $e) {
    error_log("Telegram bot error: " . $e->getMessage());
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

    // Команды начинаются с /
    if (strpos($text, '/') === 0) {
        handleCommand($chatId, $telegramId, $username, $text);
        return;
    }

    // Обычные сообщения игнорируем
    sendTelegramMessage($chatId, "Используйте команды:\n/start - Регистрация\n/today - Заработок сегодня\n/week - Заработок за неделю");
}

/**
 * Обработка команд
 */
function handleCommand($chatId, $telegramId, $username, $text) {
    $parts = explode(' ', $text);
    $command = strtolower($parts[0]);

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

        case '/schedule':
            require_once __DIR__ . '/handlers/ScheduleCommand.php';
            handleScheduleCommand($chatId, $telegramId);
            break;

        case '/help':
            sendTelegramMessage($chatId,
                "📚 <b>Доступные команды:</b>\n\n" .
                "/start - Привязать аккаунт преподавателя\n" .
                "/today - Заработок за сегодня\n" .
                "/week - Заработок за неделю\n" .
                "/schedule - Расписание на сегодня\n" .
                "/help - Эта справка"
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
