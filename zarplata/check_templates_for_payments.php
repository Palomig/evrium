<?php
/**
 * Диагностика: найти шаблоны расписания для выплат без привязки к урокам
 */
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Диагностика шаблонов для выплат</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #e0e0e0; padding: 20px; }
        h2 { color: #14b8a6; border-bottom: 2px solid #14b8a6; padding-bottom: 10px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #444; padding: 12px; text-align: left; }
        th { background: #2a2a2a; font-weight: bold; color: #14b8a6; }
        tr:nth-child(even) { background: #252525; }
        .warning { color: #f59e0b; font-weight: bold; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🔍 Диагностика выплат и шаблонов расписания</h1>

    <?php
    // 1. Найти выплаты без привязки к урокам
    echo "<h2>1. Выплаты без lesson_instance_id</h2>";
    $orphanedPayments = dbQuery(
        "SELECT
            p.id,
            p.teacher_id,
            t.name as teacher_name,
            p.amount,
            p.created_at,
            p.payment_type,
            DATE(p.created_at) as payment_date,
            DAYOFWEEK(p.created_at) as day_of_week_num,
            TIME(p.created_at) as payment_time
        FROM payments p
        LEFT JOIN teachers t ON p.teacher_id = t.id
        WHERE p.lesson_instance_id IS NULL
        ORDER BY p.created_at DESC",
        []
    );

    echo "<p>Найдено выплат без привязки к урокам: <strong>" . count($orphanedPayments) . "</strong></p>";

    if (count($orphanedPayments) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Преподаватель</th><th>Сумма</th><th>Дата создания</th><th>Тип</th><th>День недели</th></tr>";
        foreach ($orphanedPayments as $payment) {
            $dayNames = ['', 'Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
            $dayName = $dayNames[$payment['day_of_week_num']] ?? '?';

            echo "<tr>";
            echo "<td>{$payment['id']}</td>";
            echo "<td>{$payment['teacher_name']} (ID: {$payment['teacher_id']})</td>";
            echo "<td>{$payment['amount']}₽</td>";
            echo "<td>{$payment['created_at']}</td>";
            echo "<td>{$payment['payment_type']}</td>";
            echo "<td>$dayName ({$payment['day_of_week_num']})</td>";
            echo "</tr>";
        }
        echo "</table>";

        // 2. Для каждой выплаты найти подходящие шаблоны
        echo "<h2>2. Подходящие шаблоны расписания</h2>";

        foreach ($orphanedPayments as $payment) {
            // DAYOFWEEK возвращает: 1=Вс, 2=Пн, 3=Вт, ... 7=Сб
            // lessons_template.day_of_week: 1=Пн, 2=Вт, ... 7=Вс
            $templateDayOfWeek = $payment['day_of_week_num'] - 1;
            if ($templateDayOfWeek == 0) {
                $templateDayOfWeek = 7; // Воскресенье
            }

            echo "<h3>Выплата #{$payment['id']} - {$payment['teacher_name']} - {$payment['created_at']}</h3>";

            $templates = dbQuery(
                "SELECT
                    lt.id,
                    lt.teacher_id,
                    lt.day_of_week,
                    lt.time_start,
                    lt.time_end,
                    lt.subject,
                    lt.lesson_type,
                    lt.room,
                    lt.tier,
                    lt.grades,
                    lt.students,
                    lt.expected_students,
                    lt.formula_id
                FROM lessons_template lt
                WHERE lt.teacher_id = ?
                    AND lt.day_of_week = ?
                    AND lt.active = 1
                ORDER BY lt.time_start",
                [$payment['teacher_id'], $templateDayOfWeek]
            );

            if (count($templates) > 0) {
                echo "<p class='success'>✅ Найдено шаблонов: " . count($templates) . "</p>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Время</th><th>Предмет</th><th>Тип</th><th>Кабинет</th><th>Tier</th><th>Классы</th><th>Студенты</th><th>Expected</th></tr>";
                foreach ($templates as $template) {
                    $students = json_decode($template['students'], true);
                    $studentsCount = is_array($students) ? count($students) : 0;
                    $studentsPreview = is_array($students) ? implode(', ', array_slice($students, 0, 2)) : '-';
                    if ($studentsCount > 2) {
                        $studentsPreview .= " +(" . ($studentsCount - 2) . ")";
                    }

                    echo "<tr>";
                    echo "<td>{$template['id']}</td>";
                    echo "<td>{$template['time_start']} - {$template['time_end']}</td>";
                    echo "<td>{$template['subject']}</td>";
                    echo "<td>{$template['lesson_type']}</td>";
                    echo "<td>{$template['room']}</td>";
                    echo "<td>{$template['tier']}</td>";
                    echo "<td>{$template['grades']}</td>";
                    echo "<td title='" . ($students ? implode(', ', $students) : '') . "'>{$studentsPreview}</td>";
                    echo "<td>{$template['expected_students']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='error'>❌ Шаблонов не найдено</p>";
            }
        }

        // 3. Проверить, есть ли уже lessons_instance для этой даты
        echo "<h2>3. Существующие lessons_instance для даты 2025-12-01</h2>";
        $instances = dbQuery(
            "SELECT
                li.id,
                li.teacher_id,
                t.name as teacher_name,
                li.lesson_date,
                li.time_start,
                li.time_end,
                li.subject,
                li.status,
                li.template_id
            FROM lessons_instance li
            LEFT JOIN teachers t ON li.teacher_id = t.id
            WHERE li.lesson_date = '2025-12-01'
            ORDER BY li.time_start",
            []
        );

        echo "<p>Найдено уроков на 2025-12-01: <strong>" . count($instances) . "</strong></p>";

        if (count($instances) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Преподаватель</th><th>Время</th><th>Предмет</th><th>Статус</th><th>Template ID</th></tr>";
            foreach ($instances as $instance) {
                echo "<tr>";
                echo "<td>{$instance['id']}</td>";
                echo "<td>{$instance['teacher_name']}</td>";
                echo "<td>{$instance['time_start']} - {$instance['time_end']}</td>";
                echo "<td>{$instance['subject']}</td>";
                echo "<td>{$instance['status']}</td>";
                echo "<td>{$instance['template_id']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ Нет lessons_instance для этой даты</p>";
        }
    }
    ?>

    <h2>💡 Рекомендации</h2>
    <ul>
        <li>Если найдены шаблоны, нужно создать lessons_instance на основе них</li>
        <li>Затем связать payments.lesson_instance_id с созданными уроками</li>
        <li>Если шаблонов нет, нужно сначала добавить расписание в систему</li>
    </ul>

</body>
</html>
