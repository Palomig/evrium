-- Миграция: добавление типа 'payout' в ENUM payment_type
-- Дата: 2025-12-24
-- Описание: Добавляет новый тип выплаты 'payout' для произвольных выплат (авансов)

-- Изменяем ENUM, добавляя 'payout'
ALTER TABLE payments
MODIFY COLUMN payment_type ENUM('lesson', 'bonus', 'penalty', 'adjustment', 'payout')
DEFAULT 'lesson';

-- Обновляем существующие записи с пустым payment_type
UPDATE payments SET payment_type = 'payout' WHERE payment_type = '' OR payment_type IS NULL;
