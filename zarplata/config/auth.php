<?php
/**
 * Модуль аутентификации и авторизации
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/device_auth.php';

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
    return can('dashboard');
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
    ensureTelegramAuthSchema();
}

/**
 * Миграция под вход через Telegram и доступы по разделам (одноразовая).
 * users.telegram_id — кто может войти через виджет; users.permissions — JSON
 * переопределений по разделам (NULL = по умолчанию для роли).
 */
function ensureTelegramAuthSchema() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $tgCol = dbQuery("SHOW COLUMNS FROM users LIKE 'telegram_id'", []);
    if (empty($tgCol)) {
        dbExecute("ALTER TABLE users ADD COLUMN telegram_id BIGINT NULL, ADD UNIQUE KEY uniq_users_telegram (telegram_id)", []);
    }
    $tgUserCol = dbQuery("SHOW COLUMNS FROM users LIKE 'telegram_username'", []);
    if (empty($tgUserCol)) {
        dbExecute("ALTER TABLE users ADD COLUMN telegram_username VARCHAR(64) NULL", []);
    }
    $permCol = dbQuery("SHOW COLUMNS FROM users LIKE 'permissions'", []);
    if (empty($permCol)) {
        dbExecute("ALTER TABLE users ADD COLUMN permissions TEXT NULL", []);
    }

    // Стас (@Palomig) — владелец: привязываем, пока ни у кого нет Telegram
    $anyLinked = dbQueryOne("SELECT id FROM users WHERE telegram_id IS NOT NULL LIMIT 1", []);
    if (!$anyLinked) {
        $owner = dbQueryOne("SELECT id FROM users WHERE role = 'owner' AND active = 1 ORDER BY id LIMIT 1", []);
        if ($owner) {
            dbExecute("UPDATE users SET telegram_id = ?, telegram_username = ? WHERE id = ?", [245710727, 'Palomig', $owner['id']]);
        }
    }
}

// ============================================================
//  Доступы по разделам
// ============================================================

/**
 * Разделы панели, которые можно включать/выключать пользователю.
 * Расписание, уроки, посещаемость, своя зарплата и смена пароля доступны всем.
 * @return array key => подпись
 */
function zpSections() {
    return [
        'dashboard'        => 'Главная',
        'students'         => 'Ученики',
        'payments'         => 'Выплаты',
        'student_payments' => 'Оплаты учеников',
        'formulas'         => 'Формулы',
        'reports'          => 'Отчёты',
        'teachers'         => 'Преподаватели',
        'audit'            => 'Аудит',
        'settings'         => 'Настройки',
    ];
}

/**
 * Доступы по умолчанию для роли: owner — всё, admin — всё кроме настроек,
 * teacher — ничего из административных разделов.
 * @param string $role
 * @return array key => bool
 */
function zpRoleDefaults($role) {
    $all = array_fill_keys(array_keys(zpSections()), false);
    if ($role === 'owner') {
        return array_fill_keys(array_keys($all), true);
    }
    if ($role === 'admin') {
        $all = array_fill_keys(array_keys($all), true);
        $all['settings'] = false;
    }
    return $all;
}

/**
 * Итоговые доступы пользователя: дефолты роли + переопределения из permissions
 * (и старое can_dashboard, если JSON про дашборд молчит).
 * @param array $user Строка users
 * @return array key => bool
 */
function zpEffectivePermissions($user) {
    $perms = zpRoleDefaults($user['role'] ?? 'teacher');
    if (($user['role'] ?? '') === 'owner') {
        return $perms; // владельца не ограничиваем
    }
    $overrides = [];
    if (!empty($user['permissions'])) {
        $decoded = json_decode($user['permissions'], true);
        if (is_array($decoded)) {
            $overrides = $decoded;
        }
    }
    if (!array_key_exists('dashboard', $overrides) && isset($user['can_dashboard']) && $user['can_dashboard'] !== '' && $user['can_dashboard'] !== null) {
        $overrides['dashboard'] = (int)$user['can_dashboard'] === 1;
    }
    foreach ($overrides as $key => $value) {
        if (array_key_exists($key, $perms)) {
            $perms[$key] = (bool)$value;
        }
    }
    return $perms;
}

/**
 * Может ли текущий пользователь открыть раздел. Читает users один раз за запрос,
 * чтобы изменения доступов применялись без перелогина.
 * @param string $section Ключ из zpSections()
 * @return bool
 */
function can($section) {
    static $cache = [];
    if (!isLoggedIn()) {
        return false;
    }
    $uid = (int)getCurrentUserId();
    if (!isset($cache[$uid])) {
        $sql = "SELECT role, permissions, can_dashboard FROM users WHERE id = ? AND active = 1";
        $user = dbQueryOne($sql, [$uid]);
        if (!$user) {
            // Колонок ещё нет (старая сессия до миграции) — мигрируем и повторяем
            ensureUsersRolesSchema();
            $user = dbQueryOne($sql, [$uid]);
        }
        $cache[$uid] = $user ? zpEffectivePermissions($user) : [];
    }
    return !empty($cache[$uid][$section]);
}

