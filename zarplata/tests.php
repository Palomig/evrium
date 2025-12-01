<?php
/**
 * Страница тестирования системы
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

define('PAGE_TITLE', 'Тесты');
define('PAGE_SUBTITLE', 'Запуск тестовых скриптов и проверка функционала');
define('ACTIVE_PAGE', 'tests');

require_once __DIR__ . '/templates/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><?= PAGE_TITLE ?></h1>
    <p class="page-subtitle"><?= PAGE_SUBTITLE ?></p>
</div>

<!-- Тесты бота -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Тесты Telegram бота</h2>
    </div>
    <div style="padding: 24px;">
        <div class="test-buttons">
            <button class="btn btn-primary" onclick="runTest('bot_attendance_all')">
                <span class="material-icons">check_circle</span>
                Тест: Все пришли
            </button>
            <button class="btn btn-primary" onclick="runTest('bot_attendance_partial')">
                <span class="material-icons">how_to_reg</span>
                Тест: Не все пришли
            </button>
            <button class="btn btn-primary" onclick="runTest('bot_check_formulas')">
                <span class="material-icons">functions</span>
                Проверка формул
            </button>
            <button class="btn btn-primary" onclick="openSendTestLessonModal()">
                <span class="material-icons">send</span>
                Отправить тестовый урок
            </button>
        </div>
    </div>
</div>

<!-- Тесты расчётов -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Тесты расчётов</h2>
    </div>
    <div style="padding: 24px;">
        <div class="test-buttons">
            <button class="btn btn-primary" onclick="runTest('payment_calculation')">
                <span class="material-icons">calculate</span>
                Тест расчёта зарплаты
            </button>
            <button class="btn btn-primary" onclick="runTest('formula_validation')">
                <span class="material-icons">rule</span>
                Валидация формул
            </button>
        </div>
    </div>
</div>

<!-- Тесты базы данных -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Тесты базы данных</h2>
    </div>
    <div style="padding: 24px;">
        <div class="test-buttons">
            <button class="btn btn-primary" onclick="runTest('db_integrity')">
                <span class="material-icons">storage</span>
                Проверка целостности БД
            </button>
            <button class="btn btn-primary" onclick="runTest('db_teachers')">
                <span class="material-icons">person</span>
                Проверка преподавателей
            </button>
            <button class="btn btn-primary" onclick="runTest('db_students')">
                <span class="material-icons">groups</span>
                Проверка учеников
            </button>
        </div>
    </div>
</div>

<!-- Генерация уроков -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Генерация уроков из шаблонов</h2>
    </div>
    <div style="padding: 24px;">
        <div style="margin-bottom: 20px; padding: 16px; background: rgba(129, 140, 248, 0.1); border-radius: 8px; color: #818cf8;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <span class="material-icons">info</span>
                <strong>Генерация уроков</strong>
            </div>
            <div style="font-size: 0.875rem; line-height: 1.5;">
                Создает записи уроков (lessons_instance) на основе активных шаблонов расписания.
                <br>Выберите период для генерации уроков. Уже существующие уроки пропускаются.
            </div>
        </div>
        <div class="test-buttons">
            <button class="btn btn-primary" onclick="generateLessons('week')">
                <span class="material-icons">date_range</span>
                Сгенерировать на текущую неделю
            </button>
            <button class="btn btn-primary" onclick="generateLessons('month')">
                <span class="material-icons">calendar_month</span>
                Сгенерировать на текущий месяц
            </button>
            <button class="btn btn-primary" onclick="generateLessons('three_months')">
                <span class="material-icons">event_available</span>
                Сгенерировать на 3 месяца
            </button>
        </div>
        <div id="generation-result" style="margin-top: 16px; padding: 12px; border-radius: 8px; display: none;"></div>
    </div>
</div>

<!-- Миграция данных -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Миграция данных</h2>
    </div>
    <div style="padding: 24px;">
        <div style="margin-bottom: 20px; padding: 16px; background: rgba(251, 191, 36, 0.1); border-radius: 8px; color: #fbbf24;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <span class="material-icons">info</span>
                <strong>Обновление формата учеников</strong>
            </div>
            <div style="font-size: 0.875rem; line-height: 1.5;">
                Обновляет формат хранения учеников в расписании с "Имя" на "Имя (класс кл.)"
                <br>Решает проблему дублирования учеников с одинаковыми именами.
            </div>
        </div>
        <div class="test-buttons">
            <button class="btn btn-primary" onclick="migrateStudents()">
                <span class="material-icons">upgrade</span>
                Мигрировать учеников в новый формат
            </button>
        </div>
    </div>
</div>

<!-- Очистка базы данных -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title" style="color: var(--md-error);">⚠️ Очистка базы данных</h2>
    </div>
    <div style="padding: 24px;">
        <div style="margin-bottom: 20px; padding: 16px; background: rgba(207, 102, 121, 0.1); border-radius: 8px; color: var(--md-error);">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <span class="material-icons">warning</span>
                <strong>ВНИМАНИЕ!</strong>
            </div>
            <div style="font-size: 0.875rem; line-height: 1.5;">
                Эти операции необратимы! Все данные будут безвозвратно удалены из базы данных.
                <br>Используйте только для тестирования на развёрнутой системе.
            </div>
        </div>
        <div class="test-buttons">
            <button class="btn" style="background-color: var(--md-error); border-color: var(--md-error);" onclick="clearStudents()">
                <span class="material-icons">delete_forever</span>
                Удалить всех учеников
            </button>
            <button class="btn" style="background-color: var(--md-error); border-color: var(--md-error);" onclick="clearTeachers()">
                <span class="material-icons">delete_forever</span>
                Удалить всех преподавателей
            </button>
            <button class="btn" style="background-color: var(--md-error); border-color: var(--md-error);" onclick="clearPayments()">
                <span class="material-icons">delete_forever</span>
                Удалить все выплаты
            </button>
        </div>
    </div>
</div>

<!-- Логи тестов -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Логи выполнения</h2>
        <button class="btn btn-secondary" onclick="clearLogs()">
            <span class="material-icons">clear</span>
            Очистить
        </button>
    </div>
    <div style="padding: 24px;">
        <div id="test-logs" style="
            background-color: #1E1E1E;
            color: #D4D4D4;
            font-family: 'Courier New', monospace;
            padding: 16px;
            border-radius: 8px;
            min-height: 300px;
            max-height: 600px;
            overflow-y: auto;
            font-size: 0.875rem;
            line-height: 1.5;
        ">
            <div style="color: #6A9955;">// Логи появятся здесь после запуска тестов...</div>
        </div>
    </div>
</div>

<style>
    .test-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 16px;
    }

    .test-buttons .btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        justify-content: center;
    }

    #test-logs .log-info {
        color: #4FC3F7;
    }

    #test-logs .log-success {
        color: #81C784;
    }

    #test-logs .log-error {
        color: #E57373;
    }

    #test-logs .log-warning {
        color: #FFB74D;
    }

    #test-logs .log-time {
        color: #9E9E9E;
    }

    /* Модальное окно */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .modal-overlay.active {
        opacity: 1;
    }

    .modal-content {
        background-color: var(--md-surface);
        border-radius: 12px;
        box-shadow: var(--elevation-5);
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        transform: translateY(20px);
        transition: transform 0.3s ease;
    }

    .modal-overlay.active .modal-content {
        transform: translateY(0);
    }

    .modal-header {
        padding: 24px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 500;
        margin: 0;
    }

    .modal-close {
        background: none;
        border: none;
        color: var(--text-medium-emphasis);
        cursor: pointer;
        padding: 8px;
        border-radius: 50%;
        transition: all 0.2s;
    }

    .modal-close:hover {
        background-color: rgba(255, 255, 255, 0.08);
        color: var(--text-high-emphasis);
    }

    .modal-body {
        padding: 24px;
    }

    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.12);
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-high-emphasis);
    }

    .form-control {
        width: 100%;
        padding: 12px 16px;
        background-color: var(--md-surface-3);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        color: var(--text-high-emphasis);
        font-size: 1rem;
        transition: all 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--md-primary);
        background-color: var(--md-surface-4);
    }
