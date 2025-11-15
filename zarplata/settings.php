<?php
/**
 * Страница настроек
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

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

<!-- Настройки Telegram Bot -->
<div class="card mb-4">
    <div class="card-header">
        <h3 style="margin: 0;">
            <span class="material-icons" style="vertical-align: middle;">telegram</span>
            Telegram Bot
        </h3>
    </div>
    <div class="card-body">
        <div class="form-group">
            <label class="form-label" for="bot_token">
                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">vpn_key</span>
                Bot Token
            </label>
            <input
                type="text"
                class="form-control"
                id="bot_token"
                value="<?= e($settingsMap['bot_token'] ?? '') ?>"
                placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                readonly
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
                value="<?= e($settingsMap['bot_check_interval'] ?? '5') ?>"
                min="1"
                max="60"
                readonly
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
                value="<?= e($settingsMap['attendance_delay'] ?? '15') ?>"
                min="0"
                max="60"
                readonly
            >
            <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                Бот спросит про посещаемость через N минут после начала урока
            </small>
        </div>

        <button class="btn btn-primary" onclick="alert('Функция изменения настроек бота будет реализована позже')">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
            Сохранить настройки бота
        </button>
    </div>
</div>

<!-- Финансовые настройки -->
<div class="card mb-4">
    <div class="card-header">
        <h3 style="margin: 0;">
            <span class="material-icons" style="vertical-align: middle;">account_balance</span>
            Финансовые настройки
        </h3>
    </div>
    <div class="card-body">
        <div class="form-group">
            <label class="form-label" for="owner_share_percent">
                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">percent</span>
                Процент владельца от выручки
            </label>
            <input
                type="number"
                class="form-control"
                id="owner_share_percent"
                value="<?= e($settingsMap['owner_share_percent'] ?? '30') ?>"
                min="0"
                max="100"
                readonly
            >
            <small style="color: var(--text-medium-emphasis); display: block; margin-top: 8px;">
                Ваша доля от общей выручки
            </small>
        </div>

        <div class="form-group">
            <label class="form-label" for="currency">
                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">attach_money</span>
                Валюта
            </label>
            <input
                type="text"
                class="form-control"
                id="currency"
                value="<?= e($settingsMap['currency'] ?? 'RUB') ?>"
                readonly
            >
        </div>

        <button class="btn btn-primary" onclick="alert('Функция изменения финансовых настроек будет реализована позже')">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
            Сохранить финансовые настройки
        </button>
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
        <div class="form-group">
            <label class="form-label" for="timezone">
                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">public</span>
                Часовой пояс
            </label>
            <input
                type="text"
                class="form-control"
                id="timezone"
                value="<?= e($settingsMap['timezone'] ?? 'Europe/Moscow') ?>"
                readonly
            >
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

        <button class="btn btn-primary" onclick="alert('Функция изменения системных настроек будет реализована позже')">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
            Сохранить системные настройки
        </button>
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
        <div class="form-group">
            <label class="form-label" for="current_password">
                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">lock</span>
                Текущий пароль
            </label>
            <input
                type="password"
                class="form-control"
                id="current_password"
                placeholder="Введите текущий пароль"
            >
        </div>

        <div class="form-group">
            <label class="form-label" for="new_password">
                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">lock_open</span>
                Новый пароль
            </label>
            <input
                type="password"
                class="form-control"
                id="new_password"
                placeholder="Введите новый пароль"
            >
        </div>

        <div class="form-group">
            <label class="form-label" for="confirm_password">
                <span class="material-icons" style="font-size: 16px; vertical-align: middle;">lock_reset</span>
                Подтвердите новый пароль
            </label>
            <input
                type="password"
                class="form-control"
                id="confirm_password"
                placeholder="Повторите новый пароль"
            >
        </div>

        <button class="btn btn-primary" onclick="alert('Функция смены пароля будет реализована позже')">
            <span class="material-icons" style="margin-right: 8px; font-size: 18px;">save</span>
            Изменить пароль
        </button>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
