<?php
/**
 * Страница расписания
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить преподавателей и формулы для селектов
$teachers = dbQuery("SELECT id, name FROM teachers WHERE active = 1 ORDER BY name", []);
$formulas = dbQuery("SELECT id, name FROM payment_formulas WHERE active = 1 ORDER BY name", []);

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
        <div>
            <?php if (empty($weekLessons)): ?>
                <button class="btn btn-secondary" onclick="generateWeek()" style="margin-right: 8px;">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">auto_fix_high</span>
                    Генерировать из шаблона
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($weekLessons)): ?>
        <div class="empty-state">
            <div class="material-icons">event_busy</div>
            <p>Нет уроков на эту неделю</p>
            <p style="margin-top: 8px;">
                <small style="color: var(--text-medium-emphasis);">
                    Нажмите "Генерировать из шаблона" чтобы создать уроки автоматически
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
        <button class="btn btn-primary" onclick="openTemplateModal()">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Добавить в шаблон
        </button>
    </div>

    <?php if (empty($templates)): ?>
        <div class="empty-state">
            <div class="material-icons">event_note</div>
            <p>Шаблон расписания пуст</p>
            <p style="margin-top: 8px;">
                <button class="btn btn-primary" onclick="openTemplateModal()">
                    Создать первый шаблон
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
                            <button class="btn btn-text" onclick="editTemplate(<?= $template['id'] ?>)" title="Редактировать">
                                <span class="material-icons" style="font-size: 18px;">edit</span>
                            </button>
                            <button class="btn btn-text" onclick="deleteTemplate(<?= $template['id'] ?>)" title="Удалить">
                                <span class="material-icons" style="font-size: 18px; color: var(--md-error);">delete</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Модальное окно добавления/редактирования шаблона -->
<div id="template-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Добавить урок в шаблон</h3>
            <button class="modal-close" onclick="closeTemplateModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <form id="template-form" onsubmit="saveTemplate(event)">
            <input type="hidden" id="template-id" name="id">

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="template-teacher">Преподаватель *</label>
                    <select id="template-teacher" name="teacher_id" required>
                        <option value="">Выберите преподавателя</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>"><?= e($teacher['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="template-day">День недели *</label>
                    <select id="template-day" name="day_of_week" required>
                        <option value="">Выберите день</option>
                        <option value="1">Понедельник</option>
                        <option value="2">Вторник</option>
                        <option value="3">Среда</option>
                        <option value="4">Четверг</option>
                        <option value="5">Пятница</option>
                        <option value="6">Суббота</option>
                        <option value="7">Воскресенье</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Время начала урока *</label>
                <input type="hidden" id="template-time-start" name="time_start" required>
                <input type="hidden" id="template-time-end" name="time_end" required>
                <div class="time-buttons">
                    <?php for ($hour = 8; $hour <= 21; $hour++): ?>
                        <button type="button" class="time-btn" data-hour="<?= $hour ?>" onclick="selectTime(<?= $hour ?>)">
                            <?= sprintf('%02d', $hour) ?>
                        </button>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Предмет *</label>
                <input type="hidden" id="template-subject" name="subject" required>
                <div class="subject-buttons">
                    <button type="button" class="subject-btn" data-subject="Математика" onclick="selectSubject('Математика')">
                        Математика
                    </button>
                    <button type="button" class="subject-btn" data-subject="Физика" onclick="selectSubject('Физика')">
                        Физика
                    </button>
                    <button type="button" class="subject-btn" data-subject="Информатика" onclick="selectSubject('Информатика')">
                        Информатика
                    </button>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="template-type">Тип урока *</label>
                    <select id="template-type" name="lesson_type" required>
                        <option value="group">Групповое</option>
                        <option value="individual">Индивидуальное</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="template-students">Количество учеников *</label>
                    <input type="number" id="template-students" name="expected_students" min="1" value="1" required>
                </div>
            </div>

            <div class="form-group">
                <label for="template-formula">Формула оплаты</label>
                <select id="template-formula" name="formula_id">
                    <option value="">Не выбрана</option>
                    <?php foreach ($formulas as $formula): ?>
                        <option value="<?= $formula['id'] ?>"><?= e($formula['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-text" onclick="closeTemplateModal()">Отмена</button>
                <button type="submit" class="btn btn-primary" id="save-template-btn">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                    Сохранить
                </button>
            </div>
        </form>
    </div>
</div>

<script src="/zarplata/assets/js/schedule.js"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
