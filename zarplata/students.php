<?php
/**
 * Страница управления учениками
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить всех учеников
$students = dbQuery(
    "SELECT * FROM students ORDER BY active DESC, class ASC, name ASC",
    []
);

define('PAGE_TITLE', 'Ученики');
define('PAGE_SUBTITLE', 'Управление учениками');
define('ACTIVE_PAGE', 'students');

require_once __DIR__ . '/templates/header.php';
?>

<!-- Панель фильтров -->
<div class="filters-panel">
    <div class="filters-content">
        <div class="filter-group">
            <span class="legend-label">Класс:</span>
            <button class="class-filter-btn active" data-class="all" onclick="toggleClassFilter(this)">Все</button>
            <?php for ($i = 7; $i <= 11; $i++): ?>
                <button class="class-filter-btn active" data-class="<?= $i ?>" onclick="toggleClassFilter(this)"><?= $i ?></button>
            <?php endfor; ?>
        </div>

        <div class="filter-divider"></div>

        <div class="filter-group">
            <span class="legend-label">Тип:</span>
            <button class="type-filter-btn active" data-type="all" onclick="toggleTypeFilter(this)">Все</button>
            <button class="type-filter-btn active" data-type="group" onclick="toggleTypeFilter(this)">Групповые</button>
            <button class="type-filter-btn active" data-type="individual" onclick="toggleTypeFilter(this)">Индивидуальные</button>
        </div>

        <div class="filter-divider"></div>

        <div class="filter-group">
            <input type="text" id="search-input" class="search-input" placeholder="Поиск по имени..." onkeyup="filterByName()">
        </div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Все ученики (<?= count($students) ?>)</h2>
        <button class="btn btn-primary" onclick="openStudentModal()">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Добавить ученика
        </button>
    </div>

    <?php if (empty($students)): ?>
        <div class="empty-state">
            <div class="material-icons">person_off</div>
            <p>Нет учеников в системе</p>
            <p style="margin-top: 8px;">
                <button class="btn btn-primary" onclick="openStudentModal()">
                    Добавить первого ученика
                </button>
            </p>
        </div>
    <?php else: ?>
        <table id="students-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ФИО</th>
                    <th>Класс</th>
                    <th>Тип занятий</th>
                    <th>Расписание</th>
                    <th>Цена/месяц</th>
                    <th>Телефон</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr data-student-id="<?= $student['id'] ?>"
                        data-class="<?= $student['class'] ?? 'none' ?>"
                        data-type="<?= $student['lesson_type'] ?? 'group' ?>"
                        data-name="<?= mb_strtolower($student['name']) ?>"
                        class="student-row">
                        <td><?= $student['id'] ?></td>
                        <td>
                            <strong><?= e($student['name']) ?></strong>
                            <?php if ($student['notes']): ?>
                                <br>
                                <small style="color: var(--text-medium-emphasis);">
                                    <?= e(truncate($student['notes'], 50)) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($student['class']): ?>
                                <span class="badge badge-info"><?= $student['class'] ?> класс</span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $lessonType = $student['lesson_type'] ?? 'group';
                            if ($lessonType === 'individual'):
                            ?>
                                <span class="badge badge-warning">
                                    <span class="material-icons" style="font-size: 14px;">person</span>
                                    Индивид.
                                </span>
                            <?php else: ?>
                                <span class="badge badge-success">
                                    <span class="material-icons" style="font-size: 14px;">group</span>
                                    Группа
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($student['lesson_day'] && $student['lesson_time']): ?>
                                <?php
                                $days = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                                $dayName = $days[$student['lesson_day']] ?? '—';
                                ?>
                                <strong><?= $dayName ?></strong> в <?= formatTime($student['lesson_time']) ?>
                            <?php else: ?>
                                <span style="color: var(--text-medium-emphasis);">Не указано</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= formatMoney($student['monthly_price'] ?? 0) ?></strong>
                        </td>
                        <td>
                            <?= $student['phone'] ? formatPhone($student['phone']) : '—' ?>
                            <?php if ($student['parent_phone']): ?>
                                <br><small style="color: var(--text-medium-emphasis);">Родитель: <?= formatPhone($student['parent_phone']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($student['active']): ?>
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
                        <td>
                            <button class="btn btn-text" onclick="viewStudent(<?= $student['id'] ?>)" title="Просмотр">
                                <span class="material-icons" style="font-size: 18px;">visibility</span>
                            </button>
                            <button class="btn btn-text" onclick="editStudent(<?= $student['id'] ?>)" title="Редактировать">
                                <span class="material-icons" style="font-size: 18px;">edit</span>
                            </button>
                            <button class="btn btn-text" onclick="toggleStudentActive(<?= $student['id'] ?>)" title="Изменить статус">
                                <span class="material-icons" style="font-size: 18px;">
                                    <?= $student['active'] ? 'block' : 'check_circle' ?>
                                </span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Модальное окно для добавления/редактирования ученика -->
<div id="student-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Добавить ученика</h2>
            <button class="modal-close" onclick="closeStudentModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <form id="student-form" onsubmit="saveStudent(event)">
            <input type="hidden" id="student-id" name="id">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="student-name">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">person</span>
                        ФИО ученика *
                    </label>
                    <input
                        type="text"
                        class="form-control"
                        id="student-name"
                        name="name"
                        placeholder="Иванов Иван Иванович"
                        required
                        maxlength="100"
                    >
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="student-class">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">school</span>
                            Класс
                        </label>
                        <select class="form-control" id="student-class" name="class">
                            <option value="">Не указан</option>
                            <?php for ($i = 7; $i <= 11; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> класс</option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="student-lesson-type">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">groups</span>
                            Тип занятий *
                        </label>
                        <select class="form-control" id="student-lesson-type" name="lesson_type" required onchange="updatePrice()">
                            <option value="group">Групповые (5000₽/мес)</option>
                            <option value="individual">Индивидуальные (1500₽/мес)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="student-lesson-day">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">calendar_today</span>
                            День недели
                        </label>
                        <select class="form-control" id="student-lesson-day" name="lesson_day">
                            <option value="">Не указан</option>
                            <option value="1">Понедельник</option>
                            <option value="2">Вторник</option>
                            <option value="3">Среда</option>
                            <option value="4">Четверг</option>
                            <option value="5">Пятница</option>
                            <option value="6">Суббота</option>
                            <option value="7">Воскресенье</option>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="student-lesson-time">
                            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">schedule</span>
                            Время
                        </label>
                        <input
                            type="time"
                            class="form-control"
                            id="student-lesson-time"
                            name="lesson_time"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="student-monthly-price">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">payments</span>
                        Цена за месяц (₽) *
                    </label>
                    <input
                        type="number"
                        class="form-control"
                        id="student-monthly-price"
                        name="monthly_price"
                        placeholder="5000"
                        required
                        min="0"
                        step="100"
                    >
                    <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                        Стандартные цены: групповые — 5000₽, индивидуальные — 1500₽ (8 занятий/месяц)
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="student-phone">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">phone</span>
                        Телефон ученика
                    </label>
                    <input
                        type="tel"
                        class="form-control"
                        id="student-phone"
                        name="phone"
                        placeholder="+7 (999) 123-45-67"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="student-parent-phone">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">phone</span>
                        Телефон родителя
                    </label>
                    <input
                        type="tel"
                        class="form-control"
                        id="student-parent-phone"
                        name="parent_phone"
                        placeholder="+7 (999) 123-45-67"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="student-email">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">email</span>
                        Email
                    </label>
                    <input
                        type="email"
                        class="form-control"
                        id="student-email"
                        name="email"
                        placeholder="student@example.com"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="student-notes">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">notes</span>
                        Примечания
                    </label>
                    <textarea
                        class="form-control"
                        id="student-notes"
                        name="notes"
                        rows="3"
                        placeholder="Дополнительная информация об ученике"
                    ></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeStudentModal()">
                    Отмена
                </button>
                <button type="submit" class="btn btn-primary" id="save-student-btn">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                    Сохранить
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно для просмотра ученика -->
<div id="view-student-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Информация об ученике</h2>
            <button class="modal-close" onclick="closeViewModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body" id="view-student-content">
            <!-- Контент загружается динамически -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeViewModal()">
                Закрыть
            </button>
            <button type="button" class="btn btn-primary" onclick="editStudentFromView()">
                <span class="material-icons" style="margin-right: 8px; font-size: 18px;">edit</span>
                Редактировать
            </button>
        </div>
    </div>
</div>

<style>
    /* Модальное окно */
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
        max-height: 85vh;
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
        flex-shrink: 0;
        background-color: var(--md-surface);
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

    /* Форма внутри модального окна */
    #student-form {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
    }

    .modal-body {
        padding: 24px;
        overflow-y: auto;
        overflow-x: hidden;
        flex: 1 1 auto;
        min-height: 0;
    }

    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.12);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-shrink: 0;
        background-color: var(--md-surface);
    }

    .form-row {
        display: flex;
        gap: 16px;
    }

    /* Панель фильтров */
    .filters-panel {
        background-color: var(--md-surface);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: var(--elevation-2);
    }

    .filters-content {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        align-items: center;
    }

    .filter-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .filter-divider {
        width: 1px;
        height: 32px;
        background: rgba(255, 255, 255, 0.12);
        margin: 0 8px;
    }

    .legend-label {
        font-size: 0.875rem;
        color: var(--text-medium-emphasis);
        font-weight: 500;
    }

    .class-filter-btn,
    .type-filter-btn {
        padding: 10px 16px;
        border: 2px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        background-color: var(--md-surface-3);
        color: var(--text-medium-emphasis);
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 600;
        font-family: 'Montserrat', sans-serif;
        transition: all 0.2s var(--transition-standard);
        user-select: none;
    }

    .class-filter-btn:hover,
    .type-filter-btn:hover {
        border-color: var(--md-primary);
        background-color: var(--md-surface-4);
    }

    .class-filter-btn.active,
    .type-filter-btn.active {
        background-color: rgba(187, 134, 252, 0.15);
        border-color: var(--md-primary);
        color: var(--md-primary);
    }

    .search-input {
        padding: 10px 16px;
        border: 2px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        background-color: var(--md-surface-3);
        color: var(--text-high-emphasis);
        font-size: 0.875rem;
        font-family: 'Montserrat', sans-serif;
        min-width: 250px;
        transition: all 0.2s;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--md-primary);
        background-color: var(--md-surface-4);
    }

    .search-input::placeholder {
        color: var(--text-medium-emphasis);
    }
</style>

<script src="/zarplata/assets/js/students.js"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
