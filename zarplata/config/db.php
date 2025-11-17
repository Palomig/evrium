<?php
/**
 * Конфигурация базы данных
 * Система учёта зарплаты преподавателей
 */

// Настройки подключения к БД
define('DB_HOST', 'localhost');
define('DB_NAME', 'cw95865_admin');
define('DB_USER', 'cw95865_admin');
define('DB_PASS', '123456789');
define('DB_CHARSET', 'utf8mb4');

// Глобальное подключение PDO
$pdo = null;

/**
 * Получить подключение к базе данных
 * @return PDO
 */
function getDB() {
    global $pdo;

    if ($pdo === null) {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Ошибка подключения к базе данных");
        }
    }

    return $pdo;
}

/**
 * Выполнить SELECT запрос и вернуть все строки
 * @param string $sql SQL запрос с плейсхолдерами
 * @param array $params Параметры для подстановки
 * @return array Массив результатов
 */
function dbQuery($sql, $params = []) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Query failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Выполнить SELECT запрос и вернуть первую строку
 * @param string $sql SQL запрос с плейсхолдерами
 * @param array $params Параметры для подстановки
 * @return array|null Первая строка результата или null
 */
function dbQueryOne($sql, $params = []) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Query failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Выполнить INSERT/UPDATE/DELETE запрос
 * @param string $sql SQL запрос с плейсхолдерами
 * @param array $params Параметры для подстановки
 * @return int ID последней вставленной записи или количество затронутых строк
 */
function dbExecute($sql, $params = []) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Для INSERT возвращаем lastInsertId, для других - количество строк
        $lastId = $pdo->lastInsertId();

        // Если это INSERT (lastInsertId > 0), возвращаем ID
        if ($lastId && $lastId !== '0') {
            return (int)$lastId;
        }

        // Иначе возвращаем количество затронутых строк
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Execute failed: " . $e->getMessage());
        throw $e; // Пробрасываем исключение вместо возврата 0
    }
}

/**
 * Начать транзакцию
 */
function dbBeginTransaction() {
    $pdo = getDB();
    $pdo->beginTransaction();
}

/**
 * Закоммитить транзакцию
 */
function dbCommit() {
    $pdo = getDB();
    $pdo->commit();
}

/**
 * Откатить транзакцию
 */
function dbRollback() {
    $pdo = getDB();
    $pdo->rollBack();
}

/**
 * Экранировать значение для LIKE запроса
 * @param string $value Значение для экранирования
 * @return string Экранированное значение
 */
function escapeLike($value) {
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

/**
 * Получить настройку из таблицы settings
 * @param string $key Ключ настройки
 * @param mixed $default Значение по умолчанию
 * @return mixed Значение настройки
 */
function getSetting($key, $default = null) {
    $result = dbQueryOne(
        "SELECT setting_value FROM settings WHERE setting_key = ?",
        [$key]
    );

    return $result ? $result['setting_value'] : $default;
}

/**
 * Установить настройку в таблице settings
 * @param string $key Ключ настройки
 * @param mixed $value Значение настройки
 * @return bool Успешность операции
 */
function setSetting($key, $value) {
    $exists = dbQueryOne(
        "SELECT id FROM settings WHERE setting_key = ?",
        [$key]
    );

    if ($exists) {
        return dbExecute(
            "UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
            [$value, $key]
        ) > 0;
    } else {
        return dbExecute(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)",
            [$key, $value]
        ) > 0;
    }
}
