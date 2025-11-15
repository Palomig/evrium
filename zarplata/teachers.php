<?php
/**
 * Страница управления преподавателями
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить всех преподавателей
$teachers = dbQuery(
    "SELECT * FROM teachers ORDER BY active DESC, name ASC",
    []
);

define('PAGE_TITLE', 'Преподаватели');
define('PAGE_SUBTITLE', 'Управление преподавателями');
define('ACTIVE_PAGE', 'teachers');

require_once __DIR__ . '/templates/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Все преподаватели</h2>
        <button class="btn btn-primary" onclick="alert('Функция добавления преподавателя будет реализована позже')">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Добавить преподавателя
        </button>
    </div>

    <?php if (empty($teachers)): ?>
        <div class="empty-state">
            <div class="material-icons">person_off</div>
            <p>Нет преподавателей в системе</p>
            <p style="margin-top: 8px;">
                <button class="btn btn-primary" onclick="alert('Функция добавления преподавателя будет реализована позже')">
                    Добавить первого преподавателя
                </button>
            </p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ФИО</th>
                    <th>Telegram</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Статус</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $teacher): ?>
                    <tr>
                        <td><?= $teacher['id'] ?></td>
                        <td>
                            <strong><?= e($teacher['name']) ?></strong>
                            <?php if ($teacher['notes']): ?>
                                <br>
                                <small style="color: var(--text-medium-emphasis);">
                                    <?= e(truncate($teacher['notes'], 50)) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($teacher['telegram_id']): ?>
                                <span class="badge badge-success">
                                    <span class="material-icons" style="font-size: 14px;">check_circle</span>
                                    @<?= e($teacher['telegram_username'] ?: 'ID: ' . $teacher['telegram_id']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-warning">
                                    <span class="material-icons" style="font-size: 14px;">warning</span>
                                    Не подключен
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= $teacher['phone'] ? formatPhone($teacher['phone']) : '—' ?></td>
                        <td><?= e($teacher['email'] ?: '—') ?></td>
                        <td>
                            <?php if ($teacher['active']): ?>
                                <span class="badge badge-success">
                                    <span class="material-icons" style="font-size: 14px;">check_circle</span>
                                    Активен
                                </span>
                            <?php else: ?>
                                <span class="badge badge-danger">
                                    <span class="material-icons" style="font-size: 14px;">block</span>
                                    Неактивен
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatDate($teacher['created_at']) ?></td>
                        <td>
                            <button class="btn btn-text" onclick="alert('Просмотр преподавателя #<?= $teacher['id'] ?>')">
                                <span class="material-icons" style="font-size: 18px;">visibility</span>
                            </button>
                            <button class="btn btn-text" onclick="alert('Редактирование преподавателя #<?= $teacher['id'] ?>')">
                                <span class="material-icons" style="font-size: 18px;">edit</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
