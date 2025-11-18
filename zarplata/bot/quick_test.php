<?php
/**
 * Быстрый тест отправки опроса посещаемости
 * Откройте: https://эвриум.рф/zarplata/bot/quick_test.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

echo "<h1>Быстрый тест опроса посещаемости</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #1a1a1a; color: #fff; }
    pre { background: #2a2a2a; padding: 15px; border-radius: 8px; overflow-x: auto; }
    .success { color: #4caf50; }
    .error { color: #f44336; }
    .warning { color: #ff9800; }
    .info { color: #2196f3; }
    .btn { display: inline-block; padding: 12px 24px; margin: 10px 5px; background: #4caf50; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; }
    .btn:hover { background: #45a049; }
    .btn-secondary { background: #2196f3; }
    .btn-secondary:hover { background: #1976d2; }
</style>";

// Обработка отправки
if (isset($_GET['send']) && isset($_GET['lesson_id'])) {
    $lessonId = filter_var($_GET['lesson_id'], FILTER_VALIDATE_INT);

    echo "<h2>Отправка опроса</h2><pre>";

    $lesson = dbQueryOne(
        "SELECT lt.*, t.name as teacher_name, t.telegram_id
         FROM lessons_template lt
         JOIN teachers t ON lt.teacher_id = t.id
         WHERE lt.id = ?",
        [$lessonId]
    );

    if (!$lesson) {
        echo "<span class='error'>❌ Урок не найден</span>\n";
    } elseif (!$lesson['telegram_id']) {
        echo "<span class='error'>❌ У преподавателя {$lesson['teacher_name']} нет Telegram ID</span>\n";
        echo "\nДобавьте Telegram ID на странице: https://эвриум.рф/zarplata/teachers.php\n";
    } else {
        // Отправляем опрос
        require_once __DIR__ . '/cron.php';
        sendAttendanceQuery($lesson);

        echo "<span class='success'>✅ Опрос отправлен!</span>\n\n";
        echo "📱 Telegram ID: {$lesson['telegram_id']}\n";
        echo "👤 Преподаватель: {$lesson['teacher_name']}\n";
        echo "📚 Предмет: " . ($lesson['subject'] ?: '-') . "\n";
        echo "🕐 Время: " . date('H:i', strtotime($lesson['time_start'])) . " - " . date('H:i', strtotime($lesson['time_end'])) . "\n\n";
        echo "✨ <strong>Проверьте Telegram - должно прийти сообщение с кнопками!</strong>\n";
    }

    echo "</pre>";
    echo "<a class='btn btn-secondary' href='quick_test.php'>← Назад</a>";
    exit;
}

// Получаем все уроки с Telegram ID
echo "<h2>Все уроки в расписании (с Telegram ID)</h2>";

$lessons = dbQuery(
    "SELECT lt.*,
            t.name as teacher_name,
            t.telegram_id,
            CASE
                WHEN lt.day_of_week = 1 THEN 'Понедельник'
                WHEN lt.day_of_week = 2 THEN 'Вторник'
                WHEN lt.day_of_week = 3 THEN 'Среда'
                WHEN lt.day_of_week = 4 THEN 'Четверг'
                WHEN lt.day_of_week = 5 THEN 'Пятница'
                WHEN lt.day_of_week = 6 THEN 'Суббота'
                WHEN lt.day_of_week = 7 THEN 'Воскресенье'
            END as day_name
     FROM lessons_template lt
     JOIN teachers t ON lt.teacher_id = t.id
     WHERE lt.active = 1 AND t.active = 1 AND t.telegram_id IS NOT NULL
     ORDER BY lt.day_of_week, lt.time_start",
    []
);

if (empty($lessons)) {
    echo "<pre>";
    echo "<span class='warning'>⚠️ Нет уроков в расписании</span>\n\n";
    echo "Что нужно сделать:\n";
    echo "1. Откройте https://эвриум.рф/zarplata/teachers.php\n";
    echo "2. Добавьте или отредактируйте преподавателя\n";
    echo "3. Укажите Telegram ID: <strong>245710727</strong>\n";
    echo "4. Откройте https://эвриум.рф/zarplata/schedule.php\n";
    echo "5. Добавьте урок на любой день недели\n";
    echo "6. Вернитесь на эту страницу и отправьте тестовый опрос\n";
    echo "</pre>";
} else {
    echo "<p>Найдено уроков: <strong>" . count($lessons) . "</strong></p>";
    echo "<p class='info'>💡 Выберите любой урок и нажмите \"Отправить тестовый опрос\" - <strong>не важно, какой сейчас день и время!</strong></p>";

    echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #333;'>
        <th>День недели</th>
        <th>Время</th>
        <th>Преподаватель</th>
        <th>Предмет</th>
        <th>Tier</th>
        <th>Кабинет</th>
        <th>Учеников</th>
        <th>Действие</th>
    </tr>";

    foreach ($lessons as $lesson) {
        $timeStart = date('H:i', strtotime($lesson['time_start']));
        $timeEnd = date('H:i', strtotime($lesson['time_end']));
        $subject = $lesson['subject'] ?: '-';
        $tier = $lesson['tier'] ?: '-';
        $room = $lesson['room'] ?: '-';

        // Проверяем, был ли опрошен сегодня
        $today = date('Y-m-d');
        $wasPolled = dbQueryOne(
            "SELECT id FROM payments WHERE teacher_id = ? AND lesson_template_id = ? AND DATE(created_at) = ?",
            [$lesson['teacher_id'], $lesson['id'], $today]
        );

        $action = $wasPolled
            ? "<span class='info'>✓ Опрошен сегодня</span>"
            : "<a class='btn' href='?send=1&lesson_id={$lesson['id']}'>Отправить тестовый опрос</a>";

        echo "<tr>
            <td>{$lesson['day_name']}</td>
            <td>{$timeStart} - {$timeEnd}</td>
            <td>{$lesson['teacher_name']}</td>
            <td>{$subject}</td>
            <td>{$tier}</td>
            <td>{$room}</td>
            <td>{$lesson['expected_students']}</td>
            <td>{$action}</td>
        </tr>";
    }

    echo "</table>";
}

echo "<hr>";
echo "<h2>Инструкция</h2>";
echo "<ol style='line-height: 1.8;'>";
echo "<li>Нажмите кнопку <strong>\"Отправить тестовый опрос\"</strong> для любого урока</li>";
echo "<li>Откройте Telegram - должно прийти сообщение с двумя кнопками:
    <ul>
        <li>✅ Да, все пришли</li>
        <li>❌ Нет, есть отсутствующие</li>
    </ul>
</li>";
echo "<li>Нажмите на любую кнопку:
    <ul>
        <li>Если нажмете \"Да\" - создастся выплата за всех учеников</li>
        <li>Если нажмете \"Нет\" - покажет кнопки с числами для выбора количества</li>
    </ul>
</li>";
echo "<li>Проверьте, что выплата создалась: <a href='../payments.php' style='color: #4caf50;'>https://эвриум.рф/zarplata/payments.php</a></li>";
echo "<li><strong style='color: #f44336;'>ВАЖНО:</strong> Удалите этот файл после тестирования!</li>";
echo "</ol>";

echo "<hr>";
echo "<p><a class='btn btn-secondary' href='test_attendance.php'>Открыть расширенный тест</a></p>";
