<?php
/**
 * Mobile Header with Hamburger Menu
 */
if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Zarplata');
}
if (!defined('ACTIVE_PAGE')) {
    define('ACTIVE_PAGE', '');
}
if (!defined('SHOW_BOTTOM_NAV')) {
    define('SHOW_BOTTOM_NAV', true);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#14b8a6">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Зарплата">
    <meta name="apple-mobile-web-app-title" content="Зарплата">
    <meta name="msapplication-TileColor" content="#14b8a6">
    <meta name="msapplication-TileImage" content="assets/icons/icon-144x144.png">

    <title><?= htmlspecialchars(PAGE_TITLE) ?> — Эвриум</title>

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
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/icon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/icon-72x72.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="assets/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="167x167" href="assets/icons/icon-192x192.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Mobile Theme CSS -->
    <link rel="stylesheet" href="assets/css/mobile-theme.css?v=20260919">

    <!-- Page-specific CSS -->
    <?php if (defined('PAGE_CSS')): ?>
    <style><?= PAGE_CSS ?></style>
    <?php endif; ?>
</head>
<body<?= SHOW_BOTTOM_NAV ? '' : ' class="no-bottom-nav"' ?>>

    <!-- Preloader «Рабочий день складывается» (раз в день) -->
    <?php require_once __DIR__ . '/preloader.php'; ?>

    <!-- Mobile Header -->
    <header class="mobile-header">
        <button class="hamburger-btn" aria-label="Menu">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <h1 class="mobile-header-title"><?= htmlspecialchars(PAGE_TITLE) ?></h1>
        <?php if (defined('HEADER_ACTION')): ?>
        <button class="mobile-header-action" onclick="<?= HEADER_ACTION_ONCLICK ?? '' ?>">
            <?= HEADER_ACTION ?>
        </button>
        <?php endif; ?>
    </header>

    <!-- Menu Overlay -->
    <div class="menu-overlay"></div>

    <!-- Slide-out Menu -->
    <nav class="slide-menu">
        <div class="menu-header">
            <div class="menu-logo">Э</div>
            <div class="menu-title">Эвриум</div>
        </div>

        <div class="menu-nav">
            <div class="menu-section">
                <div class="menu-section-label">Основное</div>
                <a href="schedule.php" class="menu-item <?= ACTIVE_PAGE === 'schedule' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>Расписание</span>
                </a>
                <?php if (isTeacherUser()): ?>
                <a href="lessons.php" class="menu-item <?= ACTIVE_PAGE === 'lessons' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    <span>Уроки</span>
                </a>
                <a href="earnings.php" class="menu-item <?= ACTIVE_PAGE === 'earnings' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Выплаты</span>
                </a>
                <?php endif; ?>
            </div>

            <?php if (can('student_payments') || can('formulas') || can('reports')): ?>
            <div class="menu-section">
                <div class="menu-section-label">Финансы</div>
                <?php if (can('student_payments')): ?>
                <a href="student_payments.php" class="menu-item <?= ACTIVE_PAGE === 'student_payments' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    <span>Оплаты учеников</span>
                </a>
                <?php endif; ?>
                <?php if (can('formulas')): ?>
                <a href="formulas.php" class="menu-item <?= ACTIVE_PAGE === 'formulas' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span>Формулы</span>
                </a>
                <?php endif; ?>
                <?php if (can('reports')): ?>
                <a href="reports.php" class="menu-item <?= ACTIVE_PAGE === 'reports' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span>Отчёты</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (can('teachers')): ?>
            <div class="menu-section">
                <div class="menu-section-label">Команда</div>
                <?php if (can('teachers')): ?>
                <a href="teachers.php" class="menu-item <?= ACTIVE_PAGE === 'teachers' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span>Преподаватели</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (can('audit')): ?>
            <div class="menu-section">
                <div class="menu-section-label">Система</div>
                <?php if (can('audit')): ?>
                <a href="audit.php" class="menu-item <?= ACTIVE_PAGE === 'audit' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Аудит</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="menu-footer">
            <a href="logout.php" class="menu-item">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span>Выход</span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="mobile-content">
