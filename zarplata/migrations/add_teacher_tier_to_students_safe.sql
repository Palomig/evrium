-- Безопасная версия миграции: добавление привязки к преподавателю и тира для учеников
-- Дата: 2025-11-18
-- Версия для баз данных с существующими записями

-- Шаг 1: Добавляем поле teacher_id как NULL (временно)
ALTER TABLE `students`
ADD COLUMN `teacher_id` INT NULL AFTER `id`;

-- Шаг 2: Заполняем teacher_id для существующих записей
-- (используем первого активного преподавателя)
UPDATE `students`
SET `teacher_id` = (SELECT `id` FROM `teachers` WHERE `active` = 1 LIMIT 1)
WHERE `teacher_id` IS NULL;

-- Шаг 3: Делаем поле обязательным
ALTER TABLE `students`
MODIFY COLUMN `teacher_id` INT NOT NULL;

-- Шаг 4: Добавляем внешний ключ
ALTER TABLE `students`
ADD CONSTRAINT `fk_students_teacher`
    FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers`(`id`)
    ON DELETE CASCADE;

-- Шаг 5: Добавляем поле tier (уровень ученика)
ALTER TABLE `students`
ADD COLUMN `tier` ENUM('S', 'A', 'B', 'C', 'D') DEFAULT 'C' AFTER `class`;

-- Шаг 6: Удаляем телефон ученика (если существует)
-- Проверяем наличие колонки перед удалением
SET @dbname = DATABASE();
SET @tablename = 'students';
SET @columnname = 'phone';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  'ALTER TABLE students DROP COLUMN phone;',
  'SELECT 1;'
));
PREPARE alterIfExists FROM @preparedStatement;
EXECUTE alterIfExists;
DEALLOCATE PREPARE alterIfExists;

-- Шаг 7: Переименовываем parent_phone в parent_name (если существует parent_phone)
SET @columnname = 'parent_phone';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  'ALTER TABLE students CHANGE COLUMN `parent_phone` `parent_name` VARCHAR(100) NULL;',
  'SELECT 1;'
));
PREPARE alterIfExists FROM @preparedStatement;
EXECUTE alterIfExists;
DEALLOCATE PREPARE alterIfExists;

-- Шаг 8: Добавляем индексы
ALTER TABLE `students`
ADD INDEX `idx_teacher_id` (`teacher_id`),
ADD INDEX `idx_tier` (`tier`);
