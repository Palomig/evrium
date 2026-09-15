<?php
/**
 * Приём ответа Telegram Login Widget (redirect-режим, data-auth-url).
 * Без сессии — вход по telegram_id; с сессией — привязка Telegram к аккаунту.
 * Куда вернуть после входа, задаёт страница логина в $_SESSION['tg_return'].
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

$return = $_SESSION['tg_return'] ?? '/zarplata/';
$loginPage = strpos($return, '/mobile/') !== false ? '/zarplata/mobile/login.php' : '/zarplata/login.php';

function tgFail($url, $message) {
    header('Location: ' . $url . (strpos($url, '?') === false ? '?' : '&') . 'tg_error=' . urlencode($message));
    exit;
}

if (!verifyTelegramAuth($_GET)) {
    tgFail(isLoggedIn() ? '/zarplata/settings.php' : $loginPage, 'Не удалось проверить ответ Telegram. Попробуйте ещё раз.');
}

// Привязка к уже вошедшему пользователю (кнопка в настройках)
if (isLoggedIn()) {
    $result = linkTelegramToCurrentUser($_GET);
    $back = '/zarplata/settings.php';
    unset($_SESSION['tg_return']);
    if (!$result['ok']) {
        tgFail($back, $result['error']);
    }
    header('Location: ' . $back . (strpos($back, '?') === false ? '?' : '&') . 'tg_linked=1');
    exit;
}

$result = loginWithTelegram($_GET);
if (!$result['ok']) {
    tgFail($loginPage, $result['error']);
}

unset($_SESSION['tg_return']);
header('Location: ' . $return);
exit;
