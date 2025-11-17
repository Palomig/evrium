<?php
/**
 * API для управления расписанием
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

// Устанавливаем JSON заголовки
header('Content-Type: application/json; charset=utf-8');

// Требуем авторизацию
if (!isLoggedIn()) {
    jsonError('Требуется авторизация', 401);
}

// Получаем действие
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Маршрутизация по действиям
switch ($action) {
    case 'list_templates':
        handleListTemplates();
        break;
    case 'get_template':
        handleGetTemplate();
        break;
    case 'add_template':
        handleAddTemplate();
        break;
    case 'update_template':
        handleUpdateTemplate();
        break;
    case 'delete_template':
        handleDeleteTemplate();
        break;
    case 'get_week':
        handleGetWeek();
        break;
    case 'generate_week':
        handleGenerateWeek();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Получить список шаблонов
 */
function handleListTemplates() {
    $templates = dbQuery(
        "SELECT lt.*, t.name as teacher_name, pf.name as formula_name
         FROM lessons_template lt
         LEFT JOIN teachers t ON lt.teacher_id = t.id
         LEFT JOIN payment_formulas pf ON lt.formula_id = pf.id
         WHERE lt.active = 1
         ORDER BY lt.day_of_week ASC, lt.time_start ASC",
        []
    );

    jsonSuccess($templates);
}

/**
 * Получить один шаблон
 */
function handleGetTemplate() {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID шаблона', 400);
    }

    $template = dbQueryOne(
        "SELECT * FROM lessons_template WHERE id = ?",
        [$id]
    );

    if (!$template) {
        jsonError('Шаблон не найден', 404);
    }

    jsonSuccess($template);
}

/**
 * Добавить шаблон урока
 */
function handleAddTemplate() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация
    $teacherId = filter_var($data['teacher_id'] ?? 0, FILTER_VALIDATE_INT);
    $dayOfWeek = filter_var($data['day_of_week'] ?? 0, FILTER_VALIDATE_INT);
    $room = filter_var($data['room'] ?? 1, FILTER_VALIDATE_INT);
    $timeStart = $data['time_start'] ?? '';
    $timeEnd = $data['time_end'] ?? '';
    $lessonType = $data['lesson_type'] ?? 'group';
    $subject = trim($data['subject'] ?? '');
    $expectedStudents = filter_var($data['expected_students'] ?? 1, FILTER_VALIDATE_INT);

    // formula_id может быть NULL, поэтому обрабатываем отдельно
    $formulaId = null;
    if (isset($data['formula_id']) && $data['formula_id']) {
        $formulaId = filter_var($data['formula_id'], FILTER_VALIDATE_INT);
        if ($formulaId === false || $formulaId === 0) {
            $formulaId = null;
        }
    }

    $tier = trim($data['tier'] ?? 'C');
    $grades = trim($data['grades'] ?? '');
    $students = $data['students'] ?? '';

    if (!$teacherId) {
        jsonError('Выберите преподавателя', 400);
    }

    if ($dayOfWeek < 1 || $dayOfWeek > 7) {
        jsonError('Неверный день недели', 400);
    }

    if ($room < 1 || $room > 3) {
        jsonError('Неверный номер кабинета', 400);
    }

    if (!$timeStart || !$timeEnd) {
        jsonError('Укажите время урока', 400);
    }

    if ($expectedStudents < 1) {
        jsonError('Количество учеников должно быть больше 0', 400);
    }

    // Создаём шаблон
    $templateId = null;
    $lastError = null;

    try {
        // Сначала пробуем с новыми полями (room, tier, grades, students)
        $templateId = dbExecute(
            "INSERT INTO lessons_template
             (teacher_id, day_of_week, room, time_start, time_end, lesson_type,
              subject, expected_students, formula_id, tier, grades, students, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [$teacherId, $dayOfWeek, $room, $timeStart, $timeEnd, $lessonType,
             $subject ?: null, $expectedStudents, $formulaId, $tier, $grades ?: null, $students ?: null]
        );

        if (!$templateId) {
            $lastError = "dbExecute returned empty value (full fields)";
            error_log($lastError);
        }
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
        $lastError = $errorMsg;
        error_log("Failed to create template (with new fields): " . $errorMsg);

        // Если ошибка из-за отсутствующих полей - пробуем без них
        if (strpos($errorMsg, 'Unknown column') !== false) {
            try {
                $templateId = dbExecute(
                    "INSERT INTO lessons_template
                     (teacher_id, day_of_week, time_start, time_end, lesson_type,
                      subject, expected_students, formula_id, active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)",
                    [$teacherId, $dayOfWeek, $timeStart, $timeEnd, $lessonType,
                     $subject ?: null, $expectedStudents, $formulaId]
                );

                if (!$templateId) {
                    $lastError = "dbExecute returned empty value (fallback fields)";
                    error_log($lastError);
                }
            } catch (PDOException $e2) {
                $lastError = $e2->getMessage();
                error_log("Failed to create template (fallback): " . $lastError);
                jsonError('Ошибка БД (fallback): ' . $lastError, 500);
            }
        } else {
            // Другая ошибка - показываем её
            jsonError('Ошибка БД: ' . $errorMsg, 500);
        }
    } catch (Exception $e) {
        $lastError = $e->getMessage();
        error_log("Unexpected error creating template: " . $lastError);
        jsonError('Неожиданная ошибка: ' . $lastError, 500);
    }

    if ($templateId) {
        logAudit('template_created', 'template', $templateId, null, [
            'teacher_id' => $teacherId,
            'day_of_week' => $dayOfWeek,
            'time' => "$timeStart-$timeEnd"
        ], 'Создан шаблон урока');

        $template = dbQueryOne("SELECT * FROM lessons_template WHERE id = ?", [$templateId]);
        jsonSuccess($template);
    } else {
        jsonError('Не удалось создать шаблон. Последняя ошибка: ' . ($lastError ?: 'unknown'), 500);
    }
}

