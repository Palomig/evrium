-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Ноя 18 2025 г., 15:35
-- Обновлено: Ноя 19 2025 г., 00:25 (окончательное исправление VIEW)
-- Версия сервера: 5.7.44-48
-- Версия PHP: 7.4.33
--
-- ИЗМЕНЕНИЯ В ЭТОЙ ВЕРСИИ:
-- 1. Таблица students: удалены устаревшие поля (phone, email, monthly_price, lesson_day, lesson_time)
-- 2. Таблица students: добавлен foreign key constraint на teacher_id
-- 3. Таблица lessons_template: объединены дублирующиеся шаблоны (ID 14+16, 15+17)
-- 4. Ученики с разными тирами теперь объединены в одну группу
-- 5. Триггеры: добавлен DROP TRIGGER IF EXISTS для предотвращения ошибки при импорте
-- 6. Таблицы: добавлен DROP TABLE IF EXISTS перед каждым CREATE TABLE
-- 7. Foreign keys: отключены на время импорта (SET FOREIGN_KEY_CHECKS = 0/1)
-- 8. VIEW: добавлен DROP TABLE + DROP VIEW перед созданием представлений (полностью исправляет #1050)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;


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
DROP TABLE IF EXISTS `attendance_log`;

CREATE TABLE `attendance_log` (
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
DROP TRIGGER IF EXISTS `audit_attendance_log`$$
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
DROP TABLE IF EXISTS `audit_log`;

CREATE TABLE `audit_log` (
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
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(28, 'payment_cancelled', 'payment', 3, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 08:49:39'),
(29, 'payment_approved', 'payment', 21, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', 'Выплата одобрена', '95.73.229.232', '2025-11-18 09:01:38'),
(30, 'payment_cancelled', 'payment', 3, 1, NULL, NULL, '{\"status\":\"cancelled\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:21'),
(31, 'payment_cancelled', 'payment', 1, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:30'),
(32, 'payment_cancelled', 'payment', 2, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:32'),
(33, 'payment_cancelled', 'payment', 4, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:35'),
(34, 'payment_cancelled', 'payment', 5, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:37'),
(35, 'payment_cancelled', 'payment', 6, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:39'),
(36, 'payment_cancelled', 'payment', 7, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:41'),
(37, 'payment_cancelled', 'payment', 8, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:45'),
(38, 'payment_cancelled', 'payment', 10, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:47'),
(39, 'payment_cancelled', 'payment', 9, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:49'),
(40, 'payment_cancelled', 'payment', 11, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:51'),
(41, 'payment_cancelled', 'payment', 12, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:52'),
(42, 'payment_cancelled', 'payment', 13, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:55'),
(43, 'payment_cancelled', 'payment', 14, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:58'),
(44, 'payment_cancelled', 'payment', 15, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:24:59'),
(45, 'payment_cancelled', 'payment', 16, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:25:02'),
(46, 'payment_cancelled', 'payment', 18, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:25:04'),
(47, 'payment_cancelled', 'payment', 17, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:25:07'),
(48, 'payment_cancelled', 'payment', 19, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:25:09'),
(49, 'payment_cancelled', 'payment', 20, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:25:12'),
(50, 'payment_paid', 'payment', 21, 1, NULL, NULL, '{\"status\":\"approved\"}', '{\"status\":\"paid\",\"paid_at\":\"2025-11-18\"}', 'Выплата отмечена как выплаченная', '95.73.229.232', '2025-11-18 09:29:05'),
(51, 'payment_cancelled', 'payment', 22, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', 'Выплата отменена', '95.73.229.232', '2025-11-18 09:29:12'),
(52, 'payment_approved', 'payment', 23, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', 'Выплата одобрена', '95.73.229.232', '2025-11-18 09:29:15'),
(53, 'payment_approved', 'payment', 24, 1, NULL, NULL, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', 'Выплата одобрена', '95.73.229.232', '2025-11-18 09:29:18'),
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
(73, 'student_created', 'student', 4, 1, NULL, NULL, NULL, '{\"name\":\"\\u0412\\u0430\\u043d\\u044f\",\"class\":8,\"lesson_type\":\"group\",\"tier\":\"C\"}', 'Создан новый ученик', '95.73.229.232', '2025-11-18 15:34:42');

-- --------------------------------------------------------

--
-- Структура таблицы `bot_states`
--
DROP TABLE IF EXISTS `bot_states`;

CREATE TABLE `bot_states` (
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
DROP TABLE IF EXISTS `lessons_instance`;

CREATE TABLE `lessons_instance` (
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Триггеры `lessons_instance`
--
DELIMITER $$
DROP TRIGGER IF EXISTS `calculate_payment_after_lesson_complete`$$
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
DROP VIEW IF EXISTS `lessons_stats`;
DROP TABLE IF EXISTS `lessons_stats`;

CREATE TABLE `lessons_stats` (
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
DROP TABLE IF EXISTS `lessons_template`;

CREATE TABLE `lessons_template` (
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `lessons_template`
--

INSERT INTO `lessons_template` (`id`, `teacher_id`, `day_of_week`, `room`, `time_start`, `time_end`, `lesson_type`, `subject`, `tier`, `grades`, `students`, `expected_students`, `formula_id`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '16:00:00', '17:00:00', 'individual', 'Математика', 'C', NULL, NULL, 1, NULL, 0, '2025-11-16 16:51:02', '2025-11-17 05:07:12'),
(2, 1, 1, 1, '17:00:00', '18:00:00', 'group', 'Математика', 'C', NULL, NULL, 5, NULL, 0, '2025-11-16 16:51:41', '2025-11-17 05:07:15'),
(3, 1, 3, 1, '16:00:00', '17:00:00', 'individual', 'Математика', 'C', NULL, NULL, 1, NULL, 0, '2025-11-17 01:08:33', '2025-11-17 05:07:17'),
(4, 1, 2, 1, '20:00:00', '21:00:00', 'group', 'Математика', 'C', NULL, NULL, 1, NULL, 0, '2025-11-17 01:20:28', '2025-11-17 05:07:18'),
(10, 1, 2, 1, '16:00:00', '17:00:00', 'group', 'Математика', 'C', '6', '[\"Вика\",\"Маша\",\"Ульяна\",\"Коля\",\"Кирилл\"]', 6, NULL, 0, '2025-11-17 05:01:22', '2025-11-17 05:07:20'),
(11, 1, 1, 1, '16:00:00', '17:00:00', 'individual', 'Математика', 'D', '9', '[\"Влада\"]', 1, NULL, 0, '2025-11-17 05:07:57', '2025-11-18 12:34:12'),
(12, 1, 2, 1, '08:00:00', '09:00:00', 'group', 'Математика', 'C', '6, 7, 8', '[\"Вика\",\"Вика\",\"Вика\",\"Вика\"]', 6, NULL, 0, '2025-11-18 07:24:37', '2025-11-18 12:34:03'),
(13, 1, 2, 1, '09:00:00', '10:00:00', 'group', 'Математика', 'C', NULL, '[\"Вика\",\"Вика\",\"Вика\",\"Вика\"]', 6, NULL, 0, '2025-11-18 08:49:06', '2025-11-18 12:34:07'),
(14, 1, 2, 1, '18:00:00', '19:30:00', 'group', NULL, 'C', NULL, '[\"Арина\",\"Ваня\"]', 2, NULL, 1, '2025-11-18 15:33:30', '2025-11-18 15:33:30'),
(15, 1, 6, 1, '15:00:00', '16:30:00', 'group', NULL, 'C', NULL, '[\"Арина\",\"Ваня\"]', 2, NULL, 1, '2025-11-18 15:33:30', '2025-11-18 15:33:30'),
(16, 1, 2, 1, '18:00:00', '19:30:00', 'group', NULL, 'C', NULL, '[\"Ваня\"]', 1, NULL, 0, '2025-11-18 15:34:42', '2025-11-18 15:34:42'),
(17, 1, 6, 1, '15:00:00', '16:30:00', 'group', NULL, 'C', NULL, '[\"Ваня\"]', 1, NULL, 0, '2025-11-18 15:34:42', '2025-11-18 15:34:42');

-- --------------------------------------------------------

--
-- Структура таблицы `lesson_students`
--
DROP TABLE IF EXISTS `lesson_students`;

CREATE TABLE `lesson_students` (
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
DROP TABLE IF EXISTS `payments`;

CREATE TABLE `payments` (
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `payments`
--

INSERT INTO `payments` (`id`, `teacher_id`, `lesson_instance_id`, `lesson_template_id`, `amount`, `payment_type`, `calculation_method`, `period_start`, `period_end`, `status`, `paid_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 12, 0, 'lesson', 'Пришло 4 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:34:24', '2025-11-18 09:24:30'),
(2, 1, NULL, 12, 0, 'lesson', 'Пришло 4 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:34:24', '2025-11-18 09:24:32'),
(3, 1, NULL, 12, 0, 'lesson', 'Пришло 4 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:34:26', '2025-11-18 09:24:21'),
(4, 1, NULL, 12, 0, 'lesson', 'Пришло 4 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:34:30', '2025-11-18 09:24:35'),
(5, 1, NULL, 12, 0, 'lesson', 'Пришло 4 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:34:39', '2025-11-18 09:24:37'),
(6, 1, NULL, 12, 0, 'lesson', 'Пришло 4 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:34:55', '2025-11-18 09:24:39'),
(7, 1, NULL, 12, 0, 'lesson', 'Пришло 4 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:35:27', '2025-11-18 09:24:41'),
(8, 1, NULL, 12, 0, 'lesson', 'Пришло 4 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:36:31', '2025-11-18 09:24:45'),
(9, 1, NULL, 11, 0, 'lesson', 'Пришло 0 из 1', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:38:48', '2025-11-18 09:24:49'),
(10, 1, NULL, 11, 0, 'lesson', 'Пришло 0 из 1', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:38:48', '2025-11-18 09:24:47'),
(11, 1, NULL, 11, 0, 'lesson', 'Пришло 0 из 1', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:38:50', '2025-11-18 09:24:51'),
(12, 1, NULL, 11, 0, 'lesson', 'Пришло 0 из 1', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:38:54', '2025-11-18 09:24:52'),
(13, 1, NULL, 11, 0, 'lesson', 'Пришло 0 из 1', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:39:02', '2025-11-18 09:24:55'),
(14, 1, NULL, 11, 0, 'lesson', 'Пришло 0 из 1', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:39:18', '2025-11-18 09:24:58'),
(15, 1, NULL, 11, 0, 'lesson', 'Пришло 0 из 1', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:39:50', '2025-11-18 09:24:59'),
(16, 1, NULL, 11, 0, 'lesson', 'Пришло 0 из 1', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:40:54', '2025-11-18 09:25:02'),
(17, 1, NULL, 13, 650, 'lesson', 'Пришло 2 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:49:20', '2025-11-18 09:25:07'),
(18, 1, NULL, 13, 650, 'lesson', 'Пришло 2 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:49:20', '2025-11-18 09:25:04'),
(19, 1, NULL, 13, 650, 'lesson', 'Пришло 2 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:49:22', '2025-11-18 09:25:09'),
(20, 1, NULL, 13, 650, 'lesson', 'Пришло 2 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:49:26', '2025-11-18 09:25:12'),
(21, 1, NULL, 13, 650, 'lesson', 'Пришло 2 из 6', NULL, NULL, 'paid', '2025-11-18 00:00:00', NULL, '2025-11-18 08:49:34', '2025-11-18 09:29:05'),
(22, 1, NULL, 13, 650, 'lesson', 'Пришло 2 из 6', NULL, NULL, 'cancelled', NULL, NULL, '2025-11-18 08:49:50', '2025-11-18 09:29:12'),
(23, 1, NULL, 13, 650, 'lesson', 'Пришло 2 из 6', NULL, NULL, 'approved', NULL, NULL, '2025-11-18 08:50:23', '2025-11-18 09:29:15'),
(24, 1, NULL, 13, 650, 'lesson', 'Пришло 2 из 6', NULL, NULL, 'approved', NULL, NULL, '2025-11-18 08:51:27', '2025-11-18 09:29:18');

-- --------------------------------------------------------

--
-- Структура таблицы `payment_formulas`
--
DROP TABLE IF EXISTS `payment_formulas`;

CREATE TABLE `payment_formulas` (
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
DROP TABLE IF EXISTS `payout_cycles`;

CREATE TABLE `payout_cycles` (
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
DROP TABLE IF EXISTS `payout_cycle_payments`;

CREATE TABLE `payout_cycle_payments` (
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
DROP TABLE IF EXISTS `settings`;

CREATE TABLE `settings` (
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
DROP TABLE IF EXISTS `students`;

CREATE TABLE `students` (
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
  KEY `idx_tier` (`tier`),
  CONSTRAINT `fk_students_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `students`
--

INSERT INTO `students` (`id`, `teacher_id`, `name`, `student_telegram`, `student_whatsapp`, `parent_name`, `parent_telegram`, `parent_whatsapp`, `class`, `tier`, `lesson_type`, `payment_type_group`, `payment_type_individual`, `price_group`, `price_individual`, `schedule`, `notes`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Влада', NULL, NULL, NULL, NULL, '79096645362', 9, 'C', 'individual', 'monthly', 'per_lesson', 5000, 1500, '{\"1\": \"16:00\", \"2\": \"16:00\", \"5\": \"17:00\"}', NULL, 1, '2025-11-18 14:40:35', '2025-11-18 15:20:29'),
(2, 1, 'Настя', 'nastch1', NULL, NULL, NULL, '79060815386', 9, 'C', 'individual', 'monthly', 'per_lesson', 5000, 1500, '{\"2\": \"17:00\", \"4\": \"17:00\"}', NULL, 1, '2025-11-18 14:43:34', '2025-11-18 15:20:29'),
(3, 1, 'Арина', 'Arinali20', NULL, 'Наталья', NULL, '79268390696', 8, 'A', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"2\": \"18:00\", \"6\": \"15:00\"}', NULL, 1, '2025-11-18 15:33:30', '2025-11-18 15:33:30'),
(4, 1, 'Ваня', NULL, NULL, 'Юлия', NULL, '79060452561', 8, 'C', 'group', 'monthly', 'per_lesson', 5000, 1500, '{\"2\": \"18:00\", \"6\": \"15:00\"}', NULL, 1, '2025-11-18 15:34:42', '2025-11-18 15:34:42');

-- --------------------------------------------------------

--
-- Структура таблицы `teachers`
--
DROP TABLE IF EXISTS `teachers`;

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `teachers`
--

INSERT INTO `teachers` (`id`, `name`, `telegram_id`, `telegram_username`, `phone`, `email`, `formula_id_group`, `formula_id_individual`, `formula_id`, `active`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Никитин Станислав Олегович', 245710727, 'Palomig', '+79103017110', NULL, NULL, NULL, NULL, 1, 'легенда', '2025-11-15 06:32:31', '2025-11-18 06:29:46');

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `teacher_stats`
-- (См. Ниже фактическое представление)
--
DROP VIEW IF EXISTS `teacher_stats`;
DROP TABLE IF EXISTS `teacher_stats`;

CREATE TABLE `teacher_stats` (
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
-- Структура таблицы `users`
--
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
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
DROP VIEW IF EXISTS `lessons_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`cw95865_admin`@`localhost` SQL SECURITY DEFINER VIEW `lessons_stats`  AS SELECT `li`.`id` AS `lesson_id`, `li`.`lesson_date` AS `lesson_date`, `t`.`name` AS `teacher_name`, `li`.`lesson_type` AS `lesson_type`, `li`.`expected_students` AS `expected_students`, `li`.`actual_students` AS `actual_students`, `li`.`status` AS `status`, coalesce(`p`.`amount`,0) AS `payment_amount`, `p`.`status` AS `payment_status` FROM ((`lessons_instance` `li` left join `teachers` `t` on((`li`.`teacher_id` = `t`.`id`))) left join `payments` `p` on((`li`.`id` = `p`.`lesson_instance_id`))) ORDER BY `li`.`lesson_date` DESC, `li`.`time_start` ASC ;

-- --------------------------------------------------------

--
-- Структура для представления `teacher_stats`
--
DROP TABLE IF EXISTS `teacher_stats`;
DROP VIEW IF EXISTS `teacher_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`cw95865_admin`@`localhost` SQL SECURITY DEFINER VIEW `teacher_stats`  AS SELECT `t`.`id` AS `teacher_id`, `t`.`name` AS `teacher_name`, count(distinct `li`.`id`) AS `total_lessons`, count(distinct (case when (`li`.`status` = 'completed') then `li`.`id` end)) AS `completed_lessons`, coalesce(sum((case when (`p`.`status` <> 'cancelled') then `p`.`amount` else 0 end)),0) AS `total_earned`, coalesce(sum((case when (`p`.`status` = 'paid') then `p`.`amount` else 0 end)),0) AS `total_paid`, coalesce(sum((case when (`p`.`status` = 'pending') then `p`.`amount` else 0 end)),0) AS `pending_amount` FROM ((`teachers` `t` left join `lessons_instance` `li` on(((`t`.`id` = `li`.`teacher_id`) or (`t`.`id` = `li`.`substitute_teacher_id`)))) left join `payments` `p` on((`t`.`id` = `p`.`teacher_id`))) WHERE (`t`.`active` = 1) GROUP BY `t`.`id` ;

SET FOREIGN_KEY_CHECKS = 1;

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
-- Ограничения внешнего ключа таблицы `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

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
