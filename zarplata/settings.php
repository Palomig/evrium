<?php
/**
 * Страница настроек
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

// Автоматический редирект на мобильную версию
require_once __DIR__ . '/mobile/config/mobile_detect.php';
redirectToMobileIfNeeded('settings.php');

requireSection('settings');
$_SESSION['tg_return'] = '/zarplata/settings.php'; // возврат после привязки Telegram
$user = getCurrentUser();

// Получить все настройки
$settings = dbQuery("SELECT * FROM settings ORDER BY setting_key ASC", []);

// Преобразовать в ассоциативный массив
$settingsMap = [];
foreach ($settings as $setting) {
    $settingsMap[$setting['setting_key']] = $setting['setting_value'];
}

define('PAGE_TITLE', 'Настройки');
define('PAGE_SUBTITLE', 'Конфигурация системы');
define('ACTIVE_PAGE', 'settings');

require_once __DIR__ . '/templates/header.php';
?>

<!-- Настройки парсинга Email -->
<div class="card mb-4">
    <div class="card-header">
        <h3 style="margin: 0;">
            <span class="material-icons" style="vertical-align: middle;">mail</span>
            Парсинг платежей из почты
        </h3>
    </div>
    <div class="card-body">
        <style>
            /* Email Settings Styles (matching site design) */
            .email-settings .setup-section {
                background: var(--bg-card, #1a1a2e);
                border: 1px solid var(--border, #2d2d44);
                border-radius: 14px;
                margin-bottom: 16px;
                overflow: hidden;
            }

            .email-settings .setup-section-header {
                padding: 16px;
                background: var(--bg-elevated, #252540);
                border-bottom: 1px solid var(--border, #2d2d44);
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .email-settings .setup-section-number {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: var(--accent, #26a69a);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 14px;
                flex-shrink: 0;
            }

            .email-settings .setup-section-title {
                font-size: 16px;
                font-weight: 600;
                color: var(--text-primary, #fff);
            }

            .email-settings .setup-section-body {
                padding: 20px;
            }

            .email-settings .form-group {
                margin-bottom: 20px;
            }

            .email-settings .form-group:last-of-type {
                margin-bottom: 0;
            }

            .email-settings .form-label {
                display: block;
                font-size: 13px;
                font-weight: 600;
                color: var(--text-secondary, #a0a0a0);
                margin-bottom: 8px;
            }

            .email-settings .form-control {
                width: 100%;
                padding: 12px 14px;
                background: var(--bg-elevated, #252540);
                border: 1px solid var(--border, #2d2d44);
                border-radius: 10px;
                font-size: 14px;
                color: var(--text-primary, #fff);
                transition: all 0.15s ease;
            }

            .email-settings .form-control:hover {
                border-color: var(--accent, #26a69a);
            }

            .email-settings .form-control:focus {
                outline: none;
                border-color: var(--accent, #26a69a);
                box-shadow: 0 0 0 3px rgba(38, 166, 154, 0.15);
            }

            .email-settings .form-control::placeholder {
                color: var(--text-muted, #666);
            }

            .email-settings .form-hint {
                display: block;
                margin-top: 6px;
                font-size: 12px;
                color: var(--text-muted, #666);
                line-height: 1.4;
            }

            .email-settings .form-hint a {
                color: var(--accent, #26a69a);
                text-decoration: none;
            }

            .email-settings .form-hint a:hover {
                text-decoration: underline;
            }

            /* Password wrapper */
            .email-settings .input-password-wrapper {
                position: relative;
            }

            .email-settings .input-password-wrapper .form-control {
                padding-right: 48px;
            }

            .email-settings .password-toggle-btn {
                position: absolute;
                right: 4px;
                top: 50%;
                transform: translateY(-50%);
                width: 40px;
                height: 40px;
                background: transparent;
                border: none;
                border-radius: 8px;
                color: var(--text-muted, #666);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.15s ease;
            }

            .email-settings .password-toggle-btn:hover {
                background: var(--bg-card, #1a1a2e);
                color: var(--accent, #26a69a);
            }

            /* Number input */
            .email-settings input[type="number"] {
                -moz-appearance: textfield;
            }

            .email-settings input[type="number"]::-webkit-outer-spin-button,
            .email-settings input[type="number"]::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }

            /* Buttons */
            .email-settings .action-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 20px;
                background: var(--accent, #26a69a);
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.15s ease;
            }

            .email-settings .action-btn:hover {
                filter: brightness(1.1);
            }

            .email-settings .action-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .email-settings .action-btn.secondary {
                background: var(--bg-elevated, #252540);
                color: var(--text-primary, #fff);
                border: 1px solid var(--border, #2d2d44);
            }

            .email-settings .action-btn.secondary:hover {
                border-color: var(--accent, #26a69a);
            }

            /* Info box */
            .email-settings .info-box {
                background: var(--accent-dim, rgba(38, 166, 154, 0.1));
                border: 1px solid var(--accent, #26a69a);
                border-radius: 10px;
                padding: 14px;
                margin-top: 20px;
            }

            .email-settings .info-box-title {
                font-size: 13px;
                font-weight: 600;
                color: var(--accent, #26a69a);
                margin-bottom: 6px;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .email-settings .info-box-text {
                font-size: 12px;
                color: var(--text-secondary, #a0a0a0);
                line-height: 1.5;
            }

            /* Test result */
            .email-settings .test-result {
                margin-top: 16px;
                padding: 14px;
                border-radius: 10px;
                display: none;
            }

            .email-settings .test-result.success {
                background: var(--status-green-dim, rgba(76, 175, 80, 0.1));
                border: 1px solid var(--status-green, #4caf50);
            }

            .email-settings .test-result.error {
                background: var(--status-rose-dim, rgba(244, 67, 54, 0.1));
                border: 1px solid var(--status-rose, #f44336);
            }
        </style>

        <div class="email-settings">
            <form id="email-parser-form" onsubmit="saveEmailParserSettings(event)">
                <!-- Gmail Credentials Section -->
                <div class="setup-section">
                    <div class="setup-section-header">
                        <div class="setup-section-number">1</div>
                        <div class="setup-section-title">Учётные данные Gmail</div>
                    </div>
                    <div class="setup-section-body">
                        <div class="form-group">
                            <label class="form-label">Gmail адрес *</label>
                            <input
                                type="email"
                                class="form-control"
                                id="gmail_user"
                                name="gmail_user"
                                value="<?= e($settingsMap['gmail_user'] ?? '') ?>"
                                placeholder="example@gmail.com"
                                required
                            >
                            <span class="form-hint">Адрес Gmail для проверки входящих уведомлений</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Пароль приложения *</label>
                            <div class="input-password-wrapper">
                                <input
                                    type="password"
                                    class="form-control"
                                    id="gmail_app_password"
                                    name="gmail_app_password"
                                    value="<?= e($settingsMap['gmail_app_password'] ?? '') ?>"
                                    placeholder="xxxx xxxx xxxx xxxx"
                                    required
                                >
                                <button type="button" class="password-toggle-btn" onclick="toggleEmailPassword('gmail_app_password')">
                                    <span class="material-icons" id="gmail_app_password-icon">visibility_off</span>
                                </button>
                            </div>
                            <span class="form-hint">
                                Создайте <a href="https://myaccount.google.com/apppasswords" target="_blank">пароль приложения</a> в настройках Google
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="setup-section">
                    <div class="setup-section-header">
                        <div class="setup-section-number">2</div>
                        <div class="setup-section-title">Фильтрация писем</div>
                    </div>
                    <div class="setup-section-body">
                        <div class="form-group">
                            <label class="form-label">Отправитель писем (FROM)</label>
                            <input
                                type="email"
                                class="form-control"
                                id="email_sender"
                                name="email_sender"
                                value="<?= e($settingsMap['email_sender'] ?? $settingsMap['gmail_user'] ?? '') ?>"
                                placeholder="sender@gmail.com"
                            >
                            <span class="form-hint">От кого приходят письма (Notification Forwarder). По умолчанию = Gmail адрес</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Фильтр по теме письма *</label>
                            <input
                                type="text"
                                class="form-control"
                                id="email_subject_filter"
                                name="email_subject_filter"
                                value="<?= e($settingsMap['email_subject_filter'] ?? 'ZARPLATAPROJECT') ?>"
                                placeholder="ZARPLATAPROJECT"
                                required
                            >
                            <span class="form-hint">Ключевое слово в теме письма для фильтрации (настройте в Notification Forwarder)</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Период поиска (дней)</label>
                            <input
                                type="number"
                                class="form-control"
                                id="email_search_days"
                                name="email_search_days"
                                value="<?= e($settingsMap['email_search_days'] ?? '60') ?>"
                                min="7"
                                max="365"
                                style="max-width: 150px;"
                            >
                            <span class="form-hint">За сколько дней проверять письма (дубликаты не добавляются повторно)</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 20px;">
                    <button type="submit" class="action-btn" id="save-email-btn">
                        <span class="material-icons" style="font-size: 18px;">save</span>
                        Сохранить настройки
                    </button>

                    <a href="webhook_logs.php" class="action-btn secondary" style="text-decoration: none;">
                        <span class="material-icons" style="font-size: 18px;">bug_report</span>
                        Тестирование парсинга
                    </a>
                </div>

                <div class="info-box">
                    <div class="info-box-title">
                        <span class="material-icons" style="font-size: 18px;">info</span>
                        Как это работает
                    </div>
                    <div class="info-box-text">
                        Cron запускает скрипт каждые 30 минут. Скрипт проверяет Gmail через IMAP,
                        находит письма с нужной темой и извлекает данные о переводах Сбербанка.
                        Сохраняются только платежи от плательщиков из белого списка.
                    </div>
                </div>
            </form>
        </div>

        <script>
            function toggleEmailPassword(fieldId) {
                const input = document.getElementById(fieldId);
                const icon = document.getElementById(fieldId + '-icon');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.textContent = 'visibility';
                } else {
                    input.type = 'password';
                    icon.textContent = 'visibility_off';
                }
            }
        </script>
    </div>
</div>

<!-- Настройки Telegram Bot -->
<div class="card mb-4">
    <div class="card-header">
        <h3 style="margin: 0;">
            <span class="material-icons" style="vertical-align: middle;">telegram</span>
            Telegram Bot
        </h3>
    </div>
    <div class="card-body">
        <!-- Статус webhook -->
        <div id="webhook-status" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background-color: var(--md-surface-3);">
            <div style="display: flex; align-items: center; margin-bottom: 12px;">
                <span class="material-icons" style="margin-right: 8px;">info</span>
                <strong>Статус Webhook</strong>
            </div>
            <div id="webhook-status-content">
                <p style="margin: 0; color: var(--text-medium-emphasis);">Нажмите "Проверить статус" после сохранения токена</p>
            </div>
            <div style="margin-top: 12px;">
                <button type="button" class="btn btn-text" onclick="checkWebhookStatus()">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">refresh</span>
                    Проверить статус
                </button>
                <button type="button" class="btn btn-text" onclick="setupWebhook()">
                    <span class="material-icons" style="margin-right: 8px; font-size: 18px;">settings</span>
                    Настроить webhook
                </button>
            </div>
        </div>

        <form id="bot-settings-form" onsubmit="saveBotSettings(event)">
            <div class="form-group">
                <label class="form-label" for="bot_token">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">vpn_key</span>
                    Bot Token *
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="bot_token"
                    name="bot_token"
                    value="<?= e($settingsMap['bot_token'] ?? '') ?>"
                    placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                    required
                >
                <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                    Получите токен у <a href="https://t.me/BotFather" target="_blank" style="color: var(--md-primary);">@BotFather</a>
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="bot_check_interval">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">schedule</span>
                    Интервал проверки уроков (минуты)
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="bot_check_interval"
                    name="bot_check_interval"
                    value="<?= e($settingsMap['bot_check_interval'] ?? '5') ?>"
                    min="1"
                    max="60"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="attendance_delay">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">timer</span>
                    Задержка опроса посещаемости (минуты)
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="attendance_delay"
                    name="attendance_delay"
                    value="<?= e($settingsMap['attendance_delay'] ?? '15') ?>"
                    min="0"
                    max="60"
                >
                <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                    Бот спросит про посещаемость через N минут после начала урока
                </small>
            </div>

            <button type="submit" class="btn btn-primary" id="save-bot-btn">
                <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                Сохранить настройки бота
            </button>
        </form>
    </div>
</div>

<!-- Настройки оплаты от учеников -->
<div class="card mb-4">
    <div class="card-header">
        <h3 style="margin: 0;">
            <span class="material-icons" style="vertical-align: middle;">credit_card</span>
            Оплата от учеников
        </h3>
    </div>
    <div class="card-body">
        <form id="payment-settings-form" onsubmit="savePaymentSettings(event)">
            <div class="form-group">
                <label class="form-label" for="payment_card_number">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">credit_card</span>
                    Номер карты для переводов
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="payment_card_number"
                    name="payment_card_number"
                    value="<?= e($settingsMap['payment_card_number'] ?? '') ?>"
                    placeholder="0000 0000 0000 0000"
                    maxlength="19"
                >
                <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                    Номер карты, на которую родители переводят оплату
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="payment_reminder_template">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">message</span>
                    Шаблон напоминания об оплате
                </label>
                <textarea
                    class="form-control"
                    id="payment_reminder_template"
                    name="payment_reminder_template"
                    rows="6"
                    placeholder="Текст напоминания..."
                ><?= e($settingsMap['payment_reminder_template'] ?? 'Здравствуйте! Напоминаем об оплате занятий за {month}.

Ученик: {student_name}
Сумма: {amount} ₽

Способ оплаты: перевод на карту {card_number}') ?></textarea>
                <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                    Доступные переменные: <code>{student_name}</code>, <code>{month}</code>, <code>{amount}</code>, <code>{card_number}</code>
                </small>
            </div>

            <button type="submit" class="btn btn-primary" id="save-payment-btn">
                <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                Сохранить настройки оплаты
            </button>
        </form>
    </div>
</div>

<!-- Системные настройки -->
<div class="card mb-4">
    <div class="card-header">
        <h3 style="margin: 0;">
            <span class="material-icons" style="vertical-align: middle;">settings</span>
            Системные настройки
        </h3>
    </div>
    <div class="card-body">
        <form id="system-settings-form" onsubmit="saveSystemSettings(event)">
            <div class="form-group">
                <label class="form-label" for="timezone">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">public</span>
                    Часовой пояс
                </label>
                <select class="form-control" id="timezone" name="timezone">
                    <option value="Europe/Moscow" <?= ($settingsMap['timezone'] ?? 'Europe/Moscow') === 'Europe/Moscow' ? 'selected' : '' ?>>Europe/Moscow (МСК)</option>
                    <option value="Europe/Kaliningrad" <?= ($settingsMap['timezone'] ?? '') === 'Europe/Kaliningrad' ? 'selected' : '' ?>>Europe/Kaliningrad (МСК-1)</option>
                    <option value="Europe/Samara" <?= ($settingsMap['timezone'] ?? '') === 'Europe/Samara' ? 'selected' : '' ?>>Europe/Samara (МСК+1)</option>
                    <option value="Asia/Yekaterinburg" <?= ($settingsMap['timezone'] ?? '') === 'Asia/Yekaterinburg' ? 'selected' : '' ?>>Asia/Yekaterinburg (МСК+2)</option>
                    <option value="Asia/Novosibirsk" <?= ($settingsMap['timezone'] ?? '') === 'Asia/Novosibirsk' ? 'selected' : '' ?>>Asia/Novosibirsk (МСК+4)</option>
                    <option value="Asia/Krasnoyarsk" <?= ($settingsMap['timezone'] ?? '') === 'Asia/Krasnoyarsk' ? 'selected' : '' ?>>Asia/Krasnoyarsk (МСК+4)</option>
                    <option value="Asia/Irkutsk" <?= ($settingsMap['timezone'] ?? '') === 'Asia/Irkutsk' ? 'selected' : '' ?>>Asia/Irkutsk (МСК+5)</option>
                    <option value="Asia/Vladivostok" <?= ($settingsMap['timezone'] ?? '') === 'Asia/Vladivostok' ? 'selected' : '' ?>>Asia/Vladivostok (МСК+7)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">info</span>
                    Информация о системе
                </label>
                <div style="background-color: var(--md-surface-3); padding: 16px; border-radius: 8px; font-size: 0.875rem;">
                    <p style="margin-bottom: 8px;"><strong>База данных:</strong> <?= DB_NAME ?></p>
                    <p style="margin-bottom: 8px;"><strong>Пользователь:</strong> <?= DB_USER ?></p>
                    <p style="margin-bottom: 8px;"><strong>Версия PHP:</strong> <?= phpversion() ?></p>
                    <p style="margin-bottom: 0;"><strong>Текущий пользователь:</strong> <?= e($user['name']) ?> (<?= $user['role'] ?>)</p>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="save-system-btn">
                <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                Сохранить системные настройки
            </button>
        </form>
    </div>
</div>

<!-- Безопасность -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">
            <span class="material-icons" style="vertical-align: middle;">security</span>
            Безопасность
        </h3>
    </div>
    <div class="card-body">
        <style>
            .input-with-icon {
                position: relative;
            }

            .password-toggle {
                position: absolute;
                right: 12px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: var(--text-medium-emphasis);
                cursor: pointer;
                padding: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: color 0.2s;
            }

            .password-toggle:hover {
                color: var(--text-high-emphasis);
            }

            .password-toggle .material-icons {
                font-size: 20px;
            }
        </style>

        <form id="password-form" onsubmit="changePassword(event)">
            <div class="form-group">
                <label class="form-label" for="current_password">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">lock</span>
                    Текущий пароль *
                </label>
                <div class="input-with-icon">
                    <input
                        type="password"
                        class="form-control"
                        id="current_password"
                        name="current_password"
                        placeholder="Введите текущий пароль"
                        required
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                        <span class="material-icons" id="current_password-icon">visibility_off</span>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">lock_open</span>
                    Новый пароль *
                </label>
                <div class="input-with-icon">
                    <input
                        type="password"
                        class="form-control"
                        id="new_password"
                        name="new_password"
                        placeholder="Введите новый пароль (минимум 6 символов)"
                        required
                        minlength="6"
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                        <span class="material-icons" id="new_password-icon">visibility_off</span>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">lock_reset</span>
                    Подтвердите новый пароль *
                </label>
                <div class="input-with-icon">
                    <input
                        type="password"
                        class="form-control"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Повторите новый пароль"
                        required
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                        <span class="material-icons" id="confirm_password-icon">visibility_off</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="change-password-btn">
                <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                Изменить пароль
            </button>
        </form>

        <script>
            function togglePassword(fieldId) {
                const input = document.getElementById(fieldId);
                const icon = document.getElementById(fieldId + '-icon');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.textContent = 'visibility';
                } else {
                    input.type = 'password';
                    icon.textContent = 'visibility_off';
                }
            }
        </script>
    </div>
</div>

<!-- Пользователи панели -->
<div class="card mt-4" style="margin-top: 24px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="margin: 0;">Пользователи и доступы</h3>
        <button class="btn btn-primary" onclick="toggleUserForm()">
            <span class="material-icons" style="margin-right: 6px; font-size: 18px;">person_add</span>
            Добавить
        </button>
    </div>

    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 16px;">
        Расписание, уроки, посещаемость и своя зарплата открыты всем. Остальные разделы включаются галочками.
        Владелец видит всё; у администратора по умолчанию всё, кроме настроек; у преподавателя — только своё.
        Через Telegram входят пользователи с привязанным Telegram и преподаватели, подключённые к боту.
        «Привязать это устройство» на экране входа: код подтверждается в боте, телефон запоминается до отзыва — список под именем пользователя.
    </p>

    <?php if (!empty($_GET['tg_linked'])): ?>
    <div class="alert alert-success" style="margin-bottom: 16px;">Telegram привязан к вашему аккаунту</div>
    <?php elseif (!empty($_GET['tg_error'])): ?>
    <div class="alert alert-error" style="margin-bottom: 16px;"><?= e($_GET['tg_error']) ?></div>
    <?php endif; ?>

    <div id="tg-self-link" style="display: none; align-items: center; gap: 14px; flex-wrap: wrap; background: var(--bg-elevated); border: 1px solid var(--border); border-radius: 10px; padding: 12px 16px; margin-bottom: 16px;">
        <div style="font-size: 13px;">
            <strong>Ваш Telegram не привязан.</strong>
            <span style="color: var(--text-secondary);">Нажмите кнопку — и сможете входить без пароля.</span>
        </div>
        <div id="tg-self-widget"></div>
    </div>

    <!-- Форма создания -->
    <div id="user-create-form" style="display: none; background: var(--bg-elevated); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 16px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
            <div>
                <label style="font-size: 12px;">Логин</label>
                <input type="text" id="nu-username" placeholder="ruslan" autocomplete="off">
            </div>
            <div>
                <label style="font-size: 12px;">Имя</label>
                <input type="text" id="nu-name" placeholder="Руслан Романович" autocomplete="off">
            </div>
            <div>
                <label style="font-size: 12px;">Роль</label>
                <select id="nu-role" onchange="document.getElementById('nu-teacher-wrap').style.display = this.value === 'teacher' ? '' : 'none'">
                    <option value="teacher">Преподаватель</option>
                    <option value="admin">Администратор</option>
                </select>
            </div>
            <div id="nu-teacher-wrap">
                <label style="font-size: 12px;">Преподаватель</label>
                <select id="nu-teacher"></select>
            </div>
            <div>
                <label style="font-size: 12px;">Telegram ID (необязательно)</label>
                <input type="text" id="nu-telegram" placeholder="245710727" inputmode="numeric" autocomplete="off">
            </div>
            <div>
                <label style="font-size: 12px;">Пароль (мин. 8, можно пусто)</label>
                <input type="text" id="nu-password" autocomplete="off" placeholder="только Telegram">
            </div>
        </div>
        <p style="color: var(--text-muted); font-size: 12px; margin: 10px 0 0;">
            Telegram ID можно узнать у бота @userinfobot. Преподавателю ID не нужен — возьмём из бота посещаемости.
        </p>
        <div style="margin-top: 12px; display: flex; gap: 8px;">
            <button class="btn btn-primary" onclick="createPanelUser()">Создать</button>
            <button class="btn btn-secondary" onclick="toggleUserForm()">Отмена</button>
        </div>
    </div>

    <!-- Список -->
    <div style="overflow-x: auto;">
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Роль</th>
                    <th>Telegram</th>
                    <th>Доступы</th>
                    <th>Активен</th>
                    <th style="text-align: right;">Действия</th>
                </tr>
            </thead>
            <tbody id="users-table-body">
                <tr><td colspan="6" style="color: var(--text-muted);">Загрузка…</td></tr>
            </tbody>
        </table>
    </div>
</div>

<style>
    .perm-grid { display: flex; flex-wrap: wrap; gap: 4px 10px; max-width: 420px; }
    .perm-grid label { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; white-space: nowrap; cursor: pointer; color: var(--text-secondary); }
    .perm-grid label.on { color: var(--text-primary); }
    .perm-grid input { width: auto; margin: 0; }
    .perm-all { color: var(--accent, #14b8a6); font-size: 12px; }
    .tg-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; }
    .tg-chip .x { cursor: pointer; color: var(--text-muted); font-size: 16px; line-height: 1; }
    .tg-chip .x:hover { color: var(--status-rose, #f43f5e); }
    .btn-mini { padding: 4px 9px; font-size: 12px; }
    .dev-list { display: flex; flex-direction: column; gap: 3px; margin-top: 6px; }
    .dev-chip { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; color: var(--text-secondary); }
    .dev-chip em { font-style: normal; color: var(--text-muted); }
    .dev-chip .x { cursor: pointer; color: var(--text-muted); font-size: 15px; line-height: 1; }
    .dev-chip .x:hover { color: var(--status-rose, #f43f5e); }
</style>

<script>
let panelUsersData = null;

async function usersApi(action, payload) {
    const opts = payload
        ? { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) }
        : {};
    const r = await fetch('/zarplata/api/users.php?action=' + action, opts);
    return r.json();
}

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function renderTelegramCell(u) {
    const isSelf = parseInt(u.id) === parseInt(panelUsersData.self_id);
    if (u.telegram_id) {
        const label = u.telegram_username ? '@' + escapeHtml(u.telegram_username) : 'ID ' + u.telegram_id;
        return `<span class="tg-chip" title="ID ${u.telegram_id}">${label}<span class="x" title="Отвязать" onclick="unlinkTelegram(${u.id}, '${escapeHtml(u.username)}')">×</span></span>`;
    }
    if (u.teacher_telegram_id) {
        return `<span class="tg-chip" style="color: var(--text-secondary);" title="Возьмём из бота при первом входе">через бота</span>
                <button class="btn btn-secondary btn-mini" onclick="setTelegramId(${u.id}, '${escapeHtml(u.username)}')">ID…</button>`;
    }
    return `<span style="color: var(--text-muted);">—</span>
            ${isSelf ? '' : `<button class="btn btn-secondary btn-mini" onclick="setTelegramId(${u.id}, '${escapeHtml(u.username)}')">ID…</button>`}`;
}

function renderDevices(u) {
    if (!u.devices || !u.devices.length) return '';
    const fmt = d => d ? d.slice(8, 10) + '.' + d.slice(5, 7) : '';
    return '<div class="dev-list">' + u.devices.map(d => {
        const label = d.kind === 'device' ? (d.label || 'Устройство') : 'Сеанс «запомнить»';
        const icon = d.kind === 'device' ? 'smartphone' : 'schedule';
        return `<span class="dev-chip" title="привязано ${d.created_at || ''}, последний вход ${d.last_used_at || '—'}">
            <span class="material-icons" style="font-size: 14px;">${icon}</span>${escapeHtml(label)} <em>${fmt(d.last_used_at || d.created_at)}</em>
            <span class="x" title="Отозвать" onclick="revokeDevice(${d.id}, '${escapeHtml(label)}')">×</span></span>`;
    }).join('') + '</div>';
}

async function revokeDevice(id, label) {
    if (!confirm(`Отозвать «${label}»? На этом устройстве придётся входить заново.`)) return;
    const result = await usersApi('revoke_device', {id: id});
    if (!result.success) alert(result.error || 'Ошибка');
    loadPanelUsers();
}

function renderPermsCell(u) {
    if (u.role === 'owner') {
        return '<span class="perm-all">все разделы</span>';
    }
    const sections = panelUsersData.sections;
    return '<div class="perm-grid">' + Object.keys(sections).map(key => {
        const on = !!u.effective[key];
        return `<label class="${on ? 'on' : ''}"><input type="checkbox" ${on ? 'checked' : ''} onchange="togglePerm(${u.id}, '${key}', this.checked)">${escapeHtml(sections[key])}</label>`;
    }).join('') + '</div>';
}

async function loadPanelUsers() {
    const result = await usersApi('list');
    if (!result.success) return;
    panelUsersData = result.data;

    // Селект преподавателей в форме создания
    const tsel = document.getElementById('nu-teacher');
    tsel.innerHTML = panelUsersData.teachers
        .map(t => `<option value="${t.id}">${escapeHtml(t.name)}${t.telegram_id ? ' · в боте' : ''}</option>`).join('');

    // Виджет привязки собственного Telegram (только если ещё не привязан и бот известен)
    const self = panelUsersData.users.find(u => parseInt(u.id) === parseInt(panelUsersData.self_id));
    const linkBox = document.getElementById('tg-self-link');
    if (self && !self.telegram_id && panelUsersData.bot_username) {
        linkBox.style.display = 'flex';
        const holder = document.getElementById('tg-self-widget');
        if (!holder.childElementCount) {
            const sc = document.createElement('script');
            sc.async = true;
            sc.src = 'https://telegram.org/js/telegram-widget.js?22';
            sc.setAttribute('data-telegram-login', panelUsersData.bot_username);
            sc.setAttribute('data-size', 'medium');
            sc.setAttribute('data-radius', '8');
            sc.setAttribute('data-lang', 'ru');
            sc.setAttribute('data-auth-url', location.origin + '/zarplata/auth/telegram.php');
            holder.appendChild(sc);
        }
    } else {
        linkBox.style.display = 'none';
    }

    const roleNames = { owner: 'Владелец', admin: 'Админ', teacher: 'Преподаватель' };
    const tbody = document.getElementById('users-table-body');
    tbody.innerHTML = panelUsersData.users.map(u => {
        const isSelf = parseInt(u.id) === parseInt(panelUsersData.self_id);
        const canEditRole = u.role !== 'owner' && !isSelf;
        const roleCell = canEditRole
            ? `<select onchange="updatePanelUser(${u.id}, {role: this.value})" style="padding: 4px 6px; font-size: 12px; min-width: 140px;">
                   <option value="teacher" ${u.role === 'teacher' ? 'selected' : ''}>Преподаватель</option>
                   <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Админ</option>
               </select>`
            : (roleNames[u.role] || u.role);
        return `<tr style="${parseInt(u.active) ? '' : 'opacity: 0.45;'}">
            <td><strong>${escapeHtml(u.username)}</strong>${isSelf ? ' <span style="color: var(--text-muted); font-size: 11px;">(вы)</span>' : ''}
                <div style="color: var(--text-secondary); font-size: 12px;">${escapeHtml(u.name || '')}${u.teacher_name ? ' · ' + escapeHtml(u.teacher_name) : ''}</div>
                ${renderDevices(u)}</td>
            <td>${roleCell}</td>
            <td>${renderTelegramCell(u)}</td>
            <td>${renderPermsCell(u)}</td>
            <td><input type="checkbox" ${parseInt(u.active) ? 'checked' : ''} ${isSelf ? 'disabled' : ''} onchange="updatePanelUser(${u.id}, {active: this.checked ? 1 : 0})"></td>
            <td style="text-align: right;">
                <button class="btn btn-secondary btn-mini" onclick="resetPanelPassword(${u.id}, '${escapeHtml(u.username)}')">Пароль</button>
            </td>
        </tr>`;
    }).join('');
}

function toggleUserForm() {
    const form = document.getElementById('user-create-form');
    form.style.display = form.style.display === 'none' ? '' : 'none';
}

async function createPanelUser() {
    const payload = {
        username: document.getElementById('nu-username').value.trim(),
        name: document.getElementById('nu-name').value.trim(),
        role: document.getElementById('nu-role').value,
        teacher_id: parseInt(document.getElementById('nu-teacher').value) || null,
        telegram_id: parseInt(document.getElementById('nu-telegram').value) || null,
        password: document.getElementById('nu-password').value
    };
    const result = await usersApi('create', payload);
    if (!result.success) {
        alert(result.error || 'Ошибка');
        return;
    }
    ['nu-username', 'nu-name', 'nu-password', 'nu-telegram'].forEach(id => document.getElementById(id).value = '');
    toggleUserForm();
    loadPanelUsers();
}

async function updatePanelUser(id, fields) {
    const result = await usersApi('update', Object.assign({id: id}, fields));
    if (!result.success) {
        alert(result.error || 'Ошибка');
    }
    loadPanelUsers();
}

// Галочка раздела: отправляем полный набор текущих доступов пользователя
function togglePerm(id, key, on) {
    const u = panelUsersData.users.find(x => parseInt(x.id) === id);
    if (!u) return;
    const perms = Object.assign({}, u.effective);
    perms[key] = on;
    updatePanelUser(id, {permissions: perms});
}

function setTelegramId(id, username) {
    const value = prompt(`Telegram ID для «${username}» (число, узнать у @userinfobot):`);
    if (value === null) return;
    const tgId = parseInt(value.trim());
    if (!tgId) { alert('Нужно число'); return; }
    updatePanelUser(id, {telegram_id: tgId});
}

function unlinkTelegram(id, username) {
    if (!confirm(`Отвязать Telegram от «${username}»? Вход через Telegram для него перестанет работать.`)) return;
    updatePanelUser(id, {telegram_id: null});
}

async function resetPanelPassword(id, username) {
    const pwd = prompt(`Новый пароль для «${username}» (мин. 8 символов):`);
    if (pwd === null) return;
    const result = await usersApi('reset_password', {id: id, password: pwd});
    alert(result.success ? 'Пароль изменён' : (result.error || 'Ошибка'));
}

document.addEventListener('DOMContentLoaded', loadPanelUsers);
</script>

<script src="/zarplata/assets/js/settings.js"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
