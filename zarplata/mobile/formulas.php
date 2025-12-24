<?php
/**
 * Mobile Formulas Page - Full CRUD
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();

$formulas = dbQuery("SELECT * FROM payment_formulas WHERE active = 1 ORDER BY name", []);

define('PAGE_TITLE', 'Формулы');
define('ACTIVE_PAGE', 'formulas');

require_once __DIR__ . '/templates/header.php';

$typeLabels = [
    'min_plus_per' => 'База + за ученика',
    'fixed' => 'Фиксированная',
    'expression' => 'Выражение'
];
?>

<style>
.formula-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 12px;
    border: 1px solid var(--border);
    cursor: pointer;
    transition: border-color 0.15s;
}

.formula-card:active {
    border-color: var(--accent);
}

.formula-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.formula-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
}

.formula-type {
    font-size: 11px;
    padding: 3px 8px;
    background: var(--bg-elevated);
    border-radius: 4px;
    color: var(--text-muted);
}

.formula-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 15px;
    color: var(--accent);
    margin-bottom: 6px;
}

.formula-desc {
    font-size: 13px;
    color: var(--text-muted);
}

/* Type selector */
.type-selector {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 16px;
}

.type-btn {
    padding: 12px 8px;
    background: var(--bg-elevated);
    border: 2px solid var(--border);
    border-radius: 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.15s;
}

.type-btn.active {
    border-color: var(--accent);
    background: var(--accent-dim);
}

.type-btn-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-primary);
}

.type-btn-desc {
    font-size: 10px;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Form fields */
.formula-fields {
    display: none;
}

.formula-fields.active {
    display: block;
}

/* Preview */
.formula-preview {
    background: var(--bg-elevated);
    border-radius: 8px;
    padding: 12px;
    margin-top: 12px;
}

.preview-label {
    font-size: 11px;
    color: var(--text-muted);
    margin-bottom: 6px;
}

.preview-values {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.preview-item {
    text-align: center;
}

.preview-item-n {
    font-size: 10px;
    color: var(--text-muted);
}

.preview-item-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    color: var(--accent);
}
</style>

<div class="page-container">
    <?php if (empty($formulas)): ?>
        <div class="empty-state">
            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <div class="empty-state-title">Нет формул</div>
            <p class="empty-state-text">Добавьте первую формулу</p>
        </div>
    <?php else: ?>
        <?php foreach ($formulas as $f): ?>
            <div class="formula-card" onclick="openFormula(<?= $f['id'] ?>)">
                <div class="formula-header">
                    <span class="formula-name"><?= htmlspecialchars($f['name']) ?></span>
                    <span class="formula-type"><?= $typeLabels[$f['type']] ?? $f['type'] ?></span>
                </div>

                <?php if ($f['type'] === 'min_plus_per'): ?>
                    <div class="formula-value">
                        <?= number_format($f['min_payment'], 0, '', ' ') ?> ₽ + <?= number_format($f['per_student'], 0, '', ' ') ?> ₽/уч.
                    </div>
                    <div class="formula-desc">С <?= $f['threshold'] ?>-го ученика</div>
                <?php elseif ($f['type'] === 'fixed'): ?>
                    <div class="formula-value">
                        <?= number_format($f['fixed_amount'], 0, '', ' ') ?> ₽
                    </div>
                    <div class="formula-desc">Фиксированная сумма</div>
                <?php else: ?>
                    <div class="formula-value" style="font-size: 13px;">
                        <?= htmlspecialchars($f['expression']) ?>
                    </div>
                    <div class="formula-desc">Пользовательская формула</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- FAB -->
<button class="fab" onclick="openAddFormula()">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
</button>

<!-- Modal -->
<div class="modal modal-fullscreen" id="formulaModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="formulaModalTitle">Формула</h3>
            <button class="modal-close" onclick="closeModal()">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="formulaForm">
                <input type="hidden" name="id" id="formulaId">

                <div class="form-group">
                    <label class="form-label">Название</label>
                    <input type="text" name="name" id="formulaName" class="form-control" required placeholder="Например: Стандартная групповая">
                </div>

                <div class="form-group">
                    <label class="form-label">Тип формулы</label>
                    <div class="type-selector">
                        <div class="type-btn active" data-type="min_plus_per" onclick="selectType('min_plus_per')">
                            <div class="type-btn-label">База + за уч.</div>
                            <div class="type-btn-desc">500 + 150×N</div>
                        </div>
                        <div class="type-btn" data-type="fixed" onclick="selectType('fixed')">
                            <div class="type-btn-label">Фиксированная</div>
                            <div class="type-btn-desc">1000 ₽</div>
                        </div>
                        <div class="type-btn" data-type="expression" onclick="selectType('expression')">
                            <div class="type-btn-label">Выражение</div>
                            <div class="type-btn-desc">max(500, N×200)</div>
                        </div>
                    </div>
                    <input type="hidden" name="type" id="formulaType" value="min_plus_per">
                </div>

                <!-- min_plus_per fields -->
                <div class="formula-fields active" id="fields_min_plus_per">
                    <div class="form-group">
                        <label class="form-label">Базовая ставка (₽)</label>
                        <input type="number" name="min_payment" id="minPayment" class="form-control" value="500" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">За каждого ученика (₽)</label>
                        <input type="number" name="per_student" id="perStudent" class="form-control" value="150" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Начиная с ученика №</label>
                        <input type="number" name="threshold" id="threshold" class="form-control" value="2" min="1">
                    </div>
                </div>

                <!-- fixed fields -->
                <div class="formula-fields" id="fields_fixed">
                    <div class="form-group">
                        <label class="form-label">Фиксированная сумма (₽)</label>
                        <input type="number" name="fixed_amount" id="fixedAmount" class="form-control" value="1000" min="0">
                    </div>
                </div>

                <!-- expression fields -->
                <div class="formula-fields" id="fields_expression">
                    <div class="form-group">
                        <label class="form-label">Формула (N = кол-во учеников)</label>
                        <input type="text" name="expression" id="expression" class="form-control" placeholder="max(500, N * 200)">
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                            Доступно: N, +, -, *, /, max(), min(), числа
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Описание (необязательно)</label>
                    <textarea name="description" id="formulaDesc" class="form-control" rows="2" placeholder="Комментарий к формуле"></textarea>
                </div>

                <!-- Preview -->
                <div class="formula-preview">
                    <div class="preview-label">Предварительный расчёт</div>
                    <div class="preview-values" id="previewValues">
                        <!-- Filled by JS -->
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-danger" id="deleteBtn" onclick="deleteFormula()" style="display: none; margin-right: auto;">Удалить</button>
            <button class="btn btn-secondary" onclick="closeModal()">Отмена</button>
            <button class="btn btn-primary" onclick="saveFormula()">Сохранить</button>
        </div>
    </div>
</div>

<script>
const formulas = <?= json_encode($formulas, JSON_UNESCAPED_UNICODE) ?>;
let currentType = 'min_plus_per';

function selectType(type) {
    currentType = type;
    document.getElementById('formulaType').value = type;

    // Update buttons
    document.querySelectorAll('.type-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.type === type);
    });

    // Show/hide fields
    document.querySelectorAll('.formula-fields').forEach(el => {
        el.classList.remove('active');
    });
    document.getElementById('fields_' + type).classList.add('active');

    updatePreview();
}

