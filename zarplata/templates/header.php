<?php
/**
 * Общий header и sidebar для всех страниц
 * Новый дизайн: Teal Theme (STYLEGUIDE.md)
 */
if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Учёт зарплаты');
}
if (!defined('PAGE_SUBTITLE')) {
    define('PAGE_SUBTITLE', '');
}
if (!defined('ACTIVE_PAGE')) {
    define('ACTIVE_PAGE', '');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= PAGE_TITLE ?> — Эвриум</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">

    <!-- Google Fonts: Nunito + JetBrains Mono + Material Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Teal Theme CSS -->
    <link rel="stylesheet" href="assets/css/teal-theme.css">
</head>
<body>
    <!-- Preloader (shows once per day) -->
    <div id="preloader" class="preloader">
        <h1 class="preloader-text">
            <span class="word">Здарова</span>
            <span class="word">Руслан</span>
        </h1>
    </div>
    <style>
        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-dark, #0c0f14);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        .preloader.hidden {
            display: none;
        }
        .preloader-text {
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            font-size: 3.8em;
            text-transform: uppercase;
            letter-spacing: 0.3em;
            color: var(--accent, #14b8a6);
            text-align: center;
        }
        .preloader-text .word {
            display: inline-block;
            line-height: 1em;
            margin: 0 0.1em;
        }
        @media (max-width: 768px) {
            .preloader-text {
                font-size: 2em;
                letter-spacing: 0.15em;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/2.0.2/anime.min.js"></script>
    <script>
        (function() {
            const preloader = document.getElementById('preloader');
            const today = new Date().toDateString();
            const lastShown = localStorage.getItem('preloader_shown_date');

            if (lastShown === today) {
                preloader.classList.add('hidden');
                return;
            }

            localStorage.setItem('preloader_shown_date', today);

            anime.timeline({loop: false})
                .add({
                    targets: '.preloader-text .word',
                    scale: [14, 1],
                    opacity: [0, 1],
                    easing: "easeOutCirc",
                    duration: 800,
                    delay: (el, i) => 800 * i
                })
                .add({
                    targets: '.preloader',
                    opacity: 0,
                    duration: 600,
                    easing: "easeOutExpo",
                    delay: 800,
                    complete: function() {
                        preloader.classList.add('hidden');
                    }
                });
        })();
    </script>

    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <div class="logo-icon">Э</div>
                <div class="logo-text">Эвриум</div>
            </div>

            <nav class="nav-section">
                <div class="nav-label">Основное</div>
                <a href="index.php" class="nav-item <?= ACTIVE_PAGE === 'dashboard' ? 'active' : '' ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span>Главная</span>
                </a>
                <a href="schedule.php" class="nav-item <?= ACTIVE_PAGE === 'schedule' ? 'active' : '' ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>Расписание</span>
                </a>
                <a href="planner.php" class="nav-item <?= ACTIVE_PAGE === 'planner' ? 'active' : '' ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                    </svg>
                    <span>Планировщик</span>
                </a>
                <a href="students.php" class="nav-item <?= ACTIVE_PAGE === 'students' ? 'active' : '' ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                    </svg>
                    <span>Ученики</span>
                </a>
            </nav>

            <nav class="nav-section">
                <div class="nav-label">Финансы</div>
                <a href="payments.php" class="nav-item <?= ACTIVE_PAGE === 'payments' ? 'active' : '' ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span>Выплаты</span>
                </a>
                <a href="formulas.php" class="nav-item <?= ACTIVE_PAGE === 'formulas' ? 'active' : '' ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span>Формулы</span>
                </a>
                <a href="reports.php" class="nav-item <?= ACTIVE_PAGE === 'reports' ? 'active' : '' ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span>Отчёты</span>
                </a>
            </nav>

            <nav class="nav-section">
                <div class="nav-label">Команда</div>
                <a href="teachers.php" class="nav-item <?= ACTIVE_PAGE === 'teachers' ? 'active' : '' ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span>Преподаватели</span>
                </a>
            </nav>

            <nav class="nav-section" style="margin-top: auto;">
                <a href="audit.php" class="nav-item <?= ACTIVE_PAGE === 'audit' ? 'active' : '' ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Аудит</span>
                </a>
                <a href="tests.php" class="nav-item <?= ACTIVE_PAGE === 'tests' ? 'active' : '' ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    <span>Тесты</span>
                </a>
                <a href="settings.php" class="nav-item <?= ACTIVE_PAGE === 'settings' ? 'active' : '' ?>">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>Настройки</span>
                </a>
                <a href="logout.php" class="nav-item">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span>Выход</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 class="page-title"><?= PAGE_TITLE ?></h1>
                    <?php if (PAGE_SUBTITLE): ?>
                        <p class="page-subtitle"><?= PAGE_SUBTITLE ?></p>
                    <?php endif; ?>
                </div>
            </div>
