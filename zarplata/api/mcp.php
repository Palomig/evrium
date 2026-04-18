<?php
/**
 * MCP API — read-only доступ к БД zarplata для Claude Code MCP-клиента.
 *
 * Авторизация: заголовок `X-Mcp-Secret: <secret>`.
 * Секрет хранится в таблице settings (ключ `mcp_secret`) и задаётся один раз
 * через /zarplata/setup_mcp.php.
 *
 * Endpoints (передаётся через ?action=):
 *   GET  ?action=tables         — список таблиц + row counts
 *   POST ?action=query          — body {sql, limit}; только SELECT/SHOW/DESCRIBE/EXPLAIN
 *   POST ?action=describe       — body {table}; DESCRIBE `table`
 *   GET  ?action=log&lines=N&file=cron_debug — хвост лог-файла (whitelisted)
 *   POST ?action=push_test      — body {teacher_id?, title?, body?} — отправить тестовый push
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/web_push.php';

header('Content-Type: application/json; charset=utf-8');

// ═══════════════════════════════════════════════════════════════════════════
// Авторизация
// ═══════════════════════════════════════════════════════════════════════════

$providedSecret = $_SERVER['HTTP_X_MCP_SECRET'] ?? '';
$storedSecret   = (string) getSetting('mcp_secret', '');

if ($storedSecret === '') {
    jsonError('MCP not configured. Run /zarplata/setup_mcp.php first.', 503);
}

if ($providedSecret === '' || !hash_equals($storedSecret, $providedSecret)) {
    jsonError('Unauthorized', 401);
}

// ═══════════════════════════════════════════════════════════════════════════
// Маршрутизация
// ═══════════════════════════════════════════════════════════════════════════

$action = $_GET['action'] ?? '';
$body   = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw ?: '[]', true) ?: [];
}

switch ($action) {
    case 'tables':
        handleTables();
        break;
    case 'query':
        handleQuery($body);
        break;
    case 'describe':
        handleDescribe($body);
        break;
    case 'log':
        handleLog();
        break;
    case 'push_test':
        handlePushTest($body);
        break;
    default:
        jsonError("Unknown action: {$action}. Allowed: tables|query|describe|log|push_test", 400);
}

// ═══════════════════════════════════════════════════════════════════════════
// Handlers
// ═══════════════════════════════════════════════════════════════════════════

function handleTables(): void {
    try {
        $pdo = getDB();
        $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);

        $result = [];
        foreach ($rows as $row) {
            $name = $row[0];
            $stmt = $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $name) . '`');
            $count = (int) $stmt->fetchColumn();
            $result[] = ['table' => $name, 'rows' => $count];
        }

        jsonResponse(['success' => true, 'tables' => $result], 200);
    } catch (Throwable $e) {
        jsonError($e->getMessage(), 500);
    }
}

function handleQuery(array $body): void {
    $sql   = trim((string) ($body['sql'] ?? ''));
    $limit = (int) ($body['limit'] ?? 100);
    if ($limit < 1)  $limit = 100;
    if ($limit > 1000) $limit = 1000;

    if ($sql === '') {
        jsonError('sql is required', 400);
    }

    // Только SELECT/SHOW/DESCRIBE/EXPLAIN
    $firstWord = strtoupper((string) strtok($sql, " \t\n\r"));
    $allowed = ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'];
    if (!in_array($firstWord, $allowed, true)) {
        jsonError('Only read-only queries allowed (SELECT/SHOW/DESCRIBE/EXPLAIN)', 403);
    }

    // Блокируем мутирующие ключевые слова в любом месте запроса
    $upper = strtoupper($sql);
    $dangerous = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE',
                  'CREATE', 'GRANT', 'REVOKE', 'REPLACE', 'RENAME', 'LOAD', 'CALL'];
    foreach ($dangerous as $kw) {
        if (preg_match('/\b' . $kw . '\b/', $upper)) {
            jsonError("Forbidden keyword: {$kw}", 403);
        }
    }

    // Добавляем LIMIT для SELECT без LIMIT
    if ($firstWord === 'SELECT' && !preg_match('/\bLIMIT\b/i', $sql)) {
        $sql = rtrim($sql, "; \t\n\r") . " LIMIT {$limit}";
    }

    try {
        $t0 = microtime(true);
        $rows = dbQuery($sql);
        $elapsed = round(microtime(true) - $t0, 3);

        jsonResponse([
            'success' => true,
            'rows'    => $rows,
            'count'   => count($rows),
            'elapsed' => "{$elapsed}s",
        ], 200);
    } catch (Throwable $e) {
        jsonError($e->getMessage(), 500);
    }
}

function handleDescribe(array $body): void {
    $table = trim((string) ($body['table'] ?? ''));
    if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        jsonError('Invalid table name', 400);
    }

    try {
        $rows = dbQuery("DESCRIBE `{$table}`");
        jsonResponse(['success' => true, 'columns' => $rows], 200);
    } catch (Throwable $e) {
        jsonError($e->getMessage(), 500);
    }
}

/**
 * Read tail of a whitelisted log file.
 *
 * Whitelist: cron_debug, bot_errors, php_error
 */
