<?php
/**
 * Страница формул оплаты
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить все формулы
$formulas = dbQuery(
    "SELECT * FROM payment_formulas ORDER BY active DESC, name ASC",
    []
);

define('PAGE_TITLE', 'Формулы оплаты');
define('PAGE_SUBTITLE', 'Управление формулами расчёта зарплаты');
define('ACTIVE_PAGE', 'formulas');

require_once __DIR__ . '/templates/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Все формулы</h2>
        <button class="btn btn-primary" onclick="alert('Функция создания формулы будет реализована позже')">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Создать формулу
        </button>
    </div>

    <?php if (empty($formulas)): ?>
        <div class="empty-state">
            <div class="material-icons">functions</div>
            <p>Нет формул оплаты</p>
            <p style="margin-top: 8px;">
                <button class="btn btn-primary" onclick="alert('Функция создания формулы будет реализована позже')">
                    Создать первую формулу
                </button>
            </p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Тип</th>
                    <th>Параметры</th>
                    <th>Пример расчёта</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($formulas as $formula): ?>
                    <tr>
                        <td><?= $formula['id'] ?></td>
                        <td>
                            <strong><?= e($formula['name']) ?></strong>
                            <?php if ($formula['description']): ?>
                                <br>
                                <small style="color: var(--text-medium-emphasis);">
                                    <?= e(truncate($formula['description'], 60)) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $typeLabels = [
                                'min_plus_per' => 'Минимум + Доплата',
                                'fixed' => 'Фиксированная',
                                'expression' => 'Пользовательская'
                            ];
                            $typeColors = [
                                'min_plus_per' => 'info',
                                'fixed' => 'success',
                                'expression' => 'warning'
                            ];
                            ?>
                            <span class="badge badge-<?= $typeColors[$formula['type']] ?? 'info' ?>">
                                <?= $typeLabels[$formula['type']] ?? $formula['type'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($formula['type'] === 'min_plus_per'): ?>
                                Мин: <?= formatMoney($formula['min_payment']) ?><br>
                                За ученика: <?= formatMoney($formula['per_student']) ?><br>
                                С <?= $formula['threshold'] ?>-го ученика
                            <?php elseif ($formula['type'] === 'fixed'): ?>
                                <?= formatMoney($formula['fixed_amount']) ?>
                            <?php elseif ($formula['type'] === 'expression'): ?>
                                <code style="font-size: 0.8rem;"><?= e($formula['expression']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            // Примеры расчёта для разного количества учеников
                            $examples = [];
                            foreach ([1, 3, 5] as $count) {
                                $amount = calculatePayment($formula, $count);
                                $examples[] = "$count: " . formatMoney($amount);
                            }
                            echo implode('<br>', $examples);
                            ?>
                        </td>
                        <td>
                            <?php if ($formula['active']): ?>
                                <span class="badge badge-success">
                                    <span class="material-icons" style="font-size: 14px;">check_circle</span>
                                    Активна
                                </span>
                            <?php else: ?>
                                <span class="badge badge-danger">
                                    <span class="material-icons" style="font-size: 14px;">block</span>
                                    Неактивна
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-text" onclick="alert('Редактировать формулу #<?= $formula['id'] ?>')">
                                <span class="material-icons" style="font-size: 18px;">edit</span>
                            </button>
                            <button class="btn btn-text" onclick="alert('Дублировать формулу #<?= $formula['id'] ?>')">
                                <span class="material-icons" style="font-size: 18px;">content_copy</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Описание типов формул -->
<div class="card mt-4">
    <div class="card-header">
        <h3 style="margin: 0;">Типы формул</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
            <div>
                <h4 style="color: var(--md-info); margin-bottom: 8px;">
                    <span class="material-icons" style="vertical-align: middle; font-size: 20px;">trending_up</span>
                    Минимум + Доплата
                </h4>
                <p style="font-size: 0.875rem; color: var(--text-medium-emphasis);">
                    Базовая сумма + доплата за каждого ученика начиная с N-го.
                </p>
                <p style="font-size: 0.875rem; margin-top: 8px;">
                    <strong>Пример:</strong> 500₽ + 150₽ за каждого со 2-го<br>
                    1 уч: 500₽, 2 уч: 650₽, 3 уч: 800₽
                </p>
            </div>

            <div>
                <h4 style="color: var(--md-success); margin-bottom: 8px;">
                    <span class="material-icons" style="vertical-align: middle; font-size: 20px;">attach_money</span>
                    Фиксированная
                </h4>
                <p style="font-size: 0.875rem; color: var(--text-medium-emphasis);">
                    Одна и та же сумма независимо от количества учеников.
                </p>
                <p style="font-size: 0.875rem; margin-top: 8px;">
                    <strong>Пример:</strong> 800₽<br>
                    Любое кол-во учеников: 800₽
                </p>
            </div>

            <div>
                <h4 style="color: var(--md-warning); margin-bottom: 8px;">
                    <span class="material-icons" style="vertical-align: middle; font-size: 20px;">code</span>
                    Пользовательская
                </h4>
                <p style="font-size: 0.875rem; color: var(--text-medium-emphasis);">
                    Произвольное математическое выражение с переменными N, min, base.
                </p>
                <p style="font-size: 0.875rem; margin-top: 8px;">
                    <strong>Пример:</strong> max(500, N * 150)<br>
                    Не меньше 500₽, иначе 150₽ за ученика
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
