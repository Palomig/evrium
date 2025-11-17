-- Добавление полей tier, grades, students в таблицу lessons_template
-- Выполнять команды по одной в phpMyAdmin

-- Команда 1: Проверить существующую структуру таблицы
SHOW COLUMNS FROM lessons_template;

-- Команда 2: Добавить поле tier (уровень группы)
ALTER TABLE `lessons_template`
ADD COLUMN `tier` ENUM('S', 'A', 'B', 'C', 'D') DEFAULT 'C' AFTER `subject`;

-- Команда 3: Добавить поле grades (классы)
ALTER TABLE `lessons_template`
ADD COLUMN `grades` VARCHAR(50) NULL AFTER `tier`;

-- Команда 4: Добавить поле students (список учеников в JSON)
ALTER TABLE `lessons_template`
ADD COLUMN `students` TEXT NULL AFTER `grades`;

-- Команда 5: Проверка результата
SELECT id, teacher_id, day_of_week, room, subject, tier, grades, students
FROM lessons_template
LIMIT 5;
