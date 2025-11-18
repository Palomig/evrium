<?php
/**
 * Обработчик посещаемости уроков
 */

// Подключаем зависимости
if (!function_exists('getTeacherByTelegramId')) {
    require_once __DIR__ . '/../config.php';
}

/**
 * Все ученики пришли
 */
function handleAllPresent($chatId, $messageId, $telegramId, $lessonTemplateId, $callbackQueryId) {
    error_log("[Telegram Bot] handleAllPresent called for lesson {$lessonTemplateId}");

    $teacher = getTeacherByTelegramId($telegramId);

    if (!$teacher) {
        answerCallbackQuery($callbackQueryId, "Ошибка: преподаватель не найден", true);
        return;
    }

    // Получаем данные урока
    $lesson = dbQueryOne(
        "SELECT * FROM lessons_template WHERE id = ?",
        [$lessonTemplateId]
    );

    if (!$lesson) {
        answerCallbackQuery($callbackQueryId, "Ошибка: урок не найден", true);
        return;
    }

    // Все ученики пришли = expected_students
    $attendedCount = $lesson['expected_students'];

    // Рассчитываем зарплату
    $paymentAmount = calculatePayment($lesson, $teacher, $attendedCount);

    // Создаём запись о выплате
    $paymentId = dbExecute(
        "INSERT INTO payments
         (teacher_id, lesson_template_id, amount, payment_type, calculation_method, status, created_at)
         VALUES (?, ?, ?, 'lesson', ?, 'pending', NOW())",
        [
            $teacher['id'],
            $lessonTemplateId,
            $paymentAmount,
            "Все пришли ({$attendedCount} из {$lesson['expected_students']})"
        ]
    );

    // Логируем в audit_log
    logAudit(
        'attendance_marked',
        'lesson_template',
        $lessonTemplateId,
        null,
        [
            'teacher_id' => $teacher['id'],
            'attended' => $attendedCount,
            'expected' => $lesson['expected_students'],
            'payment_id' => $paymentId,
            'amount' => $paymentAmount
        ],
        'Посещаемость отмечена через Telegram бот'
    );

    // Обновляем сообщение
    $subject = $lesson['subject'] ? "{$lesson['subject']}" : "Урок";
    $time = date('H:i', strtotime($lesson['time_start']));

    editTelegramMessage($chatId, $messageId,
        "✅ <b>Посещаемость отмечена</b>\n\n" .
        "📚 {$subject} ({$time})\n" .
        "👥 Присутствовало: <b>{$attendedCount} из {$lesson['expected_students']}</b>\n\n" .
        "💰 Начислено: <b>" . number_format($paymentAmount, 0, ',', ' ') . " ₽</b>\n\n" .
        "✨ Выплата добавлена в систему со статусом \"Ожидает одобрения\""
    );

    answerCallbackQuery($callbackQueryId, "Посещаемость сохранена!");
}

/**
 * Некоторые ученики отсутствуют
 */
function handleSomeAbsent($chatId, $messageId, $telegramId, $lessonTemplateId, $callbackQueryId) {
    error_log("[Telegram Bot] handleSomeAbsent called for lesson {$lessonTemplateId}");

    $teacher = getTeacherByTelegramId($telegramId);

    if (!$teacher) {
        error_log("[Telegram Bot] Teacher not found for telegram_id {$telegramId}");
        answerCallbackQuery($callbackQueryId, "Ошибка: преподаватель не найден", true);
        return;
    }

    // Получаем данные урока
    $lesson = dbQueryOne(
        "SELECT * FROM lessons_template WHERE id = ?",
        [$lessonTemplateId]
    );

    if (!$lesson) {
        error_log("[Telegram Bot] Lesson not found: {$lessonTemplateId}");
        answerCallbackQuery($callbackQueryId, "Ошибка: урок не найден", true);
        return;
    }

    error_log("[Telegram Bot] Creating keyboard for {$lesson['expected_students']} students");

    // Создаём клавиатуру с выбором количества присутствующих (от 1 до N)
    $keyboard = [];
    $row = [];

    for ($i = 1; $i <= $lesson['expected_students']; $i++) {
        $row[] = [
            'text' => (string)$i,
            'callback_data' => "attendance_count:{$lessonTemplateId}:{$i}"
        ];

        // По 5 кнопок в ряду
        if (count($row) == 5) {
            $keyboard[] = $row;
            $row = [];
        }
    }

    // Добавляем кнопку "0" (никто не пришел) в отдельный ряд
    if (!empty($row)) {
        $keyboard[] = $row;
    }

    $keyboard[] = [
        [
            'text' => '0 (никто не пришел)',
            'callback_data' => "attendance_count:{$lessonTemplateId}:0"
        ]
    ];

    // Обновляем сообщение
    $subject = $lesson['subject'] ? "{$lesson['subject']}" : "Урок";
    $time = date('H:i', strtotime($lesson['time_start']));

    editTelegramMessage($chatId, $messageId,
        "📊 <b>Посещаемость урока</b>\n\n" .
        "📚 {$subject} ({$time})\n" .
        "👥 Ожидалось: {$lesson['expected_students']}\n\n" .
        "❓ Сколько учеников <b>ПРИШЛО</b> на урок?\n" .
        "Выберите число:",
        ['inline_keyboard' => $keyboard]
    );

    answerCallbackQuery($callbackQueryId);
}

