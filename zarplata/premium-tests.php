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

require_once __DIR__ . '/templates/header-premium.php';
?>

<div class="page-header">
    <h1 class="page-title"><?= PAGE_TITLE ?></h1>
    <p class="page-subtitle"><?= PAGE_SUBTITLE ?></p>
</div>

<!-- Генерация выплат за конкретную дату -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">🗓️ Генерация выплат за конкретную дату</h2>
    </div>
    <div style="padding: 24px;">
        <div style="margin-bottom: 20px; padding: 16px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; color: #10b981;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <span class="material-icons">info</span>
                <strong>Ручная генерация выплат</strong>
            </div>
            <div style="font-size: 0.875rem; line-height: 1.5;">
                Создаёт уроки и выплаты за выбранную дату на основе расписания учеников (students.schedule).
                <br>Используйте для восстановления данных когда бот не работал.
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 200px 1fr; gap: 16px; align-items: end; margin-bottom: 20px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label style="display: block; margin-bottom: 8px; color: var(--text-high-emphasis);">Дата</label>
                <input type="date" id="paymentDate" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="test-buttons" style="display: flex; gap: 12px;">
                <button class="btn btn-primary" onclick="generatePaymentsForDate()">
                    <span class="material-icons">payments</span>
                    Создать выплаты
                </button>
                <button class="btn btn-secondary" onclick="generatePaymentsForDate(true)">
                    <span class="material-icons">refresh</span>
                    Пересоздать (удалить старые)
                </button>
            </div>
        </div>

        <!-- Быстрые кнопки для дат -->
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;">
            <span style="color: var(--text-medium-emphasis); font-size: 0.875rem; margin-right: 8px;">Быстрый выбор:</span>
            <?php
            // Генерируем кнопки для дат с 6 по сегодня
            $today = new DateTime();
            $startDate = new DateTime('2025-12-06');
            while ($startDate <= $today):
                $dateStr = $startDate->format('Y-m-d');
                $dayNum = $startDate->format('d');
                $dayName = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'][(int)$startDate->format('w')];
            ?>
                <button class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;" onclick="document.getElementById('paymentDate').value='<?= $dateStr ?>'">
                    <?= $dayNum ?> (<?= $dayName ?>)
                </button>
            <?php
                $startDate->modify('+1 day');
            endwhile;
            ?>
        </div>

        <div id="payment-generation-result" style="margin-top: 16px; padding: 12px; border-radius: 8px; display: none;"></div>
    </div>
</div>

<!-- Диагностика и тесты Telegram бота -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">🤖 Telegram бот</h2>
    </div>
    <div style="padding: 24px;">
        <div style="margin-bottom: 20px; padding: 16px; background: rgba(99, 102, 241, 0.1); border-radius: 8px; color: #818cf8;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <span class="material-icons">info</span>
                <strong>Диагностика бота</strong>
            </div>
            <div style="font-size: 0.875rem; line-height: 1.5;">
                Проверьте работу бота: наличие токена, подключённых преподавателей и расписание на сегодня.
            </div>
        </div>

        <div class="test-buttons" style="margin-bottom: 24px;">
            <button class="btn btn-primary" onclick="runBotDiagnostic()">
                <span class="material-icons">bug_report</span>
                Диагностика бота
            </button>
            <button class="btn btn-primary" onclick="sendTestMessage()">
                <span class="material-icons">send</span>
                Отправить тестовое сообщение
            </button>
            <button class="btn btn-primary" onclick="runCronManually()">
                <span class="material-icons">schedule</span>
                Запустить cron вручную
            </button>
            <button class="btn btn-secondary" onclick="openSendTestLessonModal()">
                <span class="material-icons">quiz</span>
                Отправить опрос посещаемости
            </button>
        </div>

        <div id="bot-diagnostic-result" style="margin-top: 16px; display: none;"></div>
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

