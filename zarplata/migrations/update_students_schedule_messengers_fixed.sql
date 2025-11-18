-- Обновление таблицы students для новой логики расписания и мессенджеров
-- Дата: 2025-11-18
-- СОВМЕСТИМО с MySQL 5.7+

-- Шаг 1: Удаляем старые поля расписания и цены (если они существуют)
-- Проверка существования полей перед удалением не требуется в MySQL 5.7

-- Пробуем удалить старые поля (если их нет, будет ошибка, но можно игнорировать)
ALTER TABLE `students`
DROP COLUMN `lesson_day`,
DROP COLUMN `lesson_time`,
DROP COLUMN `monthly_price`;

-- Шаг 2: Добавляем новые поля для цен и типа оплаты
ALTER TABLE `students`
ADD COLUMN `payment_type_group` ENUM('per_lesson', 'monthly') DEFAULT 'monthly' AFTER `lesson_type`,
ADD COLUMN `payment_type_individual` ENUM('per_lesson', 'monthly') DEFAULT 'per_lesson' AFTER `payment_type_group`,
ADD COLUMN `price_group` INT DEFAULT 5000 AFTER `payment_type_individual`,
ADD COLUMN `price_individual` INT DEFAULT 1500 AFTER `price_group`;

-- Шаг 3: Добавляем поле для расписания (JSON: {1: "14:00", 3: "15:30"})
ALTER TABLE `students`
ADD COLUMN `schedule` JSON NULL AFTER `price_individual`;

-- Шаг 4: Добавляем поля для мессенджеров ученика
ALTER TABLE `students`
ADD COLUMN `student_telegram` VARCHAR(100) NULL AFTER `phone`,
ADD COLUMN `student_whatsapp` VARCHAR(20) NULL AFTER `student_telegram`;

-- Шаг 5: Добавляем поля для мессенджеров родителя
ALTER TABLE `students`
ADD COLUMN `parent_telegram` VARCHAR(100) NULL AFTER `parent_phone`,
ADD COLUMN `parent_whatsapp` VARCHAR(20) NULL AFTER `parent_telegram`;

-- Шаг 6: Удаляем поле email (не нужно)
ALTER TABLE `students`
DROP COLUMN `email`;

-- Шаг 7: Добавляем индексы
ALTER TABLE `students`
ADD INDEX `idx_payment_type_group` (`payment_type_group`),
ADD INDEX `idx_payment_type_individual` (`payment_type_individual`);
