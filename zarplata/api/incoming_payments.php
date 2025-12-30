<?php
/**
 * API для входящих платежей от учеников
 * Автоматический прием push-уведомлений от Sberbank через Automate
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Webhook не требует авторизации, но требует токен
if ($action === 'webhook') {
    handleWebhook();
    exit;
}

// Все остальные действия требуют авторизации
if (!isLoggedIn()) {
    jsonError('Требуется авторизация', 401);
}

switch ($action) {
    case 'list':
        handleList();
        break;
    case 'match':
        handleMatch();
        break;
    case 'confirm':
        handleConfirm();
        break;
    case 'ignore':
        handleIgnore();
        break;
    case 'stats':
        handleStats();
        break;
    case 'payers':
        handlePayers();
        break;
    case 'add_payer':
        handleAddPayer();
        break;
    case 'update_payer':
        handleUpdatePayer();
        break;
    case 'delete_payer':
        handleDeletePayer();
        break;
    case 'get_token':
        handleGetToken();
        break;
    case 'regenerate_token':
        handleRegenerateToken();
        break;
    case 'add_cash':
        handleAddCash();
        break;
    case 'delete_cash':
        handleDeleteCash();
        break;
    default:
        jsonError('Неизвестное действие', 400);
}

/**
 * Webhook для приема уведомлений от Automate
 * POST /api/incoming_payments.php?action=webhook&token=XXX
 */
