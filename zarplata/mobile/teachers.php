<?php
/**
 * Mobile Teachers Page
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();

$teachers = dbQuery("
    SELECT t.*, pf.name as formula_name
    FROM teachers t
    LEFT JOIN payment_formulas pf ON t.formula_id = pf.id
    WHERE t.active = 1
    ORDER BY t.name
", []);

$formulas = dbQuery("SELECT id, name FROM payment_formulas WHERE active = 1 ORDER BY name", []);

define('PAGE_TITLE', 'Преподаватели');
define('ACTIVE_PAGE', 'teachers');

require_once __DIR__ . '/templates/header.php';
?>

<div class="page-container">
    <?php if (empty($teachers)): ?>
        <div class="empty-state">
            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <div class="empty-state-title">Нет преподавателей</div>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 0;">
            <?php foreach ($teachers as $t): ?>
                <div class="list-item" onclick="openTeacher(<?= $t['id'] ?>)">
                    <div class="list-item-avatar" style="background: linear-gradient(135deg, var(--accent), #0d9488);">
                        <?= mb_substr($t['display_name'] ?? $t['name'], 0, 1) ?>
                    </div>
                    <div class="list-item-content">
                        <div class="list-item-title"><?= htmlspecialchars($t['display_name'] ?? $t['name']) ?></div>
                        <div class="list-item-subtitle">
                            <?= $t['formula_name'] ? $t['formula_name'] : 'Без формулы' ?>
                            <?= $t['phone'] ? ' • ' . $t['phone'] : '' ?>
                        </div>
                    </div>
                    <div class="list-item-action">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- FAB -->
<button class="fab" onclick="openAddTeacher()">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
</button>

<!-- Modal -->
<div class="modal modal-fullscreen" id="teacherModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="teacherModalTitle">Преподаватель</h3>
            <button class="modal-close" onclick="closeModal()">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="teacherForm">
                <input type="hidden" name="id" id="teacherId">
                <div class="form-group">
                    <label class="form-label">Имя (полное)</label>
                    <input type="text" name="name" id="teacherName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Отображаемое имя</label>
                    <input type="text" name="display_name" id="teacherDisplayName" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Телефон</label>
                    <input type="tel" name="phone" id="teacherPhone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="teacherEmail" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Telegram ID</label>
                    <input type="text" name="telegram_id" id="teacherTelegram" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Формула оплаты</label>
                    <select name="formula_id" id="teacherFormula" class="form-control">
                        <option value="">Не выбрана</option>
                        <?php foreach ($formulas as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Отмена</button>
            <button class="btn btn-primary" onclick="saveTeacher()">Сохранить</button>
        </div>
    </div>
</div>

<script>
const teachers = <?= json_encode($teachers, JSON_UNESCAPED_UNICODE) ?>;

function openTeacher(id) {
    const t = teachers.find(x => x.id == id);
    if (!t) return;

    document.getElementById('teacherModalTitle').textContent = 'Редактировать';
    document.getElementById('teacherId').value = t.id;
    document.getElementById('teacherName').value = t.name || '';
    document.getElementById('teacherDisplayName').value = t.display_name || '';
    document.getElementById('teacherPhone').value = t.phone || '';
    document.getElementById('teacherEmail').value = t.email || '';
    document.getElementById('teacherTelegram').value = t.telegram_id || '';
    document.getElementById('teacherFormula').value = t.formula_id || '';

    document.getElementById('teacherModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function openAddTeacher() {
    document.getElementById('teacherModalTitle').textContent = 'Добавить преподавателя';
    document.getElementById('teacherForm').reset();
    document.getElementById('teacherId').value = '';

    document.getElementById('teacherModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('teacherModal').classList.remove('active');
    document.body.style.overflow = '';
}

async function saveTeacher() {
    const form = document.getElementById('teacherForm');
    const data = Object.fromEntries(new FormData(form));
    const action = data.id ? 'update' : 'add';

    try {
        MobileApp.showLoading();
        const res = await fetch(`../api/teachers.php?action=${action}`, {
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
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
