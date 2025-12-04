<?php
/**
 * Обработчик посещаемости уроков
 * Исправленная версия с защитой от ошибок
 */

// Подключаем зависимости
if (!function_exists('getTeacherByTelegramId')) {
    require_once __DIR__ . '/../config.php';
}
if (!function_exists('getStudentsForLesson')) {
    require_once __DIR__ . '/../../config/student_helpers.php';
}

/**
 * Получить ID формулы для преподавателя
 * С fallback на старое поле formula_id
 */
function getFormulaIdForTeacher($teacher, $lessonType) {
    // Новые поля (если есть)
    if ($lessonType === 'individual') {
        $formulaId = $teacher['formula_id_individual'] ?? null;
        if ($formulaId) {
            error_log("[Telegram Bot] Using formula_id_individual: {$formulaId}");
            return $formulaId;
        }
    } else {
        $formulaId = $teacher['formula_id_group'] ?? null;
        if ($formulaId) {
            error_log("[Telegram Bot] Using formula_id_group: {$formulaId}");
            return $formulaId;
        }
    }

    // Fallback на старое поле formula_id
    $formulaId = $teacher['formula_id'] ?? null;
    if ($formulaId) {
        error_log("[Telegram Bot] Using legacy formula_id: {$formulaId}");
        return $formulaId;
    }

    error_log("[Telegram Bot] No formula_id found for teacher {$teacher['id']}");
    return null;
}

/**
 * Все ученики пришли
 */
function handleAllPresent($chatId, $messageId, $telegramId, $lessonTemplateId, $callbackQueryId) {
    error_log("[Telegram Bot] handleAllPresent called for lesson {$lessonTemplateId}");

    try {
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

        // ⭐ ДИНАМИЧЕСКИЙ РАСЧЁТ: Получаем реальное количество учеников
        $studentsData = getStudentsForLesson(
            $lesson['teacher_id'],
            $lesson['day_of_week'],
            substr($lesson['time_start'], 0, 5)
        );
        $dynamicStudentCount = $studentsData['count'];

        // Используем динамический расчёт, если он > 0, иначе fallback на expected_students
        $attendedCount = $dynamicStudentCount > 0 ? $dynamicStudentCount : (int)$lesson['expected_students'];
        error_log("[Telegram Bot] handleAllPresent: dynamic={$dynamicStudentCount}, expected={$lesson['expected_students']}, using={$attendedCount}");

        $lessonType = $lesson['lesson_type'] ?? 'group';

        // Получаем ID формулы (с fallback)
        $formulaId = getFormulaIdForTeacher($teacher, $lessonType);

        if (!$formulaId) {
            error_log("[Telegram Bot] No formula configured for teacher {$teacher['id']}");
            answerCallbackQuery($callbackQueryId, "Ошибка: не настроена формула расчета. Обратитесь к администратору.", true);
            return;
        }

        // Получаем формулу
        $formula = dbQueryOne(
            "SELECT * FROM payment_formulas WHERE id = ? AND active = 1",
            [$formulaId]
        );

        if (!$formula) {
            error_log("[Telegram Bot] Formula {$formulaId} not found or inactive");
            answerCallbackQuery($callbackQueryId, "Ошибка: формула расчета не найдена или неактивна", true);
            return;
        }

        error_log("[Telegram Bot] Using formula '{$formula['name']}' (type: {$formula['type']})");

        // Проверяем, не создана ли уже выплата за этот урок сегодня
        $today = date('Y-m-d');
        $existingPayment = dbQueryOne(
            "SELECT id FROM payments
             WHERE teacher_id = ? AND lesson_template_id = ? AND DATE(created_at) = ?
             ORDER BY created_at DESC LIMIT 1",
            [$teacher['id'], $lessonTemplateId, $today]
        );

        if ($existingPayment) {
            error_log("[Telegram Bot] Payment already exists for lesson {$lessonTemplateId} today");
            answerCallbackQuery($callbackQueryId, "⚠️ Выплата за этот урок уже создана сегодня", true);
            return;
        }

        // Рассчитываем зарплату
        $paymentAmount = calculatePayment($formula, $attendedCount);
        error_log("[Telegram Bot] Calculated payment: {$paymentAmount} RUB for {$attendedCount} students");

        // Сохраняем ожидаемое количество для отображения
        $expectedForDisplay = $attendedCount; // Все пришли = ожидаемое = пришедшие

        // Создаём запись о выплате
        $paymentId = dbExecute(
            "INSERT INTO payments
             (teacher_id, lesson_template_id, amount, payment_type, calculation_method, status, created_at)
             VALUES (?, ?, ?, 'lesson', ?, 'pending', NOW())",
            [
                $teacher['id'],
                $lessonTemplateId,
                $paymentAmount,
                "Все пришли ({$attendedCount} из {$expectedForDisplay})"
            ]
        );

        // Логируем в audit_log (с защитой от ошибок)
        try {
            if (function_exists('logAudit')) {
                logAudit(
                    'attendance_marked',
                    'lesson_template',
                    $lessonTemplateId,
                    null,
                    [
                        'teacher_id' => $teacher['id'],
                        'attended' => $attendedCount,
                        'expected' => $expectedForDisplay,
                        'payment_id' => $paymentId,
                        'amount' => $paymentAmount
                    ],
                    'Посещаемость отмечена через Telegram бот'
                );
            }
        } catch (Throwable $e) {
            error_log("[Telegram Bot] logAudit failed: " . $e->getMessage());
        }

        // Формируем текст подтверждения
        $subject = $lesson['subject'] ? "{$lesson['subject']}" : "Урок";
        $time = date('H:i', strtotime($lesson['time_start']));

        $confirmationText =
            "✅ <b>Посещаемость отмечена!</b>\n\n" .
            "📚 <b>{$subject}</b> ({$time})\n" .
            "👥 Присутствовало: <b>{$attendedCount} из {$expectedForDisplay}</b> (все пришли)\n\n" .
            "💰 Начислено: <b>" . number_format($paymentAmount, 0, ',', ' ') . " ₽</b>\n\n" .
            "✨ Выплата добавлена в систему";

        // Отвечаем на callback query
        $alertText = "✅ Посещаемость отмечена!\n💰 Начислено: " . number_format($paymentAmount, 0, ',', ' ') . " ₽";
        answerCallbackQuery($callbackQueryId, $alertText, true);

        // Обновляем сообщение (убираем кнопки)
        $editResult = editTelegramMessage($chatId, $messageId, $confirmationText, ['inline_keyboard' => []]);

        if (!$editResult || !isset($editResult['ok']) || !$editResult['ok']) {
            error_log("[Telegram Bot] editTelegramMessage failed, sending new message");
            sendTelegramMessage($chatId, $confirmationText);
        }

    } catch (Throwable $e) {
        error_log("[Telegram Bot] Error in handleAllPresent: " . $e->getMessage());
        error_log("[Telegram Bot] Trace: " . $e->getTraceAsString());
        answerCallbackQuery($callbackQueryId, "Произошла ошибка. Попробуйте позже.", true);
    }
}

