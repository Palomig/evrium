<?php
/**
 * Модуль аутентификации и авторизации
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/db.php';

// Инициализация сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Проверить, авторизован ли пользователь
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Получить ID текущего пользователя
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Получить данные текущего пользователя
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    return dbQueryOne(
        "SELECT id, username, name, email, role, active FROM users WHERE id = ?",
        [getCurrentUserId()]
    );
}

/**
 * Получить роль текущего пользователя
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Получить имя текущего пользователя
 * @return string|null
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Проверить, является ли пользователь владельцем
 * @return bool
 */
function isOwner() {
    return getCurrentUserRole() === 'owner';
}

/**
 * Проверить, является ли пользователь администратором
 * @return bool
 */
function isAdmin() {
    $role = getCurrentUserRole();
    return $role === 'admin' || $role === 'owner';
}

/**
 * Авторизовать пользователя
 * @param string $username Имя пользователя
 * @param string $password Пароль
 * @return bool Успешность авторизации
 */
function login($username, $password) {
    // Получить пользователя из БД
    $user = dbQueryOne(
        "SELECT * FROM users WHERE username = ? AND active = 1",
        [$username]
    );

    if (!$user) {
        return false;
    }

    // Проверить пароль
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Установить сессию
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];

    // Логирование входа
    logAudit('user_login', 'user', $user['id'], null, null, 'Вход в систему');

    return true;
}

/**
 * Выйти из системы
 */
function logout() {
    // Логирование выхода
    if (isLoggedIn()) {
        logAudit('user_logout', 'user', getCurrentUserId(), null, null, 'Выход из системы');
    }

    // Очистить сессию
    $_SESSION = [];
    session_destroy();
}

/**
 * Требовать авторизацию (редирект на login.php если не авторизован)
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: /zarplata/login.php');
        exit;
    }
}

/**
 * Требовать роль владельца (403 если не владелец)
 */
function requireOwner() {
    requireAuth();

    if (!isOwner()) {
        http_response_code(403);
        die('Доступ запрещён. Требуется роль владельца.');
    }
}

/**
 * Требовать роль администратора (403 если не админ)
 */
function requireAdmin() {
    requireAuth();

    if (!isAdmin()) {
        http_response_code(403);
        die('Доступ запрещён. Требуется роль администратора.');
    }
}

/**
 * Проверить принадлежность ресурса пользователю
 * @param int $userId ID пользователя владельца ресурса
 * @return bool
 */
function checkResourceOwner($userId) {
    // Владелец видит всё
    if (isOwner()) {
        return true;
    }

    // Обычный пользователь видит только свои ресурсы
    return getCurrentUserId() === (int)$userId;
}

/**
 * Сгенерировать CSRF токен
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Проверить CSRF токен
 * @param string $token Токен для проверки
 * @return bool
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Требовать валидный CSRF токен (403 если невалиден)
 */
function requireCSRF() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        http_response_code(403);
        die('Невалидный CSRF токен');
    }
}

/**
 * Записать событие в журнал аудита
 * @param string $actionType Тип действия
 * @param string $entityType Тип сущности
 * @param int|null $entityId ID сущности
 * @param mixed $oldValue Старое значение
 * @param mixed $newValue Новое значение
 * @param string|null $notes Примечания
 */
function logAudit($actionType, $entityType, $entityId = null, $oldValue = null, $newValue = null, $notes = null) {
    $userId = getCurrentUserId();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    // Конвертируем значения в JSON если они массивы/объекты
    $oldValueJson = is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue) : $oldValue;
    $newValueJson = is_array($newValue) || is_object($newValue) ? json_encode($newValue) : $newValue;

    dbExecute(
        "INSERT INTO audit_log (action_type, entity_type, entity_id, user_id, old_value, new_value, notes, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$actionType, $entityType, $entityId, $userId, $oldValueJson, $newValueJson, $notes, $ipAddress]
    );
}

/**
 * Хешировать пароль
 * @param string $password Пароль в открытом виде
 * @return string Хеш пароля
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Создать нового пользователя
 * @param string $username Имя пользователя
 * @param string $password Пароль
 * @param string $name Полное имя
 * @param string $role Роль (admin/owner)
 * @param string|null $email Email
 * @return int|bool ID созданного пользователя или false
 */
function createUser($username, $password, $name, $role = 'admin', $email = null) {
    // Проверить существование пользователя
    $existing = dbQueryOne("SELECT id FROM users WHERE username = ?", [$username]);
    if ($existing) {
        return false;
    }

    // Создать пользователя
    $passwordHash = hashPassword($password);
    $userId = dbExecute(
        "INSERT INTO users (username, password_hash, name, role, email, active)
         VALUES (?, ?, ?, ?, ?, 1)",
        [$username, $passwordHash, $name, $role, $email]
    );

    if ($userId) {
        logAudit('user_created', 'user', $userId, null, ['username' => $username, 'role' => $role], 'Создан новый пользователь');
    }

    return $userId;
}

/**
 * Изменить пароль пользователя
 * @param int $userId ID пользователя
 * @param string $newPassword Новый пароль
 * @return bool Успешность операции
 */
function changePassword($userId, $newPassword) {
    $passwordHash = hashPassword($newPassword);

    $result = dbExecute(
        "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
        [$passwordHash, $userId]
    );

    if ($result) {
        logAudit('password_changed', 'user', $userId, null, null, 'Пароль изменён');
    }

    return $result > 0;
}
