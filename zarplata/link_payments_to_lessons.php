<?php
/**
 * Связывание выплат с уроками по времени создания
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Связывание выплат с уроками</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #e0e0e0; padding: 20px; }
        h2 { color: #14b8a6; border-bottom: 2px solid #14b8a6; padding-bottom: 10px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #444; padding: 12px; text-align: left; }
        th { background: #2a2a2a; font-weight: bold; color: #14b8a6; }
        tr:nth-child(even) { background: #252525; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        .btn { padding: 12px 24px; background: #14b8a6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; margin: 20px 0; }
        .btn:hover { background: #0d9488; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
    </style>
</head>
<body>
    <h1>🔗 Связывание выплат с уроками</h1>

    <?php
    $action = $_GET['action'] ?? 'preview';

    if ($action === 'preview') {
        // Показываем предпросмотр связывания
        echo "<h2>Предпросмотр связывания</h2>";
        echo "<p>Найдем соответствия между выплатами и уроками по времени.</p>";

        // Получить выплаты без привязки
        $orphanedPayments = dbQuery(
            "SELECT
                p.id,
                p.teacher_id,
                t.name as teacher_name,
                p.amount,
                p.created_at,
                DATE(p.created_at) as payment_date,
                TIME(p.created_at) as payment_time
            FROM payments p
            LEFT JOIN teachers t ON p.teacher_id = t.id
            WHERE p.lesson_instance_id IS NULL
            ORDER BY p.created_at",
            []
        );

        echo "<table>";
        echo "<tr>
                <th>Payment ID</th>
                <th>Преподаватель</th>
                <th>Дата</th>
                <th>Время создания выплаты</th>
                <th>Сумма</th>
                <th>→</th>
                <th>Lesson ID</th>
                <th>Время урока</th>
                <th>Предмет</th>
                <th>Статус</th>
              </tr>";

        $matchCount = 0;
        $matches = [];

        foreach ($orphanedPayments as $payment) {
            // Найти урок для этой выплаты
            // Логика: берем урок, который начинается ПЕРЕД временем создания выплаты
            // и заканчивается ПОСЛЕ времени создания выплаты (или просто ближайший урок до этого времени)

            $paymentTime = $payment['payment_time'];

            // Ищем урок, в промежутке которого была создана выплата
            $lesson = dbQueryOne(
                "SELECT
                    li.id,
                    li.time_start,
                    li.time_end,
                    li.subject,
                    li.status,
                    li.lesson_type
                FROM lessons_instance li
                WHERE li.teacher_id = ?
                    AND li.lesson_date = ?
                    AND li.time_start <= ?
                ORDER BY li.time_start DESC
                LIMIT 1",
                [$payment['teacher_id'], $payment['payment_date'], $paymentTime]
            );

            if ($lesson) {
                $matches[] = [
                    'payment_id' => $payment['id'],
                    'lesson_id' => $lesson['id']
                ];
                $matchCount++;
                $rowClass = 'success';
            } else {
                $rowClass = 'error';
            }

            echo "<tr>";
            echo "<td>{$payment['id']}</td>";
            echo "<td>{$payment['teacher_name']}</td>";
            echo "<td>{$payment['payment_date']}</td>";
            echo "<td>{$payment['payment_time']}</td>";
            echo "<td>{$payment['amount']}₽</td>";
            echo "<td>→</td>";

            if ($lesson) {
                echo "<td class='$rowClass'>{$lesson['id']}</td>";
                echo "<td class='$rowClass'>{$lesson['time_start']} - {$lesson['time_end']}</td>";
                echo "<td class='$rowClass'>{$lesson['subject']}</td>";
                echo "<td class='$rowClass'>{$lesson['status']}</td>";
            } else {
                echo "<td class='$rowClass' colspan='4'>❌ Урок не найден</td>";
            }
            echo "</tr>";
        }
        echo "</table>";

        echo "<p><strong>Найдено совпадений:</strong> $matchCount из " . count($orphanedPayments) . "</p>";

        if ($matchCount > 0) {
            echo "<form method='POST' action='?action=execute' onsubmit='return confirm(\"Вы уверены? Это обновит $matchCount записей в базе данных.\");'>";
            echo "<input type='hidden' name='matches' value='" . e(json_encode($matches)) . "'>";
            echo "<button type='submit' class='btn'>✅ Выполнить связывание ($matchCount записей)</button>";
            echo "</form>";
        }

    } elseif ($action === 'execute') {
        // Выполняем связывание
        echo "<h2>Выполнение связывания</h2>";

        $matchesJson = $_POST['matches'] ?? '';
        $matches = json_decode($matchesJson, true);

        if (!is_array($matches) || empty($matches)) {
            echo "<p class='error'>❌ Нет данных для связывания</p>";
            exit;
        }

        $successCount = 0;
        $errorCount = 0;

        echo "<table>";
        echo "<tr><th>Payment ID</th><th>Lesson ID</th><th>Результат</th></tr>";

        foreach ($matches as $match) {
            try {
                $result = dbExecute(
                    "UPDATE payments SET lesson_instance_id = ? WHERE id = ?",
                    [$match['lesson_id'], $match['payment_id']]
                );

                echo "<tr>";
                echo "<td>{$match['payment_id']}</td>";
                echo "<td>{$match['lesson_id']}</td>";
                echo "<td class='success'>✅ Успешно</td>";
                echo "</tr>";
                $successCount++;
            } catch (Exception $e) {
                echo "<tr>";
                echo "<td>{$match['payment_id']}</td>";
                echo "<td>{$match['lesson_id']}</td>";
                echo "<td class='error'>❌ Ошибка: {$e->getMessage()}</td>";
                echo "</tr>";
                $errorCount++;
            }
        }

        echo "</table>";

        echo "<p class='success'><strong>✅ Успешно связано:</strong> $successCount</p>";
        if ($errorCount > 0) {
            echo "<p class='error'><strong>❌ Ошибок:</strong> $errorCount</p>";
        }

        echo "<p><a href='/zarplata/payments.php' class='btn'>Перейти на страницу Выплаты</a></p>";
    }
    ?>

</body>
</html>
