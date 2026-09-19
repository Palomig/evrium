<?php
/**
 * Mobile Login Page
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

// Если уже авторизован, редирект на главную
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = null;
if (!empty($_GET['tg_error'])) {
    $error = (string)$_GET['tg_error'];
}

// Вход через Telegram: вернуть в мобильную версию, username бота для кнопки
$_SESSION['tg_return'] = '/zarplata/mobile/';
$botUsername = getBotUsername();
$tgAuthUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'эвриум.рф') . '/zarplata/auth/telegram.php';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && false) { // вход по паролю в PWA отключён — только код через бота
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        if (login($username, $password, $remember)) {
            header('Location: index.php');
            exit;
        } else {
            $error = 'Неверный логин или пароль';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0c0f14">
    <title>Вход — Эвриум</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-dark: #0c0f14;
            --bg-card: #14181f;
            --bg-elevated: #1a1f28;
            --text-primary: #f0f2f5;
            --text-secondary: #8b95a5;
            --text-muted: #5a6473;
            --border: #252b36;
            --accent: #14b8a6;
            --accent-dim: rgba(20, 184, 166, 0.15);
            --status-rose: #f43f5e;
            --status-rose-dim: rgba(244, 63, 94, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px;
            min-height: 100vh;
            min-height: -webkit-fill-available;
        }

        .login-container {
            width: 100%;
            max-width: 360px;
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(24px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--accent) 0%, #0d9488 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            font-size: 32px;
            color: white;
            box-shadow: 0 8px 32px rgba(20, 184, 166, 0.3);
        }

        .login-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
        }

        .error-message {
            background: var(--status-rose-dim);
            border: 1px solid rgba(244, 63, 94, 0.3);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--status-rose);
            animation: shake 0.4s ease-out;
        }

        @keyframes shake {
            10%, 90% { transform: translateX(-2px); }
            20%, 80% { transform: translateX(4px); }
            30%, 50%, 70% { transform: translateX(-6px); }
            40%, 60% { transform: translateX(6px); }
        }

        .error-icon {
            flex-shrink: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            height: 52px;
            padding: 0 16px;
            padding-left: 48px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 16px;
            font-family: inherit;
            transition: border-color 0.15s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            width: 20px;
            height: 20px;
        }

        .password-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 8px;
        }

        .password-toggle:active {
            background: var(--bg-card);
        }

        .password-toggle svg {
            width: 22px;
            height: 22px;
        }

        .btn-login {
            width: 100%;
            height: 52px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background 0.15s ease, transform 0.15s ease;
        }

        .btn-login:active {
            background: #0d9488;
            transform: scale(0.98);
        }

        .btn-login svg {
            width: 22px;
            height: 22px;
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .desktop-link {
            margin-top: 20px;
            text-align: center;
        }

        .desktop-link a {
            color: var(--text-secondary);
            font-size: 13px;
            text-decoration: none;
        }

        .desktop-link a:active {
            color: var(--accent);
        }
            .tg-divider { display: flex; align-items: center; gap: 12px; margin: 18px 0 14px; color: var(--text-muted); font-size: 12px; }
        .tg-divider::before, .tg-divider::after { content: ""; flex: 1; height: 1px; background: var(--border); }
        .tg-login { display: flex; justify-content: center; min-height: 40px; }
        .tg-hint { margin-top: 10px; text-align: center; color: var(--text-muted); font-size: 12px; }
            .btn-device { background: var(--bg-elevated); color: var(--text-primary); border: 1px solid var(--border); }
        .btn-device:active { background: var(--bg-hover); }
        .dl-box { margin-top: 14px; padding: 16px; border: 1px solid var(--border); border-radius: 14px; background: var(--bg-elevated); text-align: center; }
        .dl-code { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 34px; font-weight: 600; letter-spacing: 4px; color: var(--accent); }
        .dl-hint { margin: 8px 0 14px; color: var(--text-secondary); font-size: 13px; }
        .dl-box .btn-login { text-decoration: none; }
        .dl-status { margin-top: 12px; font-size: 13px; color: var(--text-secondary); min-height: 18px; }
        .dl-status.wait::before { content: ''; display: inline-block; width: 10px; height: 10px; margin-right: 8px; border-radius: 50%; border: 2px solid var(--accent); border-top-color: transparent; animation: dlspin .8s linear infinite; vertical-align: -1px; }
        .dl-status.ok { color: var(--accent); }
        .dl-status.err { color: #f43f5e; }
        .dl-timer { margin-top: 6px; font-size: 12px; color: var(--text-muted); }
        @keyframes dlspin { to { transform: rotate(360deg); } }
    </style>
    <!-- Обязательная установка PWA: в браузере на телефоне приложение не работает -->
    <script>
    (function () {
        var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
        var mobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
        if (mobile && !standalone) {
            location.replace('/zarplata/mobile/install.php');
        }
    })();
    </script>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <div class="logo-icon">Э</div>
            <h1 class="login-title">Эвриум</h1>
            <p class="login-subtitle">Учёт зарплаты преподавателей</p>
        </div>

        <div class="login-card">
            <?php if ($error): ?>
            <div class="error-message">
                <svg class="error-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>


            <?php if ($botUsername): ?>
            <button type="button" class="btn-login btn-device" id="dl-start">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
                Привязать это устройство
            </button>
            <div id="dl-box" class="dl-box" hidden>
                <div class="dl-code" id="dl-code">··· ···</div>
                <p class="dl-hint">Отправьте этот код боту — или просто нажмите кнопку</p>
                <a id="dl-link" class="btn-login" href="#" target="_blank" rel="noopener">Открыть бота</a>
                <div class="dl-status wait" id="dl-status"></div>
                <div class="dl-timer" id="dl-timer"></div>
            </div>
            <p class="tg-hint">Устройство запомнится насовсем — вход только по коду через Telegram</p>
            <?php else: ?>
            <p class="tg-hint">Бот не настроен — обратитесь к администратору</p>
            <?php endif; ?>
        </div>

        <div class="desktop-link">
            <a href="../login.php?desktop=1">Перейти к десктопной версии</a>
        </div>

        <div class="login-footer">
            <p>© <?= date('Y') ?> Эвриум</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                `;
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                `;
            }
        }
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
        start(); // экран входа = сразу код
    })();
    </script>
</body>
</html>
