-- ============================================================================
-- БАЗА ДАННЫХ: Система учёта зарплаты преподавателей
-- Версия: 1.0
-- Дата: 2025-11-15
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+03:00";

-- ============================================================================
-- 1. ПОЛЬЗОВАТЕЛИ И ПРЕПОДАВАТЕЛИ
-- ============================================================================

-- Таблица администраторов (владельцев системы)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100),
    `role` ENUM('admin', 'owner') DEFAULT 'admin',
    `active` BOOLEAN DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`),
    INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица преподавателей
CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `telegram_id` BIGINT UNIQUE NULL,
    `telegram_username` VARCHAR(50),
    `phone` VARCHAR(20),
    `email` VARCHAR(100),
    `active` BOOLEAN DEFAULT 1,
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_telegram_id` (`telegram_id`),
    INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. УЧЕНИКИ
-- ============================================================================

CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20),
    `parent_phone` VARCHAR(20),
    `email` VARCHAR(100),
    `class` INT,
    `notes` TEXT,
    `active` BOOLEAN DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. ФОРМУЛЫ ОПЛАТЫ
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payment_formulas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('min_plus_per', 'fixed', 'expression') NOT NULL,
    `description` TEXT,

    -- Для типа 'min_plus_per'
    `min_payment` INT DEFAULT 0,
    `per_student` INT DEFAULT 0,
    `threshold` INT DEFAULT 2, -- С какого ученика начинается доплата

    -- Для типа 'fixed'
    `fixed_amount` INT DEFAULT 0,

    -- Для типа 'expression'
    `expression` TEXT, -- Например: "max(500, N * 150)"

    `active` BOOLEAN DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. РАСПИСАНИЕ (ШАБЛОНЫ)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `lessons_template` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `day_of_week` TINYINT NOT NULL, -- 1=Пн, 7=Вс
    `time_start` TIME NOT NULL,
    `time_end` TIME NOT NULL,
    `lesson_type` ENUM('group', 'individual') DEFAULT 'group',
    `subject` VARCHAR(100),
    `expected_students` INT DEFAULT 1,
    `formula_id` INT,
    `active` BOOLEAN DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`formula_id`) REFERENCES `payment_formulas`(`id`) ON DELETE SET NULL,
    INDEX `idx_teacher_day` (`teacher_id`, `day_of_week`),
    INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. ИНСТАНСЫ УРОКОВ (КОНКРЕТНЫЕ ЗАНЯТИЯ)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `lessons_instance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_id` INT NULL, -- Связь с шаблоном (NULL = разовый урок)
    `teacher_id` INT NOT NULL,
    `substitute_teacher_id` INT NULL, -- Замещающий преподаватель
    `lesson_date` DATE NOT NULL,
    `time_start` TIME NOT NULL,
    `time_end` TIME NOT NULL,
    `lesson_type` ENUM('group', 'individual') DEFAULT 'group',
    `subject` VARCHAR(100),
    `expected_students` INT DEFAULT 1,
    `actual_students` INT DEFAULT 0,
    `formula_id` INT,
    `status` ENUM('scheduled', 'completed', 'cancelled', 'rescheduled') DEFAULT 'scheduled',
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`template_id`) REFERENCES `lessons_template`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`substitute_teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`formula_id`) REFERENCES `payment_formulas`(`id`) ON DELETE SET NULL,
    INDEX `idx_date_teacher` (`lesson_date`, `teacher_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. СВЯЗЬ УРОКОВ И УЧЕНИКОВ
-- ============================================================================

