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

requireAuth();
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
        <form id="email-parser-form" onsubmit="saveEmailParserSettings(event)">
            <div class="form-group">
                <label class="form-label" for="gmail_user">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">email</span>
                    Gmail адрес *
                </label>
                <input
                    type="email"
                    class="form-control"
                    id="gmail_user"
                    name="gmail_user"
                    value="<?= e($settingsMap['gmail_user'] ?? '') ?>"
                    placeholder="example@gmail.com"
                    required
                >
                <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                    Адрес Gmail для проверки входящих уведомлений
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="gmail_app_password">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">vpn_key</span>
                    Пароль приложения Gmail *
                </label>
                <div class="input-with-icon">
                    <input
                        type="password"
                        class="form-control"
                        id="gmail_app_password"
                        name="gmail_app_password"
                        value="<?= e($settingsMap['gmail_app_password'] ?? '') ?>"
                        placeholder="xxxx xxxx xxxx xxxx"
                        required
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword('gmail_app_password')">
                        <span class="material-icons" id="gmail_app_password-icon">visibility_off</span>
                    </button>
                </div>
                <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                    Создайте <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color: var(--md-primary);">пароль приложения</a> в настройках Google
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="email_sender">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">person</span>
                    Отправитель писем (FROM)
                </label>
                <input
                    type="email"
                    class="form-control"
                    id="email_sender"
                    name="email_sender"
                    value="<?= e($settingsMap['email_sender'] ?? $settingsMap['gmail_user'] ?? '') ?>"
                    placeholder="sender@gmail.com"
                >
                <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                    От кого приходят письма (Notification Forwarder). По умолчанию = Gmail адрес
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="email_subject_filter">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">label</span>
                    Фильтр по теме письма *
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="email_subject_filter"
                    name="email_subject_filter"
                    value="<?= e($settingsMap['email_subject_filter'] ?? 'ZARPLATAPROJECT') ?>"
                    placeholder="ZARPLATAPROJECT"
                    required
                >
                <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                    Ключевое слово в теме письма для фильтрации (настройте в Notification Forwarder)
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="email_search_days">
                    <span class="material-icons" style="font-size: 16px; vertical-align: middle;">date_range</span>
                    Период поиска писем (дней)
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="email_search_days"
                    name="email_search_days"
                    value="<?= e($settingsMap['email_search_days'] ?? '60') ?>"
                    min="7"
                    max="365"
                >
                <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                    За сколько дней проверять письма (дубликаты не добавляются повторно)
                </small>
            </div>

            <button type="submit" class="btn btn-primary" id="save-email-btn">
                <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
                Сохранить настройки почты
            </button>
        </form>
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

<script src="/zarplata/assets/js/settings.js"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
