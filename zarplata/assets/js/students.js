/**
 * JavaScript для управления учениками
 */

let currentStudentId = null;
let currentViewStudentId = null;

// Фильтрация по классам
function toggleClassFilter(button) {
    const selectedClass = button.dataset.class;

    if (selectedClass === 'all') {
        // Если нажали "Все" - включаем все классы
        document.querySelectorAll('.class-filter-btn').forEach(btn => {
            btn.classList.add('active');
        });
    } else {
        // Если нажали конкретный класс - переключаем его
        button.classList.toggle('active');
        // Снимаем "Все"
        document.querySelector('.class-filter-btn[data-class="all"]').classList.remove('active');
    }

    updateVisibleStudents();
}

// Фильтрация по типу занятий
function toggleTypeFilter(button) {
    const selectedType = button.dataset.type;

    if (selectedType === 'all') {
        // Если нажали "Все" - включаем все типы
        document.querySelectorAll('.type-filter-btn').forEach(btn => {
            btn.classList.add('active');
        });
    } else {
        // Если нажали конкретный тип - переключаем его
        button.classList.toggle('active');
        // Снимаем "Все"
        document.querySelector('.type-filter-btn[data-type="all"]').classList.remove('active');
    }

    updateVisibleStudents();
}

// Поиск по имени
function filterByName() {
    updateVisibleStudents();
}

