<?php declare(strict_types=1);
/**
 * QRAttend :: Session Engine & AJAX Endpoint
 * -----------------------------------------------------------------------------
 * Two responsibilities, selected by request method / parameters:
 *
 *   1) INIT  (POST + allocation_id)  -> create a new attendance_sessions row
 *   2) POLL  (GET/POST + session_id) -> return live state + auto-rotate token
 *
 * All DB access uses native prepared statements (EMULATE_PREPARES=false from
 * database.php). Output is JSON for the polling branch; init returns JSON too
 * so the launcher page can react without a full reload.
 */

require_once __DIR__ . '/../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

// Always speak JSON on this endpoint.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

/**
 * Standard JSON response helper.
 */
function session_json(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

// =============================================================================
// 1. SESSION INITIALIZATION
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocation_id'])) {

    // Must be an authenticated lecturer.
    if (($_SESSION['user_type'] ?? null) !== 'lecturer') {
        session_json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    $allocationId = filter_var($_POST['allocation_id'], FILTER_VALIDATE_INT);
    if ($allocationId === false || $allocationId <= 0) {
        session_json(['success' => false, 'message' => 'Invalid allocation.'], 400);
    }

    // Optional session duration (minutes). Falls back to the lecturer's saved
    // default, then the global constant. Clamped to a sane 1-180 range.
    $duration = filter_var($_POST['duration'] ?? null, FILTER_VALIDATE_INT);
    if ($duration === false || $duration < 1 || $duration > 180) {
        $duration = null;
    }

    // Optional max students (class size) for this session. Null = unlimited.
    $maxStudents = filter_var($_POST['max_students'] ?? null, FILTER_VALIDATE_INT);
    if ($maxStudents === false || $maxStudents < 1) {
        $maxStudents = null;
    }

    try {
        $db = get_db();

        // Verify the lecturer owns this allocation (anti-spoofing).
        $ownStmt = $db->prepare(
            'SELECT id FROM course_allocations WHERE id = :id AND lecturer_id = :lecturer'
        );
        $ownStmt->execute([
            ':id'       => $allocationId,
            ':lecturer' => (int) $_SESSION['user_id'],
        ]);
        if ($ownStmt->fetch() === false) {
            session_json(['success' => false, 'message' => 'Allocation not owned by lecturer.'], 403);
        }

        // Resolve the effective duration: explicit request -> lecturer default -> constant.
        if ($duration === null) {
            $defStmt = $db->prepare(
                'SELECT default_duration_minutes FROM lecturers WHERE id = :lid'
            );
            $defStmt->execute([':lid' => (int) $_SESSION['user_id']]);
            $duration = (int) ($defStmt->fetchColumn() ?: SESSION_DEFAULT_MINUTES);
        }

        // Reuse an existing OPEN, non-expired session for this allocation so
        // that switching tabs / leaving and returning does not spawn duplicate
        // or orphaned sessions. Otherwise mint a fresh one.
        $reuse = $db->prepare(
            'SELECT id, qr_token, session_pin, expires_at, duration_minutes
             FROM attendance_sessions
             WHERE course_allocation_id = :alloc AND status = :open
               AND expires_at > NOW()
             ORDER BY expires_at DESC
             LIMIT 1'
        );
        $reuse->execute([':alloc' => $allocationId, ':open' => 'Open']);
        $existing = $reuse->fetch(PDO::FETCH_ASSOC);

        if ($existing !== false) {
            session_json([
                'success'       => true,
                'session_id'    => (int) $existing['id'],
                'qr_token'      => $existing['qr_token'],
                'session_pin'   => $existing['session_pin'],
                'expires_at'    => $existing['expires_at'],
                'duration'      => (int) $existing['duration_minutes'],
                'max_students' => $existing['max_students'] === null ? null : (int) $existing['max_students'],
                'reused'        => true,
            ]);
        }

        // Generate cryptographic token + 6-digit numeric pin.
        $qrToken = bin2hex(random_bytes(16));               // 32-char hex
        $sessionPin = (string) random_int(100000, 999999);  // 6-digit backup pin

        $expiresAt = date('Y-m-d H:i:s', time() + $duration * 60);

        // Persist this duration as the lecturer's new default for next time.
        $upd = $db->prepare(
            'UPDATE lecturers SET default_duration_minutes = :dur WHERE id = :lid'
        );
        $upd->execute([':dur' => $duration, ':lid' => (int) $_SESSION['user_id']]);

        $ins = $db->prepare(
            'INSERT INTO attendance_sessions
                (course_allocation_id, qr_token, session_pin, duration_minutes, max_students, status, expires_at)
             VALUES (:alloc, :token, :pin, :dur, :max, :status, :expires)'
        );
        $ins->execute([
            ':alloc'   => $allocationId,
            ':token'   => $qrToken,
            ':pin'     => $sessionPin,
            ':dur'     => $duration,
            ':max'     => $maxStudents,
            ':status'  => 'Open',
            ':expires' => $expiresAt,
        ]);

        $sessionId = (int) $db->lastInsertId();

        // Refresh the lecturer's default in-session so the modal pre-fills next time.
        $_SESSION['default_duration_minutes'] = $duration;

        log_activity(
            $db, 'lecturer', (int) $_SESSION['user_id'],
            "Launched attendance session #{$sessionId} for allocation #{$allocationId}",
            get_client_ip()
        );

        session_json([
            'success'       => true,
            'session_id'    => $sessionId,
            'qr_token'      => $qrToken,
            'session_pin'   => $sessionPin,
            'expires_at'    => $expiresAt,
            'duration'      => $duration,
            'max_students' => $maxStudents === null ? null : (int) $maxStudents,
        ]);
    } catch (RuntimeException $e) {
        session_json(['success' => false, 'message' => $e->getMessage()], 500);
    } catch (PDOException $e) {
        error_log('[QRAttend] session init error: ' . $e->getMessage());
        session_json(['success' => false, 'message' => 'Could not create session.'], 500);
    }
}

// =============================================================================
// 2. REAL-TIME AJAX POLLING  (+ Auto-Token Rotation Anti-Cheat)
// =============================================================================
if (isset($_REQUEST['session_id'])) {

    $sessionId = filter_var($_REQUEST['session_id'], FILTER_VALIDATE_INT);
    if ($sessionId === false || $sessionId <= 0) {
        session_json(['success' => false, 'message' => 'Invalid session.'], 400);
    }

    try {
        $db = get_db();

        // Fetch current session state + active token.
        $stmt = $db->prepare(
            'SELECT s.id, s.status, s.qr_token, s.expires_at, s.course_allocation_id,
                    a.lecturer_id
             FROM attendance_sessions s
             JOIN course_allocations a ON a.id = s.course_allocation_id
             WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session === false) {
            session_json(['success' => false, 'message' => 'Session not found.'], 404);
        }

        // Enforce ownership for lecturers polling their own session.
        if (($_SESSION['user_type'] ?? null) === 'lecturer'
            && (int) $session['lecturer_id'] !== (int) $_SESSION['user_id']) {
            session_json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        // Count checked-in students for this session.
        $cnt = $db->prepare(
            'SELECT COUNT(*) AS total FROM attendance_records WHERE session_id = :id'
        );
        $cnt->execute([':id' => $sessionId]);
        $checkedIn = (int) $cnt->fetchColumn();

        // Determine expiry.
        $expiresTs = strtotime($session['expires_at']);
        $nowTs = time();
        $remaining = max(0, $expiresTs - $nowTs);
        $expired = ($remaining <= 0) || $session['status'] === 'Closed';

        // ---- AUTO-TOKEN ROTATION ANTI-CHEAT RULE -----------------------------
        $rotated = false;
        $baseline = (int) TOKEN_ROTATE_DEFAULT; // default 5 (config constant)
        if (!$expired && $checkedIn > 0 && ($checkedIn % $baseline) === 0) {
            // Boundary crossed -> mint a fresh token and persist it.
            $newToken = bin2hex(random_bytes(16));
            $upd = $db->prepare(
                'UPDATE attendance_sessions SET qr_token = :token WHERE id = :id'
            );
            $upd->execute([':token' => $newToken, ':id' => $sessionId]);
            $session['qr_token'] = $newToken;
            $rotated = true;

            log_activity(
                $db,
                'lecturer',
                (int) ($_SESSION['user_id'] ?? 0),
                "Auto-rotated QR token for session #{$sessionId} at {$checkedIn} check-ins",
                get_client_ip()
            );
        }
        // ---------------------------------------------------------------------

        session_json([
            'success'    => true,
            'session_id' => $sessionId,
            'status'     => $expired ? 'Expired' : $session['status'],
            'qr_token'   => $session['qr_token'],
            'checked_in' => $checkedIn,
            'remaining'  => $remaining,
            'expires_at' => $session['expires_at'],
            'rotated'    => $rotated,
        ]);
    } catch (RuntimeException $e) {
        session_json(['success' => false, 'message' => $e->getMessage()], 500);
    } catch (PDOException $e) {
        error_log('[QRAttend] session poll error: ' . $e->getMessage());
        session_json(['success' => false, 'message' => 'Polling failed.'], 500);
    }
}

// No recognized action.
session_json(['success' => false, 'message' => 'Bad request.'], 400);

