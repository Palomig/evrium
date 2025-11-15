<?php
/**
 * Скрипт для создания администратора
 * Запустите этот файл один раз через браузер: https://эвриум.рф/zarplata/create_admin.php
 */

require_once __DIR__ . '/config/db.php';

// Создаём правильный хеш для пароля admin123
$password = 'admin123';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Проверяем, существует ли пользователь admin
    $existing = dbQueryOne("SELECT id FROM users WHERE username = 'admin'", []);

    if ($existing) {
        // Обновляем существующего пользователя
        $result = dbExecute(
            "UPDATE users SET password_hash = ?, name = 'Администратор', role = 'owner', active = 1 WHERE username = 'admin'",
            [$passwordHash]
        );

        if ($result) {
            echo "<h1>✅ Успех!</h1>";
            echo "<p>Пользователь <strong>admin</strong> обновлён</p>";
            echo "<p><strong>Логин:</strong> admin</p>";
            echo "<p><strong>Пароль:</strong> admin123</p>";
            echo "<hr>";
            echo "<p><a href='/zarplata/login.php'>➡️ Перейти к входу</a></p>";
            echo "<hr>";
            echo "<p style='color: red;'><strong>ВАЖНО:</strong> Удалите файл create_admin.php после успешного входа!</p>";
        } else {
            echo "<h1>❌ Ошибка</h1>";
            echo "<p>Не удалось обновить пользователя</p>";
        }
    } else {
        // Создаём нового пользователя
        $userId = dbExecute(
            "INSERT INTO users (username, password_hash, name, role, active) VALUES (?, ?, ?, ?, ?)",
            ['admin', $passwordHash, 'Администратор', 'owner', 1]
        );

        if ($userId) {
            echo "<h1>✅ Успех!</h1>";
            echo "<p>Пользователь <strong>admin</strong> создан (ID: $userId)</p>";
            echo "<p><strong>Логин:</strong> admin</p>";
            echo "<p><strong>Пароль:</strong> admin123</p>";
            echo "<hr>";
            echo "<p><a href='/zarplata/login.php'>➡️ Перейти к входу</a></p>";
            echo "<hr>";
            echo "<p style='color: red;'><strong>ВАЖНО:</strong> Удалите файл create_admin.php после успешного входа!</p>";
        } else {
            echo "<h1>❌ Ошибка</h1>";
            echo "<p>Не удалось создать пользователя</p>";
        }
    }

    // Показываем отладочную информацию
    echo "<hr>";
    echo "<h3>Отладочная информация:</h3>";
    echo "<p><strong>База данных:</strong> " . DB_NAME . "</p>";
    echo "<p><strong>Пользователь БД:</strong> " . DB_USER . "</p>";
    echo "<p><strong>Подключение:</strong> OK ✅</p>";

    // Проверяем таблицы
    $tables = dbQuery("SHOW TABLES", []);
    echo "<p><strong>Таблиц в БД:</strong> " . count($tables) . "</p>";

} catch (Exception $e) {
    echo "<h1>❌ Ошибка</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<hr>";
    echo "<h3>Проверьте:</h3>";
    echo "<ul>";
    echo "<li>Импортирована ли база данных из database.sql?</li>";
    echo "<li>Правильные ли данные подключения в config/db.php?</li>";
    echo "<li>Существует ли таблица users?</li>";
    echo "</ul>";
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создание администратора</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 { color: #333; }
        p { line-height: 1.6; }
        a {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        a:hover { background: #45a049; }
    </style>
</head>
<body>
</body>
</html>
