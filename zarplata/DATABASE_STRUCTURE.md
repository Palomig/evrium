# 📊 Полная структура базы данных `cw95865_admin`

## Основные таблицы

### 1. **users** - Пользователи системы (администраторы)
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'owner') DEFAULT 'admin',
    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

### 2. **teachers** - Преподаватели
```sql
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    display_name VARCHAR(100),            -- Короткое имя для отображения
    telegram_id BIGINT UNIQUE,
    telegram_username VARCHAR(50),
    phone VARCHAR(20),
    email VARCHAR(100),
    formula_id INT,                       -- Формула оплаты по умолчанию
    formula_id_group INT,                 -- Для групповых занятий
    formula_id_individual INT,            -- Для индивидуальных
    active BOOLEAN DEFAULT 1,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (formula_id) REFERENCES payment_formulas(id) ON DELETE SET NULL
);
```

---

### 3. **students** - Ученики ⭐ КЛЮЧЕВАЯ ТАБЛИЦА
```sql
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,              -- Преподаватель
    name VARCHAR(100) NOT NULL,           -- Имя ученика

    -- Контакты ученика
    student_telegram VARCHAR(100),
    student_whatsapp VARCHAR(20),

    -- Контакты родителей
    parent_name VARCHAR(100),
    parent_telegram VARCHAR(100),
    parent_whatsapp VARCHAR(20),

    -- Данные об ученике
    class INT,                            -- Класс (7, 8, 9...)
    tier ENUM('S','A','B','C','D') DEFAULT 'C',  -- Уровень

    -- Тип занятий
    lesson_type ENUM('group','individual') DEFAULT 'group',

    -- Оплата
    payment_type_group ENUM('per_lesson','monthly') DEFAULT 'monthly',
    payment_type_individual ENUM('per_lesson','monthly') DEFAULT 'per_lesson',
    price_group INT DEFAULT 5000,
    price_individual INT DEFAULT 1500,

    -- ⭐ РАСПИСАНИЕ УЧЕНИКА (JSON)
    schedule JSON,                        -- {"2": "17:00", "4": "19:00"}
                                          -- где ключ = день недели (1-7)

    notes TEXT,
    active BOOLEAN DEFAULT 1,             -- Активен / Деактивирован
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);
```

**Пример JSON расписания:**
```json
{
  "1": "15:00",  // Понедельник 15:00
  "3": "17:00",  // Среда 17:00
  "5": "19:00"   // Пятница 19:00
}
```

---

### 4. **lessons_template** - Шаблоны расписания ⭐ ПРОБЛЕМНАЯ ТАБЛИЦА
```sql
CREATE TABLE lessons_template (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,        -- 1=Пн, 2=Вт, ..., 7=Вс
    room TINYINT DEFAULT 1,               -- Номер кабинета (1-3)
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,

    lesson_type ENUM('group','individual') DEFAULT 'group',
    subject VARCHAR(100),                 -- Математика, Физика...

    tier ENUM('S','A','B','C','D') DEFAULT 'C',
    grades VARCHAR(50),                   -- "7, 8-9"

    -- ❌ ПРОБЛЕМА: Дублирование данных!
    students TEXT,                        -- JSON: ["Иван (8 кл.)", "Мария (9 кл.)"]
    expected_students INT DEFAULT 1,      -- Ожидаемое количество

    formula_id INT,                       -- Формула оплаты для этого урока
    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (formula_id) REFERENCES payment_formulas(id) ON DELETE SET NULL,

    KEY idx_teacher_day (teacher_id, day_of_week),
    KEY idx_active (active)
);
```

**❌ ПРОБЛЕМА:** Поле `students` дублирует данные из таблицы `students.schedule`!

---

