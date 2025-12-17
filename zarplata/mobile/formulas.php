<?php
/**
 * Mobile Formulas Page
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();

$formulas = dbQuery("SELECT * FROM payment_formulas WHERE active = 1 ORDER BY name", []);

define('PAGE_TITLE', 'Формулы');
define('ACTIVE_PAGE', 'formulas');

require_once __DIR__ . '/templates/header.php';

$typeLabels = [
    'min_plus_per' => 'База + за ученика',
    'fixed' => 'Фиксированная',
    'expression' => 'Выражение'
];
?>

<div class="page-container">
    <?php if (empty($formulas)): ?>
        <div class="empty-state">
            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <div class="empty-state-title">Нет формул</div>
        </div>
    <?php else: ?>
        <?php foreach ($formulas as $f): ?>
            <div class="card" style="margin-bottom: 12px;">
                <div class="card-body">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div>
                            <div style="font-size: 16px; font-weight: 600;"><?= htmlspecialchars($f['name']) ?></div>
                            <div style="font-size: 13px; color: var(--text-secondary);">
                                <?= $typeLabels[$f['type']] ?? $f['type'] ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($f['type'] === 'min_plus_per'): ?>
                        <div style="font-family: 'JetBrains Mono', monospace; font-size: 14px; color: var(--accent);">
                            <?= number_format($f['min_payment'], 0, '', ' ') ?> ₽ + <?= number_format($f['per_student'], 0, '', ' ') ?> ₽/уч. (от <?= $f['threshold'] ?>)
                        </div>
                    <?php elseif ($f['type'] === 'fixed'): ?>
                        <div style="font-family: 'JetBrains Mono', monospace; font-size: 14px; color: var(--accent);">
                            <?= number_format($f['fixed_amount'], 0, '', ' ') ?> ₽
                        </div>
                    <?php else: ?>
                        <div style="font-family: 'JetBrains Mono', monospace; font-size: 13px; color: var(--text-secondary);">
                            <?= htmlspecialchars($f['expression']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($f['description']): ?>
                        <div style="margin-top: 8px; font-size: 13px; color: var(--text-muted);">
                            <?= htmlspecialchars($f['description']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<p style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 13px;">
    Для редактирования формул используйте
    <a href="../formulas.php?desktop=1" style="color: var(--accent);">десктопную версию</a>
</p>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
