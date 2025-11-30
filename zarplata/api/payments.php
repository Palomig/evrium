<?php
/**
 * API для управления выплатами
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
    case 'approve':
        handleApprove();
        break;
    case 'mark_paid':
        handleMarkPaid();
        break;
    case 'cancel':
        handleCancel();
        break;
    case 'delete':
        handleDelete();
        break;
    case 'add_adjustment':
        handleAddAdjustment();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Получить список выплат
 */
function handleList() {
    // Фильтры
    $teacherId = filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT);
    $status = $_GET['status'] ?? null;
    $paymentType = $_GET['payment_type'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 100;
    $offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT) ?: 0;

    $query = "SELECT p.*, t.name as teacher_name,
              li.lesson_date, li.time_start, li.subject
              FROM payments p
              LEFT JOIN teachers t ON p.teacher_id = t.id
              LEFT JOIN lessons_instance li ON p.lesson_instance_id = li.id
              WHERE 1=1";

    $params = [];

    if ($teacherId) {
        $query .= " AND p.teacher_id = ?";
        $params[] = $teacherId;
    }

    if ($status) {
        $query .= " AND p.status = ?";
        $params[] = $status;
    }

    if ($paymentType) {
        $query .= " AND p.payment_type = ?";
        $params[] = $paymentType;
    }

    if ($dateFrom) {
        $query .= " AND p.payment_date >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $query .= " AND p.payment_date <= ?";
        $params[] = $dateTo;
    }

    $query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $payments = dbQuery($query, $params);

    jsonSuccess($payments);
}

/**
 * Получить одну выплату
 */
function handleGet() {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID выплаты', 400);
    }

    // Запрос с поддержкой как lesson_instance_id, так и lesson_template_id
    // Если выплата создана из instance - берём данные из li + lt
    // Если выплата создана напрямую из template (бот) - берём данные из lt_direct
    $payment = dbQueryOne(
        "SELECT p.*, t.name as teacher_name,
         -- Данные из lessons_instance (если есть)
         COALESCE(li.lesson_date, DATE(p.created_at)) as lesson_date,
         COALESCE(li.time_start, lt_direct.time_start) as time_start,
         COALESCE(li.time_end, lt_direct.time_end) as time_end,
         COALESCE(li.subject, lt_direct.subject) as subject,
         COALESCE(li.lesson_type, lt_direct.lesson_type) as lesson_type,
         COALESCE(li.expected_students, lt_direct.expected_students) as expected_students,
         li.actual_students,
         -- Данные из lessons_template
         COALESCE(lt.room, lt_direct.room) as room,
         COALESCE(lt.students, lt_direct.students) as students,
         COALESCE(lt.tier, lt_direct.tier) as tier,
         COALESCE(lt.grades, lt_direct.grades) as grades
         FROM payments p
         LEFT JOIN teachers t ON p.teacher_id = t.id
         -- JOIN через lesson_instance
         LEFT JOIN lessons_instance li ON p.lesson_instance_id = li.id
         LEFT JOIN lessons_template lt ON li.template_id = lt.id
         -- Прямой JOIN через lesson_template_id (для выплат из бота)
         LEFT JOIN lessons_template lt_direct ON p.lesson_template_id = lt_direct.id
         WHERE p.id = ?",
        [$id]
    );

    if (!$payment) {
        jsonError('Выплата не найдена', 404);
    }

    // Парсим calculation_method чтобы извлечь actual_students для выплат из бота
    // Формат: "Пришло X из Y" или "Все пришли (X из Y)"
    if (!$payment['actual_students'] && $payment['calculation_method']) {
        if (preg_match('/пришло (\d+) из (\d+)/iu', $payment['calculation_method'], $matches)) {
            $payment['actual_students'] = intval($matches[1]);
        } elseif (preg_match('/все пришли \((\d+) из (\d+)\)/iu', $payment['calculation_method'], $matches)) {
            $payment['actual_students'] = intval($matches[1]);
        }
    }

    jsonSuccess($payment);
}

