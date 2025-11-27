-- Миграция: Создание таблицы telegram_updates для защиты от дублей
-- Дата: 2025-11-27
-- Описание: Таблица для хранения обработанных update_id от Telegram
--           Защищает от повторной обработки одного и того же update

CREATE TABLE IF NOT EXISTS telegram_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    update_id BIGINT NOT NULL COMMENT 'ID обновления от Telegram',
    created_at DATETIME NOT NULL COMMENT 'Дата и время получения обновления',
    UNIQUE KEY idx_update_id (update_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='История обработанных обновлений от Telegram бота';

-- Индекс для очистки старых записей
CREATE INDEX idx_created_at ON telegram_updates(created_at);
