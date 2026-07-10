<?php
/**
 * QRAttend :: Context-Aware Top Navbar
 * -----------------------------------------------------------------------------
 * Responsive (navbar-expand-lg) top bar styled with the Primary Green brand
 * color and white typography. When a user is authenticated it renders
 * role-specific links; always offers a Logout route.
 *
 * Requires: config.php + functions.php (sanitize_input) loaded beforehand.
 */
$userType = $_SESSION['user_type'] ?? null;
$userName = $_SESSION['user_name']  ?? null;
?>
<nav class="navbar navbar-expand-lg" style="background-color: var(--brand-primary);" data-bs-theme="dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center text-white fw-bold" href="<?= APP_URL ?>/login.php">
            <i class="bi bi-qr-code-scan me-2"></i>
            <?= sanitize_input(INSTITUTION_SHORT) ?> - QRAttend
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#qrattendNavbar" aria-controls="qrattendNavbar"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="qrattendNavbar">
            <?php if ($userType): ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php if ($userType === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= APP_URL ?>/portals/admin/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= APP_URL ?>/portals/admin/students.php">
                                <i class="bi bi-people me-1"></i>Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= APP_URL ?>/portals/admin/lecturers.php">
                                <i class="bi bi-person-video3 me-1"></i>Lecturers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= APP_URL ?>/portals/admin/courses.php">
                                <i class="bi bi-book me-1"></i>Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= APP_URL ?>/portals/admin/allocations.php">
                                <i class="bi bi-diagram-3 me-1"></i>Allocations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= APP_URL ?>/portals/admin/audit.php">
                                <i class="bi bi-shield-lock me-1"></i>Audit
                            </a>
                        </li>
                    <?php elseif ($userType === 'lecturer'): ?>
                        <?php
                            // Resolve the lecturer's first allocation so the Roster
                            // link always carries a valid allocation_id.
                            $navAllocId = 0;
                            try {
                                $navDb = get_db();
                                $navStmt = $navDb->prepare(
                                    'SELECT id FROM course_allocations
                                     WHERE lecturer_id = :lecturer ORDER BY id ASC LIMIT 1'
                                );
                                $navStmt->execute([':lecturer' => (int) $_SESSION['user_id']]);
                                $navAllocId = (int) ($navStmt->fetchColumn() ?: 0);
                            } catch (Throwable $e) {
                                $navAllocId = 0;
                            }
                            $rosterHref = $navAllocId > 0
                                ? APP_URL . '/portals/lecturer/roster.php?allocation_id=' . $navAllocId
                                : APP_URL . '/portals/lecturer/dashboard.php';
                        ?>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= APP_URL ?>/portals/lecturer/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= APP_URL ?>/portals/lecturer/launch_session.php">
                                <i class="bi bi-broadcast me-1"></i>Launch Session
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= sanitize_input($rosterHref) ?>">
                                <i class="bi bi-clipboard-check me-1"></i>Roster
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= APP_URL ?>/portals/lecturer/analytics.php">
                                <i class="bi bi-graph-up me-1"></i>Analytics
                            </a>
                        </li>
                    <?php elseif ($userType === 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= APP_URL ?>/portals/student/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= APP_URL ?>/portals/student/scan.php">
                                <i class="bi bi-camera me-1"></i>Scan Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= APP_URL ?>/portals/student/progress.php">
                                <i class="bi bi-bar-chart me-1"></i>My Progress
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                    <li class="nav-item d-flex align-items-center text-white-50 me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <span class="text-white"><?= sanitize_input($userName ?? '') ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-light btn-sm fw-semibold" href="<?= APP_URL ?>/logout.php?action=logout">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            <?php else: ?>
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?= APP_URL ?>/login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Login
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>

