<?php
/**
 * Модуль аутентификации и авторизации
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/db.php';

// Сколько хранить вход на устройстве (cookie сессии и токен «запомнить меня»)
define('REMEMBER_LIFETIME', 60 * 60 * 24 * 30); // 30 дней
define('REMEMBER_COOKIE', 'zp_remember');

// Инициализация сессии с долгоживущей cookie, чтобы вход не слетал
// при закрытии браузера и переживал чистку сессий на шаредхостинге
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.gc_maxlifetime', (string)REMEMBER_LIFETIME);
    session_set_cookie_params([
        'lifetime' => REMEMBER_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isHttpsRequest(),
    ]);
    session_start();
}

// Сессии нет, но устройство «запомнено» — восстановить вход по токену
tryRememberLogin();

/**
 * Запрос пришёл по HTTPS (с учётом прокси хостинга)
 * @return bool
 */
function isHttpsRequest() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
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

    // SELECT * — устойчиво к появлению новых колонок (teacher_id, can_dashboard)
    $user = dbQueryOne("SELECT * FROM users WHERE id = ?", [getCurrentUserId()]);
    if ($user) {
        unset($user['password_hash']);
    }
    return $user;
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
 * Проверить, является ли пользователь преподавателем
 * @return bool
 */
function isTeacherUser() {
    return getCurrentUserRole() === 'teacher';
}

/**
 * ID преподавателя, к которому привязан текущий пользователь-учитель
 * @return int|null
 */
function getCurrentTeacherId() {
    $tid = $_SESSION['teacher_id'] ?? null;
    return $tid ? (int)$tid : null;
}

/**
 * Видит ли текущий пользователь дашборд.
 * admin/owner — да (если явно не выключено), teacher — только если включено.
 */
function canSeeDashboard() {
    if (!isLoggedIn()) {
        return false;
    }
    $flag = $_SESSION['can_dashboard'] ?? null; // NULL = по роли
    if ($flag !== null && $flag !== '') {
        return (int)$flag === 1;
    }
    return isAdmin();
}

/**
 * Требовать доступ к дашборду; преподавателей уводим на расписание
 * @param string $redirect Куда отправить без доступа
 */
