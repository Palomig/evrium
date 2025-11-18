<?php
/**
 * API для управления преподавателями
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
 * Получить список всех преподавателей
 */
function handleList() {
    $teachers = dbQuery(
        "SELECT * FROM teachers ORDER BY active DESC, name ASC",
        []
    );

    jsonSuccess($teachers);
}

/**
 * Получить одного преподавателя
 */
function handleGet() {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID преподавателя', 400);
    }

    $teacher = dbQueryOne(
        "SELECT * FROM teachers WHERE id = ?",
        [$id]
    );

    if (!$teacher) {
        jsonError('Преподаватель не найден', 404);
    }

    jsonSuccess($teacher);
}

/**
 * Добавить нового преподавателя
 */
function handleAdd() {
    // Получаем данные из POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация
    $name = trim($data['name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');
    $telegramId = trim($data['telegram_id'] ?? '');
    $telegram_username = trim($data['telegram_username'] ?? '');
    $notes = trim($data['notes'] ?? '');

    // Формулы (могут быть NULL)
    $formulaIdGroup = null;
    if (isset($data['formula_id_group']) && $data['formula_id_group'] !== '') {
        $formulaIdGroup = filter_var($data['formula_id_group'], FILTER_VALIDATE_INT);
        if ($formulaIdGroup === false || $formulaIdGroup === 0) {
            $formulaIdGroup = null;
        }
    }

    $formulaIdIndividual = null;
    if (isset($data['formula_id_individual']) && $data['formula_id_individual'] !== '') {
        $formulaIdIndividual = filter_var($data['formula_id_individual'], FILTER_VALIDATE_INT);
        if ($formulaIdIndividual === false || $formulaIdIndividual === 0) {
            $formulaIdIndividual = null;
        }
    }

    if (empty($name)) {
        jsonError('ФИО преподавателя обязательно', 400);
    }

    if (mb_strlen($name) > 100) {
        jsonError('ФИО слишком длинное (максимум 100 символов)', 400);
    }

    if ($email && !isValidEmail($email)) {
        jsonError('Неверный формат email', 400);
    }

    // Валидация telegram_id (должен быть числовым)
    if ($telegramId && !is_numeric($telegramId)) {
        jsonError('Telegram ID должен быть числом', 400);
    }

    // Создаем преподавателя
    try {
        $teacherId = dbExecute(
            "INSERT INTO teachers (name, phone, email, telegram_id, telegram_username, formula_id_group, formula_id_individual, notes, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [$name, $phone ?: null, $email ?: null, $telegramId ?: null, $telegram_username ?: null, $formulaIdGroup, $formulaIdIndividual, $notes ?: null]
        );

        if ($teacherId) {
            // Логируем создание
            logAudit('teacher_created', 'teacher', $teacherId, null, [
                'name' => $name,
                'phone' => $phone,
                'email' => $email
            ], 'Создан новый преподаватель');

            // Возвращаем созданного преподавателя
            $teacher = dbQueryOne("SELECT * FROM teachers WHERE id = ?", [$teacherId]);
            jsonSuccess($teacher);
        } else {
            jsonError('Не удалось создать преподавателя', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to create teacher: " . $e->getMessage());
        jsonError('Ошибка при создании преподавателя', 500);
    }
}

/**
 * Обновить данные преподавателя
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
        jsonError('Неверный ID преподавателя', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM teachers WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Преподаватель не найден', 404);
    }

    // Валидация
    $name = trim($data['name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');
    $telegramId = trim($data['telegram_id'] ?? '');
    $telegram_username = trim($data['telegram_username'] ?? '');
    $notes = trim($data['notes'] ?? '');

    // Формулы (могут быть NULL)
    $formulaIdGroup = null;
    if (isset($data['formula_id_group']) && $data['formula_id_group'] !== '') {
        $formulaIdGroup = filter_var($data['formula_id_group'], FILTER_VALIDATE_INT);
        if ($formulaIdGroup === false || $formulaIdGroup === 0) {
            $formulaIdGroup = null;
        }
    }

    $formulaIdIndividual = null;
    if (isset($data['formula_id_individual']) && $data['formula_id_individual'] !== '') {
        $formulaIdIndividual = filter_var($data['formula_id_individual'], FILTER_VALIDATE_INT);
        if ($formulaIdIndividual === false || $formulaIdIndividual === 0) {
            $formulaIdIndividual = null;
        }
    }

    if (empty($name)) {
        jsonError('ФИО преподавателя обязательно', 400);
    }

    if (mb_strlen($name) > 100) {
        jsonError('ФИО слишком длинное (максимум 100 символов)', 400);
    }

    if ($email && !isValidEmail($email)) {
        jsonError('Неверный формат email', 400);
    }

    // Валидация telegram_id (должен быть числовым)
    if ($telegramId && !is_numeric($telegramId)) {
        jsonError('Telegram ID должен быть числом', 400);
    }

    // Обновляем преподавателя
    try {
        $result = dbExecute(
            "UPDATE teachers
             SET name = ?, phone = ?, email = ?, telegram_id = ?, telegram_username = ?, formula_id_group = ?, formula_id_individual = ?, notes = ?, updated_at = NOW()
             WHERE id = ?",
            [$name, $phone ?: null, $email ?: null, $telegramId ?: null, $telegram_username ?: null, $formulaIdGroup, $formulaIdIndividual, $notes ?: null, $id]
        );

        if ($result !== false) {
            // Логируем изменение
            logAudit('teacher_updated', 'teacher', $id, $existing, [
                'name' => $name,
                'phone' => $phone,
                'email' => $email
            ], 'Обновлены данные преподавателя');

            // Возвращаем обновленного преподавателя
            $teacher = dbQueryOne("SELECT * FROM teachers WHERE id = ?", [$id]);
            jsonSuccess($teacher);
        } else {
            jsonError('Не удалось обновить преподавателя', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to update teacher: " . $e->getMessage());
        jsonError('Ошибка при обновлении преподавателя', 500);
    }
}

/**
 * Удалить преподавателя (на самом деле деактивировать)
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
        jsonError('Неверный ID преподавателя', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM teachers WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Преподаватель не найден', 404);
    }

    // Проверяем, есть ли связанные данные
    $lessonsCount = dbQueryOne(
        "SELECT COUNT(*) as count FROM lessons_instance WHERE teacher_id = ? OR substitute_teacher_id = ?",
        [$id, $id]
    );

    if ($lessonsCount['count'] > 0) {
        // Если есть уроки - деактивируем, а не удаляем
        $result = dbExecute(
            "UPDATE teachers SET active = 0, updated_at = NOW() WHERE id = ?",
            [$id]
        );

        if ($result !== false) {
            logAudit('teacher_deactivated', 'teacher', $id, $existing, ['active' => 0], 'Преподаватель деактивирован');
            jsonSuccess(['message' => 'Преподаватель деактивирован (есть связанные уроки)']);
        } else {
            jsonError('Не удалось деактивировать преподавателя', 500);
        }
    } else {
        // Если нет уроков - можно удалить
        $result = dbExecute("DELETE FROM teachers WHERE id = ?", [$id]);

        if ($result) {
            logAudit('teacher_deleted', 'teacher', $id, $existing, null, 'Преподаватель удалён');
            jsonSuccess(['message' => 'Преподаватель удалён']);
        } else {
            jsonError('Не удалось удалить преподавателя', 500);
        }
    }
}

/**
 * Переключить активность преподавателя
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
        jsonError('Неверный ID преподавателя', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM teachers WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Преподаватель не найден', 404);
    }

    // Переключаем активность
    $newActive = $existing['active'] ? 0 : 1;
    $result = dbExecute(
        "UPDATE teachers SET active = ?, updated_at = NOW() WHERE id = ?",
        [$newActive, $id]
    );

    if ($result !== false) {
        logAudit(
            $newActive ? 'teacher_activated' : 'teacher_deactivated',
            'teacher',
            $id,
            ['active' => $existing['active']],
            ['active' => $newActive],
            $newActive ? 'Преподаватель активирован' : 'Преподаватель деактивирован'
        );

        $teacher = dbQueryOne("SELECT * FROM teachers WHERE id = ?", [$id]);
        jsonSuccess($teacher);
    } else {
        jsonError('Не удалось изменить статус преподавателя', 500);
    }
}
