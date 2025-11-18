<?php
/**
 * Страница управления преподавателями
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получить всех преподавателей
$teachers = dbQuery(
    "SELECT * FROM teachers ORDER BY active DESC, name ASC",
    []
);

define('PAGE_TITLE', 'Преподаватели');
define('PAGE_SUBTITLE', 'Управление преподавателями');
define('ACTIVE_PAGE', 'teachers');

require_once __DIR__ . '/templates/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Все преподаватели (<?= count($teachers) ?>)</h2>
        <button class="btn btn-primary" onclick="openTeacherModal()">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">add</span>
            Добавить преподавателя
        </button>
    </div>

    <?php if (empty($teachers)): ?>
        <div class="empty-state">
            <div class="material-icons">person_off</div>
            <p>Нет преподавателей в системе</p>
            <p style="margin-top: 8px;">
                <button class="btn btn-primary" onclick="openTeacherModal()">
                    Добавить первого преподавателя
                </button>
            </p>
        </div>
    <?php else: ?>
        <table id="teachers-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ФИО</th>
                    <th>Telegram</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Статус</th>
                    <th>Дата создания</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $teacher): ?>
                    <tr data-teacher-id="<?= $teacher['id'] ?>">
                        <td><?= $teacher['id'] ?></td>
                        <td>
                            <strong><?= e($teacher['name']) ?></strong>
                            <?php if ($teacher['notes']): ?>
                                <br>
                                <small style="color: var(--text-medium-emphasis);">
                                    <?= e(truncate($teacher['notes'], 50)) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($teacher['telegram_id']): ?>
                                <span class="badge badge-success">
                                    <span class="material-icons" style="font-size: 14px;">check_circle</span>
                                    @<?= e($teacher['telegram_username'] ?: 'ID: ' . $teacher['telegram_id']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-warning">
                                    <span class="material-icons" style="font-size: 14px;">warning</span>
                                    Не подключен
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= $teacher['phone'] ? formatPhone($teacher['phone']) : '—' ?></td>
                        <td><?= e($teacher['email'] ?: '—') ?></td>
                        <td>
                            <?php if ($teacher['active']): ?>
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
                        <td><?= formatDate($teacher['created_at']) ?></td>
                        <td>
                            <button class="btn btn-text" onclick="viewTeacher(<?= $teacher['id'] ?>)" title="Просмотр">
                                <span class="material-icons" style="font-size: 18px;">visibility</span>
                            </button>
                            <button class="btn btn-text" onclick="editTeacher(<?= $teacher['id'] ?>)" title="Редактировать">
                                <span class="material-icons" style="font-size: 18px;">edit</span>
                            </button>
                            <button class="btn btn-text" onclick="toggleTeacherActive(<?= $teacher['id'] ?>)" title="<?= $teacher['active'] ? 'Деактивировать' : 'Активировать' ?>">
                                <span class="material-icons" style="font-size: 18px;"><?= $teacher['active'] ? 'block' : 'check_circle' ?></span>
                            </button>
                            <button class="btn btn-text" onclick="deleteTeacher(<?= $teacher['id'] ?>)" title="Удалить" style="color: var(--md-error);">
                                <span class="material-icons" style="font-size: 18px;">delete</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Модальное окно для добавления/редактирования преподавателя -->
<div id="teacher-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Добавить преподавателя</h2>
            <button class="modal-close" onclick="closeTeacherModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <form id="teacher-form" onsubmit="saveTeacher(event)">
            <input type="hidden" id="teacher-id" name="id">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="teacher-name">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">person</span>
                        ФИО преподавателя *
                    </label>
                    <input
                        type="text"
                        class="form-control"
                        id="teacher-name"
                        name="name"
                        placeholder="Иванов Иван Иванович"
                        required
                        maxlength="100"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="teacher-phone">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">phone</span>
                        Телефон
                    </label>
                    <input
                        type="tel"
                        class="form-control"
                        id="teacher-phone"
                        name="phone"
                        placeholder="+7 (999) 123-45-67"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="teacher-email">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">email</span>
                        Email
                    </label>
                    <input
                        type="email"
                        class="form-control"
                        id="teacher-email"
                        name="email"
                        placeholder="teacher@example.com"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="teacher-telegram-id">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">tag</span>
                        Telegram ID
                    </label>
                    <input
                        type="text"
                        class="form-control"
                        id="teacher-telegram-id"
                        name="telegram_id"
                        placeholder="245710727"
                        pattern="[0-9]+"
                    >
                    <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                        Преподаватель получит этот ID после команды /start в боте
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="teacher-telegram">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">telegram</span>
                        Telegram username
                    </label>
                    <input
                        type="text"
                        class="form-control"
                        id="teacher-telegram"
                        name="telegram_username"
                        placeholder="username (без @)"
                    >
                    <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                        Опционально. Автоматически обновляется при первом контакте с ботом.
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="teacher-notes">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">notes</span>
                        Примечания
                    </label>
                    <textarea
                        class="form-control"
                        id="teacher-notes"
                        name="notes"
                        rows="3"
                        placeholder="Дополнительная информация о преподавателе"
                    ></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeTeacherModal()">
                    Отмена
                </button>
                <button type="submit" class="btn btn-primary" id="save-teacher-btn">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                    Сохранить
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно для просмотра преподавателя -->
<div id="view-teacher-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Информация о преподавателе</h2>
            <button class="modal-close" onclick="closeViewModal()">
                <span class="material-icons">close</span>
            </button>
        </div>
        <div class="modal-body" id="view-teacher-content">
            <!-- Контент загружается динамически -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeViewModal()">
                Закрыть
            </button>
            <button type="button" class="btn btn-primary" onclick="editTeacherFromView()">
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
        max-width: 600px;
        width: 90%;
        max-height: 85vh;
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

    .modal-body {
        padding: 24px;
        overflow-y: auto;
        flex: 1;
    }

    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.12);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    /* Кнопки действий */
    .btn btn-text:hover {
        background-color: rgba(255, 255, 255, 0.08);
    }