function handleWebhook() {
    // Логируем все входящие запросы для отладки
    $debugLog = __DIR__ . '/../logs/webhook_debug.log';
    $logDir = dirname($debugLog);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logData = [
        'time' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'get' => $_GET,
        'post' => $_POST,
        'raw_input' => file_get_contents('php://input'),
    ];
    @file_put_contents($debugLog, json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

    // Проверяем токен
    $token = $_GET['token'] ?? '';
    $savedToken = getSetting('automate_api_token', '');

    if (empty($savedToken)) {
        // Если токен не установлен, генерируем и сохраняем
        $savedToken = bin2hex(random_bytes(16));
        setSetting('automate_api_token', $savedToken);
    }

    if ($token !== $savedToken) {
        http_response_code(401);
        jsonError('Неверный токен', 401);
        return;
    }

    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $notification = trim($data['notification'] ?? $data['text'] ?? '');

    if (empty($notification)) {
        jsonError('Пустое уведомление', 400);
        return;
    }

    // Парсим уведомление Sberbank
    $parsed = parseSberbankNotification($notification);

    if (!$parsed) {
        // Логируем непарсенное уведомление
        error_log("Failed to parse notification: " . $notification);
        jsonSuccess(['status' => 'ignored', 'reason' => 'not_payment']);
        return;
    }

    // Ищем соответствующего плательщика
    $matchResult = findMatchingPayer($parsed['sender_name']);

    // Определяем месяц (текущий, если не указан)
    $month = date('Y-m');

    // Сохраняем платеж
    try {
        $paymentId = dbExecute(
            "INSERT INTO incoming_payments
             (payer_id, student_id, sender_name, amount, bank_name, raw_notification,
              status, month, match_confidence, matched_by, received_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $matchResult['payer_id'],
                $matchResult['student_id'],
                $parsed['sender_name'],
                $parsed['amount'],
                $parsed['bank_name'],
                $notification,
                $matchResult['payer_id'] ? 'matched' : 'pending',
                $month,
                $matchResult['confidence'],
                $matchResult['payer_id'] ? 'auto' : null
            ]
        );

        if ($paymentId) {
            logAudit('incoming_payment_received', 'incoming_payment', $paymentId, null, [
                'sender_name' => $parsed['sender_name'],
                'amount' => $parsed['amount'],
                'bank' => $parsed['bank_name'],
                'matched' => $matchResult['payer_id'] ? true : false
            ], 'Получен входящий платеж');

            jsonSuccess([
                'id' => $paymentId,
                'status' => $matchResult['payer_id'] ? 'matched' : 'pending',
                'matched_to' => $matchResult['student_name'] ?? null,
                'confidence' => $matchResult['confidence']
            ]);
        } else {
            jsonError('Ошибка сохранения', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to save incoming payment: " . $e->getMessage());
        jsonError('Ошибка базы данных', 500);
    }
}

/**
 * Парсинг уведомления Sberbank
 * Формат: "Перевод по СБП от СТАНИСЛАВ ОЛЕГОВИЧ... Альфа-Банк +100 ₽"
 */
function parseSberbankNotification($text) {
    // Паттерн для парсинга
    // Варианты:
    // "Перевод по СБП от ИВАН ИВАНОВИЧ... Банк +5000 ₽"
    // "Перевод от ИВАН ИВАНОВИЧ +5000₽"
    // "СБП от ИВАН ИВАНОВИЧ Банк +5000 ₽"

    $patterns = [
        // Полный формат с банком
        '/(?:Перевод\s+)?(?:по\s+)?СБП\s+от\s+([А-ЯЁа-яё\s]+?)(?:\.{3}|…)?\s*([А-Яа-яA-Za-z\-]+(?:\s*[А-Яа-яA-Za-z\-]+)?(?:\s*Банк)?)\s*\+?\s*([\d\s]+)\s*[₽руб]/iu',
        // Формат без многоточия
        '/(?:Перевод\s+)?(?:по\s+)?СБП\s+от\s+([А-ЯЁа-яё\s]+?)\s+([А-Яа-яA-Za-z\-]+(?:\s*Банк)?)\s*\+?\s*([\d\s]+)\s*[₽руб]/iu',
        // Простой формат
        '/Перевод\s+от\s+([А-ЯЁа-яё\s]+?)\s*\+?\s*([\d\s]+)\s*[₽руб]/iu',
        // Альтернативный формат
        '/от\s+([А-ЯЁа-яё\s]+?)\s*(?:\.{3}|…)?\s*.*?\+?\s*([\d\s]+)\s*[₽руб]/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $senderName = trim($matches[1]);

            // Определяем банк и сумму в зависимости от паттерна
            if (count($matches) >= 4) {
                $bankName = trim($matches[2]);
                $amount = (int)preg_replace('/\s+/', '', $matches[3]);
            } else {
                $bankName = 'Неизвестный банк';
                $amount = (int)preg_replace('/\s+/', '', $matches[2]);
            }

            // Убираем лишние пробелы из имени
            $senderName = preg_replace('/\s+/', ' ', $senderName);

            // Проверяем что сумма валидна
            if ($amount > 0) {
                return [
                    'sender_name' => $senderName,
                    'bank_name' => $bankName,
                    'amount' => $amount
                ];
            }
        }
    }

    return null;
}

/**
 * Поиск соответствующего плательщика по имени
 */
function findMatchingPayer($senderName) {
    // Нормализуем имя для поиска
    $normalizedName = mb_strtoupper(trim($senderName));

    // Ищем точное совпадение
    $payer = dbQueryOne(
        "SELECT sp.*, s.name as student_name
         FROM student_payers sp
         JOIN students s ON sp.student_id = s.id
         WHERE sp.active = 1 AND UPPER(sp.name) = ?",
        [$normalizedName]
    );

    if ($payer) {
        return [
            'payer_id' => (int)$payer['id'],
            'student_id' => (int)$payer['student_id'],
            'student_name' => $payer['student_name'],
            'confidence' => 100
        ];
    }

    // Ищем частичное совпадение (первые 2 слова)
    $nameParts = explode(' ', $normalizedName);
    if (count($nameParts) >= 2) {
        $searchPattern = $nameParts[0] . ' ' . $nameParts[1] . '%';

        $payer = dbQueryOne(
            "SELECT sp.*, s.name as student_name
             FROM student_payers sp
             JOIN students s ON sp.student_id = s.id
             WHERE sp.active = 1 AND UPPER(sp.name) LIKE ?",
            [$searchPattern]
        );

        if ($payer) {
            return [
                'payer_id' => (int)$payer['id'],
                'student_id' => (int)$payer['student_id'],
                'student_name' => $payer['student_name'],
                'confidence' => 80
            ];
        }
    }

    // Ищем по первому слову (фамилии)
    if (!empty($nameParts[0]) && mb_strlen($nameParts[0]) > 2) {
        $payer = dbQueryOne(
            "SELECT sp.*, s.name as student_name
             FROM student_payers sp
             JOIN students s ON sp.student_id = s.id
             WHERE sp.active = 1 AND UPPER(sp.name) LIKE ?",
            [$nameParts[0] . '%']
        );

        if ($payer) {
            return [
                'payer_id' => (int)$payer['id'],
                'student_id' => (int)$payer['student_id'],
                'student_name' => $payer['student_name'],
                'confidence' => 50
            ];
        }
    }

    return [
        'payer_id' => null,
        'student_id' => null,
        'student_name' => null,
        'confidence' => 0
    ];
}

/**
 * Получить список входящих платежей
 */
function handleList() {
    $month = $_GET['month'] ?? date('Y-m');
    $status = $_GET['status'] ?? 'all';

    $where = ["month = ?"];
    $params = [$month];

    if ($status !== 'all') {
        $where[] = "ip.status = ?";
        $params[] = $status;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $payments = dbQuery(
        "SELECT ip.*,
                sp.name as payer_name,
                sp.relation as payer_relation,
                s.name as student_name,
                s.id as student_id
         FROM incoming_payments ip
         LEFT JOIN student_payers sp ON ip.payer_id = sp.id
         LEFT JOIN students s ON ip.student_id = s.id
         $whereClause
         ORDER BY ip.received_at DESC",
        $params
    );

    jsonSuccess($payments);
}

/**
 * Связать платеж с учеником вручную
 */
function handleMatch() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: $_POST;

    $paymentId = filter_var($data['payment_id'] ?? 0, FILTER_VALIDATE_INT);
    $studentId = filter_var($data['student_id'] ?? 0, FILTER_VALIDATE_INT);
    $payerId = filter_var($data['payer_id'] ?? 0, FILTER_VALIDATE_INT);
    $createPayer = $data['create_payer'] ?? false;
    $payerName = trim($data['payer_name'] ?? '');
    $payerRelation = trim($data['payer_relation'] ?? '');

    if (!$paymentId) {
        jsonError('Укажите ID платежа', 400);
    }

    if (!$studentId) {
        jsonError('Выберите ученика', 400);
    }

    // Получаем платеж
    $payment = dbQueryOne("SELECT * FROM incoming_payments WHERE id = ?", [$paymentId]);
    if (!$payment) {
        jsonError('Платеж не найден', 404);
    }

    // Если нужно создать нового плательщика
    if ($createPayer && $payerName) {
        try {
            $payerId = dbExecute(
                "INSERT INTO student_payers (student_id, name, relation) VALUES (?, ?, ?)",
                [$studentId, $payerName, $payerRelation ?: null]
            );
        } catch (Exception $e) {
            jsonError('Ошибка создания плательщика', 500);
        }
    }

    // Обновляем платеж
    try {
        dbExecute(
            "UPDATE incoming_payments
             SET student_id = ?, payer_id = ?, status = 'matched',
                 matched_by = 'manual', match_confidence = 100
             WHERE id = ?",
            [$studentId, $payerId ?: null, $paymentId]
        );

        $user = getCurrentUser();
        logAudit('incoming_payment_matched', 'incoming_payment', $paymentId, null, [
            'student_id' => $studentId,
            'payer_id' => $payerId,
            'matched_by' => $user['username']
        ], 'Платеж связан с учеником');

        jsonSuccess(['message' => 'Платеж связан']);
    } catch (Exception $e) {
        jsonError('Ошибка обновления', 500);
    }
}

/**
 * Подтвердить платеж (зачесть в счет оплаты)
 */
function handleConfirm() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: $_POST;

    $paymentId = filter_var($data['payment_id'] ?? 0, FILTER_VALIDATE_INT);
    $month = trim($data['month'] ?? date('Y-m'));

    if (!$paymentId) {
        jsonError('Укажите ID платежа', 400);
    }

    // Получаем платеж
    $payment = dbQueryOne("SELECT * FROM incoming_payments WHERE id = ?", [$paymentId]);
    if (!$payment) {
        jsonError('Платеж не найден', 404);
    }

    if (!$payment['student_id']) {
        jsonError('Сначала свяжите платеж с учеником', 400);
    }

    try {
        dbBeginTransaction();

        // Обновляем статус платежа
        dbExecute(
            "UPDATE incoming_payments SET status = 'confirmed', month = ?, confirmed_at = NOW() WHERE id = ?",
            [$month, $paymentId]
        );

        // Добавляем или обновляем запись в student_payments
        $existing = dbQueryOne(
            "SELECT id, amount FROM student_payments WHERE student_id = ? AND month = ?",
            [$payment['student_id'], $month]
        );

        if ($existing) {
            // Добавляем к существующей сумме
            dbExecute(
                "UPDATE student_payments SET amount = amount + ?, auto_payment_id = ?, paid_at = NOW() WHERE id = ?",
                [$payment['amount'], $paymentId, $existing['id']]
            );
        } else {
            // Создаем новую запись
            dbExecute(
                "INSERT INTO student_payments (student_id, month, amount, payment_method, auto_payment_id, paid_at)
                 VALUES (?, ?, ?, 'card', ?, NOW())",
                [$payment['student_id'], $month, $payment['amount'], $paymentId]
            );
        }

        dbCommit();

        logAudit('incoming_payment_confirmed', 'incoming_payment', $paymentId, null, [
            'student_id' => $payment['student_id'],
            'amount' => $payment['amount'],
            'month' => $month
        ], 'Платеж подтвержден');

        jsonSuccess(['message' => 'Платеж подтвержден']);
    } catch (Exception $e) {
        dbRollback();
        error_log("Failed to confirm payment: " . $e->getMessage());
        jsonError('Ошибка подтверждения', 500);
    }
}

/**
 * Игнорировать платеж
 */
function handleIgnore() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: $_POST;

    $paymentId = filter_var($data['payment_id'] ?? 0, FILTER_VALIDATE_INT);
    $reason = trim($data['reason'] ?? '');

    if (!$paymentId) {
        jsonError('Укажите ID платежа', 400);
    }

    try {
        dbExecute(
            "UPDATE incoming_payments SET status = 'ignored', notes = ? WHERE id = ?",
            [$reason ?: null, $paymentId]
        );

        jsonSuccess(['message' => 'Платеж проигнорирован']);
    } catch (Exception $e) {
        jsonError('Ошибка обновления', 500);
    }
}

