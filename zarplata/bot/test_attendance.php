<?php
/**
 * Тестовый скрипт для проверки системы опроса посещаемости
 * Откройте: https://эвриум.рф/zarplata/bot/test_attendance.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

echo "<h1>Тест системы опроса посещаемости</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #1a1a1a; color: #fff; }
    pre { background: #2a2a2a; padding: 15px; border-radius: 8px; overflow-x: auto; }
    .success { color: #4caf50; }
    .error { color: #f44336; }
    .warning { color: #ff9800; }
    .info { color: #2196f3; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #444; padding: 12px; text-align: left; }
    th { background: #333; }
    .btn { display: inline-block; padding: 10px 20px; margin: 5px; background: #2196f3; color: white; text-decoration: none; border-radius: 4px; }
    .btn:hover { background: #1976d2; }
</style>";

echo "<pre>";

// Шаг 1: Проверяем расписание на сегодня
echo "=== Шаг 1: Расписание на сегодня ===\n";
$dayOfWeek = date('N');
$dayNames = ['', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
$today = date('d.m.Y');
$currentTime = date('H:i:s');

echo "📅 Сегодня: {$dayNames[$dayOfWeek]}, {$today}\n";
echo "🕐 Текущее время: {$currentTime}\n\n";

$lessons = dbQuery(
    "SELECT lt.*, t.name as teacher_name, t.telegram_id
     FROM lessons_template lt
     JOIN teachers t ON lt.teacher_id = t.id
     WHERE lt.day_of_week = ? AND lt.active = 1 AND t.active = 1
     ORDER BY lt.time_start ASC",
    [$dayOfWeek]
);

if (empty($lessons)) {
    echo "<span class='warning'>⚠️ На сегодня нет уроков в расписании</span>\n";
    echo "\nДобавьте уроки в расписание через: https://эвриум.рф/zarplata/schedule.php\n";
} else {
    echo "<span class='success'>✅ Найдено уроков: " . count($lessons) . "</span>\n";
}

echo "</pre>";

// Таблица с уроками
if (!empty($lessons)) {
    echo "<h2>Расписание на сегодня</h2>";
    echo "<table>";
    echo "<tr>
        <th>ID</th>
        <th>Время</th>
        <th>Преподаватель</th>
        <th>Предмет</th>
        <th>Кабинет</th>
        <th>Ожидается учеников</th>
        <th>Telegram ID</th>
        <th>Статус</th>
        <th>Действие</th>
    </tr>";

    foreach ($lessons as $lesson) {
        $timeStart = date('H:i', strtotime($lesson['time_start']));
        $timeEnd = date('H:i', strtotime($lesson['time_end']));
        $subject = $lesson['subject'] ?: '-';
        $room = $lesson['room'] ?: '-';
        $hasTelegram = $lesson['telegram_id'] ? '✅' : '❌';

        // Проверяем, прошло ли 15 минут с начала урока
        $lessonStart = strtotime($lesson['time_start']);
        $currentTimestamp = time();
        $minutesSinceStart = ($currentTimestamp - strtotime(date('Y-m-d') . ' ' . $lesson['time_start'])) / 60;

        $status = '';
        $action = '';

        if ($minutesSinceStart < 0) {
            $status = "⏳ Еще не начался";
        } elseif ($minutesSinceStart >= 0 && $minutesSinceStart < 15) {
            $status = "▶️ Идет (прошло " . round($minutesSinceStart) . " мин)";
        } elseif ($minutesSinceStart >= 15 && $minutesSinceStart < 120) {
            $status = "<span class='success'>✅ Можно опросить</span>";

            // Проверяем, не опрашивали ли уже
            $existingPayment = dbQueryOne(
                "SELECT id FROM payments WHERE teacher_id = ? AND lesson_template_id = ? AND DATE(created_at) = ?",
                [$lesson['teacher_id'], $lesson['id'], date('Y-m-d')]
            );

            if ($existingPayment) {
                $action = "<span class='info'>Уже опрошен</span>";
            } else {
                $action = "<a class='btn' href='?send_query={$lesson['id']}'>Отправить опрос</a>";
            }
        } else {
            $status = "⏹️ Закончился";
        }

        echo "<tr>
            <td>{$lesson['id']}</td>
            <td>{$timeStart} - {$timeEnd}</td>
            <td>{$lesson['teacher_name']}</td>
            <td>{$subject}</td>
            <td>{$room}</td>
            <td>{$lesson['expected_students']}</td>
            <td>{$hasTelegram} " . ($lesson['telegram_id'] ?: 'Не указан') . "</td>
            <td>{$status}</td>
            <td>{$action}</td>
        </tr>";
    }

    echo "</table>";
}

// Обработка отправки опроса
if (isset($_GET['send_query'])) {
    $lessonId = filter_var($_GET['send_query'], FILTER_VALIDATE_INT);

    if ($lessonId) {
        echo "<h2>Отправка опроса</h2><pre>";

        $lesson = dbQueryOne(
            "SELECT lt.*, t.name as teacher_name, t.telegram_id
             FROM lessons_template lt
             JOIN teachers t ON lt.teacher_id = t.id
             WHERE lt.id = ?",
            [$lessonId]
        );

        if ($lesson && $lesson['telegram_id']) {
            // Включаем функцию из cron.php
            require_once __DIR__ . '/cron.php';
            sendAttendanceQuery($lesson);

            echo "<span class='success'>✅ Опрос отправлен преподавателю {$lesson['teacher_name']}</span>\n";
            echo "📱 Telegram ID: {$lesson['telegram_id']}\n";
            echo "\nПроверьте Telegram - должно прийти сообщение с кнопками:\n";
            echo "• ✅ Да, все пришли\n";
            echo "• ❌ Нет, есть отсутствующие\n";
        } else {
            echo "<span class='error'>❌ Урок не найден или у преподавателя нет Telegram ID</span>\n";
        }

        echo "</pre>";
        echo "<a class='btn' href='test_attendance.php'>← Назад к расписанию</a>";
    }
}

echo "<hr>";
echo "<h2>Инструкции</h2>";
echo "<ol style='line-height: 1.8;'>";
echo "<li><strong>Добавьте расписание:</strong> Если нет уроков, добавьте их через <a href='../schedule.php' style='color: #2196f3;'>Расписание</a></li>";
echo "<li><strong>Добавьте Telegram ID:</strong> Убедитесь, что у преподавателя указан Telegram ID в профиле</li>";
echo "<li><strong>Тестовая отправка:</strong> Нажмите кнопку \"Отправить опрос\" для урока, который прошел 15+ минут назад</li>";
echo "<li><strong>Настройка cron:</strong> Добавьте в cron на сервере:<br><code style='background: #2a2a2a; padding: 5px;'>*/5 * * * * php /home/c/cw95865/PALOMATIKA/public_html/zarplata/bot/cron.php</code></li>";
echo "<li><strong>Удалите этот файл</strong> после тестирования для безопасности</li>";
echo "</ol>";

echo "<hr>";
echo "<h2>Как работает система</h2>";
echo "<ol style='line-height: 1.8;'>";
echo "<li>Cron запускается каждые 5 минут</li>";
echo "<li>Находит уроки, которые начались 15 минут назад (±3 мин)</li>";
echo "<li>Проверяет, что урок еще не опрошен сегодня</li>";
echo "<li>Отправляет преподавателю сообщение с кнопками</li>";
echo "<li>Преподаватель нажимает кнопку (все пришли / есть отсутствующие)</li>";
echo "<li>Если есть отсутствующие - показывает кнопки с числами</li>";
echo "<li>Автоматически рассчитывается зарплата по формуле</li>";
echo "<li>Создается запись в таблице payments</li>";
echo "</ol>";
