-- ============================================================================
-- Миграция: Привязка формулы оплаты к преподавателю
-- Дата: 2025-11-16
-- Описание: Добавляет поле formula_id в таблицу teachers для автоматической
--           подстановки формулы оплаты при создании уроков
-- ============================================================================

-- Добавить поле formula_id в таблицу teachers
ALTER TABLE `teachers`
ADD COLUMN `formula_id` INT NULL AFTER `email`,
ADD CONSTRAINT `fk_teachers_formula`
    FOREIGN KEY (`formula_id`)
    REFERENCES `payment_formulas`(`id`)
    ON DELETE SET NULL;

-- Создать индекс для быстрого поиска
ALTER TABLE `teachers`
ADD INDEX `idx_formula_id` (`formula_id`);

-- Мигрировать существующие данные: присвоить преподавателям наиболее часто используемые формулы
UPDATE teachers t
LEFT JOIN (
    SELECT teacher_id, formula_id, COUNT(*) as cnt
    FROM lessons_template
    WHERE formula_id IS NOT NULL
    GROUP BY teacher_id, formula_id
    ORDER BY teacher_id, cnt DESC
) lt ON t.id = lt.teacher_id
SET t.formula_id = lt.formula_id
WHERE lt.formula_id IS NOT NULL;

-- Если у преподавателя нет формулы, попробовать взять из любого его урока
UPDATE teachers t
LEFT JOIN (
    SELECT teacher_id, formula_id
    FROM lessons_template
    WHERE formula_id IS NOT NULL
    GROUP BY teacher_id
    LIMIT 1
) lt ON t.id = lt.teacher_id
SET t.formula_id = lt.formula_id
WHERE t.formula_id IS NULL AND lt.formula_id IS NOT NULL;
