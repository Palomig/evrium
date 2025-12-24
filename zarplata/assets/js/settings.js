/**
 * JavaScript для управления настройками
 */

// Проверить статус webhook
async function checkWebhookStatus() {
    const statusContent = document.getElementById('webhook-status-content');
    statusContent.innerHTML = '<p style="margin: 0; color: var(--text-medium-emphasis);">Проверка...</p>';

    try {
        const response = await fetch('/zarplata/api/telegram.php?action=check_webhook');
        const result = await response.json();

        if (result.success) {
            const info = result.data;
            let html = '<div style="font-size: 0.875rem;">';

            if (info.url) {
                html += `<p style="margin-bottom: 8px;"><strong>URL:</strong> ${escapeHtml(info.url)}</p>`;
            } else {
                html += `<p style="margin-bottom: 8px; color: var(--md-warning);">⚠️ Webhook не настроен</p>`;
            }

            if (info.pending_update_count !== undefined) {
                html += `<p style="margin-bottom: 8px;"><strong>Необработанных сообщений:</strong> ${info.pending_update_count}</p>`;
            }

            if (info.last_error_message) {
                html += `<p style="margin-bottom: 8px; color: var(--md-error);"><strong>Последняя ошибка:</strong><br>${escapeHtml(info.last_error_message)}</p>`;

                if (info.last_error_message.includes('SSL')) {
                    html += `<p style="margin-top: 12px; padding: 12px; background-color: var(--md-error-container); border-radius: 8px; font-size: 0.875rem;">
                        <strong>⚠️ Проблема с SSL сертификатом</strong><br>
                        Telegram не может подключиться к вашему серверу из-за проблем с SSL.<br><br>
                        <strong>Решение:</strong> Убедитесь, что ваш сервер использует действительный SSL сертификат (Let's Encrypt, Cloudflare и т.д.)
                    </p>`;
                }
            } else if (info.url) {
                html += `<p style="margin-bottom: 0; color: var(--md-success);">✅ Webhook работает нормально</p>`;
            }

            html += '</div>';
            statusContent.innerHTML = html;
        } else {
            statusContent.innerHTML = `<p style="margin: 0; color: var(--md-error);">${escapeHtml(result.error || 'Ошибка проверки')}</p>`;
        }
    } catch (error) {
        console.error('Error checking webhook:', error);
        statusContent.innerHTML = '<p style="margin: 0; color: var(--md-error);">Ошибка проверки статуса</p>';
    }
}

// Настроить webhook
async function setupWebhook() {
    const statusContent = document.getElementById('webhook-status-content');
    statusContent.innerHTML = '<p style="margin: 0; color: var(--text-medium-emphasis);">Настройка webhook...</p>';

    try {
        const response = await fetch('/zarplata/api/telegram.php?action=setup_webhook', {
            method: 'POST'
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Webhook настроен успешно!', 'success');
            // Автоматически проверяем статус после настройки
            setTimeout(() => checkWebhookStatus(), 1000);
        } else {
            showNotification(result.error || 'Ошибка настройки webhook', 'error');
            statusContent.innerHTML = `<p style="margin: 0; color: var(--md-error);">${escapeHtml(result.error)}</p>`;
        }
    } catch (error) {
        console.error('Error setting up webhook:', error);
        showNotification('Ошибка настройки webhook', 'error');
        statusContent.innerHTML = '<p style="margin: 0; color: var(--md-error);">Ошибка настройки webhook</p>';
    }
}

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
            showNotification('Настройки бота сохранены. Теперь настройте webhook.', 'success');
            // Автоматически проверяем статус после сохранения
            setTimeout(() => checkWebhookStatus(), 500);
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

// Сохранить настройки оплаты
async function savePaymentSettings(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    const saveBtn = document.getElementById('save-payment-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-icons rotating" style="margin-right: 8px; font-size: 18px;">sync</span>Сохранение...';

    try {
        const response = await fetch('/zarplata/api/settings.php?action=update_payment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Настройки оплаты сохранены', 'success');
        } else {
            showNotification(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Error saving payment settings:', error);
        showNotification('Ошибка сохранения настроек', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>Сохранить настройки оплаты';
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
