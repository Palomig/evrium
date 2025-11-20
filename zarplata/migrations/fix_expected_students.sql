-- Миграция: Исправление expected_students для групповых занятий
-- Дата: 2025-11-18
-- Проблема: При добавлении учеников через форму, expected_students устанавливался = количеству учеников
-- Решение: Устанавливаем expected_students = 6 для всех групповых занятий

-- Обновляем все групповые шаблоны, где expected_students < 6
UPDATE lessons_template
SET expected_students = 6,
    updated_at = NOW()
WHERE lesson_type = 'group'
  AND expected_students < 6
  AND active = 1;

-- Показываем результат
SELECT
    id,
    teacher_id,
    day_of_week,
    time_start,
    lesson_type,
    expected_students,
    JSON_LENGTH(students) as actual_students
FROM lessons_template
WHERE lesson_type = 'group' AND active = 1
ORDER BY day_of_week, time_start;
