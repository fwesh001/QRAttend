<?php
/**
 * QRAttend :: Student Hub
 * -----------------------------------------------------------------------------
 * Mobile-first dashboard for students: profile header, primary "Scan
 * Attendance" CTA, and an attendance analytics widget with a CLEAR / AT-RISK
 * status badge driven by the institutional 75% threshold.
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only students may view this page.
require_student();

$pageTitle = INSTITUTION_SHORT . ' - Student Dashboard';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';

// --- Live analytics -------------------------------------------------------
$studentId = (int) $_SESSION['user_id'];
$classesAttended = 0;
$totalClasses    = 0;
try {
    $db = get_db();

    // Total department-wide sessions held (student's registered courses).
    $heldStmt = $db->prepare(
        'SELECT COUNT(*)
         FROM attendance_sessions s
         JOIN course_allocations ca ON ca.id = s.course_allocation_id
         JOIN lecturers l ON l.id = ca.lecturer_id
         JOIN students st ON st.department_id = l.department_id
         WHERE st.id = :student_id'
    );
    $heldStmt->execute([':student_id' => $studentId]);
    $totalClasses = (int) $heldStmt->fetchColumn();

    // This student's Present records.
    $attStmt = $db->prepare(
        'SELECT COUNT(*)
         FROM attendance_records
         WHERE student_id = :student_id AND attendance_status = \'Present\''
    );
    $attStmt->execute([':student_id' => $studentId]);
    $classesAttended = (int) $attStmt->fetchColumn();
} catch (RuntimeException $e) {
    set_flash_message('danger', $e->getMessage());
} catch (PDOException $e) {
    error_log('[QRAttend] student dashboard query error: ' . $e->getMessage());
    set_flash_message('danger', 'Could not load attendance summary.');
}
$classesMissed = max(0, $totalClasses - $classesAttended);
$attendancePct = $totalClasses > 0
    ? round(($classesAttended / $totalClasses) * 100, 1)
    : 0;
$isClear = $attendancePct >= ATTENDANCE_THRESHOLD;
$statusBadge = $isClear
    ? '<span class="badge rounded-pill text-white" style="background-color:var(--brand-success);">CLEAR</span>'
    : '<span class="badge rounded-pill text-white" style="background-color:var(--brand-danger);">AT-RISK</span>';
?>
<main class="container py-4">
    <?= display_flash_message() ?>

    <!-- 1. Profile header card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body d-flex align-items-center gap-3">
            <img src="/assets/img/logo.png" alt="Institution Logo"
                 class="rounded-circle border" style="width:64px;height:64px;object-fit:cover;">
            <div>
                <h1 class="h5 mb-1 fw-bold">Welcome, <?= sanitize_input($_SESSION['user_name'] ?? '') ?></h1>
                <p class="mb-0 text-muted small">
                    Matric No: <span class="fw-semibold"><?= sanitize_input($_SESSION['user_label'] ?? '') ?></span>
                </p>
            </div>
        </div>
    </div>

    <!-- 2. Primary CTA -->
    <a href="scan.php"
       class="btn w-100 py-3 mb-4 fw-bold text-white d-flex align-items-center justify-content-center gap-2"
       style="background-color:var(--brand-primary); font-size:1.15rem; min-height:56px;">
        <i class="bi bi-camera-fill" style="font-size:1.4rem;"></i> Scan Attendance
    </a>

    <!-- 3. Metric analytics widget -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
            <h2 class="h6 mb-0 fw-bold">Attendance Summary</h2>
            <?= $statusBadge ?>
        </div>
        <div class="card-body">
            <div class="row text-center g-3">
                <div class="col-4">
                    <div class="h3 mb-0 fw-bold" style="color:var(--brand-primary);"><?= $classesAttended ?></div>
                    <div class="small text-muted">Classes Attended</div>
                </div>
                <div class="col-4">
                    <div class="h3 mb-0 fw-bold" style="color:var(--brand-danger);"><?= $classesMissed ?></div>
                    <div class="small text-muted">Missed Sessions</div>
                </div>
                <div class="col-4">
                    <div class="h3 mb-0 fw-bold" style="color:var(--brand-secondary);"><?= $attendancePct ?>%</div>
                    <div class="small text-muted">Attendance</div>
                </div>
            </div>

            <div class="progress mt-3" style="height:10px;">
                <div class="progress-bar <?= $isClear ? '' : 'bg-danger' ?>"
                     role="progressbar"
                     style="width:<?= $attendancePct ?>%; background-color:<?= $isClear ? 'var(--brand-primary)' : 'var(--brand-danger)' ?>;"
                     aria-valuenow="<?= $attendancePct ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <p class="text-center small text-muted mt-2 mb-0">
                Threshold: <?= ATTENDANCE_THRESHOLD ?>% to clear exams
            </p>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../../../app/layouts/footer.php';

