<?php
/**
 * Страница отчётов и аналитики
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Параметры по умолчанию
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

// Получить всех преподавателей для фильтра
$teachers = dbQuery("SELECT id, name FROM teachers WHERE active = 1 ORDER BY name", []);

define('PAGE_TITLE', 'Отчёты и аналитика');
define('PAGE_SUBTITLE', 'Статистика, графики и экспорт данных');
define('ACTIVE_PAGE', 'reports');

require_once __DIR__ . '/templates/header.php';
?>

<!-- Фильтры -->
<div class="card mb-4">
    <div class="card-header">
        <h3 style="margin: 0;">
            <span class="material-icons" style="vertical-align: middle;">filter_list</span>
            Фильтры отчётов
        </h3>
    </div>
    <div class="card-body">
        <form id="filters-form">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Дата с</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $dateFrom ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Дата по</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $dateTo ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Преподаватель</label>
                    <select class="form-control" id="teacher_id" name="teacher_id">
                        <option value="">Все преподаватели</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>"><?= e($teacher['name']) ?></option>
                        <?php endforeach; ?>
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

<!-- Статистика (загружается динамически) -->
<div id="summary-stats"></div>

<!-- Графики -->
<div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 24px;">
    <!-- График по дням -->
    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">
                <span class="material-icons" style="vertical-align: middle;">show_chart</span>
                Уроки и выручка по дням
            </h3>
        </div>
        <div class="card-body">
            <canvas id="daily-chart" height="300"></canvas>
        </div>
    </div>

    <!-- График по преподавателям -->
    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">
                <span class="material-icons" style="vertical-align: middle;">pie_chart</span>
                Распределение по преподавателям
            </h3>
        </div>
        <div class="card-body">
            <canvas id="teacher-chart" height="300"></canvas>
        </div>
    </div>
</div>

<!-- Детальная таблица (для выбранного преподавателя) -->
<div id="teacher-details" style="display: none;"></div>

<!-- Экспорт -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">
            <span class="material-icons" style="vertical-align: middle;">cloud_download</span>
            Экспорт данных
        </h3>
    </div>
    <div class="card-body">
        <p style="color: var(--text-medium-emphasis); margin-bottom: 16px;">
            Выгрузить отчёт за выбранный период
        </p>
        <div style="display: flex; gap: 16px;">
            <button class="btn btn-outline" onclick="exportToExcel()">
                <span class="material-icons" style="margin-right: 8px; font-size: 18px;">table_chart</span>
                Экспорт в CSV
            </button>
        </div>
    </div>
</div>

<!-- Подключаем Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="/zarplata/assets/js/reports.js"></script>

<style>
    .stats-grid {
        display: grid;
        gap: 24px;
        margin-bottom: 32px;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

    .stat-card-icon.secondary {
        background-color: rgba(3, 218, 198, 0.12);
        color: var(--md-secondary);
    }

    .stat-card-icon.success {
        background-color: rgba(76, 175, 80, 0.12);
        color: var(--md-success);
    }

    .stat-card-icon.warning {
        background-color: rgba(255, 152, 0, 0.12);
        color: var(--md-warning);
    }

    .stat-card-icon.info {
        background-color: rgba(33, 150, 243, 0.12);
        color: var(--md-info);
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

    .row {
        margin-left: -12px;
        margin-right: -12px;
    }

    .card {
        margin-bottom: 24px;
    }

    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--md-surface-3);
    }

    .card-body {
        padding: 24px;
    }

    .btn-block {
        width: 100%;
    }
</style>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
