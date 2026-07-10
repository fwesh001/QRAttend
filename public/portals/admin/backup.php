<?php
/**
 * QRAttend :: System Maintenance & Cleanup
 * -----------------------------------------------------------------------------
 * Danger Zone for destructive admin operations (purge logs) plus a System
 * Health readout (PHP version + DB connectivity).
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only admins.
require_admin();

$pageTitle = INSTITUTION_SHORT . ' - System Maintenance';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';

// System health probes.
$phpVersion = PHP_VERSION;
$dbStatus   = 'Disconnected';
try {
    $db = get_db();
    // Lightweight connectivity check.
    $db->query('SELECT 1');
    $dbStatus = 'Connected (' . DB_HOST . ' / ' . DB_NAME . ')';
} catch (RuntimeException $e) {
    $dbStatus = 'Error: ' . $e->getMessage();
}
?>
<main class="container py-4">
    <?= display_flash_message() ?>

    <h1 class="h4 fw-bold mb-4">
        <i class="bi bi-tools me-2" style="color:var(--brand-primary);"></i>
        System Maintenance &amp; Cleanup
    </h1>

    <div class="row g-4">
        <!-- System info -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-3">
                    <h2 class="h6 fw-bold mb-0">System Health</h2>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">PHP Version</span>
                            <span class="fw-semibold"><?= sanitize_input($phpVersion) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Database</span>
                            <span class="fw-semibold"><?= sanitize_input($dbStatus) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Application Env</span>
                            <span class="fw-semibold text-uppercase"><?= sanitize_input(APP_ENV) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Timezone</span>
                            <span class="fw-semibold"><?= sanitize_input(APP_TIMEZONE) ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="col-12 col-lg-5">
            <div class="card border-danger border-2 rounded-4">
                <div class="card-header bg-danger text-white border-0">
                    <h2 class="h6 fw-bold mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Danger Zone
                    </h2>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Purging permanently removes all audit log records. This action cannot be
                        undone from the interface (a secure sys_log entry is still written).
                    </p>

                    <form action="../../handlers/admin_gateway.php" method="POST"
                          onsubmit="return confirm('WARNING: This will permanently delete ALL audit logs. Continue?');">
                        <input type="hidden" name="action" value="purge_logs">
                        <button type="submit" class="btn btn-outline-danger w-100 py-2 fw-bold">
                            <i class="bi bi-trash3 me-1"></i> Purge Logs
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../../../app/layouts/footer.php';

