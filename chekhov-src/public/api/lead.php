<?php
/**
 * Приём заявок с сайта репетитора (эвриум.рф).
 *
 * Поведение:
 *   1) Отсекает ботов: honeypot, подписанный одноразовый токен формы,
 *      whitelist значений селектов, российский формат телефона.
 *   2) Логирует заявку в JSONL (по одной строке на заявку),
 *      отбитые попытки — в blocked-YYYY-MM.jsonl с причиной.
 *   3) При наличии Telegram-настроек — отправляет уведомление в бот.
 *   4) Возвращает JSON { ok: true } или { ok: false, error: "..." }.
 *
 * Настройки — api/config.php или таблица settings базы zarplata,
 * см. leadLoadConfig() в _lead_guard.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/_lead_guard.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$config = leadLoadConfig();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// --- Извлечение полей --------------------------------------------------------
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

// Сквозная аналитика: источник заявки
$utmSource   = $pick('utm_source', 100);
$utmMedium   = $pick('utm_medium', 100);
$utmCampaign = $pick('utm_campaign', 150);
$utmTerm     = $pick('utm_term', 150);
$utmContent  = $pick('utm_content', 150);
$yclid       = $pick('yclid', 50);
$ymClientId  = $pick('ym_client_id', 40);
$landing     = $pick('landing', 200);

/**
 * Журнал отбитых попыток. Нужен, чтобы поймать ложные срабатывания: если живой
 * человек не смог отправить заявку, это будет видно здесь с причиной.
 */
$logBlocked = static function (string $reason)
        use ($ip, $name, $phone, $messenger, $comment, $config): void {
    $logDir = $config['log_dir'];
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    @file_put_contents(
        rtrim($logDir, '/') . '/blocked-' . date('Y-m') . '.jsonl',
        json_encode([
            'ts' => date('c'),
            'reason' => $reason,
            'ip' => $ip,
            'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
            'referer' => substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 300),
            'name' => $name,
            'phone' => $phone,
            'messenger' => $messenger,
            'comment' => mb_substr($comment, 0, 200),
        ], JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
    );
};

/** Записать причину и ответить ошибкой. */
$reject = static function (string $reason, int $code = 422, string $error = 'invalid_request')
        use ($logBlocked): void {
    $logBlocked($reason);
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error]);
};

// --- Honeypot -------------------------------------------------------------
if (!empty($_POST['website'])) {
    // Бот заполнил скрытое поле — тихо отвечаем «ок», чтобы не подсказывать.
    $logBlocked('honeypot');
    echo json_encode(['ok' => true]);
    exit;
}

// --- Токен формы ----------------------------------------------------------
// Выдаётся form-token.php при загрузке страницы, подписан серверным секретом,
// одноразовый и «созревает» пару секунд. Прямой POST мимо страницы его не имеет.
[$tokenOk, $tokenReason] = leadCheckToken((string)($_POST['form_token'] ?? ''));
if (!$tokenOk) {
    $reject($tokenReason, 403, 'form_expired');
    exit;
}

// --- Капча (по умолчанию выключена) ---------------------------------------
if (leadCaptchaEnabled($config)) {
    [$capOk, $capReason] = leadCaptchaVerify(
        (string)$config['smartcaptcha_server_key'],
        trim((string)($_POST['smart-token'] ?? '')),
        $ip
    );
    if (!$capOk) {
        $reject($capReason, 403, 'captcha_failed');
        exit;
    }
}

// --- Валидация полей ------------------------------------------------------
if ($name === '' || mb_strlen($name) < 2) {
    $reject('invalid_name', 422, 'invalid_name');
    exit;
}

// Телефон: приводим к 11 цифрам с семёркой впереди и требуем российский код.
// 9xx — мобильные, 3xx/4xx — географические (Москва и область: 495/499/498/496).
$digits = preg_replace('/\D+/', '', $phone);
if (strlen($digits) === 10) {
    $digits = '7' . $digits;
} elseif (strlen($digits) === 11 && $digits[0] === '8') {
    $digits = '7' . substr($digits, 1);
}
if (!preg_match('/^7[349]\d{9}$/', $digits)) {
    $reject('invalid_phone', 422, 'invalid_phone');
    exit;
}

