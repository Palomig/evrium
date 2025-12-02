-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Дек 02 2025 г., 14:13
-- Версия сервера: 5.7.44-48
-- Версия PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `cw95865_admin`
--

-- --------------------------------------------------------

--
-- Структура таблицы `attendance_log`
--

CREATE TABLE IF NOT EXISTS `attendance_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_instance_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attended` tinyint(1) DEFAULT '1',
  `marked_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `marked_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'telegram_bot',
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `idx_lesson_student` (`lesson_instance_id`,`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Триггеры `attendance_log`
--
DELIMITER $$
CREATE TRIGGER `audit_attendance_log` AFTER INSERT ON `attendance_log` FOR EACH ROW BEGIN
    INSERT INTO audit_log (
        action_type,
        entity_type,
        entity_id,
        new_value,
        notes
    ) VALUES (
        'attendance_marked',
        'lesson',
        NEW.lesson_instance_id,
        JSON_OBJECT('student_id', NEW.student_id, 'attended', NEW.attended),
        NEW.notes
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `audit_log`
--

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `telegram_id` bigint(20) DEFAULT NULL,
  `old_value` text COLLATE utf8mb4_unicode_ci,
  `new_value` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=254 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `audit_log`
--

INSERT INTO `audit_log` (`id`, `action_type`, `entity_type`, `entity_id`, `user_id`, `teacher_id`, `telegram_id`, `old_value`, `new_value`, `notes`, `ip_address`, `created_at`) VALUES
(1, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.73.229.232', '2025-11-15 05:23:14'),
(2, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.73.229.232', '2025-11-15 05:36:00'),
(3, 'teacher_created', 'teacher', 1, 1, NULL, NULL, NULL, '{\"name\":\"\\u041d\\u0438\\u043a\\u0438\\u0442\\u0438\\u043d \\u0421\\u0442\\u0430\\u043d\\u0438\\u0441\\u043b\\u0430\\u0432 \\u041e\\u043b\\u0435\\u0433\\u043e\\u0432\\u0438\\u0447\",\"phone\":\"+79103017110\",\"email\":\"\"}', 'Создан новый преподаватель', '95.73.229.232', '2025-11-15 06:32:31'),
(4, 'formula_deactivated', 'formula', 2, 1, NULL, NULL, '{\"active\":1}', '{\"active\":0}', 'Формула деактивирована', '95.73.229.232', '2025-11-15 08:13:17'),
(5, 'formula_activated', 'formula', 2, 1, NULL, NULL, '{\"active\":0}', '{\"active\":1}', 'Формула активирована', '95.73.229.232', '2025-11-15 08:13:27'),
(6, 'formula_updated', 'formula', 2, 1, NULL, NULL, '{\"id\":2,\"name\":\"\\u0418\\u043d\\u0434\\u0438\\u0432\\u0438\\u0434\\u0443\\u0430\\u043b\\u044c\\u043d\\u043e\\u0435 \\u0437\\u0430\\u043d\\u044f\\u0442\\u0438\\u0435\",\"type\":\"fixed\",\"description\":\"\\u0424\\u0438\\u043a\\u0441\\u0438\\u0440\\u043e\\u0432\\u0430\\u043d\\u043d\\u0430\\u044f \\u0441\\u0442\\u0430\\u0432\\u043a\\u0430 \\u0437\\u0430 \\u0438\\u043d\\u0434\\u0438\\u0432\\u0438\\u0434\\u0443\\u0430\\u043b\\u044c\\u043d\\u043e\\u0435 \\u0437\\u0430\\u043d\\u044f\\u0442\\u0438\\u0435\",\"min_payment\":0,\"per_student\":0,\"threshold\":1,\"fixed_amount\":800,\"expression\":null,\"active\":1,\"created_at\":\"2025-11-15 04:29:26\",\"updated_at\":\"2025-11-15 08:13:27\"}', '{\"name\":\"\\u0418\\u043d\\u0434\\u0438\\u0432\\u0438\\u0434\\u0443\\u0430\\u043b\\u044c\\u043d\\u043e\\u0435 \\u0437\\u0430\\u043d\\u044f\\u0442\\u0438\\u0435\",\"type\":\"fixed\"}', 'Обновлена формула оплаты', '95.73.229.232', '2025-11-15 08:22:20'),
(7, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.73.229.232', '2025-11-16 16:38:03'),
(8, 'template_created', 'template', 1, 1, NULL, NULL, NULL, '{\"teacher_id\":1,\"day_of_week\":1,\"time\":\"16:00:00-17:00:00\"}', 'Создан шаблон урока', '95.73.229.232', '2025-11-16 16:51:02'),
(9, 'template_created', 'template', 2, 1, NULL, NULL, NULL, '{\"teacher_id\":1,\"day_of_week\":1,\"time\":\"17:00:00-18:00:00\"}', 'Создан шаблон урока', '95.73.229.232', '2025-11-16 16:51:41'),
(10, 'week_generated', 'schedule', NULL, 1, NULL, NULL, NULL, '{\"week_start\":\"2025-11-10\",\"created\":2}', 'Сгенерировано 2 уроков на неделю', '95.73.229.232', '2025-11-16 16:52:54'),
(11, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.73.229.232', '2025-11-17 00:46:11'),
(12, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.73.229.232', '2025-11-17 00:46:19'),
(13, 'template_created', 'template', 3, 1, NULL, NULL, NULL, '{\"teacher_id\":1,\"day_of_week\":3,\"time\":\"16:00:00-17:00:00\"}', 'Создан шаблон урока', '95.73.229.232', '2025-11-17 01:08:33'),
(14, 'template_created', 'template', 4, 1, NULL, NULL, NULL, '{\"teacher_id\":1,\"day_of_week\":2,\"time\":\"20:00:00-21:00:00\"}', 'Создан шаблон урока', '95.73.229.232', '2025-11-17 01:20:28'),
(15, 'template_created', 'template', 10, 1, NULL, NULL, NULL, '{\"teacher_id\":1,\"day_of_week\":2,\"time\":\"16:00:00-17:00:00\"}', 'Создан шаблон урока', '95.73.229.232', '2025-11-17 05:01:22'),
(16, 'template_updated', 'template', 10, 1, NULL, NULL, '{\"id\":10,\"teacher_id\":1,\"day_of_week\":2,\"room\":1,\"time_start\":\"16:00:00\",\"time_end\":\"17:00:00\",\"lesson_type\":\"group\",\"subject\":\"\\u041c\\u0430\\u0442\\u0435\\u043c\\u0430\\u0442\\u0438\\u043a\\u0430\",\"tier\":\"C\",\"grades\":\"6\",\"students\":\"[]\",\"expected_students\":6,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-17 05:01:22\",\"updated_at\":\"2025-11-17 05:01:22\"}', '{\"teacher_id\":1,\"day_of_week\":2}', 'Обновлён шаблон урока', '95.73.229.232', '2025-11-17 05:03:43'),
(17, 'template_created', 'template', 11, 1, NULL, NULL, NULL, '{\"teacher_id\":1,\"day_of_week\":1,\"time\":\"16:00:00-17:00:00\"}', 'Создан шаблон урока', '95.73.229.232', '2025-11-17 05:07:57'),
(18, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.73.229.232', '2025-11-17 05:23:53'),
(19, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.73.229.232', '2025-11-17 05:24:41'),
(20, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '91.199.118.178', '2025-11-17 20:29:47'),
(21, 'settings_updated', 'settings', NULL, 1, NULL, NULL, NULL, '{\"section\":\"bot\"}', 'Обновлены настройки Telegram бота', '95.73.229.232', '2025-11-18 05:19:35'),
(22, 'migration_applied', 'payments', NULL, NULL, NULL, NULL, NULL, NULL, 'Применена миграция add_lesson_template_to_payments.sql', NULL, '2025-11-18 05:33:40'),
(23, 'teacher_updated', 'teacher', 1, 1, NULL, NULL, '{\"id\":1,\"name\":\"\\u041d\\u0438\\u043a\\u0438\\u0442\\u0438\\u043d \\u0421\\u0442\\u0430\\u043d\\u0438\\u0441\\u043b\\u0430\\u0432 \\u041e\\u043b\\u0435\\u0433\\u043e\\u0432\\u0438\\u0447\",\"telegram_id\":null,\"telegram_username\":\"Palomig\",\"phone\":\"+79103017110\",\"email\":null,\"formula_id\":1,\"active\":1,\"notes\":\"\\u043b\\u0435\\u0433\\u0435\\u043d\\u0434\\u0430\",\"created_at\":\"2025-11-15 06:32:31\",\"updated_at\":\"2025-11-17 01:07:52\"}', '{\"name\":\"\\u041d\\u0438\\u043a\\u0438\\u0442\\u0438\\u043d \\u0421\\u0442\\u0430\\u043d\\u0438\\u0441\\u043b\\u0430\\u0432 \\u041e\\u043b\\u0435\\u0433\\u043e\\u0432\\u0438\\u0447\",\"phone\":\"+79103017110\",\"email\":\"\"}', 'Обновлены данные преподавателя', '95.73.229.232', '2025-11-18 06:29:46'),
(24, 'settings_updated', 'settings', NULL, 1, NULL, NULL, NULL, '{\"section\":\"system\"}', 'Обновлены системные настройки', '95.73.229.232', '2025-11-18 06:47:13'),
(25, 'telegram_webhook_setup', 'settings', NULL, 1, NULL, NULL, NULL, '{\"webhook_url\":\"https:\\/\\/\\u044d\\u0432\\u0440\\u0438\\u0443\\u043c.\\u0440\\u0444\\/zarplata\\/bot\\/webhook.php\"}', 'Настроен Telegram webhook', '95.73.229.232', '2025-11-18 07:11:07'),
(26, 'template_created', 'template', 12, 1, NULL, NULL, NULL, '{\"teacher_id\":1,\"day_of_week\":2,\"time\":\"08:00:00-09:00:00\"}', 'Создан шаблон урока', '95.73.229.232', '2025-11-18 07:24:37'),
(27, 'template_created', 'template', 13, 1, NULL, NULL, NULL, '{\"teacher_id\":1,\"day_of_week\":2,\"time\":\"09:00:00-10:00:00\"}', 'Создан шаблон урока', '95.73.229.232', '2025-11-18 08:49:06'),
(54, 'formula_created', 'formula', 3, 1, NULL, NULL, NULL, '{\"name\":\"\\u0421\\u0442\\u0430\\u0441 \\u0438\\u043d\\u0434\",\"type\":\"fixed\"}', 'Создана новая формула оплаты', '95.73.229.232', '2025-11-18 10:02:23'),
(55, 'formula_created', 'formula', 4, 1, NULL, NULL, NULL, '{\"name\":\"\\u0421\\u0442\\u0430\\u0441 \\u0433\\u0440\\u0443\\u043f\\u043f\\u0430\",\"type\":\"min_plus_per\"}', 'Создана новая формула оплаты', '95.73.229.232', '2025-11-18 10:02:54'),
(56, 'formula_deactivated', 'formula', 1, 1, NULL, NULL, '{\"id\":1,\"name\":\"\\u0421\\u0442\\u0430\\u043d\\u0434\\u0430\\u0440\\u0442\\u043d\\u0430\\u044f \\u0433\\u0440\\u0443\\u043f\\u043f\\u043e\\u0432\\u0430\\u044f\",\"type\":\"min_plus_per\",\"description\":\"\\u041c\\u0438\\u043d\\u0438\\u043c\\u0443\\u043c 500\\u20bd + 150\\u20bd \\u0437\\u0430 \\u043a\\u0430\\u0436\\u0434\\u043e\\u0433\\u043e \\u0443\\u0447\\u0435\\u043d\\u0438\\u043a\\u0430 \\u043d\\u0430\\u0447\\u0438\\u043d\\u0430\\u044f \\u0441\\u043e \\u0432\\u0442\\u043e\\u0440\\u043e\\u0433\\u043e\",\"min_payment\":500,\"per_student\":150,\"threshold\":2,\"fixed_amount\":0,\"expression\":null,\"active\":1,\"created_at\":\"2025-11-15 04:29:26\",\"updated_at\":\"2025-11-15 04:29:26\"}', '{\"active\":0}', 'Формула деактивирована', '95.73.229.232', '2025-11-18 10:03:04'),
(57, 'formula_deactivated', 'formula', 2, 1, NULL, NULL, '{\"id\":2,\"name\":\"\\u0418\\u043d\\u0434\\u0438\\u0432\\u0438\\u0434\\u0443\\u0430\\u043b\\u044c\\u043d\\u043e\\u0435 \\u0437\\u0430\\u043d\\u044f\\u0442\\u0438\\u0435\",\"type\":\"fixed\",\"description\":\"\\u0424\\u0438\\u043a\\u0441\\u0438\\u0440\\u043e\\u0432\\u0430\\u043d\\u043d\\u0430\\u044f \\u0441\\u0442\\u0430\\u0432\\u043a\\u0430 \\u0437\\u0430 \\u0438\\u043d\\u0434\\u0438\\u0432\\u0438\\u0434\\u0443\\u0430\\u043b\\u044c\\u043d\\u043e\\u0435 \\u0437\\u0430\\u043d\\u044f\\u0442\\u0438\\u0435\",\"min_payment\":null,\"per_student\":null,\"threshold\":null,\"fixed_amount\":900,\"expression\":null,\"active\":1,\"created_at\":\"2025-11-15 04:29:26\",\"updated_at\":\"2025-11-15 08:22:20\"}', '{\"active\":0}', 'Формула деактивирована', '95.73.229.232', '2025-11-18 12:33:35'),
(58, 'formula_deactivated', 'formula', 2, 1, NULL, NULL, '{\"id\":2,\"name\":\"\\u0418\\u043d\\u0434\\u0438\\u0432\\u0438\\u0434\\u0443\\u0430\\u043b\\u044c\\u043d\\u043e\\u0435 \\u0437\\u0430\\u043d\\u044f\\u0442\\u0438\\u0435\",\"type\":\"fixed\",\"description\":\"\\u0424\\u0438\\u043a\\u0441\\u0438\\u0440\\u043e\\u0432\\u0430\\u043d\\u043d\\u0430\\u044f \\u0441\\u0442\\u0430\\u0432\\u043a\\u0430 \\u0437\\u0430 \\u0438\\u043d\\u0434\\u0438\\u0432\\u0438\\u0434\\u0443\\u0430\\u043b\\u044c\\u043d\\u043e\\u0435 \\u0437\\u0430\\u043d\\u044f\\u0442\\u0438\\u0435\",\"min_payment\":null,\"per_student\":null,\"threshold\":null,\"fixed_amount\":900,\"expression\":null,\"active\":0,\"created_at\":\"2025-11-15 04:29:26\",\"updated_at\":\"2025-11-18 12:33:35\"}', '{\"active\":0}', 'Формула деактивирована', '95.73.229.232', '2025-11-18 12:33:41'),
(59, 'template_deleted', 'template', 12, 1, NULL, NULL, '{\"id\":12,\"teacher_id\":1,\"day_of_week\":2,\"room\":1,\"time_start\":\"08:00:00\",\"time_end\":\"09:00:00\",\"lesson_type\":\"group\",\"subject\":\"\\u041c\\u0430\\u0442\\u0435\\u043c\\u0430\\u0442\\u0438\\u043a\\u0430\",\"tier\":\"C\",\"grades\":\"6, 7, 8\",\"students\":\"[\\\"\\u0412\\u0438\\u043a\\u0430\\\",\\\"\\u0412\\u0438\\u043a\\u0430\\\",\\\"\\u0412\\u0438\\u043a\\u0430\\\",\\\"\\u0412\\u0438\\u043a\\u0430\\\"]\",\"expected_students\":6,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-18 07:24:37\",\"updated_at\":\"2025-11-18 07:24:37\"}', NULL, 'Шаблон удалён', '95.73.229.232', '2025-11-18 12:34:03'),
(60, 'template_deleted', 'template', 13, 1, NULL, NULL, '{\"id\":13,\"teacher_id\":1,\"day_of_week\":2,\"room\":1,\"time_start\":\"09:00:00\",\"time_end\":\"10:00:00\",\"lesson_type\":\"group\",\"subject\":\"\\u041c\\u0430\\u0442\\u0435\\u043c\\u0430\\u0442\\u0438\\u043a\\u0430\",\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\u0412\\u0438\\u043a\\u0430\\\",\\\"\\u0412\\u0438\\u043a\\u0430\\\",\\\"\\u0412\\u0438\\u043a\\u0430\\\",\\\"\\u0412\\u0438\\u043a\\u0430\\\"]\",\"expected_students\":6,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-18 08:49:06\",\"updated_at\":\"2025-11-18 08:49:06\"}', NULL, 'Шаблон удалён', '95.73.229.232', '2025-11-18 12:34:07'),
(61, 'template_deleted', 'template', 11, 1, NULL, NULL, '{\"id\":11,\"teacher_id\":1,\"day_of_week\":1,\"room\":1,\"time_start\":\"16:00:00\",\"time_end\":\"17:00:00\",\"lesson_type\":\"individual\",\"subject\":\"\\u041c\\u0430\\u0442\\u0435\\u043c\\u0430\\u0442\\u0438\\u043a\\u0430\",\"tier\":\"D\",\"grades\":\"9\",\"students\":\"[\\\"\\u0412\\u043b\\u0430\\u0434\\u0430\\\"]\",\"expected_students\":1,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-17 05:07:57\",\"updated_at\":\"2025-11-17 05:07:57\"}', NULL, 'Шаблон удалён', '95.73.229.232', '2025-11-18 12:34:12'),
(62, 'formula_deactivated', 'formula', 2, 1, NULL, NULL, '{\"id\":2,\"name\":\"\\u0418\\u043d\\u0434\\u0438\\u0432\\u0438\\u0434\\u0443\\u0430\\u043b\\u044c\\u043d\\u043e\\u0435 \\u0437\\u0430\\u043d\\u044f\\u0442\\u0438\\u0435\",\"type\":\"fixed\",\"description\":\"\\u0424\\u0438\\u043a\\u0441\\u0438\\u0440\\u043e\\u0432\\u0430\\u043d\\u043d\\u0430\\u044f \\u0441\\u0442\\u0430\\u0432\\u043a\\u0430 \\u0437\\u0430 \\u0438\\u043d\\u0434\\u0438\\u0432\\u0438\\u0434\\u0443\\u0430\\u043b\\u044c\\u043d\\u043e\\u0435 \\u0437\\u0430\\u043d\\u044f\\u0442\\u0438\\u0435\",\"min_payment\":null,\"per_student\":null,\"threshold\":null,\"fixed_amount\":900,\"expression\":null,\"active\":0,\"created_at\":\"2025-11-15 04:29:26\",\"updated_at\":\"2025-11-18 12:33:41\"}', '{\"active\":0}', 'Формула деактивирована', '95.73.229.232', '2025-11-18 12:34:19'),
(63, 'lesson_deleted', 'lesson', 2, 1, NULL, NULL, '{\"id\":2,\"template_id\":2,\"teacher_id\":1,\"substitute_teacher_id\":null,\"lesson_date\":\"2025-11-10\",\"time_start\":\"17:00:00\",\"time_end\":\"18:00:00\",\"lesson_type\":\"group\",\"subject\":\"\\u041c\\u0430\\u0442\\u0435\\u043c\\u0430\\u0442\\u0438\\u043a\\u0430\",\"expected_students\":5,\"actual_students\":0,\"formula_id\":1,\"status\":\"scheduled\",\"notes\":null,\"created_at\":\"2025-11-16 16:52:54\",\"updated_at\":\"2025-11-16 16:52:54\"}', NULL, 'Урок удалён', '95.73.229.232', '2025-11-18 12:34:26'),
(64, 'lesson_deleted', 'lesson', 1, 1, NULL, NULL, '{\"id\":1,\"template_id\":1,\"teacher_id\":1,\"substitute_teacher_id\":null,\"lesson_date\":\"2025-11-10\",\"time_start\":\"16:00:00\",\"time_end\":\"17:00:00\",\"lesson_type\":\"individual\",\"subject\":\"\\u041c\\u0430\\u0442\\u0435\\u043c\\u0430\\u0442\\u0438\\u043a\\u0430\",\"expected_students\":1,\"actual_students\":0,\"formula_id\":2,\"status\":\"scheduled\",\"notes\":null,\"created_at\":\"2025-11-16 16:52:54\",\"updated_at\":\"2025-11-16 16:52:54\"}', NULL, 'Урок удалён', '95.73.229.232', '2025-11-18 12:34:29'),
(65, 'formula_deleted', 'formula', 2, 1, NULL, NULL, '{\"id\":2,\"name\":\"\\u0418\\u043d\\u0434\\u0438\\u0432\\u0438\\u0434\\u0443\\u0430\\u043b\\u044c\\u043d\\u043e\\u0435 \\u0437\\u0430\\u043d\\u044f\\u0442\\u0438\\u0435\",\"type\":\"fixed\",\"description\":\"\\u0424\\u0438\\u043a\\u0441\\u0438\\u0440\\u043e\\u0432\\u0430\\u043d\\u043d\\u0430\\u044f \\u0441\\u0442\\u0430\\u0432\\u043a\\u0430 \\u0437\\u0430 \\u0438\\u043d\\u0434\\u0438\\u0432\\u0438\\u0434\\u0443\\u0430\\u043b\\u044c\\u043d\\u043e\\u0435 \\u0437\\u0430\\u043d\\u044f\\u0442\\u0438\\u0435\",\"min_payment\":null,\"per_student\":null,\"threshold\":null,\"fixed_amount\":900,\"expression\":null,\"active\":0,\"created_at\":\"2025-11-15 04:29:26\",\"updated_at\":\"2025-11-18 12:34:19\"}', NULL, 'Формула удалена', '95.73.229.232', '2025-11-18 12:34:37'),
(66, 'formula_deleted', 'formula', 1, 1, NULL, NULL, '{\"id\":1,\"name\":\"\\u0421\\u0442\\u0430\\u043d\\u0434\\u0430\\u0440\\u0442\\u043d\\u0430\\u044f \\u0433\\u0440\\u0443\\u043f\\u043f\\u043e\\u0432\\u0430\\u044f\",\"type\":\"min_plus_per\",\"description\":\"\\u041c\\u0438\\u043d\\u0438\\u043c\\u0443\\u043c 500\\u20bd + 150\\u20bd \\u0437\\u0430 \\u043a\\u0430\\u0436\\u0434\\u043e\\u0433\\u043e \\u0443\\u0447\\u0435\\u043d\\u0438\\u043a\\u0430 \\u043d\\u0430\\u0447\\u0438\\u043d\\u0430\\u044f \\u0441\\u043e \\u0432\\u0442\\u043e\\u0440\\u043e\\u0433\\u043e\",\"min_payment\":500,\"per_student\":150,\"threshold\":2,\"fixed_amount\":0,\"expression\":null,\"active\":0,\"created_at\":\"2025-11-15 04:29:26\",\"updated_at\":\"2025-11-18 10:03:04\"}', NULL, 'Формула удалена', '95.73.229.232', '2025-11-18 12:34:39'),
(67, 'student_created', 'student', 1, 1, NULL, NULL, NULL, '{\"name\":\"\\u0412\\u043b\\u0430\\u0434\\u0430\",\"class\":9,\"lesson_type\":\"individual\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-18 14:40:35'),
(68, 'student_created', 'student', 2, 1, NULL, NULL, NULL, '{\"name\":\"\\u041d\\u0430\\u0441\\u0442\\u044f\",\"class\":9,\"lesson_type\":\"individual\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-18 14:43:34'),
(69, 'student_updated', 'student', 2, 1, NULL, NULL, '{\"id\":2,\"name\":\"\\u041d\\u0430\\u0441\\u0442\\u044f\",\"phone\":null,\"student_telegram\":\"@nastch1\",\"student_whatsapp\":null,\"parent_phone\":null,\"parent_telegram\":null,\"parent_whatsapp\":\"79060815386\",\"email\":null,\"class\":9,\"lesson_type\":\"individual\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"2\\\": \\\"17:00\\\", \\\"4\\\": \\\"17:00\\\"}\",\"monthly_price\":5000,\"lesson_day\":null,\"lesson_time\":null,\"notes\":null,\"active\":1,\"created_at\":\"2025-11-18 14:43:34\",\"updated_at\":\"2025-11-18 14:43:34\"}', '{\"name\":\"\\u041d\\u0430\\u0441\\u0442\\u044f\",\"class\":9,\"lesson_type\":\"individual\"}', 'Обновлены данные ученика', '95.73.229.232', '2025-11-18 14:43:52'),
(70, 'student_deactivated', 'student', 1, 1, NULL, NULL, '{\"active\":1}', '{\"active\":0}', 'Ученик деактивирован', '95.73.229.232', '2025-11-18 14:44:45'),
(71, 'student_activated', 'student', 1, 1, NULL, NULL, '{\"active\":0}', '{\"active\":1}', 'Ученик активирован', '95.73.229.232', '2025-11-18 14:45:41'),
(72, 'student_created', 'student', 3, 1, NULL, NULL, NULL, '{\"name\":\"\\u0410\\u0440\\u0438\\u043d\\u0430\",\"class\":8,\"lesson_type\":\"group\",\"tier\":\"A\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-18 15:33:30'),
(73, 'student_created', 'student', 4, 1, NULL, NULL, NULL, '{\"name\":\"\\u0412\\u0430\\u043d\\u044f\",\"class\":8,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-18 15:34:42'),
(74, 'template_updated', 'template', 14, 1, NULL, NULL, '{\"id\":14,\"teacher_id\":1,\"day_of_week\":2,\"room\":1,\"time_start\":\"18:00:00\",\"time_end\":\"19:30:00\",\"lesson_type\":\"group\",\"subject\":null,\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\u0410\\u0440\\u0438\\u043d\\u0430\\\",\\\"\\u0412\\u0430\\u043d\\u044f\\\"]\",\"expected_students\":2,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-18 15:33:30\",\"updated_at\":\"2025-11-18 15:33:30\"}', '{\"teacher_id\":1,\"day_of_week\":2}', 'Обновлён шаблон урока', '95.73.229.232', '2025-11-20 01:41:01'),
(75, 'template_updated', 'template', 14, 1, NULL, NULL, '{\"id\":14,\"teacher_id\":1,\"day_of_week\":2,\"room\":1,\"time_start\":\"18:00:00\",\"time_end\":\"19:00:00\",\"lesson_type\":\"group\",\"subject\":null,\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\u0410\\u0440\\u0438\\u043d\\u0430\\\",\\\"\\u0412\\u0430\\u043d\\u044f\\\"]\",\"expected_students\":6,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-18 15:33:30\",\"updated_at\":\"2025-11-20 01:41:01\"}', '{\"teacher_id\":1,\"day_of_week\":2}', 'Обновлён шаблон урока', '95.73.229.232', '2025-11-20 01:41:19'),
(76, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.73.229.232', '2025-11-20 23:21:40'),
(77, 'teacher_updated', 'teacher', 1, 1, NULL, NULL, '{\"id\":1,\"name\":\"\\u041d\\u0438\\u043a\\u0438\\u0442\\u0438\\u043d \\u0421\\u0442\\u0430\\u043d\\u0438\\u0441\\u043b\\u0430\\u0432 \\u041e\\u043b\\u0435\\u0433\\u043e\\u0432\\u0438\\u0447\",\"telegram_id\":245710727,\"telegram_username\":\"Palomig\",\"phone\":\"+79103017110\",\"email\":null,\"formula_id_group\":null,\"formula_id_individual\":null,\"formula_id\":null,\"active\":1,\"notes\":\"\\u043b\\u0435\\u0433\\u0435\\u043d\\u0434\\u0430\",\"created_at\":\"2025-11-15 06:32:31\",\"updated_at\":\"2025-11-18 06:29:46\"}', '{\"name\":\"\\u041d\\u0438\\u043a\\u0438\\u0442\\u0438\\u043d \\u0421\\u0442\\u0430\\u043d\\u0438\\u0441\\u043b\\u0430\\u0432 \\u041e\\u043b\\u0435\\u0433\\u043e\\u0432\\u0438\\u0447\",\"phone\":\"+79103017110\",\"email\":\"\"}', 'Обновлены данные преподавателя', '95.73.229.232', '2025-11-21 00:04:06'),
(78, 'student_created', 'student', 5, 1, NULL, NULL, NULL, '{\"name\":\"\\u0413\\u043b\\u0435\\u0431\",\"class\":9,\"lesson_type\":\"individual\",\"tier\":\"B\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-21 00:26:23'),
(79, 'template_updated', 'template', 15, 1, NULL, NULL, '{\"id\":15,\"teacher_id\":1,\"day_of_week\":6,\"room\":1,\"time_start\":\"15:00:00\",\"time_end\":\"16:30:00\",\"lesson_type\":\"group\",\"subject\":null,\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\u0410\\u0440\\u0438\\u043d\\u0430\\\",\\\"\\u0412\\u0430\\u043d\\u044f\\\"]\",\"expected_students\":2,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-18 15:33:30\",\"updated_at\":\"2025-11-18 15:33:30\"}', '{\"teacher_id\":1,\"day_of_week\":6}', 'Обновлён шаблон урока', '95.73.229.232', '2025-11-21 00:27:00'),
(80, 'template_updated', 'template', 18, 1, NULL, NULL, '{\"id\":18,\"teacher_id\":1,\"day_of_week\":3,\"room\":1,\"time_start\":\"17:00:00\",\"time_end\":\"18:30:00\",\"lesson_type\":\"individual\",\"subject\":null,\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\\\u0413\\\\u043b\\\\u0435\\\\u0431\\\"]\",\"expected_students\":6,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-21 00:26:23\",\"updated_at\":\"2025-11-21 00:26:23\"}', '{\"teacher_id\":1,\"day_of_week\":3}', 'Обновлён шаблон урока', '95.73.229.232', '2025-11-21 00:36:04'),
(81, 'student_created', 'student', 6, 1, NULL, NULL, NULL, '{\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u043c\\u0430\\u043b\\u0435\\u043d\\u044c\\u043a\\u0438\\u0439)\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"S\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-21 02:38:54'),
(82, 'student_created', 'student', 7, 1, NULL, NULL, NULL, '{\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u0431\\u043e\\u043b\\u044c\\u0448\\u043e\\u0439)\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"S\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-21 02:39:30'),
(83, 'student_updated', 'student', 6, 1, NULL, NULL, '{\"id\":6,\"teacher_id\":1,\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u043c\\u0430\\u043b\\u0435\\u043d\\u044c\\u043a\\u0438\\u0439)\",\"student_telegram\":\"@snowol44\",\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":9,\"tier\":\"S\",\"lesson_type\":\"group\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"3\\\": \\\"16:00\\\", \\\"5\\\": \\\"16:00\\\"}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-21 02:38:54\",\"updated_at\":\"2025-11-21 02:38:54\"}', '{\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u043c\\u0430\\u043b\\u0435\\u043d\\u044c\\u043a\\u0438\\u0439)\",\"teacher_id\":1,\"tier\":\"S\",\"class\":9,\"lesson_type\":\"group\"}', 'Обновлены данные ученика', '95.73.229.232', '2025-11-21 02:39:41'),
(84, 'template_updated', 'template', 20, 1, NULL, NULL, '{\"id\":20,\"teacher_id\":1,\"day_of_week\":3,\"room\":1,\"time_start\":\"16:00:00\",\"time_end\":\"17:30:00\",\"lesson_type\":\"group\",\"subject\":null,\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\\\u0413\\\\u0440\\\\u0438\\\\u0448\\\\u0430 (\\\\u043c\\\\u0430\\\\u043b\\\\u0435\\\\u043d\\\\u044c\\\\u043a\\\\u0438\\\\u0439)\\\",\\\"\\\\u0413\\\\u0440\\\\u0438\\\\u0448\\\\u0430 (\\\\u0431\\\\u043e\\\\u043b\\\\u044c\\\\u0448\\\\u043e\\\\u0439)\\\"]\",\"expected_students\":6,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-21 02:38:54\",\"updated_at\":\"2025-11-21 02:48:00\"}', '{\"teacher_id\":1,\"day_of_week\":3}', 'Обновлён шаблон урока', '95.73.229.232', '2025-11-21 02:51:16'),
(85, 'student_created', 'student', 8, 1, NULL, NULL, NULL, '{\"name\":\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"B\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-21 02:54:19'),
(86, 'student_created', 'student', 9, 1, NULL, NULL, NULL, '{\"name\":\"\\u0410\\u043b\\u0435\\u043a\\u0441\\u0430\\u043d\\u0434\\u0440\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-21 02:55:26'),
(87, 'student_updated', 'student', 8, 1, NULL, NULL, '{\"id\":8,\"teacher_id\":1,\"name\":\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"student_telegram\":\"@Mirohodech\",\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":9,\"tier\":\"B\",\"lesson_type\":\"group\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"3\\\": \\\"16:00\\\", \\\"5\\\": \\\"16:00\\\"}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-21 02:54:19\",\"updated_at\":\"2025-11-21 02:54:19\"}', '{\"name\":\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"teacher_id\":1,\"tier\":\"B\",\"class\":9,\"lesson_type\":\"group\"}', 'Обновлены данные ученика', '95.73.229.232', '2025-11-21 02:55:39'),
(88, 'template_updated', 'template', 20, 1, NULL, NULL, '{\"id\":20,\"teacher_id\":1,\"day_of_week\":3,\"room\":2,\"time_start\":\"16:00:00\",\"time_end\":\"17:00:00\",\"lesson_type\":\"group\",\"subject\":\"\\u041c\\u0430\\u0442\\u0435\\u043c\\u0430\\u0442\\u0438\\u043a\\u0430\",\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\\\u0413\\\\u0440\\\\u0438\\\\u0448\\\\u0430 (\\\\u043c\\\\u0430\\\\u043b\\\\u0435\\\\u043d\\\\u044c\\\\u043a\\\\u0438\\\\u0439)\\\",\\\"\\\\u0413\\\\u0440\\\\u0438\\\\u0448\\\\u0430 (\\\\u0431\\\\u043e\\\\u043b\\\\u044c\\\\u0448\\\\u043e\\\\u0439)\\\",\\\"\\\\u041a\\\\u0438\\\\u0440\\\\u0438\\\\u043b\\\\u043b\\\",\\\"\\\\u0410\\\\u043b\\\\u0435\\\\u043a\\\\u0441\\\\u0430\\\\u043d\\\\u0434\\\\u0440\\\"]\",\"expected_students\":6,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-21 02:38:54\",\"updated_at\":\"2025-11-21 02:55:26\"}', '{\"teacher_id\":1,\"day_of_week\":3}', 'Обновлён шаблон урока', '95.73.229.232', '2025-11-21 02:56:02'),
(89, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.73.229.232', '2025-11-21 14:19:55'),
(90, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '141.255.164.210', '2025-11-21 15:33:17'),
(91, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '2a03:d000:4190:1aac:38cb:134b:b12d:260b', '2025-11-21 21:30:50'),
(92, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.24.68.108', '2025-11-21 22:07:42'),
(93, 'teacher_created', 'teacher', 2, 1, NULL, NULL, NULL, '{\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"phone\":\"\",\"email\":\"\"}', 'Создан новый преподаватель', '95.24.68.108', '2025-11-21 22:08:36'),
(94, 'teacher_updated', 'teacher', 2, 1, NULL, NULL, '{\"id\":2,\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"telegram_id\":null,\"telegram_username\":null,\"phone\":null,\"email\":null,\"formula_id_group\":null,\"formula_id_individual\":null,\"formula_id\":null,\"active\":1,\"notes\":null,\"created_at\":\"2025-11-21 22:08:36\",\"updated_at\":\"2025-11-21 22:08:36\"}', '{\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"phone\":\"+79017186366\",\"email\":\"hiallglhf@gmail.com\"}', 'Обновлены данные преподавателя', '95.24.68.108', '2025-11-21 22:08:47'),
(95, 'teacher_updated', 'teacher', 2, 1, NULL, NULL, '{\"id\":2,\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"telegram_id\":null,\"telegram_username\":null,\"phone\":\"+79017186366\",\"email\":\"hiallglhf@gmail.com\",\"formula_id_group\":null,\"formula_id_individual\":null,\"formula_id\":null,\"active\":1,\"notes\":null,\"created_at\":\"2025-11-21 22:08:36\",\"updated_at\":\"2025-11-21 22:08:47\"}', '{\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"phone\":\"+79017186366\",\"email\":\"\"}', 'Обновлены данные преподавателя', '95.24.68.108', '2025-11-21 22:09:13'),
(96, 'teacher_updated', 'teacher', 2, 1, NULL, NULL, '{\"id\":2,\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"telegram_id\":null,\"telegram_username\":\"hiallglhf\",\"phone\":\"+79017186366\",\"email\":null,\"formula_id_group\":null,\"formula_id_individual\":null,\"formula_id\":null,\"active\":1,\"notes\":null,\"created_at\":\"2025-11-21 22:08:36\",\"updated_at\":\"2025-11-21 22:09:13\"}', '{\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"phone\":\"+79017186366\",\"email\":\"\"}', 'Обновлены данные преподавателя', '95.24.68.108', '2025-11-21 22:09:21'),
(97, 'teacher_updated', 'teacher', 2, 1, NULL, NULL, '{\"id\":2,\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"telegram_id\":null,\"telegram_username\":\"hiallglhf\",\"phone\":\"+79017186366\",\"email\":null,\"formula_id_group\":null,\"formula_id_individual\":null,\"formula_id\":null,\"active\":1,\"notes\":null,\"created_at\":\"2025-11-21 22:08:36\",\"updated_at\":\"2025-11-21 22:09:21\"}', '{\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"phone\":\"+79017186366\",\"email\":\"\"}', 'Обновлены данные преподавателя', '95.24.68.108', '2025-11-21 22:09:36'),
(98, 'teacher_updated', 'teacher', 2, 1, NULL, NULL, '{\"id\":2,\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"telegram_id\":null,\"telegram_username\":\"hiallglhf\",\"phone\":\"+79017186366\",\"email\":null,\"formula_id_group\":null,\"formula_id_individual\":null,\"formula_id\":null,\"active\":1,\"notes\":\"\\u043d\\u0435 \\u043b\\u0435\\u0433\\u0435\\u043d\\u0434\\u0430\",\"created_at\":\"2025-11-21 22:08:36\",\"updated_at\":\"2025-11-21 22:09:36\"}', '{\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"phone\":\"+79017186366\",\"email\":\"\"}', 'Обновлены данные преподавателя', '95.24.68.108', '2025-11-21 22:18:46'),
(99, 'teacher_updated', 'teacher', 2, 1, NULL, NULL, '{\"id\":2,\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"telegram_id\":704366908,\"telegram_username\":\"hiallglhf\",\"phone\":\"+79017186366\",\"email\":null,\"formula_id_group\":null,\"formula_id_individual\":null,\"formula_id\":null,\"active\":1,\"notes\":\"\\u043d\\u0435 \\u043b\\u0435\\u0433\\u0435\\u043d\\u0434\\u0430\",\"created_at\":\"2025-11-21 22:08:36\",\"updated_at\":\"2025-11-21 22:18:46\"}', '{\"name\":\"\\u0420\\u0443\\u0441\\u043b\\u0430\\u043d \\u0420\\u043e\\u043c\\u0430\\u043d\\u043e\\u0432\\u0438\\u0447\",\"phone\":\"+79017186366\",\"email\":\"\"}', 'Обновлены данные преподавателя', '95.24.68.108', '2025-11-21 22:28:19'),
(100, 'student_created', 'student', 10, 1, NULL, NULL, NULL, '{\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"D\"}', 'Создан новый ученик', '95.24.68.108', '2025-11-21 22:29:43'),
(101, 'student_created', 'student', 11, 1, NULL, NULL, NULL, '{\"name\":\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"D\"}', 'Создан новый ученик', '95.24.68.108', '2025-11-21 22:29:56'),
(102, 'student_updated', 'student', 11, 1, NULL, NULL, '{\"id\":11,\"teacher_id\":2,\"name\":\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"student_telegram\":null,\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":9,\"tier\":\"D\",\"lesson_type\":\"group\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-21 22:29:56\",\"updated_at\":\"2025-11-21 22:29:56\"}', '{\"name\":\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"teacher_id\":2,\"tier\":\"D\",\"class\":9,\"lesson_type\":\"group\"}', 'Обновлены данные ученика', '95.24.68.108', '2025-11-21 22:30:16'),
(103, 'student_updated', 'student', 7, 1, NULL, NULL, '{\"id\":7,\"teacher_id\":1,\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u0431\\u043e\\u043b\\u044c\\u0448\\u043e\\u0439)\",\"student_telegram\":\"Greenaloe\",\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":9,\"tier\":\"S\",\"lesson_type\":\"group\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"3\\\": \\\"16:00\\\", \\\"5\\\": \\\"16:00\\\"}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-21 02:39:30\",\"updated_at\":\"2025-11-21 02:39:30\"}', '{\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u0431\\u043e\\u043b\\u044c\\u0448\\u043e\\u0439)\",\"teacher_id\":1,\"tier\":\"S\",\"class\":9,\"lesson_type\":\"group\"}', 'Обновлены данные ученика', '95.24.68.108', '2025-11-21 22:30:36'),
(104, 'teacher_updated', 'teacher', 1, 1, NULL, NULL, '{\"id\":1,\"name\":\"\\u041d\\u0438\\u043a\\u0438\\u0442\\u0438\\u043d \\u0421\\u0442\\u0430\\u043d\\u0438\\u0441\\u043b\\u0430\\u0432 \\u041e\\u043b\\u0435\\u0433\\u043e\\u0432\\u0438\\u0447\",\"display_name\":\"\\u041d\\u0438\\u043a\\u0438\\u0442\\u0438\\u043d\",\"telegram_id\":245710727,\"telegram_username\":\"Palomig\",\"phone\":\"+79103017110\",\"email\":null,\"formula_id_group\":4,\"formula_id_individual\":3,\"formula_id\":null,\"active\":1,\"notes\":\"\\u043b\\u0435\\u0433\\u0435\\u043d\\u0434\\u0430\",\"created_at\":\"2025-11-15 06:32:31\",\"updated_at\":\"2025-11-21 22:58:44\"}', '{\"name\":\"\\u041d\\u0438\\u043a\\u0438\\u0442\\u0438\\u043d \\u0421\\u0442\\u0430\\u043d\\u0438\\u0441\\u043b\\u0430\\u0432 \\u041e\\u043b\\u0435\\u0433\\u043e\\u0432\\u0438\\u0447\",\"phone\":\"+79103017110\",\"email\":\"\"}', 'Обновлены данные преподавателя', '95.73.229.232', '2025-11-21 23:00:33'),
(115, 'template_created', 'template', 24, 1, NULL, NULL, NULL, '{\"teacher_id\":2,\"day_of_week\":5,\"time\":\"21:00:00-22:00:00\"}', 'Создан шаблон урока', '95.24.68.108', '2025-11-21 23:13:11'),
(116, 'telegram_webhook_setup', 'settings', NULL, 1, NULL, NULL, NULL, '{\"webhook_url\":\"https:\\/\\/\\u044d\\u0432\\u0440\\u0438\\u0443\\u043c.\\u0440\\u0444\\/zarplata\\/bot\\/webhook.php\"}', 'Настроен Telegram webhook', '95.73.229.232', '2025-11-21 23:15:34'),
(117, 'clear_students', 'students', NULL, 1, NULL, NULL, NULL, '{\"count_deleted\":11}', 'Очищена таблица учеников через тесты', '95.24.68.108', '2025-11-21 23:17:28'),
(118, 'student_created', 'student', 12, 1, NULL, NULL, NULL, '{\"name\":\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"D\"}', 'Создан новый ученик', '95.24.68.108', '2025-11-21 23:24:00'),
(119, 'student_created', 'student', 13, 1, NULL, NULL, NULL, '{\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u0431\\u043e\\u043b\\u044c\\u0448\\u043e\\u0439)\",\"class\":null,\"lesson_type\":\"group\",\"tier\":\"D\"}', 'Создан новый ученик', '95.24.68.108', '2025-11-21 23:24:24'),
(120, 'template_deleted', 'template', 24, 1, NULL, NULL, '{\"id\":24,\"teacher_id\":2,\"day_of_week\":5,\"room\":1,\"time_start\":\"21:00:00\",\"time_end\":\"22:00:00\",\"lesson_type\":\"group\",\"subject\":\"\\u0424\\u0438\\u0437\\u0438\\u043a\\u0430\",\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\\\"]\",\"expected_students\":6,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-21 23:13:11\",\"updated_at\":\"2025-11-21 23:13:11\"}', NULL, 'Шаблон удалён', '95.24.68.108', '2025-11-21 23:25:06'),
(121, 'clear_students', 'students', NULL, 1, NULL, NULL, NULL, '{\"students_deleted\":2,\"templates_deleted\":21}', 'Очищена таблица учеников и шаблонов уроков через тесты', '95.73.229.232', '2025-11-21 23:29:29'),
(122, 'student_created', 'student', 14, 1, NULL, NULL, NULL, '{\"name\":\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"B\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-21 23:38:06'),
(123, 'clear_students', 'students', NULL, 1, NULL, NULL, NULL, '{\"students_deleted\":1,\"templates_deleted\":4}', 'Очищена таблица учеников и шаблонов уроков через тесты', '95.73.229.232', '2025-11-21 23:50:41'),
(124, 'student_created', 'student', 15, 1, NULL, NULL, NULL, '{\"name\":\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"B\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-21 23:51:11'),
(125, 'template_updated', 'template', 32, 1, NULL, NULL, '{\"id\":32,\"teacher_id\":2,\"day_of_week\":3,\"room\":1,\"time_start\":\"16:00:00\",\"time_end\":\"17:00:00\",\"lesson_type\":\"group\",\"subject\":null,\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\\\u041a\\\\u0438\\\\u0440\\\\u0438\\\\u043b\\\\u043b\\\"]\",\"expected_students\":6,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-21 23:51:11\",\"updated_at\":\"2025-11-21 23:51:11\"}', '{\"teacher_id\":2,\"day_of_week\":3}', 'Обновлён шаблон урока', '95.73.229.232', '2025-11-22 00:06:09'),
(126, 'template_updated', 'template', 31, 1, NULL, NULL, '{\"id\":31,\"teacher_id\":1,\"day_of_week\":3,\"room\":1,\"time_start\":\"15:00:00\",\"time_end\":\"16:00:00\",\"lesson_type\":\"group\",\"subject\":null,\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\\\u041a\\\\u0438\\\\u0440\\\\u0438\\\\u043b\\\\u043b\\\"]\",\"expected_students\":6,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-21 23:51:11\",\"updated_at\":\"2025-11-21 23:51:11\"}', '{\"teacher_id\":1,\"day_of_week\":3}', 'Обновлён шаблон урока', '95.73.229.232', '2025-11-22 00:14:05'),
(127, 'clear_students', 'students', NULL, 1, NULL, NULL, NULL, '{\"students_deleted\":1,\"templates_deleted\":4}', 'Очищена таблица учеников и шаблонов уроков через тесты', '95.73.229.232', '2025-11-22 00:21:30'),
(128, 'student_created', 'student', 16, 1, NULL, NULL, NULL, '{\"name\":\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-22 00:22:45'),
(129, 'teacher_updated', 'teacher', 1, 1, NULL, NULL, '{\"id\":1,\"name\":\"\\u041d\\u0438\\u043a\\u0438\\u0442\\u0438\\u043d \\u0421\\u0442\\u0430\\u043d\\u0438\\u0441\\u043b\\u0430\\u0432 \\u041e\\u043b\\u0435\\u0433\\u043e\\u0432\\u0438\\u0447\",\"display_name\":\"Palomig\",\"telegram_id\":245710727,\"telegram_username\":\"Palomig\",\"phone\":\"+79103017110\",\"email\":null,\"formula_id_group\":4,\"formula_id_individual\":3,\"formula_id\":null,\"active\":1,\"notes\":\"\\u043b\\u0435\\u0433\\u0435\\u043d\\u0434\\u0430\",\"created_at\":\"2025-11-15 06:32:31\",\"updated_at\":\"2025-11-21 23:00:33\"}', '{\"name\":\"\\u0421\\u0442\\u0430\\u043d\\u0438\\u0441\\u043b\\u0430\\u0432 \\u041e\\u043b\\u0435\\u0433\\u043e\\u0432\\u0438\\u0447\",\"phone\":\"+79103017110\",\"email\":\"\"}', 'Обновлены данные преподавателя', '95.73.229.232', '2025-11-22 00:29:32'),
(130, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '37.120.209.146', '2025-11-23 15:38:48'),
(131, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.24.68.108', '2025-11-23 16:09:34'),
(132, 'student_created', 'student', 17, 1, NULL, NULL, NULL, '{\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u0431\\u043e\\u043b\\u044c\\u0448\\u043e\\u0439)\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '95.24.68.108', '2025-11-23 16:38:06'),
(133, 'student_created', 'student', 18, 1, NULL, NULL, NULL, '{\"name\":\"1\",\"class\":8,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '95.24.68.108', '2025-11-23 16:38:40'),
(134, 'student_updated', 'student', 17, 1, NULL, NULL, '{\"id\":17,\"teacher_id\":1,\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u0431\\u043e\\u043b\\u044c\\u0448\\u043e\\u0439)\",\"student_telegram\":null,\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":9,\"tier\":\"C\",\"lesson_type\":\"group\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"3\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"16:00\\\", \\\"teacher_id\\\": \\\"\\\"}, {\\\"room\\\": 1, \\\"time\\\": \\\"17:00\\\", \\\"teacher_id\\\": \\\"\\\"}], \\\"5\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"16:00\\\", \\\"teacher_id\\\": \\\"\\\"}, {\\\"room\\\": 1, \\\"time\\\": \\\"17:00\\\", \\\"teacher_id\\\": \\\"\\\"}]}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-23 16:38:06\",\"updated_at\":\"2025-11-23 16:38:06\"}', '{\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u0431\\u043e\\u043b\\u044c\\u0448\\u043e\\u0439)\",\"teacher_id\":1,\"tier\":\"C\",\"class\":9,\"lesson_type\":\"group\"}', 'Обновлены данные ученика', '95.24.68.108', '2025-11-23 16:38:51'),
(135, 'student_created', 'student', 19, 1, NULL, NULL, NULL, '{\"name\":\"2\",\"class\":null,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '95.24.68.108', '2025-11-23 16:39:06'),
(136, 'student_created', 'student', 20, 1, NULL, NULL, NULL, '{\"name\":\"3\",\"class\":null,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '95.24.68.108', '2025-11-23 16:39:19'),
(137, 'student_created', 'student', 21, 1, NULL, NULL, NULL, '{\"name\":\"4\",\"class\":null,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '95.24.68.108', '2025-11-23 16:39:29'),
(141, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '77.51.71.203', '2025-11-24 22:39:49'),
(142, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.72.192.144', '2025-11-25 13:22:46'),
(143, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '37.120.209.146', '2025-11-25 17:41:23'),
(144, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '37.120.209.146', '2025-11-25 17:41:34'),
(145, 'clear_students', 'students', NULL, 1, NULL, NULL, NULL, '{\"students_deleted\":6,\"templates_deleted\":9}', 'Очищена таблица учеников и шаблонов уроков через тесты', '85.94.7.223', '2025-11-28 02:03:52'),
(146, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '85.94.7.223', '2025-11-30 16:48:49'),
(147, 'user_logout', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Выход из системы', '85.94.7.223', '2025-11-30 18:15:41'),
(148, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '85.94.7.223', '2025-11-30 18:15:43'),
(149, 'student_created', 'student', 22, 1, NULL, NULL, NULL, '{\"name\":\"\\u0412\\u043b\\u0430\\u0434\\u0430\",\"class\":9,\"lesson_type\":\"individual\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 18:37:00'),
(150, 'student_created', 'student', 23, 1, NULL, NULL, NULL, '{\"name\":\"\\u041b\\u0451\\u0448\\u0430\",\"class\":6,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:27:06'),
(151, 'student_created', 'student', 24, 1, NULL, NULL, NULL, '{\"name\":\"\\u041b\\u0435\\u0440\\u0430\",\"class\":7,\"lesson_type\":\"group\",\"tier\":\"B\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:27:41'),
(152, 'student_created', 'student', 25, 1, NULL, NULL, NULL, '{\"name\":\"\\u041d\\u0430\\u0441\\u0442\\u044f\",\"class\":8,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:28:35'),
(153, 'student_created', 'student', 26, 1, NULL, NULL, NULL, '{\"name\":\"\\u041a\\u043e\\u043b\\u044f\",\"class\":7,\"lesson_type\":\"group\",\"tier\":\"B\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:29:25'),
(154, 'student_created', 'student', 27, 1, NULL, NULL, NULL, '{\"name\":\"\\u0410\\u043d\\u0442\\u043e\\u043d\\u0438\\u0439\",\"class\":6,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:30:07'),
(155, 'student_created', 'student', 28, 1, NULL, NULL, NULL, '{\"name\":\"\\u041c\\u0430\\u0448\\u0430\",\"class\":5,\"lesson_type\":\"individual\",\"tier\":\"S\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:31:11'),
(156, 'student_created', 'student', 29, 1, NULL, NULL, NULL, '{\"name\":\"\\u0421\\u0430\\u0448\\u0430\",\"class\":6,\"lesson_type\":\"individual\",\"tier\":\"S\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:31:50'),
(157, 'student_created', 'student', 30, 1, NULL, NULL, NULL, '{\"name\":\"\\u0412\\u0438\\u043a\\u0430\",\"class\":6,\"lesson_type\":\"individual\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:32:22'),
(158, 'student_created', 'student', 31, 1, NULL, NULL, NULL, '{\"name\":\"\\u041d\\u0430\\u0441\\u0442\\u044f\",\"class\":null,\"lesson_type\":\"individual\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:33:01'),
(159, 'student_created', 'student', 32, 1, NULL, NULL, NULL, '{\"name\":\"\\u0410\\u0440\\u0438\\u043d\\u0430\",\"class\":8,\"lesson_type\":\"group\",\"tier\":\"A\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:33:35'),
(160, 'student_created', 'student', 33, 1, NULL, NULL, NULL, '{\"name\":\"\\u0412\\u0430\\u043d\\u044f\",\"class\":null,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:34:06'),
(161, 'student_created', 'student', 34, 1, NULL, NULL, NULL, '{\"name\":\"\\u041c\\u0438\\u043b\\u0430\\u043d\\u0430\",\"class\":7,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:34:48'),
(162, 'student_created', 'student', 35, 1, NULL, NULL, NULL, '{\"name\":\"\\u0410\\u0440\\u0442\\u0435\\u043c\",\"class\":8,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:35:38'),
(163, 'student_created', 'student', 36, 1, NULL, NULL, NULL, '{\"name\":\"\\u041a\\u043e\\u043b\\u044f\",\"class\":2,\"lesson_type\":\"individual\",\"tier\":\"S\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:36:26'),
(164, 'student_created', 'student', 37, 1, NULL, NULL, NULL, '{\"name\":\"\\u041c\\u0430\\u0442\\u0432\\u0435\\u0439\",\"class\":null,\"lesson_type\":\"individual\",\"tier\":\"D\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:37:05'),
(165, 'student_created', 'student', 38, 1, NULL, NULL, NULL, '{\"name\":\"\\u0412\\u043b\\u0430\\u0434\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"B\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:37:41'),
(166, 'student_created', 'student', 39, 1, NULL, NULL, NULL, '{\"name\":\"\\u0413\\u043b\\u0435\\u0431\",\"class\":null,\"lesson_type\":\"group\",\"tier\":\"B\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:37:57'),
(167, 'student_updated', 'student', 31, 1, NULL, NULL, '{\"id\":31,\"teacher_id\":1,\"name\":\"\\u041d\\u0430\\u0441\\u0442\\u044f\",\"student_telegram\":null,\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":null,\"tier\":\"C\",\"lesson_type\":\"individual\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"2\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"17:00\\\", \\\"teacher_id\\\": 1}], \\\"4\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"17:00\\\", \\\"teacher_id\\\": 1}]}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-30 19:33:01\",\"updated_at\":\"2025-11-30 19:33:01\"}', '{\"name\":\"\\u041d\\u0430\\u0441\\u0442\\u044f\",\"teacher_id\":1,\"tier\":\"C\",\"class\":9,\"lesson_type\":\"individual\"}', 'Обновлены данные ученика', '85.94.7.223', '2025-11-30 19:38:22'),
(168, 'student_updated', 'student', 37, 1, NULL, NULL, '{\"id\":37,\"teacher_id\":1,\"name\":\"\\u041c\\u0430\\u0442\\u0432\\u0435\\u0439\",\"student_telegram\":null,\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":null,\"tier\":\"D\",\"lesson_type\":\"individual\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"2\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"20:00\\\", \\\"teacher_id\\\": 1}], \\\"5\\\": [{\\\"room\\\": 2, \\\"time\\\": \\\"19:00\\\", \\\"teacher_id\\\": 1}]}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-30 19:37:05\",\"updated_at\":\"2025-11-30 19:37:05\"}', '{\"name\":\"\\u041c\\u0430\\u0442\\u0432\\u0435\\u0439\",\"teacher_id\":1,\"tier\":\"D\",\"class\":6,\"lesson_type\":\"individual\"}', 'Обновлены данные ученика', '85.94.7.223', '2025-11-30 19:38:29'),
(169, 'student_updated', 'student', 39, 1, NULL, NULL, '{\"id\":39,\"teacher_id\":1,\"name\":\"\\u0413\\u043b\\u0435\\u0431\",\"student_telegram\":null,\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":null,\"tier\":\"B\",\"lesson_type\":\"group\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"1\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"15:00\\\", \\\"teacher_id\\\": 1}], \\\"3\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"15:00\\\", \\\"teacher_id\\\": 1}]}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-30 19:37:57\",\"updated_at\":\"2025-11-30 19:37:57\"}', '{\"name\":\"\\u0413\\u043b\\u0435\\u0431\",\"teacher_id\":1,\"tier\":\"B\",\"class\":9,\"lesson_type\":\"group\"}', 'Обновлены данные ученика', '85.94.7.223', '2025-11-30 19:38:38');
INSERT INTO `audit_log` (`id`, `action_type`, `entity_type`, `entity_id`, `user_id`, `teacher_id`, `telegram_id`, `old_value`, `new_value`, `notes`, `ip_address`, `created_at`) VALUES
(170, 'student_updated', 'student', 33, 1, NULL, NULL, '{\"id\":33,\"teacher_id\":1,\"name\":\"\\u0412\\u0430\\u043d\\u044f\",\"student_telegram\":null,\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":null,\"tier\":\"C\",\"lesson_type\":\"group\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"2\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"18:00\\\", \\\"teacher_id\\\": 1}], \\\"6\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"15:00\\\", \\\"teacher_id\\\": 1}]}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-30 19:34:06\",\"updated_at\":\"2025-11-30 19:34:06\"}', '{\"name\":\"\\u0412\\u0430\\u043d\\u044f\",\"teacher_id\":1,\"tier\":\"C\",\"class\":8,\"lesson_type\":\"group\"}', 'Обновлены данные ученика', '85.94.7.223', '2025-11-30 19:38:48'),
(171, 'student_created', 'student', 40, 1, NULL, NULL, NULL, '{\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u0431)\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"A\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:39:35'),
(172, 'student_created', 'student', 41, 1, NULL, NULL, NULL, '{\"name\":\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u043c)\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"S\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:39:57'),
(173, 'student_created', 'student', 42, 1, NULL, NULL, NULL, '{\"name\":\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"B\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:41:19'),
(174, 'student_created', 'student', 43, 1, NULL, NULL, NULL, '{\"name\":\"\\u0421\\u0430\\u043d\\u044f\",\"class\":null,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:41:37'),
(175, 'student_created', 'student', 44, 1, NULL, NULL, NULL, '{\"name\":\"\\u0422\\u0438\\u0445\\u043e\\u043d\",\"class\":9,\"lesson_type\":\"group\",\"tier\":\"A\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:42:57'),
(176, 'student_created', 'student', 45, 1, NULL, NULL, NULL, '{\"name\":\"\\u0410\\u043b\\u0438\\u0441\\u0430\",\"class\":null,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:43:29'),
(177, 'student_updated', 'student', 45, 1, NULL, NULL, '{\"id\":45,\"teacher_id\":1,\"name\":\"\\u0410\\u043b\\u0438\\u0441\\u0430\",\"student_telegram\":null,\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":null,\"tier\":\"C\",\"lesson_type\":\"group\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"3\\\": [{\\\"room\\\": 2, \\\"time\\\": \\\"19:00\\\", \\\"teacher_id\\\": 1}], \\\"6\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"11:00\\\", \\\"teacher_id\\\": 1}]}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-30 19:43:29\",\"updated_at\":\"2025-11-30 19:43:29\"}', '{\"name\":\"\\u0410\\u043b\\u0438\\u0441\\u0430\",\"teacher_id\":1,\"tier\":\"C\",\"class\":null,\"lesson_type\":\"individual\"}', 'Обновлены данные ученика', '85.94.7.223', '2025-11-30 19:43:54'),
(178, 'student_updated', 'student', 43, 1, NULL, NULL, '{\"id\":43,\"teacher_id\":1,\"name\":\"\\u0421\\u0430\\u043d\\u044f\",\"student_telegram\":null,\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":null,\"tier\":\"C\",\"lesson_type\":\"group\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"3\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"16:00\\\", \\\"teacher_id\\\": 1}], \\\"5\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"16:00\\\", \\\"teacher_id\\\": 1}]}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-30 19:41:37\",\"updated_at\":\"2025-11-30 19:41:37\"}', '{\"name\":\"\\u0421\\u0430\\u043d\\u044f\",\"teacher_id\":1,\"tier\":\"C\",\"class\":9,\"lesson_type\":\"group\"}', 'Обновлены данные ученика', '85.94.7.223', '2025-11-30 19:44:02'),
(179, 'student_updated', 'student', 45, 1, NULL, NULL, '{\"id\":45,\"teacher_id\":1,\"name\":\"\\u0410\\u043b\\u0438\\u0441\\u0430\",\"student_telegram\":null,\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":null,\"tier\":\"C\",\"lesson_type\":\"individual\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"3\\\": [{\\\"room\\\": 2, \\\"time\\\": \\\"19:00\\\", \\\"teacher_id\\\": 1}], \\\"6\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"11:00\\\", \\\"teacher_id\\\": 1}]}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-30 19:43:29\",\"updated_at\":\"2025-11-30 19:43:54\"}', '{\"name\":\"\\u0410\\u043b\\u0438\\u0441\\u0430\",\"teacher_id\":1,\"tier\":\"C\",\"class\":8,\"lesson_type\":\"individual\"}', 'Обновлены данные ученика', '85.94.7.223', '2025-11-30 19:44:11'),
(180, 'student_created', 'student', 46, 1, NULL, NULL, NULL, '{\"name\":\"\\u041b\\u0438\\u0437\\u0430\",\"class\":null,\"lesson_type\":\"individual\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:45:17'),
(181, 'student_updated', 'student', 46, 1, NULL, NULL, '{\"id\":46,\"teacher_id\":1,\"name\":\"\\u041b\\u0438\\u0437\\u0430\",\"student_telegram\":null,\"student_whatsapp\":null,\"parent_name\":null,\"parent_telegram\":null,\"parent_whatsapp\":null,\"class\":null,\"tier\":\"C\",\"lesson_type\":\"individual\",\"payment_type_group\":\"monthly\",\"payment_type_individual\":\"per_lesson\",\"price_group\":5000,\"price_individual\":1500,\"schedule\":\"{\\\"4\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"15:00\\\", \\\"teacher_id\\\": 1}], \\\"6\\\": [{\\\"room\\\": 1, \\\"time\\\": \\\"13:00\\\", \\\"teacher_id\\\": 1}]}\",\"notes\":null,\"active\":1,\"created_at\":\"2025-11-30 19:45:17\",\"updated_at\":\"2025-11-30 19:45:17\"}', '{\"name\":\"\\u041b\\u0438\\u0437\\u0430\",\"teacher_id\":1,\"tier\":\"C\",\"class\":9,\"lesson_type\":\"individual\"}', 'Обновлены данные ученика', '85.94.7.223', '2025-11-30 19:45:28'),
(182, 'student_created', 'student', 47, 1, NULL, NULL, NULL, '{\"name\":\"\\u0423\\u043b\\u044c\\u044f\\u043d\\u0430\",\"class\":7,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:46:15'),
(183, 'student_created', 'student', 48, 1, NULL, NULL, NULL, '{\"name\":\"\\u041c\\u0430\\u0448\\u0430\",\"class\":6,\"lesson_type\":\"group\",\"tier\":\"B\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:47:14'),
(184, 'student_created', 'student', 49, 1, NULL, NULL, NULL, '{\"name\":\"\\u0412\\u0438\\u043a\\u0430\",\"class\":6,\"lesson_type\":\"group\",\"tier\":\"A\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:47:38'),
(185, 'student_created', 'student', 50, 1, NULL, NULL, NULL, '{\"name\":\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"class\":6,\"lesson_type\":\"group\",\"tier\":\"A\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:48:18'),
(186, 'student_created', 'student', 51, 1, NULL, NULL, NULL, '{\"name\":\"\\u0422\\u0438\\u043c\\u043e\\u0444\\u0435\\u0439\",\"class\":3,\"lesson_type\":\"individual\",\"tier\":\"S\"}', 'Создан новый ученик', '85.94.7.223', '2025-11-30 19:48:56'),
(187, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '95.24.64.228', '2025-11-30 19:49:49'),
(188, 'template_updated', 'template', 66, 1, NULL, NULL, '{\"id\":66,\"teacher_id\":1,\"day_of_week\":1,\"room\":1,\"time_start\":\"15:00:00\",\"time_end\":\"16:00:00\",\"lesson_type\":\"group\",\"subject\":null,\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\\\u0412\\\\u043b\\\\u0430\\\\u0434\\\",\\\"\\\\u0413\\\\u043b\\\\u0435\\\\u0431\\\"]\",\"expected_students\":6,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-30 19:37:41\",\"updated_at\":\"2025-11-30 19:38:38\"}', '{\"teacher_id\":1,\"day_of_week\":1}', 'Обновлён шаблон урока', '85.94.7.223', '2025-11-30 19:50:25'),
(189, 'template_updated', 'template', 44, 1, NULL, NULL, '{\"id\":44,\"teacher_id\":1,\"day_of_week\":1,\"room\":1,\"time_start\":\"16:00:00\",\"time_end\":\"17:00:00\",\"lesson_type\":\"individual\",\"subject\":null,\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\\\u0412\\\\u043b\\\\u0430\\\\u0434\\\\u0430\\\"]\",\"expected_students\":1,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-30 18:37:00\",\"updated_at\":\"2025-11-30 18:37:00\"}', '{\"teacher_id\":1,\"day_of_week\":1}', 'Обновлён шаблон урока', '85.94.7.223', '2025-11-30 19:50:33'),
(190, 'payments_cleared_all', 'payment', NULL, 1, NULL, NULL, NULL, '{\"deleted_payments\":56,\"deleted_audit_logs\":39}', 'Удалены ВСЕ выплаты из системы', '85.94.7.223', '2025-12-01 02:17:27'),
(191, 'students_migrated', 'template', 44, 1, NULL, NULL, '[\"Влада\"]', '[\"Влада (9 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(192, 'students_migrated', 'template', 45, 1, NULL, NULL, '[\"\\u0412\\u043b\\u0430\\u0434\\u0430\"]', '[\"Влада (9 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(193, 'students_migrated', 'template', 46, 1, NULL, NULL, '[\"\\u0412\\u043b\\u0430\\u0434\\u0430\"]', '[\"Влада (9 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(194, 'students_migrated', 'template', 47, 1, NULL, NULL, '[\"\\u041b\\u0451\\u0448\\u0430\",\"\\u041b\\u0435\\u0440\\u0430\",\"\\u041a\\u043e\\u043b\\u044f\",\"\\u0410\\u043d\\u0442\\u043e\\u043d\\u0438\\u0439\"]', '[\"Лёша (6 кл.)\",\"Лера (7 кл.)\",\"Коля\",\"Антоний (6 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(195, 'students_migrated', 'template', 48, 1, NULL, NULL, '[\"\\u041b\\u0451\\u0448\\u0430\",\"\\u041b\\u0435\\u0440\\u0430\"]', '[\"Лёша (6 кл.)\",\"Лера (7 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(196, 'students_migrated', 'template', 49, 1, NULL, NULL, '[\"\\u041a\\u043e\\u043b\\u044f\",\"\\u0423\\u043b\\u044c\\u044f\\u043d\\u0430\",\"\\u041c\\u0430\\u0448\\u0430\",\"\\u0412\\u0438\\u043a\\u0430\",\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\"]', '[\"Коля\",\"Ульяна (7 кл.)\",\"Маша\",\"Вика\",\"Кирилл\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(197, 'students_migrated', 'template', 50, 1, NULL, NULL, '[\"\\u0410\\u043d\\u0442\\u043e\\u043d\\u0438\\u0439\",\"\\u041c\\u0438\\u043b\\u0430\\u043d\\u0430\",\"\\u0423\\u043b\\u044c\\u044f\\u043d\\u0430\",\"\\u041c\\u0430\\u0448\\u0430\",\"\\u0412\\u0438\\u043a\\u0430\",\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\"]', '[\"Антоний (6 кл.)\",\"Милана (7 кл.)\",\"Ульяна (7 кл.)\",\"Маша\",\"Вика\",\"Кирилл\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(198, 'students_migrated', 'template', 54, 1, NULL, NULL, '[\"\\u0421\\u0430\\u0448\\u0430\"]', '[\"Саша (6 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(199, 'students_migrated', 'template', 55, 1, NULL, NULL, '[\"\\u0421\\u0430\\u0448\\u0430\"]', '[\"Саша (6 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(200, 'students_migrated', 'template', 60, 1, NULL, NULL, '[\"\\u0410\\u0440\\u0438\\u043d\\u0430\",\"\\u041c\\u0438\\u043b\\u0430\\u043d\\u0430\",\"\\u0410\\u0440\\u0442\\u0435\\u043c\",\"\\u0412\\u0430\\u043d\\u044f\"]', '[\"Арина (8 кл.)\",\"Милана (7 кл.)\",\"Артем (8 кл.)\",\"Ваня (8 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(201, 'students_migrated', 'template', 61, 1, NULL, NULL, '[\"\\u0410\\u0440\\u0438\\u043d\\u0430\",\"\\u0410\\u0440\\u0442\\u0435\\u043c\",\"\\u0412\\u0430\\u043d\\u044f\",\"\\u0422\\u0438\\u0445\\u043e\\u043d\"]', '[\"Арина (8 кл.)\",\"Артем (8 кл.)\",\"Ваня (8 кл.)\",\"Тихон (9 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(202, 'students_migrated', 'template', 66, 1, NULL, NULL, '[\"Влад\",\"Глеб\"]', '[\"Влад (9 кл.)\",\"Глеб (9 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(203, 'students_migrated', 'template', 67, 1, NULL, NULL, '[\"\\u0412\\u043b\\u0430\\u0434\",\"\\u0413\\u043b\\u0435\\u0431\"]', '[\"Влад (9 кл.)\",\"Глеб (9 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(204, 'students_migrated', 'template', 70, 1, NULL, NULL, '[\"\\u041c\\u0430\\u0442\\u0432\\u0435\\u0439\"]', '[\"Матвей (6 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(205, 'students_migrated', 'template', 71, 1, NULL, NULL, '[\"\\u041c\\u0430\\u0442\\u0432\\u0435\\u0439\"]', '[\"Матвей (6 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(206, 'students_migrated', 'template', 72, 1, NULL, NULL, '[\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u0431)\",\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u043c)\",\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"\\u0421\\u0430\\u043d\\u044f\"]', '[\"Гриша (б) (9 кл.)\",\"Гриша (м) (9 кл.)\",\"Кирилл\",\"Саня (9 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(207, 'students_migrated', 'template', 73, 1, NULL, NULL, '[\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u0431)\",\"\\u0413\\u0440\\u0438\\u0448\\u0430 (\\u043c)\",\"\\u041a\\u0438\\u0440\\u0438\\u043b\\u043b\",\"\\u0421\\u0430\\u043d\\u044f\"]', '[\"Гриша (б) (9 кл.)\",\"Гриша (м) (9 кл.)\",\"Кирилл\",\"Саня (9 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(208, 'students_migrated', 'template', 74, 1, NULL, NULL, '[\"\\u0422\\u0438\\u0445\\u043e\\u043d\"]', '[\"Тихон (9 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(209, 'students_migrated', 'template', 79, 1, NULL, NULL, '[\"\\u0410\\u043b\\u0438\\u0441\\u0430\"]', '[\"Алиса (8 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(210, 'students_migrated', 'template', 80, 1, NULL, NULL, '[\"\\u0410\\u043b\\u0438\\u0441\\u0430\"]', '[\"Алиса (8 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(211, 'students_migrated', 'template', 83, 1, NULL, NULL, '[\"\\u041b\\u0438\\u0437\\u0430\"]', '[\"Лиза (9 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(212, 'students_migrated', 'template', 84, 1, NULL, NULL, '[\"\\u041b\\u0438\\u0437\\u0430\"]', '[\"Лиза (9 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(213, 'students_migrated', 'template', 85, 1, NULL, NULL, '[\"\\u0422\\u0438\\u043c\\u043e\\u0444\\u0435\\u0439\"]', '[\"Тимофей (3 кл.)\"]', 'Миграция учеников в новый формат', '85.94.7.223', '2025-12-01 02:38:01'),
(214, 'template_updated', 'template', 51, 1, NULL, NULL, '{\"id\":51,\"teacher_id\":1,\"day_of_week\":1,\"room\":1,\"time_start\":\"18:00:00\",\"time_end\":\"19:00:00\",\"lesson_type\":\"individual\",\"subject\":null,\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\\\u041c\\\\u0430\\\\u0448\\\\u0430\\\"]\",\"expected_students\":1,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-30 19:31:11\",\"updated_at\":\"2025-11-30 19:31:11\"}', '{\"teacher_id\":1,\"day_of_week\":1}', 'Обновлён шаблон урока', '85.94.7.223', '2025-12-01 02:44:17'),
(215, 'template_updated', 'template', 47, 1, NULL, NULL, '{\"id\":47,\"teacher_id\":1,\"day_of_week\":1,\"room\":1,\"time_start\":\"17:00:00\",\"time_end\":\"18:00:00\",\"lesson_type\":\"group\",\"subject\":null,\"tier\":\"C\",\"grades\":null,\"students\":\"[\\\"\\u041b\\u0451\\u0448\\u0430 (6 \\u043a\\u043b.)\\\",\\\"\\u041b\\u0435\\u0440\\u0430 (7 \\u043a\\u043b.)\\\",\\\"\\u041a\\u043e\\u043b\\u044f\\\",\\\"\\u0410\\u043d\\u0442\\u043e\\u043d\\u0438\\u0439 (6 \\u043a\\u043b.)\\\"]\",\"expected_students\":6,\"formula_id\":null,\"active\":1,\"created_at\":\"2025-11-30 19:27:06\",\"updated_at\":\"2025-12-01 02:38:01\"}', '{\"teacher_id\":1,\"day_of_week\":1}', 'Обновлён шаблон урока', '85.94.7.223', '2025-12-01 02:48:05'),
(216, 'student_updated', 'template', 49, 1, NULL, NULL, 'Коля', 'Коля (7 кл.)', 'Обновлён ученик: \'Коля\' -> \'Коля (7 кл.)\'', '85.94.7.223', '2025-12-01 02:52:14'),
(217, 'student_updated', 'template', 49, 1, NULL, NULL, 'Кирилл', 'Кирилл (6 кл.)', 'Обновлён ученик: \'Кирилл\' -> \'Кирилл (6 кл.)\'', '85.94.7.223', '2025-12-01 02:52:27'),
(218, 'student_updated', 'template', 49, 1, NULL, NULL, 'Маша', 'Маша (6 кл.)', 'Обновлён ученик: \'Маша\' -> \'Маша (6 кл.)\'', '85.94.7.223', '2025-12-01 02:52:31'),
(219, 'student_updated', 'template', 49, 1, NULL, NULL, 'Вика', 'Вика (6 кл.)', 'Обновлён ученик: \'Вика\' -> \'Вика (6 кл.)\'', '85.94.7.223', '2025-12-01 02:52:36'),
(220, 'student_updated', 'template', 50, 1, NULL, NULL, 'Кирилл', 'Кирилл (6 кл.)', 'Обновлён ученик: \'Кирилл\' -> \'Кирилл (6 кл.)\'', '85.94.7.223', '2025-12-01 02:53:35'),
(221, 'student_updated', 'template', 50, 1, NULL, NULL, 'Вика', 'Вика (6 кл.)', 'Обновлён ученик: \'Вика\' -> \'Вика (6 кл.)\'', '85.94.7.223', '2025-12-01 02:53:38'),
(222, 'student_updated', 'template', 50, 1, NULL, NULL, 'Маша', 'Маша (6 кл.)', 'Обновлён ученик: \'Маша\' -> \'Маша (6 кл.)\'', '85.94.7.223', '2025-12-01 02:53:41'),
(223, 'student_updated', 'template', 51, 1, NULL, NULL, 'Маша', 'Маша (5 кл.)', 'Обновлён ученик: \'Маша\' -> \'Маша (5 кл.)\'', '85.94.7.223', '2025-12-01 02:53:51'),
(224, 'student_updated', 'template', 52, 1, NULL, NULL, 'Маша', 'Маша (5 кл.)', 'Обновлён ученик: \'Маша\' -> \'Маша (5 кл.)\'', '85.94.7.223', '2025-12-01 02:53:55'),
(225, 'student_updated', 'template', 53, 1, NULL, NULL, 'Маша', 'Маша (5 кл.)', 'Обновлён ученик: \'Маша\' -> \'Маша (5 кл.)\'', '85.94.7.223', '2025-12-01 02:54:00'),
(226, 'student_updated', 'template', 56, 1, NULL, NULL, 'Вика', 'Вика (6 кл.)', 'Обновлён ученик: \'Вика\' -> \'Вика (6 кл.)\'', '85.94.7.223', '2025-12-01 02:54:04'),
(227, 'student_updated', 'template', 57, 1, NULL, NULL, 'Вика', 'Вика (6 кл.)', 'Обновлён ученик: \'Вика\' -> \'Вика (6 кл.)\'', '85.94.7.223', '2025-12-01 02:54:07'),
(228, 'student_updated', 'template', 62, 1, NULL, NULL, 'Коля', 'Коля (2 кл.)', 'Обновлён ученик: \'Коля\' -> \'Коля (2 кл.)\'', '85.94.7.223', '2025-12-01 02:54:11'),
(229, 'student_updated', 'template', 63, 1, NULL, NULL, 'Коля', 'Коля (2 кл.)', 'Обновлён ученик: \'Коля\' -> \'Коля (2 кл.)\'', '85.94.7.223', '2025-12-01 02:54:13'),
(230, 'student_updated', 'template', 68, 1, NULL, NULL, 'Настя', 'Настя (9 кл.)', 'Обновлён ученик: \'Настя\' -> \'Настя (9 кл.)\'', '85.94.7.223', '2025-12-01 02:54:20'),
(231, 'student_updated', 'template', 69, 1, NULL, NULL, 'Настя', 'Настя (9 кл.)', 'Обновлён ученик: \'Настя\' -> \'Настя (9 кл.)\'', '85.94.7.223', '2025-12-01 02:54:24'),
(232, 'student_updated', 'template', 72, 1, NULL, NULL, 'Кирилл', 'Кирилл (9 кл.)', 'Обновлён ученик: \'Кирилл\' -> \'Кирилл (9 кл.)\'', '85.94.7.223', '2025-12-01 02:54:30'),
(233, 'student_updated', 'template', 73, 1, NULL, NULL, 'Кирилл', 'Кирилл (9 кл.)', 'Обновлён ученик: \'Кирилл\' -> \'Кирилл (9 кл.)\'', '85.94.7.223', '2025-12-01 02:54:46'),
(234, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '85.94.7.223', '2025-12-01 13:39:56'),
(235, 'student_deactivated', 'student', 24, 1, NULL, NULL, '{\"active\":1}', '{\"active\":0}', 'Ученик деактивирован', '85.94.7.223', '2025-12-01 13:40:16'),
(236, 'student_deactivated', 'student', 23, 1, NULL, NULL, '{\"active\":1}', '{\"active\":0}', 'Ученик деактивирован', '85.94.7.223', '2025-12-01 13:40:29'),
(237, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '2a03:d000:4301:154d:20bf:2ca5:28d9:1857', '2025-12-01 15:19:47'),
(238, 'week_generated', 'schedule', NULL, 1, NULL, NULL, NULL, '{\"week_start\":\"2025-12-01\",\"created\":32}', 'Сгенерировано 32 уроков на неделю', '85.94.7.223', '2025-12-01 23:14:46'),
(239, 'week_generated', 'schedule', NULL, 1, NULL, NULL, NULL, '{\"week_start\":\"2025-12-01\",\"created\":0}', 'Сгенерировано 0 уроков на неделю', '85.94.7.223', '2025-12-01 23:14:46'),
(240, 'week_generated', 'schedule', NULL, 1, NULL, NULL, NULL, '{\"week_start\":\"2025-12-01\",\"created\":0}', 'Сгенерировано 0 уроков на неделю', '85.94.7.223', '2025-12-01 23:14:46'),
(241, 'week_generated', 'schedule', NULL, 1, NULL, NULL, NULL, '{\"week_start\":\"2025-12-01\",\"created\":0}', 'Сгенерировано 0 уроков на неделю', '85.94.7.223', '2025-12-01 23:14:46'),
(242, 'week_generated', 'schedule', NULL, 1, NULL, NULL, NULL, '{\"week_start\":\"2025-12-01\",\"created\":0}', 'Сгенерировано 0 уроков на неделю', '85.94.7.223', '2025-12-01 23:14:47'),
(243, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '85.94.7.223', '2025-12-01 23:19:05'),
(244, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '85.94.7.223', '2025-12-01 23:27:17'),
(245, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '85.94.7.223', '2025-12-02 03:43:32'),
(246, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '85.94.7.223', '2025-12-02 03:51:26'),
(247, 'students_sync', 'template', NULL, NULL, NULL, NULL, NULL, '{\"total\":32,\"updated\":10,\"skipped\":22,\"errors\":0}', 'Синхронизация количества студентов: обновлено 10 из 32', '85.94.7.223', '2025-12-02 03:57:17'),
(248, 'students_sync', 'template', NULL, NULL, NULL, NULL, NULL, '{\"total\":32,\"updated\":0,\"skipped\":32,\"errors\":0}', 'Синхронизация количества студентов: обновлено 0 из 32', '85.94.7.223', '2025-12-02 03:57:36'),
(249, 'students_sync', 'template', NULL, NULL, NULL, NULL, NULL, '{\"total\":32,\"updated\":0,\"skipped\":32,\"errors\":0}', 'Синхронизация количества студентов: обновлено 0 из 32', '85.94.7.223', '2025-12-02 03:57:39'),
(250, 'students_sync', 'template', NULL, NULL, NULL, NULL, NULL, '{\"total\":32,\"updated\":0,\"skipped\":32,\"errors\":0}', 'Синхронизация количества студентов: обновлено 0 из 32', '85.94.7.223', '2025-12-02 03:57:43'),
(251, 'user_login', 'user', 1, 1, NULL, NULL, NULL, NULL, 'Вход в систему', '85.94.7.223', '2025-12-02 03:58:22'),
(252, 'students_sync', 'template', NULL, 1, NULL, NULL, NULL, '{\"total\":32,\"updated\":0,\"skipped\":32,\"errors\":0}', 'Синхронизация количества студентов: обновлено 0 из 32', '85.94.7.223', '2025-12-02 03:59:12'),
(253, 'students_sync', 'template', NULL, 1, NULL, NULL, NULL, '{\"total\":32,\"updated\":0,\"skipped\":32,\"errors\":0}', 'Синхронизация количества студентов: обновлено 0 из 32', '85.94.7.223', '2025-12-02 04:05:18');

-- --------------------------------------------------------

--
-- Структура таблицы `bot_states`
--

CREATE TABLE IF NOT EXISTS `bot_states` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telegram_id` bigint(20) NOT NULL,
  `state` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `context_data` text COLLATE utf8mb4_unicode_ci,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_telegram_id` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `lessons_instance`
--

CREATE TABLE IF NOT EXISTS `lessons_instance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `substitute_teacher_id` int(11) DEFAULT NULL,
  `lesson_date` date NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `lesson_type` enum('group','individual') COLLATE utf8mb4_unicode_ci DEFAULT 'group',
  `subject` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_students` int(11) DEFAULT '1',
  `actual_students` int(11) DEFAULT '0',
  `formula_id` int(11) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','rescheduled') COLLATE utf8mb4_unicode_ci DEFAULT 'scheduled',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `substitute_teacher_id` (`substitute_teacher_id`),
  KEY `formula_id` (`formula_id`),
  KEY `idx_date_teacher` (`lesson_date`,`teacher_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `lessons_instance`
--

INSERT INTO `lessons_instance` (`id`, `template_id`, `teacher_id`, `substitute_teacher_id`, `lesson_date`, `time_start`, `time_end`, `lesson_type`, `subject`, `expected_students`, `actual_students`, `formula_id`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(3, 44, 1, NULL, '2025-12-01', '16:00:00', '17:00:00', 'individual', 'Математика', 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(4, 45, 1, NULL, '2025-12-02', '16:00:00', '17:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(5, 46, 1, NULL, '2025-12-05', '17:00:00', '18:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(6, 47, 1, NULL, '2025-12-01', '17:00:00', '18:00:00', 'group', 'Математика', 6, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(7, 48, 1, NULL, '2025-12-04', '16:00:00', '17:00:00', 'group', NULL, 6, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(8, 49, 1, NULL, '2025-12-04', '20:00:00', '21:00:00', 'group', NULL, 6, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(9, 50, 1, NULL, '2025-12-06', '16:00:00', '17:00:00', 'group', NULL, 6, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(10, 51, 1, NULL, '2025-12-01', '18:00:00', '19:00:00', 'individual', 'Математика', 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(11, 52, 1, NULL, '2025-12-04', '18:00:00', '19:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(12, 53, 1, NULL, '2025-12-06', '14:00:00', '15:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(13, 54, 1, NULL, '2025-12-01', '19:00:00', '20:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(14, 55, 1, NULL, '2025-12-04', '19:00:00', '20:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(15, 56, 1, NULL, '2025-12-01', '20:00:00', '21:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(16, 57, 1, NULL, '2025-12-06', '17:00:00', '18:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(17, 60, 1, NULL, '2025-12-02', '18:00:00', '19:00:00', 'group', NULL, 6, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(18, 61, 1, NULL, '2025-12-06', '15:00:00', '16:00:00', 'group', NULL, 6, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(19, 62, 1, NULL, '2025-12-02', '19:00:00', '20:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(20, 63, 1, NULL, '2025-12-05', '18:00:00', '19:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(21, 66, 1, NULL, '2025-12-01', '15:00:00', '16:00:00', 'group', 'Математика', 6, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(22, 67, 1, NULL, '2025-12-03', '15:00:00', '16:00:00', 'group', NULL, 6, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(23, 68, 1, NULL, '2025-12-02', '17:00:00', '18:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(24, 69, 1, NULL, '2025-12-04', '17:00:00', '18:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(25, 70, 1, NULL, '2025-12-02', '20:00:00', '21:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(26, 71, 1, NULL, '2025-12-05', '19:00:00', '20:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(27, 72, 1, NULL, '2025-12-03', '16:00:00', '17:00:00', 'group', NULL, 6, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(28, 73, 1, NULL, '2025-12-05', '16:00:00', '17:00:00', 'group', NULL, 6, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(29, 74, 1, NULL, '2025-12-03', '18:00:00', '19:00:00', 'group', NULL, 6, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(30, 79, 1, NULL, '2025-12-03', '19:00:00', '20:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(31, 80, 1, NULL, '2025-12-06', '11:00:00', '12:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(32, 83, 1, NULL, '2025-12-04', '15:00:00', '16:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(33, 84, 1, NULL, '2025-12-06', '13:00:00', '14:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27'),
(34, 85, 1, NULL, '2025-12-06', '12:00:00', '13:00:00', 'individual', NULL, 1, 0, 4, 'scheduled', NULL, '2025-12-01 23:14:46', '2025-12-01 23:27:27');

--
-- Триггеры `lessons_instance`
--
DELIMITER $$
CREATE TRIGGER `calculate_payment_after_lesson_complete` AFTER UPDATE ON `lessons_instance` FOR EACH ROW BEGIN
    DECLARE calculated_amount INT DEFAULT 0;
    DECLARE formula_type VARCHAR(20);
    DECLARE min_pay INT;
    DECLARE per_stud INT;
    DECLARE threshold_val INT;
    DECLARE fixed_amt INT;
    DECLARE expr TEXT;
    DECLARE teacher_for_payment INT;

    -- Проверяем, что статус изменился на 'completed'
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN

        -- Определяем кому платить (замещающему или основному)
        SET teacher_for_payment = IFNULL(NEW.substitute_teacher_id, NEW.teacher_id);

        -- Получаем формулу оплаты
        IF NEW.formula_id IS NOT NULL THEN
            SELECT type, min_payment, per_student, threshold, fixed_amount, expression
            INTO formula_type, min_pay, per_stud, threshold_val, fixed_amt, expr
            FROM payment_formulas
            WHERE id = NEW.formula_id AND active = 1;

            -- Рассчитываем сумму в зависимости от типа формулы
            IF formula_type = 'min_plus_per' THEN
                IF NEW.actual_students >= threshold_val THEN
                    SET calculated_amount = min_pay + ((NEW.actual_students - threshold_val + 1) * per_stud);
                ELSE
                    SET calculated_amount = min_pay;
                END IF;

            ELSEIF formula_type = 'fixed' THEN
                SET calculated_amount = fixed_amt;

            ELSEIF formula_type = 'expression' THEN
                -- Здесь нужна более сложная логика для парсинга выражений
                -- Пока используем простую подстановку
                SET calculated_amount = min_pay + (NEW.actual_students * per_stud);
            END IF;

            -- Создаём запись о начислении
            INSERT INTO payments (
                teacher_id,
                lesson_instance_id,
                amount,
                payment_type,
                calculation_method,
                status
            ) VALUES (
                teacher_for_payment,
                NEW.id,
                calculated_amount,
                'lesson',
                CONCAT('Formula: ', formula_type, ', Students: ', NEW.actual_students),
                'pending'
            );
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `lessons_stats`
-- (См. Ниже фактическое представление)
--
CREATE TABLE IF NOT EXISTS `lessons_stats` (
`lesson_id` int(11)
,`lesson_date` date
,`teacher_name` varchar(100)
,`lesson_type` enum('group','individual')
,`expected_students` int(11)
,`actual_students` int(11)
,`status` enum('scheduled','completed','cancelled','rescheduled')
,`payment_amount` bigint(11)
,`payment_status` enum('pending','approved','paid','cancelled')
);

-- --------------------------------------------------------

--
-- Структура таблицы `lessons_template`
--

CREATE TABLE IF NOT EXISTS `lessons_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `room` tinyint(1) DEFAULT '1',
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `lesson_type` enum('group','individual') COLLATE utf8mb4_unicode_ci DEFAULT 'group',
  `subject` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tier` enum('S','A','B','C','D') COLLATE utf8mb4_unicode_ci DEFAULT 'C',
  `grades` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `students` text COLLATE utf8mb4_unicode_ci,
  `expected_students` int(11) DEFAULT '1',
  `formula_id` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `formula_id` (`formula_id`),
  KEY `idx_teacher_day` (`teacher_id`,`day_of_week`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `lessons_template`
--

INSERT INTO `lessons_template` (`id`, `teacher_id`, `day_of_week`, `room`, `time_start`, `time_end`, `lesson_type`, `subject`, `tier`, `grades`, `students`, `expected_students`, `formula_id`, `active`, `created_at`, `updated_at`) VALUES
(44, 1, 1, 1, '16:00:00', '17:00:00', 'individual', 'Математика', 'C', NULL, '[\"Влада (9 кл.)\"]', 1, NULL, 1, '2025-11-30 18:37:00', '2025-12-01 02:38:01'),
(45, 1, 2, 1, '16:00:00', '17:00:00', 'individual', NULL, 'C', NULL, '[\"Влада (9 кл.)\"]', 1, NULL, 1, '2025-11-30 18:37:00', '2025-12-01 02:38:01'),
(46, 1, 5, 2, '17:00:00', '18:00:00', 'individual', NULL, 'C', NULL, '[\"Влада (9 кл.)\"]', 1, NULL, 1, '2025-11-30 18:37:00', '2025-12-01 02:38:01'),
(47, 1, 1, 1, '17:00:00', '18:00:00', 'group', 'Математика', 'C', NULL, '[\"Лёша (6 кл.)\",\"Лера (7 кл.)\",\"Коля (7 кл.)\",\"Антоний (6 кл.)\"]', 4, NULL, 1, '2025-11-30 19:27:06', '2025-12-02 03:57:17'),
(48, 1, 4, 1, '16:00:00', '17:00:00', 'group', NULL, 'C', NULL, '[\"Лёша (6 кл.)\",\"Лера (7 кл.)\"]', 2, NULL, 1, '2025-11-30 19:27:06', '2025-12-02 03:57:17'),
(49, 1, 4, 1, '20:00:00', '21:00:00', 'group', NULL, 'C', NULL, '[\"Коля (7 кл.)\",\"Ульяна (7 кл.)\",\"Маша (6 кл.)\",\"Вика (6 кл.)\",\"Кирилл (6 кл.)\"]', 5, NULL, 1, '2025-11-30 19:29:25', '2025-12-02 03:57:17'),
(50, 1, 6, 1, '16:00:00', '17:00:00', 'group', NULL, 'C', NULL, '[\"Антоний (6 кл.)\",\"Милана (7 кл.)\",\"Ульяна (7 кл.)\",\"Маша (6 кл.)\",\"Вика (6 кл.)\",\"Кирилл (6 кл.)\"]', 6, NULL, 1, '2025-11-30 19:30:07', '2025-12-01 02:53:41'),
(51, 1, 1, 1, '18:00:00', '19:00:00', 'individual', 'Математика', 'C', '5', '[\"Маша (5 кл.)\"]', 1, NULL, 1, '2025-11-30 19:31:11', '2025-12-01 02:53:51'),
(52, 1, 4, 1, '18:00:00', '19:00:00', 'individual', NULL, 'C', NULL, '[\"Маша (5 кл.)\"]', 1, NULL, 1, '2025-11-30 19:31:11', '2025-12-01 02:53:55'),
(53, 1, 6, 1, '14:00:00', '15:00:00', 'individual', NULL, 'C', NULL, '[\"Маша (5 кл.)\"]', 1, NULL, 1, '2025-11-30 19:31:11', '2025-12-01 02:54:00'),
(54, 1, 1, 1, '19:00:00', '20:00:00', 'individual', NULL, 'C', NULL, '[\"Саша (6 кл.)\"]', 1, NULL, 1, '2025-11-30 19:31:50', '2025-12-01 02:38:01'),
(55, 1, 4, 1, '19:00:00', '20:00:00', 'individual', NULL, 'C', NULL, '[\"Саша (6 кл.)\"]', 1, NULL, 1, '2025-11-30 19:31:50', '2025-12-01 02:38:01'),
(56, 1, 1, 1, '20:00:00', '21:00:00', 'individual', NULL, 'C', NULL, '[\"Вика (6 кл.)\"]', 1, NULL, 1, '2025-11-30 19:32:22', '2025-12-01 02:54:04'),
(57, 1, 6, 1, '17:00:00', '18:00:00', 'individual', NULL, 'C', NULL, '[\"Вика (6 кл.)\"]', 1, NULL, 1, '2025-11-30 19:32:22', '2025-12-01 02:54:07'),
(60, 1, 2, 1, '18:00:00', '19:00:00', 'group', NULL, 'C', NULL, '[\"Арина (8 кл.)\",\"Милана (7 кл.)\",\"Артем (8 кл.)\",\"Ваня (8 кл.)\"]', 4, NULL, 1, '2025-11-30 19:33:35', '2025-12-02 03:57:17'),
(61, 1, 6, 1, '15:00:00', '16:00:00', 'group', NULL, 'C', NULL, '[\"Арина (8 кл.)\",\"Артем (8 кл.)\",\"Ваня (8 кл.)\",\"Тихон (9 кл.)\"]', 4, NULL, 1, '2025-11-30 19:33:35', '2025-12-02 03:57:17'),
(62, 1, 2, 2, '19:00:00', '20:00:00', 'individual', NULL, 'C', NULL, '[\"Коля (2 кл.)\"]', 1, NULL, 1, '2025-11-30 19:36:26', '2025-12-01 02:54:11'),
(63, 1, 5, 2, '18:00:00', '19:00:00', 'individual', NULL, 'C', NULL, '[\"Коля (2 кл.)\"]', 1, NULL, 1, '2025-11-30 19:36:26', '2025-12-01 02:54:13'),
(66, 1, 1, 1, '15:00:00', '16:00:00', 'group', 'Математика', 'C', NULL, '[\"Влад (9 кл.)\",\"Глеб (9 кл.)\"]', 2, NULL, 1, '2025-11-30 19:37:41', '2025-12-02 03:57:17'),
(67, 1, 3, 1, '15:00:00', '16:00:00', 'group', NULL, 'C', NULL, '[\"Влад (9 кл.)\",\"Глеб (9 кл.)\"]', 2, NULL, 1, '2025-11-30 19:37:41', '2025-12-02 03:57:17'),
(68, 1, 2, 1, '17:00:00', '18:00:00', 'individual', NULL, 'C', NULL, '[\"Настя (9 кл.)\"]', 1, NULL, 1, '2025-11-30 19:38:22', '2025-12-01 02:54:20'),
(69, 1, 4, 1, '17:00:00', '18:00:00', 'individual', NULL, 'C', NULL, '[\"Настя (9 кл.)\"]', 1, NULL, 1, '2025-11-30 19:38:22', '2025-12-01 02:54:24'),
(70, 1, 2, 1, '20:00:00', '21:00:00', 'individual', NULL, 'C', NULL, '[\"Матвей (6 кл.)\"]', 1, NULL, 1, '2025-11-30 19:38:29', '2025-12-01 02:38:01'),
(71, 1, 5, 2, '19:00:00', '20:00:00', 'individual', NULL, 'C', NULL, '[\"Матвей (6 кл.)\"]', 1, NULL, 1, '2025-11-30 19:38:29', '2025-12-01 02:38:01'),
(72, 1, 3, 1, '16:00:00', '17:00:00', 'group', NULL, 'C', NULL, '[\"Гриша (б) (9 кл.)\",\"Гриша (м) (9 кл.)\",\"Кирилл (9 кл.)\",\"Саня (9 кл.)\"]', 4, NULL, 1, '2025-11-30 19:39:35', '2025-12-02 03:57:17'),
(73, 1, 5, 1, '16:00:00', '17:00:00', 'group', NULL, 'C', NULL, '[\"Гриша (б) (9 кл.)\",\"Гриша (м) (9 кл.)\",\"Кирилл (9 кл.)\",\"Саня (9 кл.)\"]', 4, NULL, 1, '2025-11-30 19:39:35', '2025-12-02 03:57:17'),
(74, 1, 3, 2, '18:00:00', '19:00:00', 'group', NULL, 'C', NULL, '[\"Тихон (9 кл.)\"]', 1, NULL, 1, '2025-11-30 19:42:57', '2025-12-02 03:57:17'),
(79, 1, 3, 2, '19:00:00', '20:00:00', 'individual', NULL, 'C', NULL, '[\"Алиса (8 кл.)\"]', 1, NULL, 1, '2025-11-30 19:44:11', '2025-12-01 02:38:01'),
(80, 1, 6, 1, '11:00:00', '12:00:00', 'individual', NULL, 'C', NULL, '[\"Алиса (8 кл.)\"]', 1, NULL, 1, '2025-11-30 19:44:11', '2025-12-01 02:38:01'),
(83, 1, 4, 1, '15:00:00', '16:00:00', 'individual', NULL, 'C', NULL, '[\"Лиза (9 кл.)\"]', 1, NULL, 1, '2025-11-30 19:45:28', '2025-12-01 02:38:01'),
(84, 1, 6, 1, '13:00:00', '14:00:00', 'individual', NULL, 'C', NULL, '[\"Лиза (9 кл.)\"]', 1, NULL, 1, '2025-11-30 19:45:28', '2025-12-01 02:38:01'),
(85, 1, 6, 1, '12:00:00', '13:00:00', 'individual', NULL, 'C', NULL, '[\"Тимофей (3 кл.)\"]', 1, NULL, 1, '2025-11-30 19:48:56', '2025-12-01 02:38:01');

-- --------------------------------------------------------

--
-- Структура таблицы `lesson_students`
--

CREATE TABLE IF NOT EXISTS `lesson_students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_instance_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `enrolled` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lesson_student` (`lesson_instance_id`,`student_id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `payments`
--

CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `lesson_instance_id` int(11) DEFAULT NULL,
  `lesson_template_id` int(11) DEFAULT NULL,
  `amount` int(11) NOT NULL,
  `payment_type` enum('lesson','bonus','penalty','adjustment') COLLATE utf8mb4_unicode_ci DEFAULT 'lesson',
  `calculation_method` text COLLATE utf8mb4_unicode_ci,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `status` enum('pending','approved','paid','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lesson_instance_id` (`lesson_instance_id`),
  KEY `idx_teacher_status` (`teacher_id`,`status`),
  KEY `idx_period` (`period_start`,`period_end`),
  KEY `idx_lesson_template_id` (`lesson_template_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `payments`
--

INSERT INTO `payments` (`id`, `teacher_id`, `lesson_instance_id`, `lesson_template_id`, `amount`, `payment_type`, `calculation_method`, `period_start`, `period_end`, `status`, `paid_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 66, 900, 'lesson', 'Пришло 2 из 6', NULL, NULL, 'pending', NULL, NULL, '2025-12-01 15:12:47', '2025-12-01 15:12:47'),
(2, 1, NULL, 44, 900, 'lesson', 'Все пришли (1 из 1)', NULL, NULL, 'pending', NULL, NULL, '2025-12-01 16:13:34', '2025-12-01 16:13:34'),
(3, 1, NULL, 47, 900, 'lesson', 'Пришло 2 из 6', NULL, NULL, 'pending', NULL, NULL, '2025-12-01 17:12:50', '2025-12-01 17:12:50'),
(4, 1, NULL, 51, 900, 'lesson', 'Все пришли (1 из 1)', NULL, NULL, 'pending', NULL, NULL, '2025-12-01 18:14:02', '2025-12-01 18:14:02'),
(5, 1, NULL, 54, 900, 'lesson', 'Все пришли (1 из 1)', NULL, NULL, 'pending', NULL, NULL, '2025-12-01 19:12:07', '2025-12-01 19:12:07'),
(6, 1, NULL, 56, 900, 'lesson', 'Все пришли (1 из 1)', NULL, NULL, 'pending', NULL, NULL, '2025-12-01 20:12:48', '2025-12-01 20:12:48');

-- --------------------------------------------------------

--
-- Структура таблицы `payment_formulas`
--

CREATE TABLE IF NOT EXISTS `payment_formulas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('min_plus_per','fixed','expression') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `min_payment` int(11) DEFAULT '0',
  `per_student` int(11) DEFAULT '0',
  `threshold` int(11) DEFAULT '2',
  `fixed_amount` int(11) DEFAULT '0',
  `expression` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `payment_formulas`
--

INSERT INTO `payment_formulas` (`id`, `name`, `type`, `description`, `min_payment`, `per_student`, `threshold`, `fixed_amount`, `expression`, `active`, `created_at`, `updated_at`) VALUES
(3, 'Стас инд', 'fixed', NULL, NULL, NULL, NULL, 900, NULL, 1, '2025-11-18 10:02:23', '2025-11-18 10:02:23'),
(4, 'Стас группа', 'min_plus_per', NULL, 900, 200, 3, NULL, NULL, 1, '2025-11-18 10:02:54', '2025-11-18 10:02:54');

-- --------------------------------------------------------

--
-- Структура таблицы `payout_cycles`
--

CREATE TABLE IF NOT EXISTS `payout_cycles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_amount` int(11) DEFAULT '0',
  `owner_share` int(11) DEFAULT '0',
  `status` enum('draft','approved','paid') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_period` (`period_start`,`period_end`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `payout_cycle_payments`
--

CREATE TABLE IF NOT EXISTS `payout_cycle_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cycle_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cycle_payment` (`cycle_id`,`payment_id`),
  KEY `payment_id` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `settings`
--

CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'bot_token', '8576699600:AAE8-z3lc2Lt0CmOhf5jgJ9j6lkjVpmdL_E', 'Telegram Bot API Token', '2025-11-18 05:19:35'),
(2, 'bot_check_interval', '5', 'Интервал проверки уроков (в минутах)', '2025-11-18 05:19:35'),
(3, 'attendance_delay', '15', 'Задержка перед опросом посещаемости (в минутах)', '2025-11-18 05:19:35'),
(4, 'owner_share_percent', '30', 'Процент владельца от выручки', '2025-11-15 04:29:26'),
(5, 'currency', 'RUB', 'Валюта системы', '2025-11-15 04:29:26'),
(6, 'timezone', 'Europe/Moscow', 'Часовой пояс', '2025-11-18 06:47:13');

-- --------------------------------------------------------

--
-- Структура таблицы `students`
--

CREATE TABLE IF NOT EXISTS `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_telegram` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_whatsapp` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_telegram` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_whatsapp` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class` int(11) DEFAULT NULL,
  `tier` enum('S','A','B','C','D') COLLATE utf8mb4_unicode_ci DEFAULT 'C',
  `lesson_type` enum('group','individual') COLLATE utf8mb4_unicode_ci DEFAULT 'group',
  `payment_type_group` enum('per_lesson','monthly') COLLATE utf8mb4_unicode_ci DEFAULT 'monthly',
  `payment_type_individual` enum('per_lesson','monthly') COLLATE utf8mb4_unicode_ci DEFAULT 'per_lesson',
  `price_group` int(11) DEFAULT '5000',
  `price_individual` int(11) DEFAULT '1500',
  `schedule` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`active`),
  KEY `idx_lesson_type` (`lesson_type`),
  KEY `idx_class` (`class`),
  KEY `idx_payment_type_group` (`payment_type_group`),
  KEY `idx_payment_type_individual` (`payment_type_individual`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_tier` (`tier`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `students`
--

INSERT INTO `students` (`id`, `teacher_id`, `name`, `student_telegram`, `student_whatsapp`, `parent_name`, `parent_telegram`, `parent_whatsapp`, `class`, `tier`, `lesson_type`, `payment_type_group`, `payment_type_individual`, `price_group`, `price_individual`, `schedule`, `notes`, `active`, `created_at`, `updated_at`) VALUES
(22, 1, 'Влада', NULL, NULL, NULL, NULL, NULL, 9, 'C', 'individual', 'monthly', 'per_lesson', 5000, 1500, '{\"1\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}], \"2\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}], \"5\": [{\"room\": 2, \"time\": \"17:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 18:37:00', '2025-11-30 18:37:00'),
(23, 1, 'Лёша', NULL, NULL, NULL, NULL, NULL, 6, 'C', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"1\": [{\"room\": 1, \"time\": \"17:00\", \"teacher_id\": 1}], \"4\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 0, '2025-11-30 19:27:06', '2025-12-01 13:40:29'),
(24, 1, 'Лера', NULL, NULL, NULL, NULL, NULL, 7, 'B', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"1\": [{\"room\": 1, \"time\": \"17:00\", \"teacher_id\": 1}], \"4\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 0, '2025-11-30 19:27:41', '2025-12-01 13:40:16'),
(25, 1, 'Настя', NULL, NULL, NULL, NULL, NULL, 8, 'C', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"1\": [{\"room\": 1, \"time\": \"17:00\", \"teacher_id\": 1}], \"4\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:28:35', '2025-11-30 19:28:35'),
(26, 1, 'Коля', NULL, NULL, NULL, NULL, NULL, 7, 'B', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"1\": [{\"room\": 1, \"time\": \"17:00\", \"teacher_id\": 1}], \"4\": [{\"room\": 1, \"time\": \"20:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:29:25', '2025-11-30 19:29:25'),
(27, 1, 'Антоний', NULL, NULL, NULL, NULL, NULL, 6, 'C', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"1\": [{\"room\": 1, \"time\": \"17:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:30:07', '2025-11-30 19:30:07'),
(28, 1, 'Маша', NULL, NULL, NULL, NULL, NULL, 5, 'S', 'individual', 'monthly', 'per_lesson', 5000, 1500, '{\"1\": [{\"room\": 1, \"time\": \"18:00\", \"teacher_id\": 1}], \"4\": [{\"room\": 1, \"time\": \"18:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"14:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:31:11', '2025-11-30 19:31:11'),
(29, 1, 'Саша', NULL, NULL, NULL, NULL, NULL, 6, 'S', 'individual', 'monthly', 'per_lesson', 5000, 1500, '{\"1\": [{\"room\": 1, \"time\": \"19:00\", \"teacher_id\": 1}], \"4\": [{\"room\": 1, \"time\": \"19:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:31:50', '2025-11-30 19:31:50'),
(30, 1, 'Вика', NULL, NULL, NULL, NULL, NULL, 6, 'C', 'individual', 'monthly', 'per_lesson', 5000, 1500, '{\"1\": [{\"room\": 1, \"time\": \"20:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"17:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:32:22', '2025-11-30 19:32:22'),
(31, 1, 'Настя', NULL, NULL, NULL, NULL, NULL, 9, 'C', 'individual', 'monthly', 'per_lesson', 5000, 1500, '{\"2\": [{\"room\": 1, \"time\": \"17:00\", \"teacher_id\": 1}], \"4\": [{\"room\": 1, \"time\": \"17:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:33:01', '2025-11-30 19:38:22'),
(32, 1, 'Арина', NULL, NULL, NULL, NULL, NULL, 8, 'A', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"2\": [{\"room\": 1, \"time\": \"18:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"15:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:33:35', '2025-11-30 19:33:35'),
(33, 1, 'Ваня', NULL, NULL, NULL, NULL, NULL, 8, 'C', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"2\": [{\"room\": 1, \"time\": \"18:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"15:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:34:06', '2025-11-30 19:38:48'),
(34, 1, 'Милана', NULL, NULL, NULL, NULL, NULL, 7, 'C', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"2\": [{\"room\": 1, \"time\": \"18:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:34:48', '2025-11-30 19:34:48'),
(35, 1, 'Артем', NULL, NULL, NULL, NULL, NULL, 8, 'C', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"2\": [{\"room\": 1, \"time\": \"18:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"15:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:35:38', '2025-11-30 19:35:38'),
(36, 1, 'Коля', NULL, NULL, NULL, NULL, NULL, 2, 'S', 'individual', 'monthly', 'per_lesson', 5000, 1500, '{\"2\": [{\"room\": 2, \"time\": \"19:00\", \"teacher_id\": 1}], \"5\": [{\"room\": 2, \"time\": \"18:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:36:26', '2025-11-30 19:36:26'),
(37, 1, 'Матвей', NULL, NULL, NULL, NULL, NULL, 6, 'D', 'individual', 'monthly', 'per_lesson', 5000, 1500, '{\"2\": [{\"room\": 1, \"time\": \"20:00\", \"teacher_id\": 1}], \"5\": [{\"room\": 2, \"time\": \"19:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:37:05', '2025-11-30 19:38:29'),
(38, 1, 'Влад', NULL, NULL, NULL, NULL, NULL, 9, 'B', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"1\": [{\"room\": 1, \"time\": \"15:00\", \"teacher_id\": 1}], \"3\": [{\"room\": 1, \"time\": \"15:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:37:41', '2025-11-30 19:37:41'),
(39, 1, 'Глеб', NULL, NULL, NULL, NULL, NULL, 9, 'B', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"1\": [{\"room\": 1, \"time\": \"15:00\", \"teacher_id\": 1}], \"3\": [{\"room\": 1, \"time\": \"15:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:37:57', '2025-11-30 19:38:38'),
(40, 1, 'Гриша (б)', NULL, NULL, NULL, NULL, NULL, 9, 'A', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"3\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}], \"5\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:39:35', '2025-11-30 19:39:35'),
(41, 1, 'Гриша (м)', NULL, NULL, NULL, NULL, NULL, 9, 'S', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"3\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}], \"5\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:39:57', '2025-11-30 19:39:57'),
(42, 1, 'Кирилл', NULL, NULL, NULL, NULL, NULL, 9, 'B', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"3\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}], \"5\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:41:19', '2025-11-30 19:41:19'),
(43, 1, 'Саня', NULL, NULL, NULL, NULL, NULL, 9, 'C', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"3\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}], \"5\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:41:37', '2025-11-30 19:44:02'),
(44, 1, 'Тихон', NULL, NULL, NULL, NULL, NULL, 9, 'A', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"3\": [{\"room\": 2, \"time\": \"18:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"15:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:42:57', '2025-11-30 19:42:57'),
(45, 1, 'Алиса', NULL, NULL, NULL, NULL, NULL, 8, 'C', 'individual', 'monthly', 'per_lesson', 5000, 1500, '{\"3\": [{\"room\": 2, \"time\": \"19:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"11:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:43:29', '2025-11-30 19:44:11'),
(46, 1, 'Лиза', NULL, NULL, NULL, NULL, NULL, 9, 'C', 'individual', 'monthly', 'per_lesson', 5000, 1500, '{\"4\": [{\"room\": 1, \"time\": \"15:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"13:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:45:17', '2025-11-30 19:45:28'),
(47, 1, 'Ульяна', NULL, NULL, NULL, NULL, NULL, 7, 'C', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"4\": [{\"room\": 1, \"time\": \"20:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:46:15', '2025-11-30 19:46:15'),
(48, 1, 'Маша', NULL, NULL, NULL, NULL, NULL, 6, 'B', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"4\": [{\"room\": 1, \"time\": \"20:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:47:14', '2025-11-30 19:47:14'),
(49, 1, 'Вика', NULL, NULL, NULL, NULL, NULL, 6, 'A', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"4\": [{\"room\": 1, \"time\": \"20:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:47:38', '2025-11-30 19:47:38'),
(50, 1, 'Кирилл', NULL, NULL, NULL, NULL, NULL, 6, 'A', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"4\": [{\"room\": 1, \"time\": \"20:00\", \"teacher_id\": 1}], \"6\": [{\"room\": 1, \"time\": \"16:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:48:18', '2025-11-30 19:48:18'),
(51, 1, 'Тимофей', NULL, NULL, NULL, NULL, NULL, 3, 'S', 'individual', 'monthly', 'per_lesson', 5000, 1500, '{\"6\": [{\"room\": 1, \"time\": \"12:00\", \"teacher_id\": 1}]}', NULL, 1, '2025-11-30 19:48:56', '2025-11-30 19:48:56');

-- --------------------------------------------------------

--
-- Структура таблицы `teachers`
--

CREATE TABLE IF NOT EXISTS `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telegram_id` bigint(20) DEFAULT NULL,
  `telegram_username` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formula_id_group` int(11) DEFAULT NULL,
  `formula_id_individual` int(11) DEFAULT NULL,
  `formula_id` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_id` (`telegram_id`),
  KEY `idx_telegram_id` (`telegram_id`),
  KEY `idx_active` (`active`),
  KEY `idx_formula_id` (`formula_id`),
  KEY `idx_formula_group` (`formula_id_group`),
  KEY `idx_formula_individual` (`formula_id_individual`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `teachers`
--

INSERT INTO `teachers` (`id`, `name`, `display_name`, `telegram_id`, `telegram_username`, `phone`, `email`, `formula_id_group`, `formula_id_individual`, `formula_id`, `active`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Станислав Олегович', 'Palomig', 245710727, 'Palomig', '+79103017110', NULL, 4, 3, 4, 1, 'легенда', '2025-11-15 06:32:31', '2025-12-01 23:27:27'),
(2, 'Руслан Романович', 'Руслан', 704366908, 'hiallglhf', '+79017186366', NULL, 4, 3, 4, 1, 'не легенда', '2025-11-21 22:08:36', '2025-12-01 23:27:27');

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `teacher_stats`
-- (См. Ниже фактическое представление)
--
CREATE TABLE IF NOT EXISTS `teacher_stats` (
`teacher_id` int(11)
,`teacher_name` varchar(100)
,`total_lessons` bigint(21)
,`completed_lessons` bigint(21)
,`total_earned` decimal(32,0)
,`total_paid` decimal(32,0)
,`pending_amount` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Структура таблицы `telegram_updates`
--

CREATE TABLE IF NOT EXISTS `telegram_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `update_id` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_update_id` (`update_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4;

--
-- Дамп данных таблицы `telegram_updates`
--

INSERT INTO `telegram_updates` (`id`, `update_id`, `created_at`) VALUES
(1, 326438367, '2025-11-28 03:37:07'),
(2, 326438368, '2025-11-28 03:37:13'),
(3, 326438369, '2025-11-28 03:37:18'),
(4, 326438370, '2025-11-30 18:10:35'),
(5, 326438371, '2025-12-01 15:12:45'),
(6, 326438372, '2025-12-01 15:12:47'),
(7, 326438373, '2025-12-01 16:13:34'),
(8, 326438374, '2025-12-01 16:13:39'),
(9, 326438375, '2025-12-01 17:12:49'),
(10, 326438376, '2025-12-01 17:12:50'),
(11, 326438377, '2025-12-01 18:14:02'),
(12, 326438378, '2025-12-01 18:14:05'),
(13, 326438379, '2025-12-01 19:12:07'),
(14, 326438380, '2025-12-01 20:12:48'),
(15, 326438381, '2025-12-01 20:12:56'),
(16, 326438382, '2025-12-01 20:13:53');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','owner') COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `name`, `email`, `role`, `active`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$LrQFr6OWhBg.6xkIFjeh9OrskQL/rktxWG6LEdv4w6OF5iB/ELWQK', 'Администратор', NULL, 'owner', 1, '2025-11-15 04:29:26', '2025-11-15 05:22:56');

-- --------------------------------------------------------

--
-- Структура для представления `lessons_stats`
--
DROP TABLE IF EXISTS `lessons_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`cw95865_admin`@`localhost` SQL SECURITY DEFINER VIEW `lessons_stats`  AS SELECT `li`.`id` AS `lesson_id`, `li`.`lesson_date` AS `lesson_date`, `t`.`name` AS `teacher_name`, `li`.`lesson_type` AS `lesson_type`, `li`.`expected_students` AS `expected_students`, `li`.`actual_students` AS `actual_students`, `li`.`status` AS `status`, coalesce(`p`.`amount`,0) AS `payment_amount`, `p`.`status` AS `payment_status` FROM ((`lessons_instance` `li` left join `teachers` `t` on((`li`.`teacher_id` = `t`.`id`))) left join `payments` `p` on((`li`.`id` = `p`.`lesson_instance_id`))) ORDER BY `li`.`lesson_date` DESC, `li`.`time_start` ASC ;

-- --------------------------------------------------------

--
-- Структура для представления `teacher_stats`
--
DROP TABLE IF EXISTS `teacher_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`cw95865_admin`@`localhost` SQL SECURITY DEFINER VIEW `teacher_stats`  AS SELECT `t`.`id` AS `teacher_id`, `t`.`name` AS `teacher_name`, count(distinct `li`.`id`) AS `total_lessons`, count(distinct (case when (`li`.`status` = 'completed') then `li`.`id` end)) AS `completed_lessons`, coalesce(sum((case when (`p`.`status` <> 'cancelled') then `p`.`amount` else 0 end)),0) AS `total_earned`, coalesce(sum((case when (`p`.`status` = 'paid') then `p`.`amount` else 0 end)),0) AS `total_paid`, coalesce(sum((case when (`p`.`status` = 'pending') then `p`.`amount` else 0 end)),0) AS `pending_amount` FROM ((`teachers` `t` left join `lessons_instance` `li` on(((`t`.`id` = `li`.`teacher_id`) or (`t`.`id` = `li`.`substitute_teacher_id`)))) left join `payments` `p` on((`t`.`id` = `p`.`teacher_id`))) WHERE (`t`.`active` = 1) GROUP BY `t`.`id` ;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `attendance_log`
--
ALTER TABLE `attendance_log`
  ADD CONSTRAINT `attendance_log_ibfk_1` FOREIGN KEY (`lesson_instance_id`) REFERENCES `lessons_instance` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_log_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `audit_log_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `lessons_instance`
--
ALTER TABLE `lessons_instance`
  ADD CONSTRAINT `lessons_instance_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `lessons_template` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lessons_instance_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lessons_instance_ibfk_3` FOREIGN KEY (`substitute_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lessons_instance_ibfk_4` FOREIGN KEY (`formula_id`) REFERENCES `payment_formulas` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `lessons_template`
--
ALTER TABLE `lessons_template`
  ADD CONSTRAINT `lessons_template_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lessons_template_ibfk_2` FOREIGN KEY (`formula_id`) REFERENCES `payment_formulas` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `lesson_students`
--
ALTER TABLE `lesson_students`
  ADD CONSTRAINT `lesson_students_ibfk_1` FOREIGN KEY (`lesson_instance_id`) REFERENCES `lessons_instance` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_lesson_template` FOREIGN KEY (`lesson_template_id`) REFERENCES `lessons_template` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`lesson_instance_id`) REFERENCES `lessons_instance` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `payout_cycle_payments`
--
ALTER TABLE `payout_cycle_payments`
  ADD CONSTRAINT `payout_cycle_payments_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `payout_cycles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payout_cycle_payments_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teachers_formula` FOREIGN KEY (`formula_id`) REFERENCES `payment_formulas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_teachers_formula_group` FOREIGN KEY (`formula_id_group`) REFERENCES `payment_formulas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_teachers_formula_individual` FOREIGN KEY (`formula_id_individual`) REFERENCES `payment_formulas` (`id`) ON DELETE SET NULL;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
