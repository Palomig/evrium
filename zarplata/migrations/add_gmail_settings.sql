-- Добавление настроек Gmail для парсинга писем
-- Выполнить в phpMyAdmin

INSERT INTO settings (setting_key, setting_value, description)
VALUES ('gmail_user', 'palomigdota@gmail.com', 'Gmail адрес для проверки уведомлений')
ON DUPLICATE KEY UPDATE setting_value = 'palomigdota@gmail.com';

INSERT INTO settings (setting_key, setting_value, description)
VALUES ('gmail_app_password', 'ejvq toot ewby lgsi', 'Пароль приложения Gmail (16 символов)')
ON DUPLICATE KEY UPDATE setting_value = 'ejvq toot ewby lgsi';
