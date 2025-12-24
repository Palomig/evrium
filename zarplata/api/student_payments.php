<?php
/**
 * API для управления оплатой от учеников
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
    case 'add':
        handleAdd();
        break;
    case 'delete':
        handleDelete();
        break;
    case 'stats':
        handleStats();
        break;
    case 'get_reminder':
        handleGetReminder();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Получить список учеников со статусом оплаты за месяц
 */
function handleList() {
    $month = $_GET['month'] ?? date('Y-m');

    // Валидация формата месяца
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        jsonError('Неверный формат месяца', 400);
    }

    // Получаем всех активных учеников с информацией об оплате
    $students = dbQuery(
        "SELECT
            s.id,
            s.name,
            s.class,
            s.lesson_type,
            s.price_group,
            s.price_individual,
            s.payment_type_group,
            s.payment_type_individual,
            s.parent_telegram,
            s.parent_whatsapp,
            s.student_telegram,
            s.student_whatsapp,
            s.schedule,
            s.notes,
            sp.id as payment_id,
            sp.amount as paid_amount,
            sp.payment_method,
            sp.lessons_count as paid_lessons,
            sp.paid_at,
            sp.notes as payment_notes
        FROM students s
        LEFT JOIN student_payments sp ON s.id = sp.student_id AND sp.month = ?
        WHERE s.active = 1
        ORDER BY s.name ASC",
        [$month]
    );

    // Считаем количество уроков за месяц для каждого ученика
    $result = [];
    foreach ($students as $student) {
        $lessonsCount = countLessonsInMonth($student, $month);
        $expectedAmount = calculateExpectedAmount($student, $lessonsCount);

        $result[] = [
            'id' => (int)$student['id'],
            'name' => $student['name'],
            'class' => $student['class'],
            'lesson_type' => $student['lesson_type'] ?? 'group',
            'price' => getStudentPrice($student),
            'payment_type' => getStudentPaymentType($student),
            'lessons_count' => $lessonsCount,
            'expected_amount' => $expectedAmount,
            'is_paid' => $student['payment_id'] !== null,
            'paid_amount' => $student['paid_amount'] ? (int)$student['paid_amount'] : null,
            'payment_method' => $student['payment_method'],
            'paid_lessons' => $student['paid_lessons'] ? (int)$student['paid_lessons'] : null,
            'paid_at' => $student['paid_at'],
            'payment_notes' => $student['payment_notes'],
            'parent_telegram' => $student['parent_telegram'],
            'parent_whatsapp' => $student['parent_whatsapp'],
            'student_telegram' => $student['student_telegram'],
            'student_whatsapp' => $student['student_whatsapp']
        ];
    }

    jsonSuccess($result);
}

/**
 * Добавить оплату
 */