</style>

<script>
const logsContainer = document.getElementById('test-logs');

function log(message, type = 'info') {
    const time = new Date().toLocaleTimeString('ru-RU');
    const className = `log-${type}`;
    const entry = document.createElement('div');
    entry.innerHTML = `<span class="log-time">[${time}]</span> <span class="${className}">${message}</span>`;
    logsContainer.appendChild(entry);
    logsContainer.scrollTop = logsContainer.scrollHeight;
}

function clearLogs() {
    logsContainer.innerHTML = '<div style="color: #6A9955;">// Логи очищены</div>';
}

async function runTest(testName) {
    log(`▶ Запуск теста: ${testName}`, 'info');

    try {
        const response = await fetch('/zarplata/api/tests.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ test: testName })
        });

        const result = await response.json();

        if (result.success) {
            log(`✓ Тест успешно выполнен`, 'success');

            // Выводим логи
            if (result.logs && result.logs.length > 0) {
                result.logs.forEach(logEntry => {
                    log(logEntry.message, logEntry.type || 'info');
                });
            }

            // Выводим результаты
            if (result.data) {
                log(`Результат: ${JSON.stringify(result.data, null, 2)}`, 'success');
            }
        } else {
            log(`✗ Ошибка: ${result.error}`, 'error');

            // Выводим логи даже при ошибке
            if (result.logs && result.logs.length > 0) {
                result.logs.forEach(logEntry => {
                    log(logEntry.message, logEntry.type || 'error');
                });
            }
        }
    } catch (error) {
        log(`✗ Ошибка выполнения: ${error.message}`, 'error');
    }

    log('─'.repeat(80), 'info');
}

