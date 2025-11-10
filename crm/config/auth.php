<?php
/**
 * Evrium CRM - Authentication System
 * Система авторизации и управления сессиями
 */

require_once __DIR__ . '/db.php';

// Настройки сессии
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Установите 1 для HTTPS
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Проверить, авторизован ли пользователь
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Получить ID текущего пользователя
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
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
 * Проверить, является ли пользователь супер-админом
 * @return bool
 */
function isSuperAdmin() {
    return getCurrentUserRole() === 'superadmin';
}

/**
 * Проверить, является ли пользователь преподавателем
 * @return bool
 */
function isTeacher() {
    return getCurrentUserRole() === 'teacher';
}

/**
 * Авторизация пользователя
 * @param string $username Имя пользователя
 * @param string $password Пароль
 * @return array ['success' => bool, 'message' => string]
 */
function login($username, $password) {
    $user = dbQueryOne(
        "SELECT * FROM admins WHERE username = ? AND active = 1",
        [$username]
    );

    if (!$user) {
        return ['success' => false, 'message' => 'Неверное имя пользователя или пароль'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Неверное имя пользователя или пароль'];
    }

    // Регенерация ID сессии для безопасности
    session_regenerate_id(true);

    // Сохранение данных пользователя в сессии
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = time();

    return ['success' => true, 'message' => 'Вход выполнен успешно'];
}

/**
 * Выход из системы
 */
function logout() {
    $_SESSION = [];

    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    session_destroy();
}

/**
 * Проверить доступ к ресурсу
 * @param string $requiredRole Требуемая роль (teacher, superadmin)
 * @return bool
 */
function checkAccess($requiredRole = null) {
    if (!isLoggedIn()) {
        return false;
    }

    if ($requiredRole === null) {
        return true;
    }

    $currentRole = getCurrentUserRole();

    // Супер-админ имеет доступ ко всему
    if ($currentRole === 'superadmin') {
        return true;
    }

    // Проверка конкретной роли
    return $currentRole === $requiredRole;
}

/**
 * Требовать авторизацию (редирект на страницу входа)
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /crm/login.php');
        exit;
    }
}

/**
 * Требовать определенную роль
 * @param string $role
 */
function requireRole($role) {
    requireLogin();

    if (!checkAccess($role)) {
        http_response_code(403);
        die('Доступ запрещен');
    }
}

/**
 * Генерация CSRF токена
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Проверка CSRF токена
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Генерация API токена
 * @param int $adminId ID администратора
 * @param int $expiresIn Время жизни токена в секундах (по умолчанию 30 дней)
 * @return string|false
 */
function generateAPIToken($adminId, $expiresIn = 2592000) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

    $result = dbExecute(
        "INSERT INTO api_tokens (admin_id, token, expires_at) VALUES (?, ?, ?)",
        [$adminId, $token, $expiresAt]
    );

    return $result ? $token : false;
}

/**
 * Проверка API токена
 * @param string $token
 * @return array|false Данные администратора или false
 */
function validateAPIToken($token) {
    $result = dbQueryOne(
        "SELECT a.* FROM admins a
         INNER JOIN api_tokens t ON a.id = t.admin_id
         WHERE t.token = ? AND t.expires_at > NOW() AND a.active = 1",
        [$token]
    );

    return $result ?: false;
}

/**
 * Получить токен из заголовка Authorization
 * @return string|null
 */
function getBearerToken() {
    $headers = getallheaders();

    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }

    return null;
}

/**
 * Проверить владельца ресурса
 * @param int $resourceTeacherId ID преподавателя-владельца ресурса
 * @return bool
 */
function checkResourceOwner($resourceTeacherId) {
    if (isSuperAdmin()) {
        return true; // Супер-админ имеет доступ ко всему
    }

    return getCurrentUserId() == $resourceTeacherId;
}
