<?php
/**
 * Страница входа в систему
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

// Автоматический редирект на мобильную версию
require_once __DIR__ . '/mobile/config/mobile_detect.php';
redirectToMobileIfNeeded('login.php');

// Если уже авторизован, редирект на главную
if (isLoggedIn()) {
    redirect('/zarplata/');
}

$error = null;
if (!empty($_GET['tg_error'])) {
    $error = (string)$_GET['tg_error'];
}

// Вход через Telegram: куда вернуть после виджета и username бота для кнопки
$_SESSION['tg_return'] = '/zarplata/';
$botUsername = getBotUsername();
$tgAuthUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'эвриум.рф') . '/zarplata/auth/telegram.php';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Пожалуйста, заполните все поля';
    } else {
        if (login($username, $password, $remember)) {
            redirect('/zarplata/');
        } else {
            $error = 'Неверное имя пользователя или пароль';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему — Учёт зарплаты</title>

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Montserrat Font -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Dark Theme CSS -->
    <link rel="stylesheet" href="/zarplata/assets/css/material-dark.css">

    <style>
        .tg-divider { display: flex; align-items: center; gap: 12px; margin: 4px 0 16px; color: var(--text-disabled, #6b7280); font-size: 12px; }
        .tg-divider::before, .tg-divider::after { content: ""; flex: 1; height: 1px; background: var(--border, #2d2d44); }
        .tg-login { display: flex; justify-content: center; min-height: 40px; margin-bottom: 16px; }
        .dl-box { margin: 4px 0 16px; padding: 16px; border: 1px solid var(--border, #2d2d44); border-radius: 12px; text-align: center; }
        .dl-code { font-family: ui-monospace, 'JetBrains Mono', monospace; font-size: 32px; font-weight: 600; letter-spacing: 4px; color: var(--accent, #14b8a6); }
        .dl-hint { margin: 8px 0 12px; color: var(--text-secondary, #9aa4b2); font-size: 13px; }
        .dl-box .btn { text-decoration: none; }
        .dl-status { margin-top: 12px; font-size: 13px; color: var(--text-secondary, #9aa4b2); min-height: 18px; }
        .dl-status.wait::before { content: ''; display: inline-block; width: 10px; height: 10px; margin-right: 8px; border-radius: 50%; border: 2px solid var(--accent, #14b8a6); border-top-color: transparent; animation: dlspin .8s linear infinite; vertical-align: -1px; }
        .dl-status.ok { color: var(--accent, #14b8a6); }
        .dl-status.err { color: #f43f5e; }
        .dl-timer { margin-top: 6px; font-size: 12px; color: var(--text-disabled, #6b7280); }
        @keyframes dlspin { to { transform: rotate(360deg); } }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--md-primary), var(--md-secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: var(--elevation-3);
        }

        .login-icon .material-icons {
            font-size: 36px;
            color: var(--md-on-primary);
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 400;
            margin-bottom: 8px;
        }

        .login-subtitle {
            font-size: 0.875rem;
            color: var(--text-medium-emphasis);
        }

        .footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 0.75rem;
            color: var(--text-disabled);
        }

        .error-shake {
            animation: shake 0.4s cubic-bezier(0.36, 0.07, 0.19, 0.97);
        }

        @keyframes shake {
            10%, 90% { transform: translateX(-2px); }
            20%, 80% { transform: translateX(4px); }
            30%, 50%, 70% { transform: translateX(-6px); }
            40%, 60% { transform: translateX(6px); }
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-medium-emphasis);
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: var(--text-high-emphasis);
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon .material-icons {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-medium-emphasis);
            font-size: 20px;
        }

        .input-with-icon input {
            padding-left: 44px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <span class="material-icons">account_balance_wallet</span>
                </div>
                <h1 class="login-title">Учёт зарплаты</h1>
                <p class="login-subtitle">Система управления выплатами преподавателям</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-error error-shake">
                            <span class="material-icons">error_outline</span>
                            <span><?= e($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <div id="pw-form" <?= $error && empty($_GET['tg_error']) ? '' : 'hidden' ?>>
                    <form method="POST" action="/zarplata/login.php" autocomplete="off">
                        <div class="form-group">
                            <label class="form-label" for="username">
                                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">person</span>
                                Имя пользователя
                            </label>
                            <div class="input-with-icon">
                                <span class="material-icons">person_outline</span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="username"
                                    name="username"
                                    placeholder="Введите имя пользователя"
                                    value="<?= e($_POST['username'] ?? '') ?>"
                                    required
                                    autofocus
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">
                                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">lock</span>
                                Пароль
                            </label>
                            <div class="input-with-icon">
                                <span class="material-icons">lock_outline</span>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    placeholder="Введите пароль"
                                    required
                                >
                                <button type="button" class="password-toggle" onclick="togglePassword()">
                                    <span class="material-icons" id="password-icon">visibility</span>
                                </button>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.875rem; color: var(--text-medium-emphasis);">
                                <input type="checkbox" name="remember" value="1" checked style="width: 18px; height: 18px; accent-color: var(--md-primary); cursor: pointer;">
                                Запомнить меня на этом устройстве
                            </label>
                        </div>

                        <div class="form-group mb-4">
                            <button type="submit" class="btn btn-primary btn-large btn-block">
                                <span class="material-icons" style="margin-right: 8px; font-size: 20px;">login</span>
                                Войти
                            </button>
                        </div>
                    </form>
                    </div>

                    <?php if ($botUsername): ?>
                    <button type="button" class="btn btn-secondary btn-large btn-block" id="dl-start" style="margin-bottom: 12px;">
                        <span class="material-icons" style="margin-right: 8px; font-size: 20px;">devices</span>
                        Привязать это устройство через Telegram
                    </button>
                    <div id="dl-box" class="dl-box" hidden>
                        <div class="dl-code" id="dl-code">··· ···</div>
                        <p class="dl-hint">Отправьте код боту или откройте его по кнопке</p>
                        <a id="dl-link" class="btn btn-primary btn-block" href="#" target="_blank" rel="noopener">Открыть бота</a>
                        <div class="dl-status wait" id="dl-status"></div>
                        <div class="dl-timer" id="dl-timer"></div>
                    </div>
                    <div class="text-center">
                        <p class="text-disabled" style="font-size: 0.75rem;">
                            Код подтверждают преподаватели, подключённые к боту, и администраторы с привязанным Telegram
                        </p>
                        <p style="font-size: 0.75rem; margin-top: 8px;">
                            <a href="#" id="pw-toggle" style="color: var(--text-secondary, #9aa4b2);">Войти по паролю</a>
                        </p>
                    </div>
                    <?php else: ?>
                    <div class="text-center">
                        <p class="text-disabled" style="font-size: 0.75rem;">
                            Для доступа к системе используйте учётные данные администратора
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="footer-text">
                <p>© <?= date('Y') ?> Система учёта зарплаты преподавателей</p>
                <p style="margin-top: 4px;">Powered by Evrium</p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.textContent = 'visibility_off';
            } else {
                passwordInput.type = 'password';
                passwordIcon.textContent = 'visibility';
            }
        }

        // Убрать анимацию ошибки после завершения
        document.addEventListener('DOMContentLoaded', () => {
            const errorAlert = document.querySelector('.error-shake');
            if (errorAlert) {
                setTimeout(() => {
                    errorAlert.classList.remove('error-shake');
                }, 400);
            }
        });
    </script>

    <script>
    // Привязка устройства через бота: код → бот подтверждает → входим
    (function () {
        const box = document.getElementById('dl-box');
        const startBtn = document.getElementById('dl-start');
        if (!box || !startBtn) return;
        const codeEl = document.getElementById('dl-code');
        const linkEl = document.getElementById('dl-link');
        const statusEl = document.getElementById('dl-status');
        const timerEl = document.getElementById('dl-timer');
        let pollToken = null, pollTimer = null, deadline = 0, tickTimer = null;

        function setStatus(text, cls) { statusEl.textContent = text; statusEl.className = 'dl-status ' + (cls || ''); }
        function stop() { clearInterval(pollTimer); clearInterval(tickTimer); pollTimer = tickTimer = null; }

        async function start() {
            startBtn.disabled = true;
            setStatus('Получаем код…');
            try {
                const r = await fetch('/zarplata/api/device_link.php?action=start', { method: 'POST' });
                const j = await r.json();
                if (!j.success) { setStatus(j.error || 'Ошибка', 'err'); startBtn.disabled = false; return; }
                pollToken = j.data.poll_token;
                deadline = Date.now() + j.data.expires_in * 1000;
                codeEl.textContent = j.data.code.slice(0, 3) + ' ' + j.data.code.slice(3);
                linkEl.href = j.data.bot_link;
                linkEl.textContent = 'Открыть @' + j.data.bot_username;
                box.hidden = false;
                startBtn.hidden = true;
                setStatus('Ждём подтверждения в Telegram…', 'wait');
                pollTimer = setInterval(poll, 2000);
                tickTimer = setInterval(tick, 1000); tick();
            } catch (e) {
                setStatus('Нет связи с сервером', 'err'); startBtn.disabled = false;
            }
        }

        function tick() {
            const left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
            timerEl.textContent = 'Код действует ещё ' + Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
            if (left <= 0) { stop(); setStatus('Код устарел — запросите новый', 'err'); startBtn.hidden = false; startBtn.disabled = false; }
        }

        async function poll() {
            try {
                const r = await fetch('/zarplata/api/device_link.php?action=status', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ poll_token: pollToken })
                });
                const j = await r.json();
                if (!j.success) return;
                const st = j.data.status;
                if (st === 'done') { stop(); setStatus('Устройство привязано, входим…', 'ok'); location.href = j.data.redirect; }
                else if (st === 'rejected' || st === 'expired' || st === 'invalid') {
                    stop(); setStatus(j.data.error || 'Отклонено', 'err'); startBtn.hidden = false; startBtn.disabled = false;
                }
            } catch (e) { /* сеть моргнула — следующий опрос */ }
        }

        startBtn.addEventListener('click', start);
        // Открыли из бота по ссылке с ?device=1 — сразу показываем код
        const pwForm = document.getElementById('pw-form'), pwToggle = document.getElementById('pw-toggle');
        if (pwToggle) pwToggle.addEventListener('click', e => { e.preventDefault(); pwForm.hidden = !pwForm.hidden; });
        if (pwForm.hidden) start(); // по умолчанию — сразу код
    })();
    </script>
</body>
</html>
