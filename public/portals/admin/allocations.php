<?php
/**
 * QRAttend :: Admin Course Allocation Matrix
 * -----------------------------------------------------------------------------
 * Bind lecturers to courses and view the current allocation ledger.
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only admins.
require_admin();

$pageTitle = INSTITUTION_SHORT . ' - Course Allocations';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';

$lecturers = [];
$courses   = [];
$allocations = [];
try {
    $db = get_db();

    $lecStmt = $db->prepare('SELECT id, name FROM lecturers ORDER BY name ASC');
    $lecStmt->execute();
    $lecturers = $lecStmt->fetchAll(PDO::FETCH_ASSOC);

    $couStmt = $db->prepare('SELECT id, course_code, course_title FROM courses ORDER BY course_code ASC');
    $couStmt->execute();
    $courses = $couStmt->fetchAll(PDO::FETCH_ASSOC);

    $allStmt = $db->prepare(
        'SELECT ca.id, l.name AS lecturer_name, c.course_code, c.course_title
         FROM course_allocations ca
         JOIN lecturers l ON ca.lecturer_id = l.id
         JOIN courses c ON ca.course_id = c.id
         ORDER BY ca.id DESC'
    );
    $allStmt->execute();
    $allocations = $allStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (RuntimeException $e) {
    set_flash_message('danger', $e->getMessage());
} catch (PDOException $e) {
    error_log('[QRAttend] allocation query error: ' . $e->getMessage());
}
?>
<main class="container py-4">
    <?= display_flash_message() ?>

    <h1 class="h4 fw-bold mb-4">
        <i class="bi bi-diagram-3 me-2" style="color:var(--brand-primary);"></i>
        Course Allocations
    </h1>

    <div class="row g-4">
        <!-- Allocation form -->
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-3">
                    <h2 class="h6 fw-bold mb-0">Bind Lecturer to Course</h2>
                </div>
                <div class="card-body">
                    <form action="../../handlers/admin_gateway.php" method="POST">
                        <input type="hidden" name="action" value="add_allocation">

                        <div class="mb-3">
                            <label for="lecturer_id" class="form-label fw-semibold">Lecturer</label>
                            <select class="form-select" id="lecturer_id" name="lecturer_id" required>
                                <option value="" selected disabled>Select lecturer…</option>
                                <?php foreach ($lecturers as $lec): ?>
                                    <option value="<?= (int) $lec['id'] ?>">
                                        <?= sanitize_input($lec['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="course_id" class="form-label fw-semibold">Course</label>
                            <select class="form-select" id="course_id" name="course_id" required>
                                <option value="" selected disabled>Select course…</option>
                                <?php foreach ($courses as $cou): ?>
                                    <option value="<?= (int) $cou['id'] ?>">
                                        <?= sanitize_input($cou['course_code']) ?>
                                        — <?= sanitize_input($cou['course_title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit"
                                class="btn w-100 py-2 fw-bold text-white"
                                style="background-color:var(--brand-secondary);">
                            <i class="bi bi-link-45deg me-1"></i> Allocate Course
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Allocation ledger -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
                    <h2 class="h6 fw-bold mb-0">Allocation Ledger</h2>
                    <span class="badge rounded-pill" style="background-color:var(--brand-primary);">
                        <?= count($allocations) ?> rows
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Lecturer</th>
                                    <th>Course</th>
                                    <th>Title</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allocations)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            No allocations created yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($allocations as $al): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= sanitize_input($al['lecturer_name']) ?></td>
                                            <td><?= sanitize_input($al['course_code']) ?></td>
                                            <td class="small text-muted"><?= sanitize_input($al['course_title']) ?></td>
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

