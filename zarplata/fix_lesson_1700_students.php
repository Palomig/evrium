<?php
/**
 * Исправление списка студентов для урока 17:00 (понедельник)
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
    <title>Исправление списка студентов урока 17:00</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a1a; color: #e0e0e0; padding: 20px; }
        h2 { color: #14b8a6; border-bottom: 2px solid #14b8a6; padding-bottom: 10px; }
        .container { max-width: 900px; margin: 0 auto; background: #2a2a2a; padding: 30px; border-radius: 12px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #444; padding: 12px; text-align: left; }
        th { background: #333; font-weight: bold; color: #14b8a6; }
        tr:nth-child(even) { background: #252525; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        .btn { padding: 12px 24px; background: #14b8a6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; margin: 10px 5px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0d9488; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        pre { background: #1a1a1a; padding: 15px; border-radius: 8px; overflow-x: auto; border: 1px solid #444; }
        .form-group { margin: 20px 0; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #14b8a6; }
        .form-group textarea { width: 100%; padding: 12px; background: #1a1a1a; border: 1px solid #444; border-radius: 8px; color: #e0e0e0; font-family: 'Courier New', monospace; font-size: 14px; min-height: 200px; }
        .form-group input[type="number"] { width: 100px; padding: 8px; background: #1a1a1a; border: 1px solid #444; border-radius: 8px; color: #e0e0e0; }
        .student-list { list-style: none; padding: 0; }
        .student-list li { padding: 10px; margin: 5px 0; background: #1a1a1a; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; }
        .student-active { border-left: 4px solid #10b981; }
        .student-inactive { border-left: 4px solid #ef4444; text-decoration: line-through; opacity: 0.6; }
        .hint { background: rgba(20, 184, 166, 0.1); padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #14b8a6; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Исправление списка студентов - Урок 17:00 (Пн)</h1>

        <?php
        $action = $_GET['action'] ?? 'form';
        $templateId = 47; // ID шаблона урока 17:00

        if ($action === 'form') {
            // Получить текущие данные шаблона
            $template = dbQueryOne(
                "SELECT
                    id,
                    teacher_id,
                    day_of_week,
                    time_start,
                    time_end,
                    subject,
                    students,
                    expected_students
                FROM lessons_template
                WHERE id = ?",
                [$templateId]
            );

            if (!$template) {
                echo "<p class='error'>❌ Шаблон с ID $templateId не найден</p>";
                exit;
            }

            $currentStudents = json_decode($template['students'], true) ?: [];

            echo "<h2>Текущие данные</h2>";
            echo "<table>";
            echo "<tr><th>Поле</th><th>Значение</th></tr>";
            echo "<tr><td>ID шаблона</td><td>{$template['id']}</td></tr>";
            echo "<tr><td>Время</td><td>{$template['time_start']} - {$template['time_end']}</td></tr>";
            echo "<tr><td>Предмет</td><td>{$template['subject']}</td></tr>";
            echo "<tr><td>Expected students</td><td>{$template['expected_students']}</td></tr>";
            echo "</table>";

            echo "<h2>Текущий список студентов</h2>";
            echo "<ul class='student-list'>";
            foreach ($currentStudents as $student) {
                // Проверяем, деактивирован ли студент
                $isDeactivated = (stripos($student, 'Лёша') !== false || stripos($student, 'Лера') !== false);
                $class = $isDeactivated ? 'student-inactive' : 'student-active';
                $status = $isDeactivated ? '❌ Деактивирован' : '✅ Активен';
                echo "<li class='$class'><span>$student</span><span>$status</span></li>";
            }
            echo "</ul>";

            echo "<p><strong>Количество:</strong> " . count($currentStudents) . "</p>";

            echo "<div class='hint'>";
            echo "<strong>📋 Рекомендуемые изменения:</strong><br>";
            echo "• Убрать: <span class='error'>Лёша (6 кл.), Лера (7 кл.)</span><br>";
            echo "• Добавить: <span class='success'>Настя (8 кл.)</span><br>";
            echo "• Оставить: <span class='success'>Коля (7 кл.), Антоний (6 кл.)</span>";
            echo "</div>";

            // Форма редактирования
            echo "<h2>Обновить список студентов</h2>";
            echo "<form method='POST' action='?action=update' onsubmit='return confirm(\"Вы уверены? Это обновит список студентов в шаблоне.\");'>";
            echo "<input type='hidden' name='template_id' value='$templateId'>";

            echo "<div class='form-group'>";
            echo "<label>Список студентов (JSON массив):</label>";
            echo "<textarea name='students_json' required>";
            // Рекомендуемый список (убрали Лёшу и Леру, добавили Настю)
            $recommendedStudents = [
                "Коля (7 кл.)",
                "Антоний (6 кл.)",
                "Настя (8 кл.)"
            ];
            echo json_encode($recommendedStudents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "</textarea>";
            echo "<small style='color: #888;'>Формат: JSON-массив строк. Каждый студент - отдельная строка в кавычках.</small>";
            echo "</div>";

            echo "<div class='form-group'>";
            echo "<label>Expected students (количество):</label>";
            echo "<input type='number' name='expected_students' value='3' min='0' max='20' required>";
            echo "<small style='color: #888;'>Должно совпадать с количеством студентов в JSON</small>";
            echo "</div>";

            echo "<button type='submit' class='btn'>✅ Обновить список студентов</button>";
            echo " <a href='/zarplata/schedule.php' class='btn' style='background: #666;'>❌ Отмена</a>";
            echo "</form>";

        } elseif ($action === 'update') {
            // Обработка формы
            $templateId = (int)$_POST['template_id'];
            $studentsJson = $_POST['students_json'];
            $expectedStudents = (int)$_POST['expected_students'];

            // Валидация JSON
            $students = json_decode($studentsJson, true);
            if (!is_array($students)) {
                echo "<p class='error'>❌ Ошибка: некорректный JSON формат</p>";
                echo "<a href='?action=form' class='btn'>← Вернуться</a>";
                exit;
            }

            // Проверка количества
            if (count($students) !== $expectedStudents) {
                echo "<p class='warning'>⚠️ Предупреждение: количество студентов в JSON (" . count($students) . ") не совпадает с expected_students ($expectedStudents)</p>";
            }

            try {
                // Обновляем шаблон
                $result = dbExecute(
                    "UPDATE lessons_template
                     SET students = ?,
                         expected_students = ?
                     WHERE id = ?",
                    [$studentsJson, $expectedStudents, $templateId]
                );

                echo "<h2 class='success'>✅ Список студентов успешно обновлён!</h2>";

                echo "<h3>Новые данные:</h3>";
                echo "<table>";
                echo "<tr><th>Поле</th><th>Значение</th></tr>";
                echo "<tr><td>Template ID</td><td>$templateId</td></tr>";
                echo "<tr><td>Expected students</td><td>$expectedStudents</td></tr>";
                echo "</table>";

                echo "<h3>Новый список студентов:</h3>";
                echo "<ul class='student-list'>";
                foreach ($students as $student) {
                    echo "<li class='student-active'><span>$student</span><span>✅ Активен</span></li>";
                }
                echo "</ul>";

                echo "<div class='hint'>";
                echo "<strong>📝 Следующий шаг:</strong><br>";
                echo "Теперь нужно обновить <strong>expected_students</strong> в существующих <code>lessons_instance</code> для даты 2025-12-01.<br>";
                echo "Перейдите на страницу <a href='/zarplata/schedule.php' style='color: #14b8a6;'>Расписание</a> и проверьте изменения.";
                echo "</div>";

                echo "<a href='/zarplata/schedule.php' class='btn'>Перейти к Расписанию</a>";
                echo " <a href='/zarplata/tests.php' class='btn'>Запустить синхронизацию студентов</a>";

            } catch (Exception $e) {
                echo "<p class='error'>❌ Ошибка при обновлении: {$e->getMessage()}</p>";
                echo "<a href='?action=form' class='btn'>← Попробовать снова</a>";
            }
        }
        ?>
    </div>
</body>
</html>
