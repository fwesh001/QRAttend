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

// --- Active live sessions (OPEN + not yet expired) for this lecturer --------
$activeSessions = [];
try {
    $db = get_db();
    $sessStmt = $db->prepare(
        'SELECT s.id AS session_id, s.course_allocation_id AS allocation_id, s.expires_at,
                c.course_code, c.course_title,
                (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = s.id) AS checked_in
         FROM attendance_sessions s
         JOIN course_allocations ca ON ca.id = s.course_allocation_id
         JOIN courses c ON c.id = ca.course_id
         WHERE ca.lecturer_id = :lecturer AND s.status = :open AND s.expires_at > NOW()
         ORDER BY s.expires_at ASC'
    );
    $sessStmt->execute([':lecturer' => (int) $_SESSION['user_id'], ':open' => 'Open']);
    $activeSessions = $sessStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (RuntimeException $e) {
    // Non-fatal: dashboard still renders without the live section.
} catch (PDOException $e) {
    error_log('[QRAttend] active sessions query error: ' . $e->getMessage());
}

// --- Recently ended sessions (CLOSED or expired) for this lecturer ----------
$endedSessions = [];
try {
    $db = get_db();
    $endStmt = $db->prepare(
        'SELECT s.id AS session_id, s.course_allocation_id AS allocation_id, s.status,
                s.expires_at, c.course_code, c.course_title,
                (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = s.id) AS checked_in
         FROM attendance_sessions s
         JOIN course_allocations ca ON ca.id = s.course_allocation_id
         JOIN courses c ON c.id = ca.course_id
         WHERE ca.lecturer_id = :lecturer
           AND (s.status = :closed OR s.expires_at <= NOW())
         ORDER BY s.expires_at DESC
         LIMIT 10'
    );
    $endStmt->execute([':lecturer' => (int) $_SESSION['user_id'], ':closed' => 'Closed']);
    $endedSessions = $endStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (RuntimeException $e) {
    // Non-fatal.
} catch (PDOException $e) {
    error_log('[QRAttend] ended sessions query error: ' . $e->getMessage());
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

    <!-- Active Live Sessions (resume if you switched tabs / left the page) -->
    <h2 class="h6 text-uppercase text-muted mb-3">
        <i class="bi bi-broadcast me-1" style="color:var(--brand-primary);"></i>
        Active Live Sessions
    </h2>
    <?php if (empty($activeSessions)): ?>
        <div class="alert rounded-4 mb-4" role="alert"
             style="background-color:var(--brand-surface); border:1px solid #e3e6e5;">
            <i class="bi bi-info-circle me-1" style="color:var(--brand-secondary);"></i>
            No live session is currently running. Launch one from your allocated courses below.
        </div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <?php foreach ($activeSessions as $sess): ?>
                <?php
                    $expiry = strtotime($sess['expires_at']);
                    $minsLeft = max(0, ceil(($expiry - time()) / 60));
                ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4"
                         style="border-left-color:var(--brand-primary) !important;">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge text-white" style="background-color:var(--brand-primary);">
                                    <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>LIVE
                                </span>
                                <span class="small text-muted">
                                    <?= (int) $minsLeft ?> min left
                                </span>
                            </div>
                            <h3 class="h6 fw-bold mb-1"><?= sanitize_input($sess['course_code']) ?></h3>
                            <p class="small text-muted mb-2"><?= sanitize_input($sess['course_title']) ?></p>
                            <div class="small mb-3">
                                <i class="bi bi-people-fill me-1"></i>
                                <?= (int) $sess['checked_in'] ?> student(s) checked in
                            </div>
                            <a href="launch_session.php?allocation_id=<?= (int) $sess['allocation_id'] ?>"
                               class="btn btn-sm w-100 fw-semibold text-white mt-auto"
                               style="background-color:var(--brand-secondary);">
                                <i class="bi bi-arrow-return-left me-1"></i> Resume Session
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

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

    <!-- Recently Ended Sessions (read-only) -->
    <h2 class="h6 text-uppercase text-muted mb-3 mt-2">
        <i class="bi bi-clock-history me-1" style="color:var(--brand-secondary);"></i>
        Recently Ended Sessions
    </h2>
    <?php if (empty($endedSessions)): ?>
        <div class="alert rounded-4 mb-2" role="alert"
             style="background-color:var(--brand-surface); border:1px solid #e3e6e5;">
            <i class="bi bi-info-circle me-1" style="color:var(--brand-secondary);"></i>
            No ended sessions yet.
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($endedSessions as $ended): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-light">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge text-white" style="background-color:var(--brand-danger);">
                                    <i class="bi bi-x-circle-fill me-1"></i>ENDED
                                </span>
                                <span class="small text-muted">
                                    <?= sanitize_input(date('M d, H:i', strtotime($ended['expires_at']))) ?>
                                </span>
                            </div>
                            <h3 class="h6 fw-bold mb-1"><?= sanitize_input($ended['course_code']) ?></h3>
                            <p class="small text-muted mb-2"><?= sanitize_input($ended['course_title']) ?></p>
                            <div class="small">
                                <i class="bi bi-people-fill me-1"></i>
                                <?= (int) $ended['checked_in'] ?> student(s) checked in
                            </div>
                            <a href="roster.php?allocation_id=<?= (int) $ended['allocation_id'] ?>"
                               class="btn btn-sm btn-outline-secondary w-100 fw-semibold mt-3">
                                <i class="bi bi-clipboard-check me-1"></i> View Roster
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php
require_once __DIR__ . '/../../../app/layouts/footer.php';