/**
 * Добавить разовую выплату
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
    $amount = filter_var($data['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $paymentType = $data['payment_type'] ?? 'adjustment';
    $paymentDate = $data['payment_date'] ?? date('Y-m-d');
    $comment = trim($data['comment'] ?? '');

    if (!$teacherId) {
        jsonError('Выберите преподавателя', 400);
    }

    if ($amount == 0) {
        jsonError('Укажите сумму выплаты', 400);
    }

    if (!in_array($paymentType, ['bonus', 'penalty', 'adjustment'])) {
        jsonError('Неверный тип выплаты', 400);
    }

    // Проверяем существование преподавателя
    $teacher = dbQueryOne("SELECT id FROM teachers WHERE id = ? AND active = 1", [$teacherId]);
    if (!$teacher) {
        jsonError('Преподаватель не найден или неактивен', 404);
    }

    // Создаём выплату
    try {
        $paymentId = dbExecute(
            "INSERT INTO payments
             (teacher_id, amount, payment_type, period_start, status, notes)
             VALUES (?, ?, ?, ?, 'pending', ?)",
            [$teacherId, $amount, $paymentType, $paymentDate, $comment ?: null]
        );

        if ($paymentId) {
            logAudit('payment_created', 'payment', $paymentId, null, [
                'teacher_id' => $teacherId,
                'amount' => $amount,
                'type' => $paymentType
            ], 'Создана разовая выплата');

            $payment = dbQueryOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);
            jsonSuccess($payment);
        } else {
            jsonError('Не удалось создать выплату', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to create payment: " . $e->getMessage());
        jsonError('Ошибка при создании выплаты', 500);
    }
}

/**
 * Одобрить выплату
 */
