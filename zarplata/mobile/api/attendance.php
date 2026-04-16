<?php
/**
 * API: Load or save lesson attendance
 *
 * GET  ?lesson_instance_id=123          → return attendance array
 * POST { "lesson_instance_id": 123, "student_id": 45, "attended": true }
 *      → upsert attendance record
 * POST { "lesson_instance_id": 123, "complete": true }
 *      → mark lesson as completed
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/student_helpers.php';

header('Content-Type: application/json');

requireAuth();
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $lessonId = (int)($_GET['lesson_instance_id'] ?? 0);

    if (!$lessonId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing lesson_instance_id']);
        exit;
    }

    $rows = dbQuery(
        "SELECT student_id, attended FROM attendance_log WHERE lesson_instance_id = ?",
        [$lessonId]
    );

    $attendance = [];
    foreach ($rows as $row) {
        $attendance[$row['student_id']] = (bool)$row['attended'];
    }

    echo json_encode(['success' => true, 'attendance' => $attendance]);
    exit;
}

// POST
$body = json_decode(file_get_contents('php://input'), true);
$lessonId = (int)($body['lesson_instance_id'] ?? 0);

if (!$lessonId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing lesson_instance_id']);
    exit;
}

// Mark lesson complete
if (!empty($body['complete'])) {
    // Count actual attendees
    $count = dbQueryOne(
        "SELECT COUNT(*) as cnt FROM attendance_log WHERE lesson_instance_id = ? AND attended = 1",
        [$lessonId]
    );
    $actual = $count['cnt'] ?? 0;

    dbExecute(
        "UPDATE lessons_instance SET status = 'completed', actual_students = ?, updated_at = NOW() WHERE id = ?",
        [$actual, $lessonId]
    );

    echo json_encode(['success' => true, 'actual_students' => $actual]);
    exit;
}

// Toggle single student attendance
$studentId = (int)($body['student_id'] ?? 0);
if (!$studentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing student_id']);
    exit;
}

$attended = !empty($body['attended']) ? 1 : 0;

$existing = dbQueryOne(
    "SELECT id FROM attendance_log WHERE lesson_instance_id = ? AND student_id = ?",
    [$lessonId, $studentId]
);

if ($existing) {
    dbExecute(
        "UPDATE attendance_log SET attended = ?, marked_at = NOW(), marked_by = 'pwa' WHERE id = ?",
        [$attended, $existing['id']]
    );
} else {
    dbExecute(
        "INSERT INTO attendance_log (lesson_instance_id, student_id, attended, marked_by) VALUES (?, ?, ?, 'pwa')",
        [$lessonId, $studentId, $attended]
    );
}

// Update actual_students count in lesson
$count = dbQueryOne(
    "SELECT COUNT(*) as cnt FROM attendance_log WHERE lesson_instance_id = ? AND attended = 1",
    [$lessonId]
);
dbExecute(
    "UPDATE lessons_instance SET actual_students = ?, updated_at = NOW() WHERE id = ?",
    [$count['cnt'] ?? 0, $lessonId]
);

echo json_encode(['success' => true]);
