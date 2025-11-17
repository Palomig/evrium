<?php
/**
 * Утилита для очистки старых уроков
 * Временная страница для удаления уроков без поля room
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Только для администраторов
if ($user['role'] !== 'superadmin') {
    die('Доступ запрещён. Только для суперадминистратора.');
}

// Получить все уроки
$lessons = dbQuery("SELECT * FROM lessons_template WHERE active = 1 ORDER BY id ASC", []);

// Обработка удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
    if ($deleteId) {
        dbExecute("UPDATE lessons_template SET active = 0 WHERE id = ?", [$deleteId]);
        header("Location: cleanup_old_lessons.php?deleted=" . $deleteId);
        exit;
    }
}

define('PAGE_TITLE', 'Очистка старых уроков');
require_once __DIR__ . '/templates/header.php';
?>

<style>
.lesson-table {
    width: 100%;
    background: var(--md-surface);
    border-radius: 12px;
    overflow: hidden;
    margin-top: 20px;
}

.lesson-table table {
    width: 100%;
    border-collapse: collapse;
}

.lesson-table th {
    background: var(--md-surface-3);
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid var(--md-surface-5);
}

.lesson-table td {
    padding: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.lesson-table tr:hover {
    background: rgba(255, 255, 255, 0.03);
}

.btn-delete-small {
    padding: 6px 12px;
    font-size: 0.875rem;
    background: #cf6679;
    color: #000;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-delete-small:hover {
    background: #b85566;
}

.alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    background: rgba(3, 218, 198, 0.1);
    border-left: 4px solid var(--md-secondary);
}

.missing-room {
    color: #ff5555;
    font-weight: 600;
}
</style>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert">
        ✓ Урок #<?= htmlspecialchars($_GET['deleted']) ?> успешно удалён
    </div>
<?php endif; ?>

<div class="page-header" style="background: var(--md-surface); padding: 24px; border-radius: 12px; margin-bottom: 24px;">
    <h1 style="margin: 0 0 8px 0;">🗑️ Управление уроками</h1>
    <p style="margin: 0; color: var(--text-medium-emphasis);">
        Эта страница для удаления старых уроков, созданных до обновления системы.
        После очистки можете вернуться на <a href="schedule.php" style="color: var(--md-primary);">страницу расписания</a>.
    </p>
</div>

<div class="lesson-table">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Преподаватель</th>
                <th>День</th>
                <th>Время</th>
                <th>Предмет</th>
                <th>Кабинет</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($lessons)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-disabled);">
                        Нет активных уроков
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($lessons as $lesson): ?>
                    <tr>
                        <td><?= $lesson['id'] ?></td>
                        <td><?= e($lesson['teacher_name'] ?? 'Не указан') ?></td>
                        <td>
                            <?php
                            $days = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                            echo $days[$lesson['day_of_week']] ?? '?';
                            ?>
                        </td>
                        <td><?= substr($lesson['time_start'], 0, 5) ?></td>
                        <td><?= e($lesson['subject'] ?? 'Не указан') ?></td>
                        <td>
                            <?php if (isset($lesson['room']) && $lesson['room']): ?>
                                Кабинет <?= $lesson['room'] ?>
                            <?php else: ?>
                                <span class="missing-room">Не указан</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить урок #<?= $lesson['id'] ?>?')">
                                <input type="hidden" name="delete_id" value="<?= $lesson['id'] ?>">
                                <button type="submit" class="btn-delete-small">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top: 24px; padding: 16px; background: var(--md-surface); border-radius: 8px; border-left: 4px solid var(--md-primary);">
    <p style="margin: 0; color: var(--text-medium-emphasis); font-size: 0.875rem;">
        💡 <strong>Совет:</strong> Удалите старые уроки без кабинетов (помечены красным),
        затем создайте новые через обычную страницу расписания с указанием кабинета.
    </p>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
