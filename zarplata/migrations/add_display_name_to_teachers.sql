-- Добавление display_name для преподавателей
-- Используется для краткого отображения в расписании

ALTER TABLE teachers
ADD COLUMN display_name VARCHAR(50) NULL AFTER name;

-- Обновляем существующих преподавателей (берём первое слово из имени)
UPDATE teachers
SET display_name = SUBSTRING_INDEX(name, ' ', 1)
WHERE display_name IS NULL;

-- Комментарий для понимания
-- display_name - короткое имя для отображения в расписании (например, "Иван" вместо "Иван Петрович Сидоров")
