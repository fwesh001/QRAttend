<?php
/**
 * QRAttend :: Student Attendance Scanner (Mobile View)
 * -----------------------------------------------------------------------------
 * Distraction-free, mobile-first scanner with two toggleable states:
 *   1. Camera View  -> #reader (html5-qrcode)
 *   2. Manual PIN    -> fallback form for the 6-digit backup session pin
 * On success the backend redirects the student to progress.php.
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only students.
require_student();

$pageTitle = INSTITUTION_SHORT . ' - Scan Attendance';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';
?>
<main class="container py-4" id="scanner-root" data-endpoint="<?= APP_URL ?>/handlers/attendance.php">
    <?= display_flash_message() ?>

    <div class="text-center mb-3">
        <h1 class="h5 fw-bold mb-1">Mark Your Attendance</h1>
        <p class="text-muted small mb-0">Scan the lecturer's QR code or enter the backup PIN.</p>
    </div>

    <!-- Toggle tabs -->
    <ul class="nav nav-pills justify-content-center mb-3" id="scanTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-camera" data-bs-toggle="pill"
                    data-bs-target="#pane-camera" type="button" role="tab">Camera</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-pin" data-bs-toggle="pill"
                    data-bs-target="#pane-pin" type="button" role="tab">PIN</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- 1. CAMERA VIEW -->
        <div class="tab-pane fade show active" id="pane-camera" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 mx-auto" style="max-width:420px;">
                <div class="card-body p-3">
                    <!-- Centered scanning square -->
                    <div id="reader" class="mx-auto rounded-3 overflow-hidden"
                         style="width:100%; max-width:360px; min-height:300px; background:#000;"></div>
                    <p class="text-center text-muted small mt-2 mb-0">
                        Point your camera at the on-screen QR code.
                    </p>
                </div>
            </div>
        </div>

        <!-- 2. MANUAL PIN FALLBACK -->
        <div class="tab-pane fade" id="pane-pin" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 mx-auto" style="max-width:420px;">
                <div class="card-body p-4">
                    <h2 class="h6 text-center mb-3">Enter 6-Digit Backup PIN</h2>
                    <form id="pin-form">
                        <div class="mb-3">
                            <input type="text" inputmode="numeric" pattern="\d{6}" maxlength="6"
                                   id="session_pin" name="session_pin"
                                   class="form-control form-control-lg text-center fw-bold tracking-wide"
                                   placeholder="••••••" autocomplete="off" required>
                            <div class="form-text text-center">Provided by your lecturer on the projector.</div>
                        </div>
                        <button type="submit" class="btn w-100 py-2 fw-bold text-white"
                                style="background-color:var(--brand-primary);">
                            Submit Attendance
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Result / status region -->
    <div id="scan-status" class="text-center mt-3"></div>
</main>

<!-- html5-qrcode library (safe CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<!-- Camera wrapper engine -->
<script src="/assets/js/scanner.js"></script>

<?php
require_once __DIR__ . '/../../../app/layouts/footer.php';

