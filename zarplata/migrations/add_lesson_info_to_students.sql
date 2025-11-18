-- Миграция: Добавление информации об уроках к ученикам
-- Дата создания: 2025-11-18
-- Описание: Добавляем поля для хранения информации о занятиях ученика

-- Добавляем тип занятия (групповое/индивидуальное)
ALTER TABLE `students`
ADD COLUMN `lesson_type` ENUM('group', 'individual') DEFAULT 'group' AFTER `class`;

-- Добавляем цену за месяц (8 занятий)
ALTER TABLE `students`
ADD COLUMN `monthly_price` INT DEFAULT 5000 AFTER `lesson_type`,
ADD COLUMN `lesson_day` TINYINT NULL AFTER `monthly_price`,
ADD COLUMN `lesson_time` TIME NULL AFTER `lesson_day`;

-- Добавляем индекс для типа занятия
ALTER TABLE `students`
ADD INDEX `idx_lesson_type` (`lesson_type`),
ADD INDEX `idx_class` (`class`);

-- Комментарии к полям:
-- lesson_type: тип занятия (group = 5000₽/мес, individual = 1500₽/мес)
-- monthly_price: цена за месяц (8 занятий, 2 раза в неделю)
-- lesson_day: день недели (1=Пн, 7=Вс)
-- lesson_time: время занятия
