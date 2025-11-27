<?php
/**
 * Очистка старых записей telegram_updates
 * Запускать раз в день через cron:
 * 0 3 * * * php /путь/к/zarplata/bot/cleanup_updates.php
 */

require_once __DIR__ . '/config.php';

try {
    // Удаляем записи старше 7 дней
    $deleted = dbExecute(
        "DELETE FROM telegram_updates WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
        []
    );

    error_log("[Telegram Bot Cleanup] Deleted old update records (older than 7 days)");

    // Опционально: логируем статистику
    $count = dbQueryOne("SELECT COUNT(*) as count FROM telegram_updates", []);
    error_log("[Telegram Bot Cleanup] Remaining records in telegram_updates: " . ($count['count'] ?? 0));

} catch (Exception $e) {
    error_log("[Telegram Bot Cleanup] Error: " . $e->getMessage());
}
