-- Простая миграция для ручного выполнения в phpMyAdmin
-- Скопируйте и выполните эти команды по одной

-- 1. Добавить столбец formula_id в таблицу teachers
ALTER TABLE `teachers`
ADD COLUMN `formula_id` INT NULL AFTER `email`;

-- 2. Создать внешний ключ
ALTER TABLE `teachers`
ADD CONSTRAINT `fk_teachers_formula`
    FOREIGN KEY (`formula_id`)
    REFERENCES `payment_formulas`(`id`)
    ON DELETE SET NULL;

-- 3. Создать индекс
ALTER TABLE `teachers`
ADD INDEX `idx_formula_id` (`formula_id`);

-- 4. Мигрировать данные: присвоить преподавателям их формулы из lessons_template
UPDATE teachers t
LEFT JOIN (
    SELECT teacher_id, formula_id, COUNT(*) as cnt
    FROM lessons_template
    WHERE formula_id IS NOT NULL
    GROUP BY teacher_id, formula_id
    ORDER BY cnt DESC
) lt ON t.id = lt.teacher_id
SET t.formula_id = lt.formula_id
WHERE lt.formula_id IS NOT NULL;

-- 5. Проверка: посмотреть результат
SELECT t.id, t.name, t.formula_id, pf.name as formula_name
FROM teachers t
LEFT JOIN payment_formulas pf ON t.formula_id = pf.id
WHERE t.active = 1;
