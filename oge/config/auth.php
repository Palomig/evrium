<?php
/**
 * Simple Authentication for OGE Admin
 */

// Admin password (change this!)
define('OGE_ADMIN_PASSWORD', 'oge2025admin');

/**
 * Start session if not started
 */
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    initSession();
    return isset($_SESSION['oge_admin']) && $_SESSION['oge_admin'] === true;
}

/**
 * Require authentication or redirect to login
 */
function requireAuth(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Login with password
 */
function login(string $password): bool {
    initSession();

    if ($password === OGE_ADMIN_PASSWORD) {
        $_SESSION['oge_admin'] = true;
        $_SESSION['oge_login_time'] = time();
        return true;
    }

    return false;
}

/**
 * Logout
 */
function logout(): void {
    initSession();
    unset($_SESSION['oge_admin']);
    unset($_SESSION['oge_login_time']);
    session_destroy();
}
