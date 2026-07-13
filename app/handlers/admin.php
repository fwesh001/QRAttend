<?php declare(strict_types=1);
/**
 * QRAttend :: Administrative Core Handler
 * -----------------------------------------------------------------------------
 * Processing gateway for admin actions. Currently implements the Bulk Student
 * Provisioning CSV importer. All actions are gated to active admin sessions.
 *
 * Output: redirects back to the relevant admin page with a flash message
 * (no direct rendering). On fatal errors it rolls back the DB transaction.
 */

require_once __DIR__ . '/../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

// Admin-only gateway.
if (($_SESSION['user_type'] ?? null) !== 'admin') {
    set_flash_message('danger', 'Access Denied: Administrator privileges required.');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Route by the "action" parameter.
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

switch ($action) {

    // =========================================================================
    // BULK IMPORT STUDENTS (CSV)
    // =========================================================================
    case 'bulk_import_students':
        handleBulkImportStudents();
        break;

    case 'add_lecturer':
        handleAddLecturer();
        break;

    case 'add_course':
        handleAddCourse();
        break;

    case 'add_allocation':
        handleAddAllocation();
        break;

    case 'purge_logs':
        handlePurgeLogs();
        break;

    case 'delete_user':
        handleDeleteUser();
        break;

    default:
        set_flash_message('danger', 'Unknown administrative action.');
        header('Location: ' . APP_URL . '/portals/admin/students.php');
        exit;
}

/**
 * Parse an uploaded CSV of students and import them inside a transaction.
 */
function handleBulkImportStudents(): void
{
    // 1. Validate upload presence + CSV type/extension.
    if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash_message('danger', 'No CSV file uploaded or upload error occurred.');
        header('Location: ' . APP_URL . '/portals/admin/students.php');
        exit;
    }

    $name = $_FILES['csv_file']['name'] ?? '';
    $tmp  = $_FILES['csv_file']['tmp_name'] ?? '';
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = mime_content_type($tmp);

    $allowedMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
    if ($ext !== 'csv' || !in_array($mime, $allowedMimes, true)) {
        set_flash_message('danger', 'Invalid file. Please upload a valid .csv document.');
        header('Location: ' . APP_URL . '/portals/admin/students.php');
        exit;
    }

    // 2. Open the file stream.
    $handle = fopen($tmp, 'r');
    if ($handle === false) {
        set_flash_message('danger', 'Could not open the uploaded file for reading.');
        header('Location: ' . APP_URL . '/portals/admin/students.php');
        exit;
    }

    // 3. Begin a transaction.
    try {
        $db = get_db();
        $db->beginTransaction();

        // 4. Read header row (Matric Number, Full Name, Level, Department ID, Email).
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $db->rollBack();
            set_flash_message('danger', 'The CSV file appears to be empty.');
            header('Location: ' . APP_URL . '/portals/admin/students.php');
            exit;
        }

        // 5. Airtight prepared statement with ON DUPLICATE KEY UPDATE.
        $stmt = $db->prepare(
            'INSERT INTO students (matric_no, name, level, department_id, email, password)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), level = VALUES(level)'
        );

        $successCount = 0;
        $failCount    = 0;
        $rowNumber    = 1; // header was row 1

        // 6. Loop rows.
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            // Expect exactly 5 columns; skip malformed rows gracefully.
            if (count($row) < 5) {
                $failCount++;
                continue;
            }

            $matricNo = trim((string) $row[0]);
            $fullName = trim((string) $row[1]);
            $level    = trim((string) $row[2]);
            $deptId   = filter_var($row[3], FILTER_VALIDATE_INT);
            $email    = trim((string) $row[4]);

            // Basic field validation.
            if ($matricNo === '' || $fullName === '' || $deptId === false || $deptId <= 0
                || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failCount++;
                continue;
            }

            // 4b. Default password + bcrypt hash.
            $rawPass = 'password123';
            $hash    = password_hash($rawPass, PASSWORD_BCRYPT);

            $stmt->execute([
                $matricNo,
                $fullName,
                $level,
                $deptId,
                $email,
                $hash,
            ]);
            $successCount++;
        }

        fclose($handle);

        // 7. Commit + audit + success flash.
        $db->commit();
        log_activity(
            $db, 'admin', (int) $_SESSION['user_id'],
            "Bulk imported students: {$successCount} success, {$failCount} skipped",
            get_client_ip()
        );

        $msg = "Import complete: {$successCount} student(s) processed successfully.";
        if ($failCount > 0) {
            $msg .= " {$failCount} row(s) skipped (malformed/duplicate).";
        }
        set_flash_message('success', $msg);
    } catch (RuntimeException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        set_flash_message('danger', 'Import failed: ' . $e->getMessage());
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[QRAttend] bulk student import error: ' . $e->getMessage());
        set_flash_message('danger', 'Import failed: a database error occurred while processing rows.');
    }

    header('Location: ' . APP_URL . '/portals/admin/students.php');
    exit;
}

