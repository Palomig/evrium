<?php
/**
 * Mobile Audit Log Page
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();

$logs = dbQuery("
    SELECT a.*, u.name as user_name
    FROM audit_log a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 50
", []);

define('PAGE_TITLE', 'Аудит');
define('ACTIVE_PAGE', 'audit');

require_once __DIR__ . '/templates/header.php';

$actionLabels = [
    'template_created' => 'Создан шаблон',
    'template_updated' => 'Обновлён шаблон',
    'template_deleted' => 'Удалён шаблон',
    'lesson_completed' => 'Урок завершён',
    'payment_created' => 'Создана выплата',
    'payment_updated' => 'Обновлена выплата',
    'attendance_marked' => 'Отмечена посещаемость'
];
?>

<div class="page-container">
    <?php if (empty($logs)): ?>
        <div class="empty-state">
            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <div class="empty-state-title">Нет записей</div>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 0;">
            <?php foreach ($logs as $log): ?>
                <div class="list-item" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                    <div style="display: flex; justify-content: space-between; width: 100%; align-items: flex-start;">
                        <div style="font-size: 14px; font-weight: 600;">
                            <?= $actionLabels[$log['action_type']] ?? $log['action_type'] ?>
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted);">
                            <?= date('d.m H:i', strtotime($log['created_at'])) ?>
                        </div>
                    </div>
                    <div style="font-size: 13px; color: var(--text-secondary);">
                        <?= $log['entity_type'] ?> #<?= $log['entity_id'] ?>
                        <?php if ($log['user_name']): ?>
                            • <?= htmlspecialchars($log['user_name']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($log['notes']): ?>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            <?= htmlspecialchars($log['notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
