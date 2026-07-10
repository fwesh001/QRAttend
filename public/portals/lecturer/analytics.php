<?php
/**
 * QRAttend :: Lecturer Course Performance Trends (Analytics)
 * -----------------------------------------------------------------------------
 * Summary cards, a per-week attendance trend chart (Chart.js), and a per-course
 * performance table aggregated with GROUP BY course_id.
 */
require_once __DIR__ . '/../../../app/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../app/includes/functions.php';
require_once __DIR__ . '/../../../app/auth/session.php';

// Strict authorization: only lecturers.
require_lecturer();

$pageTitle = INSTITUTION_SHORT . ' - Analytics';
require_once __DIR__ . '/../../../app/layouts/header.php';
require_once __DIR__ . '/../../../app/layouts/navbar.php';

$lecturerId = (int) $_SESSION['user_id'];

// Defaults.
$avgAttendance   = 0;
$mostActiveCourse = '—';
$atRiskCount      = 0;
$coursePerf       = [];
$weeklyTrend      = [];

try {
    $db = get_db();

    // Per-course aggregation (GROUP BY course_id).
    $perfStmt = $db->prepare(
        'SELECT c.course_code, c.course_title,
                COUNT(DISTINCT ses.id) AS sessions_held,
                COUNT(ar.id) AS attended
         FROM course_allocations ca
         JOIN courses c ON c.id = ca.course_id
         LEFT JOIN attendance_sessions ses ON ses.course_allocation_id = ca.id
         LEFT JOIN attendance_records ar
                ON ar.session_id = ses.id AND ar.attendance_status = \'Present\'
         WHERE ca.lecturer_id = :lecturer
         GROUP BY c.id
         ORDER BY c.course_code ASC'
    );
    $perfStmt->execute([':lecturer' => $lecturerId]);
    $coursePerf = $perfStmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute summary metrics.
    $totalHeld = 0;
    $totalAtt  = 0;
    $bestPct   = -1;
    foreach ($coursePerf as $cp) {
        $held = (int) $cp['sessions_held'];
        $att  = (int) $cp['attended'];
        $totalHeld += $held;
        $totalAtt  += $att;
        if ($held > 0) {
            $pct = ($att / $held) * 100;
            if ($pct > $bestPct) {
                $bestPct = $pct;
                $mostActiveCourse = $cp['course_code'];
            }
        }
    }
    $avgAttendance = $totalHeld > 0 ? round(($totalAtt / $totalHeld) * 100, 1) : 0;

    // At-risk students: distinct students with < threshold attendance in any allocation.
    $riskStmt = $db->prepare(
        'SELECT COUNT(DISTINCT s.id) AS at_risk
         FROM students s
         JOIN lecturers l ON l.department_id = s.department_id
         JOIN course_allocations ca ON ca.lecturer_id = l.id
         LEFT JOIN attendance_sessions ses ON ses.course_allocation_id = ca.id
         LEFT JOIN attendance_records ar
                ON ar.session_id = ses.id AND ar.student_id = s.id
                AND ar.attendance_status = \'Present\'
         WHERE ca.lecturer_id = :lecturer
         GROUP BY s.id
         HAVING COALESCE(SUM(ar.attendance_status = \'Present\'), 0) /
                NULLIF(COUNT(DISTINCT ses.id), 0) < :threshold'
    );
    $riskStmt->execute([':lecturer' => $lecturerId, ':threshold' => ATTENDANCE_THRESHOLD / 100]);
    $atRiskCount = (int) $riskStmt->fetchColumn();

    // Weekly trend: attendance % per ISO week for the current semester.
    $trendStmt = $db->prepare(
        'SELECT YEARWEEK(ses.created_at, 1) AS wk,
                COUNT(DISTINCT ses.id) AS held,
                COUNT(ar.id) AS attended
         FROM course_allocations ca
         JOIN attendance_sessions ses ON ses.course_allocation_id = ca.id
         LEFT JOIN attendance_records ar
                ON ar.session_id = ses.id AND ar.attendance_status = \'Present\'
         WHERE ca.lecturer_id = :lecturer
         GROUP BY wk
         ORDER BY wk ASC
         LIMIT 12'
    );
    $trendStmt->execute([':lecturer' => $lecturerId]);
    $weeklyTrend = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (RuntimeException $e) {
    set_flash_message('danger', $e->getMessage());
} catch (PDOException $e) {
    error_log('[QRAttend] analytics query error: ' . $e->getMessage());
    set_flash_message('danger', 'Could not load analytics data.');
}

// Build chart datasets.
$labels = [];
$dataPct = [];
foreach ($weeklyTrend as $w) {
    $held = (int) $w['held'];
    $att  = (int) $w['attended'];
    $labels[] = substr((string) $w['wk'], 4); // last 2 digits = week number
    $dataPct[] = $held > 0 ? round(($att / $held) * 100, 1) : 0;
}
$chartLabels = json_encode($labels);
$chartData   = json_encode($dataPct);
?>
<main class="container py-4">
    <?= display_flash_message() ?>

    <h1 class="h4 fw-bold mb-4">
        <i class="bi bi-graph-up me-2" style="color:var(--brand-primary);"></i>
        Course Performance Trends
    </h1>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 text-center h-100">
                <div class="card-body">
                    <i class="bi bi-speedometer2 mb-2" style="font-size:1.6rem;color:var(--brand-primary);"></i>
                    <div class="h2 mb-0 fw-bold"><?= $avgAttendance ?>%</div>
                    <div class="small text-muted">Average Class Attendance</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 text-center h-100">
                <div class="card-body">
                    <i class="bi bi-trophy mb-2" style="font-size:1.6rem;color:var(--brand-secondary);"></i>
                    <div class="h2 mb-0 fw-bold"><?= sanitize_input($mostActiveCourse) ?></div>
                    <div class="small text-muted">Most Active Course</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 text-center h-100">
                <div class="card-body">
                    <i class="bi bi-exclamation-triangle mb-2" style="font-size:1.6rem;color:var(--brand-danger);"></i>
                    <div class="h2 mb-0 fw-bold"><?= (int) $atRiskCount ?></div>
                    <div class="small text-muted">At-Risk Students</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trend chart -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0">
            <h2 class="h6 fw-bold mb-0">Weekly Attendance Trend</h2>
        </div>
        <div class="card-body">
            <canvas id="trendChart" height="120"></canvas>
        </div>
    </div>

    <!-- Course performance table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0">
            <h2 class="h6 fw-bold mb-0">Performance by Course</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Course</th>
                            <th>Title</th>
                            <th>Sessions Held</th>
                            <th>Attended</th>
                            <th>Avg %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coursePerf)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No course performance data available yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($coursePerf as $cp): ?>
                                <?php
                                    $held = (int) $cp['sessions_held'];
                                    $att  = (int) $cp['attended'];
                                    $pct  = $held > 0 ? round(($att / $held) * 100, 1) : 0;
                                    $pctStyle = $pct < ATTENDANCE_THRESHOLD
                                        ? 'color:var(--brand-danger);font-weight:700;'
                                        : 'color:var(--brand-success);font-weight:700;';
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?= sanitize_input($cp['course_code']) ?></td>
                                    <td><?= sanitize_input($cp['course_title']) ?></td>
                                    <td><?= $held ?></td>
                                    <td><?= $att ?></td>
                                    <td style="<?= $pctStyle ?>"><?= $pct ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Chart.js (safe CDN) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var ctx = document.getElementById('trendChart');
    if (!ctx || !window.Chart) return;
    new window.Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= $chartLabels ?>,
            datasets: [{
                label: 'Attendance %',
                data: <?= $chartData ?>,
                borderColor: '#0B6E4F',
                backgroundColor: 'rgba(11,110,79,0.15)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#F39200'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + '%'; } } },
                x: { title: { display: true, text: 'ISO Week' } }
            },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: function (c) { return c.parsed.y + '%'; } } }
            }
        }
    });
})();
</script>

<?php
require_once __DIR__ . '/../../../app/layouts/footer.php';

