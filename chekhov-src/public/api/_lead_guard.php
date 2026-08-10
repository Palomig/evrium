<?php
/**
 * Общие помощники защиты формы заявок (эвриум.рф).
 *
 * Подключается из lead.php (проверка) и form-token.php (выдача токена),
 * чтобы обе стороны считали подпись одним и тем же секретом.
 *
 * Прямое обращение по HTTP ничего не выводит — здесь только функции.
 */

declare(strict_types=1);

/** Возраст токена, при котором заявка считается «слишком быстрой» (сек). */
const LEAD_TOKEN_MIN_AGE = 2;
/** Срок жизни токена (сек). Вкладку могут держать открытой долго. */
const LEAD_TOKEN_MAX_AGE = 7200;

/**
 * Каталог для служебных файлов и логов — по возможности вне public_html.
 * Логика повторяет ту, что была в lead.php: сначала /storage рядом с сайтом,
 * при запрете open_basedir — /storage внутри сайта, закрытый .htaccess.
 */
function leadStorageBase(): string
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $base = dirname(__DIR__, 2) . '/storage';
    if (!is_dir($base)) @mkdir($base, 0775, true);
    if (is_dir($base) && is_writable($base)) return $cached = $base;

    $fallback = dirname(__DIR__) . '/storage';
    if (!is_dir($fallback)) @mkdir($fallback, 0775, true);
    $ht = $fallback . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\n");
    return $cached = $fallback;
}

/**
 * Секрет для подписи токенов формы. Создаётся сам при первом обращении —
 * настраивать вручную ничего не нужно.
 */
function leadSecret(): string
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $file = leadStorageBase() . '/.form-secret';
    $existing = @file_get_contents($file);
    if (is_string($existing) && strlen(trim($existing)) >= 32) {
        return $cached = trim($existing);
    }

    $fresh = bin2hex(random_bytes(32));
    @file_put_contents($file, $fresh, LOCK_EX);
    @chmod($file, 0600);

    // Перечитываем: если параллельный запрос успел записать свой секрет,
    // берём его — иначе половина токенов окажется подписана «не тем».
    $check = @file_get_contents($file);
    if (is_string($check) && strlen(trim($check)) >= 32) {
        return $cached = trim($check);
    }
    return $cached = $fresh;
}

/** Выдать одноразовый токен формы: "<unixtime>.<hmac>". */
function leadIssueToken(): string
{
    $ts = time();
    return $ts . '.' . hash_hmac('sha256', 'lead|' . $ts, leadSecret());
}

/**
 * Проверить токен. Возвращает [ok, reason].
 * Токен одноразовый: при успешной проверке помечается использованным.
 */
function leadCheckToken(string $token): array
{
    if ($token === '') return [false, 'token_missing'];

    $parts = explode('.', $token);
    if (count($parts) !== 2) return [false, 'token_malformed'];

    [$ts, $sig] = $parts;
    if (!ctype_digit($ts) || !ctype_xdigit($sig)) return [false, 'token_malformed'];

    $expected = hash_hmac('sha256', 'lead|' . $ts, leadSecret());
    if (!hash_equals($expected, $sig)) return [false, 'token_bad_signature'];

    $age = time() - (int)$ts;
    if ($age < LEAD_TOKEN_MIN_AGE) return [false, 'token_too_fast'];
    if ($age > LEAD_TOKEN_MAX_AGE) return [false, 'token_expired'];

    if (leadTokenSeenBefore($sig)) return [false, 'token_reused'];

    return [true, ''];
}

/**
 * Отметить токен использованным. true — если он уже встречался раньше.
 * Хранилище — компактный файл последних подписей; протухшие вычищаются на лету.
 * Если файл недоступен, повторы не ловим, но заявку не роняем.
 */
function leadTokenSeenBefore(string $sig): bool
{
    $file = leadStorageBase() . '/.used-tokens';
    $key = substr($sig, 0, 32);
    $now = time();

    $fh = @fopen($file, 'c+');
    if (!$fh) return false;
    @flock($fh, LOCK_EX);

    $seen = false;
    $keep = [];
    foreach (explode("\n", (string)stream_get_contents($fh)) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        [$k, $t] = array_pad(explode(' ', $line, 2), 2, '0');
        if ($now - (int)$t > LEAD_TOKEN_MAX_AGE) continue;
        if ($k === $key) $seen = true;
        $keep[] = $k . ' ' . (int)$t;
    }

    if (!$seen) {
        $keep[] = $key . ' ' . $now;
        if (count($keep) > 500) $keep = array_slice($keep, -500);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, implode("\n", $keep) . "\n");
    }

    @flock($fh, LOCK_UN);
    fclose($fh);
    return $seen;
}

/**
 * Настройки приёма заявок: файл api/config.php (приоритет), затем таблица
 * settings базы zarplata — тот же источник, что и токен бота @evrium_bot.
 */
