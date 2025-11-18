<?php
/**
 * Скрипт для полного сброса webhook
 * Удаляет webhook, очищает очередь, затем устанавливает заново
 */

require_once __DIR__ . '/../config/db.php';

// Получаем токен из БД
$setting = dbQueryOne("SELECT setting_value FROM settings WHERE setting_key = 'bot_token'", []);
$token = $setting['setting_value'] ?? '';

if (empty($token)) {
    die("❌ Bot token not found in database!");
}

echo "<h1>Telegram Webhook Reset</h1>";
echo "<pre>";

// Шаг 1: Удаляем webhook
echo "=== Step 1: Deleting webhook ===\n";
$url = "https://api.telegram.org/bot{$token}/deleteWebhook?drop_pending_updates=true";
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['ok']) {
    echo "✅ Webhook deleted, pending updates dropped\n";
} else {
    echo "❌ Error: " . ($data['description'] ?? 'Unknown error') . "\n";
}

sleep(1);

// Шаг 2: Проверяем статус
echo "\n=== Step 2: Checking webhook info ===\n";
$url = "https://api.telegram.org/bot{$token}/getWebhookInfo";
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['ok']) {
    $info = $data['result'];
    echo "URL: " . ($info['url'] ?: '(not set)') . "\n";
    echo "Pending updates: " . $info['pending_update_count'] . "\n";
    if (isset($info['last_error_message'])) {
        echo "Last error: " . $info['last_error_message'] . "\n";
    }
}

sleep(1);

// Шаг 3: Устанавливаем webhook заново
echo "\n=== Step 3: Setting webhook ===\n";
$webhookUrl = 'https://эвриум.рф/zarplata/bot/webhook.php';
$url = "https://api.telegram.org/bot{$token}/setWebhook";

$postData = json_encode([
    'url' => $webhookUrl,
    'drop_pending_updates' => true, // Очищаем очередь
    'allowed_updates' => ['message', 'callback_query']
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($data['ok']) {
    echo "✅ Webhook set successfully to: $webhookUrl\n";
} else {
    echo "❌ Error: " . ($data['description'] ?? 'Unknown error') . "\n";
}

sleep(1);

// Шаг 4: Финальная проверка
echo "\n=== Step 4: Final verification ===\n";
$url = "https://api.telegram.org/bot{$token}/getWebhookInfo";
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['ok']) {
    $info = $data['result'];
    echo "✅ Webhook URL: " . $info['url'] . "\n";
    echo "✅ Pending updates: " . $info['pending_update_count'] . "\n";

    if (isset($info['last_error_message'])) {
        echo "⚠️ Last error: " . $info['last_error_message'] . "\n";
        echo "   (This error is from before the reset, should be clear after first message)\n";
    } else {
        echo "✅ No errors\n";
    }
}

echo "\n=== Reset completed ===\n";
echo "</pre>";

echo "<hr>";
echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li><strong>Open Telegram</strong> and send <code>/start</code> to your bot</li>";
echo "<li>Bot should respond with keyboard buttons</li>";
echo "<li>If bot doesn't respond, check <a href='test_webhook.php'>test_webhook.php</a></li>";
echo "<li><strong>Delete this file</strong> after testing for security</li>";
echo "</ol>";
