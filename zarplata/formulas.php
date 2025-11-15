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
        <button class="btn btn-primary" onclick="openFormulaModal()">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Создать формулу
        </button>
    </div>

    <?php if (empty($formulas)): ?>
        <div class="empty-state">
            <div class="material-icons">functions</div>
            <p>Нет формул оплаты</p>
            <p style="margin-top: 8px;">
                <button class="btn btn-primary" onclick="openFormulaModal()">
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
                            <button class="btn btn-text" onclick="editFormula(<?= $formula['id'] ?>)" title="Редактировать">
                                <span class="material-icons" style="font-size: 18px;">edit</span>
                            </button>
                            <button class="btn btn-text" onclick="toggleFormulaActive(<?= $formula['id'] ?>)" title="Переключить активность">
                                <span class="material-icons" style="font-size: 18px;">
                                    <?= $formula['active'] ? 'toggle_on' : 'toggle_off' ?>
                                </span>
                            </button>
                            <button class="btn btn-text" onclick="deleteFormula(<?= $formula['id'] ?>)" title="Удалить">
                                <span class="material-icons" style="font-size: 18px; color: var(--md-error);">delete</span>
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

<!-- Модальное окно добавления/редактирования формулы -->
<div id="formula-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Создать формулу</h3>
            <button class="modal-close" onclick="closeFormulaModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <form id="formula-form" onsubmit="saveFormula(event)">
            <input type="hidden" id="formula-id" name="id">

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="formula-name">Название формулы *</label>
                    <input type="text" id="formula-name" name="name" required maxlength="100">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="formula-description">Описание</label>
                    <textarea id="formula-description" name="description" rows="2"></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="formula-type">Тип формулы *</label>
                    <select id="formula-type" name="type" required onchange="updateFormulaFields()">
                        <option value="">Выберите тип</option>
                        <option value="min_plus_per">Минимум + Доплата</option>
                        <option value="fixed">Фиксированная сумма</option>
                        <option value="expression">Пользовательская формула</option>
                    </select>
                </div>
            </div>

            <!-- Поля для типа min_plus_per -->
            <div id="min-plus-per-fields" style="display: none;">
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="min-payment">Минимальная оплата (₽) *</label>
                        <input type="number" id="min-payment" name="min_payment" step="0.01" min="0">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="per-student">Доплата за ученика (₽) *</label>
                        <input type="number" id="per-student" name="per_student" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="threshold">Начиная с N-го ученика *</label>
                        <input type="number" id="threshold" name="threshold" min="1" value="1">
                        <small style="color: var(--text-medium-emphasis);">
                            С какого ученика начинать доплату (обычно 1 или 2)
                        </small>
                    </div>
                </div>
            </div>

            <!-- Поля для типа fixed -->
            <div id="fixed-fields" style="display: none;">
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="fixed-amount">Фиксированная сумма (₽) *</label>
                        <input type="number" id="fixed-amount" name="fixed_amount" step="0.01" min="0">
                    </div>
                </div>
            </div>

            <!-- Поля для типа expression -->
            <div id="expression-fields" style="display: none;">
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="expression">Математическое выражение *</label>
                        <input type="text" id="expression" name="expression" placeholder="max(500, N * 150)">
                        <small style="color: var(--text-medium-emphasis);">
                            Доступные переменные: <strong>N</strong> (кол-во учеников), <strong>min</strong>, <strong>base</strong><br>
                            Функции: max, min, abs, pow, sqrt, floor, ceil
                        </small>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-text" onclick="closeFormulaModal()">Отмена</button>
                <button type="submit" class="btn btn-primary" id="save-formula-btn">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                    Сохранить
                </button>
            </div>
        </form>
    </div>
</div>

<script src="/zarplata/assets/js/formulas.js"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