/**
 * Некоторые ученики отсутствуют
 */
function handleSomeAbsent($chatId, $messageId, $telegramId, $lessonTemplateId, $callbackQueryId) {
    error_log("[Telegram Bot] handleSomeAbsent called for lesson {$lessonTemplateId}");

    try {
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

        // ⭐ ДИНАМИЧЕСКИЙ РАСЧЁТ: Получаем реальное количество учеников
        $studentsData = getStudentsForLesson(
            $lesson['teacher_id'],
            $lesson['day_of_week'],
            substr($lesson['time_start'], 0, 5)
        );
        $dynamicStudentCount = $studentsData['count'];

        // Используем динамический расчёт, если он > 0, иначе fallback на expected_students
        $expectedStudents = $dynamicStudentCount > 0 ? $dynamicStudentCount : (int)$lesson['expected_students'];
        error_log("[Telegram Bot] handleSomeAbsent: dynamic={$dynamicStudentCount}, expected={$lesson['expected_students']}, using={$expectedStudents}");
        error_log("[Telegram Bot] Creating keyboard for {$expectedStudents} students");

        // Создаём клавиатуру с выбором количества присутствующих
        $keyboard = [];
        $row = [];

        for ($i = 1; $i <= $expectedStudents; $i++) {
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

        // Добавляем оставшиеся кнопки
        if (!empty($row)) {
            $keyboard[] = $row;
        }

        // Кнопка "0" в отдельном ряду
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
            "👥 Ожидалось: {$expectedStudents}\n\n" .
            "❓ Сколько учеников <b>ПРИШЛО</b> на урок?\n" .
            "Выберите число:",
            ['inline_keyboard' => $keyboard]
        );

        answerCallbackQuery($callbackQueryId);

    } catch (Throwable $e) {
        error_log("[Telegram Bot] Error in handleSomeAbsent: " . $e->getMessage());
        error_log("[Telegram Bot] Trace: " . $e->getTraceAsString());
        answerCallbackQuery($callbackQueryId, "Произошла ошибка. Попробуйте позже.", true);
    }
}

/**
 * Указано количество присутствующих
 */
