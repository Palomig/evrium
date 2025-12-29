<?php
/**
 * Automate Setup Page
 * Инструкция по настройке приложения Automate для автоматического учета платежей
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requireAuth();
$user = getCurrentUser();

// Получаем или генерируем токен
$token = getSetting('automate_api_token', '');
if (empty($token)) {
    $token = bin2hex(random_bytes(16));
    setSetting('automate_api_token', $token);
}

// Формируем URL для webhook
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$webhookUrl = $protocol . '://' . $host . '/zarplata/api/incoming_payments.php?action=webhook&token=' . $token;

define('PAGE_TITLE', 'Настройка Automate');
define('ACTIVE_PAGE', 'settings');
define('SHOW_BOTTOM_NAV', false);

require_once __DIR__ . '/templates/header.php';
?>

<style>
.setup-page {
    padding: 16px;
    max-width: 600px;
    margin: 0 auto;
}

.setup-section {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    margin-bottom: 16px;
    overflow: hidden;
}

.setup-section-header {
    padding: 16px;
    background: var(--bg-elevated);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
}

.setup-section-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--accent);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}

.setup-section-title {
    font-size: 16px;
    font-weight: 600;
}

.setup-section-body {
    padding: 16px;
}

.setup-section-text {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 12px;
}

.setup-section-text:last-child {
    margin-bottom: 0;
}

.token-box {
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 12px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    word-break: break-all;
    margin: 12px 0;
    position: relative;
}

.token-label {
    font-size: 10px;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-bottom: 6px;
}

.token-value {
    color: var(--accent);
}

.copy-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    padding: 6px 10px;
    background: var(--accent);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 11px;
    cursor: pointer;
}

.copy-btn:active {
    opacity: 0.8;
}

.store-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.15s ease;
}

.store-btn:active {
    background: var(--accent-dim);
    border-color: var(--accent);
}

.store-btn img {
    width: 40px;
    height: 40px;
    border-radius: 8px;
}

.store-btn-text {
    flex: 1;
}

.store-btn-title {
    font-weight: 600;
    margin-bottom: 2px;
}

.store-btn-subtitle {
    font-size: 12px;
    color: var(--text-muted);
}

.warning-box {
    background: var(--status-orange-dim);
    border: 1px solid var(--status-orange);
    border-radius: 10px;
    padding: 14px;
    margin-top: 12px;
}

.warning-box-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--status-orange);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.warning-box-title svg {
    width: 18px;
    height: 18px;
}

.warning-box-text {
    font-size: 12px;
    color: var(--text-secondary);
    line-height: 1.5;
}

.code-block {
    background: #1a1a2e;
    border-radius: 8px;
    padding: 14px;
    margin: 12px 0;
    overflow-x: auto;
}

.code-block pre {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: #e0e0e0;
    margin: 0;
    white-space: pre-wrap;
    word-break: break-all;
}

.success-card {
    background: linear-gradient(135deg, var(--status-green-dim), var(--bg-card));
    border: 1px solid var(--status-green);
    border-radius: 14px;
    padding: 20px;
    text-align: center;
    margin-bottom: 16px;
}

.success-card svg {
    width: 48px;
    height: 48px;
    color: var(--status-green);
    margin-bottom: 12px;
}

.success-card-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 6px;
}

.success-card-text {
    font-size: 13px;
    color: var(--text-secondary);
}

.action-btn {
    display: block;
    width: 100%;
    padding: 14px;
    background: var(--accent);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
    margin-top: 12px;
}

.action-btn:active {
    opacity: 0.9;
}

.action-btn.secondary {
    background: var(--bg-elevated);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.step-image {
    border-radius: 8px;
    border: 1px solid var(--border);
    max-width: 100%;
    margin: 12px 0;
}
</style>

<div class="setup-page">
    <!-- Header -->
    <div class="success-card">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        <div class="success-card-title">Автоматический учет оплат</div>
        <div class="success-card-text">Следуйте инструкции для настройки автоматического приема уведомлений от Сбербанка</div>
    </div>

    <!-- Step 1: Install Automate -->
    <div class="setup-section">
        <div class="setup-section-header">
            <div class="setup-section-number">1</div>
            <div class="setup-section-title">Установите Automate</div>
        </div>
        <div class="setup-section-body">
            <div class="setup-section-text">
                Automate - бесплатное приложение для автоматизации действий на Android. Оно будет перехватывать уведомления от Сбербанка и отправлять их на сервер.
            </div>

            <a href="https://play.google.com/store/apps/details?id=com.llamalab.automate" target="_blank" class="store-btn">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="var(--accent)">
                    <path d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.5,12.92 20.16,13.19L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z"/>
                </svg>
                <div class="store-btn-text">
                    <div class="store-btn-title">Automate</div>
                    <div class="store-btn-subtitle">Открыть в Google Play</div>
                </div>
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
            </a>
        </div>
    </div>

    <!-- Step 2: Create Flow -->
    <div class="setup-section">
        <div class="setup-section-header">
            <div class="setup-section-number">2</div>
            <div class="setup-section-title">Создайте поток (Flow)</div>
        </div>
        <div class="setup-section-body">
            <div class="setup-section-text">
                Откройте Automate и создайте новый поток. Добавьте блоки в следующем порядке:
            </div>

            <div class="setup-section-text">
                <strong>Блок 1:</strong> Notification posted<br>
                <span style="color: var(--text-muted);">Фильтр: App = Сбербанк (или ru.sberbankmobile)</span>
            </div>

            <div class="setup-section-text">
                <strong>Блок 2:</strong> HTTP request<br>
                <span style="color: var(--text-muted);">Method = POST, URL = (см. ниже)</span>
            </div>

            <div class="token-box">
                <div class="token-label">URL для Automate</div>
                <div class="token-value" id="webhookUrl"><?= htmlspecialchars($webhookUrl) ?></div>
                <button class="copy-btn" onclick="copyToClipboard('webhookUrl')">Копировать</button>
            </div>

            <div class="setup-section-text">
                <strong>Тело запроса (Body):</strong>
            </div>

            <div class="code-block">
                <pre>{"notification": "#ntitle# #ntext#"}</pre>
            </div>

            <div class="setup-section-text">
                <strong>Headers:</strong><br>
                Content-Type: application/json
            </div>
        </div>
    </div>

    <!-- Step 3: Grant Permissions -->
    <div class="setup-section">
        <div class="setup-section-header">
            <div class="setup-section-number">3</div>
            <div class="setup-section-title">Выдайте разрешения</div>
        </div>
        <div class="setup-section-body">
            <div class="setup-section-text">
                Automate потребует доступ к уведомлениям. Перейдите в настройки Android и разрешите Automate читать уведомления:
            </div>
            <div class="setup-section-text">
                <strong>Настройки</strong> → <strong>Приложения</strong> → <strong>Специальный доступ</strong> → <strong>Доступ к уведомлениям</strong> → включите <strong>Automate</strong>
            </div>
        </div>
    </div>

    <!-- Step 4: Start Flow -->
    <div class="setup-section">
        <div class="setup-section-header">
            <div class="setup-section-number">4</div>
            <div class="setup-section-title">Запустите поток</div>
        </div>
        <div class="setup-section-body">
            <div class="setup-section-text">
                Нажмите кнопку "Start" в Automate для запуска потока. Теперь каждое уведомление от Сбербанка о переводе будет автоматически отправляться на сервер.
            </div>

            <div class="warning-box">
                <div class="warning-box-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Важно
                </div>
                <div class="warning-box-text">
                    Automate должен работать в фоне. Добавьте его в исключения оптимизации батареи, чтобы Android не закрывал приложение.
                </div>
            </div>
        </div>
    </div>

    <!-- Step 5: Add Payers -->
    <div class="setup-section">
        <div class="setup-section-header">
            <div class="setup-section-number">5</div>
            <div class="setup-section-title">Добавьте плательщиков</div>
        </div>
        <div class="setup-section-body">
            <div class="setup-section-text">
                Для автоматического распознавания платежей добавьте плательщиков к ученикам. Имя плательщика должно совпадать с именем отправителя в уведомлении Сбербанка.
            </div>
            <div class="setup-section-text">
                <strong>Пример имени:</strong> СТАНИСЛАВ ОЛЕГОВИЧ<br>
                <span style="color: var(--text-muted);">Используйте заглавные буквы, как в уведомлении</span>
            </div>

            <a href="student_payments.php" class="action-btn">
                Перейти к оплатам
            </a>
        </div>
    </div>

    <!-- Token Management -->
    <div class="setup-section">
        <div class="setup-section-header">
            <div class="setup-section-number">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div class="setup-section-title">Безопасность</div>
        </div>
        <div class="setup-section-body">
            <div class="setup-section-text">
                API токен защищает ваш webhook от несанкционированного доступа. Если токен скомпрометирован, сгенерируйте новый.
            </div>

            <div class="token-box">
                <div class="token-label">Текущий токен</div>
                <div class="token-value" id="currentToken"><?= htmlspecialchars($token) ?></div>
            </div>

            <button class="action-btn secondary" onclick="regenerateToken()">
                Сгенерировать новый токен
            </button>

            <div class="warning-box" style="margin-top: 12px;">
                <div class="warning-box-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Внимание
                </div>
                <div class="warning-box-text">
                    После генерации нового токена вам нужно будет обновить URL в Automate.
                </div>
            </div>
        </div>
    </div>

    <!-- Test Section -->
    <div class="setup-section">
        <div class="setup-section-header">
            <div class="setup-section-number">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="setup-section-title">Тестирование</div>
        </div>
        <div class="setup-section-body">
            <div class="setup-section-text">
                Отправьте тестовое уведомление, чтобы проверить работу системы:
            </div>

            <button class="action-btn" onclick="sendTestNotification()">
                Отправить тестовое уведомление
            </button>

            <div id="testResult" style="margin-top: 12px; display: none;"></div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(elementId) {
    const text = document.getElementById(elementId).textContent;
    navigator.clipboard.writeText(text).then(() => {
        MobileApp.showToast('Скопировано', 'success');
    }).catch(() => {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        MobileApp.showToast('Скопировано', 'success');
    });
}

async function regenerateToken() {
    if (!confirm('Вы уверены? После генерации нужно обновить URL в Automate.')) return;

    try {
        MobileApp.showLoading();
        const res = await fetch('../api/incoming_payments.php?action=regenerate_token', {
            method: 'POST'
        });
        const result = await res.json();

        if (result.success) {
            document.getElementById('currentToken').textContent = result.data.token;
            // Update webhook URL
            const url = document.getElementById('webhookUrl').textContent;
            document.getElementById('webhookUrl').textContent = url.replace(/token=[^&]+/, 'token=' + result.data.token);
            MobileApp.showToast('Токен обновлен', 'success');
        } else {
            MobileApp.showToast(result.error || 'Ошибка', 'error');
        }
    } catch (e) {
        MobileApp.showToast('Ошибка сети', 'error');
    } finally {
        MobileApp.hideLoading();
    }
}

async function sendTestNotification() {
    const testNotification = "Перевод по СБП от ТЕСТ ТЕСТОВИЧ... Тест-Банк +100 ₽";

    try {
        MobileApp.showLoading();
        const webhookUrl = document.getElementById('webhookUrl').textContent;

        const res = await fetch(webhookUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification: testNotification })
        });

        const result = await res.json();
        const resultDiv = document.getElementById('testResult');

        if (result.success) {
            resultDiv.innerHTML = `
                <div style="background: var(--status-green-dim); border: 1px solid var(--status-green); border-radius: 8px; padding: 12px;">
                    <div style="font-weight: 600; color: var(--status-green); margin-bottom: 4px;">Успешно!</div>
                    <div style="font-size: 13px; color: var(--text-secondary);">
                        Платеж ID: ${result.data.id}<br>
                        Статус: ${result.data.status}
                    </div>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div style="background: var(--status-rose-dim); border: 1px solid var(--status-rose); border-radius: 8px; padding: 12px;">
                    <div style="font-weight: 600; color: var(--status-rose); margin-bottom: 4px;">Ошибка</div>
                    <div style="font-size: 13px; color: var(--text-secondary);">${result.error || 'Неизвестная ошибка'}</div>
                </div>
            `;
        }

        resultDiv.style.display = 'block';
    } catch (e) {
        const resultDiv = document.getElementById('testResult');
        resultDiv.innerHTML = `
            <div style="background: var(--status-rose-dim); border: 1px solid var(--status-rose); border-radius: 8px; padding: 12px;">
                <div style="font-weight: 600; color: var(--status-rose); margin-bottom: 4px;">Ошибка сети</div>
                <div style="font-size: 13px; color: var(--text-secondary);">${e.message}</div>
            </div>
        `;
        resultDiv.style.display = 'block';
    } finally {
        MobileApp.hideLoading();
    }
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
