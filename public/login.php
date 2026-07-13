<?php
/**
 * QRAttend :: Public Login Entry Point
 * -----------------------------------------------------------------------------
 * Mobile-first, centered Bootstrap 5 login card. Posts credentials to the
 * authentication engine (../app/auth/auth.php) via a secure POST payload.
 */
require_once __DIR__ . '/../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../app/includes/functions.php';

// If already authenticated, send them to their portal.
if (!empty($_SESSION['user_type']) && !empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/portals/' . $_SESSION['user_type'] . '/dashboard.php');
    exit;
}

$pageTitle = INSTITUTION_SHORT . ' - QRAttend Login';
require_once __DIR__ . '/../app/layouts/header.php';
require_once __DIR__ . '/../app/layouts/navbar.php';
?>

<main class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

            <!-- Flash messages (errors / success) from auth.php -->
            <?= display_flash_message() ?>

            <div class="card shadow-sm border-0 rounded-4">
                <!-- Elegant head region: logo badge + institution -->
                <div class="card-header bg-white border-0 text-center pt-4 pb-2">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2"
                         style="width:72px;height:72px;background-color:var(--brand-primary);">
                        <i class="bi bi-qr-code-scan text-white" style="font-size:2rem;"></i>
                    </div>
                    <h1 class="h5 mb-0 fw-bold" style="color:var(--brand-primary);">
                        <?= sanitize_input(INSTITUTION_NAME) ?>
                    </h1>
                    <p class="text-muted small mb-0"><?= sanitize_input(INSTITUTION_DEPT) ?></p>
                </div>

                <div class="card-body p-4">

                    <!-- STEP 1: role selection -->
                    <div id="role-step">
                        <h2 class="h6 text-center text-uppercase letter-spacing-1 mb-2 text-muted">
                            Choose Your Role
                        </h2>
                        <p class="text-center small text-muted mb-4">
                            Your account is detected automatically after you sign in.
                        </p>

                        <div class="d-grid gap-2 mb-2">
                            <button type="button" class="btn btn-role" data-role="admin">
                                <i class="bi bi-shield-lock me-2"></i>Administrator
                            </button>
                            <button type="button" class="btn btn-role" data-role="lecturer">
                                <i class="bi bi-person-video3 me-2"></i>Lecturer
                            </button>
                            <button type="button" class="btn btn-role" data-role="student">
                                <i class="bi bi-mortarboard me-2"></i>Student
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: credentials (hidden until a role is picked) -->
                    <div id="form-step" class="d-none">
                        <div class="d-flex align-items-center mb-3">
                            <button type="button" id="role-back" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </button>
                            <span id="role-label" class="badge rounded-pill text-white mb-0"></span>
                        </div>

                        <form action="auth.php" method="POST" novalidate>
                            <input type="hidden" name="role_hint" id="role-hint" value="">

                            <!-- Identity -->
                            <div class="mb-3">
                                <label for="identity" class="form-label fw-semibold">Identity</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-person-badge text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control" id="identity" name="identity"
                                           placeholder="Enter Username, Email, Staff No, or Matric No"
                                           autocomplete="username" required>
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-lock text-muted"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password"
                                           placeholder="Enter Password" autocomplete="current-password" required>
                                </div>
                            </div>

                            <!-- Submit -->
                            <button type="submit"
                                    class="btn w-100 py-2 fw-bold text-white"
                                    style="background-color:var(--brand-primary);">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Login
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card-footer bg-white border-0 text-center py-3">
                    <small class="text-muted">
                        Secure QR Attendance &middot; <?= date('Y') ?>
                    </small>
                </div>
            </div>

        </div>
    </div>
</main>

<script>
(function () {
    'use strict';
    const roleStep  = document.getElementById('role-step');
    const formStep  = document.getElementById('form-step');
    const roleLabel = document.getElementById('role-label');
    const roleHint  = document.getElementById('role-hint');
    const backBtn   = document.getElementById('role-back');
    if (!roleStep || !formStep) return;

    const meta = {
        admin:    { label: 'Administrator', color: 'var(--brand-primary)' },
        lecturer: { label: 'Lecturer',     color: 'var(--brand-secondary)' },
        student:  { label: 'Student',      color: 'var(--brand-success)' }
    };

    document.querySelectorAll('.btn-role').forEach((btn) => {
        btn.addEventListener('click', () => {
            const role = btn.dataset.role;
            roleStep.classList.add('d-none');
            formStep.classList.remove('d-none');
            roleLabel.textContent = meta[role].label;
            roleLabel.style.backgroundColor = meta[role].color;
            roleHint.value = role;
            const id = document.getElementById('identity');
            if (id) id.focus();
        });
    });

    if (backBtn) {
        backBtn.addEventListener('click', () => {
            formStep.classList.add('d-none');
            roleStep.classList.remove('d-none');
            roleHint.value = '';
        });
    }
})();
</script>

<?php
require_once __DIR__ . '/../app/layouts/footer.php';

