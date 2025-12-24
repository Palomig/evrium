<?php
/**
 * Mobile Settings Page
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();
$user = getCurrentUser();

define('PAGE_TITLE', 'Настройки');
define('ACTIVE_PAGE', 'settings');

require_once __DIR__ . '/templates/header.php';
?>

<div class="page-container">
    <!-- User Info -->
    <div class="card mb-2">
        <div class="card-body">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, var(--accent), #0d9488); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 600;">
                    <?= mb_substr($user['name'] ?? $user['username'], 0, 1) ?>
                </div>
                <div>
                    <div style="font-size: 18px; font-weight: 600;"><?= htmlspecialchars($user['name'] ?? $user['username']) ?></div>
                    <div style="font-size: 14px; color: var(--text-secondary);"><?= $user['role'] === 'owner' ? 'Владелец' : 'Администратор' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Install PWA -->
    <div class="card mb-2" id="pwa-install-card" style="display: none;">
        <div class="card-header">
            <span class="card-title">Приложение</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="list-item" id="pwa-install-btn" style="cursor: pointer;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: linear-gradient(135deg, var(--accent), #0d9488); display: flex; align-items: center; justify-content: center; color: white;">
                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </div>
                <div class="list-item-content">
                    <div class="list-item-title" style="color: var(--accent);">Установить приложение</div>
                    <div class="list-item-subtitle">Добавить на главный экран</div>
                </div>
                <div class="list-item-action" style="color: var(--accent);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Version Switch -->
    <div class="card mb-2">
        <div class="card-header">
            <span class="card-title">Версия сайта</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <a href="../index.php?desktop=1" class="list-item">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: var(--bg-elevated); display: flex; align-items: center; justify-content: center;">
                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="list-item-content">
                    <div class="list-item-title">Десктопная версия</div>
                    <div class="list-item-subtitle">Переключиться на полную версию</div>
                </div>
                <div class="list-item-action">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>
        </div>
    </div>

    <!-- Links -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <a href="audit.php" class="list-item">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: var(--bg-elevated); display: flex; align-items: center; justify-content: center;">
                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="list-item-content">
                    <div class="list-item-title">Журнал аудита</div>
                    <div class="list-item-subtitle">История действий</div>
                </div>
                <div class="list-item-action">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>
            <a href="logout.php" class="list-item" style="color: var(--status-rose);">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: var(--status-rose-dim); display: flex; align-items: center; justify-content: center; color: var(--status-rose);">
                    <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </div>
                <div class="list-item-content">
                    <div class="list-item-title" style="color: var(--status-rose);">Выйти</div>
                    <div class="list-item-subtitle">Завершить сеанс</div>
                </div>
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
