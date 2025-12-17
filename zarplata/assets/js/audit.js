/**
 * JavaScript для журнала аудита
 */

// Маппинг названий полей на русский
const fieldNames = {
    'amount': 'Сумма',
    'status': 'Статус',
    'payment_status': 'Статус выплаты',
    'payment_type': 'Тип выплаты',
    'notes': 'Примечание',
    'description': 'Описание',
    'name': 'Имя',
    'teacher_id': 'Преподаватель',
    'teacher_name': 'Преподаватель',
    'student_id': 'Ученик',
    'student_name': 'Ученик',
    'lesson_date': 'Дата урока',
    'time_start': 'Начало',
    'time_end': 'Окончание',
    'subject': 'Предмет',
    'lesson_type': 'Тип урока',
    'actual_students': 'Учеников присутствовало',
    'expected_students': 'Учеников ожидалось',
    'formula_id': 'Формула',
    'formula_name': 'Формула',
    'calculation_method': 'Метод расчёта',
    'created_at': 'Дата создания',
    'updated_at': 'Дата обновления',
    'active': 'Активен',
    'phone': 'Телефон',
    'email': 'Email',
    'class': 'Класс',
    'schedule': 'Расписание',
    'telegram_id': 'Telegram ID',
    'telegram_username': 'Telegram',
    'day_of_week': 'День недели',
    'room': 'Кабинет',
    'tier': 'Уровень',
    'grades': 'Классы',
    'students': 'Ученики'
};

// Маппинг значений на русский
const valueTranslations = {
    'pending': 'Ожидает',
    'approved': 'Одобрено',
    'paid': 'Выплачено',
    'cancelled': 'Отменено',
    'lesson': 'Урок',
    'bonus': 'Бонус',
    'penalty': 'Штраф',
    'adjustment': 'Корректировка',
    'group': 'Групповой',
    'individual': 'Индивидуальный',
    'scheduled': 'Запланирован',
    'completed': 'Завершён',
    'rescheduled': 'Перенесён',
    'true': 'Да',
    'false': 'Нет',
    '1': 'Да',
    '0': 'Нет'
};

