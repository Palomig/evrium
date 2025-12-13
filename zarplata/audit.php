<?php
/**
 * Страница журнала аудита
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

// Автоматический редирект на мобильную версию
require_once __DIR__ . '/mobile/config/mobile_detect.php';
redirectToMobileIfNeeded('audit.php');

requireAuth();
$user = getCurrentUser();

// Фильтры
$action = $_GET['action_filter'] ?? '';
$entityType = $_GET['entity_type'] ?? '';
$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 50;
$offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT) ?: 0;

// Построение запроса
$query = "SELECT al.*, u.name as user_name
          FROM audit_log al
          LEFT JOIN users u ON al.user_id = u.id
          WHERE DATE(al.created_at) BETWEEN ? AND ?";

$params = [$dateFrom, $dateTo];

if ($action) {
    $query .= " AND al.action = ?";
    $params[] = $action;
}

if ($entityType) {
    $query .= " AND al.entity_type = ?";
    $params[] = $entityType;
}

if ($userId) {
    $query .= " AND al.user_id = ?";
    $params[] = $userId;
}

$query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Получить логи
$logs = dbQuery($query, $params);

// Получить пользователей для фильтра
$users = dbQuery("SELECT id, name FROM users WHERE active = 1 ORDER BY name", []);

// Статистика
$stats = dbQueryOne(
    "SELECT
        COUNT(*) as total_actions,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT DATE(created_at)) as active_days
     FROM audit_log
     WHERE DATE(created_at) BETWEEN ? AND ?",
    [$dateFrom, $dateTo]
);

// Маппинги для отображения
$actionLabels = [
    'payment_created' => 'Создание выплаты',
    'payment_updated' => 'Изменение выплаты',
    'payment_deleted' => 'Удаление выплаты',
    'payment_approved' => 'Одобрение выплаты',
    'Одобрение' => 'Одобрение выплаты',
    'Изменение' => 'Редактирование',
    'attendance_query_sent' => 'Отправка опроса посещаемости',
    'attendance_marked' => 'Отметка посещаемости',
    'lesson_created' => 'Создание урока',
    'lesson_deleted' => 'Удаление урока'
];

$entityLabels = [
    'payment' => 'Выплата',
    'lesson' => 'Урок',
    'lesson_template' => 'Шаблон урока',
    'lesson_schedule' => 'Урок (расписание)',
    'teacher' => 'Преподаватель',
    'student' => 'Ученик',
    'formula' => 'Формула',
    'template' => 'Шаблон',
    'settings' => 'Настройки',
    'user' => 'Пользователь'
];

$fieldNames = [
    'amount' => 'Сумма',
    'status' => 'Статус',
    'payment_status' => 'Статус выплаты',
    'payment_type' => 'Тип выплаты',
    'notes' => 'Примечание',
    'description' => 'Описание',
    'name' => 'Имя',
    'teacher_id' => 'Преподаватель',
    'student_id' => 'Ученик',
    'lesson_date' => 'Дата урока',
    'time_start' => 'Начало',
    'time_end' => 'Окончание',
    'subject' => 'Предмет',
    'lesson_type' => 'Тип урока',
    'actual_students' => 'Присутствовало',
    'expected_students' => 'Ожидалось',
    'formula_id' => 'Формула',
    'attended' => 'Присутствовало',
    'expected' => 'Ожидалось',
    'payment_id' => 'ID выплаты',
    'telegram_id' => 'Telegram ID',
    'time' => 'Время',
    'student_names' => 'Ученики'
];

$valueTranslations = [
    'pending' => 'Ожидает',
    'approved' => 'Одобрено',
    'paid' => 'Выплачено',
    'cancelled' => 'Отменено',
    'lesson' => 'Урок',
    'bonus' => 'Бонус',
    'penalty' => 'Штраф',
    'adjustment' => 'Корректировка',
    'group' => 'Групповой',
    'individual' => 'Индивидуальный',
    'scheduled' => 'Запланирован',
    'completed' => 'Завершён'
];

define('PAGE_TITLE', 'Журнал аудита');
define('PAGE_SUBTITLE', 'История изменений и действий');
define('ACTIVE_PAGE', 'audit');

require_once __DIR__ . '/templates/header.php';
?>

<!-- Статистика -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value"><?= $stats['total_actions'] ?? 0 ?></div>
                <div class="stat-card-label">Всего действий</div>
            </div>
            <div class="stat-card-icon primary">
                <span class="material-icons">history</span>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value"><?= $stats['unique_users'] ?? 0 ?></div>
                <div class="stat-card-label">Активных пользователей</div>
            </div>
            <div class="stat-card-icon info">
                <span class="material-icons">people</span>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div>
                <div class="stat-card-value"><?= $stats['active_days'] ?? 0 ?></div>
                <div class="stat-card-label">Дней с активностью</div>
            </div>
            <div class="stat-card-icon success">
                <span class="material-icons">event</span>
            </div>
        </div>
    </div>
</div>

<style>
    .stats-grid {
        display: grid;
        gap: 24px;
        margin-bottom: 32px;
    }

    .stat-card {
        padding: 24px;
        background-color: var(--md-surface);
        border-radius: 12px;
        box-shadow: var(--elevation-2);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--elevation-3);
    }

    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .stat-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stat-card-icon.primary {
        background-color: rgba(187, 134, 252, 0.12);
        color: var(--md-primary);
    }

    .stat-card-icon.info {
        background-color: rgba(33, 150, 243, 0.12);
        color: var(--md-info);
    }

    .stat-card-icon.success {
        background-color: rgba(76, 175, 80, 0.12);
        color: var(--md-success);
    }

    .stat-card-value {
        font-size: 1.5rem;
        font-weight: 300;
        margin-bottom: 4px;
    }

    .stat-card-label {
        font-size: 0.875rem;
        color: var(--text-medium-emphasis);
    }

    /* Раскрывающиеся записи аудита */
    .audit-entries {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .audit-entry {
        background: var(--md-surface);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: var(--elevation-1);
    }

    .audit-header {
        display: grid;
        grid-template-columns: 150px 140px 1fr 120px 50px;
        gap: 16px;
        padding: 16px 20px;
        cursor: pointer;
        transition: background 0.2s;
        align-items: center;
    }

    .audit-header:hover {
        background: var(--md-surface-2);
    }

    .audit-header.expanded {
        background: var(--md-surface-2);
        border-bottom: 1px solid var(--md-outline);
    }

    .audit-header .chevron {
        transition: transform 0.3s ease;
        color: var(--text-medium-emphasis);
    }

    .audit-header.expanded .chevron {
        transform: rotate(90deg);
    }

    .audit-date {
        font-size: 13px;
        color: var(--text-medium-emphasis);
        font-family: 'JetBrains Mono', monospace;
    }

    .audit-action {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .audit-action-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .audit-action-icon.create { background: rgba(16, 185, 129, 0.15); color: #10b981; }
    .audit-action-icon.update { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
    .audit-action-icon.delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    .audit-action-icon.approve { background: rgba(20, 184, 166, 0.15); color: #14b8a6; }
    .audit-action-icon.info { background: rgba(99, 102, 241, 0.15); color: #6366f1; }

    .audit-action-text {
        font-size: 13px;
        font-weight: 500;
        color: var(--text-high-emphasis);
    }

    .audit-description {
        font-size: 13px;
        color: var(--text-medium-emphasis);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .audit-user {
        font-size: 13px;
        color: var(--text-medium-emphasis);
    }

    .audit-details {
        display: none;
        padding: 20px;
        background: var(--md-surface-1);
        border-top: 1px solid var(--md-outline);
    }

    .audit-details.expanded {
        display: block;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }

    .detail-item {
        padding: 12px;
        background: var(--md-surface);
        border-radius: 8px;
    }

    .detail-label {
        font-size: 11px;
        color: var(--text-medium-emphasis);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .detail-value {
        font-size: 14px;
        color: var(--text-high-emphasis);
        font-weight: 500;
    }

    .changes-section {
        margin-top: 16px;
    }

    .changes-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-medium-emphasis);
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .change-row {
        display: grid;
        grid-template-columns: 140px 1fr 1fr;
        gap: 12px;
        padding: 10px 12px;
        background: var(--md-surface);
        border-radius: 8px;
        margin-bottom: 8px;
        font-size: 13px;
    }

    .change-field {
        color: var(--text-medium-emphasis);
        font-weight: 500;
    }

    .change-old {
        color: #ef4444;
    }

    .change-new {
        color: #10b981;
    }

    .data-block {
        padding: 16px;
        background: var(--md-surface);
        border-radius: 8px;
        border-left: 3px solid;
    }

    .data-block.created {
        border-color: #10b981;
        background: rgba(16, 185, 129, 0.05);
    }

    .data-block.deleted {
        border-color: #ef4444;
        background: rgba(239, 68, 68, 0.05);
    }

    .data-list {
        display: grid;
        gap: 8px;
    }

    .data-item {
        display: grid;
        grid-template-columns: 140px 1fr;
        gap: 12px;
        font-size: 13px;
    }

    .data-item-label {
        color: var(--text-medium-emphasis);
    }

    .data-item-value {
        color: var(--text-high-emphasis);
    }

    .no-data {
        padding: 20px;
        text-align: center;
        color: var(--text-medium-emphasis);
        font-size: 13px;
        background: var(--md-surface);
        border-radius: 8px;
    }
</style>

<!-- Фильтры -->
<div class="card mb-4">
    <div class="card-header">
        <h3 style="margin: 0;">
            <span class="material-icons" style="vertical-align: middle;">filter_list</span>
            Фильтры
        </h3>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Дата с</label>
                    <input type="date" class="form-control" name="date_from" value="<?= $dateFrom ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Дата по</label>
                    <input type="date" class="form-control" name="date_to" value="<?= $dateTo ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Пользователь</label>
                    <select class="form-control" name="user_id">
                        <option value="">Все пользователи</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $userId == $u['id'] ? 'selected' : '' ?>>
                                <?= e($u['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Тип сущности</label>
                    <select class="form-control" name="entity_type">
                        <option value="">Все типы</option>
                        <option value="teacher" <?= $entityType === 'teacher' ? 'selected' : '' ?>>Преподаватели</option>
                        <option value="lesson" <?= $entityType === 'lesson' ? 'selected' : '' ?>>Уроки</option>
                        <option value="payment" <?= $entityType === 'payment' ? 'selected' : '' ?>>Выплаты</option>
                        <option value="formula" <?= $entityType === 'formula' ? 'selected' : '' ?>>Формулы</option>
                        <option value="template" <?= $entityType === 'template' ? 'selected' : '' ?>>Шаблоны</option>
                        <option value="settings" <?= $entityType === 'settings' ? 'selected' : '' ?>>Настройки</option>
                        <option value="user" <?= $entityType === 'user' ? 'selected' : '' ?>>Пользователи</option>
                    </select>
                </div>

                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary btn-block">
                        <span class="material-icons" style="margin-right: 8px; font-size: 18px;">search</span>
                        Применить
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Журнал -->
<div class="card">
    <div class="card-header">
        <h2 style="margin: 0; font-size: 18px; font-weight: 600;">
            <span class="material-icons" style="vertical-align: middle; margin-right: 8px;">history</span>
            Журнал действий
        </h2>
    </div>
    <div class="card-body" style="padding: 16px;">
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <div class="material-icons">assignment</div>
                <p>Нет записей для отображения</p>
            </div>
        <?php else: ?>
            <div class="audit-entries">
                <?php foreach ($logs as $log):
                    // Определяем иконку и класс по типу действия
                    $actionType = $log['action'] ?? $log['action_type'] ?? '';
                    $iconClass = 'info';
                    $icon = 'info';

                    if (strpos($actionType, 'create') !== false || strpos($actionType, 'created') !== false) {
                        $iconClass = 'create';
                        $icon = 'add_circle';
                    } elseif (strpos($actionType, 'update') !== false || strpos($actionType, 'Изменение') !== false) {
                        $iconClass = 'update';
                        $icon = 'edit';
                    } elseif (strpos($actionType, 'delete') !== false) {
                        $iconClass = 'delete';
                        $icon = 'delete';
                    } elseif (strpos($actionType, 'approv') !== false || strpos($actionType, 'Одобрение') !== false) {
                        $iconClass = 'approve';
                        $icon = 'check_circle';
                    } elseif (strpos($actionType, 'attendance') !== false) {
                        $iconClass = 'approve';
                        $icon = 'how_to_reg';
                    }

                    $actionLabel = $actionLabels[$actionType] ?? $actionType;
                    $entityLabel = $entityLabels[$log['entity_type']] ?? $log['entity_type'];

                    // Парсим данные
                    $oldValue = null;
                    $newValue = null;
                    if ($log['old_value']) {
                        $oldValue = json_decode($log['old_value'], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $oldValue = $log['old_value'];
                        }
                    }
                    if ($log['new_value']) {
                        $newValue = json_decode($log['new_value'], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $newValue = $log['new_value'];
                        }
                    }
                ?>
                    <div class="audit-entry">
                        <div class="audit-header" onclick="toggleAuditEntry(this)">
                            <div class="audit-date">
                                <?= date('d.m.Y H:i', strtotime($log['created_at'])) ?>
                            </div>
                            <div class="audit-action">
                                <div class="audit-action-icon <?= $iconClass ?>">
                                    <span class="material-icons"><?= $icon ?></span>
                                </div>
                                <span class="audit-action-text"><?= e($actionLabel) ?></span>
                            </div>
                            <div class="audit-description">
                                <?= e($log['description'] ?: $entityLabel . ($log['entity_id'] ? ' #' . $log['entity_id'] : '')) ?>
                            </div>
                            <div class="audit-user">
                                <?= e($log['user_name'] ?: 'Система') ?>
                            </div>
                            <div style="text-align: right;">
                                <span class="material-icons chevron">chevron_right</span>
                            </div>
                        </div>

                        <div class="audit-details">
                            <!-- Основная информация -->
                            <div class="details-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Дата и время</div>
                                    <div class="detail-value"><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Действие</div>
                                    <div class="detail-value"><?= e($actionLabel) ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Тип сущности</div>
                                    <div class="detail-value"><?= e($entityLabel) ?><?= $log['entity_id'] ? ' #' . $log['entity_id'] : '' ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Пользователь</div>
                                    <div class="detail-value"><?= e($log['user_name'] ?: 'Система') ?></div>
                                </div>
                                <?php if ($log['ip_address']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">IP адрес</div>
                                    <div class="detail-value"><?= e($log['ip_address']) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($log['description']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Описание</div>
                                    <div class="detail-value"><?= e($log['description']) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($log['notes']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Примечание</div>
                                    <div class="detail-value"><?= e($log['notes']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Данные изменений -->
                            <?php if ($oldValue || $newValue): ?>
                                <div class="changes-section">
                                    <?php if ($oldValue && $newValue && is_array($oldValue) && is_array($newValue)): ?>
                                        <!-- Изменение: показываем было → стало -->
                                        <div class="changes-title">Изменения</div>
                                        <div class="change-row" style="font-weight: 600; background: var(--md-surface-2);">
                                            <div>Поле</div>
                                            <div style="color: #ef4444;">Было</div>
                                            <div style="color: #10b981;">Стало</div>
                                        </div>
                                        <?php
                                        $allKeys = array_unique(array_merge(array_keys($oldValue), array_keys($newValue)));
                                        foreach ($allKeys as $key):
                                            $oldVal = $oldValue[$key] ?? null;
                                            $newVal = $newValue[$key] ?? null;
                                            if (json_encode($oldVal) !== json_encode($newVal)):
                                                $fieldLabel = $fieldNames[$key] ?? $key;
                                                $oldDisplay = formatAuditValue($oldVal, $valueTranslations);
                                                $newDisplay = formatAuditValue($newVal, $valueTranslations);
                                        ?>
                                            <div class="change-row">
                                                <div class="change-field"><?= e($fieldLabel) ?></div>
                                                <div class="change-old"><?= $oldDisplay ?></div>
                                                <div class="change-new"><?= $newDisplay ?></div>
                                            </div>
                                        <?php
                                            endif;
                                        endforeach;
                                        ?>

                                    <?php elseif ($newValue && !$oldValue): ?>
                                        <!-- Создание: показываем созданные данные -->
                                        <div class="changes-title">Созданные данные</div>
                                        <div class="data-block created">
                                            <div class="data-list">
                                                <?php if (is_array($newValue)):
                                                    foreach ($newValue as $key => $val):
                                                        $fieldLabel = $fieldNames[$key] ?? $key;
                                                        $valDisplay = formatAuditValue($val, $valueTranslations);
                                                ?>
                                                    <div class="data-item">
                                                        <div class="data-item-label"><?= e($fieldLabel) ?></div>
                                                        <div class="data-item-value"><?= $valDisplay ?></div>
                                                    </div>
                                                <?php endforeach;
                                                else: ?>
                                                    <div class="data-item-value"><?= e($newValue) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                    <?php elseif ($oldValue && !$newValue): ?>
                                        <!-- Удаление: показываем удалённые данные -->
                                        <div class="changes-title">Удалённые данные</div>
                                        <div class="data-block deleted">
                                            <div class="data-list">
                                                <?php if (is_array($oldValue)):
                                                    foreach ($oldValue as $key => $val):
                                                        $fieldLabel = $fieldNames[$key] ?? $key;
                                                        $valDisplay = formatAuditValue($val, $valueTranslations);
                                                ?>
                                                    <div class="data-item">
                                                        <div class="data-item-label"><?= e($fieldLabel) ?></div>
                                                        <div class="data-item-value"><?= $valDisplay ?></div>
                                                    </div>
                                                <?php endforeach;
                                                else: ?>
                                                    <div class="data-item-value"><?= e($oldValue) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-data">Подробная информация не сохранена</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleAuditEntry(header) {
    const entry = header.parentElement;
    const details = entry.querySelector('.audit-details');
    const isExpanded = header.classList.contains('expanded');

    // Закрываем другие открытые записи
    document.querySelectorAll('.audit-header.expanded').forEach(el => {
        if (el !== header) {
            el.classList.remove('expanded');
            el.parentElement.querySelector('.audit-details').classList.remove('expanded');
        }
    });

    // Переключаем текущую
    if (isExpanded) {
        header.classList.remove('expanded');
        details.classList.remove('expanded');
    } else {
        header.classList.add('expanded');
        details.classList.add('expanded');
    }
}
</script>

<?php
// Хелпер для форматирования значений
function formatAuditValue($value, $translations) {
    if ($value === null || $value === '') {
        return '<span style="color: var(--text-disabled);">—</span>';
    }
    if (is_bool($value)) {
        return $value ? 'Да' : 'Нет';
    }
    if (is_array($value)) {
        if (empty($value)) {
            return '<span style="color: var(--text-disabled);">(пусто)</span>';
        }
        return e(implode(', ', $value));
    }
    $strValue = (string)$value;
    if (isset($translations[$strValue])) {
        return e($translations[$strValue]);
    }
    // Форматируем суммы
    if (is_numeric($strValue) && (int)$strValue > 100) {
        return number_format((int)$strValue, 0, ',', ' ') . ' ₽';
    }
    return e($strValue);
}

require_once __DIR__ . '/templates/footer.php';
?>
