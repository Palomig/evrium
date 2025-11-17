-- Добавление поля "кабинет" в таблицу lessons_template

-- 1. Добавить столбец room (номер кабинета)
ALTER TABLE `lessons_template`
ADD COLUMN `room` TINYINT(1) DEFAULT 1 AFTER `day_of_week`;

-- 2. Обновить существующие записи (установить кабинет 1 по умолчанию)
UPDATE `lessons_template`
SET `room` = 1
WHERE `room` IS NULL;

-- Проверка
SELECT id, teacher_id, day_of_week, room, time_start, subject FROM lessons_template LIMIT 10;
