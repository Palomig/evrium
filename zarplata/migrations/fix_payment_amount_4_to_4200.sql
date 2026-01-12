-- Исправление суммы платежа от Лейла Габиловна Т.
-- Было: 4 ₽ (неправильно распарсено из-за неразрывного пробела в "4 200 ₽")
-- Должно быть: 4200 ₽

-- Обновляем платёж по имени отправителя и неправильной сумме
UPDATE incoming_payments
SET amount = 4200
WHERE sender_name LIKE '%Лейла Габиловна%'
  AND amount = 4;

-- Проверяем результат
SELECT id, sender_name, amount, received_at, raw_notification
FROM incoming_payments
WHERE sender_name LIKE '%Лейла Габиловна%';