/**
 * Add a single lecturer (faculty member) with a secure default password.
 */
function handleAddLecturer(): void
{
    $staffNo = trim((string) ($_POST['staff_no'] ?? ''));
    $name    = trim((string) ($_POST['name'] ?? ''));
    $email   = trim((string) ($_POST['email'] ?? ''));
    $deptId  = filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT);
    $rawPass = trim((string) ($_POST['password'] ?? ''));

    if ($staffNo === '' || $name === '' || $deptId === false || $deptId <= 0
        || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash_message('danger', 'Please complete all lecturer fields with valid values.');
        header('Location: ' . APP_URL . '/portals/admin/lecturers.php');
        exit;
    }

    try {
        $db = get_db();
        // Use the submitted password, or the default when left blank.
        if ($rawPass === '') {
            $rawPass = 'password123';
        }
        $hash = password_hash($rawPass, PASSWORD_BCRYPT);

        $stmt = $db->prepare(
            'INSERT INTO lecturers (staff_no, name, email, password, department_id)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email),
                                     department_id = VALUES(department_id)'
        );
        $stmt->execute([$staffNo, $name, $email, $hash, $deptId]);

        log_activity(
            $db, 'admin', (int) $_SESSION['user_id'],
            "Added/updated lecturer {$staffNo} ({$name})",
            get_client_ip()
        );
        set_flash_message('success', "Lecturer '{$name}' saved. Temporary password: {$rawPass}");
    } catch (RuntimeException $e) {
        set_flash_message('danger', 'Save failed: ' . $e->getMessage());
    } catch (PDOException $e) {
        error_log('[QRAttend] add lecturer error: ' . $e->getMessage());
        set_flash_message('danger', 'Save failed: a database error occurred.');
    }

    header('Location: ' . APP_URL . '/portals/admin/lecturers.php');
    exit;
}

/**
 * Add a single course (curriculum entry).
 */
function handleAddCourse(): void
{
    $code    = trim((string) ($_POST['course_code'] ?? ''));
    $title   = trim((string) ($_POST['course_title'] ?? ''));
    $units   = filter_var($_POST['credit_units'] ?? null, FILTER_VALIDATE_INT);

    if ($code === '' || $title === '' || $units === false || $units < 0) {
        set_flash_message('danger', 'Please provide a valid course code, title, and credit units.');
        header('Location: ' . APP_URL . '/portals/admin/courses.php');
        exit;
    }

    try {
        $db = get_db();
        $stmt = $db->prepare(
            'INSERT INTO courses (course_code, course_title, credit_units)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE course_title = VALUES(course_title),
                                     credit_units = VALUES(credit_units)'
        );
        $stmt->execute([$code, $title, $units]);

        log_activity(
            $db, 'admin', (int) $_SESSION['user_id'],
            "Added/updated course {$code} ({$title})",
            get_client_ip()
        );
        set_flash_message('success', "Course '{$code}' saved successfully.");
    } catch (RuntimeException $e) {
        set_flash_message('danger', 'Save failed: ' . $e->getMessage());
    } catch (PDOException $e) {
        error_log('[QRAttend] add course error: ' . $e->getMessage());
        set_flash_message('danger', 'Save failed: a database error occurred.');
    }

    header('Location: ' . APP_URL . '/portals/admin/courses.php');
    exit;
}

/**
 * Bind a lecturer to a course (course allocation).
 */