CREATE TABLE IF NOT EXISTS `lesson_students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lesson_instance_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `enrolled` BOOLEAN DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`lesson_instance_id`) REFERENCES `lessons_instance`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_lesson_student` (`lesson_instance_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. ЖУРНАЛ ПОСЕЩАЕМОСТИ
-- ============================================================================

CREATE TABLE IF NOT EXISTS `attendance_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lesson_instance_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `attended` BOOLEAN DEFAULT 1,
    `marked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `marked_by` VARCHAR(50) DEFAULT 'telegram_bot',
    `notes` TEXT,
    FOREIGN KEY (`lesson_instance_id`) REFERENCES `lessons_instance`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    INDEX `idx_lesson_student` (`lesson_instance_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. НАЧИСЛЕНИЯ ЗАРПЛАТЫ
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `lesson_instance_id` INT NULL, -- NULL для разовых начислений
    `amount` INT NOT NULL, -- В рублях, без копеек
    `payment_type` ENUM('lesson', 'bonus', 'penalty', 'adjustment') DEFAULT 'lesson',
    `calculation_method` TEXT, -- Описание как рассчитано
    `period_start` DATE,
    `period_end` DATE,
    `status` ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
    `paid_at` DATETIME NULL,
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`lesson_instance_id`) REFERENCES `lessons_instance`(`id`) ON DELETE SET NULL,
    INDEX `idx_teacher_status` (`teacher_id`, `status`),
    INDEX `idx_period` (`period_start`, `period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. ЦИКЛЫ ВЫПЛАТ
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payout_cycles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `total_amount` INT DEFAULT 0,
    `owner_share` INT DEFAULT 0, -- Доля владельца
    `status` ENUM('draft', 'approved', 'paid') DEFAULT 'draft',
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_period` (`period_start`, `period_end`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Связь выплат с циклами
CREATE TABLE IF NOT EXISTS `payout_cycle_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cycle_id` INT NOT NULL,
    `payment_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cycle_id`) REFERENCES `payout_cycles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_cycle_payment` (`cycle_id`, `payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. ЖУРНАЛ АУДИТА (Audit Log)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `action_type` VARCHAR(50) NOT NULL, -- 'attendance_marked', 'payment_calculated', 'lesson_rescheduled', etc.
    `entity_type` VARCHAR(50), -- 'lesson', 'payment', 'teacher', etc.
    `entity_id` INT,
    `user_id` INT NULL,
    `teacher_id` INT NULL,
    `telegram_id` BIGINT NULL,
    `old_value` TEXT,
    `new_value` TEXT,
    `notes` TEXT,
    `ip_address` VARCHAR(45),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL,
    INDEX `idx_action_type` (`action_type`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. НАСТРОЙКИ СИСТЕМЫ
-- ============================================================================

CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT,
    `description` TEXT,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 12. TELEGRAM BOT СОСТОЯНИЯ
-- ============================================================================

CREATE TABLE IF NOT EXISTS `bot_states` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `telegram_id` BIGINT NOT NULL,
    `state` VARCHAR(50), -- 'waiting_attendance', 'selecting_absent', etc.
    `context_data` TEXT, -- JSON с дополнительными данными
    `expires_at` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_telegram_id` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 13. ПРЕДСТАВЛЕНИЯ (VIEWS)
-- ============================================================================

-- Статистика по преподавателям
CREATE OR REPLACE VIEW `teacher_stats` AS
SELECT
    t.id AS teacher_id,
    t.name AS teacher_name,
    COUNT(DISTINCT li.id) AS total_lessons,
    COUNT(DISTINCT CASE WHEN li.status = 'completed' THEN li.id END) AS completed_lessons,
    COALESCE(SUM(CASE WHEN p.status != 'cancelled' THEN p.amount ELSE 0 END), 0) AS total_earned,
    COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END), 0) AS total_paid,
    COALESCE(SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END), 0) AS pending_amount
FROM teachers t
LEFT JOIN lessons_instance li ON t.id = li.teacher_id OR t.id = li.substitute_teacher_id
LEFT JOIN payments p ON t.id = p.teacher_id
WHERE t.active = 1
GROUP BY t.id;

-- Статистика по урокам за период
CREATE OR REPLACE VIEW `lessons_stats` AS
SELECT
    li.id AS lesson_id,
    li.lesson_date,
    t.name AS teacher_name,
    li.lesson_type,
    li.expected_students,
    li.actual_students,
    li.status,
    COALESCE(p.amount, 0) AS payment_amount,
    p.status AS payment_status
FROM lessons_instance li
LEFT JOIN teachers t ON li.teacher_id = t.id
LEFT JOIN payments p ON li.id = p.lesson_instance_id
ORDER BY li.lesson_date DESC, li.time_start ASC;

-- ============================================================================
-- 14. ТРИГГЕРЫ
-- ============================================================================

DELIMITER //

