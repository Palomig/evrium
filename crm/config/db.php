<?php
/**
 * Evrium CRM - Database Configuration
 * Подключение к базе данных MySQL
 */

// Параметры подключения к БД
define('DB_HOST', 'localhost');
define('DB_NAME', 'evrium_crm');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Получить подключение к базе данных
 * @return PDO|null
 */
function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            return null;
        }
    }

    return $pdo;
}

/**
 * Выполнить запрос SELECT
 * @param string $query SQL запрос
 * @param array $params Параметры запроса
 * @return array|false
 */
function dbQuery($query, $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        return false;
    }
}

/**
 * Выполнить запрос SELECT и получить одну строку
 * @param string $query SQL запрос
 * @param array $params Параметры запроса
 * @return array|false
 */
function dbQueryOne($query, $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        return false;
    }
}

/**
 * Выполнить INSERT/UPDATE/DELETE запрос
 * @param string $query SQL запрос
 * @param array $params Параметры запроса
 * @return int|false ID последней вставленной записи или количество затронутых строк
 */
function dbExecute($query, $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        // Если это INSERT, возвращаем ID
        if (stripos($query, 'INSERT') === 0) {
            return $pdo->lastInsertId();
        }

        // Иначе возвращаем количество затронутых строк
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Execute error: " . $e->getMessage());
        return false;
    }
}

/**
 * Начать транзакцию
 */
function dbBeginTransaction() {
    $pdo = getDBConnection();
    if ($pdo) {
        return $pdo->beginTransaction();
    }
    return false;
}

/**
 * Зафиксировать транзакцию
 */
function dbCommit() {
    $pdo = getDBConnection();
    if ($pdo) {
        return $pdo->commit();
    }
    return false;
}

/**
 * Откатить транзакцию
 */
function dbRollback() {
    $pdo = getDBConnection();
    if ($pdo) {
        return $pdo->rollBack();
    }
    return false;
}
