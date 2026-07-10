<?php
/**
 * QRAttend :: Administrator Master Panel
 * -----------------------------------------------------------------------------
 * Mobile-first master control suite: quick-stat badges (Total Students, Total
 * Faculty, Active Courses) and a navigation grid linking to the management
 * suite (Bulk Provisioning, Course Allocations, Master Audit Vault).
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only administrators may view this page.
require_admin();

$pageTitle = INSTITUTION_SHORT . ' - Admin Dashboard';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';

// --- Demo stats (replace with live COUNT queries in a later phase) ----------
$stats = [
    'students' => 312,
    'faculty'  => 24,
    'courses'  => 18,
];

// Management suite navigation panels
$panels = [
    [
        'title' => 'Bulk Provisioning',
        'desc'  => 'Import students, lecturers & courses via CSV.',
        'icon'  => 'bi-upload',
        'href'  => 'students.php',
        'color' => 'var(--brand-primary)',
    ],
    [
        'title' => 'Course Allocations',
        'desc'  => 'Bind lecturers to courses (matrix control).',
        'icon'  => 'bi-diagram-3',
        'href'  => 'allocations.php',
        'color' => 'var(--brand-secondary)',
    ],
    [
        'title' => 'Master Security Audit',
        'desc'  => 'Review immutable system event logs.',
        'icon'  => 'bi-shield-lock',
        'href'  => 'audit.php',
        'color'  => 'var(--brand-danger)',
    ],
];
?>
<main class="container py-4">
    <?= display_flash_message() ?>

    <!-- Welcome header -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <img src="/assets/img/logo.png" alt="Institution Logo"
             class="rounded-circle border" style="width:64px;height:64px;object-fit:cover;">
        <div>
            <h1 class="h5 mb-1 fw-bold">Admin Control Center</h1>
            <p class="mb-0 text-muted small">
                <?= sanitize_input(INSTITUTION_NAME) ?>
            </p>
        </div>
    </div>

    <!-- Quick stats row -->
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="card border-0 shadow-sm rounded-4 text-center h-100">
                <div class="card-body">
                    <i class="bi bi-people-fill mb-2" style="font-size:1.6rem;color:var(--brand-primary);"></i>
                    <div class="h3 mb-0 fw-bold"><?= (int) $stats['students'] ?></div>
                    <div class="small text-muted">Total Students</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card border-0 shadow-sm rounded-4 text-center h-100">
                <div class="card-body">
                    <i class="bi bi-person-video3 mb-2" style="font-size:1.6rem;color:var(--brand-secondary);"></i>
                    <div class="h3 mb-0 fw-bold"><?= (int) $stats['faculty'] ?></div>
                    <div class="small text-muted">Total Faculty</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card border-0 shadow-sm rounded-4 text-center h-100">
                <div class="card-body">
                    <i class="bi bi-book-fill mb-2" style="font-size:1.6rem;color:var(--brand-success);"></i>
                    <div class="h3 mb-0 fw-bold"><?= (int) $stats['courses'] ?></div>
                    <div class="small text-muted">Active Courses</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Administrative navigation grid -->
    <h2 class="h6 text-uppercase text-muted mb-3">Management Suite</h2>
    <div class="row g-3">
        <?php foreach ($panels as $panel): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <a href="<?= sanitize_input($panel['href']) ?>" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-4 h-100 hover-lift">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white"
                                  style="width:52px;height:52px;background-color:<?= $panel['color'] ?>;flex:0 0 auto;">
                                <i class="bi <?= sanitize_input($panel['icon']) ?>" style="font-size:1.4rem;"></i>
                            </span>
                            <div>
                                <h3 class="h6 mb-1 fw-bold text-dark"><?= sanitize_input($panel['title']) ?></h3>
                                <p class="small text-muted mb-0"><?= sanitize_input($panel['desc']) ?></p>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<?php
require_once __DIR__ . '/../../../app/layouts/footer.php';

