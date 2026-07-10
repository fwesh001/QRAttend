<?php
/**
 * QRAttend :: Root Application Router
 * -----------------------------------------------------------------------------
 * Domain entry path. Starts the secure session, then routes authenticated
 * users to their role portal and anonymous visitors to the login screen.
 */
require_once __DIR__ . '/../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_type']) && !empty($_SESSION['user_id'])) {
    // Already authenticated -> go straight to the role dashboard.
    header('Location: ' . APP_URL . '/portals/' . $_SESSION['user_type'] . '/dashboard.php');
    exit;
}

// Anonymous -> login.
header('Location: ' . APP_URL . '/login.php');
exit;

