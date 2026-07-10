<?php
/**
 * QRAttend :: Admin Student Provisioning Interface
 * -----------------------------------------------------------------------------
 * Upload card (CSV bulk import) + formatting alert + live student registry.
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only admins.
require_admin();

$pageTitle = INSTITUTION_SHORT . ' - Student Provisioning';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';

// Live registry (latest 50 students with department name).
$students = [];
try {
    $db = get_db();
    $stmt = $db->prepare(
        'SELECT s.id, s.matric_no, s.name, s.level, s.email, d.name AS dept_name
         FROM students s
         LEFT JOIN departments d ON s.department_id = d.id
         ORDER BY s.id DESC
         LIMIT 50'
    );
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (RuntimeException $e) {
    set_flash_message('danger', $e->getMessage());
} catch (PDOException $e) {
    error_log('[QRAttend] student registry query error: ' . $e->getMessage());
}
?>
<main class="container py-4">
    <?= display_flash_message() ?>

    <h1 class="h4 fw-bold mb-4">
        <i class="bi bi-people-fill me-2" style="color:var(--brand-primary);"></i>
        Student Provisioning
    </h1>

    <div class="row g-4">
        <!-- Upload card -->
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-3">
                    <h2 class="h6 fw-bold mb-0">Bulk Import Students (CSV)</h2>
                </div>
                <div class="card-body">
                    <form action="../../../app/handlers/admin.php" method="POST"
                          enctype="multipart/form-data">
                        <input type="hidden" name="action" value="bulk_import_students">

                        <div class="mb-3">
                            <label for="csv_file" class="form-label fw-semibold">CSV File</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file"
                                   accept=".csv,text/csv" required>
                        </div>

                        <button type="submit"
                                class="btn w-100 py-2 fw-bold text-white"
                                style="background-color:var(--brand-secondary);">
                            <i class="bi bi-upload me-1"></i> Import Students
                        </button>
                    </form>
                </div>
            </div>

            <!-- Formatting alert -->
            <div class="alert mt-3 rounded-4" role="alert"
                 style="background-color:var(--brand-surface); border:1px solid #e3e6e5;">
                <h3 class="h6 fw-bold mb-2">
                    <i class="bi bi-info-circle me-1" style="color:var(--brand-secondary);"></i>
                    CSV Format Requirements
                </h3>
                <p class="small mb-1">The first row must be a header with these exact columns:</p>
                <ol class="small mb-1 ps-3">
                    <li><code>Matric Number</code></li>
                    <li><code>Full Name</code></li>
                    <li><code>Level</code> (e.g., HND II)</li>
                    <li><code>Department ID</code> (integer)</li>
                    <li><code>Email</code></li>
                </ol>
                <p class="small mb-0 text-muted">
                    Default password for all imports: <code>FedpoNas123!</code> (bcrypt-hashed).
                    Duplicate matric/emails update the existing record.
                </p>
            </div>
        </div>

        <!-- Live registry table -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
                    <h2 class="h6 fw-bold mb-0">Current Registry (Latest 50)</h2>
                    <span class="badge rounded-pill" style="background-color:var(--brand-primary);">
                        <?= count($students) ?> shown
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Matric No</th>
                                    <th>Name</th>
                                    <th>Level</th>
                                    <th>Department</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No students imported yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $st): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= sanitize_input($st['matric_no']) ?></td>
                                            <td><?= sanitize_input($st['name']) ?></td>
                                            <td><?= sanitize_input($st['level']) ?></td>
                                            <td><?= sanitize_input($st['dept_name'] ?? '—') ?></td>
                                            <td class="small text-muted"><?= sanitize_input($st['email']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../../../app/layouts/footer.php';

