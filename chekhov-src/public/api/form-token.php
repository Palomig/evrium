<?php
/**
 * Выдача одноразового токена формы заявки + настройки капчи для клиента.
 *
 * Форма на сайте статическая, поэтому включение/выключение SmartCaptcha
 * живёт на сервере: фронт спрашивает этот эндпоинт и подстраивается сам,
 * пересобирать сайт для переключения не нужно.
 */

declare(strict_types=1);

require_once __DIR__ . '/_lead_guard.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$config = leadLoadConfig();

echo json_encode([
    'ok' => true,
    'token' => leadIssueToken(),
    'min_age' => LEAD_TOKEN_MIN_AGE,
    'captcha' => [
        'enabled' => leadCaptchaEnabled($config),
        // Клиентский ключ SmartCaptcha публичен по устройству — это не секрет.
        'client_key' => leadCaptchaEnabled($config) ? (string)$config['smartcaptcha_client_key'] : '',
    ],
], JSON_UNESCAPED_UNICODE);
