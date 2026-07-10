<?php
/**
 * QRAttend :: Strict Role Guards (Authorization Middleware)
 * -----------------------------------------------------------------------------
 * Every protected page requires this file at the very top. It guarantees:
 *   - config + session hardening are loaded
 *   - the session is started under our secure cookie vectors
 *   - the visitor is authenticated AND holds the required role
 *
 * On any violation the engine: drops a red "Access Denied" flash, destroys the
 * invalid session, and redirects to public/login.php.
 */

declare(strict_types=1);

// Load configuration (defines constants + hardens session settings)
require_once __DIR__ . '/../config/config.php';

// Start the session using our pre-defined secure vectors (set in config.php).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper utilities (flash messages, IP capture, sanitization)
require_once __DIR__ . '/../includes/functions.php';

// Absolute path to the public login screen (used for all redirects).
define('LOGIN_URL', APP_URL . '/login.php');

/**
 * Tear down the current session completely and redirect to login.
 * Used whenever an authentication/authorization violation is detected.
 *
 * @param string $flashText Message shown on the login screen
 * @return void  (terminates script)
 */
function auth_deny(string $flashText = 'Access Denied: Unauthorized profile'): void
{
    set_flash_message('danger', $flashText);

    // Destroy all session data
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();

    header('Location: ' . LOGIN_URL);
    exit;
}

/**
 * Gatekeeper: must be logged in at all (any role).
 */
function require_login(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_type'])) {
        auth_deny('Access Denied: Please log in to continue.');
    }
}

/**
 * Gatekeeper: administrator only.
 */
function require_admin(): void
{
    require_login();
    if ($_SESSION['user_type'] !== 'admin') {
        auth_deny();
    }
}

/**
 * Gatekeeper: lecturer only.
 */
function require_lecturer(): void
{
    require_login();
    if ($_SESSION['user_type'] !== 'lecturer') {
        auth_deny();
    }
}

/**
 * Gatekeeper: student only.
 */
function require_student(): void
{
    require_login();
    if ($_SESSION['user_type'] !== 'student') {
        auth_deny();
    }
}

// End of session.php