function updatePreview() {
    const previewEl = document.getElementById('previewValues');
    const testCounts = [1, 3, 5, 8];
    let html = '';

    testCounts.forEach(n => {
        let value = 0;

        if (currentType === 'min_plus_per') {
            const min = parseInt(document.getElementById('minPayment').value) || 0;
            const per = parseInt(document.getElementById('perStudent').value) || 0;
            const thresh = parseInt(document.getElementById('threshold').value) || 1;
            value = min + Math.max(0, n - thresh + 1) * per;
        } else if (currentType === 'fixed') {
            value = parseInt(document.getElementById('fixedAmount').value) || 0;
        } else {
            // expression - simplified evaluation
            try {
                const expr = document.getElementById('expression').value || '0';
                const safeExpr = expr.replace(/N/g, n).replace(/[^0-9+\-*/().maxin,\s]/g, '');
                value = eval(safeExpr);
            } catch (e) {
                value = 0;
            }
        }

        html += `
            <div class="preview-item">
                <div class="preview-item-n">${n} уч.</div>
                <div class="preview-item-value">${value.toLocaleString('ru-RU')} ₽</div>
            </div>
        `;
    });

    previewEl.innerHTML = html;
}

function openFormula(id) {
    const f = formulas.find(x => x.id == id);
    if (!f) return;

    document.getElementById('formulaModalTitle').textContent = 'Редактировать формулу';
    document.getElementById('formulaId').value = f.id;
    document.getElementById('formulaName').value = f.name || '';
    document.getElementById('formulaDesc').value = f.description || '';
    document.getElementById('deleteBtn').style.display = '';

    selectType(f.type);

    if (f.type === 'min_plus_per') {
        document.getElementById('minPayment').value = f.min_payment || 0;
        document.getElementById('perStudent').value = f.per_student || 0;
        document.getElementById('threshold').value = f.threshold || 2;
    } else if (f.type === 'fixed') {
        document.getElementById('fixedAmount').value = f.fixed_amount || 0;
    } else {
        document.getElementById('expression').value = f.expression || '';
    }

    updatePreview();

    document.getElementById('formulaModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function openAddFormula() {
    document.getElementById('formulaModalTitle').textContent = 'Новая формула';
    document.getElementById('formulaForm').reset();
    document.getElementById('formulaId').value = '';
    document.getElementById('deleteBtn').style.display = 'none';

    selectType('min_plus_per');
    document.getElementById('minPayment').value = 500;
    document.getElementById('perStudent').value = 150;
    document.getElementById('threshold').value = 2;

    updatePreview();

    document.getElementById('formulaModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('formulaModal').classList.remove('active');
    document.body.style.overflow = '';
}

async function saveFormula() {
    const form = document.getElementById('formulaForm');
    const data = Object.fromEntries(new FormData(form));
    const action = data.id ? 'update' : 'add';

    try {
        MobileApp.showLoading();
        const res = await fetch(`../api/formulas.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();

        if (result.success) {
            MobileApp.showToast('Сохранено', 'success');
            closeModal();
            setTimeout(() => location.reload(), 500);
        } else {
            MobileApp.showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        MobileApp.showToast('Ошибка сети', 'error');
    } finally {
        MobileApp.hideLoading();
    }
}

async function deleteFormula() {
    if (!confirm('Удалить формулу?')) return;

    const id = document.getElementById('formulaId').value;

    try {
        MobileApp.showLoading();
        const res = await fetch(`../api/formulas.php?action=delete`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await res.json();

        if (result.success) {
            MobileApp.showToast('Удалено', 'success');
            closeModal();
            setTimeout(() => location.reload(), 500);
        } else {
            MobileApp.showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        MobileApp.showToast('Ошибка сети', 'error');
    } finally {
        MobileApp.hideLoading();
    }
}

// Update preview on input change
document.querySelectorAll('#minPayment, #perStudent, #threshold, #fixedAmount, #expression').forEach(el => {
    el.addEventListener('input', updatePreview);
});

// Initial preview
updatePreview();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
