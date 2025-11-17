<?php
/**
 * Команда /week - Заработок за неделю
 */

function handleWeekCommand($chatId, $telegramId) {
    // Проверяем, зарегистрирован ли преподаватель
    $teacher = getTeacherByTelegramId($telegramId);

    if (!$teacher) {
        sendTelegramMessage($chatId,
            "❌ Ваш аккаунт не привязан.\n\n" .
            "Используйте /start для инструкций по регистрации."
        );
        return;
    }

    // Получаем начало текущей недели (понедельник)
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekEnd = date('Y-m-d', strtotime('sunday this week'));

    // Получаем выплаты за неделю
    $payments = dbQuery(
        "SELECT DATE(created_at) as payment_date, SUM(amount) as daily_total, COUNT(*) as lessons_count
         FROM payments
         WHERE teacher_id = ? AND DATE(created_at) BETWEEN ? AND ?
         GROUP BY DATE(created_at)
         ORDER BY payment_date ASC",
        [$teacher['id'], $weekStart, $weekEnd]
    );

    // Общая сумма за неделю
    $weekTotal = dbQueryOne(
        "SELECT SUM(amount) as total, COUNT(*) as count
         FROM payments
         WHERE teacher_id = ? AND DATE(created_at) BETWEEN ? AND ?",
        [$teacher['id'], $weekStart, $weekEnd]
    );

    // Формируем сообщение
    $message = "📅 <b>Заработок за неделю</b>\n\n";
    $message .= "👤 <b>Преподаватель:</b> {$teacher['name']}\n";
    $message .= "📆 <b>Период:</b> " . date('d.m', strtotime($weekStart)) . " - " . date('d.m.Y', strtotime($weekEnd)) . "\n\n";

    if (empty($payments)) {
        $message .= "Нет начисленных выплат за эту неделю.";
        sendTelegramMessage($chatId, $message);
        return;
    }

    $message .= "📊 <b>По дням:</b>\n\n";

    $days = [
        1 => 'Пн',
        2 => 'Вт',
        3 => 'Ср',
        4 => 'Чт',
        5 => 'Пт',
        6 => 'Сб',
        7 => 'Вс'
    ];

    foreach ($payments as $payment) {
        $dayOfWeek = date('N', strtotime($payment['payment_date']));
        $dayName = $days[$dayOfWeek];
        $date = date('d.m', strtotime($payment['payment_date']));
        $amount = number_format($payment['daily_total'], 0, ',', ' ');
        $count = $payment['lessons_count'];

        $message .= "• <b>{$dayName}</b> {$date}: {$amount} ₽ ({$count} " . plural($count, 'урок', 'урока', 'уроков') . ")\n";
    }

    $message .= "\n━━━━━━━━━━━━━━━━━━\n";
    $total = $weekTotal['total'] ?? 0;
    $count = $weekTotal['count'] ?? 0;
    $message .= "💵 <b>Итого:</b> <b>" . number_format($total, 0, ',', ' ') . " ₽</b>\n";
    $message .= "📚 <b>Уроков:</b> {$count}";

    sendTelegramMessage($chatId, $message);
}

function plural($n, $form1, $form2, $form3) {
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) return $form3;
    if ($n1 > 1 && $n1 < 5) return $form2;
    if ($n1 == 1) return $form1;
    return $form3;
}