-- Автоматический расчёт зарплаты при завершении урока
-- ✅ ОБНОВЛЕНО 2025-11-17: Исправлена формула min_plus_per, добавлен fallback на formula_id преподавателя
CREATE TRIGGER `calculate_payment_after_lesson_complete`
AFTER UPDATE ON `lessons_instance`
FOR EACH ROW
BEGIN
    DECLARE calculated_amount INT DEFAULT 0;
    DECLARE formula_type VARCHAR(20);
    DECLARE min_pay INT;
    DECLARE per_stud INT;
    DECLARE threshold_val INT;
    DECLARE fixed_amt INT;
    DECLARE expr TEXT;
    DECLARE teacher_for_payment INT;
    DECLARE formula_to_use INT;

    -- Проверяем, что статус изменился на 'completed'
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN

        -- Определяем кому платить (замещающему или основному)
        SET teacher_for_payment = IFNULL(NEW.substitute_teacher_id, NEW.teacher_id);

        -- Определяем какую формулу использовать:
        -- 1. Формула из урока (приоритет)
        -- 2. Формула из преподавателя (fallback)
        SET formula_to_use = NEW.formula_id;

        IF formula_to_use IS NULL THEN
            -- Берём формулу из профиля преподавателя
            SELECT formula_id INTO formula_to_use
            FROM teachers
            WHERE id = teacher_for_payment AND active = 1;
        END IF;

        -- Получаем формулу оплаты
        IF formula_to_use IS NOT NULL THEN
            SELECT type, min_payment, per_student, threshold, fixed_amount, expression
            INTO formula_type, min_pay, per_stud, threshold_val, fixed_amt, expr
            FROM payment_formulas
            WHERE id = formula_to_use AND active = 1;

            -- Рассчитываем сумму в зависимости от типа формулы
            IF formula_type = 'min_plus_per' THEN
                -- ✅ ИСПРАВЛЕНО: убрали +1 и упростили логику
                -- Формула: базовая + (студентов сверх порога * доплата)
                -- Пример: min=500, per=150, threshold=2, students=5
                -- Результат: 500 + ((5-2) * 150) = 500 + 450 = 950₽
                SET calculated_amount = min_pay + (GREATEST(0, NEW.actual_students - threshold_val) * per_stud);

            ELSEIF formula_type = 'fixed' THEN
                -- Фиксированная сумма независимо от количества студентов
                SET calculated_amount = fixed_amt;

            ELSEIF formula_type = 'expression' THEN
                -- ✅ УЛУЧШЕНО: Базовая поддержка простых выражений
                -- Для сложных выражений используется fallback расчёт
                IF expr LIKE 'N %' OR expr LIKE '% N %' THEN
                    SET calculated_amount = NEW.actual_students * IFNULL(per_stud, 100);
                ELSE
                    SET calculated_amount = IFNULL(min_pay, 0) + (NEW.actual_students * IFNULL(per_stud, 0));
                END IF;
            END IF;

            -- Создаём запись о начислении
            INSERT INTO payments (
                teacher_id,
                lesson_instance_id,
                amount,
                payment_type,
                calculation_method,
                status,
                created_at
            ) VALUES (
                teacher_for_payment,
                NEW.id,
                calculated_amount,
                'lesson',
                CONCAT('Formula: ', formula_type, ', Students: ', NEW.actual_students, ', Formula ID: ', formula_to_use),
                'pending',
                NOW()
            );
        ELSE
            -- Нет формулы - логируем пропуск
            INSERT INTO audit_log (
                action_type,
                entity_type,
                entity_id,
                notes,
                created_at
            ) VALUES (
                'payment_skipped',
                'lesson',
                NEW.id,
                'Урок завершён без формулы оплаты',
                NOW()
            );
        END IF;
    END IF;
END//

-- Логирование изменений в attendance_log
CREATE TRIGGER `audit_attendance_log`
AFTER INSERT ON `attendance_log`
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (
        action_type,
        entity_type,
        entity_id,
        new_value,
        notes
    ) VALUES (
        'attendance_marked',
        'lesson',
        NEW.lesson_instance_id,
        JSON_OBJECT('student_id', NEW.student_id, 'attended', NEW.attended),
        NEW.notes
    );
END//

DELIMITER ;

-- ============================================================================
-- 15. НАЧАЛЬНЫЕ ДАННЫЕ
-- ============================================================================

-- Администратор по умолчанию (логин: admin, пароль: admin123)
INSERT INTO `users` (`username`, `password_hash`, `name`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Администратор', 'owner');

-- Настройки по умолчанию
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('bot_token', '', 'Telegram Bot API Token'),
('bot_check_interval', '5', 'Интервал проверки уроков (в минутах)'),
('attendance_delay', '15', 'Задержка перед опросом посещаемости (в минутах)'),
('owner_share_percent', '30', 'Процент владельца от выручки'),
('currency', 'RUB', 'Валюта системы'),
('timezone', 'Europe/Moscow', 'Часовой пояс');

-- Примеры формул оплаты
INSERT INTO `payment_formulas` (`name`, `type`, `description`, `min_payment`, `per_student`, `threshold`) VALUES
('Стандартная групповая', 'min_plus_per', 'Минимум 500₽ + 150₽ за каждого ученика начиная со второго', 500, 150, 2),
('Индивидуальное занятие', 'fixed', 'Фиксированная ставка за индивидуальное занятие', 0, 0, 1);

UPDATE `payment_formulas` SET `fixed_amount` = 800 WHERE `name` = 'Индивидуальное занятие';

-- ============================================================================
-- КОНЕЦ СХЕМЫ
-- ============================================================================