### 5. **lessons_instance** - Фактические уроки (экземпляры)
```sql
CREATE TABLE lessons_instance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_id INT,                      -- Из какого шаблона создан
    teacher_id INT NOT NULL,
    substitute_teacher_id INT,            -- Замещающий преподаватель

    lesson_date DATE NOT NULL,
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,

    lesson_type ENUM('group','individual') DEFAULT 'group',
    subject VARCHAR(100),

    expected_students INT DEFAULT 1,
    actual_students INT DEFAULT 0,        -- Кто реально пришёл

    formula_id INT,                       -- Формула расчёта оплаты
    status ENUM('scheduled','completed','cancelled','rescheduled') DEFAULT 'scheduled',

    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (template_id) REFERENCES lessons_template(id) ON DELETE SET NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (substitute_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    FOREIGN KEY (formula_id) REFERENCES payment_formulas(id) ON DELETE SET NULL
);
```

---

### 6. **lesson_students** - Связь учеников с уроками (Many-to-Many)
```sql
CREATE TABLE lesson_students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lesson_instance_id INT NOT NULL,
    student_id INT NOT NULL,
    enrolled BOOLEAN DEFAULT 1,           -- Записан на урок
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_lesson_student (lesson_instance_id, student_id),
    FOREIGN KEY (lesson_instance_id) REFERENCES lessons_instance(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
```

---

### 7. **attendance_log** - Посещаемость уроков
```sql
CREATE TABLE attendance_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lesson_instance_id INT NOT NULL,
    student_id INT NOT NULL,
    attended BOOLEAN NOT NULL,            -- Пришёл / не пришёл
    marked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    marked_by VARCHAR(50) DEFAULT 'telegram_bot',  -- Кто отметил
    notes TEXT,

    FOREIGN KEY (lesson_instance_id) REFERENCES lessons_instance(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    KEY idx_lesson_student (lesson_instance_id, student_id)
);
```

---

### 8. **payment_formulas** - Формулы расчёта зарплаты
```sql
CREATE TABLE payment_formulas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,           -- "Стандартная групповая"
    type ENUM('min_plus_per', 'fixed', 'expression') NOT NULL,
    description TEXT,

    -- Для типа 'min_plus_per': min + per * (N - threshold)
    min_payment INT DEFAULT 0,            -- Базовая ставка: 500₽
    per_student INT DEFAULT 0,            -- За каждого студента: 150₽
    threshold INT DEFAULT 2,              -- Начиная со 2-го студента

    -- Для типа 'fixed'
    fixed_amount INT DEFAULT 0,           -- Фиксированная: 900₽

    -- Для типа 'expression'
    expression TEXT,                      -- Кастомная формула: "max(500, N * 150)"

    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Примеры формул:**
- **min_plus_per**: 500₽ + 150₽ за каждого студента начиная со 2-го
- **fixed**: 900₽ фиксированно
- **expression**: `max(500, N * 150)` где N = количество студентов

---

### 9. **payments** - Выплаты преподавателям
```sql
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    lesson_instance_id INT,               -- Связь с уроком (может быть NULL)
    lesson_template_id INT,               -- Связь с шаблоном

    amount INT NOT NULL,                  -- Сумма в рублях
    payment_type ENUM('lesson','bonus','penalty','adjustment') DEFAULT 'lesson',
    calculation_method TEXT,              -- Как рассчитана сумма

    period_start DATE,                    -- Период оплаты
    period_end DATE,

    status ENUM('pending','approved','paid','cancelled') DEFAULT 'pending',
    paid_at DATETIME,
    notes TEXT,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_instance_id) REFERENCES lessons_instance(id) ON DELETE SET NULL,
    FOREIGN KEY (lesson_template_id) REFERENCES lessons_template(id) ON DELETE SET NULL
);
```

---

### 10. **payout_cycles** - Циклы выплат (периоды)
```sql
CREATE TABLE payout_cycles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,           -- "Ноябрь 2025"
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    status ENUM('draft','finalized','paid') DEFAULT 'draft',
    total_amount INT DEFAULT 0,
    notes TEXT,
    finalized_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

### 11. **payout_cycle_payments** - Связь выплат с циклами
```sql
CREATE TABLE payout_cycle_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cycle_id INT NOT NULL,
    payment_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_cycle_payment (cycle_id, payment_id),
    FOREIGN KEY (cycle_id) REFERENCES payout_cycles(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);
```