/**
 * Обновить шаблон урока
 */
function handleUpdateTemplate() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID шаблона', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM lessons_template WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Шаблон не найден', 404);
    }

    // Валидация
    $teacherId = filter_var($data['teacher_id'] ?? 0, FILTER_VALIDATE_INT);
    $dayOfWeek = filter_var($data['day_of_week'] ?? 0, FILTER_VALIDATE_INT);
    $room = filter_var($data['room'] ?? 1, FILTER_VALIDATE_INT);
    $timeStart = $data['time_start'] ?? '';
    $timeEnd = $data['time_end'] ?? '';
    $lessonType = $data['lesson_type'] ?? 'group';
    $subject = trim($data['subject'] ?? '');
    $expectedStudents = filter_var($data['expected_students'] ?? 1, FILTER_VALIDATE_INT);

    // formula_id может быть NULL, поэтому обрабатываем отдельно
    $formulaId = null;
    if (isset($data['formula_id']) && $data['formula_id']) {
        $formulaId = filter_var($data['formula_id'], FILTER_VALIDATE_INT);
        if ($formulaId === false || $formulaId === 0) {
            $formulaId = null;
        }
    }

    $tier = trim($data['tier'] ?? 'C');
    $grades = trim($data['grades'] ?? '');
    $students = $data['students'] ?? '';

    if (!$teacherId) {
        jsonError('Выберите преподавателя', 400);
    }

    if ($dayOfWeek < 1 || $dayOfWeek > 7) {
        jsonError('Неверный день недели', 400);
    }

    if ($room < 1 || $room > 3) {
        jsonError('Неверный номер кабинета', 400);
    }

    if ($expectedStudents < 1) {
        jsonError('Количество учеников должно быть больше 0', 400);
    }

    // Обновляем шаблон
    try {
        // Сначала пробуем с новыми полями (room, tier, grades, students)
        $result = dbExecute(
            "UPDATE lessons_template
             SET teacher_id = ?, day_of_week = ?, room = ?, time_start = ?, time_end = ?,
                 lesson_type = ?, subject = ?, expected_students = ?,
                 formula_id = ?, tier = ?, grades = ?, students = ?, updated_at = NOW()
             WHERE id = ?",
            [$teacherId, $dayOfWeek, $room, $timeStart, $timeEnd, $lessonType,
             $subject ?: null, $expectedStudents, $formulaId, $tier, $grades ?: null, $students ?: null, $id]
        );
    } catch (PDOException $e) {
        // Если ошибка из-за отсутствующих полей - пробуем без них
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $result = dbExecute(
                "UPDATE lessons_template
                 SET teacher_id = ?, day_of_week = ?, time_start = ?, time_end = ?,
                     lesson_type = ?, subject = ?, expected_students = ?,
                     formula_id = ?, updated_at = NOW()
                 WHERE id = ?",
                [$teacherId, $dayOfWeek, $timeStart, $timeEnd, $lessonType,
                 $subject ?: null, $expectedStudents, $formulaId, $id]
            );
        } else {
            throw $e;
        }
    }

    if ($result !== false) {
        logAudit('template_updated', 'template', $id, $existing, [
            'teacher_id' => $teacherId,
            'day_of_week' => $dayOfWeek
        ], 'Обновлён шаблон урока');

        $template = dbQueryOne("SELECT * FROM lessons_template WHERE id = ?", [$id]);
        jsonSuccess($template);
    } else {
        jsonError('Не удалось обновить шаблон', 500);
    }
}

