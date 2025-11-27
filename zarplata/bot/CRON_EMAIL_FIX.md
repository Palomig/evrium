# Исправление: Cron отправляет email

## Проблема
Cron-скрипт `cron.php` отправляет уведомления на email вместо того, чтобы только логировать в файл.

## Причина
По умолчанию cron отправляет весь вывод (stdout и stderr) на email администратора.

## Решение 1: Перенаправить вывод в /dev/null (без логов)

Если логи не нужны, отключить вывод полностью:

```bash
*/5 * * * * /opt/php82/bin/php /home/c/cw95865/PALOMATIKA/public_html/zarplata/bot/cron.php > /dev/null 2>&1
```

## Решение 2: Перенаправить вывод в файл лога (с логами) ⭐ РЕКОМЕНДУЕТСЯ

Сохранять логи в файл вместо отправки на email:

```bash
*/5 * * * * /opt/php82/bin/php /home/c/cw95865/PALOMATIKA/public_html/zarplata/bot/cron.php >> /home/c/cw95865/logs/attendance_cron.log 2>&1
```

**Создать папку для логов:**
```bash
mkdir -p /home/c/cw95865/logs
chmod 755 /home/c/cw95865/logs
```

## Решение 3: Отключить email глобально для всех cron-задач

Добавить в начало crontab (перед всеми задачами):

```bash
MAILTO=""
```

Полный пример crontab:

```bash
MAILTO=""

# Attendance polling - every 5 minutes
*/5 * * * * /opt/php82/bin/php /home/c/cw95865/PALOMATIKA/public_html/zarplata/bot/cron.php >> /home/c/cw95865/logs/attendance_cron.log 2>&1

# Cleanup old telegram updates - daily at 3:00 AM
0 3 * * * /opt/php82/bin/php /home/c/cw95865/PALOMATIKA/public_html/zarplata/bot/cleanup_updates.php >> /home/c/cw95865/logs/cleanup.log 2>&1
```

## Как редактировать crontab

**Вариант A: Через cPanel на Timeweb**

1. Войти в cPanel
2. Раздел "Cron Jobs" (Задания Cron)
3. Найти задачу с `cron.php`
4. Изменить команду, добавив `>> /path/to/log 2>&1`

**Вариант B: Через SSH**

```bash
crontab -e
```

Найти строку:
```
*/5 * * * * /opt/php82/bin/php /home/c/cw95865/PALOMATIKA/public_html/zarplata/bot/cron.php
```

Заменить на:
```
*/5 * * * * /opt/php82/bin/php /home/c/cw95865/PALOMATIKA/public_html/zarplata/bot/cron.php >> /home/c/cw95865/logs/attendance_cron.log 2>&1
```

Сохранить: `Ctrl+O`, `Enter`, `Ctrl+X`

## Просмотр логов

После изменения логи будут в файле вместо email:

```bash
# Последние 50 строк
tail -50 /home/c/cw95865/logs/attendance_cron.log

# Следить за логами в реальном времени
tail -f /home/c/cw95865/logs/attendance_cron.log

# Найти ошибки
grep -i error /home/c/cw95865/logs/attendance_cron.log
```

## Объяснение флагов

- `>>` - дописать в конец файла (вместо перезаписи)
- `2>&1` - перенаправить stderr (ошибки) тоже в этот файл
- `> /dev/null 2>&1` - выбросить весь вывод (не сохранять)

## Проверка после изменения

1. Подождать 5 минут
2. Email больше не должны приходить
3. Проверить логи в файле:
   ```bash
   cat /home/c/cw95865/logs/attendance_cron.log
   ```

---

**Дата**: 2025-11-27
**Проблема**: Email-спам от cron
**Решение**: Перенаправить вывод в файл
