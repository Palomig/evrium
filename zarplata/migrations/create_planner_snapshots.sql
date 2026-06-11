-- История версий планировщика-расписания (как в Google Таблицах)
-- Версия создаётся по внесённым изменениям: правки в пределах 10 минут
-- схлопываются в одну версию, пауза длиннее начинает новую.
-- Таблица создаётся автоматически (config/planner_snapshots.php),
-- этот файл — для документации/ручного применения.

CREATE TABLE IF NOT EXISTS planner_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL,    -- начало сессии правок (МСК)
    updated_at DATETIME NOT NULL,    -- последняя правка сессии (МСК)
    data MEDIUMTEXT NOT NULL,        -- полный JSON-дамп planner_notes после правки
    KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
