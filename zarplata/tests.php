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
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
