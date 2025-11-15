/**
 * JavaScript для управления уроками
 */

let currentLessonId = null;
let currentViewLessonId = null;

// Открыть модальное окно добавления урока
function openLessonModal(lessonId = null) {
    currentLessonId = lessonId;
    const modal = document.getElementById('lesson-modal');
    const form = document.getElementById('lesson-form');
    const title = document.getElementById('modal-title');

    form.reset();

    if (lessonId) {
        // Режим редактирования
        title.textContent = 'Редактировать урок';
        loadLessonData(lessonId);
    } else {
        // Режим создания - устанавливаем дату на сегодня
        title.textContent = 'Добавить урок';
        document.getElementById('lesson-id').value = '';
        document.getElementById('lesson-date').value = new Date().toISOString().split('T')[0];
    }

    modal.classList.add('active');
}

// Загрузить данные урока для редактирования
async function loadLessonData(lessonId) {
    try {
        const response = await fetch(`/zarplata/api/lessons.php?action=get&id=${lessonId}`);
        const result = await response.json();

        if (result.success) {
            const lesson = result.data;
            document.getElementById('lesson-id').value = lesson.id;
            document.getElementById('lesson-teacher').value = lesson.teacher_id || '';
            document.getElementById('lesson-date').value = lesson.lesson_date || '';
            document.getElementById('lesson-time-start').value = lesson.time_start || '';
            document.getElementById('lesson-time-end').value = lesson.time_end || '';
            document.getElementById('lesson-type').value = lesson.lesson_type || 'group';
            document.getElementById('lesson-students').value = lesson.expected_students || 1;
            document.getElementById('lesson-subject').value = lesson.subject || '';
            document.getElementById('lesson-formula').value = lesson.formula_id || '';
            document.getElementById('lesson-notes').value = lesson.notes || '';
        } else {
            showNotification(result.error || 'Ошибка загрузки данных', 'error');
        }
    } catch (error) {
        console.error('Error loading lesson:', error);
        showNotification('Ошибка загрузки данных урока', 'error');
    }
}

// Закрыть модальное окно
function closeLessonModal() {
    document.getElementById('lesson-modal').classList.remove('active');
    currentLessonId = null;
}