function handleApprove() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id) {
        jsonError('Неверный ID выплаты', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM payments WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Выплата не найдена', 404);
    }

    if ($existing['status'] !== 'pending') {
        jsonError('Можно одобрить только ожидающие выплаты', 400);
    }

    // Одобряем выплату
    try {
        $result = dbExecute(
            "UPDATE payments
             SET status = 'approved', updated_at = NOW()
             WHERE id = ?",
            [$id]
        );

        if ($result !== false) {
            logAudit('payment_approved', 'payment', $id,
                ['status' => 'pending'],
                ['status' => 'approved'],
                'Выплата одобрена'
            );

            $payment = dbQueryOne("SELECT * FROM payments WHERE id = ?", [$id]);
            jsonSuccess($payment);
        } else {
            jsonError('Не удалось одобрить выплату', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to approve payment: " . $e->getMessage());
        jsonError('Ошибка при одобрении выплаты', 500);
    }
}

/**
 * Отметить как выплаченную
 */
function handleMarkPaid() {
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
    $paymentDate = $data['payment_date'] ?? date('Y-m-d');

    if (!$id) {
        jsonError('Неверный ID выплаты', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM payments WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Выплата не найдена', 404);
    }

    if ($existing['status'] !== 'approved') {
        jsonError('Можно отметить выплаченными только одобренные выплаты', 400);
    }

    // Отмечаем как выплаченную
    try {
        $result = dbExecute(
            "UPDATE payments
             SET status = 'paid', paid_at = ?, updated_at = NOW()
             WHERE id = ?",
            [$paymentDate, $id]
        );

        if ($result !== false) {
            logAudit('payment_paid', 'payment', $id,
                ['status' => 'approved'],
                ['status' => 'paid', 'paid_at' => $paymentDate],
                'Выплата отмечена как выплаченная'
            );

            $payment = dbQueryOne("SELECT * FROM payments WHERE id = ?", [$id]);
            jsonSuccess($payment);
        } else {
            jsonError('Не удалось отметить выплату', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to mark payment as paid: " . $e->getMessage());
        jsonError('Ошибка при отметке выплаты', 500);
    }
}

/**
 * Отменить выплату
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
        jsonError('Неверный ID выплаты', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM payments WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Выплата не найдена', 404);
    }

    if ($existing['status'] === 'paid') {
        jsonError('Нельзя отменить уже выплаченную сумму', 400);
    }

    // Отменяем выплату
    try {
        $result = dbExecute(
            "UPDATE payments
             SET status = 'cancelled', updated_at = NOW()
             WHERE id = ?",
            [$id]
        );

        if ($result !== false) {
            logAudit('payment_cancelled', 'payment', $id,
                ['status' => $existing['status']],
                ['status' => 'cancelled'],
                'Выплата отменена'
            );

            $payment = dbQueryOne("SELECT * FROM payments WHERE id = ?", [$id]);
            jsonSuccess($payment);
        } else {
            jsonError('Не удалось отменить выплату', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to cancel payment: " . $e->getMessage());
        jsonError('Ошибка при отмене выплаты', 500);
    }
}

/**
 * Удалить выплату
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
        jsonError('Неверный ID выплаты', 400);
    }

    // Проверяем существование
    $existing = dbQueryOne("SELECT * FROM payments WHERE id = ?", [$id]);
    if (!$existing) {
        jsonError('Выплата не найдена', 404);
    }

    // Нельзя удалить выплаченную сумму
    if ($existing['status'] === 'paid') {
        jsonError('Нельзя удалить выплаченную сумму. Отмените выплату.', 400);
    }

    // Удаляем выплату
    try {
        $result = dbExecute("DELETE FROM payments WHERE id = ?", [$id]);

        if ($result) {
            logAudit('payment_deleted', 'payment', $id, $existing, null, 'Выплата удалена');
            jsonSuccess(['message' => 'Выплата удалена']);
        } else {
            jsonError('Не удалось удалить выплату', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to delete payment: " . $e->getMessage());
        jsonError('Ошибка при удалении выплаты', 500);
    }
}

/**
 * Добавить ручную корректировку выплаты
 */
function handleAddAdjustment() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация
    $teacherId = filter_var($data['teacher_id'] ?? 0, FILTER_VALIDATE_INT);
    $amount = filter_var($data['amount'] ?? 0, FILTER_VALIDATE_INT);
    $paymentType = $data['payment_type'] ?? 'adjustment';
    $date = $data['date'] ?? date('Y-m-d');
    $notes = $data['notes'] ?? null;

    if (!$teacherId) {
        jsonError('Выберите преподавателя', 400);
    }

    if ($amount === 0 || $amount === false) {
        jsonError('Введите сумму', 400);
    }

    if (!in_array($paymentType, ['bonus', 'penalty', 'adjustment'])) {
        jsonError('Неверный тип корректировки', 400);
    }

    // Проверяем существование преподавателя
    $teacher = dbQueryOne("SELECT id, name FROM teachers WHERE id = ? AND active = 1", [$teacherId]);
    if (!$teacher) {
        jsonError('Преподаватель не найден', 404);
    }

    // Создаём выплату
    try {
        $result = dbExecute(
            "INSERT INTO payments
                (teacher_id, lesson_instance_id, amount, payment_type, status, notes, created_at)
             VALUES
                (?, NULL, ?, ?, 'pending', ?, NOW())",
            [$teacherId, $amount, $paymentType, $notes]
        );

        if ($result) {
            logAudit(
                'payment_adjustment_added',
                'payment',
                $result,
                null,
                [
                    'teacher_id' => $teacherId,
                    'amount' => $amount,
                    'payment_type' => $paymentType,
                    'date' => $date,
                    'notes' => $notes
                ],
                "Ручная корректировка: {$paymentType}, сумма: {$amount}₽"
            );

            jsonSuccess([
                'id' => $result,
                'message' => 'Корректировка успешно добавлена',
                'teacher_name' => $teacher['name'],
                'amount' => $amount
            ]);
        } else {
            jsonError('Не удалось создать корректировку', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to add payment adjustment: " . $e->getMessage());
        jsonError('Ошибка при создании корректировки: ' . $e->getMessage(), 500);
    }
}
