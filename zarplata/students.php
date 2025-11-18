<?php
/**
 * Страница управления учениками
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить всех преподавателей
$teachers = dbQuery(
    "SELECT id, name FROM teachers WHERE active = 1 ORDER BY name ASC",
    []
);

// Получить всех учеников с именами преподавателей
$students = dbQuery(
    "SELECT s.*, t.name as teacher_name
     FROM students s
     LEFT JOIN teachers t ON s.teacher_id = t.id
     ORDER BY s.active DESC, s.class ASC, s.name ASC",
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
            <?php for ($i = 2; $i <= 11; $i++): ?>
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
                    <th>Тип</th>
                    <th>Расписание</th>
                    <th>Цена</th>
                    <th>Контакты</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <?php
                    $schedule = null;
                    if (isset($student['schedule']) && $student['schedule']) {
                        $schedule = json_decode($student['schedule'], true);
                    }
                    ?>
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
                                    Соло
                                </span>
                            <?php else: ?>
                                <span class="badge badge-success">
                                    <span class="material-icons" style="font-size: 14px;">group</span>
                                    Группа
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($schedule && !empty($schedule)): ?>
                                <?php
                                $days = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                                $scheduleItems = [];
                                foreach ($schedule as $day => $time) {
                                    $dayName = $days[$day] ?? '';
                                    $scheduleItems[] = "$dayName $time";
                                }
                                echo e(implode(', ', $scheduleItems));
                                ?>
                            <?php else: ?>
                                <span style="color: var(--text-medium-emphasis);">Не указано</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $lessonType = $student['lesson_type'] ?? 'group';
                            if ($lessonType === 'group') {
                                $price = $student['price_group'] ?? 5000;
                                $paymentType = $student['payment_type_group'] ?? 'monthly';
                            } else {
                                $price = $student['price_individual'] ?? 1500;
                                $paymentType = $student['payment_type_individual'] ?? 'per_lesson';
                            }
                            $paymentLabel = $paymentType === 'monthly' ? '/мес' : '/урок';
                            ?>
                            <strong><?= formatMoney($price) ?><?= $paymentLabel ?></strong>
                        </td>
                        <td>
                            <!-- Контакты ученика -->
                            <div style="margin-bottom: 8px;">
                                <small style="color: var(--text-medium-emphasis); display: block; margin-bottom: 4px;">Ученик:</small>
                                <?php if ($student['student_telegram']): ?>
                                    <a href="https://t.me/<?= e($student['student_telegram']) ?>" target="_blank" class="messenger-btn messenger-telegram" title="Telegram ученика">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161l-1.84 8.673c-.139.623-.5.775-.99.483l-2.738-2.018-1.32 1.27c-.146.146-.27.27-.552.27l.197-2.8 5.094-4.602c.222-.197-.048-.307-.344-.11l-6.3 3.965-2.71-.85c-.59-.185-.602-.59.124-.874l10.6-4.086c.49-.183.92.11.76.874z"/>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                <?php if ($student['student_whatsapp']): ?>
                                    <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $student['student_whatsapp'])) ?>" target="_blank" class="messenger-btn messenger-whatsapp" title="WhatsApp ученика">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                <?php if (!$student['student_telegram'] && !$student['student_whatsapp']): ?>
                                    <span style="color: var(--text-medium-emphasis);">—</span>
                                <?php endif; ?>
                            </div>

                            <!-- Контакты родителя -->
                            <div>
                                <small style="color: var(--text-medium-emphasis); display: block; margin-bottom: 4px;">Родитель:</small>
                                <?php if ($student['parent_telegram']): ?>
                                    <a href="https://t.me/<?= e($student['parent_telegram']) ?>" target="_blank" class="messenger-btn messenger-telegram" title="Telegram родителя">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161l-1.84 8.673c-.139.623-.5.775-.99.483l-2.738-2.018-1.32 1.27c-.146.146-.27.27-.552.27l.197-2.8 5.094-4.602c.222-.197-.048-.307-.344-.11l-6.3 3.965-2.71-.85c-.59-.185-.602-.59.124-.874l10.6-4.086c.49-.183.92.11.76.874z"/>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                <?php if ($student['parent_whatsapp']): ?>
                                    <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $student['parent_whatsapp'])) ?>" target="_blank" class="messenger-btn messenger-whatsapp" title="WhatsApp родителя">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                                <?php if (!$student['parent_telegram'] && !$student['parent_whatsapp']): ?>
                                    <span style="color: var(--text-medium-emphasis);">—</span>
                                <?php endif; ?>
                            </div>
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
                <!-- ФИО -->
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

                <!-- Преподаватель -->
                <div class="form-group">
                    <label class="form-label" for="student-teacher">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">person_pin</span>
                        Преподаватель *
                    </label>
                    <select class="form-control" id="student-teacher" name="teacher_id" required>
                        <option value="">Выберите преподавателя</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>"><?= e($teacher['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Класс -->
                <div class="form-group">
                    <label class="form-label" for="student-class">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">school</span>
                        Класс
                    </label>
                    <select class="form-control" id="student-class" name="class">
                        <option value="">Не указан</option>
                        <?php for ($i = 2; $i <= 11; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?> класс</option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Тир (уровень ученика) -->
                <div class="form-group">
                    <label class="form-label">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">star</span>
                        Уровень (Тир)
                    </label>
                    <div class="tier-group">
                        <button type="button" class="btn-tier" data-tier="S" onclick="selectTier(this)">S</button>
                        <button type="button" class="btn-tier" data-tier="A" onclick="selectTier(this)">A</button>
                        <button type="button" class="btn-tier" data-tier="B" onclick="selectTier(this)">B</button>
                        <button type="button" class="btn-tier active" data-tier="C" onclick="selectTier(this)">C</button>
                        <button type="button" class="btn-tier" data-tier="D" onclick="selectTier(this)">D</button>
                    </div>
                    <input type="hidden" id="student-tier" name="tier" value="C" required>
                </div>

                <!-- Тип занятий (кнопки) -->
                <div class="form-group">
                    <label class="form-label">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">groups</span>
                        Тип занятий *
                    </label>
                    <div class="button-group">
                        <button type="button" class="btn-toggle active" data-value="group" onclick="selectLessonType(this)">
                            <span class="material-icons" style="font-size: 18px;">group</span>
                            Группа
                        </button>
                        <button type="button" class="btn-toggle" data-value="individual" onclick="selectLessonType(this)">
                            <span class="material-icons" style="font-size: 18px;">person</span>
                            Соло
                        </button>
                    </div>
                    <input type="hidden" id="student-lesson-type" name="lesson_type" value="group" required>
                </div>

                <!-- Цены и тип оплаты для групповых -->
                <div class="form-group price-group" id="price-group-section">
                    <label class="form-label">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">payments</span>
                        Цена групповых занятий *
                    </label>
                    <div class="price-row">
                        <input
                            type="number"
                            class="form-control"
                            id="price-group"
                            name="price_group"
                            placeholder="5000"
                            min="0"
                            step="100"
                            value="5000"
                        >
                        <select class="form-control" id="payment-type-group" name="payment_type_group">
                            <option value="monthly">За месяц</option>
                            <option value="per_lesson">За урок</option>
                        </select>
                    </div>
                </div>

                <!-- Цены и тип оплаты для индивидуальных -->
                <div class="form-group price-group" id="price-individual-section">
                    <label class="form-label">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">payments</span>
                        Цена индивидуальных занятий *
                    </label>
                    <div class="price-row">
                        <input
                            type="number"
                            class="form-control"
                            id="price-individual"
                            name="price_individual"
                            placeholder="1500"
                            min="0"
                            step="100"
                            value="1500"
                        >
                        <select class="form-control" id="payment-type-individual" name="payment_type_individual">
                            <option value="per_lesson">За урок</option>
                            <option value="monthly">За месяц</option>
                        </select>
                    </div>
                </div>

                <!-- Выбор дней недели -->
                <div class="form-group">
                    <label class="form-label">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">calendar_today</span>
                        Дни недели
                    </label>
                    <div class="days-group">
                        <button type="button" class="btn-day" data-day="1" onclick="toggleDay(this)">Пн</button>
                        <button type="button" class="btn-day" data-day="2" onclick="toggleDay(this)">Вт</button>
                        <button type="button" class="btn-day" data-day="3" onclick="toggleDay(this)">Ср</button>
                        <button type="button" class="btn-day" data-day="4" onclick="toggleDay(this)">Чт</button>
                        <button type="button" class="btn-day" data-day="5" onclick="toggleDay(this)">Пт</button>
                        <button type="button" class="btn-day" data-day="6" onclick="toggleDay(this)">Сб</button>
                        <button type="button" class="btn-day" data-day="7" onclick="toggleDay(this)">Вс</button>
                    </div>
                </div>

                <!-- Список выбранных дней с временем -->
                <div id="schedule-list" class="schedule-list">
                    <!-- Динамически добавляются строки расписания -->
                </div>

                <!-- ФИО родителя -->
                <div class="form-group">
                    <label class="form-label" for="student-parent-name">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">person</span>
                        ФИО родителя
                    </label>
                    <input
                        type="text"
                        class="form-control"
                        id="student-parent-name"
                        name="parent_name"
                        placeholder="Иванов Иван Иванович"
                        maxlength="100"
                    >
                </div>

                <!-- Мессенджеры родителя -->
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="parent-telegram">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 4px;">
                                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161l-1.84 8.673c-.139.623-.5.775-.99.483l-2.738-2.018-1.32 1.27c-.146.146-.27.27-.552.27l.197-2.8 5.094-4.602c.222-.197-.048-.307-.344-.11l-6.3 3.965-2.71-.85c-.59-.185-.602-.59.124-.874l10.6-4.086c.49-.183.92.11.76.874z"/>
                            </svg>
                            Telegram родителя
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="parent-telegram"
                            name="parent_telegram"
                            placeholder="username"
                        >
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="parent-whatsapp">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; margin-right: 4px;">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            WhatsApp родителя
                        </label>
                        <input
                            type="tel"
                            class="form-control"
                            id="parent-whatsapp"
                            name="parent_whatsapp"
                            placeholder="+7 (999) 123-45-67"
                        >
                    </div>
                </div>

                <!-- Примечания -->
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
        max-width: 700px;
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

    /* Кнопки-переключатели */
    .button-group {
        display: flex;
        gap: 12px;
    }

    .btn-toggle {
        flex: 1;
        padding: 12px 20px;
        border: 2px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        background-color: var(--md-surface-3);
        color: var(--text-medium-emphasis);
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 600;
        font-family: 'Montserrat', sans-serif;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-toggle:hover {
        border-color: var(--md-primary);
        background-color: var(--md-surface-4);
    }

    .btn-toggle.active {
        background-color: rgba(187, 134, 252, 0.15);
        border-color: var(--md-primary);
        color: var(--md-primary);
    }

    /* Цены */
    .price-row {
        display: flex;
        gap: 12px;
    }

    .price-row input {
        flex: 2;
    }

    .price-row select {
        flex: 1;
    }

    /* Кнопки дней недели */
    .days-group {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-day {
        padding: 10px 16px;
        border: 2px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        background-color: var(--md-surface-3);
        color: var(--text-medium-emphasis);
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 600;
        font-family: 'Montserrat', sans-serif;
        transition: all 0.2s;
        min-width: 50px;
    }

    .btn-day:hover {
        border-color: var(--md-primary);
        background-color: var(--md-surface-4);
    }

    .btn-day.active {
        background-color: rgba(187, 134, 252, 0.15);
        border-color: var(--md-primary);
        color: var(--md-primary);
    }

    /* Кнопки тира */
    .tier-group {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-tier {
        padding: 12px 20px;
        border: 2px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        background-color: var(--md-surface-3);
        color: var(--text-medium-emphasis);
        cursor: pointer;
        font-size: 1rem;
        font-weight: 700;
        font-family: 'Montserrat', sans-serif;
        transition: all 0.2s;
        min-width: 60px;
    }

    .btn-tier:hover {
        border-color: var(--md-primary);
        background-color: var(--md-surface-4);
    }

    .btn-tier.active {
        background-color: rgba(187, 134, 252, 0.15);
        border-color: var(--md-primary);
        color: var(--md-primary);
    }

    /* Список расписания */
    .schedule-list {
        margin-top: 16px;
    }

    .schedule-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background-color: var(--md-surface-3);
        border-radius: 8px;
        margin-bottom: 8px;
    }

    .schedule-item-day {
        font-weight: 600;
        color: var(--md-primary);
        min-width: 120px;
    }

    .schedule-item-time {
        flex: 1;
    }

    .schedule-item-remove {
        background: none;
        border: none;
        color: var(--md-error);
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background-color 0.2s;
    }

    .schedule-item-remove:hover {
        background-color: rgba(207, 102, 121, 0.12);
    }

    /* Кнопки мессенджеров */
    .messenger-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        margin-right: 4px;
        transition: all 0.2s;
        text-decoration: none;
    }

    .messenger-telegram {
        background-color: rgba(0, 136, 204, 0.15);
        color: #0088cc;
    }

    .messenger-telegram:hover {
        background-color: rgba(0, 136, 204, 0.25);
        transform: scale(1.1);
    }

    .messenger-whatsapp {
        background-color: rgba(37, 211, 102, 0.15);
        color: #25d366;
    }

    .messenger-whatsapp:hover {
        background-color: rgba(37, 211, 102, 0.25);
        transform: scale(1.1);
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