function handleAdd() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Валидация
    $studentId = filter_var($data['student_id'] ?? 0, FILTER_VALIDATE_INT);
    $month = trim($data['month'] ?? '');
    $amount = filter_var($data['amount'] ?? 0, FILTER_VALIDATE_INT);
    $paymentMethod = $data['payment_method'] ?? 'card';
    $lessonsCount = filter_var($data['lessons_count'] ?? 0, FILTER_VALIDATE_INT);
    $notes = trim($data['notes'] ?? '');

    if (!$studentId) {
        jsonError('Выберите ученика', 400);
    }

    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        jsonError('Неверный формат месяца', 400);
    }

    if ($amount <= 0) {
        jsonError('Укажите сумму оплаты', 400);
    }

    if (!in_array($paymentMethod, ['cash', 'card'])) {
        jsonError('Неверный способ оплаты', 400);
    }

    // Проверяем существование ученика
    $student = dbQueryOne("SELECT id, name FROM students WHERE id = ?", [$studentId]);
    if (!$student) {
        jsonError('Ученик не найден', 404);
    }

    // Добавляем или обновляем оплату (UPSERT)
    try {
        // Проверяем, есть ли уже оплата за этот месяц
        $existing = dbQueryOne(
            "SELECT id FROM student_payments WHERE student_id = ? AND month = ?",
            [$studentId, $month]
        );

        if ($existing) {
            // Обновляем
            dbExecute(
                "UPDATE student_payments
                 SET amount = ?, payment_method = ?, lessons_count = ?, notes = ?, paid_at = NOW()
                 WHERE id = ?",
                [$amount, $paymentMethod, $lessonsCount, $notes ?: null, $existing['id']]
            );
            $paymentId = $existing['id'];
        } else {
            // Создаём
            $paymentId = dbExecute(
                "INSERT INTO student_payments
                 (student_id, month, amount, payment_method, lessons_count, notes, paid_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$studentId, $month, $amount, $paymentMethod, $lessonsCount, $notes ?: null]
            );
        }

        if ($paymentId) {
            logAudit('student_payment_added', 'student_payment', $paymentId, null, [
                'student_id' => $studentId,
                'student_name' => $student['name'],
                'month' => $month,
                'amount' => $amount,
                'payment_method' => $paymentMethod
            ], 'Добавлена оплата от ученика');

            jsonSuccess([
                'id' => $paymentId,
                'message' => 'Оплата сохранена'
            ]);
        } else {
            jsonError('Не удалось сохранить оплату', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to add student payment: " . $e->getMessage());
        jsonError('Ошибка при сохранении оплаты', 500);
    }
}

/**
 * Удалить оплату
 */
function handleDelete() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $studentId = filter_var($data['student_id'] ?? 0, FILTER_VALIDATE_INT);
    $month = trim($data['month'] ?? '');

    if (!$studentId || !$month) {
        jsonError('Неверные параметры', 400);
    }

    // Получаем информацию об оплате перед удалением
    $payment = dbQueryOne(
        "SELECT sp.*, s.name as student_name
         FROM student_payments sp
         JOIN students s ON sp.student_id = s.id
         WHERE sp.student_id = ? AND sp.month = ?",
        [$studentId, $month]
    );

    if (!$payment) {
        jsonError('Оплата не найдена', 404);
    }

    try {
        $result = dbExecute(
            "DELETE FROM student_payments WHERE student_id = ? AND month = ?",
            [$studentId, $month]
        );

        if ($result !== false) {
            logAudit('student_payment_deleted', 'student_payment', $payment['id'], [
                'student_name' => $payment['student_name'],
                'month' => $month,
                'amount' => $payment['amount']
            ], null, 'Оплата от ученика удалена');

            jsonSuccess(['message' => 'Оплата удалена']);
        } else {
            jsonError('Не удалось удалить оплату', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to delete student payment: " . $e->getMessage());
        jsonError('Ошибка при удалении оплаты', 500);
    }
}

/**
 * Получить статистику за месяц
 */
function handleStats() {
    $month = $_GET['month'] ?? date('Y-m');

    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        jsonError('Неверный формат месяца', 400);
    }

    // Считаем общую статистику
    $stats = dbQueryOne(
        "SELECT
            COUNT(DISTINCT s.id) as total_students,
            COUNT(DISTINCT sp.student_id) as paid_students,
            COALESCE(SUM(sp.amount), 0) as total_paid,
            SUM(CASE WHEN sp.payment_method = 'cash' THEN sp.amount ELSE 0 END) as paid_cash,
            SUM(CASE WHEN sp.payment_method = 'card' THEN sp.amount ELSE 0 END) as paid_card
        FROM students s
        LEFT JOIN student_payments sp ON s.id = sp.student_id AND sp.month = ?
        WHERE s.active = 1",
        [$month]
    );

    // Считаем ожидаемую сумму
    $students = dbQuery(
        "SELECT id, lesson_type, price_group, price_individual,
                payment_type_group, payment_type_individual, schedule
         FROM students WHERE active = 1",
        []
    );

    $expectedTotal = 0;
    foreach ($students as $student) {
        $lessonsCount = countLessonsInMonth($student, $month);
        $expectedTotal += calculateExpectedAmount($student, $lessonsCount);
    }

    jsonSuccess([
        'total_students' => (int)$stats['total_students'],
        'paid_students' => (int)$stats['paid_students'],
        'unpaid_students' => (int)$stats['total_students'] - (int)$stats['paid_students'],
        'total_paid' => (int)$stats['total_paid'],
        'paid_cash' => (int)$stats['paid_cash'],
        'paid_card' => (int)$stats['paid_card'],
        'expected_total' => $expectedTotal,
        'remaining' => $expectedTotal - (int)$stats['total_paid']
    ]);
}

/**
 * Получить текст напоминания для ученика
 */
function handleGetReminder() {
    $studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
    $month = $_GET['month'] ?? date('Y-m');

    if (!$studentId) {
        jsonError('Неверный ID ученика', 400);
    }

    // Получаем данные ученика
    $student = dbQueryOne(
        "SELECT s.*,
                sp.amount as paid_amount
         FROM students s
         LEFT JOIN student_payments sp ON s.id = sp.student_id AND sp.month = ?
         WHERE s.id = ?",
        [$month, $studentId]
    );

    if (!$student) {
        jsonError('Ученик не найден', 404);
    }

    // Получаем настройки
    $template = dbQueryOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'payment_reminder_template'"
    );
    $cardNumber = dbQueryOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'payment_card_number'"
    );

    $templateText = $template['setting_value'] ?? 'Здравствуйте! Напоминаем об оплате занятий за {month}.\n\nУченик: {student_name}\nСумма: {amount} ₽\n\nСпособ оплаты: перевод на карту {card_number}';
    $cardNumberValue = $cardNumber['setting_value'] ?? '';

    // Считаем сумму к оплате
    $lessonsCount = countLessonsInMonth($student, $month);
    $expectedAmount = calculateExpectedAmount($student, $lessonsCount);

    // Форматируем месяц
    $monthNames = [
        '01' => 'Январь', '02' => 'Февраль', '03' => 'Март',
        '04' => 'Апрель', '05' => 'Май', '06' => 'Июнь',
        '07' => 'Июль', '08' => 'Август', '09' => 'Сентябрь',
        '10' => 'Октябрь', '11' => 'Ноябрь', '12' => 'Декабрь'
    ];
    $monthParts = explode('-', $month);
    $monthFormatted = ($monthNames[$monthParts[1]] ?? $monthParts[1]) . ' ' . $monthParts[0];

    // Заменяем переменные
    $message = str_replace(
        ['{student_name}', '{month}', '{amount}', '{card_number}'],
        [$student['name'], $monthFormatted, number_format($expectedAmount, 0, '', ' '), $cardNumberValue],
        $templateText
    );

    jsonSuccess([
        'message' => $message,
        'student_name' => $student['name'],
        'amount' => $expectedAmount,
        'parent_telegram' => $student['parent_telegram'],
        'parent_whatsapp' => $student['parent_whatsapp']
    ]);
}

