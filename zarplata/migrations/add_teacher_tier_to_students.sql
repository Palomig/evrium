-- Добавление привязки к преподавателю и тира для учеников
-- Дата: 2025-11-18

-- Добавляем поле teacher_id (обязательное)
ALTER TABLE `students`
ADD COLUMN `teacher_id` INT NOT NULL AFTER `id`,
ADD CONSTRAINT `fk_students_teacher`
    FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers`(`id`)
    ON DELETE CASCADE;

-- Добавляем поле tier (уровень ученика)
ALTER TABLE `students`
ADD COLUMN `tier` ENUM('S', 'A', 'B', 'C', 'D') DEFAULT 'C' AFTER `class`;

-- Добавляем мессенджеры ученика
ALTER TABLE `students`
ADD COLUMN `student_telegram` VARCHAR(50) NULL AFTER `tier`,
ADD COLUMN `student_whatsapp` VARCHAR(20) NULL AFTER `student_telegram`;

-- Удаляем телефон ученика (не нужен)
ALTER TABLE `students`
DROP COLUMN `phone`;

-- Переименовываем parent_phone в parent_name
ALTER TABLE `students`
CHANGE COLUMN `parent_phone` `parent_name` VARCHAR(100) NULL;

-- Добавляем индекс на teacher_id
ALTER TABLE `students`
ADD INDEX `idx_teacher_id` (`teacher_id`);

-- Добавляем индекс на tier
ALTER TABLE `students`
ADD INDEX `idx_tier` (`tier`);