function handleAttendanceCount($chatId, $messageId, $telegramId, $lessonTemplateId, $attendedCount, $callbackQueryId) {
    error_log("[Telegram Bot] handleAttendanceCount called for lesson {$lessonTemplateId}, attended: {$attendedCount}");

    try {
        $attendedCount = (int)$attendedCount;

        $teacher = getTeacherByTelegramId($telegramId);

        if (!$teacher) {
            error_log("[Telegram Bot] Teacher not found for telegram_id {$telegramId}");
            answerCallbackQuery($callbackQueryId, "Ошибка: преподаватель не найден", true);
            return;
        }

        error_log("[Telegram Bot] Teacher found: {$teacher['name']} (ID: {$teacher['id']})");

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

        // ⭐ ДИНАМИЧЕСКИЙ РАСЧЁТ: Получаем реальное количество учеников
        $studentsData = getStudentsForLesson(
            $lesson['teacher_id'],
            $lesson['day_of_week'],
            substr($lesson['time_start'], 0, 5)
        );
        $dynamicStudentCount = $studentsData['count'];

        // Используем динамический расчёт, если он > 0, иначе fallback на expected_students
        $expectedStudents = $dynamicStudentCount > 0 ? $dynamicStudentCount : (int)$lesson['expected_students'];
        error_log("[Telegram Bot] handleAttendanceCount: dynamic={$dynamicStudentCount}, template_expected={$lesson['expected_students']}, using={$expectedStudents}");

        $lessonType = $lesson['lesson_type'] ?? 'group';

        // Получаем ID формулы (с fallback)
        $formulaId = getFormulaIdForTeacher($teacher, $lessonType);

        if (!$formulaId) {
            error_log("[Telegram Bot] No formula configured for teacher {$teacher['id']}");
            answerCallbackQuery($callbackQueryId, "Ошибка: не настроена формула расчета. Обратитесь к администратору.", true);
            return;
        }

        // Получаем формулу
        $formula = dbQueryOne(
            "SELECT * FROM payment_formulas WHERE id = ? AND active = 1",
            [$formulaId]
        );

        if (!$formula) {
            error_log("[Telegram Bot] Formula {$formulaId} not found or inactive");
            answerCallbackQuery($callbackQueryId, "Ошибка: формула расчета не найдена", true);
            return;
        }

        error_log("[Telegram Bot] Using formula '{$formula['name']}' (type: {$formula['type']})");

        // Проверяем, не создана ли уже выплата за этот урок сегодня
        $today = date('Y-m-d');
        $existingPayment = dbQueryOne(
            "SELECT id FROM payments
             WHERE teacher_id = ? AND lesson_template_id = ? AND DATE(created_at) = ?
             ORDER BY created_at DESC LIMIT 1",
            [$teacher['id'], $lessonTemplateId, $today]
        );

        if ($existingPayment) {
            error_log("[Telegram Bot] Payment already exists for lesson {$lessonTemplateId} today");
            answerCallbackQuery($callbackQueryId, "⚠️ Выплата за этот урок уже создана сегодня", true);
            return;
        }

        // Рассчитываем зарплату
        $paymentAmount = calculatePayment($formula, $attendedCount);
        error_log("[Telegram Bot] Calculated payment: {$paymentAmount} RUB for {$attendedCount} students");

        // Создаём запись о выплате
        $paymentId = dbExecute(
            "INSERT INTO payments
             (teacher_id, lesson_template_id, amount, payment_type, calculation_method, status, created_at)
             VALUES (?, ?, ?, 'lesson', ?, 'pending', NOW())",
            [
                $teacher['id'],
                $lessonTemplateId,
                $paymentAmount,
                "Пришло {$attendedCount} из {$expectedStudents}"
            ]
        );

        // Логируем в audit_log (с защитой от ошибок)
        try {
            if (function_exists('logAudit')) {
                logAudit(
                    'attendance_marked',
                    'lesson_template',
                    $lessonTemplateId,
                    null,
                    [
                        'teacher_id' => $teacher['id'],
                        'attended' => $attendedCount,
                        'expected' => $expectedStudents,
                        'payment_id' => $paymentId,
                        'amount' => $paymentAmount
                    ],
                    'Посещаемость отмечена через Telegram бот'
                );
            }
        } catch (Throwable $e) {
            error_log("[Telegram Bot] logAudit failed: " . $e->getMessage());
        }

        // Формируем текст подтверждения
        $subject = $lesson['subject'] ? "{$lesson['subject']}" : "Урок";
        $time = date('H:i', strtotime($lesson['time_start']));

        $confirmationText =
            "✅ <b>Посещаемость отмечена!</b>\n\n" .
            "📚 <b>{$subject}</b> ({$time})\n" .
            "👥 Присутствовало: <b>{$attendedCount} из {$expectedStudents}</b>\n\n" .
            "💰 Начислено: <b>" . number_format($paymentAmount, 0, ',', ' ') . " ₽</b>\n\n" .
            "✨ Выплата добавлена в систему";

        // Отвечаем на callback query
        $alertText = "✅ Посещаемость отмечена!\n👥 Пришло: {$attendedCount}\n💰 Начислено: " . number_format($paymentAmount, 0, ',', ' ') . " ₽";
        answerCallbackQuery($callbackQueryId, $alertText, true);

        // Обновляем сообщение (убираем кнопки)
        $editResult = editTelegramMessage($chatId, $messageId, $confirmationText, ['inline_keyboard' => []]);

        if (!$editResult || !isset($editResult['ok']) || !$editResult['ok']) {
            error_log("[Telegram Bot] editTelegramMessage failed, sending new message");
            sendTelegramMessage($chatId, $confirmationText);
        }

    } catch (Throwable $e) {
        error_log("[Telegram Bot] Error in handleAttendanceCount: " . $e->getMessage());
        error_log("[Telegram Bot] Trace: " . $e->getTraceAsString());
        answerCallbackQuery($callbackQueryId, "Произошла ошибка. Попробуйте позже.", true);
    }
}
