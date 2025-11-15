<?php
/**
 * Страница уроков
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить все уроки
$lessons = dbQuery(
    "SELECT li.*, t.name as teacher_name,
            CASE WHEN li.substitute_teacher_id IS NOT NULL
                 THEN (SELECT name FROM teachers WHERE id = li.substitute_teacher_id)
                 ELSE NULL
            END as substitute_name
     FROM lessons_instance li
     LEFT JOIN teachers t ON li.teacher_id = t.id
     ORDER BY li.lesson_date DESC, li.time_start DESC
     LIMIT 50",
    []
);

define('PAGE_TITLE', 'Уроки');
define('PAGE_SUBTITLE', 'История и управление уроками');
define('ACTIVE_PAGE', 'lessons');

require_once __DIR__ . '/templates/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Все уроки</h2>
        <button class="btn btn-primary" onclick="alert('Функция добавления урока будет реализована позже')">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Добавить урок
        </button>
    </div>

    <?php if (empty($lessons)): ?>
        <div class="empty-state">
            <div class="material-icons">school</div>
            <p>Нет данных об уроках</p>
            <p style="margin-top: 8px;">
                <button class="btn btn-primary" onclick="alert('Функция добавления урока будет реализована позже')">
                    Добавить первый урок
                </button>
            </p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Дата</th>
                    <th>Время</th>
                    <th>Преподаватель</th>
                    <th>Предмет</th>
                    <th>Тип</th>
                    <th>Учеников</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lessons as $lesson): ?>
                    <?php $statusBadge = getLessonStatusBadge($lesson['status']); ?>
                    <tr>
                        <td><?= $lesson['id'] ?></td>
                        <td>
                            <?= formatDate($lesson['lesson_date']) ?>
                            <?php if (isToday($lesson['lesson_date'])): ?>
                                <br><span class="badge badge-info" style="font-size: 0.7rem;">Сегодня</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatTime($lesson['time_start']) ?> - <?= formatTime($lesson['time_end']) ?></td>
                        <td>
                            <?= e($lesson['teacher_name']) ?>
                            <?php if ($lesson['substitute_name']): ?>
                                <br>
                                <small style="color: var(--text-medium-emphasis);">
                                    Замена: <?= e($lesson['substitute_name']) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($lesson['subject'] ?: '—') ?></td>
                        <td>
                            <?php if ($lesson['lesson_type'] === 'group'): ?>
                                <span class="badge badge-info">
                                    <span class="material-icons" style="font-size: 14px;">groups</span>
                                    Групповое
                                </span>
                            <?php else: ?>
                                <span class="badge badge-success">
                                    <span class="material-icons" style="font-size: 14px;">person</span>
                                    Индивид.
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($lesson['status'] === 'completed'): ?>
                                <?= $lesson['actual_students'] ?> / <?= $lesson['expected_students'] ?>
                            <?php else: ?>
                                <?= $lesson['expected_students'] ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $statusBadge['class'] ?>">
                                <span class="material-icons" style="font-size: 14px;"><?= $statusBadge['icon'] ?></span>
                                <?= $statusBadge['text'] ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-text" onclick="alert('Просмотр урока #<?= $lesson['id'] ?>')">
                                <span class="material-icons" style="font-size: 18px;">visibility</span>
                            </button>
                            <?php if ($lesson['status'] === 'scheduled'): ?>
                                <button class="btn btn-text" onclick="alert('Завершить урок #<?= $lesson['id'] ?>')">
                                    <span class="material-icons" style="font-size: 18px;">check</span>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
