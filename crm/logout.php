<?php
/**
 * Evrium CRM - Logout
 * Выход из системы
 */

require_once __DIR__ . '/config/auth.php';

logout();
redirect('/crm/login.php');
