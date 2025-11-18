-- Объединение дублирующихся шаблонов уроков
-- Дата: 2025-11-18
-- Цель: Объединить шаблоны, которые отличаются только тиром

-- =========================================
-- ВНИМАНИЕ: Выполняйте этот скрипт ТОЛЬКО ОДИН РАЗ!
-- =========================================

-- Шаг 1: Объединяем шаблоны для Вторника 18:00 (ID 14 и 16)
UPDATE lessons_template
SET students = '["Арина","Ваня"]',
    expected_students = 2,
    tier = 'C',
    updated_at = NOW()
WHERE id = 14;

-- Деактивируем дубликат
UPDATE lessons_template
SET active = 0,
    updated_at = NOW()
WHERE id = 16;

-- Шаг 2: Объединяем шаблоны для Субботы 15:00 (ID 15 и 17)
UPDATE lessons_template
SET students = '["Арина","Ваня"]',
    expected_students = 2,
    tier = 'C',
    updated_at = NOW()
WHERE id = 15;

-- Деактивируем дубликат
UPDATE lessons_template
SET active = 0,
    updated_at = NOW()
WHERE id = 17;

-- Шаг 3: Проверка результата
SELECT
    id,
    teacher_id,
    day_of_week,
    time_start,
    lesson_type,
    tier,
    students,
    expected_students,
    active
FROM lessons_template
WHERE id IN (14, 15, 16, 17)
ORDER BY id;

-- Ожидаемый результат:
-- ID 14: active=1, students=["Арина","Ваня"], expected_students=2
-- ID 15: active=1, students=["Арина","Ваня"], expected_students=2
-- ID 16: active=0 (деактивирован)
-- ID 17: active=0 (деактивирован)