// Обновление видимости учеников
function updateVisibleStudents() {
    const activeClasses = Array.from(document.querySelectorAll('.class-filter-btn.active'))
        .map(btn => btn.dataset.class);

    const activeTypes = Array.from(document.querySelectorAll('.type-filter-btn.active'))
        .map(btn => btn.dataset.type);

    const searchQuery = document.getElementById('search-input').value.toLowerCase().trim();

    document.querySelectorAll('.student-row').forEach(row => {
        const studentClass = row.getAttribute('data-class');
        const studentType = row.getAttribute('data-type');
        const studentName = row.getAttribute('data-name');

        // Проверка класса
        const classMatch = activeClasses.includes('all') || activeClasses.includes(studentClass);

        // Проверка типа
        const typeMatch = activeTypes.includes('all') || activeTypes.includes(studentType);

        // Проверка поиска
        const searchMatch = !searchQuery || studentName.includes(searchQuery);

        // Показываем строку только если все фильтры совпадают
        if (classMatch && typeMatch && searchMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Автообновление цены при смене типа занятий
function updatePrice() {
    const lessonType = document.getElementById('student-lesson-type').value;
    const priceInput = document.getElementById('student-monthly-price');

    if (lessonType === 'individual') {
        priceInput.value = 1500;
    } else {
        priceInput.value = 5000;
    }
}

// Открыть модальное окно добавления ученика
function openStudentModal(studentId = null) {
    currentStudentId = studentId;
    const modal = document.getElementById('student-modal');
    const form = document.getElementById('student-form');
    const title = document.getElementById('modal-title');

    form.reset();

    if (studentId) {
        // Режим редактирования
        title.textContent = 'Редактировать ученика';
        loadStudentData(studentId);
    } else {
        // Режим создания
        title.textContent = 'Добавить ученика';
        document.getElementById('student-id').value = '';
        // Устанавливаем цену по умолчанию
        document.getElementById('student-monthly-price').value = 5000;
    }

    modal.classList.add('active');
}

// Загрузить данные ученика для редактирования
async function loadStudentData(studentId) {
    try {
        const response = await fetch(`/zarplata/api/students.php?action=get&id=${studentId}`);
        const result = await response.json();

        if (result.success) {
            const student = result.data;
            document.getElementById('student-id').value = student.id;
            document.getElementById('student-name').value = student.name || '';
            document.getElementById('student-class').value = student.class || '';
            document.getElementById('student-lesson-type').value = student.lesson_type || 'group';
            document.getElementById('student-lesson-day').value = student.lesson_day || '';
            document.getElementById('student-lesson-time').value = student.lesson_time || '';
            document.getElementById('student-monthly-price').value = student.monthly_price || 5000;
            document.getElementById('student-phone').value = student.phone || '';
            document.getElementById('student-parent-phone').value = student.parent_phone || '';
            document.getElementById('student-email').value = student.email || '';
            document.getElementById('student-notes').value = student.notes || '';
        } else {
            showNotification(result.error || 'Ошибка загрузки данных', 'error');
        }
    } catch (error) {
        console.error('Error loading student:', error);
        showNotification('Ошибка загрузки данных ученика', 'error');
    }
}

// Закрыть модальное окно
function closeStudentModal() {
    document.getElementById('student-modal').classList.remove('active');
    currentStudentId = null;
}

// Сохранить ученика
async function saveStudent(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    const studentId = document.getElementById('student-id').value;
    const action = studentId ? 'update' : 'add';

    if (studentId) {
        data.id = studentId;
    }

    const saveBtn = document.getElementById('save-student-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Сохранение...';

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
            showNotification(studentId ? 'Ученик обновлён' : 'Ученик добавлен', 'success');
            closeStudentModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Error saving student:', error);
        showNotification('Ошибка сохранения данных', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>Сохранить';
    }
}

// Редактировать ученика
function editStudent(studentId) {
    openStudentModal(studentId);
}

// Просмотр ученика
async function viewStudent(studentId) {
    currentViewStudentId = studentId;
    const modal = document.getElementById('view-student-modal');
    const content = document.getElementById('view-student-content');

    content.innerHTML = '<p style="text-align: center;">Загрузка...</p>';
    modal.classList.add('active');

    try {
        const response = await fetch(`/zarplata/api/students.php?action=get&id=${studentId}`);
        const result = await response.json();

        if (result.success) {
            const student = result.data;
            const days = ['', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
            const dayName = student.lesson_day ? days[student.lesson_day] : null;
            const lessonTypeLabel = student.lesson_type === 'individual' ? 'Индивидуальные' : 'Групповые';

            content.innerHTML = `
                <div style="display: grid; gap: 16px;">
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">ФИО:</strong><br>
                        <span style="font-size: 1.25rem;">${escapeHtml(student.name)}</span>
                    </div>
                    ${student.class ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Класс:</strong><br>
                        <span>${student.class} класс</span>
                    </div>
                    ` : ''}
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Тип занятий:</strong><br>
                        <span>${lessonTypeLabel}</span>
                    </div>
                    ${dayName && student.lesson_time ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Расписание:</strong><br>
                        <span>${dayName} в ${formatTime(student.lesson_time)}</span>
                    </div>
                    ` : ''}
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Цена за месяц:</strong><br>
                        <span style="font-size: 1.5rem; font-weight: 500;">${formatMoney(student.monthly_price || 0)}</span>
                    </div>
                    ${student.phone ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Телефон:</strong><br>
                        <span>${escapeHtml(student.phone)}</span>
                    </div>
                    ` : ''}
                    ${student.parent_phone ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Телефон родителя:</strong><br>
                        <span>${escapeHtml(student.parent_phone)}</span>
                    </div>
                    ` : ''}
                    ${student.email ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Email:</strong><br>
                        <span>${escapeHtml(student.email)}</span>
                    </div>
                    ` : ''}
                    ${student.notes ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Примечания:</strong><br>
                        <span>${escapeHtml(student.notes)}</span>
                    </div>
                    ` : ''}
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Статус:</strong><br>
                        <span class="badge badge-${student.active ? 'success' : 'danger'}">
                            ${student.active ? 'Активен' : 'Неактивен'}
                        </span>
                    </div>
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Дата добавления:</strong><br>
                        <span>${formatDate(student.created_at)}</span>
                    </div>
                </div>
            `;
        } else {
            content.innerHTML = `<p style="color: var(--md-error);">${escapeHtml(result.error || 'Ошибка загрузки')}</p>`;
        }
    } catch (error) {
        console.error('Error viewing student:', error);
        content.innerHTML = '<p style="color: var(--md-error);">Ошибка загрузки данных</p>';
    }
}

// Закрыть модальное окно просмотра
function closeViewModal() {
    document.getElementById('view-student-modal').classList.remove('active');
    currentViewStudentId = null;
}

// Редактировать из просмотра
function editStudentFromView() {
    closeViewModal();
    editStudent(currentViewStudentId);
}

// Переключить активность ученика
async function toggleStudentActive(studentId) {
    if (!confirm('Изменить статус ученика?')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/students.php?action=toggle_active', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: studentId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Статус ученика изменён', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка изменения статуса', 'error');
        }
    } catch (error) {
        console.error('Error toggling student status:', error);
        showNotification('Ошибка изменения статуса', 'error');
    }
}

// Утилиты
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ru-RU');
}

function formatTime(timeStr) {
    if (!timeStr) return '';
    const parts = timeStr.split(':');
    return `${parts[0]}:${parts[1]}`;
}

function formatMoney(amount) {
    if (!amount && amount !== 0) return '0 ₽';
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0
    }).format(amount);
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
