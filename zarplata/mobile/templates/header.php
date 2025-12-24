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
    <link rel="stylesheet" href="assets/css/mobile-theme.css">

    <!-- Page-specific CSS -->
    <?php if (defined('PAGE_CSS')): ?>
    <style><?= PAGE_CSS ?></style>
    <?php endif; ?>
</head>
<body<?= SHOW_BOTTOM_NAV ? '' : ' class="no-bottom-nav"' ?>>

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
                <a href="index.php" class="menu-item <?= ACTIVE_PAGE === 'dashboard' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span>Главная</span>
                </a>
                <a href="schedule.php" class="menu-item <?= ACTIVE_PAGE === 'schedule' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>Расписание</span>
                </a>
                <a href="planner.php" class="menu-item <?= ACTIVE_PAGE === 'planner' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                    </svg>
                    <span>Планировщик</span>
                </a>
                <a href="students.php" class="menu-item <?= ACTIVE_PAGE === 'students' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                    </svg>
                    <span>Ученики</span>
                </a>
            </div>

            <div class="menu-section">
                <div class="menu-section-label">Финансы</div>
                <a href="payments.php" class="menu-item <?= ACTIVE_PAGE === 'payments' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span>Выплаты</span>
                </a>
                <a href="formulas.php" class="menu-item <?= ACTIVE_PAGE === 'formulas' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span>Формулы</span>
                </a>
                <a href="reports.php" class="menu-item <?= ACTIVE_PAGE === 'reports' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span>Отчёты</span>
                </a>
            </div>

            <div class="menu-section">
                <div class="menu-section-label">Команда</div>
                <a href="teachers.php" class="menu-item <?= ACTIVE_PAGE === 'teachers' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span>Преподаватели</span>
                </a>
            </div>

            <div class="menu-section">
                <div class="menu-section-label">Система</div>
                <a href="audit.php" class="menu-item <?= ACTIVE_PAGE === 'audit' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Аудит</span>
                </a>
                <a href="settings.php" class="menu-item <?= ACTIVE_PAGE === 'settings' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>Настройки</span>
                </a>
            </div>
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
