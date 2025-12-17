<?php
/**
 * Mobile Device Detection & Redirect Helper
 * Include this at the top of zarplata/*.php files for auto-redirect
 */

/**
 * Check if current device is mobile
 * @return bool
 */
function isMobileDevice(): bool {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Common mobile keywords
    $mobileKeywords = [
        'Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'webOS',
        'BlackBerry', 'Windows Phone', 'Opera Mini', 'IEMobile'
    ];

    foreach ($mobileKeywords as $keyword) {
        if (stripos($userAgent, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Check if user explicitly requested desktop version
 * @return bool
 */
function wantsDesktopVersion(): bool {
    // Check cookie
    if (isset($_COOKIE['prefer_desktop']) && $_COOKIE['prefer_desktop'] === '1') {
        return true;
    }

    // Check URL parameter
    if (isset($_GET['desktop']) && $_GET['desktop'] === '1') {
        // Set cookie for 24 hours
        setcookie('prefer_desktop', '1', time() + 86400, '/zarplata/');
        return true;
    }

    return false;
}

/**
 * Check if user explicitly requested mobile version
 * @return bool
 */
function wantsMobileVersion(): bool {
    if (isset($_GET['mobile']) && $_GET['mobile'] === '1') {
        // Clear desktop preference
        setcookie('prefer_desktop', '', time() - 3600, '/zarplata/');
        return true;
    }
    return false;
}

/**
 * Redirect to mobile version if needed
 * Call this function at the top of main zarplata pages
 * @param string $currentPage - current page filename (e.g., 'schedule.php')
 */
function redirectToMobileIfNeeded(string $currentPage = ''): void {
    // Don't redirect if already in mobile folder
    if (strpos($_SERVER['REQUEST_URI'], '/zarplata/mobile/') !== false) {
        return;
    }

    // Don't redirect API calls
    if (strpos($_SERVER['REQUEST_URI'], '/zarplata/api/') !== false) {
        return;
    }

    // User explicitly wants desktop
    if (wantsDesktopVersion()) {
        return;
    }

    // User explicitly wants mobile OR is on mobile device
    if (wantsMobileVersion() || isMobileDevice()) {
        $mobilePath = '/zarplata/mobile/';

        if ($currentPage) {
            $mobilePath .= $currentPage;
        } else {
            // Try to detect current page from URI
            $uri = $_SERVER['REQUEST_URI'];
            $baseName = basename(parse_url($uri, PHP_URL_PATH));
            if ($baseName && $baseName !== 'zarplata' && strpos($baseName, '.php') !== false) {
                $mobilePath .= $baseName;
            } else {
                $mobilePath .= 'index.php';
            }
        }

        // Preserve query string (except desktop/mobile params)
        $queryParams = $_GET;
        unset($queryParams['desktop'], $queryParams['mobile']);
        if (!empty($queryParams)) {
            $mobilePath .= '?' . http_build_query($queryParams);
        }

        header('Location: ' . $mobilePath);
        exit;
    }
}

/**
 * Get link to switch to desktop version
 * @return string
 */
function getDesktopVersionLink(): string {
    $currentUri = $_SERVER['REQUEST_URI'];
    $separator = strpos($currentUri, '?') !== false ? '&' : '?';
    return str_replace('/mobile/', '/', $currentUri) . $separator . 'desktop=1';
}

/**
 * Get link to switch to mobile version (for desktop pages)
 * @return string
 */
function getMobileVersionLink(): string {
    $currentUri = $_SERVER['REQUEST_URI'];
    $baseName = basename(parse_url($currentUri, PHP_URL_PATH));

    // Handle query string
    $queryParams = $_GET;
    unset($queryParams['desktop']);
    $queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';

    return '/zarplata/mobile/' . $baseName . $queryString;
}
