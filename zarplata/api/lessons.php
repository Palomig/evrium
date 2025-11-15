<?php
/**
 * API для управления уроками
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
    case 'list':
        handleList();
        break;
    case 'get':
        handleGet();
        break;
    case 'add':
        handleAdd();
        break;
    case 'update':
        handleUpdate();
        break;
    case 'delete':
        handleDelete();
        break;
    case 'complete':
        handleComplete();
        break;
    case 'cancel':
        handleCancel();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Получить список уроков
 */
function handleList() {
    // Фильтры
    $teacherId = filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT);
    $status = $_GET['status'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 50;
    $offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT) ?: 0;

    $query = "SELECT li.*, t.name as teacher_name,
              CASE WHEN li.substitute_teacher_id IS NOT NULL
                   THEN (SELECT name FROM teachers WHERE id = li.substitute_teacher_id)
                   ELSE NULL
              END as substitute_name,
              pf.name as formula_name
              FROM lessons_instance li
              LEFT JOIN teachers t ON li.teacher_id = t.id
              LEFT JOIN payment_formulas pf ON li.formula_id = pf.id
              WHERE 1=1";

    $params = [];

    if ($teacherId) {
        $query .= " AND (li.teacher_id = ? OR li.substitute_teacher_id = ?)";
        $params[] = $teacherId;
        $params[] = $teacherId;
    }

    if ($status) {
        $query .= " AND li.status = ?";
        $params[] = $status;
    }

    if ($dateFrom) {
        $query .= " AND li.lesson_date >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $query .= " AND li.lesson_date <= ?";
        $params[] = $dateTo;
    }

    $query .= " ORDER BY li.lesson_date DESC, li.time_start DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $lessons = dbQuery($query, $params);

    jsonSuccess($lessons);
}

/**
 * Получить один урок
 */
function handleGet() {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID урока', 400);
    }

    $lesson = dbQueryOne(
        "SELECT li.*, t.name as teacher_name,
         CASE WHEN li.substitute_teacher_id IS NOT NULL
              THEN (SELECT name FROM teachers WHERE id = li.substitute_teacher_id)
              ELSE NULL
         END as substitute_name,
         pf.name as formula_name
         FROM lessons_instance li
         LEFT JOIN teachers t ON li.teacher_id = t.id
         LEFT JOIN payment_formulas pf ON li.formula_id = pf.id
         WHERE li.id = ?",
        [$id]
    );

    if (!$lesson) {
        jsonError('Урок не найден', 404);
    }

    jsonSuccess($lesson);
}

/**
 * Добавить новый урок
 */
