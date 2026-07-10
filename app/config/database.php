<?php declare(strict_types=1);
/**
 * QRAttend :: Database Connection Manager (PDO)
 * -----------------------------------------------------------------------------
 * Provides a single, reusable PDO instance (singleton) configured to satisfy
 * the project's Non-Functional Requirements:
 *   - SQL Injection Defense : native prepared statements (emulation OFF)
 *   - Clean reads           : associative fetch mode by default
 *   - Strict error tracing  : exceptions instead of silent failures
 *
 * On connection failure it logs a sanitized message server-side and surfaces a
 * generic, non-leaking message to the client (no host/user/password exposed).
 */

// Ensure configuration (DB_* constants + session hardening) is loaded first.
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

// Holds the single shared connection for the request lifecycle.
$pdo = null;

/**
 * Return the shared PDO instance, creating it on first call.
 *
 * @return PDO
 * @throws RuntimeException When the connection cannot be established.
 */
function db_connect(): PDO
{
    global $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // DSN built from constants defined in config.php (no user input concatenated)
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    // Security / performance options required by the PRD NFRs.
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // strict error tracing
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // clean associative reads
        PDO::ATTR_EMULATE_PREPARES  => false,                    // native prepared stmts (no SQLi)
        PDO::ATTR_PERSISTENT        => false,                    // fresh conn per request
        PDO::MYSQL_ATTR_FOUND_ROWS  => true,                     // accurate row counts
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        // Extra charset hardening at the connection level
        $pdo->exec('SET NAMES ' . DB_CHARSET . ' COLLATE utf8mb4_unicode_ci');
        return $pdo;
    } catch (PDOException $e) {
        // Sanitized server-side log (includes detail for the admin, NOT the client)
        error_log(
            sprintf(
                '[QRAttend] DB connection failed | host=%s db=%s code=%s msg=%s',
                DB_HOST,
                DB_NAME,
                $e->getCode(),
                $e->getMessage()
            )
        );

        // Generic, leak-free message for the browser
        if (defined('APP_DEBUG') && APP_DEBUG) {
            // In development only, reveal a safe hint (no credentials)
            throw new RuntimeException(
                'Database connection failed. Check DB_HOST/DB_NAME in config. '
                . '(' . $e->getCode() . ')'
            );
        }
        throw new RuntimeException(
            'System temporarily unavailable. Please contact the administrator.'
        );
    }
}

/**
 * Convenience accessor so callers can do:  $db = get_db();
 *
 * @return PDO
 */
function get_db(): PDO
{
    return db_connect();
}

// End of database.php

