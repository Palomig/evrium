/**
 * JavaScript для управления выплатами
 */

let currentViewPaymentId = null;

// Переключение фильтра статусов
function toggleStatusFilter(button) {
    button.classList.toggle('active');
    updateVisiblePayments();
}

// Обновление видимости выплат
function updateVisiblePayments() {
    const activeStatuses = Array.from(document.querySelectorAll('.status-filter-btn.active'))
        .map(btn => btn.dataset.status);

    document.querySelectorAll('.payment-row').forEach(row => {
        const status = row.getAttribute('data-status');

        if (activeStatuses.length === 0 || activeStatuses.includes(status)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Открыть модальное окно добавления разовой выплаты
function openPaymentModal() {
    const modal = document.getElementById('payment-modal');
    const form = document.getElementById('payment-form');

    form.reset();
    document.getElementById('payment-date').value = new Date().toISOString().split('T')[0];

    modal.classList.add('active');
}

// Закрыть модальное окно добавления
function closePaymentModal() {
    document.getElementById('payment-modal').classList.remove('active');
}

// Сохранить разовую выплату
async function savePayment(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Конвертируем числа
    data.teacher_id = parseInt(data.teacher_id);
    data.amount = parseFloat(data.amount);

    const saveBtn = document.getElementById('save-payment-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Сохранение...';

    try {
        const response = await fetch('/zarplata/api/payments.php?action=add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Выплата добавлена', 'success');
            closePaymentModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Error saving payment:', error);
        showNotification('Ошибка сохранения данных', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>Сохранить';
    }
}

// Просмотр выплаты
async function viewPayment(paymentId) {
    currentViewPaymentId = paymentId;
    const modal = document.getElementById('view-payment-modal');
    const content = document.getElementById('view-payment-content');

    content.innerHTML = '<p style="text-align: center;">Загрузка...</p>';
    modal.classList.add('active');

    try {
        const response = await fetch(`/zarplata/api/payments.php?action=get&id=${paymentId}`);
        const result = await response.json();

        if (result.success) {
            const payment = result.data;
            const statusBadge = getPaymentStatusBadge(payment.status);
            const typeLabels = {
                'lesson': 'Урок',
                'bonus': 'Премия',
                'penalty': 'Штраф',
                'adjustment': 'Корректировка'
            };

            // Парсим список студентов если есть
            let studentsList = '';
            if (payment.students) {
                try {
                    const students = JSON.parse(payment.students);
                    if (Array.isArray(students) && students.length > 0) {
                        studentsList = students.join(', ');
                    }
                } catch (e) {
                    console.error('Error parsing students:', e);
                }
            }

            // Определяем тип урока
            const lessonTypeLabel = payment.lesson_type === 'group' ? 'Групповое' :
                                   payment.lesson_type === 'individual' ? 'Индивидуальное' : '';

            content.innerHTML = `
                <div style="display: grid; gap: 16px;">
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">ID:</strong><br>
                        <span style="font-size: 1.25rem;">#${payment.id}</span>
                    </div>
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Преподаватель:</strong><br>
                        <span>${escapeHtml(payment.teacher_name)}</span>
                    </div>
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Тип выплаты:</strong><br>
                        <span>${typeLabels[payment.payment_type] || payment.payment_type}</span>
                    </div>
                    ${payment.lesson_date ? `
                    <div style="background: var(--md-surface-variant); padding: 16px; border-radius: 8px; margin: 8px 0;">
                        <strong style="color: var(--text-medium-emphasis); font-size: 0.9rem;">📚 ИНФОРМАЦИЯ ОБ УРОКЕ</strong>
                        <div style="margin-top: 12px; display: grid; gap: 8px;">
                            <div>
                                <strong>Дата:</strong> ${formatDate(payment.lesson_date)}
                            </div>
                            <div>
                                <strong>Время:</strong> ${formatTime(payment.time_start)}${payment.time_end ? ' - ' + formatTime(payment.time_end) : ''}
                            </div>
                            ${payment.subject ? `
                            <div>
                                <strong>Предмет:</strong> ${escapeHtml(payment.subject)}
                            </div>
                            ` : ''}
                            ${lessonTypeLabel ? `
                            <div>
                                <strong>Тип урока:</strong> ${lessonTypeLabel}
                            </div>
                            ` : ''}
                            ${payment.room ? `
                            <div>
                                <strong>Кабинет:</strong> ${payment.room}
                            </div>
                            ` : ''}
                            ${payment.expected_students || payment.actual_students ? `
                            <div>
                                <strong>Количество учеников:</strong>
                                ${payment.actual_students ? payment.actual_students : payment.expected_students}
                                ${payment.expected_students && payment.actual_students !== payment.expected_students
                                    ? ` из ${payment.expected_students} (ожидалось)`
                                    : ''}
                            </div>
                            ` : ''}
                            ${studentsList ? `
                            <div>
                                <strong>Ученики:</strong><br>
                                <span style="color: var(--text-medium-emphasis);">${escapeHtml(studentsList)}</span>
                            </div>
                            ` : ''}
                            ${payment.calculation_method ? `
                            <div>
                                <strong>Метод расчета:</strong><br>
                                <span style="color: var(--text-medium-emphasis); font-size: 0.9rem;">${escapeHtml(payment.calculation_method)}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    ` : ''}
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Сумма:</strong><br>
                        <span style="font-size: 1.5rem; font-weight: 500;">${formatMoney(payment.amount)}</span>
                    </div>
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Статус:</strong><br>
                        <span class="badge badge-${statusBadge.class}">
                            <span class="material-icons" style="font-size: 14px;">${statusBadge.icon}</span>
                            ${statusBadge.text}
                        </span>
                    </div>
                    ${payment.payment_date ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Дата выплаты:</strong><br>
                        <span>${formatDate(payment.payment_date)}</span>
                    </div>
                    ` : ''}
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Создано:</strong><br>
                        <span>${formatDate(payment.created_at)}</span>
                    </div>
                    ${payment.comment ? `
                    <div>
                        <strong style="color: var(--text-medium-emphasis);">Комментарий:</strong><br>
                        <span>${escapeHtml(payment.comment)}</span>
                    </div>
                    ` : ''}
                </div>
            `;
        } else {
            content.innerHTML = `<p style="color: var(--md-error);">${escapeHtml(result.error || 'Ошибка загрузки')}</p>`;
        }
    } catch (error) {
        console.error('Error viewing payment:', error);
        content.innerHTML = '<p style="color: var(--md-error);">Ошибка загрузки данных</p>';
    }
}

// Закрыть модальное окно просмотра
function closeViewModal() {
    document.getElementById('view-payment-modal').classList.remove('active');
    currentViewPaymentId = null;
}

// Одобрить выплату
async function approvePayment(paymentId) {
    if (!confirm('Одобрить эту выплату?')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/payments.php?action=approve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: paymentId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Выплата одобрена', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка одобрения', 'error');
        }
    } catch (error) {
        console.error('Error approving payment:', error);
        showNotification('Ошибка одобрения выплаты', 'error');
    }
}

// Открыть модальное окно отметки как выплаченной
function openMarkPaidModal(paymentId) {
    const modal = document.getElementById('mark-paid-modal');
    document.getElementById('mark-paid-id').value = paymentId;
    document.getElementById('mark-paid-date').value = new Date().toISOString().split('T')[0];
    modal.classList.add('active');
}

// Закрыть модальное окно отметки
function closeMarkPaidModal() {
    document.getElementById('mark-paid-modal').classList.remove('active');
}

// Сохранить отметку как выплаченной
async function saveMarkPaid(event) {
    event.preventDefault();

    const paymentId = document.getElementById('mark-paid-id').value;
    const paymentDate = document.getElementById('mark-paid-date').value;

    const markPaidBtn = document.getElementById('mark-paid-btn');
    markPaidBtn.disabled = true;
    markPaidBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Обработка...';

    try {
        const response = await fetch('/zarplata/api/payments.php?action=mark_paid', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: parseInt(paymentId),
                payment_date: paymentDate
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Выплата отмечена как выплаченная', 'success');
            closeMarkPaidModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка отметки выплаты', 'error');
        }
    } catch (error) {
        console.error('Error marking payment as paid:', error);
        showNotification('Ошибка отметки выплаты', 'error');
    } finally {
        markPaidBtn.disabled = false;
        markPaidBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">check_circle</span>Отметить выплаченной';
    }
}

// Отменить выплату
async function cancelPayment(paymentId) {
    if (!confirm('Вы уверены, что хотите отменить эту выплату?')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/payments.php?action=cancel', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: paymentId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Выплата отменена', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка отмены выплаты', 'error');
        }
    } catch (error) {
        console.error('Error canceling payment:', error);
        showNotification('Ошибка отмены выплаты', 'error');
    }
}

// Открыть модальное окно редактирования выплаты
async function openEditModal(paymentId) {
    const modal = document.getElementById('edit-payment-modal');
    const form = document.getElementById('edit-payment-form');

    // Загружаем данные выплаты
    try {
        const response = await fetch(`/zarplata/api/payments.php?action=get&id=${paymentId}`);
        const result = await response.json();

        if (result.success) {
            const payment = result.data;

            document.getElementById('edit-payment-id').value = payment.id;
            document.getElementById('edit-payment-amount').value = payment.amount;
            document.getElementById('edit-payment-notes').value = payment.notes || '';

            // Парсим количество учеников из calculation_method
            let studentCount = '';
            if (payment.calculation_method) {
                const match = payment.calculation_method.match(/пришло (\d+)/iu) ||
                              payment.calculation_method.match(/все пришли \((\d+)/iu);
                if (match) {
                    studentCount = match[1];
                }
            }
            document.getElementById('edit-payment-students').value = studentCount;

            // Показываем информацию о выплате
            document.getElementById('edit-payment-info').innerHTML = `
                <strong>Выплата #${payment.id}</strong><br>
                <span style="color: var(--text-medium-emphasis);">
                    ${escapeHtml(payment.teacher_name)} • ${formatDate(payment.lesson_date || payment.created_at)}
                </span>
            `;

            modal.classList.add('active');
        } else {
            showNotification(result.error || 'Ошибка загрузки', 'error');
        }
    } catch (error) {
        console.error('Error loading payment:', error);
        showNotification('Ошибка загрузки данных', 'error');
    }
}

// Закрыть модальное окно редактирования
function closeEditModal() {
    document.getElementById('edit-payment-modal').classList.remove('active');
}

// Сохранить редактирование выплаты
async function saveEditPayment(event) {
    event.preventDefault();

    const paymentId = document.getElementById('edit-payment-id').value;
    const newAmount = document.getElementById('edit-payment-amount').value;
    const newStudentCount = document.getElementById('edit-payment-students').value;
    const notes = document.getElementById('edit-payment-notes').value;

    const saveBtn = document.getElementById('edit-payment-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Сохранение...';

    try {
        const data = { id: parseInt(paymentId) };

        if (newAmount) {
            data.amount = parseInt(newAmount);
        }
        if (newStudentCount) {
            data.student_count = parseInt(newStudentCount);
        }
        if (notes !== undefined) {
            data.notes = notes;
        }

        const response = await fetch('/zarplata/api/payments.php?action=update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Выплата обновлена', 'success');
            closeEditModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Error updating payment:', error);
        showNotification('Ошибка сохранения данных', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>Сохранить';
    }
}

// Удалить выплату
async function deletePayment(paymentId) {
    if (!confirm('Вы уверены, что хотите удалить эту выплату?')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/payments.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: paymentId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.data.message || 'Выплата удалена', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка удаления', 'error');
        }
    } catch (error) {
        console.error('Error deleting payment:', error);
        showNotification('Ошибка удаления выплаты', 'error');
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

function getPaymentStatusBadge(status) {
    const statuses = {
        'pending': { text: 'Ожидает', class: 'warning', icon: 'pending' },
        'approved': { text: 'Одобрено', class: 'info', icon: 'thumb_up' },
        'paid': { text: 'Выплачено', class: 'success', icon: 'check_circle' },
        'cancelled': { text: 'Отменено', class: 'danger', icon: 'cancel' }
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