/**
 * Требовать доступ к разделу на странице; без доступа — на расписание
 * @param string $section
 * @param string|null $redirect
 */
function requireSection($section, $redirect = null) {
    requireAuth();
    if (!can($section)) {
        if ($redirect === null) {
            $isMobile = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/mobile/') !== false;
            $redirect = $isMobile ? '/zarplata/mobile/schedule.php' : '/zarplata/planner.php';
        }
        header('Location: ' . $redirect . (strpos($redirect, '?') === false ? '?' : '&') . 'denied=' . urlencode($section));
        exit;
    }
}

/**
 * Требовать доступ к разделу в JSON-API (401/403)
 * @param string $section
 */
function requireSectionApi($section) {
    if (!isLoggedIn()) {
        jsonError('Требуется авторизация', 401);
    }
    if (!can($section)) {
        jsonError('Нет доступа к разделу «' . (zpSections()[$section] ?? $section) . '»', 403);
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

// ============================================================
//  Вход через Telegram Login Widget
// ============================================================

/**
 * Username бота для виджета: из settings.bot_username, иначе getMe по токену и кэш
 * @return string без @, пусто если бота нет
 */
function getBotUsername() {
    $row = dbQueryOne("SELECT setting_value FROM settings WHERE setting_key = 'bot_username'", []);
    $name = ltrim(trim((string)($row['setting_value'] ?? '')), '@');
    if ($name !== '') {
        return $name;
    }
    $tokenRow = dbQueryOne("SELECT setting_value FROM settings WHERE setting_key = 'bot_token'", []);
    $token = trim((string)($tokenRow['setting_value'] ?? ''));
    if ($token === '') {
        return '';
    }
    $ctx = stream_context_create(['http' => ['timeout' => 4]]);
    $resp = @file_get_contents("https://api.telegram.org/bot{$token}/getMe", false, $ctx);
    $json = $resp ? json_decode($resp, true) : null;
    $name = (string)($json['result']['username'] ?? '');
    if ($name !== '') {
        dbExecute(
            "INSERT INTO settings (setting_key, setting_value, description) VALUES ('bot_username', ?, 'Username бота (для входа через Telegram)')
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$name]
        );
    }
    return $name;
}

/**
 * Проверить подпись данных Telegram Login Widget
 * https://core.telegram.org/widgets/login#checking-authorization
 * @param array $data Параметры запроса от виджета
 * @return bool
 */
function verifyTelegramAuth(array $data) {
    $tokenRow = dbQueryOne("SELECT setting_value FROM settings WHERE setting_key = 'bot_token'", []);
    $token = trim((string)($tokenRow['setting_value'] ?? ''));
    $hash = (string)($data['hash'] ?? '');
    if ($token === '' || $hash === '' || empty($data['id']) || empty($data['auth_date'])) {
        return false;
    }
    $pairs = [];
    foreach ($data as $key => $value) {
        if ($key === 'hash' || !in_array($key, ['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date'], true)) {
            continue;
        }
        $pairs[] = $key . '=' . $value;
    }
    sort($pairs, SORT_STRING);
    $secret = hash('sha256', $token, true);
    $calc = hash_hmac('sha256', implode("\n", $pairs), $secret);
    if (!hash_equals($calc, $hash)) {
        return false;
    }
    // Подпись живёт сутки
    return (time() - (int)$data['auth_date']) < 86400;
}

/**
 * Войти по данным виджета. Пускаем только известных: users.telegram_id или
 * teachers.telegram_id (тогда создаём/находим аккаунт преподавателя).
 * @param array $data Проверенные данные виджета
 * @return array ['ok' => bool, 'error' => string|null]
 */
function loginWithTelegram(array $data) {
    ensureUsersRolesSchema();
    $tgId = (int)$data['id'];
    $tgUsername = (string)($data['username'] ?? '');

    $user = resolveTelegramUser($tgId, $tgUsername);
    if (!$user) {
        logAudit('telegram_login_rejected', 'user', null, null, ['telegram_id' => $tgId, 'username' => $tgUsername], 'Неизвестный Telegram');
        return ['ok' => false, 'error' => 'Этот Telegram не подключён к системе. Попросите администратора добавить вас.'];
    }
    if (!(int)$user['active']) {
        return ['ok' => false, 'error' => 'Аккаунт отключён'];
    }

    establishSession($user);
    issueRememberToken($user['id']);
    logAudit('user_login', 'user', $user['id'], null, ['via' => 'telegram'], 'Вход через Telegram');
    return ['ok' => true, 'error' => null];
}

// ============================================================
//  Привязка устройства через бота (см. config/device_auth.php)
// ============================================================

/**
 * Приложение опрашивает статус кода. Когда бот подтвердил — входим и выдаём
 * постоянный токен устройства.
 * @param string $pollToken
 * @return array ['status' => pending|done|expired|rejected|invalid, 'error' => ?string]
 */
function completeDeviceLogin($pollToken) {
    ensureDeviceSchema();
    $link = dbQueryOne("SELECT * FROM device_links WHERE poll_token = ?", [(string)$pollToken]);
    if (!$link) {
        return ['status' => 'invalid', 'error' => 'Код не найден'];
    }
    if ($link['status'] === 'rejected') {
        return ['status' => 'rejected', 'error' => $link['reject_reason'] ?: 'Отклонено'];
    }
    if ($link['status'] === 'used') {
        return ['status' => 'invalid', 'error' => 'Код уже использован'];
    }
    if ($link['status'] === 'pending') {
        if (strtotime($link['expires_at']) < time()) {
            return ['status' => 'expired', 'error' => 'Код устарел'];
        }
        return ['status' => 'pending', 'error' => null];
    }

    // confirmed
    ensureUsersRolesSchema();
    $user = dbQueryOne("SELECT * FROM users WHERE id = ? AND active = 1", [$link['user_id']]);
    if (!$user) {
        return ['status' => 'rejected', 'error' => 'Аккаунт отключён'];
    }
    dbExecute("UPDATE device_links SET status = 'used' WHERE id = ?", [$link['id']]);
    establishSession($user);
    issueDeviceToken($user['id'], $link['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    logAudit('user_login', 'user', $user['id'], null, ['via' => 'device', 'device' => deviceLabelFromUserAgent($link['user_agent'] ?? '')], 'Вход с привязанного устройства');
    return ['status' => 'done', 'error' => null];
}

/**
 * Постоянный токен устройства: как «запомнить меня», но kind = device,
 * с подписью и сроком 400 дней, который продлевается при каждом визите.
 * @param int $userId
 * @param string $userAgent
 */
function issueDeviceToken($userId, $userAgent = '') {
    ensureRememberSchema();
    ensureDeviceSchema();

    $selector  = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + DEVICE_TOKEN_LIFETIME);

    try {
        dbExecute(
            "INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at, kind, label, user_agent, last_used_at)
             VALUES (?, ?, ?, ?, 'device', ?, ?, NOW())",
            [$userId, $selector, hash('sha256', $validator), $expiresAt, deviceLabelFromUserAgent($userAgent), mb_substr((string)$userAgent, 0, 255)]
        );
    } catch (Exception $e) {
        error_log('issueDeviceToken failed: ' . $e->getMessage());
        return;
    }

    setRememberCookie($selector . ':' . $validator, time() + DEVICE_TOKEN_LIFETIME);
}

/**
 * Список устройств/сеансов пользователя для панели
 * @param int $userId
 * @return array
 */
function listUserDevices($userId) {
    ensureRememberSchema();
    ensureDeviceSchema();
    return dbQuery(
        "SELECT id, kind, label, created_at, last_used_at, expires_at
         FROM remember_tokens WHERE user_id = ? AND expires_at > NOW()
         ORDER BY kind = 'device' DESC, COALESCE(last_used_at, created_at) DESC",
        [(int)$userId]
    );
}

/**
 * Привязать Telegram к текущему (уже вошедшему) пользователю
 * @param array $data Проверенные данные виджета
 * @return array ['ok' => bool, 'error' => string|null]
 */
function linkTelegramToCurrentUser(array $data) {
    ensureUsersRolesSchema();
    $tgId = (int)$data['id'];
    $taken = dbQueryOne("SELECT id FROM users WHERE telegram_id = ? AND id <> ?", [$tgId, getCurrentUserId()]);
    if ($taken) {
        return ['ok' => false, 'error' => 'Этот Telegram уже привязан к другому аккаунту'];
    }
    dbExecute("UPDATE users SET telegram_id = ?, telegram_username = ? WHERE id = ?", [$tgId, ($data['username'] ?? '') ?: null, getCurrentUserId()]);
    logAudit('telegram_linked', 'user', getCurrentUserId(), null, ['telegram_id' => $tgId], 'Привязан Telegram');
    return ['ok' => true, 'error' => null];
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

    // Восстановить сессию и продлить срок жизни токена (скользящее окно);
    // привязанное устройство живёт до отзыва — продлеваем на максимум
    establishSession($user);
    $isDevice = ($row['kind'] ?? 'remember') === 'device';
    $lifetime = $isDevice ? DEVICE_TOKEN_LIFETIME : REMEMBER_LIFETIME;
    $newExpiry = date('Y-m-d H:i:s', time() + $lifetime);
    if (array_key_exists('last_used_at', $row)) {
        dbExecute("UPDATE remember_tokens SET expires_at = ?, last_used_at = NOW() WHERE id = ?", [$newExpiry, $row['id']]);
    } else {
        dbExecute("UPDATE remember_tokens SET expires_at = ? WHERE id = ?", [$newExpiry, $row['id']]);
    }
    setRememberCookie($_COOKIE[REMEMBER_COOKIE], time() + $lifetime);
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
