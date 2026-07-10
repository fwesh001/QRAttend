<?php
/**
 * QRAttend :: Attendance Verification Processor (AJAX-only gateway)
 * -----------------------------------------------------------------------------
 * Accepts POST payloads containing EITHER a qr_token OR a session_pin and:
 *   1. Confirms the caller is an authenticated student
 *   2. Validates the session (Open, matches token/pin, not expired)
 *   3. Blocks duplicate check-ins (anti-fraud)
 *   4. Inserts a 'Present' record + logs the event
 *
 * Output is always JSON. No direct browser rendering.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function attendance_json(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

// AJAX-only: must be a POST from an authenticated student.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    attendance_json(['success' => false, 'message' => 'Method not allowed.'], 405);
}
if (($_SESSION['user_type'] ?? null) !== 'student' || empty($_SESSION['user_id'])) {
    attendance_json(['success' => false, 'message' => 'Unauthorized.'], 403);
}

$token = trim((string) ($_POST['qr_token'] ?? ''));
$pin   = trim((string) ($_POST['session_pin'] ?? ''));

if ($token === '' && $pin === '') {
    attendance_json(['success' => false, 'message' => 'No token or pin supplied.'], 400);
}

try {
    $db = get_db();
    $studentId = (int) $_SESSION['user_id'];

    // 2. Resolve the active session by token OR pin.
    //    Use a single parameterized query with OR; both placeholders are bound.
    if ($token !== '') {
        $lookup = $db->prepare(
            'SELECT id, status, expires_at
             FROM attendance_sessions
             WHERE qr_token = :token AND status = :open
             LIMIT 1'
        );
        $lookup->execute([':token' => $token, ':open' => 'Open']);
    } else {
        $lookup = $db->prepare(
            'SELECT id, status, expires_at
             FROM attendance_sessions
             WHERE session_pin = :pin AND status = :open
             LIMIT 1'
        );
        $lookup->execute([':pin' => $pin, ':open' => 'Open']);
    }
    $session = $lookup->fetch(PDO::FETCH_ASSOC);

    if ($session === false) {
        attendance_json(
            ['success' => false, 'message' => 'No active session matches this code.'],
            404
        );
    }

    // Time-validity check (strictly before expiry).
    $expiresTs = strtotime($session['expires_at']);
    if ($expiresTs === false || time() >= $expiresTs) {
        attendance_json(
            ['success' => false, 'message' => 'This attendance session has expired.'],
            410
        );
    }

    $sessionId = (int) $session['id'];

    // 3. Anti-fraud: reject duplicate check-ins.
    $dup = $db->prepare(
        'SELECT id FROM attendance_records
         WHERE student_id = :sid AND session_id = :sessid LIMIT 1'
    );
    $dup->execute([':sid' => $studentId, ':sessid' => $sessionId]);
    if ($dup->fetch() !== false) {
        attendance_json(
            ['success' => false, 'message' => 'Duplicate attempt: Your attendance is already logged.'],
            409
        );
    }

    // 4. Insert the Present record.
    $ins = $db->prepare(
        'INSERT INTO attendance_records (student_id, session_id, attendance_status, scanned_at)
         VALUES (:sid, :sessid, :status, NOW())'
    );
    $ins->execute([
        ':sid'    => $studentId,
        ':sessid' => $sessionId,
        ':status' => 'Present',
    ]);

    log_activity(
        $db, 'student', $studentId,
        "Marked present for session #{$sessionId}",
        get_client_ip()
    );

    attendance_json([
        'success'  => true,
        'redirect' => 'progress.php',
    ]);
} catch (RuntimeException $e) {
    attendance_json(['success' => false, 'message' => $e->getMessage()], 500);
} catch (PDOException $e) {
    error_log('[QRAttend] attendance insert error: ' . $e->getMessage());
    attendance_json(['success' => false, 'message' => 'Could not record attendance.'], 500);
}

