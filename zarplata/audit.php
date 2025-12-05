<?php
/**
 * Страница журнала аудита
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

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
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Журнал действий</h2>
    </div>

    <?php if (empty($logs)): ?>
        <div class="empty-state">
            <div class="material-icons">assignment</div>
            <p>Нет записей для отображения</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Дата и время</th>
                    <th>Пользователь</th>
                    <th>Действие</th>
                    <th>Сущность</th>
                    <th>ID</th>
                    <th>Описание</th>
                    <th>IP</th>
                    <th>Детали</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <strong><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></strong>
                        </td>
                        <td><?= e($log['user_name'] ?: 'Система') ?></td>
                        <td>
                            <?php
                                $actionBadge = getActionBadge($log['action']);
                            ?>
                            <span class="badge badge-<?= $actionBadge['class'] ?>">
                                <span class="material-icons" style="font-size: 14px;"><?= $actionBadge['icon'] ?></span>
                                <?= $actionBadge['text'] ?>
                            </span>
                        </td>
                        <td><?= ucfirst($log['entity_type']) ?></td>
                        <td><?= $log['entity_id'] ?? '—' ?></td>
                        <td><?= e($log['description'] ?: '—') ?></td>
                        <td><?= $log['ip_address'] ?? '—' ?></td>
                        <td>
                            <?php if ($log['old_value'] || $log['new_value']): ?>
                                <button class="btn btn-text" onclick="viewAuditDetails(<?= $log['id'] ?>)">
                                    <span class="material-icons" style="font-size: 18px;">visibility</span>
                                </button>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Модальное окно деталей -->
<div id="audit-details-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Детали изменения</h3>
            <button class="modal-close" onclick="closeAuditDetails()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div id="audit-details-content" style="padding: 24px; max-height: 400px; overflow-y: auto;">
            <p style="text-align: center;">Загрузка...</p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-primary" onclick="closeAuditDetails()">Закрыть</button>
        </div>
    </div>
</div>

<script src="/zarplata/assets/js/audit.js"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
