<?php
/**
 * Команда /start - Регистрация преподавателя
 */

function handleStartCommand($chatId, $telegramId, $username) {
    // Проверяем, привязан ли уже этот Telegram ID
    $teacher = getTeacherByTelegramId($telegramId, $username);

    // Получаем клавиатуру меню
    $keyboard = function_exists('getMainMenuKeyboard') ? getMainMenuKeyboard() : null;

    if ($teacher) {
        sendTelegramMessage($chatId,
            "✅ Ваш аккаунт уже привязан!\n\n" .
            "👤 <b>Преподаватель:</b> {$teacher['name']}\n" .
            "📱 <b>Telegram:</b> @{$username}\n\n" .
            "Используйте кнопки меню ниже для навигации.",
            $keyboard
        );
        return;
    }

    // Если не привязан, показываем инструкцию
    sendTelegramMessage($chatId,
        "👋 <b>Добро пожаловать в Zarplata Bot!</b>\n\n" .
        "Для привязки аккаунта преподавателя, обратитесь к администратору.\n\n" .
        "Администратор должен:\n" .
        "1. Зайти в систему на эвриум.рф/zarplata\n" .
        "2. Открыть раздел \"Преподаватели\"\n" .
        "3. Найти ваш профиль и указать Telegram ID: <code>{$telegramId}</code>\n\n" .
        "После привязки вы сможете получать уведомления об уроках и видеть статистику заработка.",
        $keyboard
    );

    // Логируем попытку регистрации
    try {
        logAudit(
            'bot_registration_attempt',
            'teacher',
            null,
            null,
            ['telegram_id' => $telegramId, 'username' => $username],
            "Попытка регистрации в боте"
        );
    } catch (Exception $e) {
        // Игнорируем ошибки логирования
        error_log("Failed to log audit: " . $e->getMessage());
    }
}
