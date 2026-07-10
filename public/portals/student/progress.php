<?php
/**
 * QRAttend :: Student "Success & Score" Card
 * -----------------------------------------------------------------------------
 * Shown after a verified check-in. Displays a green success badge, live
 * attendance metrics, and a context-aware threshold progress gauge.
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only students.
require_student();

$studentId = (int) $_SESSION['user_id'];

/* ===========================================================================
 * MOCK DATA STRATEGY
 * ---------------------------------------------------------------------------
 * Values are mocked until the global data-import managers are complete.
 * The live PDO query structure is provided in comments beside each variable
 * so we can swap to get_db() counts in the next optimization pass.
 * =========================================================================== */

// 1. Total Course Lectures Held (sessions for this student's registered courses)
$lecturesHeld = 15;
/*
SELECT COUNT(*) FROM attendance_sessions s
JOIN course_allocations ca ON ca.id = s.course_allocation_id
JOIN students st ON st.department_id = ca.department_id  -- or via enrolment table
WHERE st.id = :student_id AND s.status = 'Closed';
*/

// 2. Total Lectures Attended (Present rows for this student)
$lecturesAttended = 12;
/*
SELECT COUNT(*) FROM attendance_records
WHERE student_id = :student_id AND attendance_status = 'Present';
*/

// 3. Aggregate Attendance Percentage
$attendancePct = ($lecturesHeld > 0)
    ? round(($lecturesAttended / $lecturesHeld) * 100, 1)
    : 0;

// Context-aware styling against the institutional threshold.
$isClear   = $attendancePct >= ATTENDANCE_THRESHOLD;
$barColor  = $isClear ? 'var(--brand-primary)' : 'var(--brand-danger)';
$barClass  = $isClear ? '' : 'bg-danger';
$statusBadge = $isClear
    ? '<span class="badge rounded-pill text-white" style="background-color:var(--brand-success);">CLEAR</span>'
    : '<span class="badge rounded-pill text-white" style="background-color:var(--brand-danger);">AT-RISK (Below ' . ATTENDANCE_THRESHOLD . '% Requirement)</span>';

$pageTitle = INSTITUTION_SHORT . ' - Attendance Progress';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';
?>
<main class="container py-4">
    <?= display_flash_message() ?>

    <!-- Success confirmation badge -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 text-center"
         style="background-color:var(--brand-success); color:#fff;">
        <div class="card-body py-4">
            <i class="bi bi-check-circle-fill" style="font-size:3.5rem;"></i>
            <h1 class="h4 fw-bold mt-2 mb-0">Attendance Successfully Verified!</h1>
            <p class="mb-0 opacity-75">Your presence has been recorded. Keep it up!</p>
        </div>
    </div>

    <!-- Real-time attendance metrics panel -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 text-center h-100">
                <div class="card-body">
                    <i class="bi bi-calendar-event mb-2" style="font-size:1.6rem;color:var(--brand-secondary);"></i>
                    <div class="h2 mb-0 fw-bold"><?= (int) $lecturesHeld ?></div>
                    <div class="small text-muted">Lectures Held</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 text-center h-100">
                <div class="card-body">
                    <i class="bi bi-check2-circle mb-2" style="font-size:1.6rem;color:var(--brand-primary);"></i>
                    <div class="h2 mb-0 fw-bold"><?= (int) $lecturesAttended ?></div>
                    <div class="small text-muted">Lectures Attended</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 text-center h-100">
                <div class="card-body">
                    <i class="bi bi-percent mb-2" style="font-size:1.6rem;color:var(--brand-success);"></i>
                    <div class="h2 mb-0 fw-bold"><?= $attendancePct ?>%</div>
                    <div class="small text-muted">Attendance</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dynamic threshold progress gauge -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Attendance Threshold</h2>
                <?= $statusBadge ?>
            </div>
            <div class="progress" style="height:18px;">
                <div class="progress-bar <?= $barClass ?>"
                     role="progressbar"
                     style="width:<?= $attendancePct ?>%; background-color:<?= $barColor ?>;"
                     aria-valuenow="<?= $attendancePct ?>" aria-valuemin="0" aria-valuemax="100">
                    <?= $attendancePct ?>%
                </div>
            </div>
            <p class="text-center small text-muted mt-2 mb-0">
                Institutional requirement: <?= ATTENDANCE_THRESHOLD ?>% to clear examinations
            </p>
        </div>
    </div>

    <!-- Return to hub -->
    <div class="text-center mt-4">
        <a href="dashboard.php" class="btn px-4 py-2 fw-bold text-white"
           style="background-color:var(--brand-primary);">
            <i class="bi bi-house-door me-1"></i> Return to Hub
        </a>
    </div>
</main>

<?php
require_once __DIR__ . '/../../../app/layouts/footer.php';

