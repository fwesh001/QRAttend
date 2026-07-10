<?php
/**
 * QRAttend :: Lecturer Class Attendance Ledger (Roster)
 * -----------------------------------------------------------------------------
 * Lists every student in the allocated course with per-student attendance
 * metrics, color-coded by the institutional 75% threshold.
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only lecturers.
require_lecturer();

// Validate + verify ownership of the allocation.
$allocationId = filter_var($_GET['allocation_id'] ?? null, FILTER_VALIDATE_INT);
if ($allocationId === false || $allocationId <= 0) {
    set_flash_message('danger', 'Invalid or missing allocation reference.');
    header('Location: ' . APP_URL . '/portals/lecturer/dashboard.php');
    exit;
}

$pageTitle = INSTITUTION_SHORT . ' - Class Roster';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';

try {
    $db = get_db();
    $lecturerId = (int) $_SESSION['user_id'];

    // Course + ownership check.
    $courseStmt = $db->prepare(
        'SELECT ca.id AS allocation_id, c.id AS course_id, c.course_code, c.course_title
         FROM course_allocations ca
         JOIN courses c ON c.id = ca.course_id
         WHERE ca.id = :alloc AND ca.lecturer_id = :lecturer
         LIMIT 1'
    );
    $courseStmt->execute([':alloc' => $allocationId, ':lecturer' => $lecturerId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    if ($course === false) {
        set_flash_message('danger', 'You are not assigned to this course allocation.');
        header('Location: ' . APP_URL . '/portals/lecturer/dashboard.php');
        exit;
    }
    $courseId = (int) $course['course_id'];

    // Total sessions held for this allocation.
    $heldStmt = $db->prepare(
        'SELECT COUNT(*) FROM attendance_sessions WHERE course_allocation_id = :alloc'
    );
    $heldStmt->execute([':alloc' => $allocationId]);
    $totalHeld = (int) $heldStmt->fetchColumn();

    // Roster: students in the allocation's department, with attendance metrics.
    // Sessions attended = Present records for this student within this allocation.
    $rosterStmt = $db->prepare(
        'SELECT s.id, s.matric_no, s.name, s.level,
                (SELECT COUNT(*)
                 FROM attendance_records ar
                 JOIN attendance_sessions ses ON ses.id = ar.session_id
                 WHERE ar.student_id = s.id
                   AND ses.course_allocation_id = :alloc
                   AND ar.attendance_status = \'Present\') AS attended
         FROM students s
         JOIN course_allocations ca ON ca.id = :alloc
         WHERE s.department_id = ca.department_id
         ORDER BY s.name ASC'
    );
    $rosterStmt->execute([':alloc' => $allocationId]);
    $roster = $rosterStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalEnrolled = count($roster);
} catch (RuntimeException $e) {
    set_flash_message('danger', $e->getMessage());
    $roster = [];
    $totalEnrolled = 0;
    $totalHeld = 0;
    $course = ['course_code' => '', 'course_title' => ''];
} catch (PDOException $e) {
    error_log('[QRAttend] roster query error: ' . $e->getMessage());
    set_flash_message('danger', 'Could not load roster data.');
    $roster = [];
    $totalEnrolled = 0;
    $totalHeld = 0;
    $course = ['course_code' => '', 'course_title' => ''];
}
?>
<main class="container py-4">
    <?= display_flash_message() ?>

    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h4 fw-bold mb-1">
                <span class="badge me-2" style="background-color:var(--brand-secondary);">
                    <?= sanitize_input($course['course_code']) ?>
                </span>
                <?= sanitize_input($course['course_title']) ?>
            </h1>
            <span class="badge rounded-pill" style="background-color:var(--brand-primary);">
                Total Enrolled: <?= (int) $totalEnrolled ?>
            </span>
        </div>
        <a href="?allocation_id=<?= (int) $allocationId ?>&export=csv"
           class="btn text-white fw-semibold" style="background-color:var(--brand-primary);">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
    </div>

    <!-- Ledger table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Matric No</th>
                            <th>Full Name</th>
                            <th>Level</th>
                            <th>Sessions Held</th>
                            <th>Attended</th>
                            <th>Attendance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roster)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No students enrolled for this course yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($roster as $row): ?>
                                <?php
                                    $held   = $totalHeld;
                                    $att    = (int) $row['attended'];
                                    $pct    = $held > 0 ? round(($att / $held) * 100, 1) : 0;
                                    $atRisk = $pct < ATTENDANCE_THRESHOLD;
                                    $pctStyle = $atRisk
                                        ? 'color:var(--brand-danger);font-weight:700;'
                                        : 'color:var(--brand-success);font-weight:700;';
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?= sanitize_input($row['matric_no']) ?></td>
                                    <td><?= sanitize_input($row['name']) ?></td>
                                    <td><?= sanitize_input($row['level']) ?></td>
                                    <td><?= (int) $held ?></td>
                                    <td><?= $att ?></td>
                                    <td style="<?= $pctStyle ?>">
                                        <?= $pct ?>%
                                        <?php if ($atRisk): ?>
                                            <span class="badge bg-danger ms-1">At-Risk</span>
                                        <?php endif; ?>
                                    </td>
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

