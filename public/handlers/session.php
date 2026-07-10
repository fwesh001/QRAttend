<?php declare(strict_types=1);
/**
 * QRAttend :: Public Session Proxy
 * -----------------------------------------------------------------------------
 * Web-served entry point (inside public/) that forwards session operations
 * to the protected handler in app/handlers/session.php. This avoids exposing the
 * app/ tree directly over HTTP and keeps session logic outside the web root.
 */

require_once __DIR__ . '/../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../app/includes/functions.php';

// Strict lecturer check at the public boundary.
if (($_SESSION['user_type'] ?? null) !== 'lecturer') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

// Delegate to the protected handler.
require_once __DIR__ . '/../../app/handlers/session.php';
