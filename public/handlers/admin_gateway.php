<?php
/**
 * QRAttend :: Public Administration Proxy
 * -----------------------------------------------------------------------------
 * Web-served entry point (inside public/) that forwards administrative actions
 * to the protected handler in app/handlers/admin.php. This avoids exposing the
 * app/ tree directly over HTTP and keeps all admin logic outside the web root.
 *
 * Authorization is enforced here AND again inside admin.php (defense in depth).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../app/includes/functions.php';

// Strict admin authorization at the public boundary.
if (($_SESSION['user_type'] ?? null) !== 'admin') {
    // 403 for direct/AJAX hits; redirect for browser form posts.
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        || strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')) === 'application/json') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Forbidden.']);
        exit;
    }
    set_flash_message('danger', 'Access Denied: Administrator privileges required.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Delegate to the protected handler (it re-checks auth and routes by "action").
require_once __DIR__ . '/../../app/handlers/admin.php';