/**
 * Статистика по платежам
 */
function handleStats() {
    $month = $_GET['month'] ?? date('Y-m');

    $stats = dbQueryOne(
        "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'matched' THEN 1 ELSE 0 END) as matched,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'ignored' THEN 1 ELSE 0 END) as ignored,
            COALESCE(SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END), 0) as confirmed_amount,
            COALESCE(SUM(CASE WHEN status IN ('pending', 'matched') THEN amount ELSE 0 END), 0) as pending_amount
         FROM incoming_payments
         WHERE month = ?",
        [$month]
    );

    jsonSuccess($stats);
}

/**
 * Получить список плательщиков
 */
function handlePayers() {
    $studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);

    $where = "WHERE sp.active = 1";
    $params = [];

    if ($studentId) {
        $where .= " AND sp.student_id = ?";
        $params[] = $studentId;
    }

    $payers = dbQuery(
        "SELECT sp.*, s.name as student_name
         FROM student_payers sp
         JOIN students s ON sp.student_id = s.id
         $where
         ORDER BY s.name, sp.name",
        $params
    );

    jsonSuccess($payers);
}

/**
 * Добавить плательщика
 */
function handleAddPayer() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: $_POST;

    $studentId = filter_var($data['student_id'] ?? 0, FILTER_VALIDATE_INT);
    $name = trim($data['name'] ?? '');
    $relation = trim($data['relation'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $notes = trim($data['notes'] ?? '');

    if (!$studentId) {
        jsonError('Выберите ученика', 400);
    }

    if (empty($name)) {
        jsonError('Укажите имя плательщика', 400);
    }

    // Нормализуем имя (приводим к верхнему регистру для сопоставления)
    $normalizedName = mb_strtoupper($name);

    try {
        $payerId = dbExecute(
            "INSERT INTO student_payers (student_id, name, relation, phone, notes)
             VALUES (?, ?, ?, ?, ?)",
            [$studentId, $normalizedName, $relation ?: null, $phone ?: null, $notes ?: null]
        );

        if ($payerId) {
            logAudit('payer_created', 'student_payer', $payerId, null, [
                'student_id' => $studentId,
                'name' => $normalizedName,
                'relation' => $relation
            ], 'Добавлен плательщик');

            jsonSuccess(['id' => $payerId, 'message' => 'Плательщик добавлен']);
        } else {
            jsonError('Ошибка создания', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to add payer: " . $e->getMessage());
        jsonError('Ошибка базы данных', 500);
    }
}

/**
 * Обновить плательщика
 */
function handleUpdatePayer() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: $_POST;

    $payerId = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
    $name = trim($data['name'] ?? '');
    $relation = trim($data['relation'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $notes = trim($data['notes'] ?? '');

    if (!$payerId) {
        jsonError('Укажите ID плательщика', 400);
    }

    if (empty($name)) {
        jsonError('Укажите имя плательщика', 400);
    }

    $normalizedName = mb_strtoupper($name);

    try {
        dbExecute(
            "UPDATE student_payers SET name = ?, relation = ?, phone = ?, notes = ? WHERE id = ?",
            [$normalizedName, $relation ?: null, $phone ?: null, $notes ?: null, $payerId]
        );

        jsonSuccess(['message' => 'Плательщик обновлен']);
    } catch (Exception $e) {
        jsonError('Ошибка обновления', 500);
    }
}

/**
 * Удалить плательщика (soft delete)
 */
function handleDeletePayer() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: $_POST;

    $payerId = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$payerId) {
        jsonError('Укажите ID плательщика', 400);
    }

    try {
        dbExecute("UPDATE student_payers SET active = 0 WHERE id = ?", [$payerId]);
        jsonSuccess(['message' => 'Плательщик удален']);
    } catch (Exception $e) {
        jsonError('Ошибка удаления', 500);
    }
}

/**
 * Получить API токен
 */
function handleGetToken() {
    $token = getSetting('automate_api_token', '');

    if (empty($token)) {
        $token = bin2hex(random_bytes(16));
        setSetting('automate_api_token', $token);
    }

    jsonSuccess(['token' => $token]);
}

/**
 * Сгенерировать новый токен
 */
function handleRegenerateToken() {
    $token = bin2hex(random_bytes(16));
    setSetting('automate_api_token', $token);

    logAudit('api_token_regenerated', 'settings', null, null, null, 'API токен перегенерирован');

    jsonSuccess(['token' => $token]);
}

/**
 * Добавить ручной/наличный платёж
 */
function handleAddCash() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: $_POST;

    $studentId = filter_var($data['student_id'] ?? 0, FILTER_VALIDATE_INT);
    $senderName = trim($data['sender_name'] ?? 'Ученик');
    $amount = filter_var($data['amount'] ?? 0, FILTER_VALIDATE_INT);
    $paymentDate = trim($data['payment_date'] ?? date('Y-m-d'));
    $bankName = trim($data['bank_name'] ?? 'Наличные');
    $notes = trim($data['notes'] ?? '');
    $month = trim($data['month'] ?? date('Y-m'));

    if (!$studentId) {
        jsonError('Выберите ученика', 400);
    }

    if (!$amount || $amount < 1) {
        jsonError('Укажите сумму', 400);
    }

    // Получаем имя ученика для отображения
    $student = dbQueryOne("SELECT name FROM students WHERE id = ?", [$studentId]);
    if (!$student) {
        jsonError('Ученик не найден', 404);
    }

    // Если имя плательщика не указано, используем имя ученика
    if ($senderName === 'Ученик' || empty($senderName)) {
        $senderName = $student['name'];
    }

    try {
        $paymentId = dbExecute(
            "INSERT INTO incoming_payments
             (student_id, sender_name, amount, bank_name, raw_notification,
              status, month, match_confidence, matched_by, notes, received_at)
             VALUES (?, ?, ?, ?, ?, 'confirmed', ?, 100, 'manual', ?, ?)",
            [
                $studentId,
                $senderName,
                $amount,
                $bankName,
                'Ручной ввод: ' . $bankName,
                $month,
                $notes ?: null,
                $paymentDate . ' ' . date('H:i:s')
            ]
        );

        if ($paymentId) {
            logAudit('cash_payment_added', 'incoming_payment', $paymentId, null, [
                'student_id' => $studentId,
                'amount' => $amount,
                'type' => $bankName
            ], 'Добавлен ручной платёж');

            jsonSuccess(['id' => $paymentId, 'message' => 'Платёж добавлен']);
        } else {
            jsonError('Ошибка сохранения', 500);
        }
    } catch (Exception $e) {
        error_log("Failed to add cash payment: " . $e->getMessage());
        jsonError('Ошибка базы данных', 500);
    }
}

/**
 * Удалить ручной платёж
 */
function handleDeleteCash() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: $_POST;

    $paymentId = filter_var($data['payment_id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$paymentId) {
        jsonError('Укажите ID платежа', 400);
    }

    // Проверяем, что это ручной платёж
    $payment = dbQueryOne(
        "SELECT * FROM incoming_payments WHERE id = ? AND bank_name IN ('Наличные', 'Ручной ввод', 'Другое')",
        [$paymentId]
    );

    if (!$payment) {
        jsonError('Платёж не найден или не является ручным', 404);
    }

    try {
        dbExecute("DELETE FROM incoming_payments WHERE id = ?", [$paymentId]);

        logAudit('cash_payment_deleted', 'incoming_payment', $paymentId, [
            'amount' => $payment['amount'],
            'student_id' => $payment['student_id']
        ], null, 'Удалён ручной платёж');

        jsonSuccess(['message' => 'Платёж удален']);
    } catch (Exception $e) {
        error_log("Failed to delete cash payment: " . $e->getMessage());
        jsonError('Ошибка удаления', 500);
    }
}
