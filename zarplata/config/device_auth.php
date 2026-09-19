<?php
/**
 * Привязка устройства через Telegram-бота.
 *
 * Приложение показывает 6-значный код → пользователь отправляет его боту
 * (или открывает t.me/<bot>?start=dev<код>) → бот подтверждает по telegram_id →
 * приложение получает постоянный токен устройства (remember_tokens.kind = 'device').
 *
 * Файл не зависит от auth.php: его подключает и вебхук бота, и веб-часть.
 */

require_once __DIR__ . '/db.php';

define('DEVICE_LINK_TTL', 10 * 60);                 // код живёт 10 минут
define('DEVICE_TOKEN_LIFETIME', 60 * 60 * 24 * 400); // максимум для cookie в Chrome; продлевается при каждом визите

/**
 * Таблица кодов привязки и колонки устройств в remember_tokens (одноразовая миграция)
 */
function ensureDeviceSchema() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $exists = dbQuery("SHOW TABLES LIKE 'device_links'", []);
    if (empty($exists)) {
        dbExecute(
            "CREATE TABLE device_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code CHAR(6) NOT NULL,
                poll_token CHAR(64) NOT NULL,
                status ENUM('pending','confirmed','used','rejected') NOT NULL DEFAULT 'pending',
                user_id INT NULL,
                telegram_id BIGINT NULL,
                reject_reason VARCHAR(255) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                UNIQUE KEY uniq_poll (poll_token),
                KEY idx_code (code, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            []
        );
    }

    $tokens = dbQuery("SHOW TABLES LIKE 'remember_tokens'", []);
    if (!empty($tokens)) {
        $kind = dbQuery("SHOW COLUMNS FROM remember_tokens LIKE 'kind'", []);
        if (empty($kind)) {
            dbExecute(
                "ALTER TABLE remember_tokens
                    ADD COLUMN kind ENUM('remember','device') NOT NULL DEFAULT 'remember',
                    ADD COLUMN label VARCHAR(120) NULL,
                    ADD COLUMN user_agent VARCHAR(255) NULL,
                    ADD COLUMN last_used_at DATETIME NULL",
                []
            );
        }
    }
}

/**
 * Найти пользователя панели по Telegram: users.telegram_id, иначе преподаватель из бота
 * (аккаунт учителя создаётся или находится по teacher_id). Неизвестный → null.
 * @param int $tgId
 * @param string $tgUsername
 * @return array|null Строка users
 */
function resolveTelegramUser($tgId, $tgUsername = '') {
    $tgId = (int)$tgId;
    $user = dbQueryOne("SELECT * FROM users WHERE telegram_id = ?", [$tgId]);
    if ($user) {
        return $user;
    }

    $teacher = dbQueryOne("SELECT * FROM teachers WHERE telegram_id = ? AND active = 1", [$tgId]);
    if (!$teacher) {
        return null;
    }

    $user = dbQueryOne(
        "SELECT * FROM users WHERE teacher_id = ? AND role = 'teacher' ORDER BY active DESC, id LIMIT 1",
        [$teacher['id']]
    );
    if (!$user) {
        $username = 'tg' . $tgId;
        $name = $teacher['display_name'] ?: $teacher['name'];
        $userId = dbExecute(
            "INSERT INTO users (username, password_hash, name, role, active, teacher_id)
             VALUES (?, ?, ?, 'teacher', 1, ?)",
            [$username, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $name, $teacher['id']]
        );
        if (!$userId) {
            return null;
        }
        $user = dbQueryOne("SELECT * FROM users WHERE id = ?", [$userId]);
    }

    dbExecute(
        "UPDATE users SET telegram_id = ?, telegram_username = ? WHERE id = ?",
        [$tgId, $tgUsername !== '' ? $tgUsername : null, $user['id']]
    );
    $user['telegram_id'] = $tgId;
    return $user;
}

/**
 * Создать код привязки для устройства, с которого пришёл запрос
 * @param string $userAgent
 * @return array ['code' => '123456', 'poll_token' => ..., 'expires_at' => ts]
 */
