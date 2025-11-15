/**
 * JavaScript для управления расписанием
 */

let currentTemplateId = null;

// Открыть модальное окно шаблона
function openTemplateModal(templateId = null) {
    currentTemplateId = templateId;
    const modal = document.getElementById('template-modal');
    const form = document.getElementById('template-form');
    const title = document.getElementById('modal-title');

    form.reset();

    if (templateId) {
        // Режим редактирования
        title.textContent = 'Редактировать шаблон';
        loadTemplateData(templateId);
    } else {
        // Режим создания
        title.textContent = 'Добавить урок в шаблон';
        document.getElementById('template-id').value = '';
    }

    modal.classList.add('active');
}

// Загрузить данные шаблона
async function loadTemplateData(templateId) {
    try {
        const response = await fetch(`/zarplata/api/schedule.php?action=get_template&id=${templateId}`);
        const result = await response.json();

        if (result.success) {
            const template = result.data;
            document.getElementById('template-id').value = template.id;
            document.getElementById('template-teacher').value = template.teacher_id || '';
            document.getElementById('template-day').value = template.day_of_week || '';
            document.getElementById('template-time-start').value = template.time_start || '';
            document.getElementById('template-time-end').value = template.time_end || '';
            document.getElementById('template-type').value = template.lesson_type || 'group';
            document.getElementById('template-students').value = template.expected_students || 1;
            document.getElementById('template-subject').value = template.subject || '';
            document.getElementById('template-formula').value = template.formula_id || '';
        } else {
            showNotification(result.error || 'Ошибка загрузки данных', 'error');
        }
    } catch (error) {
        console.error('Error loading template:', error);
        showNotification('Ошибка загрузки данных шаблона', 'error');
    }
}

// Закрыть модальное окно
function closeTemplateModal() {
    document.getElementById('template-modal').classList.remove('active');
    currentTemplateId = null;
}

// Сохранить шаблон
async function saveTemplate(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    const templateId = document.getElementById('template-id').value;
    const action = templateId ? 'update_template' : 'add_template';

    if (templateId) {
        data.id = parseInt(templateId);
    }

    // Конвертируем числа
    data.teacher_id = parseInt(data.teacher_id);
    data.day_of_week = parseInt(data.day_of_week);
    data.expected_students = parseInt(data.expected_students);
    if (data.formula_id) {
        data.formula_id = parseInt(data.formula_id);
    }

    const saveBtn = document.getElementById('save-template-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Сохранение...';

    try {
        const response = await fetch(`/zarplata/api/schedule.php?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification(
                templateId ? 'Шаблон обновлён' : 'Шаблон добавлен',
                'success'
            );
            closeTemplateModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Error saving template:', error);
        showNotification('Ошибка сохранения данных', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>Сохранить';
    }
}

// Редактировать шаблон
function editTemplate(templateId) {
    openTemplateModal(templateId);
}

// Удалить шаблон
async function deleteTemplate(templateId) {
    if (!confirm('Вы уверены, что хотите удалить этот шаблон?')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/schedule.php?action=delete_template', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: templateId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.data.message || 'Шаблон удалён', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка удаления', 'error');
        }
    } catch (error) {
        console.error('Error deleting template:', error);
        showNotification('Ошибка удаления шаблона', 'error');
    }
}

// Генерировать уроки на неделю
async function generateWeek() {
    if (!confirm('Создать уроки на эту неделю из шаблона?')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/schedule.php?action=generate_week', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ date: new Date().toISOString().split('T')[0] })
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.data.message || 'Уроки созданы', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(result.error || 'Ошибка генерации', 'error');
        }
    } catch (error) {
        console.error('Error generating week:', error);
        showNotification('Ошибка генерации уроков', 'error');
    }
}

// Утилиты
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span class="material-icons">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}</span>
        <span>${escapeHtml(message)}</span>
    `;

    document.body.appendChild(notification);

    setTimeout(() => notification.classList.add('show'), 10);

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