/**
 * Указано количество присутствующих
 */
function handleAttendanceCount($chatId, $messageId, $telegramId, $lessonTemplateId, $attendedCount, $callbackQueryId) {
    $teacher = getTeacherByTelegramId($telegramId);

    if (!$teacher) {
        answerCallbackQuery($callbackQueryId, "Ошибка: преподаватель не найден", true);
        return;
    }

    // Получаем данные урока
    $lesson = dbQueryOne(
        "SELECT * FROM lessons_template WHERE id = ?",
        [$lessonTemplateId]
    );

    if (!$lesson) {
        answerCallbackQuery($callbackQueryId, "Ошибка: урок не найден", true);
        return;
    }

    // Рассчитываем зарплату
    $paymentAmount = calculatePayment($lesson, $teacher, $attendedCount);

    // Создаём запись о выплате
    $paymentId = dbExecute(
        "INSERT INTO payments
         (teacher_id, lesson_template_id, amount, payment_type, calculation_method, status, created_at)
         VALUES (?, ?, ?, 'lesson', ?, 'pending', NOW())",
        [
            $teacher['id'],
            $lessonTemplateId,
            $paymentAmount,
            "Пришло {$attendedCount} из {$lesson['expected_students']}"
        ]
    );

    // Логируем в audit_log
    logAudit(
        'attendance_marked',
        'lesson_template',
        $lessonTemplateId,
        null,
        [
            'teacher_id' => $teacher['id'],
            'attended' => $attendedCount,
            'expected' => $lesson['expected_students'],
            'payment_id' => $paymentId,
            'amount' => $paymentAmount
        ],
        'Посещаемость отмечена через Telegram бот'
    );

    // Обновляем сообщение
    $subject = $lesson['subject'] ? "{$lesson['subject']}" : "Урок";
    $time = date('H:i', strtotime($lesson['time_start']));

    editTelegramMessage($chatId, $messageId,
        "✅ <b>Посещаемость отмечена</b>\n\n" .
        "📚 {$subject} ({$time})\n" .
        "👥 Присутствовало: <b>{$attendedCount} из {$lesson['expected_students']}</b>\n\n" .
        "💰 Начислено: <b>" . number_format($paymentAmount, 0, ',', ' ') . " ₽</b>\n\n" .
        "✨ Выплата добавлена в систему со статусом \"Ожидает одобрения\""
    );

    answerCallbackQuery($callbackQueryId, "Посещаемость сохранена!");
}

/**
 * Рассчитать зарплату за урок
 */
function calculatePayment($lesson, $teacher, $attendedCount) {
    // Определяем какую формулу использовать
    $formulaId = $lesson['formula_id'] ?? $teacher['formula_id'] ?? null;

    if (!$formulaId) {
        // Нет формулы - возвращаем 0
        return 0;
    }

    // Получаем формулу
    $formula = dbQueryOne(
        "SELECT * FROM payment_formulas WHERE id = ? AND active = 1",
        [$formulaId]
    );

    if (!$formula) {
        return 0;
    }

    // Рассчитываем на основе типа формулы
    switch ($formula['type']) {
        case 'min_plus_per':
            // Базовая + (студентов сверх порога * доплата)
            $threshold = $formula['threshold'] ?? 2;
            $minPayment = $formula['min_payment'] ?? 0;
            $perStudent = $formula['per_student'] ?? 0;

            return $minPayment + (max(0, $attendedCount - $threshold) * $perStudent);

        case 'fixed':
            // Фиксированная сумма
            return $formula['fixed_amount'] ?? 0;

        case 'expression':
            // Базовая поддержка выражений
            // Пока используем fallback
            $minPayment = $formula['min_payment'] ?? 0;
            $perStudent = $formula['per_student'] ?? 0;

            return $minPayment + ($attendedCount * $perStudent);

        default:
            return 0;
    }
}