<!-- Исправление данных уроков -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Исправление данных уроков</h2>
    </div>
    <div style="padding: 24px;">
        <div style="margin-bottom: 20px; padding: 16px; background: rgba(251, 191, 36, 0.1); border-radius: 8px; color: #fbbf24;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <span class="material-icons">build</span>
                <strong>Исправление пустых данных</strong>
            </div>
            <div style="font-size: 0.875rem; line-height: 1.5;">
                Исправляет уроки, у которых отсутствуют формулы выплат или предметы.
                <br>1. Назначает формулы преподавателям (если отсутствуют)
                <br>2. Обновляет существующие уроки, копируя данные из шаблонов
            </div>
        </div>
        <div class="test-buttons">
            <button class="btn btn-primary" onclick="fixLessonsData()">
                <span class="material-icons">build_circle</span>
                Исправить данные уроков
            </button>
        </div>
        <div id="fix-result" style="margin-top: 16px; padding: 12px; border-radius: 8px; display: none;"></div>
    </div>
</div>

<!-- Синхронизация количества студентов -->
<div class="table-container">
    <div class="table-header">
        <h2 class="table-title">Синхронизация количества студентов</h2>
    </div>
    <div style="padding: 24px;">
        <div style="margin-bottom: 20px; padding: 16px; background: rgba(217, 171, 94, 0.1); border-radius: 8px; color: #d9ab5e;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <span class="material-icons">sync</span>
                <strong>Синхронизация expected_students</strong>
            </div>
            <div style="font-size: 0.875rem; line-height: 1.5;">
                Обновляет поле <code>expected_students</code> в шаблонах расписания на основе реального количества студентов в JSON-массиве <code>students</code>.
                <br>Это необходимо для корректного расчёта выплат на странице "Выплаты", особенно для запланированных уроков.
            </div>
        </div>
        <div class="test-buttons">
            <button class="btn btn-primary" onclick="syncStudentsCount()">
                <span class="material-icons">sync</span>
                Синхронизировать количество студентов
            </button>
        </div>
        <div id="sync-result" style="margin-top: 16px;"></div>
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

// ═══════════════════════════════════════════════════════════════════════════
// ДИАГНОСТИКА И ТЕСТЫ TELEGRAM БОТА
// ═══════════════════════════════════════════════════════════════════════════

