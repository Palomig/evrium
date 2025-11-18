<?php
/**
 * Тестовый скрипт для диагностики webhook
 * Запустите через браузер: https://эвриум.рф/zarplata/bot/test_webhook.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Telegram Bot Webhook Test</h1>";
echo "<pre>";

echo "=== Step 1: Checking config.php ===\n";
try {
    require_once __DIR__ . '/config.php';
    echo "✅ config.php loaded successfully\n";
} catch (Exception $e) {
    echo "❌ Error loading config.php: " . $e->getMessage() . "\n";
    exit;
}

echo "\n=== Step 2: Checking bot token ===\n";
$token = getBotToken();
if ($token) {
    echo "✅ Bot token found: " . substr($token, 0, 10) . "...\n";
} else {
    echo "❌ Bot token is empty!\n";
}

echo "\n=== Step 3: Testing database connection ===\n";
try {
    $teachers = dbQuery("SELECT COUNT(*) as count FROM teachers", []);
    echo "✅ Database connected, teachers count: " . $teachers[0]['count'] . "\n";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Step 4: Checking functions ===\n";
$functions = [
    'getMainMenuKeyboard',
    'plural',
    'sendTelegramMessage',
    'getTeacherByTelegramId',
    'dbQuery',
    'dbExecute'
];

foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✅ Function '$func' exists\n";
    } else {
        echo "❌ Function '$func' NOT FOUND\n";
    }
}

echo "\n=== Step 5: Testing handlers ===\n";
$handlers = [
    'StartCommand.php',
    'TodayCommand.php',
    'WeekCommand.php',
    'ScheduleCommand.php'
];

foreach ($handlers as $handler) {
    $path = __DIR__ . '/handlers/' . $handler;
    if (file_exists($path)) {
        try {
            require_once $path;
            echo "✅ Handler '$handler' loaded\n";
        } catch (Exception $e) {
            echo "❌ Handler '$handler' error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Handler '$handler' NOT FOUND\n";
    }
}

echo "\n=== Step 6: Testing getMainMenuKeyboard() ===\n";
try {
    $keyboard = getMainMenuKeyboard();
    echo "✅ Keyboard generated:\n";
    print_r($keyboard);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Step 7: Simulating /start command ===\n";
try {
    ob_start();
    // Не отправляем реальное сообщение, просто проверяем, что функция работает
    echo "Testing handleStartCommand function...\n";
    if (function_exists('handleStartCommand')) {
        echo "✅ handleStartCommand exists\n";
    } else {
        echo "❌ handleStartCommand NOT FOUND\n";
    }
    ob_end_flush();
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== All tests completed ===\n";
echo "</pre>";

echo "<hr>";
echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>If all tests passed, go to <a href='../settings.php'>Settings</a> and click 'Настроить webhook'</li>";
echo "<li>Check error logs on server: /PALOMATIKA/public_html/zarplata/error_log</li>";
echo "<li>Send /start to the bot and check if it responds</li>";
echo "</ol>";