function leadLoadConfig(): array
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $configFile = __DIR__ . '/config.php';
    $config = is_file($configFile) ? (require $configFile) : [];
    $config = array_merge([
        'telegram_token' => null,    // 'XXXXXXXXX:YYYYYYY'
        'telegram_chat_id' => null,  // '123456789'
        'email_to' => null,          // 'tutor@example.com'
        'log_dir' => leadStorageBase() . '/leads',
        // Yandex SmartCaptcha — выключена, пока не выставлен флаг и оба ключа.
        'smartcaptcha_enabled' => null,
        'smartcaptcha_client_key' => null,
        'smartcaptcha_server_key' => null,
    ], $config);

    $needsDb = empty($config['telegram_token'])
        || empty($config['telegram_chat_id'])
        || $config['smartcaptcha_enabled'] === null
        || empty($config['smartcaptcha_client_key'])
        || empty($config['smartcaptcha_server_key']);

    if (!$needsDb) return $cached = $config;

    // Недоступность БД не должна ломать форму — заявка важнее настроек.
    $dbConfig = __DIR__ . '/../zarplata/config/db.php';
    if (!is_file($dbConfig)) return $cached = $config;

    require_once $dbConfig; // определяет DB_HOST/DB_NAME/DB_USER/DB_PASS
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 3,
            ]
        );
        $stmt = $pdo->prepare(
            "SELECT setting_key, setting_value FROM settings
             WHERE setting_key IN ('bot_token', 'leads_chat_id', 'admin_telegram_chat_id',
                                   'smartcaptcha_enabled', 'smartcaptcha_client_key', 'smartcaptcha_server_key')"
        );
        $stmt->execute();
        $s = [];
        foreach ($stmt->fetchAll() as $row) {
            $s[$row['setting_key']] = $row['setting_value'];
        }

        if (empty($config['telegram_token']) && !empty($s['bot_token'])) {
            $config['telegram_token'] = $s['bot_token'];
        }
        // Получатель заявок: отдельная настройка leads_chat_id (приоритет),
        // иначе — общий админ-чат zarplata (admin_telegram_chat_id).
        if (empty($config['telegram_chat_id'])) {
            $config['telegram_chat_id'] = $s['leads_chat_id'] ?? null;
            if (empty($config['telegram_chat_id'])) {
                $config['telegram_chat_id'] = $s['admin_telegram_chat_id'] ?? null;
            }
        }
        if ($config['smartcaptcha_enabled'] === null && isset($s['smartcaptcha_enabled'])) {
            $config['smartcaptcha_enabled'] = $s['smartcaptcha_enabled'];
        }
        if (empty($config['smartcaptcha_client_key']) && !empty($s['smartcaptcha_client_key'])) {
            $config['smartcaptcha_client_key'] = $s['smartcaptcha_client_key'];
        }
        if (empty($config['smartcaptcha_server_key']) && !empty($s['smartcaptcha_server_key'])) {
            $config['smartcaptcha_server_key'] = $s['smartcaptcha_server_key'];
        }
    } catch (Throwable $e) {
        error_log('[lead] Не удалось прочитать настройки из БД: ' . $e->getMessage());
    }

    return $cached = $config;
}

/**
 * Капча включается только когда флаг взведён И оба ключа на месте —
 * так «включил флаг, забыл ключи» не превращается в отказ всех заявок.
 */
function leadCaptchaEnabled(array $config): bool
{
    $flag = $config['smartcaptcha_enabled'] ?? null;
    $on = in_array(strtolower(trim((string)$flag)), ['1', 'true', 'yes', 'on'], true);
    return $on
        && !empty($config['smartcaptcha_client_key'])
        && !empty($config['smartcaptcha_server_key']);
}

/**
 * Проверка токена SmartCaptcha на стороне Яндекса. Возвращает [ok, reason].
 *
 * Сеть недоступна или ответ невнятный — пропускаем заявку (fail-open):
 * потерять живого клиента дороже, чем пропустить спамера.
 */
function leadCaptchaVerify(string $serverKey, string $token, string $ip): array
{
    if ($token === '') return [false, 'captcha_missing'];

    $url = 'https://smartcaptcha.yandexcloud.net/validate?' . http_build_query([
        'secret' => $serverKey,
        'token' => $token,
        'ip' => $ip,
    ]);
    $ctx = stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => 5, 'ignore_errors' => true],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        error_log('[lead] SmartCaptcha недоступна — заявка пропущена без проверки');
        return [true, 'captcha_unreachable'];
    }
    $json = json_decode((string)$raw, true);
    if (!is_array($json) || !isset($json['status'])) {
        error_log('[lead] SmartCaptcha вернула неожиданный ответ: ' . substr((string)$raw, 0, 200));
        return [true, 'captcha_bad_response'];
    }
    return [$json['status'] === 'ok', 'captcha_failed'];
}
