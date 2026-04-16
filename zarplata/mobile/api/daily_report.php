<?php
/**
 * API: Send daily absence report via Telegram
 *
 * POST /zarplata/mobile/api/daily_report.php
 * Body: { "date": "2026-04-16" }  (optional, defaults to today)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../bot/config.php';

header('Content-Type: application/json');

requireAuth();
$user = getCurrentUser();

$body = json_decode(file_get_contents('php://input'), true);
$date = $body['date'] ?? date('Y-m-d');

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid date format']);
    exit;
}

// Get all completed lessons for the day
$lessons = dbQuery(
    "SELECT li.id, li.teacher_id, li.time_start, li.time_end, li.actual_students,
            li.expected_students, li.subject,
            COALESCE(t.display_name, t.name) as teacher_name, t.telegram_id
     FROM lessons_instance li
     LEFT JOIN teachers t ON li.teacher_id = t.id
     WHERE li.lesson_date = ? AND li.status IN ('completed', 'scheduled')
     ORDER BY li.time_start ASC",
    [$date]
);

if (empty($lessons)) {
    echo json_encode(['success' => true, 'message' => 'Нет уроков за ' . $date, 'sent' => 0]);
    exit;
}

// Build per-teacher report
$teacherReports = [];

foreach ($lessons as $lesson) {
    $teacherId = $lesson['teacher_id'];

    if (!isset($teacherReports[$teacherId])) {
        $teacherReports[$teacherId] = [
            'name'        => $lesson['teacher_name'],
            'telegram_id' => $lesson['telegram_id'],
            'lessons'     => [],
        ];
    }

    // Get absent students
    $absentStudents = dbQuery(
        "SELECT s.name, s.class
         FROM attendance_log al
         JOIN students s ON al.student_id = s.id
         WHERE al.lesson_instance_id = ? AND al.attended = 0
         ORDER BY s.name",
        [$lesson['id']]
    );

    $presentStudents = dbQuery(
        "SELECT s.name
         FROM attendance_log al
         JOIN students s ON al.student_id = s.id
         WHERE al.lesson_instance_id = ? AND al.attended = 1
         ORDER BY s.name",
        [$lesson['id']]
    );

    $teacherReports[$teacherId]['lessons'][] = [
        'time'    => substr($lesson['time_start'], 0, 5),
        'subject' => $lesson['subject'] ?? 'Урок',
        'present' => array_column($presentStudents, 'name'),
        'absent'  => array_column($absentStudents, 'name'),
        'actual'  => (int)$lesson['actual_students'],
        'expected'=> (int)$lesson['expected_students'],
    ];
}

$sentCount = 0;

foreach ($teacherReports as $teacherId => $report) {
    if (!$report['telegram_id']) continue;

    // Format date nicely in Russian
    $dateObj   = new DateTime($date);
    $dayNames  = ['вс','пн','вт','ср','чт','пт','сб'];
    $dayOfWeek = $dayNames[(int)$dateObj->format('w')];
    $dateFormatted = $dateObj->format('d.m') . ' (' . $dayOfWeek . ')';

    $lines   = ["📊 <b>Отчёт за {$dateFormatted}</b>", ''];

    $totalPresent = 0;
    $totalExpected = 0;

    foreach ($report['lessons'] as $lesson) {
        $lines[] = "🕐 <b>{$lesson['time']} — {$lesson['subject']}</b>";

        if (!empty($lesson['present'])) {
            $lines[] = '✅ Были: ' . implode(', ', $lesson['present']);
        }
        if (!empty($lesson['absent'])) {
            $lines[] = '❌ Отсутствовали: ' . implode(', ', $lesson['absent']);
        }
        if (empty($lesson['present']) && empty($lesson['absent'])) {
            $lines[] = '⚠️ Посещаемость не отмечена';
        }

        $totalPresent  += $lesson['actual'];
        $totalExpected += $lesson['expected'];
        $lines[] = '';
    }

    if ($totalExpected > 0) {
        $lines[] = "Итого: {$totalPresent}/{$totalExpected} учеников пришли";
    }

    $text = implode("\n", $lines);

    $sent = sendTelegramMessage($report['telegram_id'], $text);
    if ($sent) {
        $sentCount++;
    }
}

echo json_encode([
    'success'  => true,
    'date'     => $date,
    'teachers' => count($teacherReports),
    'sent'     => $sentCount,
]);
