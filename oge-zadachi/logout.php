<?php
/**
 * Logout and redirect to public page
 */
require_once __DIR__ . '/config/auth.php';

logout();
header('Location: index.php');
exit;
