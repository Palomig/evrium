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
 *   POST ?action=artisan        — НЕ поддерживается (заглушка), т.к. zarplata без Laravel
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

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
    default:
        jsonError("Unknown action: {$action}. Allowed: tables|query|describe", 400);
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
