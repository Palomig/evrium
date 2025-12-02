<?php
/**
 * Веб-интерфейс для синхронизации количества студентов
 * URL: https://эвриум.рф/zarplata/sync_students_web.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Обработка запроса на синхронизацию
$syncResults = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync') {
    $syncResults = performSync();
}

/**
 * Выполнить синхронизацию
 */
function performSync() {
    $results = [
        'total' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'details' => []
    ];

    try {
        // Получить все активные шаблоны
        $templates = dbQuery(
            "SELECT id, teacher_id, day_of_week, time_start, subject, expected_students, students
             FROM lessons_template
             WHERE active = 1
             ORDER BY day_of_week ASC, time_start ASC",
            []
        );

        $results['total'] = count($templates);

        $days = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

        foreach ($templates as $template) {
            $templateId = $template['id'];
            $expectedStudents = (int)$template['expected_students'];
            $studentsJson = $template['students'];

            // Парсим JSON
            $students = [];
            if ($studentsJson) {
                $studentsData = json_decode($studentsJson, true);
                if (is_array($studentsData)) {
                    $students = $studentsData;
                }
            }

            $realCount = count($students);
            $dayName = $days[$template['day_of_week']] ?? '?';
            $timeStart = substr($template['time_start'], 0, 5);
            $subject = $template['subject'] ?: '(без предмета)';

            $detail = [
                'id' => $templateId,
                'day' => $dayName,
                'time' => $timeStart,
                'subject' => $subject,
                'expected' => $expectedStudents,
                'real' => $realCount,
                'students' => $students,
                'updated' => false,
                'error' => null
            ];

            // Если количество не совпадает - обновляем
            if ($expectedStudents !== $realCount) {
                try {
                    $result = dbExecute(
                        "UPDATE lessons_template SET expected_students = ? WHERE id = ?",
                        [$realCount, $templateId]
                    );

                    if ($result !== false) {
                        $detail['updated'] = true;
                        $results['updated']++;
                    } else {
                        $detail['error'] = 'Не удалось обновить запись';
                        $results['errors']++;
                    }
                } catch (Exception $e) {
                    $detail['error'] = $e->getMessage();
                    $results['errors']++;
                }
            } else {
                $results['skipped']++;
            }

            $results['details'][] = $detail;
        }

        // Логируем в аудит
        logAudit(
            'students_sync',
            'template',
            null,
            null,
            [
                'total' => $results['total'],
                'updated' => $results['updated'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors']
            ],
            "Синхронизация количества студентов: обновлено {$results['updated']} из {$results['total']}"
        );

    } catch (Exception $e) {
        $results['error'] = $e->getMessage();
    }

    return $results;
}

// Page settings
define('PAGE_TITLE', 'Синхронизация студентов');
define('PAGE_SUBTITLE', 'Обновление количества студентов в шаблонах расписания');
define('ACTIVE_PAGE', 'settings');

require_once __DIR__ . '/templates/header.php';
?>

<style>
    .sync-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .info-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }

    .info-card h3 {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 12px 0;
    }

    .info-card p {
        color: var(--text-secondary);
        line-height: 1.6;
        margin: 8px 0;
    }

    .warning-box {
        background: rgba(251, 191, 36, 0.1);
        border: 1px solid rgba(251, 191, 36, 0.3);
        border-radius: 10px;
        padding: 16px;
        margin-top: 16px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .warning-box .material-icons {
        color: var(--status-amber);
        font-size: 24px;
    }

    .warning-box-content {
        flex: 1;
    }

    .warning-box-content strong {
        color: var(--status-amber);
        display: block;
        margin-bottom: 4px;
    }

    .sync-button {
        background: var(--accent);
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.2s;
    }

    .sync-button:hover {
        background: var(--accent-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);
    }

    .sync-button:disabled {
        background: var(--bg-elevated);
        color: var(--text-disabled);
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .sync-button .material-icons {
        font-size: 20px;
    }

    .results-container {
        margin-top: 32px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: var(--bg-elevated);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 20px;
        text-align: center;
    }

    .stat-value {
        font-family: 'JetBrains Mono', monospace;
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .stat-card.total .stat-value { color: var(--text-primary); }
    .stat-card.updated .stat-value { color: var(--status-green); }
    .stat-card.skipped .stat-value { color: var(--status-blue); }
    .stat-card.errors .stat-value { color: var(--status-red); }

    .stat-label {
        font-size: 13px;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .details-table {
        background: var(--bg-elevated);
        border-radius: 10px;
        overflow: hidden;
        margin-top: 20px;
    }

    .table-header {
        display: grid;
        grid-template-columns: 60px 80px 80px 1fr 100px 100px 120px;
        gap: 12px;
        padding: 14px 20px;
        background: var(--bg-dark);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
    }

    .table-row {
        display: grid;
        grid-template-columns: 60px 80px 80px 1fr 100px 100px 120px;
        gap: 12px;
        padding: 14px 20px;
        border-bottom: 1px solid var(--border);
        align-items: center;
        font-size: 14px;
    }

    .table-row:last-child {
        border-bottom: none;
    }

    .table-row.updated {
        background: rgba(16, 185, 129, 0.05);
    }

    .table-row.error {
        background: rgba(239, 68, 68, 0.05);
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .status-badge.success {
        background: var(--status-green-dim);
        color: var(--status-green);
    }

    .status-badge.skipped {
        background: var(--status-blue-dim);
        color: var(--status-blue);
    }

    .status-badge.error {
        background: var(--status-red-dim);
        color: var(--status-red);
    }

    .status-badge .material-icons {
        font-size: 14px;
    }

    .student-list {
        font-size: 12px;
        color: var(--text-muted);
    }

    .count-change {
        font-family: 'JetBrains Mono', monospace;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .count-change.changed {
        color: var(--status-green);
        font-weight: 600;
    }
</style>

<div class="sync-container">
    <div class="info-card">
        <h3>О синхронизации</h3>
        <p>
            Эта утилита обновляет поле <code>expected_students</code> в шаблонах расписания на основе
            реального количества студентов в JSON-массиве <code>students</code>.
        </p>
        <p>
            Это необходимо для корректного расчёта выплат на странице "Выплаты", особенно для
            запланированных (ещё не проведённых) уроков.
        </p>

        <div class="warning-box">
            <span class="material-icons">info</span>
            <div class="warning-box-content">
                <strong>Важно:</strong>
                <p style="margin: 4px 0 0 0; color: var(--text-secondary);">
                    Синхронизация изменит данные в базе данных. Рекомендуется запускать её после
                    массового редактирования списков студентов или перед формированием отчётов по выплатам.
                </p>
            </div>
        </div>
    </div>

    <form method="post" id="syncForm">
        <input type="hidden" name="action" value="sync">
        <button type="submit" class="sync-button" id="syncButton">
            <span class="material-icons">sync</span>
            Запустить синхронизацию
        </button>
    </form>

    <?php if ($syncResults): ?>
        <div class="results-container">
            <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 20px;">Результаты синхронизации</h2>

            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-value"><?= $syncResults['total'] ?></div>
                    <div class="stat-label">Всего шаблонов</div>
                </div>
                <div class="stat-card updated">
                    <div class="stat-value"><?= $syncResults['updated'] ?></div>
                    <div class="stat-label">Обновлено</div>
                </div>
                <div class="stat-card skipped">
                    <div class="stat-value"><?= $syncResults['skipped'] ?></div>
                    <div class="stat-label">Без изменений</div>
                </div>
                <div class="stat-card errors">
                    <div class="stat-value"><?= $syncResults['errors'] ?></div>
                    <div class="stat-label">Ошибок</div>
                </div>
            </div>

            <?php if (!empty($syncResults['details'])): ?>
                <div class="details-table">
                    <div class="table-header">
                        <div>ID</div>
                        <div>День</div>
                        <div>Время</div>
                        <div>Предмет</div>
                        <div>Было</div>
                        <div>Стало</div>
                        <div>Статус</div>
                    </div>

                    <?php foreach ($syncResults['details'] as $detail): ?>
                        <div class="table-row <?= $detail['updated'] ? 'updated' : ($detail['error'] ? 'error' : '') ?>">
                            <div><?= $detail['id'] ?></div>
                            <div><?= e($detail['day']) ?></div>
                            <div style="font-family: 'JetBrains Mono', monospace;"><?= e($detail['time']) ?></div>
                            <div>
                                <?= e($detail['subject']) ?>
                                <?php if (!empty($detail['students'])): ?>
                                    <div class="student-list" title="<?= e(implode(', ', $detail['students'])) ?>">
                                        <?= count($detail['students']) ?> студ:
                                        <?= e(implode(', ', array_slice($detail['students'], 0, 2))) ?>
                                        <?php if (count($detail['students']) > 2): ?>
                                            +<?= count($detail['students']) - 2 ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="count-change"><?= $detail['expected'] ?></div>
                            <div class="count-change <?= $detail['expected'] !== $detail['real'] ? 'changed' : '' ?>">
                                <?= $detail['real'] ?>
                            </div>
                            <div>
                                <?php if ($detail['error']): ?>
                                    <span class="status-badge error" title="<?= e($detail['error']) ?>">
                                        <span class="material-icons">error</span>
                                        Ошибка
                                    </span>
                                <?php elseif ($detail['updated']): ?>
                                    <span class="status-badge success">
                                        <span class="material-icons">check_circle</span>
                                        Обновлено
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge skipped">
                                        <span class="material-icons">remove_circle</span>
                                        Пропущено
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Показываем индикатор загрузки при отправке формы
    document.getElementById('syncForm').addEventListener('submit', function() {
        const button = document.getElementById('syncButton');
        button.disabled = true;
        button.innerHTML = '<span class="material-icons" style="animation: spin 1s linear infinite;">sync</span> Синхронизация...';
    });
</script>

<style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    code {
        background: rgba(255, 255, 255, 0.1);
        padding: 2px 6px;
        border-radius: 4px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 13px;
        color: var(--accent);
    }
</style>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
