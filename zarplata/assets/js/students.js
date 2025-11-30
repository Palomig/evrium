/**
 * Скрипт управления учениками
 */

// Глобальная переменная для хранения расписания
let schedule = {};
let currentStudentId = null;

// Открыть модальное окно добавления ученика
function openStudentModal() {
    document.getElementById('student-modal').classList.add('active');
    document.getElementById('modal-title').textContent = 'Добавить ученика';
    document.getElementById('student-form').reset();
    document.getElementById('student-id').value = '';
    currentStudentId = null;

    // Сброс расписания
    schedule = {};
    renderSchedule();

    // Сброс активных кнопок дней
    document.querySelectorAll('.btn-day').forEach(btn => btn.classList.remove('active'));

    // Активировать "Группа" по умолчанию
    document.querySelectorAll('.btn-toggle').forEach(btn => {
        if (btn.dataset.value === 'group') {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    document.getElementById('student-lesson-type').value = 'group';

    // Активировать "C" по умолчанию
    document.querySelectorAll('.btn-tier').forEach(btn => {
        if (btn.dataset.tier === 'C') {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    document.getElementById('student-tier').value = 'C';
}

// Закрыть модальное окно
function closeStudentModal() {
    document.getElementById('student-modal').classList.remove('active');
    schedule = {};
}

// Выбрать тип занятий
function selectLessonType(button) {
    // Убрать active у всех кнопок
    button.parentElement.querySelectorAll('.btn-toggle').forEach(btn => {
        btn.classList.remove('active');
    });

    // Добавить active к выбранной
    button.classList.add('active');

    // Обновить скрытое поле
    document.getElementById('student-lesson-type').value = button.dataset.value;
}

// Выбрать тир
function selectTier(button) {
    // Убрать active у всех кнопок тира
    button.parentElement.querySelectorAll('.btn-tier').forEach(btn => {
        btn.classList.remove('active');
    });

    // Добавить active к выбранной
    button.classList.add('active');

    // Обновить скрытое поле
    document.getElementById('student-tier').value = button.dataset.tier;
}

// Переключить день недели
function toggleDay(button) {
    const day = button.dataset.day;
    const isActive = button.classList.contains('active');

    if (isActive) {
        // Удалить день из расписания
        button.classList.remove('active');
        delete schedule[day];
    } else {
        // Добавить день в расписание с одним уроком по умолчанию
        button.classList.add('active');
        schedule[day] = [
            { time: '15:00', teacher_id: '', room: 1 } // Первый урок с кабинетом
        ];
    }

    renderSchedule();
}

// Отрисовать список расписания
function renderSchedule() {
    const scheduleList = document.getElementById('schedule-list');
    scheduleList.innerHTML = '';

    const dayNames = {
        '1': 'Понедельник',
        '2': 'Вторник',
        '3': 'Среда',
        '4': 'Четверг',
        '5': 'Пятница',
        '6': 'Суббота',
        '7': 'Воскресенье'
    };

    // Отсортировать дни по порядку
    const sortedDays = Object.keys(schedule).sort((a, b) => parseInt(a) - parseInt(b));

    sortedDays.forEach(day => {
        const lessons = schedule[day];
        const dayName = dayNames[day];

        // Контейнер для дня
        const dayContainer = document.createElement('div');
        dayContainer.style.marginBottom = '20px';
        dayContainer.style.padding = '16px';
        dayContainer.style.backgroundColor = 'var(--md-surface-2)';
        dayContainer.style.borderRadius = '12px';

        // Заголовок дня с кнопкой удаления
        const dayHeader = document.createElement('div');
        dayHeader.style.display = 'flex';
        dayHeader.style.justifyContent = 'space-between';
        dayHeader.style.alignItems = 'center';
        dayHeader.style.marginBottom = '12px';
        dayHeader.innerHTML = `
            <div style="font-weight: 600; color: var(--md-primary); font-size: 1rem;">${dayName}</div>
            <button type="button" class="schedule-item-remove" onclick="removeScheduleDay(${day})" title="Удалить день" style="position: static;">
                <span class="material-icons" style="font-size: 18px;">close</span>
            </button>
        `;
        dayContainer.appendChild(dayHeader);

        // Список уроков для этого дня
        lessons.forEach((lesson, index) => {
            const lessonItem = document.createElement('div');
            lessonItem.className = 'schedule-item';
            lessonItem.style.marginBottom = index < lessons.length - 1 ? '8px' : '0';
            lessonItem.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px; flex: 1;">
                    <button type="button"
                            style="background: none; border: none; color: var(--md-primary); cursor: pointer; padding: 4px; display: flex; align-items: center;"
                            onclick="addLessonToDay(${day})"
                            title="Добавить ещё урок в этот день">
                        <span class="material-icons" style="font-size: 20px;">arrow_downward</span>
                    </button>
                    <input
                        type="time"
                        class="form-control"
                        value="${lesson.time}"
                        onchange="updateLessonTime(${day}, ${index}, this.value)"
                        style="width: 110px;"
                    >
                    <select
                        class="form-control"
                        onchange="updateLessonTeacher(${day}, ${index}, this.value)"
                        style="flex: 1; min-width: 180px;"
                    >
                        <option value="">Выберите преподавателя</option>
                        ${teachersData.map(t => `
                            <option value="${t.id}" ${lesson.teacher_id == t.id ? 'selected' : ''}>
                                ${t.name}
                            </option>
                        `).join('')}
                    </select>
                    <select
                        class="form-control"
                        onchange="updateLessonRoom(${day}, ${index}, this.value)"
                        style="width: 95px;"
                        title="Кабинет"
                    >
                        <option value="1" ${(lesson.room == 1 || !lesson.room) ? 'selected' : ''}>Каб 1</option>
                        <option value="2" ${lesson.room == 2 ? 'selected' : ''}>Каб 2</option>
                        <option value="3" ${lesson.room == 3 ? 'selected' : ''}>Каб 3</option>
                    </select>
                </div>
                ${lessons.length > 1 ? `
                    <button type="button" class="schedule-item-remove" onclick="removeLessonFromDay(${day}, ${index})" title="Удалить урок">
                        <span class="material-icons" style="font-size: 18px;">close</span>
                    </button>
                ` : ''}
            `;
            dayContainer.appendChild(lessonItem);
        });

        scheduleList.appendChild(dayContainer);
    });
}

// Обновить время урока
function updateLessonTime(day, index, time) {
    if (schedule[day] && schedule[day][index]) {
        schedule[day][index].time = time;
    }
}

// Обновить преподавателя урока
function updateLessonTeacher(day, index, teacherId) {
    if (schedule[day] && schedule[day][index]) {
        schedule[day][index].teacher_id = teacherId ? parseInt(teacherId) : '';
    }
}

// Обновить кабинет урока
function updateLessonRoom(day, index, room) {
    if (schedule[day] && schedule[day][index]) {
        schedule[day][index].room = room ? parseInt(room) : 1;
    }
}

// Добавить урок в день
function addLessonToDay(day) {
    if (!schedule[day]) {
        schedule[day] = [];
    }

    // Добавляем новый урок на час позже последнего
    const lastLesson = schedule[day][schedule[day].length - 1];
    const lastTime = lastLesson ? lastLesson.time : '15:00';
    const lastRoom = lastLesson ? lastLesson.room : 1;
    const [hours, minutes] = lastTime.split(':').map(Number);
    const newHours = (hours + 1) % 24;
    const newTime = String(newHours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');

    schedule[day].push({
        time: newTime,
        teacher_id: '',
        room: lastRoom // Используем тот же кабинет, что и в предыдущем уроке
    });

    renderSchedule();
}

// Удалить урок из дня
function removeLessonFromDay(day, index) {
    if (schedule[day]) {
        schedule[day].splice(index, 1);

        // Если уроков не осталось, удалить день
        if (schedule[day].length === 0) {
            delete schedule[day];
            document.querySelector(`.btn-day[data-day="${day}"]`).classList.remove('active');
        }

        renderSchedule();
    }
}

// Удалить день из расписания
function removeScheduleDay(day) {
    delete schedule[day];

    // Убрать active у кнопки
    document.querySelector(`.btn-day[data-day="${day}"]`).classList.remove('active');

    renderSchedule();
}

// Сохранить ученика
async function saveStudent(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = {
        name: formData.get('name'),
        // teacher_id больше не в форме, извлекается из расписания на бэкенде
        class: formData.get('class') || null,
        tier: formData.get('tier'),
        lesson_type: formData.get('lesson_type'),
        price_group: parseInt(formData.get('price_group')) || 5000,
        price_individual: parseInt(formData.get('price_individual')) || 1500,
        payment_type_group: formData.get('payment_type_group'),
        payment_type_individual: formData.get('payment_type_individual'),
        schedule: JSON.stringify(schedule),
        student_telegram: formData.get('student_telegram') || null,
        student_whatsapp: formData.get('student_whatsapp') || null,
        parent_name: formData.get('parent_name') || null,
        parent_telegram: formData.get('parent_telegram') || null,
        parent_whatsapp: formData.get('parent_whatsapp') || null,
        notes: formData.get('notes') || null
    };

    const studentId = document.getElementById('student-id').value;
    const action = studentId ? 'update' : 'add';

    if (studentId) {
        data.id = parseInt(studentId);
    }

    try {
        const response = await fetch(`/zarplata/api/students.php?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification(studentId ? 'Ученик успешно обновлен!' : 'Ученик успешно добавлен!', 'success');
            closeStudentModal();
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else {
            showNotification(result.error || 'Ошибка при сохранении ученика', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Ошибка при сохранении ученика', 'error');
    }
}

// Редактировать ученика
async function editStudent(id) {
    try {
        console.log('Fetching student data for ID:', id);
        const response = await fetch(`/zarplata/api/students.php?action=get&id=${id}`);

        console.log('Response status:', response.status);
        const result = await response.json();
        console.log('API result:', result);

        if (result.success) {
            const student = result.data;
            console.log('Student data:', student);
            currentStudentId = id;

            // Заполнить форму
            document.getElementById('student-id').value = student.id;
            document.getElementById('student-name').value = student.name || '';
            // Примечание: поле student-teacher удалено, преподаватель выбирается для каждого урока
            document.getElementById('student-class').value = student.class || '';
            document.getElementById('student-parent-name').value = student.parent_name || '';
            document.getElementById('student-telegram').value = student.student_telegram || '';
            document.getElementById('student-whatsapp').value = student.student_whatsapp || '';
            document.getElementById('parent-telegram').value = student.parent_telegram || '';
            document.getElementById('parent-whatsapp').value = student.parent_whatsapp || '';
            document.getElementById('student-notes').value = student.notes || '';
            document.getElementById('price-group').value = student.price_group || 5000;
            document.getElementById('price-individual').value = student.price_individual || 1500;
            document.getElementById('payment-type-group').value = student.payment_type_group || 'monthly';
            document.getElementById('payment-type-individual').value = student.payment_type_individual || 'per_lesson';

            // Тип занятий
            const lessonType = student.lesson_type || 'group';
            document.getElementById('student-lesson-type').value = lessonType;
            document.querySelectorAll('.btn-toggle').forEach(btn => {
                if (btn.dataset.value === lessonType) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            // Тир
            const tier = student.tier || 'C';
            document.getElementById('student-tier').value = tier;
            document.querySelectorAll('.btn-tier').forEach(btn => {
                if (btn.dataset.tier === tier) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            // Расписание
            schedule = {};
            if (student.schedule) {
                try {
                    console.log('Parsing schedule:', student.schedule);
                    schedule = JSON.parse(student.schedule);
                    console.log('Parsed schedule:', schedule);
                } catch (e) {
                    console.error('Error parsing schedule:', e, 'Raw schedule:', student.schedule);
                }
            }

            // Активировать кнопки дней
            document.querySelectorAll('.btn-day').forEach(btn => {
                const day = btn.dataset.day;
                if (schedule[day]) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            renderSchedule();

            // Открыть модалку
            document.getElementById('modal-title').textContent = 'Редактировать ученика';
            document.getElementById('student-modal').classList.add('active');
        } else {
            console.error('API returned error:', result.error);
            showNotification(result.error || 'Ошибка при загрузке ученика', 'error');
        }
    } catch (error) {
        console.error('Error in editStudent:', error);
        showNotification('Ошибка при загрузке ученика: ' + error.message, 'error');
    }
}

// Просмотр ученика
async function viewStudent(id) {
    try {
        const response = await fetch(`/zarplata/api/students.php?action=get&id=${id}`);
        const result = await response.json();

        if (result.success) {
            const student = result.data;
            currentStudentId = id;

            // Парсинг расписания
            let scheduleHTML = '<span style="color: var(--text-medium-emphasis);">Не указано</span>';
            if (student.schedule) {
                try {
                    const scheduleData = JSON.parse(student.schedule);
                    const dayNames = ['', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
                    const scheduleItems = [];

                    Object.keys(scheduleData).sort((a, b) => parseInt(a) - parseInt(b)).forEach(day => {
                        const dayName = dayNames[parseInt(day)];
                        const time = scheduleData[day];
                        scheduleItems.push(`<div><strong>${dayName}</strong>: ${time}</div>`);
                    });

                    if (scheduleItems.length > 0) {
                        scheduleHTML = scheduleItems.join('');
                    }
                } catch (e) {
                    console.error('Error parsing schedule:', e);
                }
            }

            // Определить цену и тип оплаты
            const lessonType = student.lesson_type || 'group';
            let price, paymentType, paymentLabel;

            if (lessonType === 'group') {
                price = student.price_group || 5000;
                paymentType = student.payment_type_group || 'monthly';
            } else {
                price = student.price_individual || 1500;
                paymentType = student.payment_type_individual || 'per_lesson';
            }

            paymentLabel = paymentType === 'monthly' ? 'за месяц' : 'за урок';

            const content = `
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">ФИО:</span>
                        <span class="info-value">${student.name}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Класс:</span>
                        <span class="info-value">${student.class ? student.class + ' класс' : '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Тип занятий:</span>
                        <span class="info-value">${lessonType === 'individual' ? 'Соло' : 'Группа'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Цена:</span>
                        <span class="info-value">${price.toLocaleString('ru-RU')} ₽ ${paymentLabel}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Расписание:</span>
                        <span class="info-value">${scheduleHTML}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Телефон ученика:</span>
                        <span class="info-value">${student.phone || '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Telegram ученика:</span>
                        <span class="info-value">${student.student_telegram ? '@' + student.student_telegram : '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">WhatsApp ученика:</span>
                        <span class="info-value">${student.student_whatsapp || '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Телефон родителя:</span>
                        <span class="info-value">${student.parent_phone || '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Telegram родителя:</span>
                        <span class="info-value">${student.parent_telegram ? '@' + student.parent_telegram : '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">WhatsApp родителя:</span>
                        <span class="info-value">${student.parent_whatsapp || '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Примечания:</span>
                        <span class="info-value">${student.notes || '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Статус:</span>
                        <span class="info-value">
                            ${student.active ? '<span class="badge badge-success">Активен</span>' : '<span class="badge badge-danger">Неактивен</span>'}
                        </span>
                    </div>
                </div>
            `;

            document.getElementById('view-student-content').innerHTML = content;
            document.getElementById('view-student-modal').classList.add('active');
        } else {
            showNotification(result.error || 'Ошибка при загрузке ученика', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Ошибка при загрузке ученика', 'error');
    }
}

// Закрыть модалку просмотра
function closeViewModal() {
    document.getElementById('view-student-modal').classList.remove('active');
    currentStudentId = null;
}

// Редактировать из просмотра
function editStudentFromView() {
    closeViewModal();
    if (currentStudentId) {
        editStudent(currentStudentId);
    }
}

// Переключить активность ученика
async function toggleStudentActive(id) {
    if (!confirm('Изменить статус ученика?')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/students.php?action=toggle_active', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Статус ученика изменен!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else {
            showNotification(result.error || 'Ошибка при изменении статуса', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Ошибка при изменении статуса', 'error');
    }
}

// Фильтрация
function toggleClassFilter(button) {
    if (button.dataset.class === 'all') {
        // Если нажата "Все", убрать active со всех кнопок (сброс фильтра)
        document.querySelectorAll('.class-filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
    } else {
        // Переключить выбранную кнопку
        button.classList.toggle('active');
    }

    updateVisibleStudents();
    saveFilters();
}

function toggleTypeFilter(button) {
    if (button.dataset.type === 'all') {
        // Если нажата "Все", убрать active со всех кнопок (сброс фильтра)
        document.querySelectorAll('.type-filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
    } else {
        // Переключить выбранную кнопку
        button.classList.toggle('active');
    }

    updateVisibleStudents();
    saveFilters();
}

function filterByName() {
    updateVisibleStudents();
    saveFilters();
}

function filterByTeacher() {
    updateVisibleStudents();
    saveFilters();
}

function updateVisibleStudents() {
    const activeClasses = Array.from(document.querySelectorAll('.class-filter-btn.active:not([data-class="all"])'))
        .map(btn => btn.dataset.class);
    const activeTypes = Array.from(document.querySelectorAll('.type-filter-btn.active:not([data-type="all"])'))
        .map(btn => btn.dataset.type);
    const searchQuery = document.getElementById('search-input').value.toLowerCase().trim();
    const teacherFilter = document.getElementById('teacher-filter')?.value || 'all';

    document.querySelectorAll('.student-row').forEach(row => {
        const studentClass = row.getAttribute('data-class');
        const studentType = row.getAttribute('data-type');
        const studentName = row.getAttribute('data-name');
        const studentTeacher = row.getAttribute('data-teacher-id');

        // Если нет активных фильтров - показываем всех
        const classMatch = activeClasses.length === 0 || activeClasses.includes(studentClass);
        const typeMatch = activeTypes.length === 0 || activeTypes.includes(studentType);
        const searchMatch = !searchQuery || studentName.includes(searchQuery);
        const teacherMatch = teacherFilter === 'all' || studentTeacher === teacherFilter;

        if (classMatch && typeMatch && searchMatch && teacherMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Показать уведомление
function showNotification(message, type = 'info') {
    // Простая реализация уведомлений
    alert(message);
}

// Вспомогательные функции для форматирования
function formatMoney(amount) {
    return parseInt(amount).toLocaleString('ru-RU') + ' ₽';
}

function formatPhone(phone) {
    return phone;
}

function formatTime(time) {
    return time.substring(0, 5);
}

function truncate(text, length) {
    if (text.length > length) {
        return text.substring(0, length) + '...';
    }
    return text;
}

// Закрыть модалку при клике вне её
window.onclick = function(event) {
    const studentModal = document.getElementById('student-modal');
    const viewModal = document.getElementById('view-student-modal');

    if (event.target === studentModal) {
        closeStudentModal();
    }
    if (event.target === viewModal) {
        closeViewModal();
    }
}

/**
 * Сохранить состояние фильтров в localStorage
 */
function saveFilters() {
    const activeClasses = Array.from(document.querySelectorAll('.class-filter-btn.active:not([data-class="all"])'))
        .map(btn => btn.dataset.class);
    const activeTypes = Array.from(document.querySelectorAll('.type-filter-btn.active:not([data-type="all"])'))
        .map(btn => btn.dataset.type);
    const searchQuery = document.getElementById('search-input')?.value || '';
    const teacherFilter = document.getElementById('teacher-filter')?.value || 'all';

    const filters = {
        classes: activeClasses,
        types: activeTypes,
        search: searchQuery,
        teacher: teacherFilter
    };

    localStorage.setItem('studentsFilters', JSON.stringify(filters));
}

/**
 * Восстановить состояние фильтров из localStorage
 */
function restoreFilters() {
    const savedFilters = localStorage.getItem('studentsFilters');

    if (!savedFilters) {
        // Если нет сохраненных фильтров, показываем всех студентов
        updateVisibleStudents();
        return;
    }

    try {
        const filters = JSON.parse(savedFilters);

        // Восстанавливаем классы
        if (filters.classes && filters.classes.length > 0) {
            document.querySelectorAll('.class-filter-btn').forEach(btn => {
                if (btn.dataset.class !== 'all' && filters.classes.includes(btn.dataset.class)) {
                    btn.classList.add('active');
                }
            });
        }

        // Восстанавливаем типы
        if (filters.types && filters.types.length > 0) {
            document.querySelectorAll('.type-filter-btn').forEach(btn => {
                if (btn.dataset.type !== 'all' && filters.types.includes(btn.dataset.type)) {
                    btn.classList.add('active');
                }
            });
        }

        // Восстанавливаем поиск
        if (filters.search) {
            const searchInput = document.getElementById('search-input');
            if (searchInput) {
                searchInput.value = filters.search;
            }
        }

        // Восстанавливаем преподавателя
        if (filters.teacher) {
            const teacherSelect = document.getElementById('teacher-filter');
            if (teacherSelect) {
                teacherSelect.value = filters.teacher;
            }
        }

        // Применяем фильтры
        updateVisibleStudents();
    } catch (e) {
        console.error('Failed to restore filters:', e);
        updateVisibleStudents();
    }
}

// Восстановить фильтры при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    restoreFilters();
});
