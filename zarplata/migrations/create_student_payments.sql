-- Миграция: создание таблицы student_payments
-- Дата: 2025-12-24
-- Описание: Таблица для учёта оплаты занятий от учеников/родителей

CREATE TABLE IF NOT EXISTS student_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    month VARCHAR(7) NOT NULL,                          -- Формат: '2025-01' (YYYY-MM)
    amount INT NOT NULL,                                 -- Сумма оплаты в рублях
    payment_method ENUM('cash', 'card') DEFAULT 'card', -- Способ оплаты
    lessons_count INT DEFAULT 0,                         -- Кол-во оплаченных уроков (для поурочной оплаты)
    paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,         -- Дата/время оплаты
    notes TEXT,                                          -- Примечания
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_month (student_id, month),
    INDEX idx_month (month),
    INDEX idx_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Добавляем настройки по умолчанию для напоминания об оплате
INSERT INTO settings (setting_key, setting_value, description) VALUES
('payment_reminder_template', 'Здравствуйте! Напоминаем об оплате занятий за {month}.\n\nУченик: {student_name}\nСумма: {amount} ₽\n\nСпособ оплаты: перевод на карту {card_number}', 'Шаблон текста напоминания об оплате'),
('payment_card_number', '', 'Номер карты для перевода оплаты')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
