<?php
/**
 * Страница уроков
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить все уроки (последние 100)
$lessons = dbQuery(
    "SELECT li.*, t.name as teacher_name,
            CASE WHEN li.substitute_teacher_id IS NOT NULL
                 THEN (SELECT name FROM teachers WHERE id = li.substitute_teacher_id)
                 ELSE NULL
            END as substitute_name
     FROM lessons_instance li
     LEFT JOIN teachers t ON li.teacher_id = t.id
     ORDER BY li.lesson_date DESC, li.time_start DESC
     LIMIT 100",
    []
);

// Получить преподавателей для селекта
$teachers = dbQuery("SELECT id, name FROM teachers WHERE active = 1 ORDER BY name", []);

// Получить формулы для селекта
$formulas = dbQuery("SELECT id, name, type FROM payment_formulas WHERE active = 1 ORDER BY name", []);

define('PAGE_TITLE', 'Уроки');
define('PAGE_SUBTITLE', 'История и управление уроками');
define('ACTIVE_PAGE', 'lessons');

require_once __DIR__ . '/templates/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Все уроки (<?= count($lessons) ?>)</h2>
        <button class="btn btn-primary" onclick="openLessonModal()">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Добавить урок
        </button>
    </div>

    <?php if (empty($lessons)): ?>
        <div class="empty-state">
            <div class="material-icons">school</div>
            <p>Нет данных об уроках</p>
            <p style="margin-top: 8px;">
                <button class="btn btn-primary" onclick="openLessonModal()">
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
                    <tr data-lesson-id="<?= $lesson['id'] ?>">
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
                            <button class="btn btn-text" onclick="viewLesson(<?= $lesson['id'] ?>)" title="Просмотр">
                                <span class="material-icons" style="font-size: 18px;">visibility</span>
                            </button>
                            <?php if ($lesson['status'] === 'scheduled'): ?>
                                <button class="btn btn-text" onclick="editLesson(<?= $lesson['id'] ?>)" title="Редактировать">
                                    <span class="material-icons" style="font-size: 18px;">edit</span>
                                </button>
                                <button class="btn btn-text" onclick="completeLesson(<?= $lesson['id'] ?>)" title="Завершить">
                                    <span class="material-icons" style="font-size: 18px;">check_circle</span>
                                </button>
                                <button class="btn btn-text" onclick="cancelLesson(<?= $lesson['id'] ?>)" title="Отменить">
                                    <span class="material-icons" style="font-size: 18px;">cancel</span>
                                </button>
                            <?php endif; ?>
                            <?php if ($lesson['status'] !== 'completed' || !hasPayment($lesson['id'])): ?>
                                <button class="btn btn-text" onclick="deleteLesson(<?= $lesson['id'] ?>)" title="Удалить" style="color: var(--md-error);">
                                    <span class="material-icons" style="font-size: 18px;">delete</span>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
// Функция проверки наличия выплаты (для демонстрации)
function hasPayment($lessonId) {
    $payment = dbQueryOne(
        "SELECT id FROM payments WHERE lesson_instance_id = ? AND status != 'cancelled'",
        [$lessonId]
    );
    return (bool)$payment;
}
?>

<!-- Модальное окно для добавления/редактирования урока -->
<div id="lesson-modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 id="modal-title">Добавить урок</h2>
            <button class="modal-close" onclick="closeLessonModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <form id="lesson-form" onsubmit="saveLesson(event)">
            <input type="hidden" id="lesson-id" name="id">

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="lesson-teacher">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">person</span>
                            Преподаватель *
                        </label>
                        <select class="form-control" id="lesson-teacher" name="teacher_id" required>
                            <option value="">Выберите преподавателя</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['id'] ?>"><?= e($teacher['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="lesson-date">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">event</span>
                            Дата урока *
                        </label>
                        <input
                            type="date"
                            class="form-control"
                            id="lesson-date"
                            name="lesson_date"
                            required
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="lesson-time-start">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">schedule</span>
                            Время начала *
                        </label>
                        <input
                            type="time"
                            class="form-control"
                            id="lesson-time-start"
                            name="time_start"
                            required
                        >
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="lesson-time-end">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">schedule</span>
                            Время окончания *
                        </label>
                        <input
                            type="time"
                            class="form-control"
                            id="lesson-time-end"
                            name="time_end"
                            required
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="lesson-type">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">groups</span>
                            Тип урока *
                        </label>
                        <select class="form-control" id="lesson-type" name="lesson_type" required>
                            <option value="group">Групповое</option>
                            <option value="individual">Индивидуальное</option>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="lesson-students">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">people</span>
                            Ожидаемо учеников *
                        </label>
                        <input
                            type="number"
                            class="form-control"
                            id="lesson-students"
                            name="expected_students"
                            min="1"
                            value="1"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="lesson-subject">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">book</span>
                        Предмет
                    </label>
                    <input
                        type="text"
                        class="form-control"
                        id="lesson-subject"
                        name="subject"
                        placeholder="Математика, Физика, и т.д."
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="lesson-formula">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">functions</span>
                        Формула оплаты
                    </label>
                    <select class="form-control" id="lesson-formula" name="formula_id">
                        <option value="">Без формулы</option>
                        <?php foreach ($formulas as $formula): ?>
                            <option value="<?= $formula['id'] ?>"><?= e($formula['name']) ?> (<?= $formula['type'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="lesson-notes">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">notes</span>
                        Примечания
                    </label>
                    <textarea
                        class="form-control"
                        id="lesson-notes"
                        name="notes"
                        rows="2"
                        placeholder="Дополнительная информация"
                    ></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeLessonModal()">
                    Отмена
                </button>
                <button type="submit" class="btn btn-primary" id="save-lesson-btn">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                    Сохранить
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно для завершения урока -->
<div id="complete-lesson-modal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Завершить урок</h2>
            <button class="modal-close" onclick="closeCompleteModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <form id="complete-form" onsubmit="saveComplete(event)">
            <input type="hidden" id="complete-lesson-id">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="actual-students">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">people</span>
                        Сколько учеников пришло? *
                    </label>
                    <input
                        type="number"
                        class="form-control"
                        id="actual-students"
                        name="actual_students"
                        min="0"
                        value="0"
                        required
                    >
                    <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                        После завершения будет автоматически рассчитана оплата
                    </small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeCompleteModal()">
                    Отмена
                </button>
                <button type="submit" class="btn btn-primary" id="complete-lesson-btn">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">check_circle</span>
                    Завершить урок
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно для просмотра урока -->
<div id="view-lesson-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Информация об уроке</h2>
            <button class="modal-close" onclick="closeViewModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body" id="view-lesson-content">
            <!-- Контент загружается динамически -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeViewModal()">
                Закрыть
            </button>
        </div>
    </div>
</div>

<style>
    /* Переиспользуем стили из teachers.php */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        animation: fadeIn 0.2s;
    }

    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background-color: var(--md-surface);
        border-radius: 12px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: var(--elevation-5);
        animation: slideUp 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-header {
        padding: 24px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 400;
    }

    .modal-close {
        background: none;
        border: none;
        color: var(--text-medium-emphasis);
        cursor: pointer;
        padding: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background-color 0.2s;
    }

    .modal-close:hover {
        background-color: rgba(255, 255, 255, 0.08);
    }

    .modal-body {
        padding: 24px;
        overflow-y: auto;
        flex: 1;
    }

    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.12);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    .form-row {
        display: flex;
        gap: 16px;
    }

    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
        }
    }

    /* Уведомления */
    .notification {
        position: fixed;
        top: 24px;
        right: 24px;
        background: var(--md-surface);
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: var(--elevation-3);
        display: flex;
        align-items: center;
        gap: 12px;
        transform: translateX(400px);
        transition: transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
        z-index: 10000;
    }

    .notification.show {
        transform: translateX(0);
    }

    .notification-success {
        border-left: 4px solid var(--md-success);
        color: var(--md-success);
    }

    .notification-error {
        border-left: 4px solid var(--md-error);
        color: var(--md-error);
    }

    .notification-info {
        border-left: 4px solid var(--md-info);
        color: var(--md-info);
    }

    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .rotating {
        animation: rotate 1s linear infinite;
    }
</style>

<script src="/zarplata/assets/js/lessons.js"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
