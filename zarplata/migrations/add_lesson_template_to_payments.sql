-- ============================================================================
-- Миграция: Добавление lesson_template_id в таблицу payments
-- Дата: 2025-11-17
-- Описание: Добавляет поле lesson_template_id для привязки выплат к шаблонам
--           уроков, так как lessons_instance не используются
-- ============================================================================

-- Добавить поле lesson_template_id в таблицу payments
ALTER TABLE `payments`
ADD COLUMN `lesson_template_id` INT NULL AFTER `lesson_instance_id`,
ADD CONSTRAINT `fk_payments_lesson_template`
    FOREIGN KEY (`lesson_template_id`)
    REFERENCES `lessons_template`(`id`)
    ON DELETE SET NULL;

-- Создать индекс для быстрого поиска
ALTER TABLE `payments`
ADD INDEX `idx_lesson_template_id` (`lesson_template_id`);

-- Логирование применения миграции
INSERT INTO audit_log (action_type, entity_type, notes, created_at)
VALUES ('migration_applied', 'payments', 'Применена миграция add_lesson_template_to_payments.sql', NOW());