</style>

<script>
let currentTeacherId = null;
let currentViewTeacherId = null;

// Открыть модальное окно добавления преподавателя
function openTeacherModal(teacherId = null) {
    currentTeacherId = teacherId;
    const modal = document.getElementById('teacher-modal');
    const form = document.getElementById('teacher-form');
    const title = document.getElementById('modal-title');

    form.reset();

    if (teacherId) {
        // Режим редактирования
        title.textContent = 'Редактировать преподавателя';
        loadTeacherData(teacherId);
    } else {
        // Режим создания
        title.textContent = 'Добавить преподавателя';
        document.getElementById('teacher-id').value = '';
    }

    modal.classList.add('active');
}

// Загрузить данные преподавателя для редактирования
async function loadTeacherData(teacherId) {
    try {
        const response = await fetch(`/zarplata/api/teachers.php?action=get&id=${teacherId}`);
        const result = await response.json();

        if (result.success) {
            const teacher = result.data;
            document.getElementById('teacher-id').value = teacher.id;
            document.getElementById('teacher-name').value = teacher.name || '';
            document.getElementById('teacher-phone').value = teacher.phone || '';
            document.getElementById('teacher-email').value = teacher.email || '';
            document.getElementById('teacher-telegram-id').value = teacher.telegram_id || '';
            document.getElementById('teacher-telegram').value = teacher.telegram_username || '';
            document.getElementById('teacher-notes').value = teacher.notes || '';
        } else {
            showNotification(result.error || 'Ошибка загрузки данных', 'error');
        }
    } catch (error) {
        console.error('Error loading teacher:', error);
        showNotification('Ошибка загрузки данных преподавателя', 'error');
    }
}

// Закрыть модальное окно
function closeTeacherModal() {
    document.getElementById('teacher-modal').classList.remove('active');
    currentTeacherId = null;
}