---

### 12. **audit_log** - Журнал аудита (все действия)
```sql
CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action_type VARCHAR(50) NOT NULL,     -- 'user_login', 'student_created'...
    entity_type VARCHAR(50),              -- 'student', 'teacher', 'payment'...
    entity_id INT,                        -- ID изменённой записи
    user_id INT,                          -- Кто совершил действие
    teacher_id INT,
    telegram_id BIGINT,
    old_value TEXT,                       -- JSON старого состояния
    new_value TEXT,                       -- JSON нового состояния
    notes TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    KEY idx_action_type (action_type),
    KEY idx_entity (entity_type, entity_id),
    KEY idx_created_at (created_at)
);
```

---

### 13. **settings** - Системные настройки
```sql
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

### 14. **bot_states** - Состояния Telegram бота
```sql
CREATE TABLE bot_states (
    id INT PRIMARY KEY AUTO_INCREMENT,
    telegram_id BIGINT NOT NULL UNIQUE,
    state VARCHAR(50),                    -- 'awaiting_lesson_date', 'marking_attendance'...
    context_data TEXT,                    -- JSON данные контекста
    expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## Представления (Views)

### **teacher_stats** - Статистика преподавателей
```sql
CREATE VIEW teacher_stats AS
SELECT
    t.id AS teacher_id,
    t.name AS teacher_name,
    COUNT(DISTINCT li.id) AS total_lessons,
    SUM(CASE WHEN li.status = 'completed' THEN 1 ELSE 0 END) AS completed_lessons,
    SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) AS total_paid,
    SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END) AS total_pending
FROM teachers t
LEFT JOIN lessons_instance li ON t.id = li.teacher_id
LEFT JOIN payments p ON t.id = p.teacher_id
GROUP BY t.id;
```

### **lessons_stats** - Статистика уроков
```sql
CREATE VIEW lessons_stats AS
SELECT
    li.id AS lesson_id,
    li.lesson_date,
    li.time_start,
    li.time_end,
    t.name AS teacher_name,
    li.subject,
    li.actual_students,
    li.status,
    p.amount AS payment_amount,
    p.status AS payment_status
FROM lessons_instance li
LEFT JOIN teachers t ON li.teacher_id = t.id
LEFT JOIN payments p ON li.id = p.lesson_instance_id
ORDER BY li.lesson_date DESC, li.time_start ASC;
```

---

## Триггеры

### **audit_attendance_log** - Аудит посещаемости
```sql
TRIGGER audit_attendance_log AFTER INSERT ON attendance_log
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (action_type, entity_type, entity_id, new_value)
    VALUES ('attendance_marked', 'lesson', NEW.lesson_instance_id,
            JSON_OBJECT('student_id', NEW.student_id, 'attended', NEW.attended));
END;
```

---

## Итого:

**Таблиц**: 14
**Представлений**: 2
**Триггеров**: 1

### Ключевые связи:

```
users (админы)

teachers (преподаватели)
  ├─> students (ученики)
  │     └─> schedule (JSON расписание)
  │
  ├─> lessons_template (шаблоны уроков)
  │     └─> students (TEXT дублирование!) ❌
  │
  ├─> lessons_instance (фактические уроки)
  │     ├─> lesson_students (связь учеников)
  │     └─> attendance_log (посещаемость)
  │
  └─> payments (выплаты)
        └─> payout_cycles (циклы выплат)
```

---

## ❌ ПРОБЛЕМА: Дублирование данных

**Расписание студента хранится в ДВУХ местах:**

1. `students.schedule` (JSON) ✅ - единственный источник правды
2. `lessons_template.students` (TEXT/JSON) ❌ - дублирование!

**Когда деактивируется студент:**
- `students.active = 0` ✅ обновляется
- `lessons_template.students` ❌ НЕ обновляется → имя остаётся!

**Решение:** Убрать `lessons_template.students` и динамически формировать список из `students.schedule`
