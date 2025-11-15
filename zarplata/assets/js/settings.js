/**
 * JavaScript для управления настройками
 */

// Сохранить настройки Telegram бота
async function saveBotSettings(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Конвертируем числа
    data.bot_check_interval = parseInt(data.bot_check_interval);
    data.attendance_delay = parseInt(data.attendance_delay);

    const saveBtn = document.getElementById('save-bot-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Сохранение...';

    try {
        const response = await fetch('/zarplata/api/settings.php?action=update_bot', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Настройки бота сохранены', 'success');
        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Error saving bot settings:', error);
        showNotification('Ошибка сохранения настроек', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>Сохранить настройки бота';
    }
}

// Сохранить финансовые настройки
async function saveFinancialSettings(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Конвертируем числа
    data.owner_share_percent = parseInt(data.owner_share_percent);

    const saveBtn = document.getElementById('save-financial-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Сохранение...';

    try {
        const response = await fetch('/zarplata/api/settings.php?action=update_financial', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Финансовые настройки сохранены', 'success');
        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Error saving financial settings:', error);
        showNotification('Ошибка сохранения настроек', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>Сохранить финансовые настройки';
    }
}

// Сохранить системные настройки
async function saveSystemSettings(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    const saveBtn = document.getElementById('save-system-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Сохранение...';

    try {
        const response = await fetch('/zarplata/api/settings.php?action=update_system', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Системные настройки сохранены', 'success');
        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Error saving system settings:', error);
        showNotification('Ошибка сохранения настроек', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>Сохранить системные настройки';
    }
}

// Изменить пароль
async function changePassword(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Проверяем совпадение паролей
    if (data.new_password !== data.confirm_password) {
        showNotification('Пароли не совпадают', 'error');
        return;
    }

    const saveBtn = document.getElementById('change-password-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Изменение...';

    try {
        const response = await fetch('/zarplata/api/settings.php?action=change_password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Пароль успешно изменён', 'success');
            form.reset();
        } else {
            showNotification(result.error || 'Ошибка изменения пароля', 'error');
        }
    } catch (error) {
        console.error('Error changing password:', error);
        showNotification('Ошибка изменения пароля', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>Изменить пароль';
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
