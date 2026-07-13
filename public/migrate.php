<?php
/**
 * QRAttend :: ONE-TIME SCHEMA MIGRATION
 * -----------------------------------------------------------------------------
 * Applies the two missing columns to the LIVE database using the app's own
 * PDO connection (get_db()). Safe + idempotent: each ALTER is attempted
 * independently and a missing-column error is treated as "already applied".
 *
 * SECURITY: protected by a one-time token in the URL. DELETE THIS FILE
 * immediately after running it (it lives in the public web root).
 */

require_once __DIR__ . '/../app/config/config.php';

// One-time token — change if you like, then visit:
//   https://<your-domain>/migrate.php?token=qrattend_migrate_2026
const MIGRATION_TOKEN = 'qrattend_migrate_2026';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['token'] ?? '') !== MIGRATION_TOKEN) {
    http_response_code(403);
    echo "Forbidden. Provide ?token=...\n";
    exit;
}

$statements = [
    "ALTER TABLE lecturers
       ADD COLUMN default_duration_minutes INT UNSIGNED NOT NULL DEFAULT 15
       COMMENT 'lecturer preferred session length'",
    "ALTER TABLE attendance_sessions
       ADD COLUMN max_students INT UNSIGNED NULL DEFAULT NULL
       COMMENT 'lecturer-set class size for this session'",
];

echo "QRAttend schema migration\n";
echo "DB host: " . DB_HOST . "  DB name: " . DB_NAME . "\n\n";

try {
    $db = get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo "CONNECTION FAILED: " . $e->getMessage() . "\n";
    exit;
}

foreach ($statements as $i => $sql) {
    $label = $i === 0 ? 'lecturers.default_duration_minutes' : 'attendance_sessions.max_students';
    try {
        $db->exec($sql);
        echo "[OK]   Added column: {$label}\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false
            || strpos($e->getMessage(), 'already exists') !== false) {
            echo "[SKIP] Column already exists: {$label}\n";
        } else {
            echo "[ERR]  {$label}: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nDone. DELETE migrate.php from the server now.\n";