// Сохранить урок
async function saveLesson(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    const lessonId = document.getElementById('lesson-id').value;
    const action = lessonId ? 'update' : 'add';

    if (lessonId) {
        data.id = lessonId;
    }

    // Конвертируем числа
    data.teacher_id = parseInt(data.teacher_id);
    data.expected_students = parseInt(data.expected_students);
    if (data.formula_id) {
        data.formula_id = parseInt(data.formula_id);
    }

    const saveBtn = document.getElementById('save-lesson-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Сохранение...';

    try {
        const response = await fetch(`/zarplata/api/lessons.php?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification(
                lessonId ? 'Урок обновлён' : 'Урок добавлен',
                'success'
            );
            closeLessonModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Error saving lesson:', error);
        showNotification('Ошибка сохранения данных', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>Сохранить';
    }
}

// Редактировать урок
function editLesson(lessonId) {
    openLessonModal(lessonId);
}

// Просмотр урока
async function viewLesson(lessonId) {
    currentViewLessonId = lessonId;
    const modal = document.getElementById('view-lesson-modal');
    const content = document.getElementById('view-lesson-content');

    content.innerHTML = '<p style="text-align: center;">Загрузка...</p>';
    modal.classList.add('active');

    try {
        const response = await fetch(`/zarplata/api/lessons.php?action=get&id=${lessonId}`);
        const result = await response.json();

        if (result.success) {
            const lesson = result.data;
            const statusBadge = getLessonStatusBadge(lesson.status);

            content.innerHTML = `
                <div style="display: grid; gap: 16px;">
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Дата:</strong><br>
                        <span style="font-size: 1.25rem;">${formatDate(lesson.lesson_date)}</span>
                    </div>
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Время:</strong><br>
                        <span>${formatTime(lesson.time_start)} - ${formatTime(lesson.time_end)}</span>
                    </div>
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Преподаватель:</strong><br>
                        <span>${escapeHtml(lesson.teacher_name)}</span>
                    </div>
                    ${lesson.subject ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Предмет:</strong><br>
                        <span>${escapeHtml(lesson.subject)}</span>
                    </div>
                    ` : ''}
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Тип:</strong><br>
                        <span>${lesson.lesson_type === 'group' ? 'Групповое' : 'Индивидуальное'}</span>
                    </div>
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Учеников:</strong><br>
                        <span>${lesson.status === 'completed' ?
                            `${lesson.actual_students} из ${lesson.expected_students}` :
                            `Ожидается ${lesson.expected_students}`}</span>
                    </div>
                    ${lesson.formula_name ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Формула оплаты:</strong><br>
                        <span>${escapeHtml(lesson.formula_name)}</span>
                    </div>
                    ` : ''}
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Статус:</strong><br>
                        <span class="badge badge-${statusBadge.class}">
                            <span class="material-icons" style="font-size: 14px;">${statusBadge.icon}</span>
                            ${statusBadge.text}
                        </span>
                    </div>
                    ${lesson.notes ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Примечания:</strong><br>
                        <span>${escapeHtml(lesson.notes)}</span>
                    </div>
                    ` : ''}
                </div>
            `;
        } else {
            content.innerHTML = `<p style="color: var(--md-error);">${escapeHtml(result.error || 'Ошибка загрузки')}</p>`;
        }
    } catch (error) {
        console.error('Error viewing lesson:', error);
        content.innerHTML = '<p style="color: var(--md-error);">Ошибка загрузки данных</p>';
    }
}

// Закрыть модальное окно просмотра
function closeViewModal() {
    document.getElementById('view-lesson-modal').classList.remove('active');
    currentViewLessonId = null;
}

// Завершить урок
function completeLesson(lessonId) {
    const modal = document.getElementById('complete-lesson-modal');
    document.getElementById('complete-lesson-id').value = lessonId;
    document.getElementById('actual-students').value = 0;
    modal.classList.add('active');
}

// Закрыть модальное окно завершения
function closeCompleteModal() {
    document.getElementById('complete-lesson-modal').classList.remove('active');
}

// Сохранить завершение урока
async function saveComplete(event) {
    event.preventDefault();

    const lessonId = document.getElementById('complete-lesson-id').value;
    const actualStudents = parseInt(document.getElementById('actual-students').value);

    const completeBtn = document.getElementById('complete-lesson-btn');
    completeBtn.disabled = true;
    completeBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Завершение...';

    try {
        const response = await fetch('/zarplata/api/lessons.php?action=complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: parseInt(lessonId),
                actual_students: actualStudents
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Урок завершён, оплата рассчитана', 'success');
            closeCompleteModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка завершения урока', 'error');
        }
    } catch (error) {
        console.error('Error completing lesson:', error);
        showNotification('Ошибка завершения урока', 'error');
    } finally {
        completeBtn.disabled = false;
        completeBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">check_circle</span>Завершить урок';
    }
}

// Отменить урок
async function cancelLesson(lessonId) {
    if (!confirm('Вы уверены, что хотите отменить урок?')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/lessons.php?action=cancel', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: lessonId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Урок отменён', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка отмены урока', 'error');
        }
    } catch (error) {
        console.error('Error canceling lesson:', error);
        showNotification('Ошибка отмены урока', 'error');
    }
}

// Удалить урок
async function deleteLesson(lessonId) {
    if (!confirm('Вы уверены, что хотите удалить урок?')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/lessons.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: lessonId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.data.message || 'Урок удалён', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка удаления', 'error');
        }
    } catch (error) {
        console.error('Error deleting lesson:', error);
        showNotification('Ошибка удаления урока', 'error');
    }
}

// Утилиты
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('ru-RU');
}

function formatTime(timeStr) {
    if (!timeStr) return '';
    const parts = timeStr.split(':');
    return `${parts[0]}:${parts[1]}`;
}

function getLessonStatusBadge(status) {
    const statuses = {
        'scheduled': { text: 'Запланирован', class: 'info', icon: 'schedule' },
        'completed': { text: 'Завершён', class: 'success', icon: 'check_circle' },
        'cancelled': { text: 'Отменён', class: 'danger', icon: 'cancel' },
        'rescheduled': { text: 'Перенесён', class: 'warning', icon: 'update' }
    };
    return statuses[status] || { text: 'Неизвестно', class: 'secondary', icon: 'help' };
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
