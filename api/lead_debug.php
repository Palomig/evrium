<?php
/**
 * ВРЕМЕННЫЙ диагностический скрипт доставки заявок в Telegram (@evrium_bot).
 * Удалить после проверки. Доступ — только с секретом ?key=...
 *
 * Что делает:
 *   1) Читает bot_token и admin_telegram_chat_id из таблицы settings (БД zarplata).
 *   2) Проверяет токен через Telegram getMe.
 *   3) Пробует реально отправить тестовое сообщение и возвращает ответ Telegram API.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

const DEBUG_KEY = '5c67491bacf4f41c';
if (($_GET['key'] ?? '') !== DEBUG_KEY) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$out = ['steps' => []];

// --- 1. Чтение настроек из БД -------------------------------------------
$dbConfig = __DIR__ . '/../zarplata/config/db.php';
$out['steps']['db_config_file'] = is_file($dbConfig);

$token = null;
$chatId = null;
if (is_file($dbConfig)) {
    require_once $dbConfig;
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 3]
        );
        $stmt = $pdo->prepare(
            "SELECT setting_key, setting_value FROM settings
             WHERE setting_key IN ('bot_token', 'admin_telegram_chat_id')"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $out['steps']['db_connect'] = 'ok';
        foreach ($rows as $r) {
            if ($r['setting_key'] === 'bot_token') $token = $r['setting_value'];
            if ($r['setting_key'] === 'admin_telegram_chat_id') $chatId = $r['setting_value'];
        }
        // Все ключи в settings — чтобы увидеть, как реально называется chat_id, если отличается.
        $all = $pdo->query("SELECT setting_key FROM settings ORDER BY setting_key")->fetchAll(PDO::FETCH_COLUMN);
        $out['settings_keys'] = $all;
    } catch (Throwable $e) {
        $out['steps']['db_connect'] = 'FAIL: ' . $e->getMessage();
    }
}

$out['bot_token_present'] = !empty($token);
$out['bot_token_masked']  = $token ? (substr($token, 0, 6) . '…' . substr($token, -4) . ' (len=' . strlen($token) . ')') : null;
$out['admin_chat_id']     = $chatId;     // ID не секрет — показываем как есть для проверки.

// --- helper --------------------------------------------------------------
$tg = static function (string $method, array $data) use ($token): array {
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($data),
        'timeout' => 8,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents("https://api.telegram.org/bot{$token}/{$method}", false, $ctx);
    return ['raw' => $raw, 'decoded' => json_decode((string)$raw, true)];
};

// --- 2. getMe ------------------------------------------------------------
if ($token) {
    $me = $tg('getMe', []);
    $out['getMe'] = $me['decoded'] ?? $me['raw'];
} else {
    $out['getMe'] = 'skipped: no token';
}

// --- 3. Тестовая отправка ------------------------------------------------
if ($token && $chatId) {
    $send = $tg('sendMessage', [
        'chat_id' => $chatId,
        'text' => '🔧 Диагностика доставки заявок: тест от lead_debug.php',
        'parse_mode' => 'HTML',
    ]);
    $out['sendMessage'] = $send['decoded'] ?? $send['raw'];
} else {
    $out['sendMessage'] = 'skipped: token or chat_id missing';
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