// Просмотр деталей аудит-записи
async function viewAuditDetails(logId) {
    const modal = document.getElementById('audit-details-modal');
    const content = document.getElementById('audit-details-content');

    content.innerHTML = '<p style="text-align: center;">Загрузка...</p>';
    modal.classList.add('active');

    try {
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

    // Маппинг типов сущностей
    const entityTypes = {
        'payment': 'Выплата',
        'lesson': 'Урок',
        'lesson_template': 'Шаблон урока',
        'lesson_schedule': 'Урок (расписание)',
        'teacher': 'Преподаватель',
        'student': 'Ученик',
        'formula': 'Формула',
        'template': 'Шаблон',
        'settings': 'Настройки',
        'user': 'Пользователь'
    };

    // Маппинг действий
    const actionNames = {
        'Изменение': 'Редактирование',
        'Одобрение': 'Одобрение выплаты',
        'attendance_query_sent': 'Отправка опроса посещаемости',
        'attendance_marked': 'Отметка посещаемости',
        'payment_created': 'Создание выплаты',
        'payment_updated': 'Изменение выплаты',
        'lesson_created': 'Создание урока',
        'lesson_deleted': 'Удаление урока'
    };

    let html = `
        <div style="display: grid; gap: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--md-outline);">
                <div>
                    <span style="color: var(--text-medium-emphasis); font-size: 0.85em;">Дата и время</span><br>
                    <strong>${formatDateTime(log.created_at)}</strong>
                </div>
                <div>
                    <span style="color: var(--text-medium-emphasis); font-size: 0.85em;">Пользователь</span><br>
                    <strong>${escapeHtml(log.user_name || 'Система')}</strong>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <span style="color: var(--text-medium-emphasis); font-size: 0.85em;">Действие</span><br>
                    <strong>${escapeHtml(actionNames[log.action] || log.action)}</strong>
                </div>
                <div>
                    <span style="color: var(--text-medium-emphasis); font-size: 0.85em;">Тип</span><br>
                    <strong>${escapeHtml(entityTypes[log.entity_type] || log.entity_type)}</strong>
                    ${log.entity_id ? ` <span style="color: var(--text-disabled);">#${log.entity_id}</span>` : ''}
                </div>
            </div>
    `;

    // Описание
    if (log.description) {
        html += `
            <div>
                <span style="color: var(--text-medium-emphasis); font-size: 0.85em;">Описание</span><br>
                <span>${escapeHtml(log.description)}</span>
            </div>
        `;
    }

    // Примечание
    if (log.notes) {
        html += `
            <div>
                <span style="color: var(--text-medium-emphasis); font-size: 0.85em;">Примечание</span><br>
                <span>${escapeHtml(log.notes)}</span>
            </div>
        `;
    }

    // Парсим old_value и new_value
    let oldValues = null;
    let newValues = null;

    try {
        if (log.old_value) oldValues = JSON.parse(log.old_value);
    } catch (e) {
        oldValues = log.old_value;
    }

    try {
        if (log.new_value) newValues = JSON.parse(log.new_value);
    } catch (e) {
        newValues = log.new_value;
    }

    // Показываем изменения
    if (oldValues || newValues) {
        html += `<div style="margin-top: 8px;">`;

        // Если это удаление (есть только old_values)
        if (oldValues && !newValues) {
            html += `
                <div style="background: rgba(244, 67, 54, 0.1); border-left: 3px solid var(--md-error); padding: 12px 16px; border-radius: 0 8px 8px 0;">
                    <strong style="color: var(--md-error);">🗑 Удалённые данные:</strong>
                    ${renderValues(oldValues)}
                </div>
            `;
        }
        // Если это создание (есть только new_values)
        else if (!oldValues && newValues) {
            html += `
                <div style="background: rgba(76, 175, 80, 0.1); border-left: 3px solid var(--md-success); padding: 12px 16px; border-radius: 0 8px 8px 0;">
                    <strong style="color: var(--md-success);">✓ Данные:</strong>
                    ${renderValues(newValues)}
                </div>
            `;
        }
        // Если это изменение (есть и old и new)
        else if (oldValues && newValues) {
            html += renderChanges(oldValues, newValues);
        }

        html += `</div>`;
    } else {
        // Нет данных об изменениях
        html += `
            <div style="padding: 16px; background: var(--md-surface-3); border-radius: 8px; text-align: center; color: var(--text-medium-emphasis);">
                Подробная информация не сохранена
            </div>
        `;
    }

    html += `</div>`;

    content.innerHTML = html;
}

// Отрисовать значения как список
function renderValues(values) {
    if (typeof values === 'string') {
        return `<div style="margin-top: 8px;">${escapeHtml(values)}</div>`;
    }

    let html = '<div style="margin-top: 12px; display: grid; gap: 8px;">';

    for (const [key, value] of Object.entries(values)) {
        const fieldName = fieldNames[key] || key;
        const displayValue = formatValue(value);

        html += `
            <div style="display: grid; grid-template-columns: 140px 1fr; gap: 8px;">
                <span style="color: var(--text-medium-emphasis);">${escapeHtml(fieldName)}:</span>
                <span>${displayValue}</span>
            </div>
        `;
    }

    html += '</div>';
    return html;
}

// Отрисовать изменения (было → стало)
function renderChanges(oldValues, newValues) {
    let html = `
        <div style="background: var(--md-surface-3); border-radius: 8px; overflow: hidden;">
            <div style="display: grid; grid-template-columns: 140px 1fr 1fr; gap: 8px; padding: 12px 16px; background: var(--md-surface-2); font-weight: 500;">
                <span>Поле</span>
                <span style="color: var(--md-error);">Было</span>
                <span style="color: var(--md-success);">Стало</span>
            </div>
    `;

    // Собираем все ключи
    const allKeys = new Set([
        ...Object.keys(oldValues || {}),
        ...Object.keys(newValues || {})
    ]);

    let hasChanges = false;

    for (const key of allKeys) {
        const oldVal = oldValues ? oldValues[key] : undefined;
        const newVal = newValues ? newValues[key] : undefined;

        // Показываем только изменённые поля
        if (JSON.stringify(oldVal) !== JSON.stringify(newVal)) {
            hasChanges = true;
            const fieldName = fieldNames[key] || key;

            html += `
                <div style="display: grid; grid-template-columns: 140px 1fr 1fr; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--md-outline);">
                    <span style="color: var(--text-medium-emphasis);">${escapeHtml(fieldName)}</span>
                    <span style="color: var(--md-error); word-break: break-word;">${oldVal !== undefined ? formatValue(oldVal) : '—'}</span>
                    <span style="color: var(--md-success); word-break: break-word;">${newVal !== undefined ? formatValue(newVal) : '—'}</span>
                </div>
            `;
        }
    }

    if (!hasChanges) {
        html += `
            <div style="padding: 16px; text-align: center; color: var(--text-medium-emphasis);">
                Изменений не обнаружено
            </div>
        `;
    }

    html += '</div>';
    return html;
}

// Форматирование значения для отображения
function formatValue(value) {
    if (value === null || value === undefined) return '—';
    if (value === '') return '<span style="color: var(--text-disabled);">(пусто)</span>';

    // Булевы значения
    if (value === true) return 'Да';
    if (value === false) return 'Нет';

    // Массивы
    if (Array.isArray(value)) {
        if (value.length === 0) return '<span style="color: var(--text-disabled);">(пусто)</span>';
        return escapeHtml(value.join(', '));
    }

    // Объекты
    if (typeof value === 'object') {
        return `<pre style="margin: 0; font-size: 0.85em;">${escapeHtml(JSON.stringify(value, null, 2))}</pre>`;
    }

    // Строки - проверяем на известные значения
    const strValue = String(value);
    if (valueTranslations[strValue]) {
        return valueTranslations[strValue];
    }

    // Проверяем на денежные значения
    if (/^\d+$/.test(strValue) && parseInt(strValue) > 100) {
        return `${parseInt(strValue).toLocaleString('ru-RU')} ₽`;
    }

    return escapeHtml(strValue);
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
