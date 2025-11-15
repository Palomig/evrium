<?php
/**
 * Вспомогательные функции
 * Система учёта зарплаты преподавателей
 */

/**
 * Экранировать HTML сущности
 * @param string $text Текст для экранирования
 * @return string Экранированный текст
 */
function e($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Форматировать сумму в рублях
 * @param int|float $amount Сумма
 * @param bool $withSymbol Добавлять ли символ рубля
 * @return string Отформатированная сумма
 */
function formatMoney($amount, $withSymbol = true) {
    $formatted = number_format((float)$amount, 0, ',', ' ');
    return $withSymbol ? $formatted . '₽' : $formatted;
}

/**
 * Форматировать дату
 * @param string $date Дата в формате SQL
 * @param string $format Формат вывода
 * @return string Отформатированная дата
 */
function formatDate($date, $format = 'd.m.Y') {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '—';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '—';
}

/**
 * Форматировать дату и время
 * @param string $datetime Дата-время в формате SQL
 * @return string Отформатированная дата-время
 */
function formatDateTime($datetime) {
    return formatDate($datetime, 'd.m.Y H:i');
}

/**
 * Форматировать время
 * @param string $time Время в формате SQL
 * @return string Отформатированное время
 */
function formatTime($time) {
    if (empty($time)) {
        return '—';
    }

    $parts = explode(':', $time);
    return sprintf('%02d:%02d', $parts[0], $parts[1]);
}

/**
 * Получить название дня недели по номеру
 * @param int $dayOfWeek Номер дня недели (1=Пн, 7=Вс)
 * @param bool $short Короткое название
 * @return string Название дня
 */
function getDayName($dayOfWeek, $short = false) {
    $days = [
        1 => ['full' => 'Понедельник', 'short' => 'Пн'],
        2 => ['full' => 'Вторник', 'short' => 'Вт'],
        3 => ['full' => 'Среда', 'short' => 'Ср'],
        4 => ['full' => 'Четверг', 'short' => 'Чт'],
        5 => ['full' => 'Пятница', 'short' => 'Пт'],
        6 => ['full' => 'Суббота', 'short' => 'Сб'],
        7 => ['full' => 'Воскресенье', 'short' => 'Вс'],
    ];

    $day = $days[$dayOfWeek] ?? ['full' => '—', 'short' => '—'];
    return $short ? $day['short'] : $day['full'];
}

/**
 * Получить название месяца по номеру
 * @param int $month Номер месяца (1-12)
 * @param string $case Падеж ('nominative', 'genitive')
 * @return string Название месяца
 */
function getMonthName($month, $case = 'nominative') {
    $months = [
        'nominative' => [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
            5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
            9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
        ],
        'genitive' => [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ]
    ];

    return $months[$case][$month] ?? '—';
}

/**
 * Получить статус урока с иконкой
 * @param string $status Статус урока
 * @return array ['text' => 'Текст', 'class' => 'CSS класс', 'icon' => 'Material Icon']
 */
function getLessonStatusBadge($status) {
    $statuses = [
        'scheduled' => [
            'text' => 'Запланирован',
            'class' => 'info',
            'icon' => 'schedule'
        ],
        'completed' => [
            'text' => 'Завершён',
            'class' => 'success',
            'icon' => 'check_circle'
        ],
        'cancelled' => [
            'text' => 'Отменён',
            'class' => 'danger',
            'icon' => 'cancel'
        ],
        'rescheduled' => [
            'text' => 'Перенесён',
            'class' => 'warning',
            'icon' => 'update'
        ]
    ];

    return $statuses[$status] ?? [
        'text' => 'Неизвестно',
        'class' => 'secondary',
        'icon' => 'help'
    ];
}

/**
 * Получить статус оплаты с иконкой
 * @param string $status Статус оплаты
 * @return array ['text' => 'Текст', 'class' => 'CSS класс', 'icon' => 'Material Icon']
 */
function getPaymentStatusBadge($status) {
    $statuses = [
        'pending' => [
            'text' => 'Ожидает',
            'class' => 'warning',
            'icon' => 'pending'
        ],
        'approved' => [
            'text' => 'Одобрено',
            'class' => 'info',
            'icon' => 'thumb_up'
        ],
        'paid' => [
            'text' => 'Выплачено',
            'class' => 'success',
            'icon' => 'payments'
        ],
        'cancelled' => [
            'text' => 'Отменено',
            'class' => 'danger',
            'icon' => 'block'
        ]
    ];

    return $statuses[$status] ?? [
        'text' => 'Неизвестно',
        'class' => 'secondary',
        'icon' => 'help'
    ];
}

/**
 * Валидация email
 * @param string $email Email адрес
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Валидация телефона (российский формат)
 * @param string $phone Номер телефона
 * @return bool
 */
function isValidPhone($phone) {
    $cleaned = preg_replace('/\D/', '', $phone);
    return preg_match('/^[78]\d{10}$/', $cleaned);
}

/**
 * Форматировать телефон
 * @param string $phone Номер телефона
 * @return string Отформатированный номер
 */
function formatPhone($phone) {
    $cleaned = preg_replace('/\D/', '', $phone);

    if (preg_match('/^([78])(\d{3})(\d{3})(\d{2})(\d{2})$/', $cleaned, $matches)) {
        return sprintf('+%s (%s) %s-%s-%s', $matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
    }

    return $phone;
}

/**
 * Получить JSON ответ (для API)
 * @param mixed $data Данные для ответа
 * @param int $statusCode HTTP код
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Получить JSON ответ с ошибкой
 * @param string $message Сообщение об ошибке
 * @param int $statusCode HTTP код
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse(['success' => false, 'error' => $message], $statusCode);
}

/**
 * Получить JSON ответ с успехом
 * @param mixed $data Данные для ответа
 */
function jsonSuccess($data) {
    jsonResponse(['success' => true, 'data' => $data], 200);
}

/**
 * Редирект
 * @param string $url URL для редиректа
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Установить flash сообщение
 * @param string $type Тип сообщения (success, error, info, warning)
 * @param string $message Текст сообщения
 */
function setFlash($type, $message) {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Получить и очистить flash сообщения
 * @return array Массив сообщений
 */
function getFlash() {
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Вычислить оплату по формуле
 * @param array $formula Формула оплаты
 * @param int $studentCount Количество студентов
 * @return int Сумма оплаты
 */
function calculatePayment($formula, $studentCount) {
    if (!$formula || !$formula['active']) {
        return 0;
    }

    switch ($formula['type']) {
        case 'min_plus_per':
            $minPayment = (int)$formula['min_payment'];
            $perStudent = (int)$formula['per_student'];
            $threshold = (int)$formula['threshold'];

            if ($studentCount >= $threshold) {
                return $minPayment + (($studentCount - $threshold + 1) * $perStudent);
            }
            return $minPayment;

        case 'fixed':
            return (int)$formula['fixed_amount'];

        case 'expression':
            // Простой парсер выражений (только базовые операции)
            $expr = $formula['expression'];
            $expr = str_replace('N', $studentCount, $expr);
            $expr = str_replace('min', $formula['min_payment'] ?? 0, $expr);

            // Безопасное вычисление (только числа и операторы)
            if (preg_match('/^[\d\s\+\-\*\/\(\)\.]+$/', $expr)) {
                try {
                    return (int)eval("return $expr;");
                } catch (Exception $e) {
                    error_log("Formula evaluation error: " . $e->getMessage());
                    return 0;
                }
            }
            return 0;

        default:
            return 0;
    }
}

/**
 * Получить дату начала недели
 * @param string $date Дата в формате SQL
 * @return string Дата начала недели
 */
function getWeekStart($date = null) {
    $timestamp = $date ? strtotime($date) : time();
    $dayOfWeek = date('N', $timestamp); // 1=Пн, 7=Вс

    $daysToSubtract = $dayOfWeek - 1;
    return date('Y-m-d', strtotime("-$daysToSubtract days", $timestamp));
}

/**
 * Получить дату конца недели
 * @param string $date Дата в формате SQL
 * @return string Дата конца недели
 */
function getWeekEnd($date = null) {
    $weekStart = getWeekStart($date);
    return date('Y-m-d', strtotime('+6 days', strtotime($weekStart)));
}

/**
 * Получить массив дат недели
 * @param string $date Дата в формате SQL
 * @return array Массив дат ['2025-11-15', '2025-11-16', ...]
 */
function getWeekDates($date = null) {
    $weekStart = getWeekStart($date);
    $dates = [];

    for ($i = 0; $i < 7; $i++) {
        $dates[] = date('Y-m-d', strtotime("+$i days", strtotime($weekStart)));
    }

    return $dates;
}

/**
 * Проверить, является ли дата сегодня
 * @param string $date Дата в формате SQL
 * @return bool
 */
function isToday($date) {
    return date('Y-m-d', strtotime($date)) === date('Y-m-d');
}

/**
 * Получить относительное время ("сегодня", "вчера", "3 дня назад")
 * @param string $datetime Дата-время в формате SQL
 * @return string
 */
function getRelativeTime($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'только что';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' ' . plural($minutes, 'минуту', 'минуты', 'минут') . ' назад';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ' . plural($hours, 'час', 'часа', 'часов') . ' назад';
    } elseif (isToday($datetime)) {
        return 'сегодня в ' . date('H:i', $timestamp);
    } elseif (date('Y-m-d', $timestamp) === date('Y-m-d', strtotime('-1 day'))) {
        return 'вчера в ' . date('H:i', $timestamp);
    } else {
        return formatDateTime($datetime);
    }
}

/**
 * Плюрализация (1 день, 2 дня, 5 дней)
 * @param int $number Число
 * @param string $one Форма для 1
 * @param string $few Форма для 2-4
 * @param string $many Форма для 5+
 * @return string
 */
function plural($number, $one, $few, $many) {
    $number = abs($number);
    $mod10 = $number % 10;
    $mod100 = $number % 100;

    if ($mod10 === 1 && $mod100 !== 11) {
        return $one;
    } elseif ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
        return $few;
    } else {
        return $many;
    }
}

/**
 * Усечь текст до определённой длины
 * @param string $text Текст
 * @param int $length Максимальная длина
 * @param string $suffix Суффикс (...)
 * @return string
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Получить badge для действия аудита
 * @param string $action Действие
 * @return array ['text' => 'Текст', 'class' => 'CSS класс', 'icon' => 'Material Icon']
 */
function getActionBadge($action) {
    // Общие паттерны действий
    if (strpos($action, 'created') !== false) {
        return ['text' => 'Создано', 'class' => 'success', 'icon' => 'add_circle'];
    } elseif (strpos($action, 'updated') !== false) {
        return ['text' => 'Изменено', 'class' => 'info', 'icon' => 'edit'];
    } elseif (strpos($action, 'deleted') !== false || strpos($action, 'deactivated') !== false) {
        return ['text' => 'Удалено', 'class' => 'danger', 'icon' => 'delete'];
    } elseif (strpos($action, 'activated') !== false) {
        return ['text' => 'Активировано', 'class' => 'success', 'icon' => 'check_circle'];
    } elseif (strpos($action, 'completed') !== false) {
        return ['text' => 'Завершено', 'class' => 'success', 'icon' => 'done'];
    } elseif (strpos($action, 'cancelled') !== false) {
        return ['text' => 'Отменено', 'class' => 'warning', 'icon' => 'cancel'];
    } elseif (strpos($action, 'approved') !== false) {
        return ['text' => 'Одобрено', 'class' => 'info', 'icon' => 'thumb_up'];
    } elseif (strpos($action, 'login') !== false) {
        return ['text' => 'Вход', 'class' => 'info', 'icon' => 'login'];
    } elseif (strpos($action, 'logout') !== false) {
        return ['text' => 'Выход', 'class' => 'secondary', 'icon' => 'logout'];
    } else {
        return ['text' => ucfirst($action), 'class' => 'secondary', 'icon' => 'info'];
    }
}
