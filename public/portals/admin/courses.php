<?php
/**
 * QRAttend :: Admin Curriculum (Course) Management Suite
 * -----------------------------------------------------------------------------
 * Add-course form + live curriculum registry table.
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only admins.
require_admin();

$pageTitle = INSTITUTION_SHORT . ' - Curriculum Management';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';

// Live curriculum list.
$courses = [];
try {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM courses ORDER BY course_code ASC');
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (RuntimeException $e) {
    set_flash_message('danger', $e->getMessage());
} catch (PDOException $e) {
    error_log('[QRAttend] course query error: ' . $e->getMessage());
}
?>
<main class="container py-4">
    <?= display_flash_message() ?>

    <h1 class="h4 fw-bold mb-4">
        <i class="bi bi-book-fill me-2" style="color:var(--brand-primary);"></i>
        Curriculum Management
    </h1>

    <div class="row g-4">
        <!-- Add course form -->
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-3">
                    <h2 class="h6 fw-bold mb-0">Add Course</h2>
                </div>
                <div class="card-body">
                    <form action="../../handlers/admin_gateway.php" method="POST">
                        <input type="hidden" name="action" value="add_course">

                        <div class="mb-3">
                            <label for="course_code" class="form-label fw-semibold">Course Code</label>
                            <input type="text" class="form-control" id="course_code" name="course_code"
                                   placeholder="CSC 401" required>
                        </div>
                        <div class="mb-3">
                            <label for="course_title" class="form-label fw-semibold">Course Title</label>
                            <input type="text" class="form-control" id="course_title" name="course_title"
                                   placeholder="Database Management Systems" required>
                        </div>
                        <div class="mb-3">
                            <label for="credit_units" class="form-label fw-semibold">Credit Units</label>
                            <select class="form-select" id="credit_units" name="credit_units" required>
                                <option value="" selected disabled>Select units…</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <button type="submit"
                                class="btn w-100 py-2 fw-bold text-white"
                                style="background-color:var(--brand-secondary);">
                            <i class="bi bi-plus-circle me-1"></i> Add Course
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Curriculum registry -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
                    <h2 class="h6 fw-bold mb-0">Course Registry</h2>
                    <span class="badge rounded-pill" style="background-color:var(--brand-primary);">
                        <?= count($courses) ?> courses
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Title</th>
                                    <th>Units</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($courses)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            No courses added yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= sanitize_input($course['course_code']) ?></td>
                                            <td><?= sanitize_input($course['course_title']) ?></td>
                                            <td><?= (int) $course['credit_units'] ?></td>
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

