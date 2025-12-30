<?php
/**
 * Проверка почты Gmail на уведомления о переводах Сбербанка
 * Запускать через cron каждые 30 минут:
 * Cron: 0,30 * * * * php /path/to/zarplata/cron/parse_payments.php
 */

// Очистка OpCache чтобы использовать свежий код
if (function_exists('opcache_reset')) {
    @opcache_reset();
}

require_once __DIR__ . '/../config/db.php';

// Настройки Gmail (загружаются из базы)
$gmailUser = getSetting('gmail_user', '');
$gmailPassword = getSetting('gmail_app_password', '');
$emailSender = getSetting('email_sender', $gmailUser); // От кого искать письма
$emailSubjectFilter = getSetting('email_subject_filter', 'ZARPLATAPROJECT');
$emailSearchDays = (int)getSetting('email_search_days', '60'); // За сколько дней проверять

if (empty($gmailUser) || empty($gmailPassword)) {
    echo "Gmail credentials not configured. Set gmail_user and gmail_app_password in settings.\n";
    exit(1);
}

define('GMAIL_USER', $gmailUser);
define('GMAIL_APP_PASSWORD', $gmailPassword);
define('IMAP_SERVER', '{imap.gmail.com:993/imap/ssl}INBOX');
define('EMAIL_SENDER', $emailSender);
define('EMAIL_SUBJECT_FILTER', $emailSubjectFilter);
define('EMAIL_SEARCH_DAYS', $emailSearchDays);

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
function savePayment($parsed, $matchResult, $emailMessageId, $emailDate) {
    $payerId = $matchResult ? $matchResult['payer']['id'] : null;
    $studentId = $matchResult ? $matchResult['payer']['student_id'] : null;
    $confidence = $matchResult ? $matchResult['confidence'] : 0;
    $matchedBy = $matchResult ? 'auto_email' : null;
    $status = $matchResult ? 'matched' : 'pending';

    // Используем дату из письма или текущий месяц
    $month = $emailDate ? date('Y-m', strtotime($emailDate)) : date('Y-m');

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
              status, month, match_confidence, matched_by, email_message_id, received_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
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
                $matchedBy,
                $emailMessageId,
                $emailDate ?: date('Y-m-d H:i:s')
            ]
        );

        return $paymentId;
    } catch (Exception $e) {
        logMessage("Error saving payment: " . $e->getMessage());
        return false;
    }
}

/**
 * Проверка на дубликат по email_message_id (чтобы не добавлять одно письмо дважды)
 */
function isDuplicate($emailMessageId) {
    if (empty($emailMessageId)) {
        return false;
    }

    $existing = dbQueryOne(
        "SELECT id FROM incoming_payments WHERE email_message_id = ?",
        [$emailMessageId]
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

// Ищем ВСЕ письма за последние N дней от указанного отправителя
$sinceDate = date('d-M-Y', strtotime('-' . EMAIL_SEARCH_DAYS . ' days'));
$searchCriteria = 'FROM "' . EMAIL_SENDER . '" SINCE "' . $sinceDate . '"';
logMessage("Searching: $searchCriteria");

$selfEmails = imap_search($inbox, $searchCriteria, SE_UID);

if (!$selfEmails) {
    logMessage("No emails found from " . EMAIL_SENDER . " since $sinceDate");
    imap_close($inbox);
    exit(0);
}

logMessage("Found " . count($selfEmails) . " emails, filtering by subject '" . EMAIL_SUBJECT_FILTER . "'...");

// Фильтруем по теме
$emails = [];
foreach ($selfEmails as $uid) {
    $headerInfo = imap_headerinfo($inbox, imap_msgno($inbox, $uid));
    $subject = isset($headerInfo->subject) ? imap_utf8($headerInfo->subject) : '';

    // Ищем ключевое слово в теме
    if (stripos($subject, EMAIL_SUBJECT_FILTER) !== false) {
        $emails[] = $uid;
    }
}

if (empty($emails)) {
    logMessage("No emails with '" . EMAIL_SUBJECT_FILTER . "' subject found");
    imap_close($inbox);
    exit(0);
}

logMessage("Found " . count($emails) . " payment email(s)");

$processed = 0;
$skipped = 0;
$duplicates = 0;

foreach ($emails as $emailUid) {
    // Получаем заголовки
    $header = imap_fetchheader($inbox, $emailUid, FT_UID);
    $headerInfo = imap_headerinfo($inbox, imap_msgno($inbox, $emailUid));

    // Получаем уникальный Message-ID письма
    $emailMessageId = isset($headerInfo->message_id) ? $headerInfo->message_id : null;
    if (empty($emailMessageId)) {
        // Если нет message_id, создаём из UID и даты
        $emailMessageId = 'uid_' . $emailUid . '_' . ($headerInfo->udate ?? time());
    }

    // Получаем дату письма
    $emailDate = null;
    if (isset($headerInfo->date)) {
        $emailDate = date('Y-m-d H:i:s', strtotime($headerInfo->date));
    }

    $subject = isset($headerInfo->subject) ? imap_utf8($headerInfo->subject) : '';

    // Проверяем дубликат по message_id (СНАЧАЛА!)
    if (isDuplicate($emailMessageId)) {
        $duplicates++;
        continue; // Тихо пропускаем, не логируем каждый раз
    }

    logMessage("Processing email: $subject (date: $emailDate)");

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
        $skipped++;
        continue;
    }

    logMessage("Parsed: sender={$parsed['sender_name']}, amount={$parsed['amount']}");

    // Ищем соответствующего плательщика (для автоматической привязки)
    $matchResult = findMatchingPayer($parsed['sender_name']);

    if ($matchResult) {
        logMessage("Matched to payer: {$matchResult['payer']['name']} (student: {$matchResult['payer']['student_name']}, confidence: {$matchResult['confidence']}%)");
    } else {
        logMessage("No matching payer found - payment will be saved as pending");
    }

    // Сохраняем в базу (ВСЕ платежи, независимо от белого списка)
    $paymentId = savePayment($parsed, $matchResult, $emailMessageId, $emailDate);

    if ($paymentId) {
        logMessage("Payment saved with ID: $paymentId");
        $processed++;
    } else {
        logMessage("Failed to save payment");
    }
}

imap_close($inbox);

logMessage("=== Email check complete: $processed processed, $skipped parse errors, $duplicates duplicates ===");