function createDeviceLink($userAgent = '') {
    ensureDeviceSchema();
    // Старые коды не нужны
    dbExecute("DELETE FROM device_links WHERE expires_at < NOW() - INTERVAL 1 DAY", []);

    do {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $clash = dbQueryOne("SELECT id FROM device_links WHERE code = ? AND status = 'pending' AND expires_at > NOW()", [$code]);
    } while ($clash);

    $pollToken = bin2hex(random_bytes(32));
    $expiresAt = time() + DEVICE_LINK_TTL;
    dbExecute(
        "INSERT INTO device_links (code, poll_token, user_agent, expires_at) VALUES (?, ?, ?, ?)",
        [$code, $pollToken, mb_substr((string)$userAgent, 0, 255), date('Y-m-d H:i:s', $expiresAt)]
    );
    return ['code' => $code, 'poll_token' => $pollToken, 'expires_at' => $expiresAt];
}

/**
 * Бот получил код от пользователя: подтвердить или отклонить
 * @param string $code
 * @param int $tgId
 * @param string $tgUsername
 * @return array ['ok' => bool, 'message' => текст ответа для бота]
 */
function confirmDeviceLink($code, $tgId, $tgUsername = '') {
    ensureDeviceSchema();
    $code = preg_replace('/\D/', '', (string)$code);
    if (strlen($code) !== 6) {
        return ['ok' => false, 'message' => 'Код должен состоять из 6 цифр.'];
    }

    $link = dbQueryOne(
        "SELECT * FROM device_links WHERE code = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
        [$code]
    );
    if (!$link) {
        return ['ok' => false, 'message' => "Код <b>{$code}</b> не найден. Откройте экран входа заново и отправьте новый код."];
    }
    if (strtotime($link['expires_at']) < time()) {
        return ['ok' => false, 'message' => 'Код устарел — коды живут 10 минут. Запросите новый на экране входа.'];
    }

    $user = resolveTelegramUser($tgId, $tgUsername);
    if (!$user || !(int)$user['active']) {
        dbExecute(
            "UPDATE device_links SET status = 'rejected', telegram_id = ?, reject_reason = ? WHERE id = ?",
            [(int)$tgId, 'Telegram не подключён к системе', $link['id']]
        );
        return ['ok' => false, 'message' => "Этот Telegram не подключён к системе. Попросите администратора добавить вас — ваш ID: <code>{$tgId}</code>"];
    }

    dbExecute(
        "UPDATE device_links SET status = 'confirmed', user_id = ?, telegram_id = ? WHERE id = ?",
        [$user['id'], (int)$tgId, $link['id']]
    );
    $device = deviceLabelFromUserAgent($link['user_agent'] ?? '');
    return [
        'ok' => true,
        'message' => "✅ Устройство привязано: <b>{$device}</b>\n\nВернитесь в приложение — вход выполнится сам. Аккаунт: <b>" . htmlspecialchars($user['name']) . "</b>",
    ];
}

/**
 * Человеческое имя устройства по User-Agent: «iPhone · Safari», «Android · Chrome»
 * @param string $ua
 * @return string
 */
function deviceLabelFromUserAgent($ua) {
    $ua = (string)$ua;
    $os = 'Устройство';
    if (preg_match('/iPhone/i', $ua)) $os = 'iPhone';
    elseif (preg_match('/iPad/i', $ua)) $os = 'iPad';
    elseif (preg_match('/Android/i', $ua)) $os = 'Android';
    elseif (preg_match('/Windows/i', $ua)) $os = 'Windows';
    elseif (preg_match('/Macintosh/i', $ua)) $os = 'Mac';
    elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';

    $browser = '';
    if (preg_match('/YaBrowser/i', $ua)) $browser = 'Яндекс';
    elseif (preg_match('/Edg\//i', $ua)) $browser = 'Edge';
    elseif (preg_match('/OPR\//i', $ua)) $browser = 'Opera';
    elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/Chrome/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';

    return $browser ? "$os · $browser" : $os;
}
