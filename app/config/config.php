<?php declare(strict_types=1);
/**
 * QRAttend :: Application Configuration & Bootstrap
 * -----------------------------------------------------------------------------
 * Responsibilities:
 *   1. Load environment configuration (real env vars -> .env file -> safe defaults)
 *   2. Harden PHP sessions BEFORE session_start() is ever called
 *   3. Define global application + institution + branding constants
 *
 * This file MUST be required once, at the very top of every entry script,
 * BEFORE any output is sent and BEFORE session_start(). It is intentionally
 * placed outside the web root (app/config) so it can never be requested
 * directly over HTTP.
 */



// All your other code, requires, and variables follow below...
// =============================================================================
// 1. ENVIRONMENT LOADER
//    Priority: real $_ENV / getenv()  ->  parsed .env file  ->  safe defaults
// =============================================================================

/**
 * Read a configuration value using the priority chain described above.
 *
 * @param string $key     Variable name (e.g. "DB_HOST")
 * @param mixed  $default Fallback when nothing is found
 * @return mixed
 */
function qrattend_env(string $key, $default = null)
{
    // 1. Real environment variables (set by the web server / process manager)
    $val = getenv($key);
    if ($val !== false && $val !== null) {
        return $val;
    }

    // 2. Parsed .env file (cached statically so we only parse once)
    static $dotEnv = null;
    if ($dotEnv === null) {
        $dotEnv = [];
        $envPath = __DIR__ . '/../../.env';
        if (is_file($envPath) && is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                // Skip comments and blank lines
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }
                // Only accept KEY=VALUE pairs
                if (strpos($line, '=') === false) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                // Strip surrounding quotes if present
                if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'")) {
                    $v = substr($v, 1, -1);
                }
                $dotEnv[$k] = $v;
            }
        }
    }

    if (array_key_exists($key, $dotEnv)) {
        return $dotEnv[$key];
    }

    // 3. Safe local defaults (XAMPP-friendly)
    return $default;
}

// =============================================================================
// 2. SESSION SECURITY HARDENING
//    Must run before session_start() so the cookie flags take effect.
// =============================================================================

// Block JavaScript access to the session cookie (mitigates XSS token theft)
ini_set('session.cookie_httponly', '1');

// Only transmit the cookie over HTTPS when we are actually on a secure origin.
// On plain-HTTP local dev (XAMPP) this stays false so login still works.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
ini_set('session.cookie_secure', $isHttps ? '1' : '0');

// Force sessions to use only cookies (no URL-based session IDs)
ini_set('session.use_only_cookies', '1');

// Additional hardening: strict same-site policy + refuse transitive ids
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

// Session lifetime: 30 minutes of inactivity
ini_set('session.gc_maxlifetime', '1800');

// =============================================================================
// 3. GLOBAL APPLICATION CONSTANTS
// =============================================================================

// Application environment: "development" | "production"
define('APP_ENV', qrattend_env('APP_ENV', 'development'));
define('APP_DEBUG', APP_ENV === 'development');

// System timezone (Nasarawa / Nigeria)
define('APP_TIMEZONE', 'Africa/Lagos');
date_default_timezone_set(APP_TIMEZONE);

// Base application URL (used for absolute links / redirects).
// Prefer an explicit APP_URL env var; otherwise auto-detect from the current
// request so the app works on any host (localhost, onrender, etc.) without
// manual configuration. The web root is the "public" directory, so we strip
// everything after "/public" from the script path.
function qrattend_base_url(): string
{
    $env = qrattend_env('APP_URL', '');
    if ($env !== '') {
        return rtrim($env, '/');
    }
    
    // Detect protocol, checking HTTPS and proxy headers
    $scheme = 'http';
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    ) {
        $scheme = 'https';
    }
    
    // Detect host, supporting proxy header if present
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    // If multiple hosts are present in X-Forwarded-Host, use the first one
    if (strpos($host, ',') !== false) {
        $hosts = explode(',', $host);
        $host = trim($hosts[0]);
    }
    
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $pos = strpos($script, '/public/');
    $basePath = $pos !== false ? substr($script, 0, $pos + 7) : dirname($script);
    return $scheme . '://' . $host . rtrim($basePath, '/');
}
define('APP_URL', qrattend_base_url());

// Path constants (absolute, filesystem-safe)
define('ROOT_PATH',   __DIR__ . '/../..');
define('APP_PATH',    __DIR__ . '/..');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('LAYOUTS_PATH', APP_PATH . '/layouts');

// Institution metadata (Federal Polytechnic Nasarawa)
define('INSTITUTION_NAME',    'Federal Polytechnic Nasarawa');
define('INSTITUTION_SHORT',   'FPN');
define('INSTITUTION_DEPT',    'Department of Computer Science');
define('INSTITUTION_EMAIL',   'admin@fpn.edu.ng');

// Attendance business rules (mirrors PRD thresholds)
define('ATTENDANCE_THRESHOLD', 75);   // % minimum to clear exams
define('SESSION_DEFAULT_MINUTES', 15);
define('TOKEN_ROTATE_DEFAULT', 5);    // auto-rotate after N scans

// =============================================================================
// 4. BRANDING PALETTE  (Federal Polytechnic Nasarawa)
//    Primary Green · Secondary Orange · White backgrounds · Black structure
// =============================================================================
define('BRAND_PRIMARY',   '#0B6E4F'); // Primary Green
define('BRAND_PRIMARY_D', '#08543C'); // Darker green (hover/active)
define('BRAND_SECONDARY', '#F39200'); // Secondary Orange
define('BRAND_SECONDARY_D','#D67E00');// Darker orange
define('BRAND_BG',        '#FFFFFF'); // Background White
define('BRAND_SURFACE',   '#F5F7F6'); // Subtle off-white surface
define('BRAND_TEXT',      '#1A1A1A'); // Structural Black (text)
define('BRAND_DANGER',    '#D32F2F'); // At-risk / error red
define('BRAND_SUCCESS',   '#2E7D32'); // Success green

// Convenience: CSS custom-property block so layouts can echo it once in :root
define('BRAND_CSS_VARS', <<<CSS
  --brand-primary:   #0B6E4F;
  --brand-primary-d: #08543C;
  --brand-secondary: #F39200;
  --brand-secondary-d:#D67E00;
  --brand-bg:        #FFFFFF;
  --brand-surface:   #F5F7F6;
  --brand-text:      #1A1A1A;
  --brand-danger:    #D32F2F;
  --brand-success:   #2E7D32;
CSS);

// =============================================================================
// 5. DATABASE CREDENTIALS (exposed to database.php via constants)
//    Never echo these; they are consumed only by the PDO layer.
// =============================================================================
define('DB_HOST',    qrattend_env('DB_HOST', 'localhost'));
define('DB_NAME',    qrattend_env('DB_NAME', 'qrattend'));
define('DB_USER',    qrattend_env('DB_USER', 'root'));
define('DB_PASS',    qrattend_env('DB_PASS', ''));
define('DB_CHARSET', qrattend_env('DB_CHARSET', 'utf8mb4'));

// Load the PDO connection manager so get_db() is available to every script
// that includes config.php. database.php self-guards against double-loading.
require_once __DIR__ . '/database.php';

// End of config.php

