<?php
/**
 * Синхронизация списка студентов в lessons_template на основе students.schedule
 *
 * АВТОМАТИЧЕСКАЯ СИНХРОНИЗАЦИЯ:
 * 1. Берет АКТИВНЫХ студентов из таблицы students (active = 1)
 * 2. Парсит их schedule (JSON) - какие дни/время у них занятия
 * 3. Обновляет lessons_template.students для каждого шаблона
 * 4. Обновляет expected_students = реальное количество
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

header('Content-Type: text/html; charset=utf-8');

// Требуем авторизацию
requireAuth();
$user = getCurrentUser();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Синхронизация студентов с расписанием</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #1a1a1a; color: #e0e0e0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h2 { color: #14b8a6; border-bottom: 2px solid #14b8a6; padding-bottom: 10px; margin-top: 30px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 13px; }
        th, td { border: 1px solid #444; padding: 10px; text-align: left; }
        th { background: #2a2a2a; font-weight: 600; color: #14b8a6; }
        tr:nth-child(even) { background: #252525; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        .btn { padding: 12px 24px; background: #14b8a6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; margin: 10px 5px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0d9488; }
        .hint { background: rgba(20, 184, 166, 0.1); padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #14b8a6; }
        pre { background: #2a2a2a; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 12px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-active { background: #10b981; color: white; }
        .badge-inactive { background: #ef4444; color: white; }
        .badge-added { background: #3b82f6; color: white; }
        .badge-removed { background: #f59e0b; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Синхронизация студентов в шаблонах расписания</h1>

        <div class="hint">
            <strong>Как это работает:</strong><br>
            • Берет АКТИВНЫХ студентов из таблицы <code>students</code> (где <code>active = 1</code>)<br>
            • Парсит их поле <code>schedule</code> (JSON) - какие дни/время у них занятия<br>
            • Автоматически обновляет <code>lessons_template.students</code> для каждого шаблона<br>
            • Деактивированные студенты автоматически удаляются из всех уроков
        </div>

        <?php
        $action = $_GET['action'] ?? 'preview';

        if ($action === 'preview') {
            // Предпросмотр изменений
            echo "<h2>📊 Анализ данных</h2>";

            // 1. Получить всех студентов
            $allStudents = dbQuery(
                "SELECT id, name, class, schedule, active, teacher_id
                 FROM students
                 ORDER BY active DESC, name",
                []
            );

            echo "<h3>1. Студенты в системе</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Имя</th><th>Класс</th><th>Расписание (JSON)</th><th>Статус</th></tr>";

            foreach ($allStudents as $student) {
                $statusBadge = $student['active']
                    ? '<span class="badge badge-active">Активен</span>'
                    : '<span class="badge badge-inactive">Деактивирован</span>';

                $schedulePreview = $student['schedule'] ? substr($student['schedule'], 0, 100) . '...' : '—';

                echo "<tr>";
                echo "<td>{$student['id']}</td>";
                echo "<td>{$student['name']}</td>";
                echo "<td>{$student['class']}</td>";
                echo "<td><code style='font-size: 11px;'>{$schedulePreview}</code></td>";
                echo "<td>$statusBadge</td>";
                echo "</tr>";
            }
            echo "</table>";

            // 2. Получить шаблоны расписания
            $templates = dbQuery(
                "SELECT
                    lt.id,
                    lt.teacher_id,
                    lt.day_of_week,
                    lt.time_start,
                    lt.time_end,
                    lt.subject,
                    lt.students,
                    lt.expected_students,
                    t.name as teacher_name
                 FROM lessons_template lt
                 LEFT JOIN teachers t ON lt.teacher_id = t.id
                 WHERE lt.active = 1
                 ORDER BY lt.day_of_week, lt.time_start",
                []
            );

            echo "<h3>2. Текущие шаблоны расписания</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>День</th><th>Время</th><th>Преподаватель</th><th>Предмет</th><th>Текущие студенты</th><th>Expected</th></tr>";

            $dayNames = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

            foreach ($templates as $template) {
                $currentStudents = json_decode($template['students'], true) ?: [];
                $studentsText = is_array($currentStudents) ? implode(', ', $currentStudents) : $template['students'];

                echo "<tr>";
                echo "<td>{$template['id']}</td>";
                echo "<td>{$dayNames[$template['day_of_week']]}</td>";
                echo "<td>" . substr($template['time_start'], 0, 5) . "</td>";
                echo "<td>{$template['teacher_name']}</td>";
                echo "<td>{$template['subject']}</td>";
                echo "<td style='font-size: 11px;'>$studentsText</td>";
                echo "<td>{$template['expected_students']}</td>";
                echo "</tr>";
            }
            echo "</table>";

            // 3. Построить карту: какие студенты должны быть в каких уроках
            echo "<h3>3. Предпросмотр изменений</h3>";

            $changesCount = 0;
            $updates = [];

            foreach ($templates as $template) {
                $templateId = $template['id'];
                $dayOfWeek = $template['day_of_week'];
                $timeStart = substr($template['time_start'], 0, 5);

                // Текущие студенты
                $currentStudents = json_decode($template['students'], true) ?: [];

                // Найти студентов, которые ДОЛЖНЫ быть на этом уроке
                $expectedStudents = [];

                foreach ($allStudents as $student) {
                    if (!$student['active']) continue; // Пропускаем неактивных
                    if ($student['teacher_id'] != $template['teacher_id']) continue; // Только студенты этого преподавателя

                    // Парсим schedule
                    $schedule = json_decode($student['schedule'], true);
                    if (!is_array($schedule)) continue;

                    // Проверяем, есть ли этот день/время в расписании студента
                    foreach ($schedule as $entry) {
                        $entryDay = $entry['day'] ?? null;
                        $entryTime = $entry['time'] ?? null;

                        // Преобразуем день из названия в номер
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
                            $studentName = $student['name'];
                            if ($student['class']) {
                                $studentName .= " ({$student['class']} кл.)";
                            }
                            $expectedStudents[] = $studentName;
                            break;
                        }
                    }
                }

                // Сравниваем текущих и ожидаемых
                sort($currentStudents);
                sort($expectedStudents);

                if ($currentStudents !== $expectedStudents) {
                    $changesCount++;

                    $added = array_diff($expectedStudents, $currentStudents);
                    $removed = array_diff($currentStudents, $expectedStudents);

                    $updates[] = [
                        'template_id' => $templateId,
                        'expected_students' => $expectedStudents,
                        'expected_count' => count($expectedStudents),
                        'current' => $currentStudents,
                        'added' => $added,
                        'removed' => $removed,
                        'template' => $template
                    ];
                }
            }

            if ($changesCount > 0) {
                echo "<p class='warning'><strong>⚠️ Найдено изменений: $changesCount</strong></p>";

                echo "<table>";
                echo "<tr><th>Урок</th><th>Текущие студенты</th><th>→</th><th>Новые студенты</th><th>Изменения</th></tr>";

                foreach ($updates as $update) {
                    $template = $update['template'];
                    $lessonInfo = $dayNames[$template['day_of_week']] . ' ' . substr($template['time_start'], 0, 5) . ' - ' . $template['subject'];

                    echo "<tr>";
                    echo "<td><strong>$lessonInfo</strong><br><small>ID: {$template['id']}</small></td>";
                    echo "<td style='font-size: 11px;'>" . implode('<br>', $update['current'] ?: ['—']) . "</td>";
                    echo "<td>→</td>";
                    echo "<td style='font-size: 11px;'>" . implode('<br>', $update['expected_students'] ?: ['—']) . "</td>";
                    echo "<td>";

                    if (!empty($update['added'])) {
                        foreach ($update['added'] as $name) {
                            echo "<span class='badge badge-added'>+ $name</span><br>";
                        }
                    }

                    if (!empty($update['removed'])) {
                        foreach ($update['removed'] as $name) {
                            echo "<span class='badge badge-removed'>− $name</span><br>";
                        }
                    }

                    echo "</td>";
                    echo "</tr>";
                }
                echo "</table>";

                echo "<form method='POST' action='?action=execute' onsubmit='return confirm(\"Вы уверены? Это обновит $changesCount шаблонов.\");'>";
                echo "<input type='hidden' name='updates' value='" . htmlspecialchars(json_encode($updates), ENT_QUOTES) . "'>";
                echo "<button type='submit' class='btn'>✅ Применить изменения ($changesCount шаблонов)</button>";
                echo "</form>";

            } else {
                echo "<p class='success'>✅ Все шаблоны уже синхронизированы! Изменений не требуется.</p>";
            }

        } elseif ($action === 'execute') {
            // Выполнение синхронизации
            echo "<h2>✅ Выполнение синхронизации</h2>";

            $updatesJson = $_POST['updates'] ?? '';
            $updates = json_decode($updatesJson, true);

            if (!is_array($updates) || empty($updates)) {
                echo "<p class='error'>❌ Нет данных для обновления</p>";
                exit;
            }

            $successCount = 0;
            $errorCount = 0;

            echo "<table>";
            echo "<tr><th>Template ID</th><th>Урок</th><th>Студентов</th><th>Результат</th></tr>";

            foreach ($updates as $update) {
                $templateId = $update['template_id'];
                $expectedStudents = $update['expected_students'];
                $expectedCount = $update['expected_count'];

                $studentsJson = json_encode($expectedStudents, JSON_UNESCAPED_UNICODE);

                try {
                    $result = dbExecute(
                        "UPDATE lessons_template
                         SET students = ?,
                             expected_students = ?
                         WHERE id = ?",
                        [$studentsJson, $expectedCount, $templateId]
                    );

                    $template = $update['template'];
                    $dayNames = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                    $lessonInfo = $dayNames[$template['day_of_week']] . ' ' . substr($template['time_start'], 0, 5);

                    echo "<tr>";
                    echo "<td>$templateId</td>";
                    echo "<td>$lessonInfo</td>";
                    echo "<td>$expectedCount</td>";
                    echo "<td class='success'>✅ Успешно</td>";
                    echo "</tr>";

                    $successCount++;
                } catch (Exception $e) {
                    echo "<tr>";
                    echo "<td>$templateId</td>";
                    echo "<td>—</td>";
                    echo "<td>—</td>";
                    echo "<td class='error'>❌ Ошибка: {$e->getMessage()}</td>";
                    echo "</tr>";

                    $errorCount++;
                }
            }

            echo "</table>";

            echo "<p class='success'><strong>✅ Успешно обновлено:</strong> $successCount</p>";
            if ($errorCount > 0) {
                echo "<p class='error'><strong>❌ Ошибок:</strong> $errorCount</p>";
            }

            echo "<a href='/zarplata/schedule.php' class='btn'>Перейти к Расписанию</a>";
            echo " <a href='?action=preview' class='btn' style='background: #666;'>Запустить еще раз</a>";
        }
        ?>
    </div>
</body>
</html>
