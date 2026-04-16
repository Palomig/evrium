<?php
/**
 * API: Save or remove a Web Push subscription
 *
 * POST /zarplata/mobile/api/push_subscribe.php
 * Body (JSON):
 *   { "action": "subscribe",   "endpoint": "...", "p256dh": "...", "auth": "..." }
 *   { "action": "unsubscribe", "endpoint": "..." }
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json');

requireAuth();
$user = getCurrentUser();

$body = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

if ($action === 'subscribe') {
    $endpoint = trim($body['endpoint'] ?? '');
    $p256dh   = trim($body['p256dh']   ?? '');
    $auth     = trim($body['auth']     ?? '');

    if (!$endpoint || !$p256dh || !$auth) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing fields']);
        exit;
    }

    // Find teacher for this user: match by name or display_name, fall back to first active teacher
    $teacher = dbQueryOne(
        "SELECT id FROM teachers WHERE (name = ? OR display_name = ?) AND active = 1 LIMIT 1",
        [$user['name'], $user['name']]
    );

    if (!$teacher) {
        // Fall back to the first (owner's) active teacher
        $teacher = dbQueryOne("SELECT id FROM teachers WHERE active = 1 ORDER BY id LIMIT 1", []);
    }

    if (!$teacher) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No teacher found']);
        exit;
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Upsert: update if endpoint exists, insert if not
    $existing = dbQueryOne(
        "SELECT id, teacher_id FROM push_subscriptions WHERE endpoint = ?",
        [$endpoint]
    );

    if ($existing) {
        dbExecute(
            "UPDATE push_subscriptions SET p256dh = ?, auth = ?, user_agent = ?, updated_at = NOW() WHERE id = ?",
            [$p256dh, $auth, $userAgent, $existing['id']]
        );
    } else {
        dbExecute(
            "INSERT INTO push_subscriptions (teacher_id, endpoint, p256dh, auth, user_agent, lesson_notify)
             VALUES (?, ?, ?, ?, ?, 1)",
            [$teacher['id'], $endpoint, $p256dh, $auth, $userAgent]
        );
    }

    echo json_encode(['success' => true]);

} elseif ($action === 'unsubscribe') {
    $endpoint = trim($body['endpoint'] ?? '');

    if (!$endpoint) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing endpoint']);
        exit;
    }

    dbExecute("DELETE FROM push_subscriptions WHERE endpoint = ?", [$endpoint]);
    echo json_encode(['success' => true]);

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
