<?php
/**
 * Привязка устройства через Telegram-бота (публичный API экрана входа).
 *   start  — выдать код и ссылку на бота
 *   status — опрос: pending | done (сессия установлена, cookie устройства выдана) | expired | rejected
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?: [];

switch ($action) {
    case 'start':
        if (isLoggedIn()) {
            jsonError('Вы уже вошли', 400);
        }
        $bot = getBotUsername();
        if ($bot === '') {
            jsonError('Бот не настроен', 500);
        }
        $link = createDeviceLink($_SERVER['HTTP_USER_AGENT'] ?? '');
        jsonSuccess([
            'code' => $link['code'],
            'poll_token' => $link['poll_token'],
            'expires_in' => $link['expires_at'] - time(),
            'bot_username' => $bot,
            'bot_link' => 'https://t.me/' . $bot . '?start=dev' . $link['code'],
        ]);
        break;

    case 'status':
        $pollToken = (string)($data['poll_token'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $pollToken)) {
            jsonError('Неверный токен', 400);
        }
        $result = completeDeviceLogin($pollToken);
        $redirect = strpos($_SERVER['HTTP_REFERER'] ?? '', '/mobile/') !== false ? '/zarplata/mobile/' : '/zarplata/';
        jsonSuccess(['status' => $result['status'], 'error' => $result['error'], 'redirect' => $redirect]);
        break;

    default:
        jsonError('Неизвестное действие', 400);
}
