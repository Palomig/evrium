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
