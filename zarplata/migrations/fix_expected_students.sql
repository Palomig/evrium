-- Миграция: Исправление expected_students для групповых и индивидуальных занятий
-- Дата: 2025-11-18
-- Проблема: При добавлении учеников через форму, expected_students устанавливался = количеству учеников
-- Решение: Устанавливаем expected_students = 6 для групповых и 1 для индивидуальных

-- Обновляем все групповые шаблоны, где expected_students < 6
UPDATE lessons_template
SET expected_students = 6,
    updated_at = NOW()
WHERE lesson_type = 'group'
  AND expected_students < 6
  AND active = 1;

-- Обновляем все индивидуальные шаблоны, где expected_students != 1
UPDATE lessons_template
SET expected_students = 1,
    updated_at = NOW()
WHERE lesson_type = 'individual'
  AND expected_students != 1
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
WHERE active = 1
ORDER BY lesson_type, day_of_week, time_start;
