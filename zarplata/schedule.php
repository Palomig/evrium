<?php
/**
 * Страница расписания
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить шаблоны расписания
$templates = dbQuery(
    "SELECT lt.*, t.name as teacher_name, pf.name as formula_name
     FROM lessons_template lt
     LEFT JOIN teachers t ON lt.teacher_id = t.id
     LEFT JOIN payment_formulas pf ON lt.formula_id = pf.id
     WHERE lt.active = 1
     ORDER BY lt.day_of_week ASC, lt.time_start ASC",
    []
);

// Получить уроки на текущую неделю
$weekStart = getWeekStart();
$weekEnd = getWeekEnd();

$weekLessons = dbQuery(
    "SELECT li.*, t.name as teacher_name
     FROM lessons_instance li
     LEFT JOIN teachers t ON li.teacher_id = t.id
     WHERE li.lesson_date BETWEEN ? AND ?
     ORDER BY li.lesson_date ASC, li.time_start ASC",
    [$weekStart, $weekEnd]
);

define('PAGE_TITLE', 'Расписание');
define('PAGE_SUBTITLE', 'Шаблон расписания и уроки на неделю');
define('ACTIVE_PAGE', 'schedule');

require_once __DIR__ . '/templates/header.php';
?>

<!-- Уроки на текущую неделю -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">
            Уроки на неделю
            <small style="color: var(--text-medium-emphasis); font-weight: 400;">
                (<?= formatDate($weekStart) ?> - <?= formatDate($weekEnd) ?>)
            </small>
        </h2>
        <button class="btn btn-primary" onclick="alert('Функция добавления разового урока будет реализована позже')">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Разовый урок
        </button>
    </div>

    <?php if (empty($weekLessons)): ?>
        <div class="empty-state">
            <div class="material-icons">event_busy</div>
            <p>Нет уроков на эту неделю</p>
            <p style="margin-top: 8px;">
                <small style="color: var(--text-medium-emphasis);">
                    Уроки создаются автоматически из шаблона расписания
                </small>
            </p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>День недели</th>
                    <th>Дата</th>
                    <th>Время</th>
                    <th>Преподаватель</th>
                    <th>Предмет</th>
                    <th>Тип</th>
                    <th>Учеников</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($weekLessons as $lesson): ?>
                    <?php
                        $statusBadge = getLessonStatusBadge($lesson['status']);
                        $dayOfWeek = date('N', strtotime($lesson['lesson_date']));
                    ?>
                    <tr>
                        <td><?= getDayName($dayOfWeek) ?></td>
                        <td><?= formatDate($lesson['lesson_date']) ?></td>
                        <td><?= formatTime($lesson['time_start']) ?> - <?= formatTime($lesson['time_end']) ?></td>
                        <td><?= e($lesson['teacher_name']) ?></td>
                        <td><?= e($lesson['subject'] ?: '—') ?></td>
                        <td><?= $lesson['lesson_type'] === 'group' ? 'Групповое' : 'Индивид.' ?></td>
                        <td><?= $lesson['expected_students'] ?></td>
                        <td>
                            <span class="badge badge-<?= $statusBadge['class'] ?>">
                                <span class="material-icons" style="font-size: 14px;"><?= $statusBadge['icon'] ?></span>
                                <?= $statusBadge['text'] ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Шаблон расписания -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Шаблон расписания (еженедельный)</h2>
        <button class="btn btn-primary" onclick="alert('Функция добавления урока в шаблон будет реализована позже')">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Добавить в шаблон
        </button>
    </div>

    <?php if (empty($templates)): ?>
        <div class="empty-state">
            <div class="material-icons">event_note</div>
            <p>Шаблон расписания пуст</p>
            <p style="margin-top: 8px;">
                <button class="btn btn-primary" onclick="alert('Функция добавления урока в шаблон будет реализована позже')">
                    Создать шаблон
                </button>
            </p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>День недели</th>
                    <th>Время</th>
                    <th>Преподаватель</th>
                    <th>Предмет</th>
                    <th>Тип</th>
                    <th>Учеников</th>
                    <th>Формула оплаты</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <td><?= $template['id'] ?></td>
                        <td><strong><?= getDayName($template['day_of_week']) ?></strong></td>
                        <td><?= formatTime($template['time_start']) ?> - <?= formatTime($template['time_end']) ?></td>
                        <td><?= e($template['teacher_name']) ?></td>
                        <td><?= e($template['subject'] ?: '—') ?></td>
                        <td>
                            <?php if ($template['lesson_type'] === 'group'): ?>
                                <span class="badge badge-info">Групповое</span>
                            <?php else: ?>
                                <span class="badge badge-success">Индивид.</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $template['expected_students'] ?></td>
                        <td><?= e($template['formula_name'] ?: '—') ?></td>
                        <td>
                            <button class="btn btn-text" onclick="alert('Редактировать шаблон #<?= $template['id'] ?>')">
                                <span class="material-icons" style="font-size: 18px;">edit</span>
                            </button>
                            <button class="btn btn-text" onclick="if(confirm('Удалить урок из шаблона?')) alert('Удалить шаблон #<?= $template['id'] ?>')">
                                <span class="material-icons" style="font-size: 18px;">delete</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
