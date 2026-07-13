<?php
/**
 * QRAttend :: Lecturer Live Session Control Center (Projector Mode)
 * -----------------------------------------------------------------------------
 * High-contrast split-screen view designed for front-of-hall projection:
 *   LEFT  : course info, countdown timer, live check-in counter
 *   RIGHT : massive QR code canvas (auto-rotated by the backend)
 *
 * Flow: validates allocation ownership, lazily creates the session via an
 * AJAX POST to the engine, then session.js polls for live updates.
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only lecturers.
require_lecturer();

// Validate allocation_id from the URL.
$allocationId = filter_var($_GET['allocation_id'] ?? null, FILTER_VALIDATE_INT);
if ($allocationId === false || $allocationId <= 0) {
    set_flash_message('danger', 'Invalid or missing allocation reference.');
    header('Location: ' . APP_URL . '/portals/lecturer/dashboard.php');
    exit;
}

// Verify the lecturer owns this allocation and fetch course details.
// Also detect an existing OPEN, non-expired session so returning to this page
// resumes the live session instead of spawning a duplicate.
$existingSession = null;
try {
    $db = get_db();
    $stmt = $db->prepare(
        'SELECT ca.id AS allocation_id, c.course_code, c.course_title, c.credit_units
         FROM course_allocations ca
         JOIN courses c ON c.id = ca.course_id
         WHERE ca.id = :id AND ca.lecturer_id = :lecturer
         LIMIT 1'
    );
    $stmt->execute([':id' => $allocationId, ':lecturer' => (int) $_SESSION['user_id']]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($course === false) {
        set_flash_message('danger', 'You are not assigned to this course allocation.');
        header('Location: ' . APP_URL . '/portals/lecturer/dashboard.php');
        exit;
    }

    $exStmt = $db->prepare(
        'SELECT id, qr_token, session_pin, expires_at
         FROM attendance_sessions
         WHERE course_allocation_id = :alloc AND status = :open AND expires_at > NOW()
         ORDER BY expires_at DESC
         LIMIT 1'
    );
    $exStmt->execute([':alloc' => $allocationId, ':open' => 'Open']);
    $existingSession = $exStmt->fetch(PDO::FETCH_ASSOC);
} catch (RuntimeException $e) {
    set_flash_message('danger', $e->getMessage());
    header('Location: ' . APP_URL . '/portals/lecturer/dashboard.php');
    exit;
}

$pageTitle = INSTITUTION_SHORT . ' - Live Session';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';
?>
<main class="container-fluid py-4" id="session-root"
      data-allocation-id="<?= (int) $allocationId ?>"
      data-poll-url="<?= APP_URL ?>/handlers/session.php"
      data-api-base="<?= rtrim(APP_URL, '/') ?>"
      data-session-id="<?= $existingSession ? (int) $existingSession['id'] : '' ?>"
      data-qr-token="<?= $existingSession ? sanitize_input($existingSession['qr_token']) : '' ?>"
      data-session-pin="<?= $existingSession ? sanitize_input($existingSession['session_pin']) : '' ?>"
      data-expires-at="<?= $existingSession ? sanitize_input($existingSession['expires_at']) : '' ?>"
      data-default-duration="<?= (int) ($_SESSION['default_duration_minutes'] ?? SESSION_DEFAULT_MINUTES) ?>"
      data-started="<?= $existingSession ? '1' : '0' ?>">

    <?= display_flash_message() ?>

    <!-- Expired / closed overlay (hidden until triggered) -->
    <div id="expired-overlay" class="alert alert-danger text-center fw-bold fs-4 d-none" role="alert">
        <i class="bi bi-exclamation-octagon-fill me-2"></i>SESSION EXPIRED
    </div>

    <div class="row g-4 align-items-stretch">
        <!-- LEFT PANEL -->
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100"
                 style="background-color:var(--brand-primary); color:#fff;">
                <div class="card-body d-flex flex-column p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <img src="/assets/img/logo.png" alt="Logo"
                             class="rounded-circle bg-white p-1" style="width:56px;height:56px;object-fit:contain;">
                        <div>
                            <div class="small opacity-75"><?= sanitize_input(INSTITUTION_SHORT) ?> QRAttend</div>
                            <div class="fw-bold">Live Attendance Session</div>
                        </div>
                    </div>

                    <span class="badge align-self-start mb-2" style="background-color:var(--brand-secondary);">
                        <?= sanitize_input($course['course_code']) ?>
                    </span>
                    <h1 class="h3 fw-bold mb-1"><?= sanitize_input($course['course_title']) ?></h1>
                    <p class="opacity-75 mb-4"><?= (int) $course['credit_units'] ?> Credit Units</p>

                    <!-- Countdown timer -->
                    <div class="text-center my-3">
                        <div class="small text-uppercase opacity-75 mb-1">Time Remaining</div>
                        <div id="countdown" class="display-1 fw-bold lh-1"
                             style="font-variant-numeric:tabular-nums;">--:--</div>
                        <button type="button" class="btn btn-sm btn-light mt-2 fw-semibold d-none"
                                id="editSessionBtn" data-bs-toggle="modal" data-bs-target="#sessionModal">
                            <i class="bi bi-sliders me-1"></i>Edit Session
                        </button>
                    </div>

                    <!-- Live check-in counter -->
                    <div class="mt-auto">
                        <div class="bg-white bg-opacity-10 rounded-4 p-3 text-center">
                            <div class="small text-uppercase opacity-75 mb-1">Students Present</div>
                            <div class="h2 fw-bold mb-0">
                                <span id="checked-in">0</span>
                                <span class="opacity-75">/ <span id="total-enrolled">--</span></span>
                            </div>
                        </div>
                        <div class="small text-center opacity-75 mt-2">
                            Backup PIN: <span id="session-pin" class="fw-bold">------</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL : QR canvas -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center p-4 bg-white">
                    <h2 class="h6 text-uppercase text-muted mb-3">Scan to Mark Attendance</h2>
                    <!-- Massive QR container -->
                    <div id="qrcode" class="d-flex align-items-center justify-content-center"
                         style="width:min(70vw,420px);height:min(70vw,420px);background:#fff;border:8px solid var(--brand-primary);border-radius:1rem;"></div>
                    <p class="text-muted small mt-3 mb-0 text-center">
                        Point the student camera at this code. It refreshes automatically.
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Session setup modal (shown FIRST, before the session starts) -->
<div class="modal fade" id="sessionModal" tabindex="-1" aria-labelledby="sessionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="sessionModalLabel">
                    <i class="bi bi-sliders me-1" style="color:var(--brand-primary);"></i>
                    Session Setup
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="maxStudentsInput" class="form-label fw-semibold">Max Students (class size)</label>
                    <input type="number" class="form-control form-control-lg" id="maxStudentsInput"
                           min="1" max="1000" step="1" placeholder="e.g. 45 (blank = unlimited)">
                    <div class="form-text">Shown as "present / max" on the live counter.</div>
                </div>
                <div class="mb-2">
                    <label for="durationInput" class="form-label fw-semibold">Duration (minutes, 1–180)</label>
                    <input type="number" class="form-control form-control-lg" id="durationInput"
                           min="1" max="180" step="1" value="15">
                    <div class="form-text">This becomes your default for future sessions.</div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="modalCancelBtn">Cancel</button>
                <button type="button" class="btn text-white fw-semibold" id="startSessionBtn"
                        style="background-color:var(--brand-primary);">
                    <i class="bi bi-broadcast me-1"></i>Start Session
                </button>
            </div>
        </div>
    </div>
</div>

<!-- QRCode rendering library (safe CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<!-- Real-time polling engine -->
<script src="/assets/js/session.js"></script>

<?php
require_once __DIR__ . '/../../../app/layouts/footer.php';

