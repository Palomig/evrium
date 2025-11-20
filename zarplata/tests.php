<?php
/**
 * Страница тестирования системы
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

session_start();
requireAuth();

define('PAGE_TITLE', 'Тесты');
define('PAGE_SUBTITLE', 'Запуск тестовых скриптов и проверка функционала');
define('ACTIVE_PAGE', 'tests');

require_once __DIR__ . '/templates/header.php';

$user = getCurrentUser();
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
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
