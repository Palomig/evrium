# Telegram Bot для системы Zarplata

Автоматический опрос посещаемости и начисление зарплаты через Telegram.

## 🎯 Как это работает

1. **Расписание** создаётся в системе (lessons_template)
2. **Через 15 минут** после начала урока бот спрашивает преподавателя в Telegram
3. **Преподаватель отвечает** - все пришли или указывает количество присутствующих
4. **Зарплата рассчитывается** автоматически по формуле
5. **Выплата создаётся** в системе со статусом "pending"

## 📂 Структура файлов

```
zarplata/bot/
├── config.php               # Конфигурация и общие функции
├── webhook.php              # Обработчик Telegram webhook
├── cron.php                 # Cron задача для опроса (каждые 5 минут)
├── handlers/                # Обработчики команд и действий
│   ├── StartCommand.php     # /start - регистрация
│   ├── TodayCommand.php     # /today - заработок сегодня
│   ├── WeekCommand.php      # /week - заработок за неделю
│   ├── MonthCommand.php     # /month - заработок за месяц
│   ├── ScheduleCommand.php  # /schedule - расписание на сегодня
│   └── AttendanceHandler.php # Обработка посещаемости
└── README.md                # Эта документация
```

## 🚀 Установка

### 1. Создать бота в Telegram

