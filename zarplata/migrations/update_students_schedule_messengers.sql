-- Обновление таблицы students для новой логики расписания и мессенджеров
-- Дата: 2025-11-18

-- Удаляем старые поля расписания и цены
ALTER TABLE `students`
DROP COLUMN IF EXISTS `lesson_day`,
DROP COLUMN IF EXISTS `lesson_time`,
DROP COLUMN IF EXISTS `monthly_price`;

-- Добавляем новые поля для цен и типа оплаты
ALTER TABLE `students`
ADD COLUMN `payment_type_group` ENUM('per_lesson', 'monthly') DEFAULT 'monthly' AFTER `lesson_type`,
ADD COLUMN `payment_type_individual` ENUM('per_lesson', 'monthly') DEFAULT 'per_lesson' AFTER `payment_type_group`,
ADD COLUMN `price_group` INT DEFAULT 5000 AFTER `payment_type_individual`,
ADD COLUMN `price_individual` INT DEFAULT 1500 AFTER `price_group`;

-- Добавляем поле для расписания (JSON: {1: "14:00", 3: "15:30"})
ALTER TABLE `students`
ADD COLUMN `schedule` JSON NULL AFTER `price_individual`;

-- Добавляем поля для мессенджеров ученика
ALTER TABLE `students`
ADD COLUMN `student_telegram` VARCHAR(100) NULL AFTER `phone`,
ADD COLUMN `student_whatsapp` VARCHAR(20) NULL AFTER `student_telegram`;

-- Добавляем поля для мессенджеров родителя
ALTER TABLE `students`
ADD COLUMN `parent_telegram` VARCHAR(100) NULL AFTER `parent_phone`,
ADD COLUMN `parent_whatsapp` VARCHAR(20) NULL AFTER `parent_telegram`;

-- Удаляем поле email (не нужно)
ALTER TABLE `students`
DROP COLUMN IF EXISTS `email`;

-- Добавляем индексы
ALTER TABLE `students`
ADD INDEX `idx_payment_type_group` (`payment_type_group`),
ADD INDEX `idx_payment_type_individual` (`payment_type_individual`);
