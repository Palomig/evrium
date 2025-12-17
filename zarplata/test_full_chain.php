<?php
/**
 * Полноценный тест всей цепочки загрузки студентов
 * Проверяет: БД → student_helpers → API → schedule.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/student_helpers.php';

// Стартуем сессию для тестов API
session_start();

// Если не авторизован, создаём тестовую сессию
if (!isLoggedIn()) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
    $_SESSION['role'] = 'admin';
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Полный тест цепочки загрузки студентов</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #fff; padding: 20px; }
        .section { margin: 30px 0; padding: 20px; background: #2a2a2a; border-radius: 8px; }
        .success { color: #4caf50; }
        .error { color: #f44336; }
        .warning { color: #ff9800; }
        h2 { color: #03dac6; border-bottom: 2px solid #03dac6; padding-bottom: 10px; }
        h3 { color: #bb86fc; margin-top: 20px; }
        pre { background: #1a1a1a; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #444; }
        th { background: #333; color: #03dac6; }
        .passed { background: #1b5e20; }
        .failed { background: #b71c1c; }
    </style>
</head>
<body>

<h1>🧪 Полный тест цепочки загрузки студентов</h1>

<?php

// ============================================================
// ТЕСТ 1: Проверка данных в базе данных
// ============================================================
echo '<div class="section">';
echo '<h2>ТЕСТ 1: Проверка данных в базе данных</h2>';

echo '<h3>1.1. Активные студенты</h3>';
$students = dbQuery("SELECT id, name, class, teacher_id, schedule, active FROM students WHERE active = 1");
echo "<p>Найдено активных студентов: <strong>" . count($students) . "</strong></p>";

if (count($students) > 0) {
    echo '<table>';
    echo '<tr><th>ID</th><th>Имя</th><th>Класс</th><th>Преподаватель ID</th><th>Формат расписания</th><th>Пример расписания</th></tr>';

    foreach (array_slice($students, 0, 10) as $student) {
        $schedule = null;
        if ($student['schedule']) {
            $schedule = json_decode($student['schedule'], true);
        }

        $format = 'NULL';
        $example = 'NULL';

        if ($schedule) {
            // Определяем формат
            $firstKey = array_key_first($schedule);
            $firstValue = $schedule[$firstKey];

            if (is_numeric($firstKey)) {
                if (is_array($firstValue)) {
                    if (isset($firstValue[0]) && is_array($firstValue[0])) {
                        $format = '<span class="success">Формат 3</span>';
                        $example = json_encode($firstValue[0]);
                    } else {
                        $format = '<span class="warning">Формат 1?</span>';
                        $example = json_encode($firstValue);
                    }
                } else {
                    $format = '<span class="warning">Формат 2</span>';
                    $example = $firstValue;
                }
            } else {
                $format = '<span class="warning">Формат 1</span>';
                $example = json_encode($firstValue);
            }
        }

        echo "<tr>";
        echo "<td>{$student['id']}</td>";
        echo "<td>{$student['name']}</td>";
        echo "<td>{$student['class']}</td>";
        echo "<td>{$student['teacher_id']}</td>";
        echo "<td>{$format}</td>";
        echo "<td><code>" . htmlspecialchars($example) . "</code></td>";
        echo "</tr>";
    }

    echo '</table>';
} else {
    echo '<p class="error">❌ Нет активных студентов в базе!</p>';
}

echo '<h3>1.2. Шаблоны уроков</h3>';
$templates = dbQuery("SELECT id, teacher_id, day_of_week, time_start, subject FROM lessons_template WHERE active = 1");
echo "<p>Найдено активных шаблонов: <strong>" . count($templates) . "</strong></p>";

if (count($templates) > 0) {
    echo '<table>';
    echo '<tr><th>ID</th><th>Преподаватель</th><th>День</th><th>Время</th><th>Предмет</th></tr>';

    foreach (array_slice($templates, 0, 10) as $template) {
        $days = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        $dayName = $days[$template['day_of_week']] ?? $template['day_of_week'];

        echo "<tr>";
        echo "<td>{$template['id']}</td>";
        echo "<td>{$template['teacher_id']}</td>";
        echo "<td>{$dayName} ({$template['day_of_week']})</td>";
        echo "<td>{$template['time_start']}</td>";
        echo "<td>{$template['subject']}</td>";
        echo "</tr>";
    }

    echo '</table>';
}

echo '</div>';

// ============================================================
// ТЕСТ 2: Проверка функции getStudentsForLesson()
// ============================================================
echo '<div class="section">';
echo '<h2>ТЕСТ 2: Проверка функции getStudentsForLesson()</h2>';

if (count($templates) > 0) {
    // Берём первые 5 шаблонов для теста
    foreach (array_slice($templates, 0, 5) as $template) {
        $days = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        $dayName = $days[$template['day_of_week']] ?? $template['day_of_week'];

        echo "<h3>Шаблон #{$template['id']}: {$dayName} {$template['time_start']} ({$template['subject']})</h3>";

        $result = getStudentsForLesson(
            $template['teacher_id'],
            $template['day_of_week'],
            substr($template['time_start'], 0, 5)
        );

        echo "<p><strong>Результат:</strong></p>";
        echo "<ul>";
        echo "<li>Количество студентов: <strong class='" . ($result['count'] > 0 ? 'success' : 'error') . "'>{$result['count']}</strong></li>";
        echo "<li>Классы: <strong>" . ($result['classes'] ?: 'нет') . "</strong></li>";
        echo "<li>Студенты: <strong>" . ($result['count'] > 0 ? implode(', ', array_column($result['students'], 'name')) : 'нет') . "</strong></li>";
        echo "</ul>";

        if ($result['count'] === 0) {
            echo '<p class="warning">⚠️ Функция вернула 0 студентов. Проверяем почему...</p>';

            // Детальная проверка
            $allStudents = dbQuery(
                "SELECT id, name, class, schedule FROM students WHERE active = 1 AND teacher_id = ?",
                [$template['teacher_id']]
            );

            echo "<p>Всего активных студентов у преподавателя {$template['teacher_id']}: <strong>" . count($allStudents) . "</strong></p>";

            if (count($allStudents) > 0) {
                echo "<p>Детальная проверка совпадений:</p>";
                echo "<ul>";

                foreach (array_slice($allStudents, 0, 5) as $student) {
                    if (!$student['schedule']) {
                        echo "<li class='error'>{$student['name']}: NULL расписание</li>";
                        continue;
                    }

                    $schedule = json_decode($student['schedule'], true);
                    if (!$schedule) {
                        echo "<li class='error'>{$student['name']}: Невалидный JSON</li>";
                        continue;
                    }

                    $found = false;
                    $debugInfo = [];

                    foreach ($schedule as $key => $entry) {
                        if (is_array($entry)) {
                            if (isset($entry[0]) && is_array($entry[0])) {
                                // Формат 3
                                if ((int)$key == $template['day_of_week']) {
                                    foreach ($entry as $timeSlot) {
                                        $entryTime = $timeSlot['time'] ?? null;
                                        $templateTime = substr($template['time_start'], 0, 5);

                                        $debugInfo[] = "День {$key}, время {$entryTime} vs {$templateTime}";

                                        if (isset($timeSlot['time']) && substr($timeSlot['time'], 0, 5) == $templateTime) {
                                            $found = true;
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if ($found) {
                        echo "<li class='success'>{$student['name']}: ✅ НАЙДЕНО совпадение</li>";
                    } else {
                        echo "<li class='error'>{$student['name']}: ❌ НЕТ совпадения (проверено: " . implode(', ', array_slice($debugInfo, 0, 3)) . ")</li>";
                    }
                }

                echo "</ul>";
            }
        }

        echo "<hr>";
    }
} else {
    echo '<p class="error">❌ Нет шаблонов для тестирования!</p>';
}

echo '</div>';

// ============================================================
// ТЕСТ 3: Проверка API /api/schedule.php
// ============================================================
echo '<div class="section">';
echo '<h2>ТЕСТ 3: Проверка API /api/schedule.php</h2>';

echo '<h3>3.1. Запрос: action=list_templates</h3>';

// Имитируем API вызов
$_GET['action'] = 'list_templates';

ob_start();
include __DIR__ . '/api/schedule.php';
$apiResponse = ob_get_clean();

$apiData = json_decode($apiResponse, true);

if ($apiData && isset($apiData['success']) && $apiData['success']) {
    echo '<p class="success">✅ API вернуло успешный ответ</p>';
    echo '<p>Количество шаблонов в ответе: <strong>' . count($apiData['data']) . '</strong></p>';

    if (count($apiData['data']) > 0) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Преподаватель</th><th>День/Время</th><th>students_array</th><th>actual_student_count</th><th>student_classes</th></tr>';

        foreach (array_slice($apiData['data'], 0, 10) as $tpl) {
            $hasStudentsArray = isset($tpl['students_array']) ? '✅' : '❌';
            $studentCount = $tpl['actual_student_count'] ?? 'null';
            $studentClasses = $tpl['student_classes'] ?? 'null';

            $days = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
            $dayName = $days[$tpl['day_of_week']] ?? $tpl['day_of_week'];

            echo "<tr class='" . ($studentCount > 0 ? 'passed' : 'failed') . "'>";
            echo "<td>{$tpl['id']}</td>";
            echo "<td>{$tpl['teacher_id']}</td>";
            echo "<td>{$dayName} {$tpl['time_start']}</td>";
            echo "<td>{$hasStudentsArray}</td>";
            echo "<td><strong>{$studentCount}</strong></td>";
            echo "<td>{$studentClasses}</td>";
            echo "</tr>";
        }

        echo '</table>';

        // Показываем первый шаблон полностью
        echo '<h3>Пример полного ответа (первый шаблон):</h3>';
        echo '<pre>' . json_encode($apiData['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
    }
} else {
    echo '<p class="error">❌ API вернуло ошибку или невалидный JSON</p>';
    echo '<pre>' . htmlspecialchars($apiResponse) . '</pre>';
}

echo '</div>';

// ============================================================
// ТЕСТ 4: Проверка schedule.php (серверная часть)
// ============================================================
echo '<div class="section">';
echo '<h2>ТЕСТ 4: Проверка schedule.php (серверная часть)</h2>';

echo '<p>Эмулируем загрузку данных из schedule.php...</p>';

$templates = dbQuery("
    SELECT
        lt.*,
        t.name AS teacher_name
    FROM lessons_template lt
    LEFT JOIN teachers t ON lt.teacher_id = t.id
    WHERE lt.active = 1
    ORDER BY lt.day_of_week, lt.time_start
");

echo '<p>Найдено шаблонов: <strong>' . count($templates) . '</strong></p>';

if (count($templates) > 0) {
    // Добавляем динамические данные студентов
    foreach ($templates as &$template) {
        $studentsData = getStudentsForLesson(
            $template['teacher_id'],
            $template['day_of_week'],
            substr($template['time_start'], 0, 5)
        );

        $template['students_array'] = $studentsData['students'];
        $template['actual_student_count'] = $studentsData['count'];
        $template['student_classes'] = $studentsData['classes'];
    }
    unset($template);

    echo '<table>';
    echo '<tr><th>ID</th><th>Преподаватель</th><th>День/Время</th><th>Студенты</th><th>Результат</th></tr>';

    foreach (array_slice($templates, 0, 10) as $tpl) {
        $days = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        $dayName = $days[$tpl['day_of_week']] ?? $tpl['day_of_week'];

        $studentNames = array_column($tpl['students_array'], 'name');

        echo "<tr class='" . ($tpl['actual_student_count'] > 0 ? 'passed' : 'failed') . "'>";
        echo "<td>{$tpl['id']}</td>";
        echo "<td>{$tpl['teacher_name']}</td>";
        echo "<td>{$dayName} {$tpl['time_start']}</td>";
        echo "<td>" . implode(', ', array_slice($studentNames, 0, 3)) . ($tpl['actual_student_count'] > 3 ? '...' : '') . "</td>";
        echo "<td><strong>{$tpl['actual_student_count']}/{$tpl['expected_students']}</strong> ({$tpl['student_classes']})</td>";
        echo "</tr>";
    }

    echo '</table>';
}

echo '</div>';

// ============================================================
// ИТОГОВЫЙ ОТЧЁТ
// ============================================================
echo '<div class="section">';
echo '<h2>📊 ИТОГОВЫЙ ОТЧЁТ</h2>';

$issues = [];

if (count($students) === 0) {
    $issues[] = 'Нет активных студентов в базе данных';
}

if (count($templates) === 0) {
    $issues[] = 'Нет активных шаблонов уроков';
}

$studentsWithSchedule = 0;
foreach ($students as $student) {
    if ($student['schedule']) {
        $studentsWithSchedule++;
    }
}

if ($studentsWithSchedule === 0) {
    $issues[] = 'Ни у одного студента нет расписания (поле schedule = NULL)';
}

if (count($issues) > 0) {
    echo '<h3 class="error">❌ Найдены проблемы:</h3>';
    echo '<ul>';
    foreach ($issues as $issue) {
        echo "<li class='error'>{$issue}</li>";
    }
    echo '</ul>';
} else {
    echo '<h3 class="success">✅ Базовые проверки пройдены</h3>';
}

echo '</div>';

?>

</body>
</html>
