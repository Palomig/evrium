# Синхронизация количества студентов

## Проблема

В шаблонах уроков (`lessons_template`) поле `expected_students` может не соответствовать реальному количеству студентов в JSON-массиве `students`. Это влияет на расчёт выплат.

## Решение

### 1. Автоматическая синхронизация (рекомендуется)

Запустите утилиту на сервере через SSH:

```bash
cd /path/to/zarplata
php sync_students_count.php
```

Утилита:
- Пройдётся по всем активным шаблонам
- Сравнит `expected_students` с реальным количеством в JSON
- Обновит несоответствующие записи
- Выведет подробный отчёт

### 2. Ручное обновление через SQL

Если нет доступа к PHP CLI, выполните SQL-запрос в phpMyAdmin:

```sql
UPDATE lessons_template lt
SET expected_students = (
    SELECT COUNT(*)
    FROM JSON_TABLE(
        lt.students,
        '$[*]' COLUMNS(student_name VARCHAR(100) PATH '$')
    ) AS jt
)
WHERE active = 1 AND students IS NOT NULL;
```

**⚠️ Внимание:** Этот запрос работает в MySQL 5.7.8+ с поддержкой JSON_TABLE.

### 3. Альтернативный SQL (для старых версий MySQL)

```sql
-- Для каждого шаблона вручную
UPDATE lessons_template SET expected_students = 2 WHERE id = 66; -- Влад, Глеб
UPDATE lessons_template SET expected_students = 4 WHERE id = 67; -- и т.д.
```

## Изменения в коде

### payments.php (исправлено)

Теперь расчёт выплат для запланированных уроков использует **реальное количество студентов** из JSON-массива `students`, а не поле `expected_students`.

Это означает, что даже если `expected_students` не синхронизирован, выплаты будут рассчитываться корректно.

## Проверка результатов

После синхронизации запустите:

```bash
php check_students_count.php
```

Вывод должен показать, что все шаблоны имеют корректное количество студентов (нет предупреждений ⚠️).

## Когда запускать синхронизацию

- После массового редактирования списков студентов
- Еженедельно (можно добавить в cron)
- Перед формированием отчётов по выплатам

## Cron (автоматический запуск)

Добавьте в crontab для еженедельной синхронизации:

```cron
0 2 * * 1 cd /path/to/zarplata && php sync_students_count.php >> /var/log/zarplata_sync.log 2>&1
```

Это запустит синхронизацию каждый понедельник в 2:00 ночи.
