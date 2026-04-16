<?php
/**
 * Cron: Send push notifications before lessons
 *
 * Runs every minute. Sends a push to teachers who subscribed
 * when a lesson starts in 10 minutes (±1 min window).
 *
 * Crontab: * * * * * php /home/c/cw95865/PALOMATIKA/public_html/zarplata/cron/send_lesson_notifications.php
 */

ob_start();

$debugLog = __DIR__ . '/push_cron_debug.log';
file_put_contents($debugLog, date('Y-m-d H:i:s') . " cron started\n", FILE_APPEND);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/web_push.php';
require_once __DIR__ . '/../config/student_helpers.php';

$today      = date('Y-m-d');
$dayOfWeek  = (int)date('N');
$now        = date('H:i');
$nowSec     = time();

// Target: lessons starting in 9–11 minutes
$notifyMinutes = 10;
$targetTime = date('H:i', $nowSec + $notifyMinutes * 60);
$windowStart = date('H:i', $nowSec + ($notifyMinutes - 1) * 60);
$windowEnd   = date('H:i', $nowSec + ($notifyMinutes + 1) * 60);

error_log("[PUSH CRON] Running at {$now}, looking for lessons starting around {$targetTime} (window {$windowStart}–{$windowEnd})");

// Get VAPID keys from settings
$vapidPublic  = '';
$vapidPrivate = '';
$vapidSubject = 'mailto:admin@evrium.ru';

try {
    $rows = dbQuery(
        "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('vapid_public_key','vapid_private_key','vapid_subject')",
        []
    );
    foreach ($rows as $row) {
        if ($row['setting_key'] === 'vapid_public_key')  $vapidPublic  = $row['setting_value'];
        if ($row['setting_key'] === 'vapid_private_key') $vapidPrivate = $row['setting_value'];
        if ($row['setting_key'] === 'vapid_subject')     $vapidSubject = $row['setting_value'];
    }
} catch (Exception $e) {
    error_log('[PUSH CRON] Failed to load VAPID keys: ' . $e->getMessage());
    exit;
}

if (!$vapidPublic || !$vapidPrivate) {
    error_log('[PUSH CRON] VAPID keys not configured — skip');
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " VAPID not configured\n", FILE_APPEND);
    exit;
}

$push = new VapidPush($vapidPublic, $vapidPrivate, $vapidSubject);

// Find lessons starting in the target window
$lessons = dbQuery(
    "SELECT li.id, li.teacher_id, li.time_start, li.subject, li.expected_students,
            COALESCE(t.display_name, t.name) as teacher_name
     FROM lessons_instance li
     LEFT JOIN teachers t ON li.teacher_id = t.id
     WHERE li.lesson_date = ?
       AND li.status = 'scheduled'
       AND TIME(li.time_start) BETWEEN ? AND ?",
    [$today, $windowStart . ':00', $windowEnd . ':59']
);

if (empty($lessons)) {
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " no lessons in window\n", FILE_APPEND);
    exit;
}

error_log('[PUSH CRON] Found ' . count($lessons) . ' lesson(s) to notify');

foreach ($lessons as $lesson) {
    $teacherId  = $lesson['teacher_id'];
    $time       = substr($lesson['time_start'], 0, 5);
    $subject    = $lesson['subject'] ?? 'Урок';

    // Get students for this lesson
    $sd = getStudentsForLesson($teacherId, $dayOfWeek, $time);
    $studentCount = $sd['count'];

    // Build notification payload
    $lessonUrl = '/zarplata/mobile/lessons.php?date=' . $today;
    $payload = [
        'title' => "Урок через {$notifyMinutes} минут",
        'body'  => "{$time} — {$subject}" . ($studentCount > 0 ? " ({$studentCount} уч.)" : ''),
        'url'   => $lessonUrl,
        'icon'  => '/zarplata/mobile/assets/icons/icon-192x192.png',
        'badge' => '/zarplata/mobile/assets/icons/icon-72x72.png',
    ];

    // Get all push subscriptions for this teacher
    $subs = dbQuery(
        "SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE teacher_id = ? AND lesson_notify = 1",
        [$teacherId]
    );

    if (empty($subs)) {
        error_log("[PUSH CRON] No push subscriptions for teacher {$teacherId}");
        continue;
    }

    $sentCount = 0;
    $deadSubs  = [];

    foreach ($subs as $sub) {
        $ok = $push->send($sub, $payload);
        if ($ok) {
            $sentCount++;
        } else {
            // Mark dead subscriptions for cleanup (410 Gone etc.)
            $deadSubs[] = $sub['endpoint'];
        }
    }

    // Remove expired subscriptions
    foreach ($deadSubs as $endpoint) {
        dbExecute("DELETE FROM push_subscriptions WHERE endpoint = ?", [$endpoint]);
        error_log("[PUSH CRON] Removed expired subscription for teacher {$teacherId}");
    }

    error_log("[PUSH CRON] Teacher {$teacherId} ({$lesson['teacher_name']}): sent {$sentCount}/" . count($subs) . " notifications");
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " sent {$sentCount} to teacher {$teacherId}\n", FILE_APPEND);
}

ob_end_clean();
