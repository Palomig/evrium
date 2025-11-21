/**
 * JavaScript для управления расписанием
 */

let currentTemplateId = null;

// Открыть модальное окно шаблона
function openTemplateModal(dayOfWeek = null) {
    // Если dayOfWeek это число > 7 - это ID для редактирования
    // Если число от 1 до 7 - это предзаполнение дня недели
    const isEditing = typeof dayOfWeek === 'number' && dayOfWeek > 7;

    currentTemplateId = isEditing ? dayOfWeek : null;
    const modal = document.getElementById('template-modal');
    const form = document.getElementById('template-form');
    const title = document.getElementById('modal-title');
    const deleteBtn = document.getElementById('delete-template-btn');

    form.reset();

    // Сбросить активные кнопки
    document.querySelectorAll('.time-btn, .subject-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    if (isEditing) {
        // Режим редактирования
        title.textContent = 'Редактировать урок';
        deleteBtn.style.display = 'inline-flex'; // Показать кнопку удаления
        loadTemplateData(dayOfWeek);
    } else {
        // Режим создания
        title.textContent = 'Добавить урок в расписание';
        deleteBtn.style.display = 'none'; // Скрыть кнопку удаления
        document.getElementById('template-id').value = '';

        // Предзаполнить день недели если передан
        if (dayOfWeek && dayOfWeek >= 1 && dayOfWeek <= 7) {
            document.getElementById('template-day').value = dayOfWeek;
        }
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
            document.getElementById('template-type').value = template.lesson_type || 'group';
            document.getElementById('template-students').value = template.expected_students || 1;

            // Установить время (извлечь час из time_start)
            if (template.time_start) {
                const hour = parseInt(template.time_start.split(':')[0]);
                selectTime(hour);
            }

            // Установить предмет
            if (template.subject) {
                selectSubject(template.subject);
            }

            // Установить тир
            if (template.tier) {
                document.getElementById('template-tier').value = template.tier;
            }

            // Установить классы
            if (template.grades) {
                document.getElementById('template-grades').value = template.grades;
            }

            // Установить список учеников
            if (template.students) {
                let studentsText = '';
                try {
                    // Если это JSON массив
                    const studentsArray = typeof template.students === 'string'
                        ? JSON.parse(template.students)
                        : template.students;
                    studentsText = studentsArray.join('\n');
                } catch (e) {
                    // Если это обычный текст
                    studentsText = template.students;
                }
                document.getElementById('template-student-list').value = studentsText;
            }

            // Установить кабинет
            if (template.room) {
                document.getElementById('template-room').value = template.room;
            }

            // Подставить формулу из преподавателя (автоматически)
            if (template.teacher_id && typeof teachersData !== 'undefined') {
                const teacher = teachersData.find(t => t.id === parseInt(template.teacher_id));
                if (teacher) {
                    const formulaInput = document.getElementById('template-formula');
                    const formulaInfoGroup = document.getElementById('formula-info-group');
                    const formulaInfoText = document.getElementById('formula-info-text');

                    if (teacher.formula_id) {
                        formulaInput.value = teacher.formula_id;
                        if (formulaInfoText && formulaInfoGroup) {
                            formulaInfoText.textContent = teacher.formula_name || 'Формула назначена';
                            formulaInfoGroup.style.display = 'block';
                        }
                    } else {
                        formulaInput.value = '';
                        if (formulaInfoText && formulaInfoGroup) {
                            formulaInfoText.textContent = 'У преподавателя не назначена формула оплаты';
                            formulaInfoGroup.style.display = 'block';
                        }
                    }
                }
            }
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

    // Сбросить активные кнопки
    document.querySelectorAll('.time-btn, .subject-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Скрыть информацию о формуле
    const formulaInfoGroup = document.getElementById('formula-info-group');
    if (formulaInfoGroup) {
        formulaInfoGroup.style.display = 'none';
    }
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
    data.room = parseInt(data.room);
    data.expected_students = parseInt(data.expected_students);
    if (data.formula_id) {
        data.formula_id = parseInt(data.formula_id);
    }

    // Конвертируем список учеников в JSON массив
    if (data.students) {
        const studentsArray = data.students
            .split('\n')
            .map(s => s.trim())
            .filter(s => s.length > 0);
        data.students = JSON.stringify(studentsArray);
    } else {
        data.students = '[]';
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

            // Перезагрузить канбан доску если функция существует
            if (typeof renderKanban === 'function') {
                // Обновить данные шаблонов
                fetch('/zarplata/api/schedule.php?action=list_templates')
                    .then(res => res.json())
                    .then(res => {
                        if (res.success && typeof templatesData !== 'undefined') {
                            // Обновить глобальную переменную templatesData
                            window.templatesData = res.data;
                        }
                        setTimeout(() => location.reload(), 500);
                    });
            } else {
                setTimeout(() => location.reload(), 500);
            }
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
async function deleteTemplate() {
    const templateId = document.getElementById('template-id').value;

    if (!templateId) {
        showNotification('Ошибка: ID шаблона не найден', 'error');
        return;
    }

    if (!confirm('Вы уверены, что хотите удалить этот урок из расписания?')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/schedule.php?action=delete_template', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: parseInt(templateId) })
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.data.message || 'Урок удалён из расписания', 'success');
            closeTemplateModal();

            // Перезагрузить расписание
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка удаления', 'error');
        }
    } catch (error) {
        console.error('Error deleting template:', error);
        showNotification('Ошибка удаления урока', 'error');
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

// Выбор времени
function selectTime(hour) {
    // Снять выделение со всех кнопок времени
    document.querySelectorAll('.time-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Выделить выбранную кнопку
    const selectedBtn = document.querySelector(`.time-btn[data-hour="${hour}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }

    // Установить скрытые поля (время начала и конца)
    const timeStart = String(hour).padStart(2, '0') + ':00:00';
    const timeEnd = String(hour + 1).padStart(2, '0') + ':00:00';

    document.getElementById('template-time-start').value = timeStart;
    document.getElementById('template-time-end').value = timeEnd;
}

// Выбор предмета
function selectSubject(subject) {
    // Снять выделение со всех кнопок предметов
    document.querySelectorAll('.subject-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Выделить выбранную кнопку
    const selectedBtn = document.querySelector(`.subject-btn[data-subject="${subject}"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }

    // Установить скрытое поле
    document.getElementById('template-subject').value = subject;
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

// Просмотр урока (модальное окно со списком учеников)
function viewTemplate(lesson) {
    // Парсим учеников
    let students = [];
    if (lesson.students) {
        try {
            students = typeof lesson.students === 'string' ? JSON.parse(lesson.students) : lesson.students;
        } catch (e) {
            students = lesson.students.split('\n').filter(s => s.trim());
        }
    }

    // Создаём модальное окно
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.style.zIndex = '10001';

    const daysMap = ['', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
    const dayName = daysMap[lesson.day_of_week] || 'День ' + lesson.day_of_week;
    const time = lesson.time_start ? lesson.time_start.substring(0, 5) : '';

    modal.innerHTML = `
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 class="modal-title">${escapeHtml(lesson.subject || 'Урок')}</h2>
                <button class="modal-close" onclick="this.closest('.modal').remove()">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 16px; color: var(--text-medium-emphasis);">
                    <div style="margin-bottom: 8px;">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">event</span>
                        ${dayName}, ${time}
                    </div>
                    <div style="margin-bottom: 8px;">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">person</span>
                        ${escapeHtml(lesson.teacher_name || '—')}
                    </div>
                    <div>
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">meeting_room</span>
                        Кабинет ${lesson.room || 1}
                    </div>
                </div>

                <div style="margin-top: 24px;">
                    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 12px; color: var(--md-primary);">
                        👥 Ученики (${students.length}/${lesson.expected_students || 6})
                    </h3>
                    ${students.length > 0 ? `
                        <div style="max-height: 300px; overflow-y: auto;">
                            ${students.map(s => `
                                <div style="padding: 8px 12px; background-color: var(--md-surface-3); border-radius: 6px; margin-bottom: 6px;">
                                    • ${escapeHtml(s)}
                                </div>
                            `).join('')}
                        </div>
                    ` : `
                        <div style="text-align: center; padding: 32px; color: var(--text-medium-emphasis);">
                            <span class="material-icons" style="font-size: 48px; opacity: 0.3;">person_outline</span>
                            <div style="margin-top: 8px;">Нет учеников</div>
                        </div>
                    `}
                </div>
            </div>
            <div class="modal-footer" style="justify-content: space-between;">
                <button type="button" class="btn btn-outline" onclick="this.closest('.modal').remove()">
                    Закрыть
                </button>
                <button type="button" class="btn btn-primary" onclick="this.closest('.modal').remove(); editTemplate(${lesson.id})">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">edit</span>
                    Редактировать
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Закрытие по клику вне модального окна
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}