// Селекты: принимаем только те значения, что реально есть в формах.
// Пустое значение легально у всех четырёх: короткая форма на главной шлёт
// только имя и телефон, поля мессенджера в ней нет вовсе.
$allowed = [
    'class'     => ['2', '3', '4', '5', '6', '7', '8', '9', '10', '11'],
    'subject'   => ['matematika', 'informatika', 'fizika', 'neskolko'],
    'goal'      => ['oge', 'ege', 'uspevaemost', 'kontrolnye', 'probely', 'drugoe'],
    'messenger' => ['any', 'whatsapp', 'telegram', 'zvonok'],
];
foreach (
    ['class' => $classNum, 'subject' => $subject, 'goal' => $goal, 'messenger' => $messenger]
    as $field => $value
) {
    if ($value !== '' && !in_array($value, $allowed[$field], true)) {
        $reject('invalid_' . $field, 422, 'invalid_request');
        exit;
    }
}

// --- Простейший rate-limit (1 заявка / 30 сек с одного IP) ---------------
$rateFile = sys_get_temp_dir() . '/chekhov_lead_' . md5($ip);
$now = time();
if (is_file($rateFile) && ($now - (int)@file_get_contents($rateFile)) < 30) {
    $reject('rate_limited', 429, 'too_many_requests');
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
    'utm_source' => $utmSource,
    'utm_medium' => $utmMedium,
    'utm_campaign' => $utmCampaign,
    'utm_term' => $utmTerm,
    'utm_content' => $utmContent,
    'yclid' => $yclid,
    'ym_client_id' => $ymClientId,
    'landing' => $landing,
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
    $lines[] = '🎓 <b>Новая заявка с сайта эвриум.рф</b>';
    $lines[] = '';
    $lines[] = '<b>Имя:</b> ' . htmlspecialchars($name);
    $lines[] = '<b>Телефон:</b> ' . htmlspecialchars($phone);
    if ($classNum)  $lines[] = '<b>Класс:</b> ' . htmlspecialchars($classNum);
    if ($subject)   $lines[] = '<b>Предмет:</b> ' . htmlspecialchars($subjectLabels[$subject] ?? $subject);
    if ($goal)      $lines[] = '<b>Цель:</b> ' . htmlspecialchars($goalLabels[$goal] ?? $goal);
    if ($messenger) $lines[] = '<b>Связь:</b> ' . htmlspecialchars($messenger);
    if ($comment)   $lines[] = '<b>Комментарий:</b> ' . htmlspecialchars($comment);
    $lines[] = '';
    if ($utmSource || $utmCampaign) {
        $src = $utmSource ?: '—';
        if ($utmMedium)   $src .= ' / ' . $utmMedium;
        if ($utmCampaign) $src .= ' / ' . $utmCampaign;
        $lines[] = '<b>Источник:</b> ' . htmlspecialchars($src);
        if ($utmTerm) $lines[] = '<b>Запрос:</b> ' . htmlspecialchars($utmTerm);
    } else {
        $lines[] = '<i>Источник: ' . htmlspecialchars($lead['referer'] ?: ($landing ?: '—')) . '</i>';
    }
    if ($yclid) $lines[] = '<i>yclid: ' . htmlspecialchars($yclid) . ' (клик Яндекс.Директа)</i>';
    $lines[] = $ymClientId
        ? '<i>ID в Метрике: <code>' . htmlspecialchars($ymClientId) . '</code> (Отчёты → Посетители → поиск по ID)</i>'
        : '<i>ID в Метрике: нет (счётчик у посетителя не загрузился)</i>';

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
    $body = "Новая заявка с сайта эвриум.рф\n\n";
    foreach ($lead as $k => $v) $body .= str_pad($k . ':', 18) . $v . "\n";
    @mail(
        $config['email_to'],
        'Новая заявка с сайта эвриум.рф',
        $body,
        "Content-Type: text/plain; charset=utf-8\r\nFrom: noreply@эвриум.рф"
    );
}

echo json_encode(['ok' => true]);
