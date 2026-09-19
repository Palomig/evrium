<?php
/**
 * Точка входа мобильного PWA (start_url манифеста).
 * Дашборда в мобильной версии нет — всегда открываем расписание.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireAuth();
header('Location: schedule.php');
exit;
