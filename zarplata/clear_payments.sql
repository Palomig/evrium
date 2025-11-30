-- Очистка всех выплат из базы данных
-- ВНИМАНИЕ: Это действие необратимо!

-- Удаляем все записи из таблицы payments
DELETE FROM payments;

-- Сбрасываем AUTO_INCREMENT для таблицы payments
ALTER TABLE payments AUTO_INCREMENT = 1;

-- Удаляем записи из audit_log связанные с выплатами (опционально)
DELETE FROM audit_log WHERE entity_type = 'payment';

SELECT 'Все выплаты успешно удалены!' as status;
