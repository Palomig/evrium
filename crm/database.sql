-- Evrium CRM Database Schema
-- Версия: 1.0
-- Дата: 2025-11-10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Создание базы данных
CREATE DATABASE IF NOT EXISTS `evrium_crm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `evrium_crm`;

-- ============================================================
-- Таблица администраторов (преподаватели + супер-админы)
-- ============================================================
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('superadmin','teacher') NOT NULL DEFAULT 'teacher',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `telegram_id` bigint(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `telegram_id` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание дефолтного супер-админа (пароль: admin123)
INSERT INTO `admins` (`username`, `password_hash`, `name`, `role`, `active`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Администратор', 'superadmin', 1);

-- ============================================================
-- Таблица учеников
-- ============================================================
CREATE TABLE IF NOT EXISTS `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `class` int(2) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `schedule` varchar(255) DEFAULT NULL,
  `goal` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `balance` decimal(8,2) NOT NULL DEFAULT 0.00,
  `status` enum('оплачено','ожидает','задолженность') NOT NULL DEFAULT 'ожидает',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Таблица навыков (общая для всех)
-- ============================================================
CREATE TABLE IF NOT EXISTS `skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `class` int(2) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class` (`class`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Примеры навыков
INSERT INTO `skills` (`name`, `category`, `class`, `description`) VALUES
('Решение линейных уравнений', 'Алгебра', 7, 'Базовые навыки решения линейных уравнений'),
('Геометрические построения', 'Геометрия', 7, 'Построение треугольников и окружностей'),
('Теорема Пифагора', 'Геометрия', 8, 'Применение теоремы Пифагора'),
('Квадратные уравнения', 'Алгебра', 8, 'Решение квадратных уравнений'),
('Тригонометрия', 'Алгебра', 9, 'Основы тригонометрии');

-- ============================================================
-- Таблица навыков учеников
-- ============================================================
CREATE TABLE IF NOT EXISTS `student_skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `level` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Уровень от 0 до 5',
  `last_update` date NOT NULL,
  `comment` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `skill_id` (`skill_id`),
  CONSTRAINT `student_skills_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Таблица уроков
-- ============================================================
CREATE TABLE IF NOT EXISTS `lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `topics` text DEFAULT NULL,
  `homework_given` tinyint(1) NOT NULL DEFAULT 0,
  `homework_done` tinyint(1) NOT NULL DEFAULT 0,
  `comment` text DEFAULT NULL,
  `paid` tinyint(1) NOT NULL DEFAULT 0,
  `rating` tinyint(1) DEFAULT NULL COMMENT 'Оценка от 1 до 5',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `date` (`date`),
  CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lessons_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Таблица оплат
-- ============================================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `amount` decimal(8,2) NOT NULL,
  `method` varchar(50) DEFAULT NULL COMMENT 'наличные, перевод, карта',
  `comment` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `date` (`date`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Таблица материалов
-- ============================================================
CREATE TABLE IF NOT EXISTS `materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `class` int(2) DEFAULT NULL,
  `type` enum('pdf','image') NOT NULL,
  `path` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `class` (`class`),
  CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Таблица токенов API
-- ============================================================
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `api_tokens_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Создание индексов для оптимизации
-- ============================================================
ALTER TABLE `students` ADD INDEX `status` (`status`);
ALTER TABLE `lessons` ADD INDEX `paid` (`paid`);
ALTER TABLE `payments` ADD INDEX `amount` (`amount`);

-- ============================================================
-- Представления для аналитики
-- ============================================================

-- Представление: статистика по преподавателям
CREATE OR REPLACE VIEW `teacher_stats` AS
SELECT
    a.id AS teacher_id,
    a.name AS teacher_name,
    COUNT(DISTINCT s.id) AS total_students,
    COUNT(l.id) AS total_lessons,
    SUM(p.amount) AS total_revenue,
    AVG(l.rating) AS avg_rating
FROM admins a
LEFT JOIN students s ON a.id = s.teacher_id
LEFT JOIN lessons l ON a.id = l.teacher_id
LEFT JOIN payments p ON a.id = p.teacher_id
WHERE a.role = 'teacher'
GROUP BY a.id, a.name;

-- Представление: статистика по ученикам
CREATE OR REPLACE VIEW `student_stats` AS
SELECT
    s.id AS student_id,
    s.name AS student_name,
    s.teacher_id,
    COUNT(l.id) AS total_lessons,
    SUM(CASE WHEN l.homework_done = 1 THEN 1 ELSE 0 END) AS homework_completed,
    SUM(CASE WHEN l.homework_given = 1 THEN 1 ELSE 0 END) AS homework_given,
    AVG(l.rating) AS avg_rating,
    s.balance,
    s.status
FROM students s
LEFT JOIN lessons l ON s.id = l.student_id
GROUP BY s.id, s.name, s.teacher_id, s.balance, s.status;

-- ============================================================
-- Триггеры для автоматического обновления баланса
-- ============================================================

DELIMITER $$

-- Триггер: обновление баланса при добавлении оплаты
CREATE TRIGGER `after_payment_insert`
AFTER INSERT ON `payments`
FOR EACH ROW
BEGIN
    UPDATE students
    SET balance = balance + NEW.amount
    WHERE id = NEW.student_id;
END$$

-- Триггер: обновление баланса при удалении оплаты
CREATE TRIGGER `after_payment_delete`
AFTER DELETE ON `payments`
FOR EACH ROW
BEGIN
    UPDATE students
    SET balance = balance - OLD.amount
    WHERE id = OLD.student_id;
END$$

-- Триггер: автоматическое обновление статуса ученика
CREATE TRIGGER `update_student_status`
AFTER UPDATE ON `students`
FOR EACH ROW
BEGIN
    IF NEW.balance < 0 THEN
        SET NEW.status = 'задолженность';
    ELSEIF NEW.balance > 0 THEN
        SET NEW.status = 'оплачено';
    ELSE
        SET NEW.status = 'ожидает';
    END IF;
END$$

DELIMITER ;
