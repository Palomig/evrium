<?php
/**
 * PWA Install Landing Page
 * Презентабельная страница для установки приложения
 */

// Не требуем авторизацию для этой страницы
require_once __DIR__ . '/../config/db.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#14b8a6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Зарплата">

    <title>Скачать приложение — Эвриум Зарплата</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/icon-96x96.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-dark: #0c0f14;
            --bg-card: #14181f;
            --bg-elevated: #1a1f28;
            --text-primary: #f0f2f5;
            --text-secondary: #8b95a5;
            --text-muted: #5a6473;
            --accent: #14b8a6;
            --accent-hover: #0d9488;
            --border: #252b36;
        }

        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Background gradient */
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 20%, rgba(20, 184, 166, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 70% 80%, rgba(20, 184, 166, 0.1) 0%, transparent 40%);
            animation: float 20s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(2%, 2%) rotate(1deg); }
            66% { transform: translate(-1%, -1%) rotate(-1deg); }
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 400px;
        }

        /* App Icon */
        .app-icon {
            width: 120px;
            height: 120px;
            border-radius: 28px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
            box-shadow: 0 20px 60px rgba(20, 184, 166, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            animation: icon-float 3s ease-in-out infinite;
        }

        @keyframes icon-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .app-icon span {
            font-size: 64px;
            font-weight: 700;
            color: white;
            font-family: 'Nunito', sans-serif;
        }

        /* Title */
        .app-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .app-subtitle {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 40px;
        }

        /* Features */
        .features {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 40px;
            text-align: left;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            background: var(--bg-card);
            border-radius: 14px;
            border: 1px solid var(--border);
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(20, 184, 166, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-icon svg {
            width: 22px;
            height: 22px;
            color: var(--accent);
        }

        .feature-text {
            flex: 1;
        }

        .feature-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .feature-desc {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* Install Button */
        .install-btn {
            width: 100%;
            padding: 18px 32px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 18px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 40px rgba(20, 184, 166, 0.3);
            margin-bottom: 16px;
        }

        .install-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 50px rgba(20, 184, 166, 0.4);
        }

        .install-btn:active {
            transform: translateY(0);
        }

        .install-btn svg {
            width: 24px;
            height: 24px;
        }

        .install-btn.installed {
            background: var(--bg-card);
            border: 2px solid var(--accent);
            color: var(--accent);
            box-shadow: none;
        }

        /* Alternative link */
        .alt-link {
            font-size: 14px;
            color: var(--text-muted);
        }

        .alt-link a {
            color: var(--accent);
            text-decoration: none;
        }

        /* Instructions */
        .instructions {
            margin-top: 48px;
            padding: 24px;
            background: var(--bg-card);
            border-radius: 20px;
            border: 1px solid var(--border);
            text-align: left;
        }

        .instructions-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .instructions-title svg {
            width: 20px;
            height: 20px;
            color: var(--accent);
        }

        .browser-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .browser-tab {
            padding: 10px 16px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .browser-tab.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }

        .browser-content {
            display: none;
        }

        .browser-content.active {
            display: block;
        }

        .step {
            display: flex;
            gap: 14px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .step:last-child {
            border-bottom: none;
        }

        .step-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .step-text {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.5;
            padding-top: 3px;
        }

        .step-text strong {
            color: var(--text-primary);
        }

        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: rgba(34, 197, 94, 0.15);
            border-radius: 20px;
            font-size: 13px;
            color: #22c55e;
            margin-bottom: 24px;
        }

        .status-badge.warning {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        .status-badge svg {
            width: 16px;
            height: 16px;
        }

        /* Hide on desktop */
        @media (min-width: 768px) {
            .hero-content {
                max-width: 480px;
            }

            .app-icon {
                width: 140px;
                height: 140px;
            }

            .app-icon span {
                font-size: 72px;
            }

            .app-title {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <section class="hero">
        <div class="hero-content">
            <!-- App Icon -->
            <div class="app-icon">
                <span>Э</span>
            </div>

            <!-- Title -->
            <h1 class="app-title">Эвриум Зарплата</h1>
            <p class="app-subtitle">Система учёта зарплат преподавателей</p>

            <!-- Status -->
            <div class="status-badge" id="statusBadge" style="display: none;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span id="statusText">Приложение установлено</span>
            </div>

            <!-- Features -->
            <div class="features">
                <div class="feature">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="feature-text">
                        <div class="feature-title">Быстрый доступ</div>
                        <div class="feature-desc">Запуск с главного экрана в один тап</div>
                    </div>
                </div>

                <div class="feature">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="feature-text">
                        <div class="feature-title">Полноэкранный режим</div>
                        <div class="feature-desc">Без адресной строки браузера</div>
                    </div>
                </div>

                <div class="feature">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="feature-text">
                        <div class="feature-title">Как родное приложение</div>
                        <div class="feature-desc">Оптимизировано для мобильных устройств</div>
                    </div>
                </div>
            </div>

            <!-- Install Button -->
            <button class="install-btn" id="installBtn">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span id="installBtnText">Установить приложение</span>
            </button>

            <p class="alt-link">
                Или <a href="login.php">войти через браузер</a>
            </p>

            <!-- Instructions -->
            <div class="instructions" id="instructions">
                <div class="instructions-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Как установить вручную
                </div>

                <div class="browser-tabs">
                    <button class="browser-tab active" onclick="showBrowser('chrome')">Chrome</button>
                    <button class="browser-tab" onclick="showBrowser('samsung')">Samsung</button>
                    <button class="browser-tab" onclick="showBrowser('safari')">Safari</button>
                </div>

                <div class="browser-content active" id="browser-chrome">
                    <div class="step">
                        <div class="step-num">1</div>
                        <div class="step-text">Нажмите на <strong>⋮</strong> (три точки) в правом верхнем углу</div>
                    </div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div class="step-text">Выберите <strong>«Установить приложение»</strong> или <strong>«Добавить на главный экран»</strong></div>
                    </div>
                    <div class="step">
                        <div class="step-num">3</div>
                        <div class="step-text">Нажмите <strong>«Установить»</strong> в появившемся окне</div>
                    </div>
                </div>

                <div class="browser-content" id="browser-samsung">
                    <div class="step">
                        <div class="step-num">1</div>
                        <div class="step-text">Нажмите на <strong>☰</strong> (три полоски) внизу экрана</div>
                    </div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div class="step-text">Выберите <strong>«Добавить страницу в»</strong></div>
                    </div>
                    <div class="step">
                        <div class="step-num">3</div>
                        <div class="step-text">Нажмите <strong>«Главный экран»</strong></div>
                    </div>
                </div>

                <div class="browser-content" id="browser-safari">
                    <div class="step">
                        <div class="step-num">1</div>
                        <div class="step-text">Нажмите на <strong>⬆</strong> (кнопка «Поделиться») внизу экрана</div>
                    </div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div class="step-text">Прокрутите вниз и выберите <strong>«На экран Домой»</strong></div>
                    </div>
                    <div class="step">
                        <div class="step-num">3</div>
                        <div class="step-text">Нажмите <strong>«Добавить»</strong> в правом верхнем углу</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Service Worker -->
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/zarplata/mobile/service-worker.js')
            .then(reg => console.log('SW registered'))
            .catch(err => console.log('SW failed:', err));
    }
    </script>

    <!-- PWA Install Logic -->
    <script>
    let deferredPrompt;
    const installBtn = document.getElementById('installBtn');
    const installBtnText = document.getElementById('installBtnText');
    const statusBadge = document.getElementById('statusBadge');
    const statusText = document.getElementById('statusText');
    const instructions = document.getElementById('instructions');

    // Check if already installed
    if (window.matchMedia('(display-mode: standalone)').matches) {
        showInstalledState();
    }

    // Listen for install prompt
    window.addEventListener('beforeinstallprompt', (e) => {
        console.log('beforeinstallprompt fired');
        e.preventDefault();
        deferredPrompt = e;

        // Update button state
        installBtn.classList.remove('installed');
        installBtnText.textContent = 'Установить приложение';
        instructions.style.display = 'none';
    });

    // Handle install button click
    installBtn.addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log('User choice:', outcome);

            if (outcome === 'accepted') {
                showInstalledState();
            }
            deferredPrompt = null;
        } else {
            // Scroll to instructions
            instructions.scrollIntoView({ behavior: 'smooth' });
        }
    });

    // Handle app installed
    window.addEventListener('appinstalled', () => {
        console.log('App installed');
        showInstalledState();
    });

    function showInstalledState() {
        statusBadge.style.display = 'inline-flex';
        statusText.textContent = 'Приложение установлено';
        installBtn.classList.add('installed');
        installBtnText.textContent = 'Открыть приложение';
        instructions.style.display = 'none';

        installBtn.onclick = () => {
            window.location.href = 'index.php';
        };
    }

    // Browser tabs
    function showBrowser(browser) {
        document.querySelectorAll('.browser-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.browser-content').forEach(content => {
            content.classList.remove('active');
        });

        event.target.classList.add('active');
        document.getElementById('browser-' + browser).classList.add('active');
    }

    // Detect browser for default tab
    const ua = navigator.userAgent.toLowerCase();
    if (ua.includes('samsungbrowser')) {
        showBrowser('samsung');
        document.querySelector('.browser-tab').classList.remove('active');
        document.querySelectorAll('.browser-tab')[1].classList.add('active');
    } else if (ua.includes('safari') && !ua.includes('chrome')) {
        showBrowser('safari');
        document.querySelector('.browser-tab').classList.remove('active');
        document.querySelectorAll('.browser-tab')[2].classList.add('active');
    }
    </script>
</body>
</html>
