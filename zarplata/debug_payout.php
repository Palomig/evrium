<?php
/**
 * Диагностический скрипт для проверки выплат
 */

require_once __DIR__ . '/config/db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Диагностика выплат</h1>";

// 1. Все записи с payment_type = 'payout'
echo "<h2>1. Записи с payment_type = 'payout'</h2>";
$payouts = dbQuery("SELECT * FROM payments WHERE payment_type = 'payout'");
echo "<pre>";
print_r($payouts);
echo "</pre>";

// 2. Сумма выплат
echo "<h2>2. Сумма выплат (payout)</h2>";
$sum = dbQueryOne("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_type = 'payout' AND status = 'paid'");
echo "<pre>";
print_r($sum);
echo "</pre>";

// 3. Все уникальные payment_type
echo "<h2>3. Все уникальные payment_type в базе</h2>";
$types = dbQuery("SELECT payment_type, COUNT(*) as cnt, SUM(amount) as total FROM payments GROUP BY payment_type");
echo "<pre>";
print_r($types);
echo "</pre>";

// 4. Все статусы выплат
echo "<h2>4. Статусы выплат</h2>";
$statuses = dbQuery("SELECT status, COUNT(*) as cnt, SUM(amount) as total FROM payments GROUP BY status");
echo "<pre>";
print_r($statuses);
echo "</pre>";

// 5. Последние 10 записей в payments
echo "<h2>5. Последние 10 записей в payments</h2>";
$recent = dbQuery("SELECT id, teacher_id, amount, payment_type, status, notes, created_at FROM payments ORDER BY id DESC LIMIT 10");
echo "<pre>";
print_r($recent);
echo "</pre>";

// 6. Проверяем teacherFilter из payments.php
echo "<h2>6. Тест запроса из payments.php</h2>";
$teacherFilter = 0; // Все преподаватели
$payoutQuery = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_type = 'payout' AND status = 'paid'";
$payoutParams = [];
if ($teacherFilter > 0) {
    $payoutQuery .= " AND teacher_id = ?";
    $payoutParams[] = $teacherFilter;
}
echo "<p>Query: <code>$payoutQuery</code></p>";
$payoutTotal = dbQueryOne($payoutQuery, $payoutParams);
echo "<pre>";
print_r($payoutTotal);
echo "</pre>";

echo "<h2>7. Структура таблицы payments</h2>";
$structure = dbQuery("DESCRIBE payments");
echo "<pre>";
print_r($structure);
echo "</pre>";