/**
 * Подсчитать количество уроков в месяце для ученика
 */
function countLessonsInMonth($student, $month) {
    // Парсим расписание
    $schedule = isset($student['schedule']) ? json_decode($student['schedule'], true) : null;

    if (!$schedule || !is_array($schedule)) {
        return 0;
    }

    // Считаем сколько раз каждый день недели встречается в месяце
    $monthParts = explode('-', $month);
    $year = (int)$monthParts[0];
    $monthNum = (int)$monthParts[1];
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $monthNum, $year);

    $dayOfWeekCounts = [];
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dayOfWeek = date('N', mktime(0, 0, 0, $monthNum, $day, $year));
        $dayOfWeekCounts[$dayOfWeek] = ($dayOfWeekCounts[$dayOfWeek] ?? 0) + 1;
    }

    // Считаем уроки
    $totalLessons = 0;
    foreach ($schedule as $dayOfWeek => $lessons) {
        if (!is_numeric($dayOfWeek)) continue;

        $lessonsOnDay = is_array($lessons) ? count($lessons) : 1;
        $daysCount = $dayOfWeekCounts[$dayOfWeek] ?? 0;
        $totalLessons += $lessonsOnDay * $daysCount;
    }

    return $totalLessons;
}

/**
 * Рассчитать ожидаемую сумму оплаты
 */
function calculateExpectedAmount($student, $lessonsCount) {
    $lessonType = $student['lesson_type'] ?? 'group';

    if ($lessonType === 'group') {
        $paymentType = $student['payment_type_group'] ?? 'monthly';
        $price = (int)($student['price_group'] ?? 5000);

        if ($paymentType === 'monthly') {
            return $price; // Фиксированная сумма за месяц
        } else {
            return $price * $lessonsCount; // Цена за урок * количество уроков
        }
    } else {
        $paymentType = $student['payment_type_individual'] ?? 'per_lesson';
        $price = (int)($student['price_individual'] ?? 1500);

        if ($paymentType === 'monthly') {
            return $price;
        } else {
            return $price * $lessonsCount;
        }
    }
}

/**
 * Получить цену ученика
 */
function getStudentPrice($student) {
    $lessonType = $student['lesson_type'] ?? 'group';

    if ($lessonType === 'group') {
        return (int)($student['price_group'] ?? 5000);
    } else {
        return (int)($student['price_individual'] ?? 1500);
    }
}

/**
 * Получить тип оплаты ученика
 */
function getStudentPaymentType($student) {
    $lessonType = $student['lesson_type'] ?? 'group';

    if ($lessonType === 'group') {
        return $student['payment_type_group'] ?? 'monthly';
    } else {
        return $student['payment_type_individual'] ?? 'per_lesson';
    }
}