function handleAddAllocation(): void
{
    $lecturerId = filter_var($_POST['lecturer_id'] ?? null, FILTER_VALIDATE_INT);
    $courseId   = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);

    if ($lecturerId === false || $lecturerId <= 0 || $courseId === false || $courseId <= 0) {
        set_flash_message('danger', 'Please select both a lecturer and a course.');
        header('Location: ' . APP_URL . '/portals/admin/allocations.php');
        exit;
    }

    try {
        $db = get_db();
        // Composite UNIQUE(lecturer_id, course_id) blocks duplicates.
        $stmt = $db->prepare(
            'INSERT INTO course_allocations (lecturer_id, course_id)
             VALUES (?, ?)'
        );
        $stmt->execute([$lecturerId, $courseId]);

        log_activity(
            $db, 'admin', (int) $_SESSION['user_id'],
            "Allocated lecturer #{$lecturerId} to course #{$courseId}",
            get_client_ip()
        );
        set_flash_message('success', 'Course allocation saved successfully.');
    } catch (RuntimeException $e) {
        set_flash_message('danger', 'Allocation failed: ' . $e->getMessage());
    } catch (PDOException $e) {
        // 23000 = integrity violation (duplicate allocation / bad FK)
        if ($e->getCode() === '23000') {
            set_flash_message('warning', 'This lecturer is already allocated to that course.');
        } else {
            error_log('[QRAttend] add allocation error: ' . $e->getMessage());
            set_flash_message('danger', 'Allocation failed: a database error occurred.');
        }
    }

    header('Location: ' . APP_URL . '/portals/admin/allocations.php');
    exit;
}

/**
 * Purge the audit_logs table (admin-only destructive action).
 * The purge event itself is recorded to a separate secure sys_log file so the
 * action is never fully erased from the system's knowledge.
 */
function handlePurgeLogs(): void
{
    try {
        $db = get_db();

        // Count before purge for the record.
        $countStmt = $db->prepare('SELECT COUNT(*) FROM audit_logs');
        $countStmt->execute();
        $purged = (int) $countStmt->fetchColumn();

        $del = $db->prepare('DELETE FROM audit_logs');
        $del->execute();

        // Record the purge in a separate secure sys_log (outside the DB table).
        $sysMsg = sprintf(
            "[%s] AUDIT PURGE by admin #%d (%s) — %d row(s) removed.\n",
            date('Y-m-d H:i:s'),
            (int) $_SESSION['user_id'],
            get_client_ip(),
            $purged
        );
        // sys_log lives one level above public/ (outside web root) for safety.
        $sysLogPath = __DIR__ . '/../../storage/sys_log.txt';
        if (!is_dir(dirname($sysLogPath))) {
            mkdir(dirname($sysLogPath), 0750, true);
        }
        file_put_contents($sysLogPath, $sysMsg, FILE_APPEND | LOCK_EX);

        set_flash_message('success', "Audit logs purged ({$purged} record(s) removed).");
    } catch (RuntimeException $e) {
        set_flash_message('danger', 'Purge failed: ' . $e->getMessage());
    } catch (PDOException $e) {
        error_log('[QRAttend] purge logs error: ' . $e->getMessage());
        set_flash_message('danger', 'Purge failed: a database error occurred.');
    }

    header('Location: ' . APP_URL . '/portals/admin/backup.php');
    exit;
}

/**
 * Delete a user (student or lecturer) by type + id.
 * Admins cannot delete themselves or other administrators.
 */
function handleDeleteUser(): void
{
    $type = trim((string) ($_POST['user_type'] ?? ''));
    $id   = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);

    $allowed = ['student' => 'students', 'lecturer' => 'lecturers'];
    if (!isset($allowed[$type]) || $id === false || $id <= 0) {
        set_flash_message('danger', 'Invalid user reference for deletion.');
        header('Location: ' . APP_URL . '/portals/admin/students.php');
        exit;
    }

    // Safety: never allow an admin to delete their own account.
    if ($type === 'admin' || (int) ($_SESSION['user_id'] ?? 0) === $id) {
        set_flash_message('danger', 'You cannot delete this account.');
        header('Location: ' . APP_URL . '/portals/admin/students.php');
        exit;
    }

    try {
        $db = get_db();
        $table = $allowed[$type];
        $stmt = $db->prepare("DELETE FROM `{$table}` WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            set_flash_message('warning', 'No matching user was found to delete.');
        } else {
            log_activity(
                $db, 'admin', (int) $_SESSION['user_id'],
                "Deleted {$type} #{$id}",
                get_client_ip()
            );
            set_flash_message('success', ucfirst($type) . ' deleted successfully.');
        }
    } catch (RuntimeException $e) {
        set_flash_message('danger', 'Delete failed: ' . $e->getMessage());
    } catch (PDOException $e) {
        error_log('[QRAttend] delete user error: ' . $e->getMessage());
        set_flash_message('danger', 'Delete failed: a database error occurred.');
    }

    header('Location: ' . APP_URL . '/portals/admin/' . ($type === 'lecturer' ? 'lecturers.php' : 'students.php'));
    exit;
}