/**
 * Удалить шаблон урока
 */
function handleDeleteTemplate() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID шаблона', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM lessons_template WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Шаблон не найден', 404);
    }

    // Деактивируем шаблон (soft delete)
    try {
        $result = dbExecute(
            "UPDATE lessons_template SET active = 0, updated_at = NOW() WHERE id = ?",
            [$id]
        );

        if ($result !== false) {
            logAudit('template_deleted', 'template', $id, $existing, null, 'Шаблон удалён');
            jsonSuccess(['message' => 'Шаблон удалён']);
        } else {
            jsonError('Не удалось удалить шаблон', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to delete template: " . $e->getMessage());
        jsonError('Ошибка при удалении шаблона', 500);
    }
}

/**
 * Получить уроки на неделю
 */
function handleGetWeek() {
    $date = $_GET['date'] ?? date('Y-m-d');

    // Вычисляем начало и конец недели
    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($date)));

    $lessons = dbQuery(
        "SELECT li.*, t.name as teacher_name
         FROM lessons_instance li
         LEFT JOIN teachers t ON li.teacher_id = t.id
         WHERE li.lesson_date BETWEEN ? AND ?
         ORDER BY li.lesson_date ASC, li.time_start ASC",
        [$weekStart, $weekEnd]
    );

    jsonSuccess([
        'week_start' => $weekStart,
        'week_end' => $weekEnd,
        'lessons' => $lessons
    ]);
}

/**
 * Генерировать уроки на неделю из шаблона
 */
function handleGenerateWeek() {
    $date = $_POST['date'] ?? $_GET['date'] ?? date('Y-m-d');

    // Вычисляем начало и конец недели
    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($date)));

    try {
        // Получаем все активные шаблоны
        $templates = dbQuery(
            "SELECT * FROM lessons_template WHERE active = 1",
            []
        );

        $created = 0;

        foreach ($templates as $template) {
            // Для каждого дня недели проверяем, есть ли уже урок
            $dayOfWeek = $template['day_of_week'];

            // Вычисляем дату для этого дня недели
            $lessonDate = date('Y-m-d', strtotime("monday this week +".($dayOfWeek-1)." days", strtotime($date)));

            // Проверяем, не существует ли уже урок в это время
            $existing = dbQueryOne(
                "SELECT id FROM lessons_instance
                 WHERE lesson_date = ? AND time_start = ? AND teacher_id = ?",
                [$lessonDate, $template['time_start'], $template['teacher_id']]
            );

            if (!$existing) {
                // Создаём урок из шаблона
                dbExecute(
                    "INSERT INTO lessons_instance
                     (template_id, teacher_id, lesson_date, time_start, time_end,
                      lesson_type, subject, expected_students, formula_id, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')",
                    [$template['id'], $template['teacher_id'], $lessonDate,
                     $template['time_start'], $template['time_end'], $template['lesson_type'],
                     $template['subject'], $template['expected_students'], $template['formula_id']]
                );

                $created++;
            }
        }

        logAudit('week_generated', 'schedule', null, null, [
            'week_start' => $weekStart,
            'created' => $created
        ], "Сгенерировано $created уроков на неделю");

        jsonSuccess([
            'message' => "Создано уроков: $created",
            'created' => $created,
            'week_start' => $weekStart,
            'week_end' => $weekEnd
        ]);
    } catch (Exception $e) {
        error_log("Failed to generate week: " . $e->getMessage());
        jsonError('Ошибка при генерации уроков', 500);
    }
}