function handleAdd() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация
    $teacherId = filter_var($data['teacher_id'] ?? 0, FILTER_VALIDATE_INT);
    $lessonDate = $data['lesson_date'] ?? '';
    $timeStart = $data['time_start'] ?? '';
    $timeEnd = $data['time_end'] ?? '';
    $lessonType = $data['lesson_type'] ?? 'group';
    $subject = trim($data['subject'] ?? '');
    $expectedStudents = filter_var($data['expected_students'] ?? 1, FILTER_VALIDATE_INT);
    $formulaId = filter_var($data['formula_id'] ?? null, FILTER_VALIDATE_INT);
    $notes = trim($data['notes'] ?? '');

    if (!$teacherId) {
        jsonError('Выберите преподавателя', 400);
    }

    if (!$lessonDate) {
        jsonError('Укажите дату урока', 400);
    }

    if (!$timeStart || !$timeEnd) {
        jsonError('Укажите время урока', 400);
    }

    if ($expectedStudents < 1) {
        jsonError('Количество учеников должно быть больше 0', 400);
    }

    // Проверяем существование преподавателя
    $teacher = dbQueryOne("SELECT id FROM teachers WHERE id = ? AND active = 1", [$teacherId]);
    if (!$teacher) {
        jsonError('Преподаватель не найден или неактивен', 404);
    }

    // Создаём урок
    try {
        $lessonId = dbExecute(
            "INSERT INTO lessons_instance
             (teacher_id, lesson_date, time_start, time_end, lesson_type, subject,
              expected_students, formula_id, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)",
            [$teacherId, $lessonDate, $timeStart, $timeEnd, $lessonType,
             $subject ?: null, $expectedStudents, $formulaId, $notes ?: null]
        );

        if ($lessonId) {
            logAudit('lesson_created', 'lesson', $lessonId, null, [
                'teacher_id' => $teacherId,
                'lesson_date' => $lessonDate,
                'time' => "$timeStart-$timeEnd"
            ], 'Создан новый урок');

            $lesson = dbQueryOne("SELECT * FROM lessons_instance WHERE id = ?", [$lessonId]);
            jsonSuccess($lesson);
        } else {
            jsonError('Не удалось создать урок', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to create lesson: " . $e->getMessage());
        jsonError('Ошибка при создании урока', 500);
    }
}

/**
 * Обновить урок
 */
function handleUpdate() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID урока', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM lessons_instance WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Урок не найден', 404);
    }

    // Валидация
    $teacherId = filter_var($data['teacher_id'] ?? 0, FILTER_VALIDATE_INT);
    $lessonDate = $data['lesson_date'] ?? '';
    $timeStart = $data['time_start'] ?? '';
    $timeEnd = $data['time_end'] ?? '';
    $lessonType = $data['lesson_type'] ?? 'group';
    $subject = trim($data['subject'] ?? '');
    $expectedStudents = filter_var($data['expected_students'] ?? 1, FILTER_VALIDATE_INT);
    $formulaId = filter_var($data['formula_id'] ?? null, FILTER_VALIDATE_INT);
    $notes = trim($data['notes'] ?? '');

    if (!$teacherId) {
        jsonError('Выберите преподавателя', 400);
    }

    if ($expectedStudents < 1) {
        jsonError('Количество учеников должно быть больше 0', 400);
    }

    // Обновляем урок
    try {
        $result = dbExecute(
            "UPDATE lessons_instance
             SET teacher_id = ?, lesson_date = ?, time_start = ?, time_end = ?,
                 lesson_type = ?, subject = ?, expected_students = ?,
                 formula_id = ?, notes = ?, updated_at = NOW()
             WHERE id = ?",
            [$teacherId, $lessonDate, $timeStart, $timeEnd, $lessonType,
             $subject ?: null, $expectedStudents, $formulaId, $notes ?: null, $id]
        );

        if ($result !== false) {
            logAudit('lesson_updated', 'lesson', $id, $existing, [
                'teacher_id' => $teacherId,
                'lesson_date' => $lessonDate
            ], 'Обновлён урок');

            $lesson = dbQueryOne("SELECT * FROM lessons_instance WHERE id = ?", [$id]);
            jsonSuccess($lesson);
        } else {
            jsonError('Не удалось обновить урок', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to update lesson: " . $e->getMessage());
        jsonError('Ошибка при обновлении урока', 500);
    }
}

/**
 * Удалить урок
 */
function handleDelete() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID урока', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM lessons_instance WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Урок не найден', 404);
    }

    // Проверяем статус - нельзя удалить завершённый урок с выплатой
    if ($existing['status'] === 'completed') {
        $payment = dbQueryOne(
            "SELECT id FROM payments WHERE lesson_instance_id = ? AND status != 'cancelled'",
            [$id]
        );

        if ($payment) {
            jsonError('Нельзя удалить урок с выплатой. Сначала отмените выплату.', 400);
        }
    }

    // Удаляем урок
    try {
        $result = dbExecute("DELETE FROM lessons_instance WHERE id = ?", [$id]);

        if ($result) {
            logAudit('lesson_deleted', 'lesson', $id, $existing, null, 'Урок удалён');
            jsonSuccess(['message' => 'Урок удалён']);
        } else {
            jsonError('Не удалось удалить урок', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to delete lesson: " . $e->getMessage());
        jsonError('Ошибка при удалении урока', 500);
    }
}

/**
 * Завершить урок
 */
function handleComplete() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
    $actualStudents = filter_var($data['actual_students'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID урока', 400);
    }

    if ($actualStudents < 0) {
        jsonError('Количество учеников не может быть отрицательным', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM lessons_instance WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Урок не найден', 404);
    }

    if ($existing['status'] === 'completed') {
        jsonError('Урок уже завершён', 400);
    }

    // Завершаем урок
    try {
        $result = dbExecute(
            "UPDATE lessons_instance
             SET status = 'completed', actual_students = ?, updated_at = NOW()
             WHERE id = ?",
            [$actualStudents, $id]
        );

        if ($result !== false) {
            logAudit('lesson_completed', 'lesson', $id,
                ['status' => $existing['status']],
                ['status' => 'completed', 'actual_students' => $actualStudents],
                'Урок завершён'
            );

            // Триггер автоматически создаст выплату
            $lesson = dbQueryOne("SELECT * FROM lessons_instance WHERE id = ?", [$id]);
            jsonSuccess($lesson);
        } else {
            jsonError('Не удалось завершить урок', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to complete lesson: " . $e->getMessage());
        jsonError('Ошибка при завершении урока', 500);
    }
}

/**
 * Отменить урок
 */
function handleCancel() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID урока', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM lessons_instance WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Урок не найден', 404);
    }

    // Отменяем урок
    try {
        $result = dbExecute(
            "UPDATE lessons_instance
             SET status = 'cancelled', updated_at = NOW()
             WHERE id = ?",
            [$id]
        );

        if ($result !== false) {
            logAudit('lesson_cancelled', 'lesson', $id,
                ['status' => $existing['status']],
                ['status' => 'cancelled'],
                'Урок отменён'
            );

            // Если была выплата - отменяем её тоже
            dbExecute(
                "UPDATE payments SET status = 'cancelled' WHERE lesson_instance_id = ?",
                [$id]
            );

            $lesson = dbQueryOne("SELECT * FROM lessons_instance WHERE id = ?", [$id]);
            jsonSuccess($lesson);
        } else {
            jsonError('Не удалось отменить урок', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to cancel lesson: " . $e->getMessage());
        jsonError('Ошибка при отмене урока', 500);
    }
}
