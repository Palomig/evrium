<?php

function assertTrue($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$baseDir = dirname(__DIR__);
$handlerPath = $baseDir . '/handlers/MonthCommand.php';
$configPath = $baseDir . '/config.php';
$webhookPath = $baseDir . '/webhook.php';

assertTrue(file_exists($handlerPath), 'MonthCommand.php must exist');

$configContents = file_get_contents($configPath);
assertTrue(strpos($configContents, '📆 Месяц') !== false, 'Main menu must include monthly button');

$webhookContents = file_get_contents($webhookPath);
assertTrue(strpos($webhookContents, "case '📆 Месяц':") !== false, 'Webhook must route monthly button text');
assertTrue(strpos($webhookContents, "case '/month':") !== false, 'Webhook must route /month command');

$teacherResponse = ['id' => 7, 'name' => 'Ирина'];
$paymentsResponse = [
    ['payment_date' => '2026-03-01', 'daily_total' => 3500, 'lessons_count' => 2],
    ['payment_date' => '2026-03-03', 'daily_total' => 2000, 'lessons_count' => 1],
];
$monthTotalResponse = ['total' => 5500, 'count' => 3];

$GLOBALS['dbQueryCalls'] = [];
$GLOBALS['sentMessages'] = [];

function getTeacherByTelegramId($telegramId) {
    return $GLOBALS['teacherResponse'];
}

function dbQuery($sql, $params) {
    $GLOBALS['dbQueryCalls'][] = ['sql' => $sql, 'params' => $params];
    return $GLOBALS['paymentsResponse'];
}

function dbQueryOne($sql, $params) {
    $GLOBALS['dbQueryCalls'][] = ['sql' => $sql, 'params' => $params];
    return $GLOBALS['monthTotalResponse'];
}

function plural($count, $one, $few, $many) {
    $mod10 = $count % 10;
    $mod100 = $count % 100;
    if ($mod10 === 1 && $mod100 !== 11) {
        return $one;
    }
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
        return $few;
    }
    return $many;
}

function getMainMenuKeyboard() {
    return ['keyboard' => [[['text' => '📆 Месяц']]]];
}

function sendTelegramMessage($chatId, $message, $keyboard = null) {
    $GLOBALS['sentMessages'][] = ['chat_id' => $chatId, 'message' => $message, 'keyboard' => $keyboard];
}

$GLOBALS['teacherResponse'] = $teacherResponse;
$GLOBALS['paymentsResponse'] = $paymentsResponse;
$GLOBALS['monthTotalResponse'] = $monthTotalResponse;

require_once $handlerPath;

handleMonthCommand(12345, 99999);

assertTrue(count($GLOBALS['sentMessages']) === 1, 'Month command must send exactly one message');
$sent = $GLOBALS['sentMessages'][0];

assertTrue(strpos($sent['message'], 'Заработок за месяц') !== false, 'Message must mention monthly salary');
assertTrue(strpos($sent['message'], '01.03') !== false, 'Message must include month start date');
assertTrue(strpos($sent['message'], '5 500 ₽') !== false, 'Message must include month total');
assertTrue(strpos($sent['message'], 'Уроков:</b> 3') !== false, 'Message must include lessons count');
assertTrue($sent['keyboard'] !== null, 'Month command must send main keyboard');

echo "PASS\n";
