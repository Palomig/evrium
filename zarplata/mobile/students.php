<?php
/**
 * Mobile Students Page
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();

// Поиск
$search = $_GET['search'] ?? '';
$searchWhere = $search ? "AND (name LIKE ? OR phone LIKE ?)" : "";
$searchParams = $search ? ["%$search%", "%$search%"] : [];

$students = dbQuery("
    SELECT * FROM students
    WHERE active = 1 $searchWhere
    ORDER BY name
    LIMIT 100
", $searchParams);

define('PAGE_TITLE', 'Ученики');
define('ACTIVE_PAGE', 'students');

require_once __DIR__ . '/templates/header.php';
?>

<!-- Search -->
<div class="search-bar">
    <form method="GET" class="search-input-wrapper">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="search" name="search" class="search-input"
               placeholder="Поиск учеников..."
               value="<?= htmlspecialchars($search) ?>">
    </form>
</div>

<div class="page-container">
    <?php if (empty($students)): ?>
        <div class="empty-state">
            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/>
            </svg>
            <div class="empty-state-title">Нет учеников</div>
            <p class="empty-state-text"><?= $search ? 'Ничего не найдено' : 'Добавьте первого ученика' ?></p>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 0;">
            <?php foreach ($students as $student): ?>
                <div class="list-item" onclick="openStudent(<?= $student['id'] ?>)">
                    <div class="list-item-avatar">
                        <?= mb_substr($student['name'], 0, 1) ?>
                    </div>
                    <div class="list-item-content">
                        <div class="list-item-title"><?= htmlspecialchars($student['name']) ?></div>
                        <div class="list-item-subtitle">
                            <?= $student['class'] ? $student['class'] . ' класс' : '' ?>
                            <?= $student['phone'] ? ' • ' . $student['phone'] : '' ?>
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
<button class="fab" onclick="openAddStudent()">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
</button>

<!-- Add Student Modal -->
<div class="modal modal-fullscreen" id="studentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="studentModalTitle">Добавить ученика</h3>
            <button class="modal-close" onclick="closeModal()">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="studentForm">
                <input type="hidden" name="id" id="studentId">
                <div class="form-group">
                    <label class="form-label">Имя</label>
                    <input type="text" name="name" id="studentName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Класс</label>
                    <select name="class" id="studentClass" class="form-control">
                        <option value="">Не указан</option>
                        <?php for ($c = 1; $c <= 11; $c++): ?>
                            <option value="<?= $c ?>"><?= $c ?> класс</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Телефон</label>
                    <input type="tel" name="phone" id="studentPhone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Телефон родителя</label>
                    <input type="tel" name="parent_phone" id="studentParentPhone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Заметки</label>
                    <textarea name="notes" id="studentNotes" class="form-control"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Отмена</button>
            <button class="btn btn-primary" onclick="saveStudent()">Сохранить</button>
        </div>
    </div>
</div>

<script>
const students = <?= json_encode($students, JSON_UNESCAPED_UNICODE) ?>;

function openStudent(id) {
    const s = students.find(x => x.id == id);
    if (!s) return;

    document.getElementById('studentModalTitle').textContent = 'Редактировать';
    document.getElementById('studentId').value = s.id;
    document.getElementById('studentName').value = s.name || '';
    document.getElementById('studentClass').value = s.class || '';
    document.getElementById('studentPhone').value = s.phone || '';
    document.getElementById('studentParentPhone').value = s.parent_phone || '';
    document.getElementById('studentNotes').value = s.notes || '';

    document.getElementById('studentModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function openAddStudent() {
    document.getElementById('studentModalTitle').textContent = 'Добавить ученика';
    document.getElementById('studentForm').reset();
    document.getElementById('studentId').value = '';

    document.getElementById('studentModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('studentModal').classList.remove('active');
    document.body.style.overflow = '';
}

async function saveStudent() {
    const form = document.getElementById('studentForm');
    const data = Object.fromEntries(new FormData(form));
    const action = data.id ? 'update' : 'add';

    try {
        MobileApp.showLoading();
        const res = await fetch(`../api/students.php?action=${action}`, {
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