1. Открыть [@BotFather](https://t.me/botfather) в Telegram
2. Отправить команду `/newbot`
3. Указать имя бота (например, "Zarplata Helper")
4. Указать username (например, `zarplata_helper_bot`)
5. Получить **токен** (например, `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)

### 2. Сохранить токен в базе данных

Выполнить SQL:

```sql
INSERT INTO settings (setting_key, setting_value, description)
VALUES ('telegram_bot_token', 'ВАШ_ТОКЕН_ЗДЕСЬ', 'Telegram Bot API Token')
ON DUPLICATE KEY UPDATE setting_value = 'ВАШ_ТОКЕН_ЗДЕСЬ';
```

Или через веб-интерфейс:
- Зайти в `https://эвриум.рф/zarplata/settings.php`
- Найти поле "Telegram Bot Token"
- Вставить токен

### 3. Настроить webhook

Открыть в браузере:

```
https://api.telegram.org/bot<ВАШ_ТОКЕН>/setWebhook?url=https://эвриум.рф/zarplata/bot/webhook.php
```

Должно вернуть:

```json
{"ok":true,"result":true,"description":"Webhook was set"}
```

### 4. Настроить cron задачу

На сервере Timeweb добавить cron задачу:

```cron
*/5 * * * * php /home/c/cw95865/PALOMATIKA/public_html/zarplata/bot/cron.php >> /home/c/cw95865/logs/zarplata_cron.log 2>&1
```

**Частота**: каждые 5 минут
**Лог**: `/home/c/cw95865/logs/zarplata_cron.log`

### 5. Привязать Telegram ID преподавателя

1. Преподаватель отправляет `/start` боту
2. Бот показывает Telegram ID
3. Администратор заходит в `https://эвриум.рф/zarplata/teachers.php`
4. Редактирует профиль преподавателя
5. Указывает Telegram ID в поле "Telegram ID"
6. Сохраняет

После этого преподаватель получает уведомления.

## 📱 Команды бота

### Для преподавателей

| Команда | Описание |
|---------|----------|
| `/start` | Регистрация и инструкции по привязке |
| `/today` | Заработок за сегодня |
| `/week` | Заработок за неделю |
| `/month` | Заработок за текущий месяц |
| `/schedule` | Расписание на сегодня |
| `/help` | Справка по командам |

### Автоматические уведомления

Через 15 минут после начала урока бот отправляет:

```
📊 Отметка посещаемости

📚 Математика [Tier A]
🕐 10:00 - 11:30
🏫 Кабинет 1
👥 Ожидалось: 8 учеников

❓ Все ученики пришли на урок?

[✅ Да, все пришли]  [❌ Нет, есть отсутствующие]
```

## 🔧 Настройка

### Миграция базы данных

Выполнить миграцию:

```bash
mysql -u cw95865_admin -p cw95865_admin < /path/to/zarplata/migrations/add_lesson_template_to_payments.sql
```

Или вручную через phpMyAdmin:

```sql
ALTER TABLE `payments`
ADD COLUMN `lesson_template_id` INT NULL AFTER `lesson_instance_id`,
ADD CONSTRAINT `fk_payments_lesson_template`
    FOREIGN KEY (`lesson_template_id`)
    REFERENCES `lessons_template`(`id`)
    ON DELETE SET NULL;

ALTER TABLE `payments`
ADD INDEX `idx_lesson_template_id` (`lesson_template_id`);
```

### Проверка работы webhook

Отправить тестовое сообщение боту в Telegram.

Проверить лог:

```bash
tail -f /home/c/cw95865/logs/php_error.log | grep "Telegram"
```

Должны появиться строки:

```
Telegram webhook received: {"update_id":...}
```

### Проверка работы cron

Проверить лог cron:

```bash
tail -f /home/c/cw95865/logs/zarplata_cron.log
```

Должны появиться строки:

```
Attendance cron started at 2025-11-17 10:15:00
Found 2 lessons for attendance polling
Attendance query sent to teacher 5 for lesson 12
Attendance cron finished
```

## 📊 Процесс начисления зарплаты

1. **Cron запускается** каждые 5 минут
2. **Ищет уроки**, которые начались 15 минут назад (±3 минуты)
3. **Проверяет**, не была ли уже отмечена посещаемость сегодня
4. **Отправляет сообщение** преподавателю в Telegram
5. **Преподаватель отвечает** через inline кнопки
6. **Зарплата рассчитывается** по формуле:
   - **min_plus_per**: базовая + (студентов сверх порога * доплата)
   - **fixed**: фиксированная сумма
   - **expression**: custom формула
7. **Создаётся payment** в базе со статусом `pending`
8. **Администратор одобряет** выплату в веб-интерфейсе
9. **Отмечает как выплаченную** после перевода денег

## 🔍 Отладка

### Логи Telegram API

Проверить логи PHP:

```bash
tail -f /var/log/php_error.log | grep Telegram
```

### Ручная отправка тестового уведомления

Создать файл `test_notification.php`:

```php
<?php
require_once __DIR__ . '/config.php';

$chatId = 123456789; // Telegram ID преподавателя
sendTelegramMessage($chatId, "Тестовое сообщение от Zarplata Bot!");
```

Запустить:

```bash
php /path/to/zarplata/bot/test_notification.php
```

### Проверка формул

Проверить, правильно ли рассчитывается зарплата:

```sql
-- Посмотреть формулы
SELECT * FROM payment_formulas WHERE active = 1;

-- Посмотреть последние выплаты
SELECT * FROM payments ORDER BY created_at DESC LIMIT 10;

-- Посмотреть выплаты за сегодня
SELECT p.*, t.name as teacher_name, lt.subject, lt.time_start
FROM payments p
LEFT JOIN teachers t ON p.teacher_id = t.id
LEFT JOIN lessons_template lt ON p.lesson_template_id = lt.id
WHERE DATE(p.created_at) = CURDATE()
ORDER BY p.created_at DESC;
```

## ⚠️ Важные замечания

1. **Cron должен запускаться каждые 5 минут** для своевременной отправки уведомлений
2. **Webhook должен быть доступен по HTTPS** (HTTP не поддерживается Telegram)
3. **Telegram ID уникален** - один преподаватель = один Telegram аккаунт
4. **Выплата создаётся один раз** - повторные опросы за тот же день игнорируются
5. **Формула берётся из урока** или из профиля преподавателя (fallback)

## 📞 Поддержка

**Проблемы с ботом:**
- Проверить токен в `settings` таблице
- Проверить webhook: `https://api.telegram.org/bot<TOKEN>/getWebhookInfo`
- Проверить логи: `/var/log/php_error.log` и `/logs/zarplata_cron.log`

**Выплаты не начисляются:**
- Проверить, привязан ли Telegram ID преподавателя
- Проверить, есть ли формула у урока или преподавателя
- Проверить логи cron задачи
- Проверить, активен ли преподаватель и урок в расписании

**Не приходят уведомления:**
- Убедиться, что cron задача запущена
- Проверить время урока (должно быть точно 15 минут назад ±3 минуты)
- Проверить, что Telegram ID правильно указан
- Убедиться, что преподаватель не заблокировал бота

---

✅ **Telegram Bot готов к использованию!**
