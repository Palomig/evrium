-- SQL скрипт для исправления проблемных шаблонов
-- ВНИМАНИЕ: Проверьте каждый шаблон перед выполнением!

-- Показать все проблемные шаблоны
SELECT
    id,
    teacher_id,
    day_of_week,
    time_start,
    subject,
    students
FROM lessons_template
WHERE id IN (47, 49, 50, 51, 52, 53, 56, 57, 62, 63, 68, 69, 72, 73)
ORDER BY id;

-- ПРИМЕРЫ исправления (РАСКОММЕНТИРУЙТЕ И АДАПТИРУЙТЕ):

-- Если в шаблоне 47 должен быть Коля 7 класс:
-- UPDATE lessons_template
-- SET students = JSON_ARRAY('Лёша (6 кл.)', 'Лера (7 кл.)', 'Коля (7 кл.)', 'Антоний (6 кл.)')
-- WHERE id = 47;

-- Если в шаблоне 47 должен быть Коля 2 класс:
-- UPDATE lessons_template
-- SET students = JSON_ARRAY('Лёша (6 кл.)', 'Лера (7 кл.)', 'Коля (2 кл.)', 'Антоний (6 кл.)')
-- WHERE id = 47;

-- Для шаблонов с Машей (выберите правильный класс):
-- UPDATE lessons_template SET students = JSON_REPLACE(students, '$[N]', 'Маша (5 кл.)') WHERE id = 49;
-- или
-- UPDATE lessons_template SET students = JSON_REPLACE(students, '$[N]', 'Маша (6 кл.)') WHERE id = 49;

-- Для шаблонов с Викой (проверьте, это одна Вика или две разные):
-- UPDATE lessons_template SET students = JSON_ARRAY('Вика (6 кл.)') WHERE id = 56;

-- Для шаблонов с Кириллом:
-- UPDATE lessons_template SET students = JSON_REPLACE(students, '$[N]', 'Кирилл (6 кл.)') WHERE id = 49;
-- или
-- UPDATE lessons_template SET students = JSON_REPLACE(students, '$[N]', 'Кирилл (9 кл.)') WHERE id = 49;

-- Для шаблонов с Настей:
-- UPDATE lessons_template SET students = JSON_REPLACE(students, '$[N]', 'Настя (8 кл.)') WHERE id = 68;
-- или
-- UPDATE lessons_template SET students = JSON_REPLACE(students, '$[N]', 'Настя (9 кл.)') WHERE id = 68;
