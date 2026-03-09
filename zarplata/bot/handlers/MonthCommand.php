<?php
/**
 * Команда /month - Заработок за текущий месяц
 */

function handleMonthCommand($chatId, $telegramId) {
    $teacher = getTeacherByTelegramId($telegramId);

    if (!$teacher) {
        sendTelegramMessage($chatId,
            "❌ Ваш аккаунт не привязан.\n\n" .
            "Используйте /start для инструкций по регистрации."
        );
        return;
    }

    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-d');

    $payments = dbQuery(
        "SELECT DATE(created_at) as payment_date, SUM(amount) as daily_total, COUNT(*) as lessons_count
         FROM payments
         WHERE teacher_id = ? AND DATE(created_at) BETWEEN ? AND ?
         GROUP BY DATE(created_at)
         ORDER BY payment_date ASC",
        [$teacher['id'], $monthStart, $monthEnd]
    );

    $monthTotal = dbQueryOne(
        "SELECT SUM(amount) as total, COUNT(*) as count
         FROM payments
         WHERE teacher_id = ? AND DATE(created_at) BETWEEN ? AND ?",
        [$teacher['id'], $monthStart, $monthEnd]
    );

    $message = "📆 <b>Заработок за месяц</b>\n\n";
    $message .= "👤 <b>Преподаватель:</b> {$teacher['name']}\n";
    $message .= "📅 <b>Период:</b> " . date('d.m', strtotime($monthStart)) . " - " . date('d.m.Y', strtotime($monthEnd)) . "\n\n";

    if (empty($payments)) {
        $message .= "Нет начисленных выплат за текущий месяц.";
        $keyboard = function_exists('getMainMenuKeyboard') ? getMainMenuKeyboard() : null;
        sendTelegramMessage($chatId, $message, $keyboard);
        return;
    }

    $days = [
        1 => 'Пн',
        2 => 'Вт',
        3 => 'Ср',
        4 => 'Чт',
        5 => 'Пт',
        6 => 'Сб',
        7 => 'Вс'
    ];

    $weeks = [];

    foreach ($payments as $payment) {
        $paymentTimestamp = strtotime($payment['payment_date']);
        $weekStartTimestamp = strtotime('monday this week', $paymentTimestamp);
        $weekEndTimestamp = strtotime('sunday this week', $paymentTimestamp);
        $weekKey = date('Y-m-d', $weekStartTimestamp);

        if (!isset($weeks[$weekKey])) {
            $weeks[$weekKey] = [
                'start' => max($weekStartTimestamp, strtotime($monthStart)),
                'end' => min($weekEndTimestamp, strtotime($monthEnd)),
                'days' => [],
                'total' => 0,
                'count' => 0
            ];
        }

        $weeks[$weekKey]['days'][] = $payment;
        $weeks[$weekKey]['total'] += (float) $payment['daily_total'];
        $weeks[$weekKey]['count'] += (int) $payment['lessons_count'];
    }

    $message .= "📊 <b>По неделям:</b>\n\n";

    $weekNumber = 1;
    foreach ($weeks as $week) {
        $weekStartLabel = date('d.m', $week['start']);
        $weekEndLabel = date('d.m', $week['end']);
        $message .= "📆 <b>Неделя {$weekNumber}</b> ({$weekStartLabel} - {$weekEndLabel})\n";

        foreach ($week['days'] as $payment) {
        $dayOfWeek = date('N', strtotime($payment['payment_date']));
        $dayName = $days[$dayOfWeek];
        $date = date('d.m', strtotime($payment['payment_date']));
        $amount = number_format($payment['daily_total'], 0, ',', ' ');
        $count = $payment['lessons_count'];

        $message .= "• <b>{$dayName}</b> {$date}: {$amount} ₽ ({$count} " . plural($count, 'урок', 'урока', 'уроков') . ")\n";
        }

        $message .= "💵 <b>Итого за неделю:</b> <b>" . number_format($week['total'], 0, ',', ' ') . " ₽</b>\n";
        $message .= "📚 <b>Уроков за неделю:</b> {$week['count']}\n\n";
        $weekNumber++;
    }

    $total = $monthTotal['total'] ?? 0;
    $count = $monthTotal['count'] ?? 0;

    $message .= "\n━━━━━━━━━━━━━━━━━━\n";
    $message .= "💵 <b>Итого:</b> <b>" . number_format($total, 0, ',', ' ') . " ₽</b>\n";
    $message .= "📚 <b>Уроков:</b> {$count}";

    $keyboard = function_exists('getMainMenuKeyboard') ? getMainMenuKeyboard() : null;
    sendTelegramMessage($chatId, $message, $keyboard);
}
