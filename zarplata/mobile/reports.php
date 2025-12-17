<?php
/**
 * Mobile Reports Page
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();

define('PAGE_TITLE', 'Отчёты');
define('ACTIVE_PAGE', 'reports');

require_once __DIR__ . '/templates/header.php';
?>

<div class="page-container">
    <div class="empty-state">
        <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <div class="empty-state-title">Отчёты</div>
        <p class="empty-state-text">Для работы с отчётами используйте десктопную версию</p>
        <a href="../reports.php?desktop=1" class="btn btn-primary mt-2">Открыть десктопную версию</a>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