// Сохранить преподавателя
async function saveTeacher(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    const teacherId = document.getElementById('teacher-id').value;
    const action = teacherId ? 'update' : 'add';

    if (teacherId) {
        data.id = teacherId;
    }

    // Убираем @ из telegram username если есть
    if (data.telegram_username) {
        data.telegram_username = data.telegram_username.replace('@', '');
    }

    const saveBtn = document.getElementById('save-teacher-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Сохранение...';

    try {
        const response = await fetch(`/zarplata/api/teachers.php?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification(
                teacherId ? 'Преподаватель обновлён' : 'Преподаватель добавлен',
                'success'
            );
            closeTeacherModal();
            // Перезагружаем страницу для обновления списка
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Error saving teacher:', error);
        showNotification('Ошибка сохранения данных', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>Сохранить';
    }
}

// Редактировать преподавателя
function editTeacher(teacherId) {
    openTeacherModal(teacherId);
}

// Просмотр преподавателя
async function viewTeacher(teacherId) {
    currentViewTeacherId = teacherId;
    const modal = document.getElementById('view-teacher-modal');
    const content = document.getElementById('view-teacher-content');

    content.innerHTML = '<p style="text-align: center;">Загрузка...</p>';
    modal.classList.add('active');

    try {
        const response = await fetch(`/zarplata/api/teachers.php?action=get&id=${teacherId}`);
        const result = await response.json();

        if (result.success) {
            const teacher = result.data;
            content.innerHTML = `
                <div style="display: grid; gap: 16px;">
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">ФИО:</strong><br>
                        <span style="font-size: 1.25rem;">${escapeHtml(teacher.name)}</span>
                    </div>
                    ${teacher.phone ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Телефон:</strong><br>
                        <span>${escapeHtml(teacher.phone)}</span>
                    </div>
                    ` : ''}
                    ${teacher.email ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Email:</strong><br>
                        <span>${escapeHtml(teacher.email)}</span>
                    </div>
                    ` : ''}
                    ${teacher.telegram_username ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Telegram:</strong><br>
                        <span>@${escapeHtml(teacher.telegram_username)}</span>
                    </div>
                    ` : ''}
                    ${teacher.notes ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Примечания:</strong><br>
                        <span>${escapeHtml(teacher.notes)}</span>
                    </div>
                    ` : ''}
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Статус:</strong><br>
                        <span class="badge badge-${teacher.active ? 'success' : 'danger'}">
                            <span class="material-icons" style="font-size: 14px;">${teacher.active ? 'check_circle' : 'block'}</span>
                            ${teacher.active ? 'Активен' : 'Неактивен'}
                        </span>
                    </div>
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Дата создания:</strong><br>
                        <span>${new Date(teacher.created_at).toLocaleDateString('ru-RU')}</span>
                    </div>
                </div>
            `;
        } else {
            content.innerHTML = `<p style="color: var(--md-error);">${escapeHtml(result.error || 'Ошибка загрузки')}</p>`;
        }
    } catch (error) {
        console.error('Error viewing teacher:', error);
        content.innerHTML = '<p style="color: var(--md-error);">Ошибка загрузки данных</p>';
    }
}

// Закрыть модальное окно просмотра
function closeViewModal() {
    document.getElementById('view-teacher-modal').classList.remove('active');
    currentViewTeacherId = null;
}

// Редактировать из модального окна просмотра
function editTeacherFromView() {
    closeViewModal();
    editTeacher(currentViewTeacherId);
}

// Переключить активность преподавателя
async function toggleTeacherActive(teacherId) {
    if (!confirm('Изменить статус преподавателя?')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/teachers.php?action=toggle_active', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: teacherId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Статус преподавателя изменён', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка изменения статуса', 'error');
        }
    } catch (error) {
        console.error('Error toggling teacher status:', error);
        showNotification('Ошибка изменения статуса', 'error');
    }
}

// Удалить преподавателя
async function deleteTeacher(teacherId) {
    if (!confirm('Вы уверены, что хотите удалить преподавателя? Если у преподавателя есть уроки, он будет деактивирован.')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/teachers.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: teacherId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.data.message || 'Преподаватель удалён', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка удаления', 'error');
        }
    } catch (error) {
        console.error('Error deleting teacher:', error);
        showNotification('Ошибка удаления преподавателя', 'error');
    }
}

// Утилиты
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'info') {
    // Создаём уведомление
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span class="material-icons">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}</span>
        <span>${escapeHtml(message)}</span>
    `;

    document.body.appendChild(notification);

    // Показываем уведомление
    setTimeout(() => notification.classList.add('show'), 10);

    // Убираем уведомление через 3 секунды
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Закрытие модального окна по клику вне его
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});
</script>

<style>
    /* Уведомления */
    .notification {
        position: fixed;
        top: 24px;
        right: 24px;
        background: var(--md-surface);
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: var(--elevation-3);
        display: flex;
        align-items: center;
        gap: 12px;
        transform: translateX(400px);
        transition: transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
        z-index: 10000;
    }

    .notification.show {
        transform: translateX(0);
    }

    .notification-success {
        border-left: 4px solid var(--md-success);
        color: var(--md-success);
    }

    .notification-error {
        border-left: 4px solid var(--md-error);
        color: var(--md-error);
    }

    .notification-info {
        border-left: 4px solid var(--md-info);
        color: var(--md-info);
    }

    /* Анимация вращения */
    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .rotating {
        animation: rotate 1s linear infinite;
    }
</style>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
