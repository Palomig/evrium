/**
 * JavaScript для управления формулами оплаты
 */

let currentFormulaId = null;

// Открыть модальное окно создания формулы
function openFormulaModal(formulaId = null) {
    currentFormulaId = formulaId;
    const modal = document.getElementById('formula-modal');
    const form = document.getElementById('formula-form');
    const title = document.getElementById('modal-title');

    form.reset();

    if (formulaId) {
        // Режим редактирования
        title.textContent = 'Редактировать формулу';
        loadFormulaData(formulaId);
    } else {
        // Режим создания
        title.textContent = 'Создать формулу';
        document.getElementById('formula-id').value = '';
    }

    updateFormulaFields();
    modal.classList.add('active');
}

// Загрузить данные формулы для редактирования
async function loadFormulaData(formulaId) {
    try {
        const response = await fetch(`/zarplata/api/formulas.php?action=get&id=${formulaId}`);
        const result = await response.json();

        if (result.success) {
            const formula = result.data;
            document.getElementById('formula-id').value = formula.id;
            document.getElementById('formula-name').value = formula.name || '';
            document.getElementById('formula-description').value = formula.description || '';
            document.getElementById('formula-type').value = formula.type || '';

            // Загружаем поля в зависимости от типа
            if (formula.type === 'min_plus_per') {
                document.getElementById('min-payment').value = formula.min_payment || '';
                document.getElementById('per-student').value = formula.per_student || '';
                document.getElementById('threshold').value = formula.threshold || 1;
            } else if (formula.type === 'fixed') {
                document.getElementById('fixed-amount').value = formula.fixed_amount || '';
            } else if (formula.type === 'expression') {
                document.getElementById('expression').value = formula.expression || '';
            }

            updateFormulaFields();
        } else {
            showNotification(result.error || 'Ошибка загрузки данных', 'error');
        }
    } catch (error) {
        console.error('Error loading formula:', error);
        showNotification('Ошибка загрузки данных формулы', 'error');
    }
}

// Закрыть модальное окно
function closeFormulaModal() {
    document.getElementById('formula-modal').classList.remove('active');
    currentFormulaId = null;
}

// Обновить видимость полей в зависимости от типа формулы
function updateFormulaFields() {
    const type = document.getElementById('formula-type').value;

    // Скрываем все поля
    document.getElementById('min-plus-per-fields').style.display = 'none';
    document.getElementById('fixed-fields').style.display = 'none';
    document.getElementById('expression-fields').style.display = 'none';

    // Показываем нужные поля и делаем их required/optional
    if (type === 'min_plus_per') {
        document.getElementById('min-plus-per-fields').style.display = 'block';
        document.getElementById('min-payment').required = true;
        document.getElementById('per-student').required = true;
        document.getElementById('threshold').required = true;
        document.getElementById('fixed-amount').required = false;
        document.getElementById('expression').required = false;
    } else if (type === 'fixed') {
        document.getElementById('fixed-fields').style.display = 'block';
        document.getElementById('fixed-amount').required = true;
        document.getElementById('min-payment').required = false;
        document.getElementById('per-student').required = false;
        document.getElementById('threshold').required = false;
        document.getElementById('expression').required = false;
    } else if (type === 'expression') {
        document.getElementById('expression-fields').style.display = 'block';
        document.getElementById('expression').required = true;
        document.getElementById('min-payment').required = false;
        document.getElementById('per-student').required = false;
        document.getElementById('threshold').required = false;
        document.getElementById('fixed-amount').required = false;
    }
}

// Сохранить формулу
async function saveFormula(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    const formulaId = document.getElementById('formula-id').value;
    const action = formulaId ? 'update' : 'add';

    if (formulaId) {
        data.id = parseInt(formulaId);
    }

    // Конвертируем числа
    if (data.min_payment) data.min_payment = parseFloat(data.min_payment);
    if (data.per_student) data.per_student = parseFloat(data.per_student);
    if (data.threshold) data.threshold = parseInt(data.threshold);
    if (data.fixed_amount) data.fixed_amount = parseFloat(data.fixed_amount);

    const saveBtn = document.getElementById('save-formula-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Сохранение...';

    try {
        const response = await fetch(`/zarplata/api/formulas.php?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification(
                formulaId ? 'Формула обновлена' : 'Формула создана',
                'success'
            );
            closeFormulaModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Error saving formula:', error);
        showNotification('Ошибка сохранения данных', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>Сохранить';
    }
}

// Редактировать формулу
function editFormula(formulaId) {
    openFormulaModal(formulaId);
}

// Переключить активность формулы
async function toggleFormulaActive(formulaId) {
    try {
        const response = await fetch('/zarplata/api/formulas.php?action=toggle_active', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: formulaId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Статус формулы изменён', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка изменения статуса', 'error');
        }
    } catch (error) {
        console.error('Error toggling formula:', error);
        showNotification('Ошибка изменения статуса формулы', 'error');
    }
}

// Удалить формулу
async function deleteFormula(formulaId) {
    if (!confirm('Вы уверены, что хотите удалить эту формулу?\n\nЕсли формула используется в уроках, она будет деактивирована вместо удаления.')) {
        return;
    }

    try {
        const response = await fetch('/zarplata/api/formulas.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: formulaId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.data.message || 'Формула удалена', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showNotification(result.error || 'Ошибка удаления', 'error');
        }
    } catch (error) {
        console.error('Error deleting formula:', error);
        showNotification('Ошибка удаления формулы', 'error');
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
