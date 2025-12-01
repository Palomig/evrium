-- Проверка наличия уроков в базе данных

-- 1. Проверка lessons_instance за последние 3 месяца
SELECT
    COUNT(*) as total_lessons,
    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
FROM lessons_instance
WHERE lesson_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH);

-- 2. Уроки за ноябрь-декабрь 2024
SELECT
    DATE_FORMAT(lesson_date, '%Y-%m') as month,
    COUNT(*) as lessons_count,
    status,
    COUNT(DISTINCT teacher_id) as teachers_count
FROM lessons_instance
WHERE lesson_date BETWEEN '2024-11-01' AND '2024-12-31'
GROUP BY month, status
ORDER BY month, status;

-- 3. Последние 10 уроков
SELECT
    id,
    lesson_date,
    time_start,
    subject,
    status,
    teacher_id,
    expected_students,
    actual_students
FROM lessons_instance
ORDER BY lesson_date DESC, time_start DESC
LIMIT 10;

-- 4. Проверка шаблонов
SELECT COUNT(*) as active_templates
FROM lessons_template
WHERE active = 1;