function requireDashboardAccess($redirect = '/zarplata/planner.php') {
    requireAuth();
    if (!canSeeDashboard()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Миграция схемы users под роли преподавателей (одноразовая).
 * dbQuery глотает исключения, поэтому проверки через SHOW COLUMNS.
 */
function ensureUsersRolesSchema() {
    $teacherCol = dbQuery("SHOW COLUMNS FROM users LIKE 'teacher_id'", []);
    if (empty($teacherCol)) {
        dbExecute("ALTER TABLE users ADD COLUMN teacher_id INT NULL", []);
    }
    $dashCol = dbQuery("SHOW COLUMNS FROM users LIKE 'can_dashboard'", []);
    if (empty($dashCol)) {
        dbExecute("ALTER TABLE users ADD COLUMN can_dashboard TINYINT NULL", []);
    }
    $roleCol = dbQuery("SHOW COLUMNS FROM users LIKE 'role'", []);
    if (!empty($roleCol) && strpos($roleCol[0]['Type'] ?? '', 'teacher') === false) {
        dbExecute("ALTER TABLE users MODIFY COLUMN role ENUM('admin','owner','teacher') NOT NULL DEFAULT 'admin'", []);
    }
}

/**
 * Авторизовать пользователя
 * @param string $username Имя пользователя
 * @param string $password Пароль
 * @return bool Успешность авторизации
 */
function login($username, $password, $remember = false) {
    // Схема под роли преподавателей (одноразовая авто-миграция)
    ensureUsersRolesSchema();

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
    establishSession($user);

    // Запомнить устройство (постоянный токен)
    if ($remember) {
        issueRememberToken($user['id']);
    }

    // Логирование входа
    logAudit('user_login', 'user', $user['id'], null, null, 'Вход в систему');

    return true;
}

/**
 * Записать данные пользователя в текущую сессию
 * @param array $user Строка из таблицы users
 */
function establishSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['teacher_id'] = $user['teacher_id'] ?? null;
    $_SESSION['can_dashboard'] = $user['can_dashboard'] ?? null;
}

/**
 * Выйти из системы
 */
function logout() {
    // Логирование выхода
    if (isLoggedIn()) {
        logAudit('user_logout', 'user', getCurrentUserId(), null, null, 'Выход из системы');
    }

    // Удалить токен «запомнить меня» для этого устройства
    forgetRememberToken();

    // Очистить сессию
    $_SESSION = [];
    session_destroy();
}

// ============================================================
//  «Запомнить устройство» — постоянный токен (selector/validator)
// ============================================================

/**
 * Создать таблицу токенов, если её ещё нет (авто-миграция)
 */
function ensureRememberSchema() {
    try {
        $exists = dbQuery("SHOW TABLES LIKE 'remember_tokens'", []);
        if (empty($exists)) {
            dbExecute(
                "CREATE TABLE remember_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    selector CHAR(32) NOT NULL,
                    validator_hash CHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_selector (selector),
                    KEY idx_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                []
            );
        }
    } catch (Exception $e) {
        error_log('ensureRememberSchema failed: ' . $e->getMessage());
    }
}

/**
 * Выдать токен «запомнить меня» и поставить cookie на устройство
 * @param int $userId
 */
function issueRememberToken($userId) {
    ensureRememberSchema();

    $selector  = bin2hex(random_bytes(16)); // 32 hex
    $validator = bin2hex(random_bytes(32)); // 64 hex
    $expiresAt = date('Y-m-d H:i:s', time() + REMEMBER_LIFETIME);

    try {
        dbExecute(
            "INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at)
             VALUES (?, ?, ?, ?)",
            [$userId, $selector, hash('sha256', $validator), $expiresAt]
        );
    } catch (Exception $e) {
        error_log('issueRememberToken failed: ' . $e->getMessage());
        return;
    }

    setRememberCookie($selector . ':' . $validator, time() + REMEMBER_LIFETIME);
}

/**
 * Если активной сессии нет, но cookie токена валидна — восстановить вход
 */
function tryRememberLogin() {
    if (isLoggedIn()) {
        return;
    }
    if (empty($_COOKIE[REMEMBER_COOKIE])) {
        return;
    }

    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
    if (count($parts) !== 2) {
        clearRememberCookie();
        return;
    }
    list($selector, $validator) = $parts;

    $row = dbQueryOne("SELECT * FROM remember_tokens WHERE selector = ?", [$selector]);
    if (!$row) {
        clearRememberCookie();
        return;
    }

    // Просрочен — удалить и выйти
    if (strtotime($row['expires_at']) < time()) {
        dbExecute("DELETE FROM remember_tokens WHERE id = ?", [$row['id']]);
        clearRememberCookie();
        return;
    }

    // Сверка секрета в постоянном времени
    if (!hash_equals($row['validator_hash'], hash('sha256', $validator))) {
        clearRememberCookie();
        return;
    }

    // Пользователь ещё активен?
    $user = dbQueryOne("SELECT * FROM users WHERE id = ? AND active = 1", [$row['user_id']]);
    if (!$user) {
        dbExecute("DELETE FROM remember_tokens WHERE id = ?", [$row['id']]);
        clearRememberCookie();
        return;
    }

    // Восстановить сессию и продлить срок жизни токена (скользящее окно)
    establishSession($user);
    $newExpiry = date('Y-m-d H:i:s', time() + REMEMBER_LIFETIME);
    dbExecute("UPDATE remember_tokens SET expires_at = ? WHERE id = ?", [$newExpiry, $row['id']]);
    setRememberCookie($_COOKIE[REMEMBER_COOKIE], time() + REMEMBER_LIFETIME);
}

/**
 * Удалить токен текущего устройства из БД и стереть cookie
 */
function forgetRememberToken() {
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
        if (count($parts) === 2) {
            try {
                dbExecute("DELETE FROM remember_tokens WHERE selector = ?", [$parts[0]]);
            } catch (Exception $e) {
                error_log('forgetRememberToken failed: ' . $e->getMessage());
            }
        }
    }
    clearRememberCookie();
}

/**
 * Поставить cookie токена устройства
 */
function setRememberCookie($value, $expires) {
    setcookie(REMEMBER_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isHttpsRequest(),
    ]);
    $_COOKIE[REMEMBER_COOKIE] = $value;
}

/**
 * Стереть cookie токена устройства
 */
function clearRememberCookie() {
    setcookie(REMEMBER_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isHttpsRequest(),
    ]);
    unset($_COOKIE[REMEMBER_COOKIE]);
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
