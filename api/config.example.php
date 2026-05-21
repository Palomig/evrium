<?php
/**
 * Конфигурация приёма заявок.
 * Скопируйте этот файл в config.php и заполните свои значения.
 * config.php НЕ публикуется в git (см. .gitignore рядом).
 */

return [
    // Telegram-бот для уведомлений.
    // Создайте бота через @BotFather, токен сюда:
    'telegram_token' => null,
    // Узнать chat_id: напишите @userinfobot или GetIDs Bot, ID сюда:
    'telegram_chat_id' => null,

    // Дубль уведомлений на e-mail (опционально).
    'email_to' => null,

    // Куда сохранять JSONL-лог заявок (по умолчанию — storage/leads вне public).
    // 'log_dir' => '/абсолютный/путь/к/каталогу',
];
