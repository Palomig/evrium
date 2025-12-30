<?php
/**
 * Проверка почты Gmail на уведомления о переводах Сбербанка
 * Запускать через cron каждые 5 минут:
 * Cron: 0,5,10,15,20,25,30,35,40,45,50,55 * * * * php /path/to/zarplata/cron/check_email.php
 */

require_once __DIR__ . '/../config/db.php';

// Настройки Gmail (загружаются из базы)
$gmailUser = getSetting('gmail_user', '');
$gmailPassword = getSetting('gmail_app_password', '');

if (empty($gmailUser) || empty($gmailPassword)) {
    echo "Gmail credentials not configured. Set gmail_user and gmail_app_password in settings.\n";
    exit(1);
}

define('GMAIL_USER', $gmailUser);
define('GMAIL_APP_PASSWORD', $gmailPassword);
define('IMAP_SERVER', '{imap.gmail.com:993/imap/ssl}INBOX');

// Логирование
function logMessage($message) {
    $logFile = __DIR__ . '/../logs/email_parser.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

/**
 * Парсинг уведомления Сбербанка
 * Формат: "Перевод от Руслан Романович Б. + 10 ₽ Счёт карты VISA •• 9089"
 */
function parseSberbankNotification($text) {
    $result = [
        'sender_name' => null,
        'amount' => null,
        'card_info' => null,
        'raw_text' => $text
    ];

    // Ищем имя отправителя: "Перевод от ИМЯ ОТЧЕСТВО Ф." или "Перевод по СБП от ИМЯ"
    if (preg_match('/Перевод\s+(?:по СБП\s+)?от\s+([А-ЯЁа-яё]+\s+[А-ЯЁа-яё]+(?:\s+[А-ЯЁа-яё]\.?)?)/u', $text, $matches)) {
        $result['sender_name'] = trim($matches[1]);
    }

    // Ищем сумму: "+ 10 ₽" или "+1000 ₽" или "+ 1 000 ₽"
    if (preg_match('/\+\s*([\d\s]+)\s*₽/u', $text, $matches)) {
        $amount = preg_replace('/\s+/', '', $matches[1]); // Убираем пробелы
        $result['amount'] = (int)$amount;
    }

    // Ищем информацию о карте: "Счёт карты VISA •• 9089"
    if (preg_match('/(Счёт\s+карты\s+\w+\s*••?\s*\d+)/u', $text, $matches)) {
        $result['card_info'] = trim($matches[1]);
    }

    return $result;
}

/**
 * Поиск соответствующего плательщика
 */
function findMatchingPayer($senderName) {
    if (empty($senderName)) {
        return null;
    }

    // Нормализуем имя (убираем лишние пробелы, приводим к нижнему регистру для сравнения)
    $normalizedName = mb_strtolower(trim($senderName));

    // Ищем точное совпадение
    $payer = dbQueryOne(
        "SELECT sp.*, s.name as student_name
         FROM student_payers sp
         JOIN students s ON sp.student_id = s.id
         WHERE LOWER(sp.name) = ? AND sp.active = 1",
        [$normalizedName]
    );

    if ($payer) {
        return [
            'payer' => $payer,
            'confidence' => 100,
            'match_type' => 'exact'
        ];
    }

    // Ищем частичное совпадение (первые два слова имени)
    $nameParts = explode(' ', $normalizedName);
    if (count($nameParts) >= 2) {
        $partialName = $nameParts[0] . ' ' . $nameParts[1];
        $payer = dbQueryOne(
            "SELECT sp.*, s.name as student_name
             FROM student_payers sp
             JOIN students s ON sp.student_id = s.id
             WHERE LOWER(sp.name) LIKE ? AND sp.active = 1",
            [$partialName . '%']
        );

        if ($payer) {
            return [
                'payer' => $payer,
                'confidence' => 80,
                'match_type' => 'partial'
            ];
        }
    }

    return null;
}

/**
 * Сохранение платежа в базу
 */
function savePayment($parsed, $matchResult) {
    $payerId = $matchResult ? $matchResult['payer']['id'] : null;
    $studentId = $matchResult ? $matchResult['payer']['student_id'] : null;
    $confidence = $matchResult ? $matchResult['confidence'] : 0;
    $matchedBy = $matchResult ? 'auto_email' : null;
    $status = $matchResult ? 'matched' : 'pending';
    $month = date('Y-m');

    // Переподключаемся к базе (может отвалиться за время проверки почты)
    global $pdo;
    $pdo = null;
    $pdo = new PDO(
        'mysql:host=localhost;dbname=cw95865_admin;charset=utf8mb4',
        'cw95865_admin',
        '123456789',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    try {
        $paymentId = dbExecute(
            "INSERT INTO incoming_payments
             (payer_id, student_id, sender_name, amount, bank_name, raw_notification,
              status, month, match_confidence, matched_by, received_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $payerId,
                $studentId,
                $parsed['sender_name'] ?? 'Неизвестно',
                $parsed['amount'] ?? 0,
                'Сбербанк',
                $parsed['raw_text'],
                $status,
                $month,
                $confidence,
                $matchedBy
            ]
        );

        return $paymentId;
    } catch (Exception $e) {
        logMessage("Error saving payment: " . $e->getMessage());
        return false;
    }
}

/**
 * Проверка на дубликат (чтобы не добавлять одно письмо дважды)
 */
function isDuplicate($rawText, $amount) {
    // Проверяем, есть ли уже такой платёж за последние 24 часа
    $existing = dbQueryOne(
        "SELECT id FROM incoming_payments
         WHERE raw_notification = ? AND amount = ?
         AND received_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        [$rawText, $amount]
    );

    return $existing !== null;
}

// Основной код
logMessage("=== Starting email check ===");

// Подключаемся к Gmail
$inbox = @imap_open(IMAP_SERVER, GMAIL_USER, GMAIL_APP_PASSWORD);

if (!$inbox) {
    logMessage("ERROR: Cannot connect to Gmail: " . imap_last_error());
    exit(1);
}

logMessage("Connected to Gmail successfully");

// Ищем непрочитанные письма ОТ СЕБЯ (Notification Forwarder шлёт на тот же адрес)
$searchCriteria = 'UNSEEN FROM "' . GMAIL_USER . '"';
logMessage("Searching: $searchCriteria");

$selfEmails = imap_search($inbox, $searchCriteria, SE_UID);

if (!$selfEmails) {
    logMessage("No unread self-emails found");
    imap_close($inbox);
    exit(0);
}

logMessage("Found " . count($selfEmails) . " self-emails, filtering by subject...");

// Фильтруем по теме "СберБанк" или "Сбербанк"
$emails = [];
foreach ($selfEmails as $uid) {
    $headerInfo = imap_headerinfo($inbox, imap_msgno($inbox, $uid));
    $subject = isset($headerInfo->subject) ? imap_utf8($headerInfo->subject) : '';

    logMessage("  Subject: $subject");

    // Ищем СберБанк/Сбербанк в теме
    if (stripos($subject, 'СберБанк') !== false || stripos($subject, 'Сбербанк') !== false) {
        $emails[] = $uid;
        logMessage("  -> MATCH!");
    }
}

if (empty($emails)) {
    logMessage("No Sberbank emails found after filtering");
    imap_close($inbox);
    exit(0);
}

logMessage("Found " . count($emails) . " Sberbank email(s)");

$processed = 0;
$skipped = 0;

foreach ($emails as $emailUid) {
    // Получаем заголовки
    $header = imap_fetchheader($inbox, $emailUid, FT_UID);
    $headerInfo = imap_headerinfo($inbox, imap_msgno($inbox, $emailUid));

    $subject = isset($headerInfo->subject) ? imap_utf8($headerInfo->subject) : '';
    logMessage("Processing email: $subject");

    // Получаем тело письма
    $body = '';
    $structure = imap_fetchstructure($inbox, $emailUid, FT_UID);

    if (isset($structure->parts) && count($structure->parts)) {
        // Multipart message
        foreach ($structure->parts as $partNum => $part) {
            if ($part->type == 0) { // Text
                $body = imap_fetchbody($inbox, $emailUid, $partNum + 1, FT_UID);
                if ($part->encoding == 3) { // BASE64
                    $body = base64_decode($body);
                } elseif ($part->encoding == 4) { // QUOTED-PRINTABLE
                    $body = quoted_printable_decode($body);
                }
                break;
            }
        }
    } else {
        // Simple message
        $body = imap_body($inbox, $emailUid, FT_UID);
        if (isset($structure->encoding)) {
            if ($structure->encoding == 3) {
                $body = base64_decode($body);
            } elseif ($structure->encoding == 4) {
                $body = quoted_printable_decode($body);
            }
        }
    }

    // Декодируем UTF-8 если нужно
    $body = mb_convert_encoding($body, 'UTF-8', 'auto');

    // Очищаем от HTML тегов
    $body = strip_tags($body);
    $body = html_entity_decode($body, ENT_QUOTES, 'UTF-8');
    $body = trim($body);

    logMessage("Email body: " . substr($body, 0, 200) . "...");

    // Парсим уведомление
    $parsed = parseSberbankNotification($body);

    if (!$parsed['sender_name'] && !$parsed['amount']) {
        logMessage("Could not parse notification, skipping");
        // Помечаем как прочитанное чтобы не обрабатывать снова
        imap_setflag_full($inbox, $emailUid, "\\Seen", ST_UID);
        $skipped++;
        continue;
    }

    logMessage("Parsed: sender={$parsed['sender_name']}, amount={$parsed['amount']}");

    // Проверяем дубликат
    if (isDuplicate($body, $parsed['amount'] ?? 0)) {
        logMessage("Duplicate payment, skipping");
        imap_setflag_full($inbox, $emailUid, "\\Seen", ST_UID);
        $skipped++;
        continue;
    }

    // Ищем соответствующего плательщика
    $matchResult = findMatchingPayer($parsed['sender_name']);

    if ($matchResult) {
        logMessage("Matched to payer: {$matchResult['payer']['name']} (student: {$matchResult['payer']['student_name']}, confidence: {$matchResult['confidence']}%)");
    } else {
        logMessage("No matching payer found");
    }

    // Сохраняем в базу
    $paymentId = savePayment($parsed, $matchResult);

    if ($paymentId) {
        logMessage("Payment saved with ID: $paymentId");
        $processed++;
    } else {
        logMessage("Failed to save payment");
    }

    // Помечаем письмо как прочитанное
    imap_setflag_full($inbox, $emailUid, "\\Seen", ST_UID);
}

imap_close($inbox);

logMessage("=== Email check complete: $processed processed, $skipped skipped ===");
