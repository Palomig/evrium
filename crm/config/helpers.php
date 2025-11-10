<?php
/**
 * Evrium CRM - Helper Functions
 * Вспомогательные функции
 */

/**
 * Безопасный вывод HTML
 * @param string $string
 * @return string
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Редирект
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * JSON ответ
 * @param mixed $data
 * @param int $status HTTP статус код
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Успешный JSON ответ
 * @param mixed $data
 * @param string $message
 */
function jsonSuccess($data = null, $message = 'Success') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * JSON ответ с ошибкой
 * @param string $message
 * @param int $status
 */
function jsonError($message, $status = 400) {
    jsonResponse([
        'success' => false,
        'message' => $message
    ], $status);
}

/**
 * Валидация email
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Валидация телефона
 * @param string $phone
 * @return bool
 */
function isValidPhone($phone) {
    return preg_match('/^[0-9+\-\s()]{7,20}$/', $phone);
}

/**
 * Форматирование даты
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'd.m.Y') {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Форматирование даты и времени
 * @param string $datetime
 * @param string $format
 * @return string
 */
function formatDateTime($datetime, $format = 'd.m.Y H:i') {
    if (empty($datetime)) return '';
    $timestamp = strtotime($datetime);
    return date($format, $timestamp);
}

/**
 * Форматирование суммы
 * @param float $amount
 * @param string $currency
 * @return string
 */
function formatMoney($amount, $currency = '₽') {
    return number_format($amount, 2, '.', ' ') . ' ' . $currency;
}

/**
 * Получить количество дней между датами
 * @param string $date1
 * @param string $date2
 * @return int
 */
function daysBetween($date1, $date2) {
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    $interval = $datetime1->diff($datetime2);
    return (int)$interval->days;
}

/**
 * Загрузка файла
 * @param array $file $_FILES элемент
 * @param string $uploadDir Директория загрузки
 * @param array $allowedTypes Разрешенные типы файлов
 * @param int $maxSize Максимальный размер в байтах
 * @return array ['success' => bool, 'file' => string, 'message' => string]
 */
function uploadFile($file, $uploadDir, $allowedTypes = [], $maxSize = 5242880) {
    // Проверка на ошибки загрузки
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Ошибка при загрузке файла'];
    }

    // Проверка размера
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Файл слишком большой'];
    }

    // Получение расширения файла
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Проверка типа файла
    if (!empty($allowedTypes) && !in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Недопустимый тип файла'];
    }

    // Генерация уникального имени файла
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . '/' . $filename;

    // Создание директории если не существует
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Перемещение файла
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'file' => $filename, 'message' => 'Файл успешно загружен'];
    }

    return ['success' => false, 'message' => 'Не удалось сохранить файл'];
}

/**
 * Удаление файла
 * @param string $filepath
 * @return bool
 */
function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Получить параметр GET
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getParam($key, $default = null) {
    return $_GET[$key] ?? $default;
}

/**
 * Получить параметр POST
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function postParam($key, $default = null) {
    return $_POST[$key] ?? $default;
}

/**
 * Получить все параметры POST
 * @return array
 */
function postParams() {
    return $_POST;
}

/**
 * Получить JSON из тела запроса
 * @return array|null
 */
function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true);
}

/**
 * Генерация случайной строки
 * @param int $length
 * @return string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Обрезка строки
 * @param string $string
 * @param int $length
 * @param string $suffix
 * @return string
 */
function truncate($string, $length = 100, $suffix = '...') {
    if (mb_strlen($string) <= $length) {
        return $string;
    }
    return mb_substr($string, 0, $length) . $suffix;
}

/**
 * Получить IP адрес клиента
 * @return string
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Логирование
 * @param string $message
 * @param string $level
 */
function logMessage($message, $level = 'INFO') {
    $logFile = __DIR__ . '/../logs/app.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Валидация обязательных полей
 * @param array $data
 * @param array $required
 * @return array ['valid' => bool, 'missing' => array]
 */
function validateRequired($data, $required) {
    $missing = [];

    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }

    return [
        'valid' => empty($missing),
        'missing' => $missing
    ];
}

/**
 * Пагинация
 * @param int $total Всего записей
 * @param int $perPage Записей на странице
 * @param int $currentPage Текущая страница
 * @return array
 */
function paginate($total, $perPage = 20, $currentPage = 1) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Получить название месяца на русском
 * @param int $month
 * @return string
 */
function getMonthName($month) {
    $months = [
        1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
        5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
        9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
    ];
    return $months[$month] ?? '';
}

/**
 * Получить статус ученика в виде badge
 * @param string $status
 * @return string
 */
function getStatusBadge($status) {
    $badges = [
        'оплачено' => '<span class="badge bg-success">Оплачено</span>',
        'ожидает' => '<span class="badge bg-warning">Ожидает</span>',
        'задолженность' => '<span class="badge bg-danger">Задолженность</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . e($status) . '</span>';
}

/**
 * Отладка (дамп переменной)
 * @param mixed $var
 * @param bool $die
 */
function dd($var, $die = true) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    if ($die) die();
}
