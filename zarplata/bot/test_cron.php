<?php
/**
 * Тестовый скрипт для диагностики бота
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Bot Diagnostics ===\n\n";

// Подключаем конфиг
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../config/student_helpers.php';

echo "1. Testing database connection...\n";
try {
    $result = dbQueryOne("SELECT 1 as test", []);
    echo "   ✓ Database connection OK\n";
} catch (Exception $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n2. Checking bot token...\n";
$token = getBotToken();
if (empty($token)) {
    echo "   ✗ Bot token is EMPTY!\n";
} else {
    echo "   ✓ Bot token exists (length: " . strlen($token) . ")\n";
}

echo "\n3. Checking today's lessons...\n";
$dayOfWeek = date('N');
$currentTime = date('H:i:s');
echo "   Today is day of week: $dayOfWeek\n";
echo "   Current time: $currentTime\n";

$lessons = dbQuery(
    "SELECT lt.id, lt.time_start, lt.time_end, lt.expected_students, lt.subject,
            t.name as teacher_name, t.telegram_id, t.telegram_username
     FROM lessons_template lt
     JOIN teachers t ON lt.teacher_id = t.id
     WHERE lt.day_of_week = ?
       AND lt.active = 1
       AND t.active = 1
     ORDER BY lt.time_start",
    [$dayOfWeek]
);

echo "   Found " . count($lessons) . " lessons for today:\n";
foreach ($lessons as $lesson) {
    $hasBot = $lesson['telegram_id'] ? '✓' : '✗';
    echo "   - {$lesson['time_start']}: {$lesson['subject']} ({$lesson['teacher_name']}) ";
    echo "[TG: {$hasBot}] expected: {$lesson['expected_students']}\n";
}

echo "\n4. Checking students with schedules...\n";
$studentsWithSchedule = dbQuery(
    "SELECT id, name, teacher_id, schedule FROM students WHERE active = 1 AND schedule IS NOT NULL LIMIT 5",
    []
);
echo "   Sample of students with schedules:\n";
foreach ($studentsWithSchedule as $student) {
    echo "   - {$student['name']} (teacher_id: {$student['teacher_id']})\n";
    $schedule = json_decode($student['schedule'], true);
    if ($schedule) {
        foreach ($schedule as $day => $entries) {
            if (is_array($entries) && isset($entries[0])) {
                foreach ($entries as $entry) {
                    $subject = $entry['subject'] ?? 'no subject';
                    echo "     Day $day: {$entry['time']} - $subject\n";
                }
            }
        }
    }
}

echo "\n5. Testing getStudentsForLesson()...\n";
if (!empty($lessons)) {
    $testLesson = $lessons[0];
    $firstTeacher = dbQueryOne(
        "SELECT id FROM teachers WHERE id = ?",
        [$testLesson['teacher_id'] ?? 1]
    );

    if ($firstTeacher) {
        $studentsData = getStudentsForLesson(
            $firstTeacher['id'],
            $dayOfWeek,
            substr($testLesson['time_start'], 0, 5)
        );
        echo "   Teacher ID: {$firstTeacher['id']}\n";
        echo "   Time: " . substr($testLesson['time_start'], 0, 5) . "\n";
        echo "   Found students: {$studentsData['count']}\n";
        echo "   Subject from students: " . ($studentsData['subject'] ?? 'none') . "\n";
    }
}

echo "\n6. Checking audit_log for today's attendance queries...\n";
$today = date('Y-m-d');
$auditLogs = dbQuery(
    "SELECT * FROM audit_log
     WHERE action_type = 'attendance_query_sent'
       AND DATE(created_at) = ?
     ORDER BY created_at DESC
     LIMIT 10",
    [$today]
);
echo "   Found " . count($auditLogs) . " attendance queries sent today:\n";
foreach ($auditLogs as $log) {
    echo "   - {$log['created_at']}: entity_id={$log['entity_id']}\n";
}

echo "\n=== Diagnostics Complete ===\n";
