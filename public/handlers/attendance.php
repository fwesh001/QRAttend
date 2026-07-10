<?php declare(strict_types=1);
/**
 * QRAttend :: Public Attendance Proxy
 * -----------------------------------------------------------------------------
 * Web-served entry point (inside public/) that forwards student attendance checks
 * to the protected handler in app/handlers/attendance.php. This avoids exposing the
 * app/ tree directly over HTTP and keeps attendance processing outside the web root.
 */

require_once __DIR__ . '/../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../app/includes/functions.php';

// Strict student check at the public boundary.
if (($_SESSION['user_type'] ?? null) !== 'student') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

// Delegate to the protected handler.
require_once __DIR__ . '/../../app/handlers/attendance.php';
