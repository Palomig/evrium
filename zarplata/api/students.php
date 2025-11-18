<?php
/**
 * API для управления учениками
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
    case 'toggle_active':
        handleToggleActive();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Получить список всех учеников
 */
function handleList() {
    $students = dbQuery(
        "SELECT * FROM students ORDER BY active DESC, name ASC",
        []
    );

    jsonSuccess($students);
}

/**
 * Получить одного ученика
 */
function handleGet() {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID ученика', 400);
    }

    $student = dbQueryOne(
        "SELECT * FROM students WHERE id = ?",
        [$id]
    );

    if (!$student) {
        jsonError('Ученик не найден', 404);
    }

    jsonSuccess($student);
}

/**
 * Добавить нового ученика
 */
function handleAdd() {
    // Получаем данные из POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация обязательных полей
    $name = trim($data['name'] ?? '');

    if (empty($name)) {
        jsonError('ФИО ученика обязательно', 400);
    }

    if (mb_strlen($name) > 100) {
        jsonError('ФИО слишком длинное (максимум 100 символов)', 400);
    }

    // Остальные поля
    $phone = trim($data['phone'] ?? '');
    $parentPhone = trim($data['parent_phone'] ?? '');
    $email = trim($data['email'] ?? '');
    $notes = trim($data['notes'] ?? '');

    // Класс (может быть NULL)
    $class = null;
    if (isset($data['class']) && $data['class'] !== '') {
        $class = filter_var($data['class'], FILTER_VALIDATE_INT);
        if ($class === false) {
            jsonError('Неверный формат класса', 400);
        }
    }

    // Тип занятия
    $lessonType = $data['lesson_type'] ?? 'group';
    if (!in_array($lessonType, ['group', 'individual'])) {
        jsonError('Неверный тип занятия', 400);
    }

    // Цена за месяц
    $monthlyPrice = filter_var($data['monthly_price'] ?? 5000, FILTER_VALIDATE_INT);
    if ($monthlyPrice === false || $monthlyPrice < 0) {
        jsonError('Неверная цена', 400);
    }

    // День недели (может быть NULL)
    $lessonDay = null;
    if (isset($data['lesson_day']) && $data['lesson_day'] !== '') {
        $lessonDay = filter_var($data['lesson_day'], FILTER_VALIDATE_INT);
        if ($lessonDay === false || $lessonDay < 1 || $lessonDay > 7) {
            jsonError('Неверный день недели (должен быть от 1 до 7)', 400);
        }
    }

    // Время занятия (может быть NULL)
    $lessonTime = isset($data['lesson_time']) && $data['lesson_time'] !== '' ? $data['lesson_time'] : null;

    // Валидация email
    if ($email && !isValidEmail($email)) {
        jsonError('Неверный формат email', 400);
    }

    // Создаем ученика
    try {
        // Пробуем вставить с новыми полями
        try {
            $studentId = dbExecute(
                "INSERT INTO students (name, phone, parent_phone, email, class, lesson_type, monthly_price, lesson_day, lesson_time, notes, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                [$name, $phone ?: null, $parentPhone ?: null, $email ?: null, $class, $lessonType, $monthlyPrice, $lessonDay, $lessonTime, $notes ?: null]
            );
        } catch (PDOException $e) {
            // Если новых полей еще нет в базе, используем старые
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $studentId = dbExecute(
                    "INSERT INTO students (name, phone, parent_phone, email, class, notes, active)
                     VALUES (?, ?, ?, ?, ?, ?, 1)",
                    [$name, $phone ?: null, $parentPhone ?: null, $email ?: null, $class, $notes ?: null]
                );
            } else {
                throw $e;
            }
        }

        if ($studentId) {
            // Логируем создание
            logAudit('student_created', 'student', $studentId, null, [
                'name' => $name,
                'class' => $class,
                'lesson_type' => $lessonType
            ], 'Создан новый ученик');

            // Возвращаем созданного ученика
            $student = dbQueryOne("SELECT * FROM students WHERE id = ?", [$studentId]);
            jsonSuccess($student);
        } else {
            jsonError('Не удалось создать ученика', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to create student: " . $e->getMessage());
        jsonError('Ошибка при создании ученика', 500);
    }
}

/**
 * Обновить данные ученика
 */
function handleUpdate() {
    // Получаем данные из POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID ученика', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM students WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Ученик не найден', 404);
    }

    // Валидация
    $name = trim($data['name'] ?? '');

    if (empty($name)) {
        jsonError('ФИО ученика обязательно', 400);
    }

    if (mb_strlen($name) > 100) {
        jsonError('ФИО слишком длинное (максимум 100 символов)', 400);
    }

    // Остальные поля
    $phone = trim($data['phone'] ?? '');
    $parentPhone = trim($data['parent_phone'] ?? '');
    $email = trim($data['email'] ?? '');
    $notes = trim($data['notes'] ?? '');

    // Класс (может быть NULL)
    $class = null;
    if (isset($data['class']) && $data['class'] !== '') {
        $class = filter_var($data['class'], FILTER_VALIDATE_INT);
        if ($class === false) {
            jsonError('Неверный формат класса', 400);
        }
    }

    // Тип занятия
    $lessonType = $data['lesson_type'] ?? 'group';
    if (!in_array($lessonType, ['group', 'individual'])) {
        jsonError('Неверный тип занятия', 400);
    }

    // Цена за месяц
    $monthlyPrice = filter_var($data['monthly_price'] ?? 5000, FILTER_VALIDATE_INT);
    if ($monthlyPrice === false || $monthlyPrice < 0) {
        jsonError('Неверная цена', 400);
    }

    // День недели (может быть NULL)
    $lessonDay = null;
    if (isset($data['lesson_day']) && $data['lesson_day'] !== '') {
        $lessonDay = filter_var($data['lesson_day'], FILTER_VALIDATE_INT);
        if ($lessonDay === false || $lessonDay < 1 || $lessonDay > 7) {
            jsonError('Неверный день недели (должен быть от 1 до 7)', 400);
        }
    }

    // Время занятия (может быть NULL)
    $lessonTime = isset($data['lesson_time']) && $data['lesson_time'] !== '' ? $data['lesson_time'] : null;

    // Валидация email
    if ($email && !isValidEmail($email)) {
        jsonError('Неверный формат email', 400);
    }

    // Обновляем ученика
    try {
        // Пробуем обновить с новыми полями
        try {
            $result = dbExecute(
                "UPDATE students
                 SET name = ?, phone = ?, parent_phone = ?, email = ?, class = ?, lesson_type = ?, monthly_price = ?, lesson_day = ?, lesson_time = ?, notes = ?, updated_at = NOW()
                 WHERE id = ?",
                [$name, $phone ?: null, $parentPhone ?: null, $email ?: null, $class, $lessonType, $monthlyPrice, $lessonDay, $lessonTime, $notes ?: null, $id]
            );
        } catch (PDOException $e) {
            // Если новых полей еще нет в базе, используем старые
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $result = dbExecute(
                    "UPDATE students
                     SET name = ?, phone = ?, parent_phone = ?, email = ?, class = ?, notes = ?, updated_at = NOW()
                     WHERE id = ?",
                    [$name, $phone ?: null, $parentPhone ?: null, $email ?: null, $class, $notes ?: null, $id]
                );
            } else {
                throw $e;
            }
        }

        if ($result !== false) {
            // Логируем изменение
            logAudit('student_updated', 'student', $id, $existing, [
                'name' => $name,
                'class' => $class,
                'lesson_type' => $lessonType
            ], 'Обновлены данные ученика');

            // Возвращаем обновленного ученика
            $student = dbQueryOne("SELECT * FROM students WHERE id = ?", [$id]);
            jsonSuccess($student);
        } else {
            jsonError('Не удалось обновить ученика', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to update student: " . $e->getMessage());
        jsonError('Ошибка при обновлении ученика', 500);
    }
}

/**
 * Удалить ученика (на самом деле деактивировать)
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
        jsonError('Неверный ID ученика', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM students WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Ученик не найден', 404);
    }

    // Проверяем, есть ли связанные данные (посещаемость)
    $attendanceCount = dbQueryOne(
        "SELECT COUNT(*) as count FROM attendance_log WHERE student_id = ?",
        [$id]
    );

    if ($attendanceCount['count'] > 0) {
        // Если есть записи посещаемости - деактивируем, а не удаляем
        $result = dbExecute(
            "UPDATE students SET active = 0, updated_at = NOW() WHERE id = ?",
            [$id]
        );

        if ($result !== false) {
            logAudit('student_deactivated', 'student', $id, $existing, ['active' => 0], 'Ученик деактивирован');
            jsonSuccess(['message' => 'Ученик деактивирован (есть связанные записи посещаемости)']);
        } else {
            jsonError('Не удалось деактивировать ученика', 500);
        }
    } else {
        // Если нет записей - можно удалить
        $result = dbExecute("DELETE FROM students WHERE id = ?", [$id]);

        if ($result) {
            logAudit('student_deleted', 'student', $id, $existing, null, 'Ученик удалён');
            jsonSuccess(['message' => 'Ученик удалён']);
        } else {
            jsonError('Не удалось удалить ученика', 500);
        }
    }
}

/**
 * Переключить активность ученика
 */
function handleToggleActive() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID ученика', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM students WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Ученик не найден', 404);
    }

    // Переключаем активность
    $newActive = $existing['active'] ? 0 : 1;
    $result = dbExecute(
        "UPDATE students SET active = ?, updated_at = NOW() WHERE id = ?",
        [$newActive, $id]
    );

    if ($result !== false) {
        logAudit(
            $newActive ? 'student_activated' : 'student_deactivated',
            'student',
            $id,
            ['active' => $existing['active']],
            ['active' => $newActive],
            $newActive ? 'Ученик активирован' : 'Ученик деактивирован'
        );

        $student = dbQueryOne("SELECT * FROM students WHERE id = ?", [$id]);
        jsonSuccess($student);
    } else {
        jsonError('Не удалось изменить статус ученика', 500);
    }
}
