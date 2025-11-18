-- Добавление ТОЛЬКО новых полей в таблицу students
-- Используйте эту миграцию, если получаете ошибки с DROP COLUMN
-- Дата: 2025-11-18

-- Добавляем все новые поля одной командой
ALTER TABLE `students`
ADD COLUMN `payment_type_group` ENUM('per_lesson', 'monthly') DEFAULT 'monthly' AFTER `lesson_type`,
ADD COLUMN `payment_type_individual` ENUM('per_lesson', 'monthly') DEFAULT 'per_lesson' AFTER `payment_type_group`,
ADD COLUMN `price_group` INT DEFAULT 5000 AFTER `payment_type_individual`,
ADD COLUMN `price_individual` INT DEFAULT 1500 AFTER `price_group`,
ADD COLUMN `schedule` JSON NULL AFTER `price_individual`,
ADD COLUMN `student_telegram` VARCHAR(100) NULL AFTER `phone`,
ADD COLUMN `student_whatsapp` VARCHAR(20) NULL AFTER `student_telegram`,
ADD COLUMN `parent_telegram` VARCHAR(100) NULL AFTER `parent_phone`,
ADD COLUMN `parent_whatsapp` VARCHAR(20) NULL AFTER `parent_telegram`,
ADD INDEX `idx_payment_type_group` (`payment_type_group`),
ADD INDEX `idx_payment_type_individual` (`payment_type_individual`);
