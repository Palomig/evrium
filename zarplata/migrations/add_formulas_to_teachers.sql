-- Миграция: Добавление двух полей формул к преподавателям
-- Дата создания: 2025-11-18
-- Описание: Добавляем два поля formula_id - для групповых и индивидуальных уроков

-- Добавляем поле formula_id_group (формула для групповых уроков)
ALTER TABLE `teachers`
ADD COLUMN `formula_id_group` INT NULL AFTER `email`,
ADD CONSTRAINT `fk_teachers_formula_group`
    FOREIGN KEY (`formula_id_group`)
    REFERENCES `payment_formulas`(`id`)
    ON DELETE SET NULL;

-- Добавляем поле formula_id_individual (формула для индивидуальных уроков)
ALTER TABLE `teachers`
ADD COLUMN `formula_id_individual` INT NULL AFTER `formula_id_group`,
ADD CONSTRAINT `fk_teachers_formula_individual`
    FOREIGN KEY (`formula_id_individual`)
    REFERENCES `payment_formulas`(`id`)
    ON DELETE SET NULL;

-- Добавляем индексы для оптимизации запросов
ALTER TABLE `teachers`
ADD INDEX `idx_formula_group` (`formula_id_group`),
ADD INDEX `idx_formula_individual` (`formula_id_individual`);
