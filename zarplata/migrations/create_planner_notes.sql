-- Таблица свободных заметок планировщика-расписания
-- Планировщик (planner.php) работает как Google-таблица: текстовые ячейки
-- с заголовком блока урока. Создаётся автоматически (CREATE TABLE IF NOT EXISTS
-- в planner.php и api/planner.php), этот файл — для документации/ручного применения.

CREATE TABLE IF NOT EXISTS planner_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day TINYINT NOT NULL,                            -- 1=Пн ... 7=Вс
    time VARCHAR(5) NOT NULL,                        -- 'HH:MM'
    room TINYINT NOT NULL DEFAULT 1,                 -- кабинет 1-3
    kind ENUM('title','student') NOT NULL DEFAULT 'student',
    position SMALLINT NOT NULL DEFAULT 0,            -- порядок ячеек внутри блока
    content VARCHAR(160) NOT NULL DEFAULT '',
    color TINYINT NOT NULL DEFAULT 0,                -- 0 = нейтральный, 1-8 = палитра преподавателей
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cell (day, time, room)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
