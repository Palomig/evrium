<?php
/**
 * Mobile Logout
 */
require_once __DIR__ . '/../config/auth.php';

logout();
header('Location: login.php');
exit;
