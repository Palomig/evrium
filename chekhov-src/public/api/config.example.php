<?php
/**
 * Конфигурация приёма заявок.
 * Скопируйте этот файл в config.php и заполните свои значения.
 *
 * Файл необязателен: если его нет, токен бота и получатель уведомлений
 * берутся из таблицы settings базы zarplata (ключи bot_token, leads_chat_id,
 * admin_telegram_chat_id) — там же живут и настройки капчи.
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
    // Рядом с leads-YYYY-MM.jsonl пишется blocked-YYYY-MM.jsonl —
    // отбитые попытки с причиной, чтобы ловить ложные срабатывания.
    // 'log_dir' => '/абсолютный/путь/к/каталогу',

    // --- Yandex SmartCaptcha -------------------------------------------------
    // Выключена. Чтобы включить: завести капчу в Yandex Cloud (тип «невидимая»),
    // вписать оба ключа и поставить флаг в '1'. Пересобирать сайт не нужно —
    // фронт спрашивает состояние у api/form-token.php при загрузке страницы.
    //
    // То же самое можно сделать без этого файла — строками в таблице settings
    // базы zarplata: smartcaptcha_enabled / smartcaptcha_client_key /
    // smartcaptcha_server_key.
    //
    // Капча включится только когда флаг взведён И оба ключа на месте.
    'smartcaptcha_enabled' => '0',
    'smartcaptcha_client_key' => null,  // публичный ключ (уходит в браузер)
    'smartcaptcha_server_key' => null,  // серверный ключ, держать в секрете
];
