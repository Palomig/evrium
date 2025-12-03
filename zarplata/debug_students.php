<?php
/**
 * Отладочный скрипт для проверки студентов и их расписаний
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

requireAuth();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Отладка студентов</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #fff; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #444; padding: 8px; text-align: left; }
        th { background: #2a2a2a; }
        .json { background: #2a2a2a; padding: 10px; border-radius: 4px; overflow-x: auto; }
        h2 { color: #14b8a6; }
        .inactive { opacity: 0.5; }
    </style>
</head>
<body>

<h1>🔍 Отладка студентов</h1>

<h2>1. Все студенты и их расписания</h2>
<?php
$students = dbQuery("SELECT * FROM students ORDER BY teacher_id, active DESC, name", []);
?>
<table>
    <tr>
        <th>ID</th>
        <th>Имя</th>
        <th>Класс</th>
        <th>Teacher ID</th>
        <th>Active</th>
        <th>Schedule (JSON)</th>
    </tr>
    <?php foreach ($students as $s): ?>
    <tr class="<?= $s['active'] ? '' : 'inactive' ?>">
        <td><?= $s['id'] ?></td>
        <td><?= htmlspecialchars($s['name']) ?></td>
        <td><?= $s['class'] ?></td>
        <td><?= $s['teacher_id'] ?></td>
        <td><?= $s['active'] ? '✅' : '❌' ?></td>
        <td><pre class="json"><?= htmlspecialchars($s['schedule'] ?: 'NULL') ?></pre></td>
    </tr>
    <?php endforeach; ?>
</table>

<h2>2. Все шаблоны уроков</h2>
<?php
$templates = dbQuery("
    SELECT lt.*, t.name as teacher_name
    FROM lessons_template lt
    LEFT JOIN teachers t ON lt.teacher_id = t.id
    WHERE lt.active = 1
    ORDER BY lt.day_of_week, lt.time_start
", []);
?>
<table>
    <tr>
        <th>ID</th>
        <th>Teacher</th>
        <th>День</th>
        <th>Время</th>
        <th>Предмет</th>
        <th>Кабинет</th>
    </tr>
    <?php foreach ($templates as $t): ?>
    <tr>
        <td><?= $t['id'] ?></td>
        <td><?= htmlspecialchars($t['teacher_name']) ?> (ID: <?= $t['teacher_id'] ?>)</td>
        <td><?= $t['day_of_week'] ?></td>
        <td><?= substr($t['time_start'], 0, 5) ?></td>
        <td><?= htmlspecialchars($t['subject']) ?></td>
        <td><?= $t['room'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h2>3. Проверка совпадений (поиск студентов для каждого шаблона)</h2>
<?php
foreach ($templates as $template) {
    $teacherId = $template['teacher_id'];
    $dayOfWeek = $template['day_of_week'];
    $timeStart = substr($template['time_start'], 0, 5);

    echo "<h3>Шаблон #{$template['id']}: {$template['teacher_name']} - День {$dayOfWeek}, {$timeStart}</h3>";

    // Получаем всех студентов этого преподавателя
    $allStudents = dbQuery(
        "SELECT id, name, class, schedule, active FROM students WHERE teacher_id = ?",
        [$teacherId]
    );

    echo "<p>Всего студентов у преподавателя: " . count($allStudents) . "</p>";

    $foundStudents = [];

    foreach ($allStudents as $student) {
        if (!$student['schedule']) {
            echo "<div>❌ Студент {$student['name']}: schedule = NULL</div>";
            continue;
        }

        $schedule = json_decode($student['schedule'], true);
        if (!is_array($schedule)) {
            echo "<div>❌ Студент {$student['name']}: schedule не JSON массив</div>";
            continue;
        }

        $hasThisLesson = false;

        foreach ($schedule as $key => $entry) {
            if (is_array($entry)) {
                // Проверяем, это массив объектов (Формат 3) или один объект (Формат 1)?
                if (isset($entry[0]) && is_array($entry[0])) {
                    // ⭐ Формат 3: {"1": [{"time": "17:00", "room": 1}, ...]}
                    // $key - это день недели, $entry - массив объектов с time/room
                    if ((int)$key == $dayOfWeek) {
                        foreach ($entry as $timeSlot) {
                            if (isset($timeSlot['time']) && substr($timeSlot['time'], 0, 5) == $timeStart) {
                                $hasThisLesson = true;
                                echo "<div>✅ Студент {$student['name']} (класс {$student['class']}): Найдено совпадение! День={$key}, Время={$timeSlot['time']} (Формат 3)</div>";
                                break 2; // Выходим из обоих циклов
                            }
                        }
                    }
                } else {
                    // Формат 1: массив объектов с полем day
                    $entryDay = $entry['day'] ?? null;
                    $entryTime = $entry['time'] ?? null;

                    $dayMap = [
                        'Monday' => 1, 'Пн' => 1, 'понедельник' => 1,
                        'Tuesday' => 2, 'Вт' => 2, 'вторник' => 2,
                        'Wednesday' => 3, 'Ср' => 3, 'среда' => 3,
                        'Thursday' => 4, 'Чт' => 4, 'четверг' => 4,
                        'Friday' => 5, 'Пт' => 5, 'пятница' => 5,
                        'Saturday' => 6, 'Сб' => 6, 'суббота' => 6,
                        'Sunday' => 7, 'Вс' => 7, 'воскресенье' => 7
                    ];

                    $entryDayNum = $dayMap[$entryDay] ?? (int)$entryDay;

                    if ($entryDayNum == $dayOfWeek && substr($entryTime, 0, 5) == $timeStart) {
                        $hasThisLesson = true;
                        echo "<div>✅ Студент {$student['name']} (класс {$student['class']}): Найдено совпадение! День={$entryDay} ({$entryDayNum}), Время={$entryTime} (Формат 1)</div>";
                        break;
                    }
                }
            } else {
                // Формат 2: объект {"1": "17:00"}
                if ((int)$key == $dayOfWeek && substr($entry, 0, 5) == $timeStart) {
                    $hasThisLesson = true;
                    echo "<div>✅ Студент {$student['name']} (класс {$student['class']}): Найдено совпадение! День={$key}, Время={$entry} (Формат 2)</div>";
                    break;
                }
            }
        }

        if (!$hasThisLesson) {
            echo "<div style='opacity:0.5'>⚪ Студент {$student['name']}: Нет совпадения</div>";
        }
    }

    echo "<hr>";
}
?>

<h2>4. Тест функции getStudentsForLesson()</h2>
<?php
require_once __DIR__ . '/config/student_helpers.php';

foreach ($templates as $template) {
    $result = getStudentsForLesson(
        $template['teacher_id'],
        $template['day_of_week'],
        substr($template['time_start'], 0, 5)
    );

    echo "<h3>Шаблон #{$template['id']}: {$template['teacher_name']} - День {$template['day_of_week']}, " . substr($template['time_start'], 0, 5) . "</h3>";
    echo "<p><strong>Результат функции:</strong></p>";
    echo "<pre class='json'>";
    echo "Count: {$result['count']}\n";
    echo "Classes: {$result['classes']}\n";
    echo "Students: " . json_encode($result['students'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    echo "</pre>";
    echo "<hr>";
}
?>

</body>
</html>
