<?php
/**
 * QRAttend :: Global Helper Framework
 * -----------------------------------------------------------------------------
 * Reusable, security-focused utilities shared across the application:
 *   - log_activity()      : safe audit-log write (prepared statement)
 *   - get_client_ip()     : safe client IP capture (proxy-aware)
 *   - Flash messaging      : set_flash_message() / display_flash_message()
 *   - sanitize_input()    : XSS-safe output escaping
 *
 * This file depends on config.php (constants + session hardening) and must be
 * required AFTER config.php so session_start() has already been invoked by the
 * caller (session.php / auth.php / portal pages).
 */

declare(strict_types=1);

// =============================================================================
// 1. CLIENT IP CAPTURE (proxy-aware, safe)
// =============================================================================

/**
 * Resolve the client IP address, honoring standard proxy headers but falling
 * back to REMOTE_ADDR. Only the first IP in X-Forwarded-For is trusted (the
 * originating client); the rest may be spoofed by the client itself.
 *
 * @return string IPv4 or IPv6 address (max 45 chars to fit the schema)
 */
function get_client_ip(): string
{
    $candidates = [];

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Comma-separated list: client, proxy1, proxy2, ...
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidates[] = trim($parts[0]);
    }
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $candidates[] = trim($_SERVER['HTTP_CLIENT_IP']);
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $candidates[] = trim($_SERVER['REMOTE_ADDR']);
    }

    foreach ($candidates as $ip) {
        if ($ip === '') {
            continue;
        }
        // Validate before returning; reject obviously malformed values
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return substr($ip, 0, 45);
        }
    }

    return '0.0.0.0';
}

// =============================================================================
// 2. AUDIT LOGGING (native prepared statement)
// =============================================================================

/**
 * Write an immutable audit entry to audit_logs.
 *
 * @param PDO    $db        Active PDO connection (from get_db())
 * @param string $user_type admin | lecturer | student
 * @param int    $user_id   Respective row id (0 for anonymous/anon events)
 * @param string $action    Human-readable description of the event
 * @param string $ip_address  Client IP (use get_client_ip())
 * @return bool True on success
 */
function log_activity(PDO $db, string $user_type, int $user_id, string $action, string $ip_address): bool
{
    $sql = 'INSERT INTO audit_logs (user_type, user_id, action_performed, ip_address)
            VALUES (:user_type, :user_id, :action, :ip)';

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_type' => $user_type,
            ':user_id'   => $user_id,
            ':action'    => $action,
            ':ip'        => substr($ip_address, 0, 45),
        ]);
        return true;
    } catch (PDOException $e) {
        // Never leak DB errors to the caller; log server-side only.
        error_log('[QRAttend] audit_log write failed: ' . $e->getMessage());
        return false;
    }
}

// =============================================================================
// 3. FLASH MESSAGE SYSTEM (session-backed, Bootstrap 5 alerts)
// =============================================================================

/**
 * Store a transient flash message that survives exactly one redirect.
 *
 * @param string $type success | danger | warning | info  (Bootstrap context)
 * @param string $text Message body
 */
function set_flash_message(string $type, string $text): void
{
    // Ensure session is active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'type' => $type,
        'text' => $text,
    ];
}

/**
 * Render and immediately clear the pending flash message as a dismissible
 * Bootstrap 5 alert. Returns an empty string when nothing is queued.
 *
 * @return string HTML markup (safe to echo)
 */
function display_flash_message(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $type = $_SESSION['flash']['type'] ?? 'info';
    // Escape the message text to prevent XSS via flash content
    $text = sanitize_input($_SESSION['flash']['text'] ?? '');

    // Clear so it shows only once
    unset($_SESSION['flash']);

    return <<<HTML
<div class="alert alert-{$type} alert-dismissible fade show" role="alert">
    {$text}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
HTML;
}

// =============================================================================
// 4. INPUT SANITIZATION (XSS-safe output escaping)
// =============================================================================

/**
 * Escape arbitrary text for safe HTML output. Use when echoing any
 * user-controlled or database-sourced string into markup.
 *
 * @param mixed $data Raw value
 * @return string Escaped string
 */
function sanitize_input($data): string
{
    if ($data === null) {
        return '';
    }
    // Trim, cast to string, and convert special chars to entities.
    return htmlspecialchars(
        trim((string) $data),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
}

// End of functions.php

