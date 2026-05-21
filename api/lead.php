<?php
/**
 * Приём заявок с сайта репетитора (эвриум.рф).
 *
 * Поведение:
 *   1) Валидирует поля (name, phone) + honeypot.
 *   2) Логирует заявку в JSONL (по одной строке на заявку).
 *   3) При наличии Telegram-настроек — отправляет уведомление в бот.
 *   4) Возвращает JSON { ok: true } или { ok: false, error: "..." }.
 *
 * TODO репетитору:
 *   - Скопируйте config.example.php в config.php и заполните токен/чат-id Telegram.
 *   - Убедитесь, что storage/leads/ существует и доступен для записи веб-серверу.
 *   - При желании — добавьте отправку на e-mail через mail() или PHPMailer.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

// --- Конфигурация ---------------------------------------------------------
$configFile = __DIR__ . '/config.php';
$config = is_file($configFile) ? (require $configFile) : [];
$config = array_merge([
    'telegram_token' => null,    // 'XXXXXXXXX:YYYYYYY'
    'telegram_chat_id' => null,  // '123456789'
    'email_to' => null,          // 'tutor@example.com'
    'log_dir' => dirname(__DIR__, 3) . '/storage/leads', // вне public
], $config);

// --- Honeypot -------------------------------------------------------------
if (!empty($_POST['website'])) {
    // Бот заполнил скрытое поле — тихо отвечаем «ок».
    echo json_encode(['ok' => true]);
    exit;
}

// --- Извлечение и валидация ----------------------------------------------
$pick = static function (string $key, int $max = 500): string {
    $v = trim((string)($_POST[$key] ?? ''));
    if ($v === '') return '';
    $v = preg_replace('/\s+/u', ' ', $v);
    if (function_exists('mb_substr')) $v = mb_substr($v, 0, $max);
    return $v;
};

$name      = $pick('name', 100);
$phone     = $pick('phone', 30);
$classNum  = $pick('class', 10);
$subject   = $pick('subject', 30);
$goal      = $pick('goal', 30);
$messenger = $pick('messenger', 30);
$comment   = $pick('comment', 1500);

if ($name === '' || mb_strlen($name) < 2) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_name']);
    exit;
}

$digits = preg_replace('/\D+/', '', $phone);
if (strlen($digits) < 10) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_phone']);
    exit;
}

// --- Простейший rate-limit (1 заявка / 30 сек с одного IP) ---------------
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = sys_get_temp_dir() . '/chekhov_lead_' . md5($ip);
$now = time();
if (is_file($rateFile) && ($now - (int)@file_get_contents($rateFile)) < 30) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'too_many_requests']);
    exit;
}
@file_put_contents($rateFile, (string)$now);

// --- Сборка записи --------------------------------------------------------
$lead = [
    'ts' => date('c'),
    'ip' => $ip,
    'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
    'referer' => substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 300),
    'name' => $name,
    'phone' => $phone,
    'phone_normalized' => '+' . $digits,
    'class' => $classNum,
    'subject' => $subject,
    'goal' => $goal,
    'messenger' => $messenger,
    'comment' => $comment,
];

// --- Лог в файл -----------------------------------------------------------
$logDir = $config['log_dir'];
if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
$logFile = rtrim($logDir, '/') . '/leads-' . date('Y-m') . '.jsonl';
@file_put_contents(
    $logFile,
    json_encode($lead, JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND | LOCK_EX
);

// --- Уведомление в Telegram ----------------------------------------------
if (!empty($config['telegram_token']) && !empty($config['telegram_chat_id'])) {
    $subjectLabels = [
        'matematika' => 'математика',
        'informatika' => 'информатика',
        'fizika' => 'физика',
        'neskolko' => 'несколько',
    ];
    $goalLabels = [
        'oge' => 'ОГЭ',
        'ege' => 'ЕГЭ',
        'uspevaemost' => 'успеваемость',
        'kontrolnye' => 'контрольные',
        'probely' => 'пробелы',
        'drugoe' => 'другое',
    ];

    $lines = [];
    $lines[] = '🎓 <b>Новая заявка с сайта (chekhov)</b>';
    $lines[] = '';
    $lines[] = '<b>Имя:</b> ' . htmlspecialchars($name);
    $lines[] = '<b>Телефон:</b> ' . htmlspecialchars($phone);
    if ($classNum)  $lines[] = '<b>Класс:</b> ' . htmlspecialchars($classNum);
    if ($subject)   $lines[] = '<b>Предмет:</b> ' . htmlspecialchars($subjectLabels[$subject] ?? $subject);
    if ($goal)      $lines[] = '<b>Цель:</b> ' . htmlspecialchars($goalLabels[$goal] ?? $goal);
    if ($messenger) $lines[] = '<b>Связь:</b> ' . htmlspecialchars($messenger);
    if ($comment)   $lines[] = '<b>Комментарий:</b> ' . htmlspecialchars($comment);
    $lines[] = '';
    $lines[] = '<i>Источник: ' . htmlspecialchars($lead['referer'] ?: '—') . '</i>';

    $payload = http_build_query([
        'chat_id' => $config['telegram_chat_id'],
        'text' => implode("\n", $lines),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => 'true',
    ]);
    $url = 'https://api.telegram.org/bot' . $config['telegram_token'] . '/sendMessage';

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);
    @file_get_contents($url, false, $ctx);
}

// --- Уведомление на e-mail (опционально) ---------------------------------
if (!empty($config['email_to']) && function_exists('mail')) {
    $body = "Новая заявка с сайта репетитора (chekhov)\n\n";
    foreach ($lead as $k => $v) $body .= str_pad($k . ':', 18) . $v . "\n";
    @mail(
        $config['email_to'],
        'Новая заявка с сайта chekhov',
        $body,
        "Content-Type: text/plain; charset=utf-8\r\nFrom: noreply@эвриум.рф"
    );
}

echo json_encode(['ok' => true]);
