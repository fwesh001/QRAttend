<?php
/**
 * QRAttend :: Login & Logout Transaction Processing Engine
 * -----------------------------------------------------------------------------
 * POST endpoint for authentication. Never call this file directly via GET for
 * login; it only acts on a submitted form. It:
 *   - sanitizes + validates the submitted credentials and role
 *   - queries the correct table with a native prepared statement
 *   - verifies the bcrypt hash with password_verify()
 *   - on success: regenerates session id (fixation defense), binds session
 *     metadata, logs the event, flashes a welcome, routes to the portal
 *   - on failure: logs the suspicious attempt, flashes an error, returns to login
 *   - supports a ?action=logout branch for secure session termination
 */

declare(strict_types=1);

// Load config (constants + session hardening) and start the secure session.
require_once __DIR__ . '/../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

// -----------------------------------------------------------------------------
// LOGOUT BRANCH (?action=logout)
// -----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_type'])) {
        try {
            $db = get_db();
            log_activity(
                $db,
                (string) $_SESSION['user_type'],
                (int) $_SESSION['user_id'],
                'Logout',
                get_client_ip()
            );
        } catch (RuntimeException $e) {
            // DB may be down; logout must still succeed.
            error_log('[QRAttend] logout audit failed: ' . $e->getMessage());
        }
    }

    // Wipe everything
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

    set_flash_message('info', 'You have been logged out successfully.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// -----------------------------------------------------------------------------
// LOGIN BRANCH (POST only)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Direct GET hit on this script -> just send them to login.
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// 1. Sanitize + validate inputs
// NOTE: identity is only trimmed here. SQL safety is guaranteed by the native
// prepared statement below, so we must NOT htmlspecialchars() it before the
// lookup (that would corrupt values containing &, <, >, etc.). Output escaping
// is applied only when the value is later echoed to HTML.
$role     = strtolower(trim((string) ($_POST['role'] ?? '')));
$identity = trim((string) ($_POST['identity'] ?? ''));  // username / email / matric / staff_no
$password = (string) ($_POST['password'] ?? '');        // raw; never escaped, only verified

// Map role -> table + identity column + display column
$roleMap = [
    'admin' => [
        'table'   => 'administrators',
        'id_col'  => 'id',
        'ident'   => ['username', 'email'],
        'label'   => 'username',
    ],
    'lecturer' => [
        'table'   => 'lecturers',
        'id_col'  => 'id',
        'ident'   => ['staff_no', 'email'],
        'label'   => 'staff_no',
    ],
    'student' => [
        'table'   => 'students',
        'id_col'  => 'id',
        'ident'   => ['matric_no', 'email'],
        'label'   => 'matric_no',
    ],
];

if (!array_key_exists($role, $roleMap) || $identity === '' || $password === '') {
    set_flash_message('danger', 'Invalid credentials or role routing mismatch.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$cfg = $roleMap[$role];

try {
    $db = get_db();

    // 2. Airtight prepared statement: match identity against any allowed column.
    $identCols = $cfg['ident'];
    $conditions = [];
    $params = [];
    foreach ($identCols as $i => $col) {
        $placeholder = ":ident_{$i}";
        $conditions[] = "`{$col}` = {$placeholder}";
        $params[$placeholder] = $identity;
    }
    $where = implode(' OR ', $conditions);

    $sql = "SELECT `{$cfg['id_col']}` AS id, `name`, `{$cfg['label']}` AS label,
                   `password`
            FROM `{$cfg['table']}`
            WHERE {$where}
            LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $ip = get_client_ip();

    if ($user === false || !password_verify($password, $user['password'])) {
        // 3a. Authentication failure -> log suspicious footprint
        log_activity(
            $db,
            $role,
            0,
            'Failed login attempt (invalid credentials or role routing mismatch) for identity: ' . $identity,
            $ip
        );
        set_flash_message('danger', 'Invalid credentials or role routing mismatch.');
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }

    // 3b. Authentication success
    // Regenerate session id to neutralize session-fixation attacks.
    session_regenerate_id(true);

    // Bind metadata to session slots
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_label'] = $user['label'];   // username / staff_no / matric_no
    $_SESSION['user_type'] = $role;

    // Audit the successful login
    log_activity($db, $role, (int) $user['id'], 'Successful login', $ip);

    set_flash_message('success', 'Welcome back, ' . sanitize_input($user['name']) . '!');

    // Route to the role's portal dashboard
    header('Location: ' . APP_URL . '/portals/' . $role . '/dashboard.php');
    exit;

} catch (RuntimeException $e) {
    // DB connection failure (already logged inside database.php)
    set_flash_message('danger', $e->getMessage());
    header('Location: ' . APP_URL . '/login.php');
    exit;
} catch (PDOException $e) {
    // Unexpected query error -> log server-side, generic client message
    error_log('[QRAttend] login query error: ' . $e->getMessage());
    set_flash_message('danger', 'Invalid credentials or role routing mismatch.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// End of auth.php

