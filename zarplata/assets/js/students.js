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
        // Добавить день в расписание с временем по умолчанию
        button.classList.add('active');
        schedule[day] = '15:00'; // Время по умолчанию
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
        const time = schedule[day];
        const dayName = dayNames[day];

        const item = document.createElement('div');
        item.className = 'schedule-item';
        item.innerHTML = `
            <div class="schedule-item-day">${dayName}</div>
            <input
                type="time"
                class="form-control schedule-item-time"
                value="${time}"
                onchange="updateScheduleTime(${day}, this.value)"
            >
            <button type="button" class="schedule-item-remove" onclick="removeScheduleDay(${day})" title="Удалить">
                <span class="material-icons" style="font-size: 18px;">close</span>
            </button>
        `;
        scheduleList.appendChild(item);
    });
}

// Обновить время для дня
function updateScheduleTime(day, time) {
    schedule[day] = time;
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
        teacher_id: parseInt(formData.get('teacher_id')),
        class: formData.get('class') || null,
        tier: formData.get('tier'),
        lesson_type: formData.get('lesson_type'),
        price_group: parseInt(formData.get('price_group')) || 5000,
        price_individual: parseInt(formData.get('price_individual')) || 1500,
        payment_type_group: formData.get('payment_type_group'),
        payment_type_individual: formData.get('payment_type_individual'),
        schedule: JSON.stringify(schedule),
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
        const response = await fetch(`/zarplata/api/students.php?action=get&id=${id}`);
        const result = await response.json();

        if (result.success) {
            const student = result.data;
            currentStudentId = id;

            // Заполнить форму
            document.getElementById('student-id').value = student.id;
            document.getElementById('student-name').value = student.name || '';
            document.getElementById('student-teacher').value = student.teacher_id || '';
            document.getElementById('student-class').value = student.class || '';
            document.getElementById('student-parent-name').value = student.parent_name || '';
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
                    schedule = JSON.parse(student.schedule);
                } catch (e) {
                    console.error('Error parsing schedule:', e);
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
            showNotification(result.error || 'Ошибка при загрузке ученика', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Ошибка при загрузке ученика', 'error');
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
        // Если нажата "Все", активировать все кнопки
        document.querySelectorAll('.class-filter-btn').forEach(btn => {
            btn.classList.add('active');
        });
    } else {
        // Иначе убрать "Все" и переключить выбранную
        const allBtn = document.querySelector('.class-filter-btn[data-class="all"]');
        allBtn.classList.remove('active');
        button.classList.toggle('active');

        // Если все остальные активны, активировать "Все"
        const otherBtns = Array.from(document.querySelectorAll('.class-filter-btn:not([data-class="all"])'));
        const allActive = otherBtns.every(btn => btn.classList.contains('active'));
        if (allActive) {
            allBtn.classList.add('active');
        }
    }

    updateVisibleStudents();
}

function toggleTypeFilter(button) {
    if (button.dataset.type === 'all') {
        // Если нажата "Все", активировать все кнопки
        document.querySelectorAll('.type-filter-btn').forEach(btn => {
            btn.classList.add('active');
        });
    } else {
        // Иначе убрать "Все" и переключить выбранную
        const allBtn = document.querySelector('.type-filter-btn[data-type="all"]');
        allBtn.classList.remove('active');
        button.classList.toggle('active');

        // Если все остальные активны, активировать "Все"
        const otherBtns = Array.from(document.querySelectorAll('.type-filter-btn:not([data-type="all"])'));
        const allActive = otherBtns.every(btn => btn.classList.contains('active'));
        if (allActive) {
            allBtn.classList.add('active');
        }
    }

    updateVisibleStudents();
}

function filterByName() {
    updateVisibleStudents();
}

function updateVisibleStudents() {
    const activeClasses = Array.from(document.querySelectorAll('.class-filter-btn.active:not([data-class="all"])'))
        .map(btn => btn.dataset.class);
    const activeTypes = Array.from(document.querySelectorAll('.type-filter-btn.active:not([data-type="all"])'))
        .map(btn => btn.dataset.type);
    const searchQuery = document.getElementById('search-input').value.toLowerCase().trim();

    const allClassesActive = document.querySelector('.class-filter-btn[data-class="all"]').classList.contains('active');
    const allTypesActive = document.querySelector('.type-filter-btn[data-type="all"]').classList.contains('active');

    document.querySelectorAll('.student-row').forEach(row => {
        const studentClass = row.getAttribute('data-class');
        const studentType = row.getAttribute('data-type');
        const studentName = row.getAttribute('data-name');

        const classMatch = allClassesActive || activeClasses.includes(studentClass);
        const typeMatch = allTypesActive || activeTypes.includes(studentType);
        const searchMatch = !searchQuery || studentName.includes(searchQuery);

        if (classMatch && typeMatch && searchMatch) {
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
