<?php
/**
 * Выход из системы
 * Система учёта зарплаты преподавателей
 */

require_once __DIR__ . '/config/auth.php';

logout();
header('Location: /zarplata/login.php');
exit;