function handleLog(): void {
    $fileKey = $_GET['file'] ?? 'cron_debug';
    $lines   = max(1, min(500, (int) ($_GET['lines'] ?? 100)));

    $whitelist = [
        'cron_debug' => __DIR__ . '/../bot/cron_debug.log',
        'bot_errors' => __DIR__ . '/../bot/errors.log',
        'php_error'  => __DIR__ . '/../../error_log',
    ];

    if (!isset($whitelist[$fileKey])) {
        jsonError("Unknown log file. Allowed: " . implode(', ', array_keys($whitelist)), 400);
    }

    $path = $whitelist[$fileKey];
    if (!file_exists($path)) {
        jsonResponse(['success' => true, 'file' => $fileKey, 'path' => $path, 'exists' => false, 'lines' => []], 200);
    }

    // Efficient tail: read whole file only if <= 2 MB; otherwise read last chunk
    $size = filesize($path);
    if ($size <= 2 * 1024 * 1024) {
        $content = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $tail = array_slice($content, -$lines);
    } else {
        // Read last 256 KB
        $fh = fopen($path, 'rb');
        fseek($fh, -min(256 * 1024, $size), SEEK_END);
        $chunk = fread($fh, 256 * 1024);
        fclose($fh);
        $arr = explode("\n", trim($chunk));
        $tail = array_slice($arr, -$lines);
    }

    jsonResponse([
        'success' => true,
        'file'    => $fileKey,
        'path'    => $path,
        'size'    => $size,
        'lines'   => $tail,
        'count'   => count($tail),
    ], 200);
}

/**
 * Send a test Web Push to the given teacher's subscriptions.
 *
 * body: { teacher_id?: int, title?: string, body?: string }
 * defaults: teacher_id = first subscription in DB, title = "Тест", body = "Проверка уведомлений"
 */
function handlePushTest(array $body): void {
    $teacherId = (int) ($body['teacher_id'] ?? 0);
    $title     = (string) ($body['title'] ?? 'Тест PALOMATIKA');
    $bodyText  = (string) ($body['body']  ?? 'Проверка Web Push (MCP)');

    // Load VAPID
    $pub  = (string) getSetting('vapid_public_key', '');
    $priv = (string) getSetting('vapid_private_key', '');
    $sub  = (string) getSetting('vapid_subject', 'mailto:admin@evrium.ru');

    if ($pub === '' || $priv === '') {
        jsonError('VAPID keys not configured', 503);
    }

    // Fetch subscriptions
    if ($teacherId > 0) {
        $subs = dbQuery(
            "SELECT id, teacher_id, endpoint, p256dh, auth FROM push_subscriptions WHERE teacher_id = ? AND lesson_notify = 1",
            [$teacherId]
        );
    } else {
        $subs = dbQuery(
            "SELECT id, teacher_id, endpoint, p256dh, auth FROM push_subscriptions WHERE lesson_notify = 1 ORDER BY id LIMIT 10",
            []
        );
    }

    if (empty($subs)) {
        jsonResponse(['success' => false, 'error' => 'No subscriptions found', 'teacher_id' => $teacherId], 404);
    }

    $push = new VapidPush($pub, $priv, $sub);

    $payload = [
        'title' => $title,
        'body'  => $bodyText,
        'url'   => '/zarplata/mobile/lessons.php',
        'icon'  => '/zarplata/mobile/assets/icons/icon-192x192.png',
        'badge' => '/zarplata/mobile/assets/icons/icon-72x72.png',
    ];

    // ─── Detailed per-subscription report via cURL (so we see HTTP status) ───
    $results = [];
    foreach ($subs as $s) {
        $r = sendDetailedPush($push, $pub, $priv, $sub, $s, $payload);
        $results[] = [
            'subscription_id' => $s['id'],
            'teacher_id'      => $s['teacher_id'],
            'endpoint_host'   => parse_url($s['endpoint'], PHP_URL_HOST),
            'status'          => $r['status'],
            'ok'              => $r['ok'],
            'error'           => $r['error'],
            'response'        => $r['response'],
        ];
    }

    jsonResponse([
        'success' => true,
        'sent_to' => count($subs),
        'results' => $results,
    ], 200);
}

/**
 * Send a push via VapidPush but also capture HTTP status + response body.
 * Reuses the encrypt() and createJwt() paths via reflection-free duplication.
 *
 * Returns: ['status' => int, 'ok' => bool, 'error' => ?string, 'response' => ?string]
 */
function sendDetailedPush(VapidPush $push, string $pub, string $priv, string $subject,
                          array $subscription, array $payload): array {
    // We re-implement the send() here so we can capture the response details.
    // The encryption/JWT work uses VapidPush's private methods — so we call ->send()
    // and additionally capture error_log via a temporary handler. Simpler: duplicate
    // the curl call path using a helper method we add below.
    try {
        // Use a simple wrapper exposed on VapidPush — we'll add sendDetailed() there.
        if (method_exists($push, 'sendDetailed')) {
            return $push->sendDetailed($subscription, $payload);
        }
        // Fallback: just call send() and report bool
        $ok = $push->send($subscription, $payload);
        return ['status' => $ok ? 201 : 0, 'ok' => $ok, 'error' => $ok ? null : 'see server error_log', 'response' => null];
    } catch (Throwable $e) {
        return ['status' => 0, 'ok' => false, 'error' => $e->getMessage(), 'response' => null];
    }
}
