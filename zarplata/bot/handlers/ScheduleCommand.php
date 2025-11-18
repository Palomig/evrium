<?php
/**
 * Команда /schedule - Расписание на сегодня
 */

function handleScheduleCommand($chatId, $telegramId) {
    // Проверяем, зарегистрирован ли преподаватель
    $teacher = getTeacherByTelegramId($telegramId);

    if (!$teacher) {
        sendTelegramMessage($chatId,
            "❌ Ваш аккаунт не привязан.\n\n" .
            "Используйте /start для инструкций по регистрации."
        );
        return;
    }

    // Получаем день недели (1 = Понедельник, 7 = Воскресенье)
    $dayOfWeek = date('N');
    $today = date('d.m.Y');

    // Получаем уроки на сегодня
    $lessons = dbQuery(
        "SELECT * FROM lessons_template
         WHERE teacher_id = ? AND day_of_week = ? AND active = 1
         ORDER BY time_start ASC",
        [$teacher['id'], $dayOfWeek]
    );

    if (empty($lessons)) {
        sendTelegramMessage($chatId,
            "📅 <b>Расписание на сегодня</b>\n\n" .
            "👤 <b>Преподаватель:</b> {$teacher['name']}\n" .
            "📆 <b>Дата:</b> {$today}\n\n" .
            "Сегодня у вас нет уроков."
        );
        return;
    }

    // Формируем сообщение
    $message = "📅 <b>Расписание на сегодня</b>\n\n";
    $message .= "👤 <b>Преподаватель:</b> {$teacher['name']}\n";
    $message .= "📆 <b>Дата:</b> {$today}\n\n";

    $lessonTypes = [
        'group' => '👥 Групповое',
        'individual' => '👤 Индивидуальное'
    ];

    foreach ($lessons as $lesson) {
        $timeStart = date('H:i', strtotime($lesson['time_start']));
        $timeEnd = date('H:i', strtotime($lesson['time_end']));
        $subject = $lesson['subject'] ? "<b>{$lesson['subject']}</b>" : "<b>Урок</b>";
        $type = $lessonTypes[$lesson['lesson_type']] ?? $lesson['lesson_type'];
        $students = $lesson['expected_students'];
        $room = $lesson['room'] ?? '-';
        $tier = $lesson['tier'] ?? '';
        $grades = $lesson['grades'] ?? '';

        $message .= "🕐 <b>{$timeStart} - {$timeEnd}</b>\n";
        $message .= "  {$subject}";

        if ($tier) {
            $message .= " [Tier {$tier}]";
        }

        if ($grades) {
            $message .= " ({$grades} класс)";
        }

        $message .= "\n";
        $message .= "  {$type}, {$students} " . plural($students, 'ученик', 'ученика', 'учеников');

        if ($room) {
            $message .= ", Кабинет {$room}";
        }

        $message .= "\n\n";
    }

    $message .= "💡 <i>Через 15 минут после начала урока бот спросит о посещаемости</i>";

    $keyboard = function_exists('getMainMenuKeyboard') ? getMainMenuKeyboard() : null;
    sendTelegramMessage($chatId, $message, $keyboard);
}
