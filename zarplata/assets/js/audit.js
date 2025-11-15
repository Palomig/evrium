/**
 * JavaScript для журнала аудита
 */

// Просмотр деталей аудит-записи
async function viewAuditDetails(logId) {
    const modal = document.getElementById('audit-details-modal');
    const content = document.getElementById('audit-details-content');

    content.innerHTML = '<p style="text-align: center;">Загрузка...</p>';
    modal.classList.add('active');

    try {
        // Получаем детали из БД (напрямую через SQL запрос на странице)
        const response = await fetch(`/zarplata/api/audit.php?action=get_details&id=${logId}`);
        const result = await response.json();

        if (result.success) {
            const log = result.data;
            renderAuditDetails(log);
        } else {
            content.innerHTML = `<p style="color: var(--md-error);">${escapeHtml(result.error || 'Ошибка загрузки')}</p>`;
        }
    } catch (error) {
        console.error('Error viewing audit details:', error);
        content.innerHTML = '<p style="color: var(--md-error);">Ошибка загрузки данных</p>';
    }
}

// Отрисовать детали
function renderAuditDetails(log) {
    const content = document.getElementById('audit-details-content');

    let html = `
        <div style="display: grid; gap: 16px;">
            <div>
                <strong style="color: var(--text-medium-emphasis);">Дата и время:</strong><br>
                <span>${formatDateTime(log.created_at)}</span>
            </div>
            <div>
                <strong style="color: var(--text-medium-emphasis);">Действие:</strong><br>
                <span>${escapeHtml(log.action)}</span>
            </div>
            <div>
                <strong style="color: var(--text-medium-emphasis);">Описание:</strong><br>
                <span>${escapeHtml(log.description || '—')}</span>
            </div>
    `;

    if (log.old_values) {
        try {
            const oldValues = JSON.parse(log.old_values);
            html += `
                <div>
                    <strong style="color: var(--text-medium-emphasis);">Старые значения:</strong><br>
                    <pre style="background-color: var(--md-surface-3); padding: 12px; border-radius: 8px; overflow-x: auto; margin-top: 8px;">${JSON.stringify(oldValues, null, 2)}</pre>
                </div>
            `;
        } catch (e) {
            // Ignore JSON parse errors
        }
    }

    if (log.new_values) {
        try {
            const newValues = JSON.parse(log.new_values);
            html += `
                <div>
                    <strong style="color: var(--text-medium-emphasis);">Новые значения:</strong><br>
                    <pre style="background-color: var(--md-surface-3); padding: 12px; border-radius: 8px; overflow-x: auto; margin-top: 8px;">${JSON.stringify(newValues, null, 2)}</pre>
                </div>
            `;
        } catch (e) {
            // Ignore JSON parse errors
        }
    }

    html += `</div>`;

    content.innerHTML = html;
}

// Закрыть модальное окно
function closeAuditDetails() {
    document.getElementById('audit-details-modal').classList.remove('active');
}

// Утилиты
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDateTime(dateTimeStr) {
    if (!dateTimeStr) return '';
    const date = new Date(dateTimeStr);
    return date.toLocaleString('ru-RU', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

// Закрытие модального окна по клику вне его
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});
