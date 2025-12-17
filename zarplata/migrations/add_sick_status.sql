-- Миграция: Добавление статуса "болеет" для учеников
-- Дата: 2025-12-17
-- Описание: Добавляет поле is_sick в таблицу students и настройку admin_telegram_chat_id

-- 1. Добавить поле is_sick в таблицу students
ALTER TABLE students ADD COLUMN is_sick BOOLEAN DEFAULT 0 AFTER active;

-- 2. Добавить настройку admin_telegram_chat_id
-- chat_id администратора @hiallglhf = 704366908
INSERT INTO settings (setting_key, setting_value, description)
VALUES ('admin_telegram_chat_id', '704366908', 'Telegram chat_id администратора для уведомлений о болеющих учениках')
ON DUPLICATE KEY UPDATE setting_value = '704366908';

-- 3. Создать индекс для быстрого поиска болеющих учеников
CREATE INDEX idx_students_is_sick ON students(is_sick);