async function runBotDiagnostic() {
    log('▶ Запуск диагностики бота...', 'info');

    const resultDiv = document.getElementById('bot-diagnostic-result');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div style="padding: 16px; background: rgba(129, 140, 248, 0.1); border-radius: 8px; color: #818cf8;"><span class="material-icons" style="vertical-align: middle;">hourglass_empty</span> Диагностика...</div>';

    try {
        const response = await fetch('/zarplata/api/bot_diagnostic.php?action=diagnostic');
        const result = await response.json();

        if (result.success) {
            const d = result.data;

            // Формируем отчёт
            let html = '<div style="padding: 16px; background: var(--bg-elevated); border-radius: 8px;">';

            // Токен
            const tokenIcon = d.token.status === 'ok' ? '✅' : '❌';
            html += `<div style="margin-bottom: 16px;"><strong>${tokenIcon} Токен бота:</strong> ${d.token.message}`;
            if (d.bot_info) {
                html += ` (${d.bot_info.username})`;
            }
            html += '</div>';

            // Преподаватели
            const teachersIcon = d.teachers.with_telegram > 0 ? '✅' : '❌';
            html += `<div style="margin-bottom: 16px;"><strong>${teachersIcon} Преподаватели:</strong> ${d.teachers.with_telegram} из ${d.teachers.total} с Telegram</div>`;

            if (d.teachers.list.length > 0) {
                html += '<div style="margin-left: 20px; margin-bottom: 16px; font-size: 0.875rem;">';
                d.teachers.list.forEach(t => {
                    const icon = t.has_telegram ? '✅' : '❌';
                    html += `${icon} ${t.name}`;
                    if (t.telegram_username) html += ` (@${t.telegram_username})`;
                    html += '<br>';
                });
                html += '</div>';
            }

            // Расписание
            const scheduleIcon = d.schedule.lessons_count > 0 ? '✅' : '⚠️';
            html += `<div style="margin-bottom: 16px;"><strong>${scheduleIcon} Расписание на сегодня (${d.schedule.day_name}):</strong> ${d.schedule.lessons_count} уроков</div>`;

            if (d.schedule.lessons.length > 0) {
                html += '<div style="margin-left: 20px; margin-bottom: 16px; font-size: 0.875rem;">';
                d.schedule.lessons.forEach(l => {
                    const icon = l.teacher_has_telegram ? '✅' : '❌';
                    html += `${l.time} - ${l.teacher_name} ${icon} (${l.student_count} уч.)<br>`;
                });
                html += '</div>';
            }

            // Отправленные сообщения
            const sentIcon = d.sent_today.count > 0 ? '✅' : '⚠️';
            html += `<div style="margin-bottom: 16px;"><strong>${sentIcon} Отправлено сообщений сегодня:</strong> ${d.sent_today.count}</div>`;

            // Окно cron
            html += `<div style="margin-bottom: 16px;"><strong>🕐 Текущее время:</strong> ${d.cron_window.current_time}</div>`;
            html += `<div style="margin-bottom: 16px;"><strong>📍 Окно cron:</strong> ${d.cron_window.window_from} - ${d.cron_window.window_to} (уроков в окне: ${d.cron_window.lessons_in_window})</div>`;

            // Ближайший урок
            if (d.next_lesson) {
                const mins = d.next_lesson.minutes_until;
                let timeText;
                if (mins > 0) {
                    timeText = `через ${mins} мин`;
                } else {
                    timeText = `${Math.abs(mins)} мин назад`;
                }
                html += `<div style="margin-bottom: 16px;"><strong>📍 Ближайший урок:</strong> ${d.next_lesson.time} (${timeText}) - ${d.next_lesson.teacher_name}</div>`;
                if (mins > 0) {
                    html += `<div style="color: #818cf8;">Сообщение будет отправлено примерно в ${d.next_lesson.message_will_be_sent_at}</div>`;
                }
            }

            html += '</div>';
            resultDiv.innerHTML = html;

            // Логируем
            log(`✅ Токен: ${d.token.message}`, d.token.status === 'ok' ? 'success' : 'error');
            log(`👥 Преподаватели: ${d.teachers.with_telegram}/${d.teachers.total} с Telegram`, d.teachers.with_telegram > 0 ? 'success' : 'warning');
            log(`📅 Уроки сегодня: ${d.schedule.lessons_count}`, 'info');
            log(`📨 Отправлено сегодня: ${d.sent_today.count}`, 'info');
            log(`🕐 Текущее время: ${d.cron_window.current_time}, окно: ${d.cron_window.window_from}-${d.cron_window.window_to}`, 'info');

        } else {
            resultDiv.innerHTML = `<div style="padding: 16px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; color: #ef4444;"><span class="material-icons" style="vertical-align: middle;">error</span> ${result.error}</div>`;
            log(`✗ Ошибка: ${result.error}`, 'error');
        }

    } catch (error) {
        resultDiv.innerHTML = `<div style="padding: 16px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; color: #ef4444;"><span class="material-icons" style="vertical-align: middle;">error</span> ${error.message}</div>`;
        log(`✗ Ошибка: ${error.message}`, 'error');
    }

    log('─'.repeat(80), 'info');
}

async function sendTestMessage() {
    log('▶ Отправка тестового сообщения...', 'info');

    try {
        const response = await fetch('/zarplata/api/bot_diagnostic.php?action=send_test', {
            method: 'POST'
        });
        const result = await response.json();

        if (result.success) {
            log(`✅ Сообщение отправлено: ${result.data.teacher} (chat_id: ${result.data.chat_id})`, 'success');
            alert(`✅ Сообщение отправлено преподавателю ${result.data.teacher}`);
        } else {
            log(`✗ Ошибка: ${result.error}`, 'error');
            alert(`❌ Ошибка: ${result.error}`);
        }
    } catch (error) {
        log(`✗ Ошибка: ${error.message}`, 'error');
        alert(`❌ Ошибка: ${error.message}`);
    }

    log('─'.repeat(80), 'info');
}

