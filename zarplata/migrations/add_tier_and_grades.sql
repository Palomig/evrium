-- Добавление тира и классов в таблицу lessons_template

-- 1. Добавить столбец tier (уровень группы)
ALTER TABLE `lessons_template`
ADD COLUMN `tier` ENUM('S', 'A', 'B', 'C', 'D') DEFAULT 'C' AFTER `subject`;

-- 2. Добавить столбец grades (классы, через запятую)
ALTER TABLE `lessons_template`
ADD COLUMN `grades` VARCHAR(50) NULL AFTER `tier`;

-- 3. Добавить столбец students (список учеников, JSON массив)
ALTER TABLE `lessons_template`
ADD COLUMN `students` TEXT NULL AFTER `grades`;

-- Проверка
SELECT id, subject, tier, grades, students FROM lessons_template LIMIT 5;
