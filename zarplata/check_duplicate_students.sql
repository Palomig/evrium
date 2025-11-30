-- Проверка дубликатов учеников в базе данных

-- 1. Найти всех учеников с одинаковыми именами
SELECT
    name,
    GROUP_CONCAT(CONCAT('ID:', id, ' класс:', class) ORDER BY class SEPARATOR ', ') as duplicates,
    COUNT(*) as count
FROM students
WHERE active = 1
GROUP BY name
HAVING COUNT(*) > 1
ORDER BY count DESC, name;

-- 2. Детали по проблемным ученикам
SELECT id, name, class, phone, parent_phone, created_at
FROM students
WHERE name IN ('Коля', 'Маша', 'Вика', 'Кирилл', 'Настя')
  AND active = 1
ORDER BY name, class;

-- 3. Найти полные дубликаты (одинаковые имя И класс)
SELECT
    name,
    class,
    GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') as student_ids,
    COUNT(*) as duplicate_count
FROM students
WHERE active = 1
GROUP BY name, class
HAVING COUNT(*) > 1
ORDER BY name, class;