async function runCronManually() {
    if (!confirm('Отправить опросы посещаемости для всех прошедших уроков сегодня?')) {
        return;
    }

    log('▶ Запуск cron вручную...', 'info');

    const resultDiv = document.getElementById('bot-diagnostic-result');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div style="padding: 16px; background: rgba(129, 140, 248, 0.1); border-radius: 8px; color: #818cf8;"><span class="material-icons" style="vertical-align: middle;">hourglass_empty</span> Отправка сообщений...</div>';

    try {
        const response = await fetch('/zarplata/api/bot_diagnostic.php?action=run_cron', {
            method: 'POST'
        });
        const result = await response.json();

        if (result.success) {
            const d = result.data;
            resultDiv.innerHTML = `<div style="padding: 16px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; color: #10b981;"><span class="material-icons" style="vertical-align: middle;">check_circle</span> <strong>Готово!</strong> Отправлено: ${d.sent}, Пропущено: ${d.skipped}</div>`;

            log(`✅ Cron выполнен`, 'success');
            log(`   Всего уроков: ${d.total_lessons}`, 'info');
            log(`   Отправлено: ${d.sent}`, 'success');
            log(`   Пропущено: ${d.skipped}`, 'info');

            if (d.errors && d.errors.length > 0) {
                d.errors.forEach(err => log(`   ⚠️ ${err}`, 'warning'));
            }
        } else {
            resultDiv.innerHTML = `<div style="padding: 16px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; color: #ef4444;"><span class="material-icons" style="vertical-align: middle;">error</span> ${result.error}</div>`;
            log(`✗ Ошибка: ${result.error}`, 'error');
        }
    } catch (error) {
        resultDiv.innerHTML = `<div style="padding: 16px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; color: #ef4444;"><span class="material-icons" style="vertical-align: middle;">error</span> ${error.message}</div>`;
        log(`✗ Ошибка: ${error.message}`, 'error');
    }

    log('─'.repeat(80), 'info');
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

// Исправление данных уроков
async function fixLessonsData() {
    if (!confirm('🔧 Исправить данные уроков?\n\n1. Назначить формулы преподавателям\n2. Обновить уроки из шаблонов')) {
        return;
    }

    const resultDiv = document.getElementById('fix-result');
    resultDiv.style.display = 'block';
    resultDiv.style.background = 'rgba(129, 140, 248, 0.1)';
    resultDiv.style.color = '#818cf8';
    resultDiv.innerHTML = '<span class="material-icons" style="vertical-align: middle;">hourglass_empty</span> Исправление данных...';

    try {
        log('🔧 Запуск исправления данных уроков...', 'info');

        const response = await fetch('/zarplata/fix_lessons_data.php?action=full_fix', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            resultDiv.style.background = 'rgba(16, 185, 129, 0.1)';
            resultDiv.style.color = '#10b981';
            resultDiv.innerHTML = `
                <span class="material-icons" style="vertical-align: middle;">check_circle</span>
                <strong>Готово!</strong> ${result.message}
            `;

            log(`✓ ${result.message}`, 'success');
            log(`✓ Обновлено уроков: ${result.updated || 0}`, 'success');

            if (result.errors && result.errors.length > 0) {
                log(`⚠️ Ошибки:`, 'warning');
                result.errors.forEach(err => log(`  ${err}`, 'warning'));
            }

            log('✓ Обновите страницу выплат для просмотра результатов', 'success');
        } else {
            resultDiv.style.background = 'rgba(239, 68, 68, 0.1)';
            resultDiv.style.color = '#ef4444';
            resultDiv.innerHTML = `
                <span class="material-icons" style="vertical-align: middle;">error</span>
                Ошибка: ${result.error}
            `;
            log(`✗ Ошибка: ${result.error}`, 'error');
        }

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

// Генерация выплат за дату
async function generatePaymentsForDate(clearExisting = false) {
    const dateInput = document.getElementById('paymentDate');
    const date = dateInput.value;

    if (!date) {
        log('✗ Выберите дату', 'error');
        return;
    }

    const action = clearExisting ? 'Пересоздать' : 'Создать';
    if (!confirm(`${action} выплаты за ${date}?`)) {
        return;
    }

    const resultDiv = document.getElementById('payment-generation-result');
    resultDiv.style.display = 'block';
    resultDiv.style.background = 'rgba(129, 140, 248, 0.1)';
    resultDiv.style.color = '#818cf8';
    resultDiv.innerHTML = '<span class="material-icons" style="vertical-align: middle;">hourglass_empty</span> Генерация выплат...';

    log(`▶ ${action} выплаты за ${date}...`, 'info');

    try {
        const response = await fetch('/zarplata/api/generate_payments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                date: date,
                clear: clearExisting
            })
        });

        const result = await response.json();

        if (result.success) {
            resultDiv.style.background = 'rgba(16, 185, 129, 0.1)';
            resultDiv.style.color = '#10b981';
            resultDiv.innerHTML = `
                <span class="material-icons" style="vertical-align: middle;">check_circle</span>
                <strong>Готово!</strong> Создано: ${result.data.created}, Пропущено: ${result.data.skipped}
            `;

            log(`✓ Генерация завершена для ${date}`, 'success');
            log(`  Создано уроков/выплат: ${result.data.created}`, 'success');
            log(`  Пропущено: ${result.data.skipped}`, 'info');
            if (result.data.errors > 0) {
                log(`  Ошибок: ${result.data.errors}`, 'warning');
            }

            // Логи деталей
            if (result.data.details && result.data.details.length > 0) {
                result.data.details.forEach(detail => {
                    log(`  ${detail}`, detail.includes('✓') ? 'success' : (detail.includes('⚠') ? 'warning' : 'info'));
                });
            }
        } else {
            resultDiv.style.background = 'rgba(239, 68, 68, 0.1)';
            resultDiv.style.color = '#ef4444';
            resultDiv.innerHTML = `
                <span class="material-icons" style="vertical-align: middle;">error</span>
                Ошибка: ${result.error}
            `;
            log(`✗ Ошибка: ${result.error}`, 'error');
        }

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

// Синхронизация количества студентов
async function syncStudentsCount() {
    if (!confirm('🔄 Синхронизировать количество студентов?\n\nОбновит поле expected_students на основе реального количества в JSON.')) {
        return;
    }

    const resultDiv = document.getElementById('sync-result');
    resultDiv.style.display = 'block';
    resultDiv.style.padding = '12px';
    resultDiv.style.borderRadius = '8px';
    resultDiv.style.background = 'rgba(129, 140, 248, 0.1)';
    resultDiv.style.color = '#818cf8';
    resultDiv.innerHTML = '<span class="material-icons" style="vertical-align: middle;">hourglass_empty</span> Синхронизация...';

    try {
        log('🔄 Запуск синхронизации количества студентов...', 'info');

        const response = await fetch('/zarplata/api/sync_students.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();

        if (result.success) {
            resultDiv.style.background = 'rgba(16, 185, 129, 0.1)';
            resultDiv.style.color = '#10b981';
            resultDiv.innerHTML = `
                <span class="material-icons" style="vertical-align: middle;">check_circle</span>
                <strong>Готово!</strong> Всего: ${result.data.total}, Обновлено: ${result.data.updated}, Пропущено: ${result.data.skipped}
            `;

            log(`✓ Синхронизация завершена`, 'success');
            log(`  Всего шаблонов: ${result.data.total}`, 'info');
            log(`  Обновлено: ${result.data.updated}`, 'success');
            log(`  Без изменений: ${result.data.skipped}`, 'info');
            log(`  Ошибок: ${result.data.errors}`, result.data.errors > 0 ? 'warning' : 'info');

            // Выводим детали
            if (result.data.details && result.data.details.length > 0) {
                log(`📋 Детали изменений:`, 'info');
                result.data.details.forEach(detail => {
                    if (detail.updated) {
                        log(`  ID ${detail.id} (${detail.day} ${detail.time}): ${detail.expected} → ${detail.real} студентов`, 'success');
                    }
                });
            }

            log('✓ Рекомендуется обновить страницу "Выплаты"', 'success');
        } else {
            resultDiv.style.background = 'rgba(239, 68, 68, 0.1)';
            resultDiv.style.color = '#ef4444';
            resultDiv.innerHTML = `
                <span class="material-icons" style="vertical-align: middle;">error</span>
                Ошибка: ${result.error}
            `;
            log(`✗ Ошибка: ${result.error}`, 'error');
        }

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

<?php require_once __DIR__ . '/templates/footer-premium.php'; ?>
