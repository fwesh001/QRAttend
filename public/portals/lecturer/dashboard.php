<?php
/**
 * QRAttend :: Lecturer Control Center
 * -----------------------------------------------------------------------------
 * Mobile-first dashboard for lecturers: welcome header with logo + Staff ID,
 * and a responsive grid of allocated course blocks, each with "Launch Active
 * Session" and "View Rosters" actions.
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only lecturers may view this page.
require_lecturer();

$pageTitle = INSTITUTION_SHORT . ' - Lecturer Dashboard';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';

// --- Live allocations for this lecturer (course_allocations -> courses) -----
$allocations = [];
try {
    $db = get_db();
    $stmt = $db->prepare(
        'SELECT ca.id, c.course_code AS code, c.course_title AS title, c.credit_units AS units
         FROM course_allocations ca
         JOIN courses c ON c.id = ca.course_id
         WHERE ca.lecturer_id = :lecturer
         ORDER BY c.course_code ASC'
    );
    $stmt->execute([':lecturer' => (int) $_SESSION['user_id']]);
    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (RuntimeException $e) {
    set_flash_message('danger', $e->getMessage());
} catch (PDOException $e) {
    error_log('[QRAttend] lecturer allocations query error: ' . $e->getMessage());
}
?>
<main class="container py-4">
    <?= display_flash_message() ?>

    <!-- Welcome header -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <img src="/assets/img/logo.png" alt="Institution Logo"
             class="rounded-circle border" style="width:64px;height:64px;object-fit:cover;">
        <div>
            <h1 class="h5 mb-1 fw-bold">Welcome, <?= sanitize_input($_SESSION['user_name'] ?? '') ?></h1>
            <p class="mb-0 text-muted small">
                Staff ID: <span class="fw-semibold"><?= sanitize_input($_SESSION['user_label'] ?? '') ?></span>
            </p>
        </div>
    </div>

    <h2 class="h6 text-uppercase text-muted mb-3">Your Allocated Courses</h2>

    <!-- Course allocation grid -->
    <div class="row g-3">
        <?php if (empty($allocations)): ?>
            <div class="col-12">
                <div class="alert rounded-4 text-center" role="alert"
                     style="background-color:var(--brand-surface); border:1px solid #e3e6e5;">
                    <i class="bi bi-info-circle me-1" style="color:var(--brand-secondary);"></i>
                    No courses are allocated to your account yet. Contact an administrator to
                    assign you to a course via the Course Allocations panel.
                </div>
            </div>
        <?php else: ?>
        <?php foreach ($allocations as $course): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge text-white" style="background-color:var(--brand-secondary);">
                                <?= sanitize_input($course['code']) ?>
                            </span>
                            <span class="small text-muted"><?= (int) $course['units'] ?> Units</span>
                        </div>
                        <h3 class="h6 fw-bold mb-3"><?= sanitize_input($course['title']) ?></h3>

                        <div class="mt-auto d-grid gap-2">
                            <a href="launch_session.php?allocation_id=<?= (int) $course['id'] ?>"
                               class="btn text-white fw-semibold"
                               style="background-color:var(--brand-primary);">
                                <i class="bi bi-broadcast me-1"></i> Launch Active Session
                            </a>
                            <a href="roster.php?allocation_id=<?= (int) $course['id'] ?>"
                               class="btn btn-outline-secondary fw-semibold">
                                <i class="bi bi-clipboard-check me-1"></i> View Rosters
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
</main>

<?php
require_once __DIR__ . '/../../../app/layouts/footer.php';

