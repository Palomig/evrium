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
if (!function_exists('logAudit')) {
    require_once __DIR__ . '/../../config/auth.php';
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

// ═══════════════════════════════════════════════════════════════════════════
// НОВЫЕ ОБРАБОТЧИКИ для формата из students.schedule
// lessonKey = "{teacherId}_{time}_{date}"
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Все ученики пришли (новый формат)
 */
function handleAttAllPresent($chatId, $messageId, $telegramId, $lessonKey, $callbackQueryId) {
    error_log("[Telegram Bot] handleAttAllPresent called for lessonKey: {$lessonKey}");

    try {
        // Парсим lessonKey: teacherId_time_date
        $parts = explode('_', $lessonKey);
        if (count($parts) < 3) {
            error_log("[Telegram Bot] Invalid lessonKey format: {$lessonKey}");
            answerCallbackQuery($callbackQueryId, "Ошибка: неверный формат данных", true);
            return;
        }

        $teacherId = (int)$parts[0];
        // Время приходит как "16-00", преобразуем обратно в "16:00"
        $time = str_replace('-', ':', $parts[1]);
        $date = $parts[2];
        $dayOfWeek = (int)date('N', strtotime($date));

        $teacher = getTeacherByTelegramId($telegramId);

        if (!$teacher) {
            error_log("[Telegram Bot] Teacher not found for telegram_id {$telegramId}");
            answerCallbackQuery($callbackQueryId, "Ошибка: преподаватель не найден", true);
            return;
        }

        // Проверяем, что это тот же преподаватель
        if ($teacher['id'] != $teacherId) {
            error_log("[Telegram Bot] Teacher mismatch: expected {$teacherId}, got {$teacher['id']}");
            answerCallbackQuery($callbackQueryId, "Ошибка: это не ваш урок", true);
            return;
        }

        // Получаем учеников для урока
        $studentsData = getStudentsForLesson($teacherId, $dayOfWeek, $time);
        $attendedCount = $studentsData['count'];

        if ($attendedCount == 0) {
            error_log("[Telegram Bot] No students found for lesson");
            answerCallbackQuery($callbackQueryId, "Ошибка: не найдены ученики для этого урока", true);
            return;
        }

        // Проверяем, не создана ли уже выплата
        $existingPayment = dbQueryOne(
            "SELECT id FROM payments
             WHERE teacher_id = ? AND DATE(created_at) = ?
               AND notes LIKE ?
             LIMIT 1",
            [$teacherId, $date, "%{$time}%"]
        );

        if ($existingPayment) {
            answerCallbackQuery($callbackQueryId, "⚠️ Выплата за этот урок уже создана", true);
            return;
        }

        // Определяем тип урока и формулу
        $lessonType = $attendedCount > 1 ? 'group' : 'individual';
        $formulaId = getFormulaIdForTeacher($teacher, $lessonType);

        if (!$formulaId) {
            answerCallbackQuery($callbackQueryId, "Ошибка: не настроена формула расчета", true);
            return;
        }

        $formula = dbQueryOne("SELECT * FROM payment_formulas WHERE id = ? AND active = 1", [$formulaId]);
        if (!$formula) {
            answerCallbackQuery($callbackQueryId, "Ошибка: формула не найдена", true);
            return;
        }

        // Рассчитываем выплату
        $paymentAmount = calculatePayment($formula, $attendedCount);

        // Создаём lessons_instance
        $timeEnd = date('H:i', strtotime($time) + 3600);
        $studentNames = array_column($studentsData['students'], 'name');
        $subject = $studentsData['subject'] ?? 'Математика';

        $lessonInstanceId = dbExecute(
            "INSERT INTO lessons_instance
             (teacher_id, lesson_date, time_start, time_end, lesson_type, subject,
              expected_students, actual_students, formula_id, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())",
            [
                $teacherId, $date, $time . ':00', $timeEnd . ':00',
                $lessonType, $subject, $attendedCount, $attendedCount, $formulaId,
                "Ученики: " . implode(', ', $studentNames)
            ]
        );

        // Создаём выплату
        $paymentId = dbExecute(
            "INSERT INTO payments
             (teacher_id, lesson_instance_id, amount, payment_type, status, calculation_method, notes, created_at)
             VALUES (?, ?, ?, 'lesson', 'pending', ?, ?, ?)",
            [
                $teacherId, $lessonInstanceId, $paymentAmount,
                "Все пришли ({$attendedCount} из {$attendedCount})",
                "Урок {$time}, {$subject}",
                $date . ' ' . $time . ':00'
            ]
        );

        // Логируем
        logAudit('attendance_marked', 'lesson_schedule', $lessonInstanceId, null, [
            'teacher_id' => $teacherId,
            'attended' => $attendedCount,
            'payment_id' => $paymentId,
            'amount' => $paymentAmount
        ], 'Посещаемость отмечена через Telegram');

        // Отвечаем
        $confirmationText =
            "✅ <b>Посещаемость отмечена!</b>\n\n" .
            "📚 <b>{$subject}</b> ({$time})\n" .
            "👥 Присутствовало: <b>{$attendedCount}</b> (все пришли)\n\n" .
            "💰 Начислено: <b>" . number_format($paymentAmount, 0, ',', ' ') . " ₽</b>";

        answerCallbackQuery($callbackQueryId, "✅ Начислено: " . number_format($paymentAmount, 0, ',', ' ') . " ₽", true);
        editTelegramMessage($chatId, $messageId, $confirmationText, ['inline_keyboard' => []]);

    } catch (Throwable $e) {
        error_log("[Telegram Bot] Error in handleAttAllPresent: " . $e->getMessage());
        error_log("[Telegram Bot] File: " . $e->getFile() . ":" . $e->getLine());
        error_log("[Telegram Bot] Trace: " . $e->getTraceAsString());
        // Показываем детали ошибки для отладки
        answerCallbackQuery($callbackQueryId, "Ошибка: " . substr($e->getMessage(), 0, 100), true);
    }
}

/**
 * Есть отсутствующие (новый формат)
 */
function handleAttSomeAbsent($chatId, $messageId, $telegramId, $lessonKey, $callbackQueryId) {
    error_log("[Telegram Bot] handleAttSomeAbsent called for lessonKey: {$lessonKey}");

    try {
        $parts = explode('_', $lessonKey);
        if (count($parts) < 3) {
            answerCallbackQuery($callbackQueryId, "Ошибка: неверный формат", true);
            return;
        }

        $teacherId = (int)$parts[0];
        // Время приходит как "16-00", преобразуем обратно в "16:00"
        $time = str_replace('-', ':', $parts[1]);
        $date = $parts[2];
        $dayOfWeek = (int)date('N', strtotime($date));

        $teacher = getTeacherByTelegramId($telegramId);
        if (!$teacher || $teacher['id'] != $teacherId) {
            answerCallbackQuery($callbackQueryId, "Ошибка: преподаватель не найден", true);
            return;
        }

        // Получаем учеников
        $studentsData = getStudentsForLesson($teacherId, $dayOfWeek, $time);
        $expectedStudents = $studentsData['count'];

        if ($expectedStudents == 0) {
            answerCallbackQuery($callbackQueryId, "Ошибка: нет учеников", true);
            return;
        }

        // Создаём клавиатуру с выбором количества
        $keyboard = [];
        $row = [];

        for ($i = 1; $i <= $expectedStudents; $i++) {
            $row[] = [
                'text' => (string)$i,
                'callback_data' => "att_count:{$lessonKey}:{$i}"
            ];
            if (count($row) == 5) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $keyboard[] = $row;
        }
        $keyboard[] = [[
            'text' => '0 (никто не пришел)',
            'callback_data' => "att_count:{$lessonKey}:0"
        ]];

        $subject = $studentsData['subject'] ?? 'Урок';

        editTelegramMessage($chatId, $messageId,
            "📊 <b>Посещаемость урока</b>\n\n" .
            "📚 {$subject} ({$time})\n" .
            "👥 Ожидалось: {$expectedStudents}\n\n" .
            "❓ Сколько учеников <b>ПРИШЛО</b>?",
            ['inline_keyboard' => $keyboard]
        );

        answerCallbackQuery($callbackQueryId);

    } catch (Throwable $e) {
        error_log("[Telegram Bot] Error in handleAttSomeAbsent: " . $e->getMessage());
        answerCallbackQuery($callbackQueryId, "Произошла ошибка", true);
    }
}

/**
 * Указано количество присутствующих (новый формат)
 */
function handleAttCount($chatId, $messageId, $telegramId, $lessonKey, $attendedCount, $callbackQueryId) {
    error_log("[Telegram Bot] handleAttCount: lessonKey={$lessonKey}, attended={$attendedCount}");

    try {
        $attendedCount = (int)$attendedCount;

        $parts = explode('_', $lessonKey);
        if (count($parts) < 3) {
            answerCallbackQuery($callbackQueryId, "Ошибка: неверный формат", true);
            return;
        }

        $teacherId = (int)$parts[0];
        // Время приходит как "16-00", преобразуем обратно в "16:00"
        $time = str_replace('-', ':', $parts[1]);
        $date = $parts[2];
        $dayOfWeek = (int)date('N', strtotime($date));

        $teacher = getTeacherByTelegramId($telegramId);
        if (!$teacher || $teacher['id'] != $teacherId) {
            answerCallbackQuery($callbackQueryId, "Ошибка: преподаватель не найден", true);
            return;
        }

        // Проверяем дубликат
        $existingPayment = dbQueryOne(
            "SELECT id FROM payments WHERE teacher_id = ? AND DATE(created_at) = ? AND notes LIKE ? LIMIT 1",
            [$teacherId, $date, "%{$time}%"]
        );

        if ($existingPayment) {
            answerCallbackQuery($callbackQueryId, "⚠️ Выплата уже создана", true);
            return;
        }

        // Получаем данные
        $studentsData = getStudentsForLesson($teacherId, $dayOfWeek, $time);
        $expectedStudents = $studentsData['count'];
        $subject = $studentsData['subject'] ?? 'Математика';

        // ⭐ ИСПРАВЛЕНИЕ: Если 0 учеников пришло - урок отменён, выплата не создаётся
        if ($attendedCount == 0) {
            // Создаём lessons_instance со статусом cancelled
            $timeEnd = date('H:i', strtotime($time) + 3600);
            $studentNames = array_column($studentsData['students'], 'name');

            $lessonInstanceId = dbExecute(
                "INSERT INTO lessons_instance
                 (teacher_id, lesson_date, time_start, time_end, lesson_type, subject,
                  expected_students, actual_students, formula_id, status, notes, created_at)
                 VALUES (?, ?, ?, ?, 'individual', ?, ?, 0, NULL, 'cancelled', ?, NOW())",
                [
                    $teacherId, $date, $time . ':00', $timeEnd . ':00',
                    $subject, $expectedStudents,
                    "Урок отменён - ученик не пришёл. Ожидались: " . implode(', ', $studentNames)
                ]
            );

            // Логируем
            logAudit('lesson_cancelled', 'lesson_schedule', $lessonInstanceId, null, [
                'teacher_id' => $teacherId,
                'expected' => $expectedStudents,
                'reason' => 'Ученик не пришёл'
            ], 'Урок отменён - 0 учеников');

            // Отвечаем
            $confirmationText =
                "❌ <b>Урок отменён</b>\n\n" .
                "📚 <b>{$subject}</b> ({$time})\n" .
                "👥 Никто не пришёл (ожидалось: {$expectedStudents})\n\n" .
                "💰 Выплата: <b>0 ₽</b> (урок не состоялся)";

            answerCallbackQuery($callbackQueryId, "❌ Урок отменён - 0₽", true);
            editTelegramMessage($chatId, $messageId, $confirmationText, ['inline_keyboard' => []]);
            return;
        }

        // Формула
        $lessonType = $attendedCount > 1 ? 'group' : 'individual';
        $formulaId = getFormulaIdForTeacher($teacher, $lessonType);

        if (!$formulaId) {
            answerCallbackQuery($callbackQueryId, "Ошибка: формула не настроена", true);
            return;
        }

        $formula = dbQueryOne("SELECT * FROM payment_formulas WHERE id = ? AND active = 1", [$formulaId]);
        if (!$formula) {
            answerCallbackQuery($callbackQueryId, "Ошибка: формула не найдена", true);
            return;
        }

        $paymentAmount = calculatePayment($formula, $attendedCount);

        // Создаём lessons_instance
        $timeEnd = date('H:i', strtotime($time) + 3600);
        $studentNames = array_column($studentsData['students'], 'name');

        $lessonInstanceId = dbExecute(
            "INSERT INTO lessons_instance
             (teacher_id, lesson_date, time_start, time_end, lesson_type, subject,
              expected_students, actual_students, formula_id, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())",
            [
                $teacherId, $date, $time . ':00', $timeEnd . ':00',
                $lessonType, $subject, $expectedStudents, $attendedCount, $formulaId,
                "Ученики: " . implode(', ', $studentNames)
            ]
        );

        // Создаём выплату
        $paymentId = dbExecute(
            "INSERT INTO payments
             (teacher_id, lesson_instance_id, amount, payment_type, status, calculation_method, notes, created_at)
             VALUES (?, ?, ?, 'lesson', 'pending', ?, ?, ?)",
            [
                $teacherId, $lessonInstanceId, $paymentAmount,
                "Пришло {$attendedCount} из {$expectedStudents}",
                "Урок {$time}, {$subject}",
                $date . ' ' . $time . ':00'
            ]
        );

        // Логируем
        logAudit('attendance_marked', 'lesson_schedule', $lessonInstanceId, null, [
            'teacher_id' => $teacherId,
            'attended' => $attendedCount,
            'expected' => $expectedStudents,
            'payment_id' => $paymentId,
            'amount' => $paymentAmount
        ], 'Посещаемость отмечена через Telegram');

        // Отвечаем
        $confirmationText =
            "✅ <b>Посещаемость отмечена!</b>\n\n" .
            "📚 <b>{$subject}</b> ({$time})\n" .
            "👥 Присутствовало: <b>{$attendedCount} из {$expectedStudents}</b>\n\n" .
            "💰 Начислено: <b>" . number_format($paymentAmount, 0, ',', ' ') . " ₽</b>";

        answerCallbackQuery($callbackQueryId, "✅ Начислено: " . number_format($paymentAmount, 0, ',', ' ') . " ₽", true);
        editTelegramMessage($chatId, $messageId, $confirmationText, ['inline_keyboard' => []]);

    } catch (Throwable $e) {
        error_log("[Telegram Bot] Error in handleAttCount: " . $e->getMessage());
        answerCallbackQuery($callbackQueryId, "Произошла ошибка", true);
    }
}
