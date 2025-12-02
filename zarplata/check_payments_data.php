<?php
/**
 * Диагностика данных для страницы Выплаты
 */

require_once __DIR__ . '/config/db.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Диагностика</title>";
echo "<style>body{font-family:monospace;background:#1a1a1a;color:#fff;padding:20px;}";
echo ".section{background:#2a2a2a;padding:15px;margin:15px 0;border-radius:5px;}";
echo ".ok{color:#10b981;}.error{color:#ef4444;}.warning{color:#f59e0b;}";
echo "table{border-collapse:collapse;width:100%;margin-top:10px;}";
echo "th,td{padding:8px;text-align:left;border:1px solid #444;}th{background:#333;}";
echo "pre{background:#0a0a0a;padding:10px;border-radius:5px;overflow-x:auto;}</style></head><body>";
echo "<h1>🔍 Диагностика данных для страницы Выплаты</h1>";

// 1. lessons_instance
echo "<div class='section'><h2>1. Таблица lessons_instance</h2>";
$lessons = dbQuery("SELECT COUNT(*) as count FROM lessons_instance", []);
$count = $lessons[0]['count'] ?? 0;
echo "<p class='" . ($count > 0 ? 'ok' : 'error') . "'>Всего уроков: <strong>$count</strong></p>";

if ($count > 0) {
    $statuses = dbQuery("SELECT status, COUNT(*) as count FROM lessons_instance GROUP BY status", []);
    echo "<p>По статусам:</p><ul>";
    foreach ($statuses as $s) echo "<li>{$s['status']}: {$s['count']}</li>";
    echo "</ul>";
}
echo "</div>";

// 2. payments
echo "<div class='section'><h2>2. Таблица payments</h2>";
$payments = dbQuery("SELECT COUNT(*) as count FROM payments", []);
$count = $payments[0]['count'] ?? 0;
echo "<p class='" . ($count > 0 ? 'ok' : 'error') . "'>Всего выплат: <strong>$count</strong></p>";

if ($count > 0) {
    $recent = dbQuery("SELECT id, lesson_instance_id, amount, status FROM payments ORDER BY created_at DESC LIMIT 5", []);
    echo "<table><tr><th>ID</th><th>Lesson ID</th><th>Сумма</th><th>Статус</th></tr>";
    foreach ($recent as $r) {
        echo "<tr><td>{$r['id']}</td><td>{$r['lesson_instance_id']}</td><td>{$r['amount']}₽</td><td>{$r['status']}</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// 3. Связь
echo "<div class='section'><h2>3. Уроки с выплатами</h2>";
$joined = dbQuery(
    "SELECT COUNT(DISTINCT li.id) as lessons_count, COUNT(DISTINCT p.id) as payments_count
     FROM lessons_instance li LEFT JOIN payments p ON li.id = p.lesson_instance_id",
    []
);
$data = $joined[0];
echo "<p>Уроков: {$data['lessons_count']}, Выплат: {$data['payments_count']}</p>";

$withPayments = dbQuery(
    "SELECT li.id, li.lesson_date, li.subject, p.amount
     FROM lessons_instance li INNER JOIN payments p ON li.id = p.lesson_instance_id
     ORDER BY li.lesson_date DESC LIMIT 5",
    []
);
echo "<p class='" . (count($withPayments) > 0 ? 'ok' : 'error') . "'>Уроков с выплатами: <strong>" . count($withPayments) . "</strong></p>";
if (count($withPayments) > 0) {
    echo "<table><tr><th>ID</th><th>Дата</th><th>Предмет</th><th>Сумма</th></tr>";
    foreach ($withPayments as $w) {
        echo "<tr><td>{$w['id']}</td><td>{$w['lesson_date']}</td><td>" . ($w['subject'] ?: '—') . "</td><td>{$w['amount']}₽</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// 4. Тест запроса из payments.php
echo "<div class='section'><h2>4. Тест запроса из payments.php</h2>";
$testResults = dbQuery(
    "SELECT li.id, li.lesson_date, li.time_start, p.amount
     FROM lessons_instance li
     LEFT JOIN payments p ON li.id = p.lesson_instance_id
     WHERE p.id IS NOT NULL AND li.lesson_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
     LIMIT 10",
    []
);
echo "<p class='" . (count($testResults) > 0 ? 'ok' : 'error') . "'>Результатов: <strong>" . count($testResults) . "</strong></p>";

if (count($testResults) > 0) {
    echo "<table><tr><th>ID</th><th>Дата</th><th>Время</th><th>Сумма</th></tr>";
    foreach ($testResults as $t) {
        echo "<tr><td>{$t['id']}</td><td>{$t['lesson_date']}</td><td>{$t['time_start']}</td><td>{$t['amount']}₽</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠️ Нет уроков с выплатами за последние 3 месяца!</p>";
}
echo "</div>";

echo "</body></html>";
