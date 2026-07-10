<?php
/**
 * QRAttend :: Master Security Audit Vault
 * -----------------------------------------------------------------------------
 * Read-only, high-density viewer of the audit_logs table. Login-style events
 * are color-coded distinctly from general ACTION events.
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only admins.
require_admin();

$pageTitle = INSTITUTION_SHORT . ' - Security Audit Vault';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';

$logs = [];
try {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 100');
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (RuntimeException $e) {
    set_flash_message('danger', $e->getMessage());
} catch (PDOException $e) {
    error_log('[QRAttend] audit query error: ' . $e->getMessage());
}

/**
 * Classify an action string as a LOGIN event or a general ACTION.
 */
function audit_event_type(string $action): string
{
    if (preg_match('/login|logout/i', $action)) {
        return 'login';
    }
    return 'action';
}
?>
<main class="container py-4">
    <?= display_flash_message() ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-shield-lock me-2" style="color:var(--brand-primary);"></i>
            Security Audit Vault
        </h1>
        <span class="badge rounded-pill" style="background-color:var(--brand-secondary);">
            Latest <?= count($logs) ?>
        </span>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>User</th>
                            <th>Action Performed</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No audit records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                    $type = audit_event_type($log['action_performed'] ?? '');
                                    $badge = $type === 'login'
                                        ? '<span class="badge rounded-pill text-white" style="background-color:var(--brand-primary);">LOGIN</span>'
                                        : '<span class="badge rounded-pill text-white" style="background-color:var(--brand-secondary);">ACTION</span>';
                                    $when = date('Y-m-d H:i:s', strtotime($log['timestamp']));
                                ?>
                                <tr>
                                    <td class="text-muted"><?= (int) $log['id'] ?></td>
                                    <td><?= $badge ?></td>
                                    <td>
                                        <span class="text-uppercase small"><?= sanitize_input($log['user_type']) ?></span>
                                        #<?= (int) $log['user_id'] ?>
                                    </td>
                                    <td><?= sanitize_input($log['action_performed']) ?></td>
                                    <td class="font-monospace"><?= sanitize_input($log['ip_address']) ?></td>
                                    <td class="text-nowrap"><?= sanitize_input($when) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../../../app/layouts/footer.php';

