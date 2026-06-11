<?php
/**
 * Смена собственного пароля (доступна всем ролям)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new1 = $_POST['new_password'] ?? '';
    $new2 = $_POST['new_password2'] ?? '';

    $dbUser = dbQueryOne("SELECT password_hash FROM users WHERE id = ?", [getCurrentUserId()]);

    if (!$dbUser || !password_verify($current, $dbUser['password_hash'])) {
        $message = 'Текущий пароль неверен';
        $messageType = 'error';
    } elseif (mb_strlen($new1) < 8) {
        $message = 'Новый пароль должен быть не короче 8 символов';
        $messageType = 'error';
    } elseif ($new1 !== $new2) {
        $message = 'Пароли не совпадают';
        $messageType = 'error';
    } elseif ($new1 === $current) {
        $message = 'Новый пароль совпадает с текущим';
        $messageType = 'error';
    } else {
        changePassword(getCurrentUserId(), $new1);
        $message = 'Пароль изменён';
        $messageType = 'success';
    }
}

define('PAGE_TITLE', 'Сменить пароль');
define('PAGE_SUBTITLE', 'Аккаунт: ' . ($user['username'] ?? ''));
define('ACTIVE_PAGE', 'password');

require_once __DIR__ . '/templates/header.php';
?>

<div class="card" style="max-width: 480px;">
    <?php if ($message): ?>
        <div style="padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;
                    background: <?= $messageType === 'success' ? 'var(--status-green-dim, rgba(34,197,94,0.12))' : 'var(--status-rose-dim, rgba(244,63,94,0.12))' ?>;
                    color: <?= $messageType === 'success' ? 'var(--status-green, #22c55e)' : 'var(--status-rose, #f43f5e)' ?>;
                    font-weight: 600; font-size: 14px;">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <div class="form-group">
            <label for="current_password">Текущий пароль</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label for="new_password">Новый пароль (мин. 8 символов)</label>
            <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="new_password2">Новый пароль ещё раз</label>
            <input type="password" id="new_password2" name="new_password2" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary">Сменить пароль</button>
    </form>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
