<?php
/**
 * Диагностика урока 17:00 понедельник - проверка студентов
 */
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Диагностика урока 17:00</title>
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
        pre { background: #2a2a2a; padding: 12px; border-radius: 8px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Диагностика урока 17:00 (понедельник)</h1>

    <?php
    // 1. Найти шаблон урока 17:00 в понедельник для Станислава Олеговича
    echo "<h2>1. Шаблон lessons_template для 17:00</h2>";

    $template = dbQueryOne(
        "SELECT
            lt.id,
            lt.teacher_id,
            t.name as teacher_name,
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
            lt.active
        FROM lessons_template lt
        LEFT JOIN teachers t ON lt.teacher_id = t.id
        WHERE lt.day_of_week = 1
            AND lt.time_start = '17:00:00'
            AND lt.active = 1
        LIMIT 1",
        []
    );

    if ($template) {
        echo "<table>";
        echo "<tr><th>Поле</th><th>Значение</th></tr>";
        echo "<tr><td>ID</td><td>{$template['id']}</td></tr>";
        echo "<tr><td>Преподаватель</td><td>{$template['teacher_name']}</td></tr>";
        echo "<tr><td>Время</td><td>{$template['time_start']} - {$template['time_end']}</td></tr>";
        echo "<tr><td>Предмет</td><td>{$template['subject']}</td></tr>";
        echo "<tr><td>Тип</td><td>{$template['lesson_type']}</td></tr>";
        echo "<tr><td>Кабинет</td><td>{$template['room']}</td></tr>";
        echo "<tr><td>Expected students</td><td>{$template['expected_students']}</td></tr>";
        echo "</table>";

        echo "<h3>Студенты в JSON (lessons_template.students):</h3>";
        $studentsJson = json_decode($template['students'], true);
        if (is_array($studentsJson)) {
            echo "<pre>" . json_encode($studentsJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            echo "<p><strong>Количество студентов в JSON:</strong> " . count($studentsJson) . "</p>";
        } else {
            echo "<p class='error'>❌ Некорректный JSON или пустое поле</p>";
        }

    } else {
        echo "<p class='error'>❌ Шаблон не найден</p>";
    }

    // 2. Найти всех активных учеников с расписанием "Пн: 17:00"
    echo "<h2>2. Ученики из CRM с расписанием 'Пн: 17:00'</h2>";

    // Проверяем, есть ли таблица students в zarplata базе
    $studentsTable = dbQuery("SHOW TABLES LIKE 'students'", []);

    if (empty($studentsTable)) {
        echo "<p class='warning'>⚠️ Таблица students не найдена в базе zarplata</p>";
        echo "<p>Проверяем в базе CRM (cw95865_crm)...</p>";

        // Нужно подключиться к CRM базе
        echo "<p class='error'>❌ Для проверки CRM требуется отдельное подключение</p>";

    } else {
        // Студенты есть в zarplata
        $students = dbQuery(
            "SELECT
                s.id,
                s.name,
                s.phone,
                s.email,
                s.class,
                s.active,
                s.notes
            FROM students s
            WHERE s.active = 1",
            []
        );

        echo "<p>Найдено активных учеников в системе: <strong>" . count($students) . "</strong></p>";

        if (count($students) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Имя</th><th>Класс</th><th>Статус</th><th>Примечания</th></tr>";
            foreach ($students as $student) {
                echo "<tr>";
                echo "<td>{$student['id']}</td>";
                echo "<td>{$student['name']}</td>";
                echo "<td>{$student['class']}</td>";
                echo "<td class='success'>Активен</td>";
                echo "<td>{$student['notes']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

        // Поиск по расписанию в примечаниях (если там хранится)
        echo "<h3>Поиск учеников с упоминанием '17:00' или 'понедельник' в данных:</h3>";
        $studentsWithSchedule = dbQuery(
            "SELECT
                s.id,
                s.name,
                s.class,
                s.notes
            FROM students s
            WHERE s.active = 1
                AND (s.notes LIKE '%17:00%' OR s.notes LIKE '%понедельник%' OR s.notes LIKE '%Пн%')
            ORDER BY s.name",
            []
        );

        if (count($studentsWithSchedule) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Имя</th><th>Класс</th><th>Примечания</th></tr>";
            foreach ($studentsWithSchedule as $student) {
                echo "<tr>";
                echo "<td>{$student['id']}</td>";
                echo "<td>{$student['name']}</td>";
                echo "<td>{$student['class']}</td>";
                echo "<td>{$student['notes']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ Не найдено учеников с расписанием 17:00</p>";
        }
    }

    // 3. Проверить деактивированных учеников (Лёша, Лера)
    echo "<h2>3. Деактивированные ученики (Лёша, Лера)</h2>";

    if (!empty($studentsTable)) {
        $deactivated = dbQuery(
            "SELECT
                s.id,
                s.name,
                s.class,
                s.active,
                s.notes
            FROM students s
            WHERE s.active = 0
                AND (s.name LIKE '%Лёша%' OR s.name LIKE '%Лера%')
            ORDER BY s.name",
            []
        );

        if (count($deactivated) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Имя</th><th>Класс</th><th>Статус</th></tr>";
            foreach ($deactivated as $student) {
                echo "<tr>";
                echo "<td>{$student['id']}</td>";
                echo "<td>{$student['name']}</td>";
                echo "<td>{$student['class']}</td>";
                echo "<td class='error'>Деактивирован</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Деактивированные ученики с именами Лёша/Лера не найдены</p>";
        }
    }

    // 4. Найти Настю (8 класс)
    echo "<h2>4. Поиск ученика 'Настя (8 класс)'</h2>";

    if (!empty($studentsTable)) {
        $nastya = dbQuery(
            "SELECT
                s.id,
                s.name,
                s.class,
                s.active,
                s.phone,
                s.notes
            FROM students s
            WHERE (s.name LIKE '%Наст%' OR s.name LIKE '%Анаст%')
                AND s.class = 8
            ORDER BY s.active DESC, s.name",
            []
        );

        if (count($nastya) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Имя</th><th>Класс</th><th>Статус</th><th>Примечания/Расписание</th></tr>";
            foreach ($nastya as $student) {
                $statusClass = $student['active'] ? 'success' : 'error';
                $statusText = $student['active'] ? 'Активна' : 'Деактивирована';
                echo "<tr>";
                echo "<td>{$student['id']}</td>";
                echo "<td>{$student['name']}</td>";
                echo "<td>{$student['class']}</td>";
                echo "<td class='$statusClass'>$statusText</td>";
                echo "<td>{$student['notes']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ Настя (8 класс) не найдена в базе</p>";
        }
    }

    ?>

    <h2>💡 Рекомендации</h2>
    <ul>
        <li>Обновить JSON в lessons_template.students для урока 17:00</li>
        <li>Убрать деактивированных учеников (Лёша, Лера)</li>
        <li>Добавить Настю (8 класс) в список</li>
        <li>Обновить expected_students = реальное количество</li>
    </ul>

    <p><strong>На следующем этапе создам скрипт для исправления данных.</strong></p>

</body>
</html>
