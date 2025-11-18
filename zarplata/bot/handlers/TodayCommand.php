<?php
/**
 * Команда /today - Заработок за сегодня
 */

function handleTodayCommand($chatId, $telegramId) {
    // Проверяем, зарегистрирован ли преподаватель
    $teacher = getTeacherByTelegramId($telegramId);

    if (!$teacher) {
        sendTelegramMessage($chatId,
            "❌ Ваш аккаунт не привязан.\n\n" .
            "Используйте /start для инструкций по регистрации."
        );
        return;
    }

    $today = date('Y-m-d');

    // Получаем выплаты за сегодня
    $payments = dbQuery(
        "SELECT p.*, lt.subject, lt.time_start
         FROM payments p
         LEFT JOIN lessons_template lt ON p.lesson_template_id = lt.id
         WHERE p.teacher_id = ? AND DATE(p.created_at) = ?
         ORDER BY p.created_at DESC",
        [$teacher['id'], $today]
    );

    if (empty($payments)) {
        sendTelegramMessage($chatId,
            "📊 <b>Заработок за сегодня</b>\n\n" .
            "Сегодня пока нет начисленных выплат.\n\n" .
            "Выплаты начисляются автоматически после отметки посещаемости."
        );
        return;
    }

    // Подсчитываем общую сумму
    $total = array_sum(array_column($payments, 'amount'));

    // Формируем сообщение
    $message = "💰 <b>Заработок за сегодня</b>\n\n";
    $message .= "👤 <b>Преподаватель:</b> {$teacher['name']}\n";
    $message .= "📅 <b>Дата:</b> " . date('d.m.Y') . "\n\n";

    $message .= "📋 <b>Начисления:</b>\n\n";

    foreach ($payments as $payment) {
        $time = date('H:i', strtotime($payment['created_at']));
        $subject = $payment['subject'] ? "({$payment['subject']})" : '';
        $amount = number_format($payment['amount'], 0, ',', ' ');

        $message .= "• {$time} {$subject} - <b>{$amount} ₽</b>\n";

        if ($payment['calculation_method']) {
            $message .= "  <i>{$payment['calculation_method']}</i>\n";
        }
    }

    $message .= "\n━━━━━━━━━━━━━━━━━━\n";
    $message .= "💵 <b>Итого:</b> <b>" . number_format($total, 0, ',', ' ') . " ₽</b>";

    sendTelegramMessage($chatId, $message, getMainMenuKeyboard());
}
