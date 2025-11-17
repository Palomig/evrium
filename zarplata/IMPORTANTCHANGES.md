# IMPORTANTCHANGES.md - Техническое Задание

**Проект**: Zarplata - Система учёта зарплаты преподавателей
**Дата**: 2025-11-17
**Версия**: 1.0
**Статус**: Планирование миграции

---

## 📋 Содержание

1. [Обзор изменений](#обзор-изменений)
2. [Текущая реализация](#текущая-реализация)
3. [Предлагаемая архитектура](#предлагаемая-архитектура)
4. [Схема базы данных](#схема-базы-данных)
5. [Процедура миграции](#процедура-миграции)
6. [Сравнение подходов](#сравнение-подходов)
7. [Рекомендации по реализации](#рекомендации-по-реализации)
8. [Тестирование](#тестирование)
9. [Стратегия отката](#стратегия-отката)

---

## 🎯 Обзор изменений

### Проблема

В текущей реализации системы студенты хранятся как JSON-массив в текстовом поле `lessons_template.students`:

```json
["Иван", "Мария", "Петр"]
```

**Ограничения**:
- ❌ Невозможно выполнять JOIN-запросы
- ❌ Нет детальной информации о студентах (телефон, класс, контакты родителей)
- ❌ Сложно отслеживать посещаемость конкретных студентов
- ❌ Невозможна аналитика по студентам
- ❌ Нет валидации данных на уровне БД
- ❌ Трудно избежать дублирования (один студент = разные написания имени)

### Решение

Миграция на **реляционную структуру** с отдельными таблицами:
- `students` - полная информация о студентах
- `lesson_students` - связь многие-ко-многим между уроками и студентами

**Преимущества**:
- ✅ Полная информация о каждом студенте (класс, телефоны, email)
- ✅ JOIN-запросы для аналитики
- ✅ Гибкая отчётность по студентам
- ✅ Автоматическая валидация через foreign keys
- ✅ Нормализация данных (нет дубликатов)
- ✅ Отслеживание посещаемости с привязкой к конкретному студенту

---

## 📊 Текущая реализация

### Структура базы данных

**Таблица `lessons_template`**:

```sql
CREATE TABLE lessons_template (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    day_of_week INT NOT NULL,        -- 1-7 (Пн-Вс)
    room INT NOT NULL,                -- 1-3
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,
    lesson_type ENUM('individual', 'group') DEFAULT 'group',
    subject VARCHAR(100),
    expected_students INT DEFAULT 1,
    formula_id INT,
    tier ENUM('S', 'A', 'B', 'C', 'D') DEFAULT 'C',
    grades VARCHAR(50),               -- "7-9" или "8"
    students TEXT,                    -- ⭐ JSON массив: ["Иван", "Мария"]
    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (formula_id) REFERENCES payment_formulas(id) ON DELETE SET NULL
);
```

### Пример данных

```sql
INSERT INTO lessons_template (teacher_id, day_of_week, room, time_start, time_end,
                               lesson_type, tier, grades, students)
VALUES (1, 1, 1, '10:00:00', '11:30:00', 'group', 'A', '7-8',
        '["Иван Петров", "Мария Сидорова", "Алексей Иванов"]');
```

### PHP код для работы с JSON

**Вставка студентов**:
```php
$students = ["Иван Петров", "Мария Сидорова", "Алексей Иванов"];
$studentsJSON = json_encode($students, JSON_UNESCAPED_UNICODE);

dbExecute(
    "INSERT INTO lessons_template (teacher_id, day_of_week, room, time_start,
     time_end, lesson_type, students)
     VALUES (?, ?, ?, ?, ?, ?, ?)",
    [$teacherId, $dayOfWeek, $room, $timeStart, $timeEnd, $lessonType, $studentsJSON]
);
```

**Чтение студентов**:
```php
$template = dbQueryOne("SELECT * FROM lessons_template WHERE id = ?", [$templateId]);
$students = json_decode($template['students'], true);

foreach ($students as $studentName) {
    echo "<li>{$studentName}</li>";
}
```

**Обновление студентов**:
```php
$students = json_decode($template['students'], true);
$students[] = "Новый студент";  // Добавление
$studentsJSON = json_encode($students, JSON_UNESCAPED_UNICODE);

dbExecute(
    "UPDATE lessons_template SET students = ? WHERE id = ?",
    [$studentsJSON, $templateId]
);
```

### Ограничения текущего подхода

1. **Невозможность JOIN-запросов**:
```sql
-- ❌ Так НЕ РАБОТАЕТ с JSON
SELECT t.name AS teacher_name, s.name AS student_name, lt.time_start
FROM lessons_template lt
JOIN teachers t ON lt.teacher_id = t.id
JOIN students s ON ???  -- Нет связи!
```

2. **Сложный поиск**:
```sql
-- ❌ Поиск студента "Иван" требует JSON-функций
SELECT * FROM lessons_template
WHERE JSON_SEARCH(students, 'one', '%Иван%') IS NOT NULL;
```

3. **Нет данных о студентах**:
- Хранится только имя (строка)
- Нет телефона, класса, email
- Нет возможности отследить историю студента

4. **Дублирование**:
```json
// Разные написания одного студента в разных уроках:
["Иван Петров"]
["Петров Иван"]
["И. Петров"]
```

---

## 🏗️ Предлагаемая архитектура

### Концепция

Переход на **трёхуровневую структуру**:

```
teachers (преподаватели)
    ↓
lessons_template (шаблоны уроков)
    ↓
lesson_students (связь многие-ко-многим)
    ↓
students (студенты)
```

### Ключевые изменения

1. **Новая таблица `students`** - полная информация о каждом студенте
2. **Новая таблица `lesson_students`** - связь уроков и студентов (many-to-many)
3. **Удаление поля `students` из `lessons_template`** (или сохранение для обратной совместимости)
4. **Расширение `attendance_log`** - привязка к конкретному студенту

---

## 🗄️ Схема базы данных

### 1. Таблица `students`

```sql
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    class INT,                        -- Класс обучения (7, 8, 9, 10, 11)
    phone VARCHAR(20),                -- Телефон студента
    parent_phone VARCHAR(20),         -- Телефон родителя
    parent_name VARCHAR(100),         -- Имя родителя
    email VARCHAR(100),               -- Email для связи
    notes TEXT,                       -- Комментарии (особенности, цели)
    active BOOLEAN DEFAULT 1,         -- Активен ли студент
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_name (name),
    INDEX idx_class (class),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Пример данных**:
```sql
INSERT INTO students (name, class, phone, parent_phone, parent_name, email, notes)
VALUES
    ('Иван Петров', 8, '+79991234567', '+79997654321', 'Анна Петрова',
     'ivan.petrov@example.com', 'Готовится к ОГЭ, нужно усилить геометрию'),
    ('Мария Сидорова', 9, '+79991112233', '+79994445566', 'Ольга Сидорова',
     'maria.sidorova@example.com', 'Сильная в алгебре, слабая в стереометрии');
```

### 2. Таблица `lesson_students` (Many-to-Many)

```sql
CREATE TABLE lesson_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_template_id INT NOT NULL,
    student_id INT NOT NULL,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (lesson_template_id) REFERENCES lessons_template(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,

    UNIQUE KEY unique_lesson_student (lesson_template_id, student_id),
    INDEX idx_template (lesson_template_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Пример данных**:
```sql
-- Урок #1 (Понедельник 10:00, Кабинет 1) - 3 студента
INSERT INTO lesson_students (lesson_template_id, student_id) VALUES
    (1, 1),  -- Иван Петров
    (1, 2),  -- Мария Сидорова
    (1, 3);  -- Алексей Иванов

-- Урок #2 (Среда 14:00, Кабинет 2) - 2 студента
INSERT INTO lesson_students (lesson_template_id, student_id) VALUES
    (2, 1),  -- Иван Петров (ходит на оба урока)
    (2, 4);  -- Елена Смирнова
```

### 3. Обновление `attendance_log`

```sql
-- ДОБАВИТЬ поле student_id для привязки к конкретному студенту
ALTER TABLE attendance_log
ADD COLUMN student_id INT AFTER lesson_instance_id,
ADD FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE;

-- Индекс для быстрого поиска по студенту
ALTER TABLE attendance_log ADD INDEX idx_student (student_id);
```

**Новая структура `attendance_log`**:
```sql
CREATE TABLE attendance_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_instance_id INT NOT NULL,
    student_id INT NOT NULL,          -- ⭐ НОВОЕ ПОЛЕ
    status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'present',
    actual_payment DECIMAL(8,2),      -- Фактическая оплата за этого студента
    notes TEXT,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (lesson_instance_id) REFERENCES lessons_instance(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,

    UNIQUE KEY unique_lesson_student_log (lesson_instance_id, student_id),
    INDEX idx_lesson (lesson_instance_id),
    INDEX idx_student (student_id)
);
```

**Преимущество**: Теперь можно отслеживать посещаемость каждого студента индивидуально.

---

## 🔄 Процедура миграции

### Этап 1: Создание новых таблиц

```sql
-- 1. Создать таблицу students
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    class INT,
    phone VARCHAR(20),
    parent_phone VARCHAR(20),
    parent_name VARCHAR(100),
    email VARCHAR(100),
    notes TEXT,
    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_class (class)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Создать таблицу lesson_students
CREATE TABLE lesson_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_template_id INT NOT NULL,
    student_id INT NOT NULL,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_template_id) REFERENCES lessons_template(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_lesson_student (lesson_template_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Обновить attendance_log
ALTER TABLE attendance_log
ADD COLUMN student_id INT AFTER lesson_instance_id,
ADD FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
ADD INDEX idx_student (student_id);
```

### Этап 2: Миграция данных из JSON

**PHP скрипт миграции**:

```php
<?php
require_once __DIR__ . '/config/db.php';

// 1. Получить все шаблоны с JSON-студентами
$templates = dbQuery("SELECT id, students FROM lessons_template WHERE students IS NOT NULL", []);

$migratedCount = 0;
$studentCache = [];  // Кэш для избежания дубликатов

foreach ($templates as $template) {
    $templateId = $template['id'];
    $studentsJSON = $template['students'];

    // Декодировать JSON
    $studentNames = json_decode($studentsJSON, true);
    if (!is_array($studentNames)) continue;

    foreach ($studentNames as $studentName) {
        $studentName = trim($studentName);
        if (empty($studentName)) continue;

        // 2. Проверить, есть ли студент в базе
        $studentId = null;

        if (isset($studentCache[$studentName])) {
            $studentId = $studentCache[$studentName];
        } else {
            $existing = dbQueryOne(
                "SELECT id FROM students WHERE name = ? LIMIT 1",
                [$studentName]
            );

            if ($existing) {
                $studentId = $existing['id'];
            } else {
                // 3. Создать нового студента
                $studentId = dbExecute(
                    "INSERT INTO students (name, active) VALUES (?, 1)",
                    [$studentName]
                );
            }

            $studentCache[$studentName] = $studentId;
        }

        // 4. Создать связь lesson_students (если её ещё нет)
        try {
            dbExecute(
                "INSERT IGNORE INTO lesson_students (lesson_template_id, student_id)
                 VALUES (?, ?)",
                [$templateId, $studentId]
            );
            $migratedCount++;
        } catch (Exception $e) {
            error_log("Failed to link student $studentId to template $templateId: " . $e->getMessage());
        }
    }
}

echo "Миграция завершена. Создано связей: $migratedCount\n";
```

**Запуск миграции**:
```bash
php /path/to/evrium/zarplata/migrate_students.php
```

### Этап 3: Обратная совместимость

**Опция 1: Сохранить поле `students` (рекомендуется для переходного периода)**

```sql
-- Поле students остаётся, но помечается как deprecated
ALTER TABLE lessons_template
COMMENT 'students field deprecated, use lesson_students table';
```

**Опция 2: Удалить поле `students` (только после полной миграции)**

```sql
-- ВНИМАНИЕ: Необратимо! Сделать бэкап!
ALTER TABLE lessons_template DROP COLUMN students;
```

### Этап 4: Обновление PHP кода

**Старый код (JSON)**:
```php
// ❌ DEPRECATED
$template = dbQueryOne("SELECT * FROM lessons_template WHERE id = ?", [$templateId]);
$students = json_decode($template['students'], true);
```

**Новый код (JOIN)**:
```php
// ✅ НОВЫЙ ПОДХОД
$studentsData = dbQuery(
    "SELECT s.id, s.name, s.class, s.phone, s.parent_phone
     FROM students s
     JOIN lesson_students ls ON s.id = ls.student_id
     WHERE ls.lesson_template_id = ?
     ORDER BY s.name ASC",
    [$templateId]
);
```

**Добавление студента к уроку**:
```php
// Старый способ (JSON)
// ❌ DEPRECATED
$students = json_decode($template['students'], true);
$students[] = "Новый студент";
dbExecute("UPDATE lessons_template SET students = ? WHERE id = ?",
    [json_encode($students), $templateId]);

// Новый способ (Relational)
// ✅ НОВЫЙ ПОДХОД
$studentId = dbExecute(
    "INSERT INTO students (name, class, phone) VALUES (?, ?, ?)",
    [$name, $class, $phone]
);

dbExecute(
    "INSERT INTO lesson_students (lesson_template_id, student_id) VALUES (?, ?)",
    [$templateId, $studentId]
);
```

---

## ⚖️ Сравнение подходов

| Критерий | JSON Storage | Relational Tables |
|----------|--------------|-------------------|
| **Простота реализации** | ✅ Очень просто | ⚠️ Требует миграции |
| **Скорость разработки** | ✅ Быстро (MVP) | ❌ Медленнее (нужна миграция) |
| **JOIN-запросы** | ❌ Невозможны | ✅ Полная поддержка |
| **Аналитика** | ❌ Ограничена | ✅ Гибкая |
| **Детализация данных** | ❌ Только имена | ✅ Полная информация |
| **Валидация** | ❌ Только в PHP | ✅ На уровне БД (FK) |
| **Дублирование** | ❌ Возможно | ✅ Исключено (UNIQUE) |
| **Производительность (чтение)** | ✅ Быстро (1 запрос) | ⚠️ Медленнее (JOIN) |
| **Производительность (поиск)** | ❌ Медленно (JSON_SEARCH) | ✅ Быстро (INDEX) |
| **Масштабируемость** | ❌ Ограничена | ✅ Отличная |
| **Поддержка истории** | ❌ Нет | ✅ Да (audit log) |
| **Telegram Bot интеграция** | ❌ Сложно | ✅ Легко (JOIN) |
| **Отчёты по студентам** | ❌ Очень сложно | ✅ Легко (SQL) |

### Когда использовать JSON

✅ **Рекомендуется для**:
- MVP (минимально жизнеспособный продукт)
- Прототипирование
- Простые списки без аналитики
- Проекты с < 50 студентами
- Краткосрочные проекты

### Когда использовать Relational Tables

✅ **Рекомендуется для**:
- Production-системы
- Проекты с > 50 студентами
- Необходимость аналитики и отчётов
- Telegram Bot интеграция
- Долгосрочные проекты
- Многопользовательские системы

---

## 💡 Рекомендации по реализации

### Фаза 1: MVP (Текущая) - JSON подход ✅

**Статус**: Уже реализовано
**Подход**: Хранение студентов в JSON
**Подходит для**: Быстрый запуск, тестирование концепции

**Действия**:
1. ✅ Оставить текущую JSON-структуру
2. ✅ Реализовать основной функционал (расписание, уроки, оплаты)
3. ✅ Собрать обратную связь от пользователей
4. ✅ Оценить реальную потребность в детальной аналитике

### Фаза 2: Production - Миграция на таблицы 🚀

**Когда**: После успешного тестирования MVP (2-4 недели использования)
**Подход**: Реляционные таблицы
**Причины для миграции**:
- Нужна аналитика по студентам
- Требуется Telegram Bot интеграция
- Более 30-50 активных студентов
- Запросы на детальные отчёты

**План миграции**:
1. Создать таблицы `students` и `lesson_students`
2. Запустить миграционный скрипт (см. [Этап 2](#этап-2-миграция-данных-из-json))
3. Обновить PHP код для работы с новыми таблицами
4. Провести параллельное тестирование (JSON + Tables)
5. Переключиться на новую систему
6. Удалить deprecated поле `students` через 2 недели

**Сроки**: 3-5 дней разработки + 1 неделя тестирования

### Фаза 3: Расширенная аналитика 📊

**После миграции на таблицы**:
1. Отчёты по студентам (посещаемость, успеваемость)
2. Отчёты по классам (статистика по 7, 8, 9 классам)
3. История студента (все уроки, оплаты, прогресс)
4. Автоматические уведомления родителям (email, Telegram)
5. Прогнозирование доходов на основе активных студентов

---

## 🧪 Тестирование

### Pre-Migration Tests

**Перед миграцией проверить**:

1. **Бэкап базы данных**:
```bash
mysqldump -u root -p zarplata_db > backup_before_migration_$(date +%Y%m%d).sql
```

2. **Проверка целостности JSON**:
```sql
SELECT id, students FROM lessons_template
WHERE students IS NOT NULL AND JSON_VALID(students) = 0;
-- Должно вернуть 0 строк
```

3. **Подсчёт студентов (для сверки)**:
```php
$totalStudentsJSON = 0;
$templates = dbQuery("SELECT students FROM lessons_template WHERE students IS NOT NULL", []);
foreach ($templates as $t) {
    $students = json_decode($t['students'], true);
    $totalStudentsJSON += count($students);
}
echo "Total students in JSON: $totalStudentsJSON\n";
```

### Post-Migration Tests

**После миграции проверить**:

1. **Подсчёт студентов в новых таблицах**:
```sql
SELECT COUNT(*) FROM students;  -- Должно совпадать с уникальными студентами
SELECT COUNT(*) FROM lesson_students;  -- Должно совпадать с $totalStudentsJSON
```

2. **Проверка целостности связей**:
```sql
-- Все студенты должны быть привязаны хотя бы к одному уроку
SELECT s.id, s.name FROM students s
LEFT JOIN lesson_students ls ON s.id = ls.student_id
WHERE ls.id IS NULL;
-- Должно вернуть 0 строк (или это неактивные студенты)
```

3. **Тест JOIN-запроса**:
```sql
SELECT
    t.name AS teacher_name,
    lt.day_of_week,
    lt.time_start,
    s.name AS student_name,
    s.class
FROM lessons_template lt
JOIN teachers t ON lt.teacher_id = t.id
JOIN lesson_students ls ON lt.id = ls.lesson_template_id
JOIN students s ON ls.student_id = s.id
WHERE lt.id = 1;
-- Должно вернуть всех студентов урока #1
```

4. **Сравнение результатов (JSON vs Tables)**:
```php
// JSON способ
$templateJSON = dbQueryOne("SELECT students FROM lessons_template WHERE id = 1", []);
$studentsJSON = json_decode($templateJSON['students'], true);
sort($studentsJSON);

// Tables способ
$studentsTables = dbQuery(
    "SELECT s.name FROM students s
     JOIN lesson_students ls ON s.id = ls.student_id
     WHERE ls.lesson_template_id = 1
     ORDER BY s.name",
    []
);
$namesFromTables = array_column($studentsTables, 'name');

// Сравнение
if ($studentsJSON === $namesFromTables) {
    echo "✅ Migration successful for template #1\n";
} else {
    echo "❌ Mismatch detected!\n";
    print_r(array_diff($studentsJSON, $namesFromTables));
}
```

### Integration Tests

**Тестовые сценарии**:

1. **Добавление нового студента к уроку**:
```php
$studentId = dbExecute(
    "INSERT INTO students (name, class, phone) VALUES (?, ?, ?)",
    ["Тестовый Студент", 8, "+79999999999"]
);

dbExecute(
    "INSERT INTO lesson_students (lesson_template_id, student_id) VALUES (?, ?)",
    [1, $studentId]
);

// Проверка
$count = dbQueryOne(
    "SELECT COUNT(*) as cnt FROM lesson_students
     WHERE lesson_template_id = 1 AND student_id = ?",
    [$studentId]
)['cnt'];

assert($count == 1, "Student not added to lesson");
```

2. **Удаление студента (cascade check)**:
```php
dbExecute("DELETE FROM students WHERE id = ?", [$studentId]);

// Проверка cascade удаления из lesson_students
$count = dbQueryOne(
    "SELECT COUNT(*) as cnt FROM lesson_students WHERE student_id = ?",
    [$studentId]
)['cnt'];

assert($count == 0, "Cascade delete failed");
```

3. **Создание урока с готовым списком студентов**:
```php
// Создать урок
$templateId = dbExecute(
    "INSERT INTO lessons_template (teacher_id, day_of_week, room, time_start, time_end)
     VALUES (?, ?, ?, ?, ?)",
    [1, 2, 1, '14:00:00', '15:30:00']
);

// Добавить студентов
$studentIds = [1, 2, 3];
foreach ($studentIds as $sid) {
    dbExecute(
        "INSERT INTO lesson_students (lesson_template_id, student_id) VALUES (?, ?)",
        [$templateId, $sid]
    );
}

// Проверка
$count = dbQueryOne(
    "SELECT COUNT(*) as cnt FROM lesson_students WHERE lesson_template_id = ?",
    [$templateId]
)['cnt'];

assert($count == 3, "Expected 3 students, got $count");
```

---

## 🔄 Стратегия отката

### Если миграция пошла не так

**Немедленные действия**:

1. **Остановить приложение**:
```bash
# Временно закрыть доступ к системе
echo "Maintenance mode" > /path/to/zarplata/maintenance.html
```

2. **Восстановить из бэкапа**:
```bash
mysql -u root -p zarplata_db < backup_before_migration_YYYYMMDD.sql
```

3. **Удалить новые таблицы** (если бэкап не помог):
```sql
DROP TABLE IF EXISTS lesson_students;
DROP TABLE IF EXISTS students;

-- Откатить изменения в attendance_log
ALTER TABLE attendance_log DROP FOREIGN KEY attendance_log_ibfk_3;
ALTER TABLE attendance_log DROP COLUMN student_id;
```

4. **Вернуть старый PHP код**:
```bash
git checkout HEAD~1 -- api/schedule.php templates/schedule.php
```

### Частичный откат

**Если миграция прошла, но есть баги**:

1. **Временно использовать оба подхода** (JSON + Tables):
```php
// Hybrid approach - читаем из обеих источников
function getStudentsForLesson($templateId) {
    // Сначала пробуем новый способ
    $students = dbQuery(
        "SELECT s.* FROM students s
         JOIN lesson_students ls ON s.id = ls.student_id
         WHERE ls.lesson_template_id = ?",
        [$templateId]
    );

    if (!empty($students)) {
        return $students;
    }

    // Fallback на JSON
    $template = dbQueryOne("SELECT students FROM lessons_template WHERE id = ?", [$templateId]);
    if ($template && $template['students']) {
        $names = json_decode($template['students'], true);
        return array_map(fn($name) => ['name' => $name], $names);
    }

    return [];
}
```

2. **Логирование расхождений**:
```php
$studentsJSON = json_decode($template['students'], true);
$studentsTables = dbQuery(
    "SELECT name FROM students s
     JOIN lesson_students ls ON s.id = ls.student_id
     WHERE ls.lesson_template_id = ?",
    [$templateId]
);

$namesFromTables = array_column($studentsTables, 'name');

if (array_diff($studentsJSON, $namesFromTables)) {
    error_log("Mismatch for template $templateId: " .
              json_encode(['json' => $studentsJSON, 'tables' => $namesFromTables]));
}
```

### Критерии успешной миграции

✅ **Миграция считается успешной, если**:
1. Все студенты из JSON перенесены в таблицу `students`
2. Все связи созданы в `lesson_students`
3. JOIN-запросы возвращают те же данные, что и JSON
4. UI работает без ошибок
5. Нет жалоб от пользователей в течение 48 часов

❌ **Откат необходим, если**:
1. Потеряны данные о студентах (count не совпадает)
2. Критические ошибки в UI (белый экран, 500 ошибки)
3. Невозможно добавлять/редактировать уроки
4. Foreign key constraints блокируют работу
5. Жалобы от пользователей на некорректные данные

---

## 📝 Чеклист миграции

### Подготовка (1 день)

- [ ] Создать полный бэкап базы данных
- [ ] Уведомить пользователей о планируемых изменениях
- [ ] Подготовить миграционный скрипт
- [ ] Написать тесты для проверки результатов
- [ ] Подготовить план отката

### Миграция (2-3 часа)

- [ ] Включить maintenance mode
- [ ] Создать таблицы `students` и `lesson_students`
- [ ] Запустить миграционный скрипт
- [ ] Проверить количество перенесённых студентов
- [ ] Проверить целостность связей (foreign keys)
- [ ] Запустить тесты

### Обновление кода (1 день)

- [ ] Обновить `api/schedule.php` (добавить методы для работы со студентами)
- [ ] Обновить `templates/schedule.php` (отображение студентов из новых таблиц)
- [ ] Обновить UI для добавления детальной информации о студентах
- [ ] Добавить страницу управления студентами
- [ ] Обновить `attendance_log` для привязки к student_id

### Тестирование (1 день)

- [ ] Тест добавления урока со студентами
- [ ] Тест редактирования списка студентов
- [ ] Тест удаления студента (cascade)
- [ ] Тест JOIN-запросов
- [ ] Тест производительности (> 100 уроков)
- [ ] Тест UI (все страницы работают)

### Запуск (0.5 дня)

- [ ] Отключить maintenance mode
- [ ] Мониторить логи ошибок (первые 24 часа)
- [ ] Собрать обратную связь от пользователей
- [ ] Исправить критические баги (если есть)

### Post-Launch (1 неделя)

- [ ] Удалить deprecated поле `students` (через 2 недели)
- [ ] Обновить документацию
- [ ] Обучить пользователей новым возможностям
- [ ] Оптимизировать индексы при необходимости

---

## 🎯 Итоговые рекомендации

### Для MVP (Текущая фаза)

**Рекомендация**: ✅ **Оставить JSON-подход**

**Причины**:
- Система уже работает на JSON
- Быстрое прототипирование
- Достаточно для 20-50 студентов
- Можно собрать обратную связь

**Действия**:
1. Завершить основной функционал (уроки, оплаты, отчёты)
2. Запустить систему в production
3. Использовать 2-4 недели для сбора требований

### Для Production (Следующая фаза)

**Рекомендация**: ✅ **Мигрировать на реляционные таблицы**

**Причины**:
- Потребность в детальной аналитике
- Интеграция с Telegram Bot
- Более 50 активных студентов
- Долгосрочное использование

**Действия**:
1. Запланировать миграцию на выходные (минимальная нагрузка)
2. Выполнить миграцию по плану выше
3. Провести тестирование
4. Запустить расширенную аналитику

### Критерии принятия решения

**Мигрировать СЕЙЧАС, если**:
- ✅ Уже есть > 50 студентов
- ✅ Нужны отчёты по студентам прямо сейчас
- ✅ Планируется Telegram Bot в ближайший месяц
- ✅ Есть жалобы на дублирование студентов

**Мигрировать ПОТОМ, если**:
- ✅ Меньше 30 студентов
- ✅ Аналитика не критична
- ✅ Telegram Bot не в планах
- ✅ Система работает стабильно

---

## 📞 Поддержка

**Вопросы по миграции**:
1. Проверить `/home/user/evrium/zarplata/ZARPLATA_TODO.md` (общий список задач)
2. Проверить `/home/user/evrium/CLAUDE.md` (документация всего проекта)
3. Проверить `/home/user/evrium/zarplata/README.md` (описание zarplata системы)

**Файлы для изменения при миграции**:
- `/home/user/evrium/zarplata/database.sql` - добавить новые таблицы
- `/home/user/evrium/zarplata/api/schedule.php` - обновить API
- `/home/user/evrium/zarplata/templates/schedule.php` - обновить UI
- `/home/user/evrium/zarplata/migrate_students.php` - создать скрипт миграции (новый файл)

---

**Конец документа** - Версия 1.0 от 2025-11-17
