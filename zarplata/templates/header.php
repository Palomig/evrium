<?php
/**
 * Общий header и sidebar для всех страниц
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
    <title><?= PAGE_TITLE ?> — Учёт зарплаты</title>

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Montserrat Font -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Dark Theme CSS -->
    <link rel="stylesheet" href="/zarplata/assets/css/material-dark.css">

    <style>
        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background-color: var(--md-surface);
            border-right: 1px solid rgba(255, 255, 255, 0.12);
            padding: 24px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 24px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .sidebar-logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--md-primary), var(--md-secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-logo-text {
            font-size: 1.25rem;
            font-weight: 500;
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background-color: var(--md-surface-3);
            border-radius: 8px;
        }

        .sidebar-user-avatar {
            width: 40px;
            height: 40px;
            background: var(--md-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--md-on-primary);
            font-weight: 500;
        }

        .sidebar-user-info {
            flex: 1;
        }

        .sidebar-user-name {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .sidebar-user-role {
            font-size: 0.75rem;
            color: var(--text-medium-emphasis);
        }

        .sidebar-nav {
            padding: 16px 0;
        }

        .sidebar-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: var(--text-high-emphasis);
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .sidebar-nav-item:hover {
            background-color: rgba(255, 255, 255, 0.08);
        }

        .sidebar-nav-item.active {
            background-color: rgba(187, 134, 252, 0.12);
            color: var(--md-primary);
            border-right: 3px solid var(--md-primary);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 24px;
            background-color: var(--md-background);
            max-width: calc(100vw - 280px);
            overflow-x: hidden;
            box-sizing: border-box;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 300;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 0.875rem;
            color: var(--text-medium-emphasis);
        }

        /* Table */
        .table-container {
            background-color: var(--md-surface);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--elevation-2);
            margin-bottom: 24px;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 500;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: var(--md-surface-2);
        }

        th {
            text-align: left;
            padding: 16px 24px;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-medium-emphasis);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        td {
            padding: 16px 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        tbody tr {
            transition: background-color 0.2s;
        }

        tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.04);
        }

        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-disabled);
        }

        .empty-state .material-icons {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-success {
            background-color: rgba(76, 175, 80, 0.12);
            color: var(--md-success);
        }

        .badge-warning {
            background-color: rgba(255, 152, 0, 0.12);
            color: var(--md-warning);
        }

        .badge-info {
            background-color: rgba(33, 150, 243, 0.12);
            color: var(--md-info);
        }

        .badge-danger {
            background-color: rgba(207, 102, 121, 0.12);
            color: var(--md-error);
        }

        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="sidebar-logo-icon">
                        <span class="material-icons">account_balance_wallet</span>
                    </div>
                    <span class="sidebar-logo-text">Зарплаты</span>
                </div>

                <div class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        <?= strtoupper(mb_substr($user['name'], 0, 1)) ?>
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?= e($user['name']) ?></div>
                        <div class="sidebar-user-role"><?= $user['role'] === 'owner' ? 'Владелец' : 'Администратор' ?></div>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="/zarplata/" class="sidebar-nav-item <?= ACTIVE_PAGE === 'dashboard' ? 'active' : '' ?>">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="/zarplata/teachers.php" class="sidebar-nav-item <?= ACTIVE_PAGE === 'teachers' ? 'active' : '' ?>">
                    <span class="material-icons">person</span>
                    <span>Преподаватели</span>
                </a>
                <a href="/zarplata/schedule.php" class="sidebar-nav-item <?= ACTIVE_PAGE === 'schedule' ? 'active' : '' ?>">
                    <span class="material-icons">event</span>
                    <span>Расписание</span>
                </a>
                <a href="/zarplata/students.php" class="sidebar-nav-item <?= ACTIVE_PAGE === 'students' ? 'active' : '' ?>">
                    <span class="material-icons">groups</span>
                    <span>Ученики</span>
                </a>
                <a href="/zarplata/payments.php" class="sidebar-nav-item <?= ACTIVE_PAGE === 'payments' ? 'active' : '' ?>">
                    <span class="material-icons">payments</span>
                    <span>Выплаты</span>
                </a>
                <a href="/zarplata/reports.php" class="sidebar-nav-item <?= ACTIVE_PAGE === 'reports' ? 'active' : '' ?>">
                    <span class="material-icons">assessment</span>
                    <span>Отчёты</span>
                </a>
                <a href="/zarplata/formulas.php" class="sidebar-nav-item <?= ACTIVE_PAGE === 'formulas' ? 'active' : '' ?>">
                    <span class="material-icons">functions</span>
                    <span>Формулы оплаты</span>
                </a>
                <a href="/zarplata/settings.php" class="sidebar-nav-item <?= ACTIVE_PAGE === 'settings' ? 'active' : '' ?>">
                    <span class="material-icons">settings</span>
                    <span>Настройки</span>
                </a>
                <a href="/zarplata/tests.php" class="sidebar-nav-item <?= ACTIVE_PAGE === 'tests' ? 'active' : '' ?>">
                    <span class="material-icons">bug_report</span>
                    <span>Тесты</span>
                </a>
                <a href="/zarplata/logout.php" class="sidebar-nav-item">
                    <span class="material-icons">logout</span>
                    <span>Выход</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title"><?= PAGE_TITLE ?></h1>
                <?php if (PAGE_SUBTITLE): ?>
                    <p class="page-subtitle"><?= PAGE_SUBTITLE ?></p>
                <?php endif; ?>
            </div>
