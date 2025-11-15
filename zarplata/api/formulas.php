<?php
/**
 * API для управления формулами оплаты
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
 * Получить список формул
 */
function handleList() {
    $formulas = dbQuery(
        "SELECT * FROM payment_formulas ORDER BY active DESC, name ASC",
        []
    );

    jsonSuccess($formulas);
}

/**
 * Получить одну формулу
 */
function handleGet() {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID формулы', 400);
    }

    $formula = dbQueryOne(
        "SELECT * FROM payment_formulas WHERE id = ?",
        [$id]
    );

    if (!$formula) {
        jsonError('Формула не найдена', 404);
    }

    jsonSuccess($formula);
}

/**
 * Добавить новую формулу
 */
function handleAdd() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $type = $data['type'] ?? '';

    if (empty($name)) {
        jsonError('Название формулы обязательно', 400);
    }

    if (!in_array($type, ['min_plus_per', 'fixed', 'expression'])) {
        jsonError('Неверный тип формулы', 400);
    }

    // Валидация по типу
    $minPayment = null;
    $perStudent = null;
    $threshold = null;
    $fixedAmount = null;
    $expression = null;

    if ($type === 'min_plus_per') {
        $minPayment = filter_var($data['min_payment'] ?? 0, FILTER_VALIDATE_FLOAT);
        $perStudent = filter_var($data['per_student'] ?? 0, FILTER_VALIDATE_FLOAT);
        $threshold = filter_var($data['threshold'] ?? 1, FILTER_VALIDATE_INT);

        if ($minPayment <= 0) {
            jsonError('Минимальная оплата должна быть больше 0', 400);
        }

        if ($perStudent < 0) {
            jsonError('Доплата не может быть отрицательной', 400);
        }

        if ($threshold < 1) {
            jsonError('Порог должен быть не меньше 1', 400);
        }
    } elseif ($type === 'fixed') {
        $fixedAmount = filter_var($data['fixed_amount'] ?? 0, FILTER_VALIDATE_FLOAT);

        if ($fixedAmount <= 0) {
            jsonError('Фиксированная сумма должна быть больше 0', 400);
        }
    } elseif ($type === 'expression') {
        $expression = trim($data['expression'] ?? '');

        if (empty($expression)) {
            jsonError('Выражение обязательно для пользовательской формулы', 400);
        }

        // Простая валидация выражения
        if (!validateExpression($expression)) {
            jsonError('Неверное выражение. Используйте переменные N, min, base и математические операторы', 400);
        }
    }

    // Создаём формулу
    try {
        $formulaId = dbExecute(
            "INSERT INTO payment_formulas
             (name, description, type, min_payment, per_student, threshold,
              fixed_amount, expression, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [$name, $description ?: null, $type, $minPayment, $perStudent, $threshold,
             $fixedAmount, $expression, ]
        );

        if ($formulaId) {
            logAudit('formula_created', 'formula', $formulaId, null, [
                'name' => $name,
                'type' => $type
            ], 'Создана новая формула оплаты');

            $formula = dbQueryOne("SELECT * FROM payment_formulas WHERE id = ?", [$formulaId]);
            jsonSuccess($formula);
        } else {
            jsonError('Не удалось создать формулу', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to create formula: " . $e->getMessage());
        jsonError('Ошибка при создании формулы', 500);
    }
}

/**
 * Обновить формулу
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
        jsonError('Неверный ID формулы', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM payment_formulas WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Формула не найдена', 404);
    }

    // Валидация
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $type = $data['type'] ?? '';

    if (empty($name)) {
        jsonError('Название формулы обязательно', 400);
    }

    if (!in_array($type, ['min_plus_per', 'fixed', 'expression'])) {
        jsonError('Неверный тип формулы', 400);
    }

    // Валидация по типу
    $minPayment = null;
    $perStudent = null;
    $threshold = null;
    $fixedAmount = null;
    $expression = null;

    if ($type === 'min_plus_per') {
        $minPayment = filter_var($data['min_payment'] ?? 0, FILTER_VALIDATE_FLOAT);
        $perStudent = filter_var($data['per_student'] ?? 0, FILTER_VALIDATE_FLOAT);
        $threshold = filter_var($data['threshold'] ?? 1, FILTER_VALIDATE_INT);

        if ($minPayment <= 0) {
            jsonError('Минимальная оплата должна быть больше 0', 400);
        }

        if ($perStudent < 0) {
            jsonError('Доплата не может быть отрицательной', 400);
        }

        if ($threshold < 1) {
            jsonError('Порог должен быть не меньше 1', 400);
        }
    } elseif ($type === 'fixed') {
        $fixedAmount = filter_var($data['fixed_amount'] ?? 0, FILTER_VALIDATE_FLOAT);

        if ($fixedAmount <= 0) {
            jsonError('Фиксированная сумма должна быть больше 0', 400);
        }
    } elseif ($type === 'expression') {
        $expression = trim($data['expression'] ?? '');

        if (empty($expression)) {
            jsonError('Выражение обязательно для пользовательской формулы', 400);
        }

        if (!validateExpression($expression)) {
            jsonError('Неверное выражение. Используйте переменные N, min, base и математические операторы', 400);
        }
    }

    // Обновляем формулу
    try {
        $result = dbExecute(
            "UPDATE payment_formulas
             SET name = ?, description = ?, type = ?,
                 min_payment = ?, per_student = ?, threshold = ?,
                 fixed_amount = ?, expression = ?, updated_at = NOW()
             WHERE id = ?",
            [$name, $description ?: null, $type, $minPayment, $perStudent, $threshold,
             $fixedAmount, $expression, $id]
        );

        if ($result !== false) {
            logAudit('formula_updated', 'formula', $id, $existing, [
                'name' => $name,
                'type' => $type
            ], 'Обновлена формула оплаты');

            $formula = dbQueryOne("SELECT * FROM payment_formulas WHERE id = ?", [$id]);
            jsonSuccess($formula);
        } else {
            jsonError('Не удалось обновить формулу', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to update formula: " . $e->getMessage());
        jsonError('Ошибка при обновлении формулы', 500);
    }
}

/**
 * Удалить формулу
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
        jsonError('Неверный ID формулы', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM payment_formulas WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Формула не найдена', 404);
    }

    // Проверяем, используется ли формула
    $usageCount = dbQueryOne(
        "SELECT COUNT(*) as count FROM lessons_instance WHERE formula_id = ?",
        [$id]
    );

    if ($usageCount['count'] > 0) {
        // Если используется - деактивируем
        $result = dbExecute(
            "UPDATE payment_formulas SET active = 0, updated_at = NOW() WHERE id = ?",
            [$id]
        );

        if ($result !== false) {
            logAudit('formula_deactivated', 'formula', $id, $existing, ['active' => 0], 'Формула деактивирована');
            jsonSuccess(['message' => 'Формула деактивирована (используется в уроках)']);
        } else {
            jsonError('Не удалось деактивировать формулу', 500);
        }
    } else {
        // Если не используется - удаляем
        $result = dbExecute("DELETE FROM payment_formulas WHERE id = ?", [$id]);

        if ($result) {
            logAudit('formula_deleted', 'formula', $id, $existing, null, 'Формула удалена');
            jsonSuccess(['message' => 'Формула удалена']);
        } else {
            jsonError('Не удалось удалить формулу', 500);
        }
    }
}

/**
 * Переключить активность формулы
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
        jsonError('Неверный ID формулы', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM payment_formulas WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Формула не найдена', 404);
    }

    // Переключаем активность
    $newActive = $existing['active'] ? 0 : 1;
    $result = dbExecute(
        "UPDATE payment_formulas SET active = ?, updated_at = NOW() WHERE id = ?",
        [$newActive, $id]
    );

    if ($result !== false) {
        logAudit(
            $newActive ? 'formula_activated' : 'formula_deactivated',
            'formula',
            $id,
            ['active' => $existing['active']],
            ['active' => $newActive],
            $newActive ? 'Формула активирована' : 'Формула деактивирована'
        );

        $formula = dbQueryOne("SELECT * FROM payment_formulas WHERE id = ?", [$id]);
        jsonSuccess($formula);
    } else {
        jsonError('Не удалось изменить статус формулы', 500);
    }
}

/**
 * Валидация математического выражения
 */
function validateExpression($expression) {
    // Проверяем на опасные функции
    $dangerous = ['exec', 'system', 'passthru', 'shell_exec', 'file', 'fopen', 'eval'];
    foreach ($dangerous as $func) {
        if (stripos($expression, $func) !== false) {
            return false;
        }
    }

    // Проверяем, что содержит только разрешённые символы
    if (!preg_match('/^[N\s\d\+\-\*\/\(\)\.,minbasxpowqrtlogflceabsMAX]+$/i', $expression)) {
        return false;
    }

    return true;
}