// Модальное окно для отправки тестового урока
let testLessonModal = null;

async function openSendTestLessonModal() {
    log('▶ Загрузка списка преподавателей...', 'info');

    try {
        // Получаем список преподавателей с telegram_id
        const response = await fetch('/zarplata/api/tests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ test: 'bot_get_teachers' })
        });

        const result = await response.json();

        if (!result.success || !result.data || result.data.length === 0) {
            log('✗ Нет преподавателей с привязанным Telegram', 'error');
            return;
        }

        const teachers = result.data;
        log(`✓ Найдено ${teachers.length} преподавателей`, 'success');

        // Создаём модальное окно
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h2 class="modal-title">Отправить тестовый урок</h2>
                    <button class="modal-close" onclick="closeTestLessonModal()">
                        <span class="material-icons">close</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Преподаватель</label>
                        <select id="testTeacherId" class="form-control">
                            <option value="">Выберите преподавателя</option>
                            ${teachers.map(t => `
                                <option value="${t.id}">
                                    ${t.name} (ID: ${t.telegram_id})
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Тип теста</label>
                        <select id="testLessonType" class="form-control">
                            <option value="random">Случайный урок из расписания</option>
                            <option value="mock">Тестовый урок (фейковый)</option>
                        </select>
                    </div>
                    <div style="margin-top: 16px; padding: 12px; background: rgba(255, 152, 0, 0.1); border-radius: 8px; color: var(--md-warning);">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">info</span>
                        <span style="font-size: 0.875rem; margin-left: 8px;">
                            Преподавателю будет отправлено уведомление о посещаемости урока
                        </span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeTestLessonModal()">Отмена</button>
                    <button class="btn btn-primary" onclick="sendTestLesson()">
                        <span class="material-icons" style="margin-right: 8px; font-size: 18px;">send</span>
                        Отправить
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        testLessonModal = modal;

        // Анимация появления
        setTimeout(() => modal.classList.add('active'), 10);

    } catch (error) {
        log(`✗ Ошибка загрузки преподавателей: ${error.message}`, 'error');
    }
}

function closeTestLessonModal() {
    if (testLessonModal) {
        testLessonModal.classList.remove('active');
        setTimeout(() => {
            testLessonModal.remove();
            testLessonModal = null;
        }, 300);
    }
}

async function sendTestLesson() {
    const teacherId = document.getElementById('testTeacherId').value;
    const lessonType = document.getElementById('testLessonType').value;

    if (!teacherId) {
        log('✗ Выберите преподавателя', 'error');
        return;
    }

    log(`▶ Отправка тестового урока преподавателю ID ${teacherId}...`, 'info');

    try {
        const response = await fetch('/zarplata/api/tests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                test: 'bot_send_test_lesson',
                teacher_id: parseInt(teacherId),
                lesson_type: lessonType
            })
        });

        const result = await response.json();

        if (result.success) {
            log(`✓ Тестовое уведомление отправлено!`, 'success');

            // Выводим логи
            if (result.logs && result.logs.length > 0) {
                result.logs.forEach(logEntry => {
                    log(logEntry.message, logEntry.type || 'info');
                });
            }

            // Выводим результаты
            if (result.data) {
                log(`Результат: ${JSON.stringify(result.data, null, 2)}`, 'success');
            }

            closeTestLessonModal();
        } else {
            log(`✗ Ошибка: ${result.error}`, 'error');

            // Выводим логи даже при ошибке
            if (result.logs && result.logs.length > 0) {
                result.logs.forEach(logEntry => {
                    log(logEntry.message, logEntry.type || 'error');
                });
            }
        }

        log('─'.repeat(80), 'info');

    } catch (error) {
        log(`✗ Ошибка отправки: ${error.message}`, 'error');
    }
}

// Очистка учеников
async function clearStudents() {
    if (!confirm('⚠️ ВЫ УВЕРЕНЫ? Все ученики будут удалены из базы данных!')) {
        return;
    }

    if (!confirm('⚠️ ПОСЛЕДНЕЕ ПРЕДУПРЕЖДЕНИЕ! Это действие НЕОБРАТИМО! Удалить всех учеников?')) {
        return;
    }

    log('⚠️ Запуск удаления всех учеников...', 'warning');

    try {
        const response = await fetch('/zarplata/api/tests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ test: 'clear_students' })
        });

        const result = await response.json();

        if (result.success) {
            log(`✓ Удалено учеников: ${result.data.count}`, 'success');
            log('✓ База данных учеников очищена', 'success');
        } else {
            log(`✗ Ошибка: ${result.error}`, 'error');
        }

        log('─'.repeat(80), 'info');
    } catch (error) {
        log(`✗ Ошибка выполнения: ${error.message}`, 'error');
    }
}

// Очистка преподавателей
async function clearTeachers() {
    if (!confirm('⚠️ ВЫ УВЕРЕНЫ? Все преподаватели будут удалены из базы данных!')) {
        return;
    }

    if (!confirm('⚠️ ПОСЛЕДНЕЕ ПРЕДУПРЕЖДЕНИЕ! Это действие НЕОБРАТИМО! Удалить всех преподавателей?')) {
        return;
    }

    log('⚠️ Запуск удаления всех преподавателей...', 'warning');

    try {
        const response = await fetch('/zarplata/api/tests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ test: 'clear_teachers' })
        });

        const result = await response.json();

        if (result.success) {
            log(`✓ Удалено преподавателей: ${result.data.count}`, 'success');
            log('✓ База данных преподавателей очищена', 'success');
        } else {
            log(`✗ Ошибка: ${result.error}`, 'error');
        }

        log('─'.repeat(80), 'info');
    } catch (error) {
        log(`✗ Ошибка выполнения: ${error.message}`, 'error');
    }
}

// Очистка выплат
async function clearPayments() {
    if (!confirm('⚠️ ВЫ УВЕРЕНЫ? Все выплаты будут удалены из базы данных!')) {
        return;
    }

    if (!confirm('⚠️ ПОСЛЕДНЕЕ ПРЕДУПРЕЖДЕНИЕ! Это действие НЕОБРАТИМО! Удалить все выплаты?')) {
        return;
    }

    log('⚠️ Запуск удаления всех выплат...', 'warning');

    try {
        const response = await fetch('/zarplata/api/clear_all_payments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            log(`✓ Удалено выплат: ${result.data.deleted_payments}`, 'success');
            log(`✓ Удалено записей аудита: ${result.data.deleted_audit_logs}`, 'success');
            log('✓ База данных выплат очищена', 'success');
        } else {
            log(`✗ Ошибка: ${result.error}`, 'error');
        }

        log('─'.repeat(80), 'info');
    } catch (error) {
        log(`✗ Ошибка выполнения: ${error.message}`, 'error');
    }
}

// Миграция учеников в новый формат
async function migrateStudents() {
    if (!confirm('🔄 Запустить миграцию учеников в новый формат "Имя (класс кл.)"?')) {
        return;
    }

    log('🔄 Запуск миграции учеников...', 'info');

    try {
        const response = await fetch('/zarplata/api/migrate_students.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            log(`✓ Миграция завершена успешно!`, 'success');
            log(`  Обновлено шаблонов: ${result.data.updated}`, 'success');
            log(`  Пропущено (уже в новом формате): ${result.data.skipped}`, 'info');

            if (result.data.errors && result.data.errors.length > 0) {
                log(`⚠️ Предупреждения и ошибки:`, 'warning');
                result.data.errors.forEach(err => {
                    log(`  ${err}`, 'warning');
                });
            }

            if (result.data.details && result.data.details.length > 0) {
                log(`📝 Детали изменений:`, 'info');
                result.data.details.forEach(detail => {
                    log(`  ${detail}`, 'info');
                });
            }

            log('✓ Рекомендуется перезагрузить страницу расписания', 'success');
        } else {
            log(`✗ Ошибка: ${result.error}`, 'error');
        }

        log('─'.repeat(80), 'info');
    } catch (error) {
        log(`✗ Ошибка выполнения: ${error.message}`, 'error');
    }
}

// Генерация уроков из шаблонов
async function generateLessons(period) {
    const periodNames = {
        'week': 'текущую неделю',
        'month': 'текущий месяц',
        'three_months': '3 месяца'
    };

    if (!confirm(`🗓️ Сгенерировать уроки на ${periodNames[period]}?`)) {
        return;
    }

    const resultDiv = document.getElementById('generation-result');
    resultDiv.style.display = 'block';
    resultDiv.style.background = 'rgba(129, 140, 248, 0.1)';
    resultDiv.style.color = '#818cf8';
    resultDiv.innerHTML = '<span class="material-icons" style="vertical-align: middle;">hourglass_empty</span> Генерация уроков...';

    try {
        // Определяем количество недель
        let weeks;
        switch(period) {
            case 'week':
                weeks = 1;
                break;
            case 'month':
                weeks = 5; // примерно месяц
                break;
            case 'three_months':
                weeks = 13; // примерно 3 месяца
                break;
        }

        let totalCreated = 0;
        const today = new Date();

        // Генерируем уроки для каждой недели
        for (let i = 0; i < weeks; i++) {
            const weekDate = new Date(today);
            weekDate.setDate(today.getDate() + (i * 7));
            const dateStr = weekDate.toISOString().split('T')[0];

            const response = await fetch(`/zarplata/api/schedule.php?action=generate_week`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ date: dateStr })
            });

            const result = await response.json();

            if (result.success) {
                totalCreated += result.data.created || 0;
                log(`✓ Неделя ${i + 1}/${weeks}: создано ${result.data.created} уроков (${result.data.week_start})`, 'success');
            } else {
                log(`✗ Неделя ${i + 1}: ${result.error}`, 'error');
            }
        }

        resultDiv.style.background = 'rgba(16, 185, 129, 0.1)';
        resultDiv.style.color = '#10b981';
        resultDiv.innerHTML = `
            <span class="material-icons" style="vertical-align: middle;">check_circle</span>
            <strong>Готово!</strong> Создано уроков: ${totalCreated}
        `;

        log(`✓ Всего создано уроков: ${totalCreated}`, 'success');
        log('✓ Обновите страницу выплат для просмотра результатов', 'success');
        log('─'.repeat(80), 'info');

    } catch (error) {
        resultDiv.style.background = 'rgba(239, 68, 68, 0.1)';
        resultDiv.style.color = '#ef4444';
        resultDiv.innerHTML = `
            <span class="material-icons" style="vertical-align: middle;">error</span>
            Ошибка: ${error.message}
        `;
        log(`✗ Ошибка выполнения: ${error.message}`, 'error');
    }
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
