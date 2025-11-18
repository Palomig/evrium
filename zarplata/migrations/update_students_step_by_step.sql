-- Обновление таблицы students для новой логики расписания и мессенджеров
-- Дата: 2025-11-18
-- ПРИМЕНЯТЬ ПО ШАГАМ (скопировать каждый блок отдельно в phpMyAdmin)

-- ============================================================
-- ШАГ 1: Удаление старых полей (если есть)
-- Скопируйте эти 3 команды вместе, если получите ошибку - значит поля уже удалены
-- ============================================================
ALTER TABLE `students` DROP COLUMN `lesson_day`;
ALTER TABLE `students` DROP COLUMN `lesson_time`;
ALTER TABLE `students` DROP COLUMN `monthly_price`;

-- ============================================================
-- ШАГ 2: Добавление новых полей для цен и типа оплаты
-- Скопируйте команду целиком
-- ============================================================
ALTER TABLE `students`
ADD COLUMN `payment_type_group` ENUM('per_lesson', 'monthly') DEFAULT 'monthly' AFTER `lesson_type`,
ADD COLUMN `payment_type_individual` ENUM('per_lesson', 'monthly') DEFAULT 'per_lesson' AFTER `payment_type_group`,
ADD COLUMN `price_group` INT DEFAULT 5000 AFTER `payment_type_individual`,
ADD COLUMN `price_individual` INT DEFAULT 1500 AFTER `price_group`;

-- ============================================================
-- ШАГ 3: Добавление поля для расписания (JSON)
-- Скопируйте команду целиком
-- ============================================================
ALTER TABLE `students`
ADD COLUMN `schedule` JSON NULL AFTER `price_individual`;

-- ============================================================
-- ШАГ 4: Добавление полей для мессенджеров ученика
-- Скопируйте команду целиком
-- ============================================================
ALTER TABLE `students`
ADD COLUMN `student_telegram` VARCHAR(100) NULL AFTER `phone`,
ADD COLUMN `student_whatsapp` VARCHAR(20) NULL AFTER `student_telegram`;

-- ============================================================
-- ШАГ 5: Добавление полей для мессенджеров родителя
-- Скопируйте команду целиком
-- ============================================================
ALTER TABLE `students`
ADD COLUMN `parent_telegram` VARCHAR(100) NULL AFTER `parent_phone`,
ADD COLUMN `parent_whatsapp` VARCHAR(20) NULL AFTER `parent_telegram`;

-- ============================================================
-- ШАГ 6: Удаление поля email (если есть)
-- Скопируйте команду целиком
-- ============================================================
ALTER TABLE `students` DROP COLUMN `email`;

-- ============================================================
-- ШАГ 7: Добавление индексов
-- Скопируйте команду целиком
-- ============================================================
ALTER TABLE `students`
ADD INDEX `idx_payment_type_group` (`payment_type_group`),
ADD INDEX `idx_payment_type_individual` (`payment_type_individual`);

-- ============================================================
-- ГОТОВО! Проверьте структуру таблицы students
-- ============================================================
