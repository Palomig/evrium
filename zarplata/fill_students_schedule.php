<?php
/**
 * Заполнение students.schedule из lessons_template
 *
 * ПРОБЛЕМА:
 * - Бот читает расписание из students.schedule (JSON)
 * - Если у студента schedule пуст - бот не видит его уроки
 * - При этом данные могут быть в lessons_template.students
 *
 * РЕШЕНИЕ:
 * - Парсим lessons_template.students (JSON массив имён)
 * - Находим соответствующих студентов в таблице students
 * - Заполняем их поле schedule в правильном формате
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
    <title>Заполнение расписания студентов</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #1a1a1a; color: #e0e0e0; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h2 { color: #14b8a6; border-bottom: 2px solid #14b8a6; padding-bottom: 10px; margin-top: 30px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 13px; }
        th, td { border: 1px solid #444; padding: 10px; text-align: left; }
        th { background: #2a2a2a; font-weight: 600; color: #14b8a6; }
        tr:nth-child(even) { background: #252525; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        .info { color: #3b82f6; font-weight: bold; }
        .btn { padding: 12px 24px; background: #14b8a6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; margin: 10px 5px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0d9488; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        .hint { background: rgba(20, 184, 166, 0.1); padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #14b8a6; }
        pre { background: #2a2a2a; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 12px; }
        code { background: #333; padding: 2px 6px; border-radius: 4px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-right: 4px; }
        .badge-new { background: #10b981; color: white; }
        .badge-empty { background: #ef4444; color: white; }
        .badge-has { background: #3b82f6; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📅 Заполнение расписания студентов из lessons_template</h1>

        <div class="hint">
            <strong>Что делает этот скрипт:</strong><br>
            • Ищет студентов с ПУСТЫМ полем <code>schedule</code><br>
            • Находит их в <code>lessons_template.students</code> (JSON массив имён)<br>
            • Формирует правильный JSON для <code>students.schedule</code><br>
            • Формат: <code>{"4": [{"time": "15:00", "teacher_id": 5, "subject": "Мат.", "room": 1}]}</code>
        </div>

        <?php
        $action = $_GET['action'] ?? 'preview';

        // Названия дней
        $dayNames = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье'
        ];

        // 1. Получаем всех студентов
        $allStudents = dbQuery(
            "SELECT id, name, schedule, teacher_id, active FROM students ORDER BY name",
            []
        );

        // Индексируем по имени (для быстрого поиска)
        $studentsByName = [];
        foreach ($allStudents as $s) {
            $studentsByName[$s['name']] = $s;
        }

        // 2. Получаем все шаблоны с учениками
        $templates = dbQuery(
            "SELECT lt.*, t.name as teacher_name
             FROM lessons_template lt
             LEFT JOIN teachers t ON lt.teacher_id = t.id
             WHERE lt.active = 1
             ORDER BY lt.day_of_week, lt.time_start",
            []
        );

        // 3. Собираем расписание для каждого студента из templates
        $newSchedules = []; // student_id => schedule array

        foreach ($templates as $tpl) {
            if (!$tpl['students']) continue;

            $studentNames = json_decode($tpl['students'], true);
            if (!is_array($studentNames)) continue;

            foreach ($studentNames as $studentName) {
                // Ищем студента по имени
                $student = $studentsByName[$studentName] ?? null;
                if (!$student) {
                    // Студент не найден в базе
                    continue;
                }

                $studentId = $student['id'];
                $day = (int)$tpl['day_of_week'];
                $time = substr($tpl['time_start'], 0, 5);
                $teacherId = (int)$tpl['teacher_id'];
                $room = (int)($tpl['room'] ?? 1);
                $subject = $tpl['subject'] ?? 'Мат.';

                // Инициализируем если нет
                if (!isset($newSchedules[$studentId])) {
                    $newSchedules[$studentId] = [];
                }
                if (!isset($newSchedules[$studentId][$day])) {
                    $newSchedules[$studentId][$day] = [];
                }

                // Добавляем урок
                $newSchedules[$studentId][$day][] = [
                    'time' => $time,
                    'teacher_id' => $teacherId,
                    'subject' => $subject,
                    'room' => $room
                ];
            }
        }

        // 4. Анализируем
        echo "<h2>📊 Анализ</h2>";

        // Студенты с пустым schedule
        $studentsWithEmptySchedule = [];
        $studentsWithSchedule = [];
        $studentsNotInTemplates = [];

        foreach ($allStudents as $s) {
            $hasSchedule = !empty($s['schedule']) && $s['schedule'] !== '{}' && $s['schedule'] !== 'null';
            $hasInTemplates = isset($newSchedules[$s['id']]);

            if (!$hasSchedule && $hasInTemplates) {
                $studentsWithEmptySchedule[] = $s;
            } elseif ($hasSchedule) {
                $studentsWithSchedule[] = $s;
            } elseif (!$hasInTemplates) {
                $studentsNotInTemplates[] = $s;
            }
        }

        echo "<p><span class='badge badge-has'>" . count($studentsWithSchedule) . "</span> студентов с заполненным schedule</p>";
        echo "<p><span class='badge badge-empty'>" . count($studentsWithEmptySchedule) . "</span> студентов с ПУСТЫМ schedule, но есть в lessons_template</p>";
        echo "<p><span class='info'>" . count($studentsNotInTemplates) . "</span> студентов без расписания (нет в templates)</p>";

        if (!empty($studentsWithEmptySchedule)) {
            echo "<h2>🔧 Студенты для обновления</h2>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Имя</th><th>Текущий schedule</th><th>Новый schedule</th></tr>";

            foreach ($studentsWithEmptySchedule as $s) {
                $newSched = $newSchedules[$s['id']] ?? [];
                $newSchedJson = json_encode($newSched, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                // Форматируем для читаемости
                $readableSchedule = [];
                foreach ($newSched as $day => $lessons) {
                    foreach ($lessons as $lesson) {
                        $readableSchedule[] = "{$dayNames[$day]}: {$lesson['time']} ({$lesson['subject']}, каб.{$lesson['room']})";
                    }
                }

                echo "<tr>";
                echo "<td>{$s['id']}</td>";
                echo "<td>{$s['name']}</td>";
                echo "<td><code>" . ($s['schedule'] ?: 'пусто') . "</code></td>";
                echo "<td><pre style='margin:0; font-size:11px;'>" . implode("\n", $readableSchedule) . "</pre></td>";
                echo "</tr>";
            }
            echo "</table>";

            if ($action === 'preview') {
                echo "<a href='?action=apply' class='btn' onclick=\"return confirm('Обновить schedule у " . count($studentsWithEmptySchedule) . " студентов?')\">✅ Применить изменения</a>";
            }
        }

        if ($action === 'apply' && !empty($studentsWithEmptySchedule)) {
            echo "<h2>⚙️ Применение изменений...</h2>";

            $updated = 0;
            $errors = [];

            foreach ($studentsWithEmptySchedule as $s) {
                $newSched = $newSchedules[$s['id']] ?? [];
                $newSchedJson = json_encode($newSched, JSON_UNESCAPED_UNICODE);

                try {
                    $result = dbExecute(
                        "UPDATE students SET schedule = ?, updated_at = NOW() WHERE id = ?",
                        [$newSchedJson, $s['id']]
                    );
                    $updated++;
                    echo "<p class='success'>✓ {$s['name']}: обновлено</p>";
                } catch (Exception $e) {
                    $errors[] = "{$s['name']}: " . $e->getMessage();
                    echo "<p class='error'>✗ {$s['name']}: " . $e->getMessage() . "</p>";
                }
            }

            echo "<h3>Результат</h3>";
            echo "<p class='success'>Обновлено: {$updated}</p>";
            if (!empty($errors)) {
                echo "<p class='error'>Ошибок: " . count($errors) . "</p>";
            }
        }

        // Показываем студентов с заполненным schedule для справки
        if (!empty($studentsWithSchedule)) {
            echo "<h2>📋 Студенты с заполненным schedule (для справки)</h2>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Имя</th><th>Schedule</th></tr>";

            foreach (array_slice($studentsWithSchedule, 0, 10) as $s) {
                $schedParsed = json_decode($s['schedule'], true);
                $readable = [];
                if (is_array($schedParsed)) {
                    foreach ($schedParsed as $day => $lessons) {
                        $dayName = $dayNames[(int)$day] ?? "День $day";
                        if (is_array($lessons)) {
                            foreach ($lessons as $lesson) {
                                if (is_array($lesson) && isset($lesson['time'])) {
                                    $tid = $lesson['teacher_id'] ?? '?';
                                    $subj = $lesson['subject'] ?? '?';
                                    $readable[] = "{$dayName}: {$lesson['time']} (teacher:{$tid}, {$subj})";
                                }
                            }
                        }
                    }
                }
                echo "<tr>";
                echo "<td>{$s['id']}</td>";
                echo "<td>{$s['name']}</td>";
                echo "<td><pre style='margin:0; font-size:11px;'>" . implode("\n", $readable) . "</pre></td>";
                echo "</tr>";
            }
            echo "</table>";
            if (count($studentsWithSchedule) > 10) {
                echo "<p>... и ещё " . (count($studentsWithSchedule) - 10) . " студентов</p>";
            }
        }
        ?>

        <p style="margin-top: 30px;">
            <a href="students.php" class="btn">← Вернуться к ученикам</a>
        </p>
    </div>
</body>
</html>
