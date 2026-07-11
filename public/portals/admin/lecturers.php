<?php
/**
 * QRAttend :: Admin Faculty (Lecturer) Management Suite
 * -----------------------------------------------------------------------------
 * Add-instructor form + live faculty registry table.
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only admins.
require_admin();

$pageTitle = INSTITUTION_SHORT . ' - Faculty Management';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';

// Departments for the select dropdown.
$departments = [];
$lecturers   = [];
try {
    $db = get_db();
    $depStmt = $db->prepare('SELECT id, name FROM departments ORDER BY name ASC');
    $depStmt->execute();
    $departments = $depStmt->fetchAll(PDO::FETCH_ASSOC);

    $lecStmt = $db->prepare(
        'SELECT l.*, d.name AS dept_name
         FROM lecturers l
         LEFT JOIN departments d ON l.department_id = d.id
         ORDER BY l.id DESC'
    );
    $lecStmt->execute();
    $lecturers = $lecStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (RuntimeException $e) {
    set_flash_message('danger', $e->getMessage());
} catch (PDOException $e) {
    error_log('[QRAttend] lecturer query error: ' . $e->getMessage());
}
?>
<main class="container py-4">
    <?= display_flash_message() ?>

    <h1 class="h4 fw-bold mb-4">
        <i class="bi bi-person-video3 me-2" style="color:var(--brand-primary);"></i>
        Faculty Management
    </h1>

    <div class="row g-4">
        <!-- Add instructor form -->
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-3">
                    <h2 class="h6 fw-bold mb-0">Add Instructor</h2>
                </div>
                <div class="card-body">
                    <form action="../../handlers/admin_gateway.php" method="POST">
                        <input type="hidden" name="action" value="add_lecturer">

                        <div class="mb-3">
                            <label for="staff_no" class="form-label fw-semibold">Staff Number</label>
                            <input type="text" class="form-control" id="staff_no" name="staff_no"
                                   placeholder="STAFF/001" required>
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name"
                                   placeholder="Dr. Ada Okafor" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   placeholder="name@fpn.edu.ng" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Temporary Password</label>
                            <input type="text" class="form-control" id="password" name="password"
                                   placeholder="FedpoNas123! (default if blank)" autocomplete="new-password">
                            <div class="form-text">Leave blank to use the secure default. Share this with the lecturer for first sign-in.</div>
                        </div>
                        <div class="mb-3">
                            <label for="department_id" class="form-label fw-semibold">Department</label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="" selected disabled>Select department…</option>
                                <?php foreach ($departments as $dep): ?>
                                    <option value="<?= (int) $dep['id'] ?>">
                                        <?= sanitize_input($dep['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit"
                                class="btn w-100 py-2 fw-bold text-white"
                                style="background-color:var(--brand-secondary);">
                            <i class="bi bi-person-plus me-1"></i> Add Instructor
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Faculty registry -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
                    <h2 class="h6 fw-bold mb-0">Current Faculty</h2>
                    <span class="badge rounded-pill" style="background-color:var(--brand-primary);">
                        <?= count($lecturers) ?> shown
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff No</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lecturers)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            No instructors added yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($lecturers as $lec): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= sanitize_input($lec['staff_no']) ?></td>
                                            <td><?= sanitize_input($lec['name']) ?></td>
                                            <td class="small text-muted"><?= sanitize_input($lec['email']) ?></td>
                                            <td><?= sanitize_input($lec['dept_name'] ?? '—') ?></td>
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

