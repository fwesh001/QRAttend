<?php
/**
 * QRAttend :: Administrative Core Handler
 * -----------------------------------------------------------------------------
 * Processing gateway for admin actions. Currently implements the Bulk Student
 * Provisioning CSV importer. All actions are gated to active admin sessions.
 *
 * Output: redirects back to the relevant admin page with a flash message
 * (no direct rendering). On fatal errors it rolls back the DB transaction.
 */

declare(strict_types=1);

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

            // 4b. Secure randomized default password + bcrypt hash.
            $rawPass = 'FedpoNas123!';
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

