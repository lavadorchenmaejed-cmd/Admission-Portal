<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_Id'])) {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . '/../database.php';
$config = require __DIR__ . '/../config.php';
$db     = new Database($config);
$conn   = $db->getConnection();

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

$user_Id = $_SESSION['user_id'] ?? $_SESSION['user_Id'] ?? null;

// Get applicant_id
$applicantId = null;
if (!empty($_SESSION['applicant_id'])) {
    $applicantId = $_SESSION['applicant_id'];
} elseif ($user_Id) {
    $stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE user_Id = ? LIMIT 1");
    $stmt->bind_param('i', $user_Id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $applicantId = $row['applicant_id'];
        $_SESSION['applicant_id'] = $applicantId;
    }
}

if (!$applicantId) {
    $_SESSION['success'] = "Applicant not found.";
    header("Location: app_status.php");
    exit();
}

// Get most recent school year regardless of deadline
$yearId = null;
$yearStmt = $conn->prepare("SELECT year_id FROM school_year ORDER BY year_id DESC LIMIT 1");
$yearStmt->execute();
$yearRow = $yearStmt->get_result()->fetch_assoc();
$yearStmt->close();

if ($yearRow) {
    $yearId = $yearRow['year_id'];
} else {
    $_SESSION['success'] = "No school year found. Please contact the administrator.";
    header("Location: app_status.php");
    exit();
}

$uploadDir   = __DIR__ . '/../uploads/photos/';
$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
$uploadedCount = 0;
$errors        = [];

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

foreach ($_FILES as $docType => $file) {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Upload error on {$docType}: code " . $file['error'];
        continue;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        $errors[] = "{$docType}: file type not allowed ({$ext}).";
        continue;
    }

    $fileName = $applicantId . '_' . $docType . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        $errors[] = "{$docType}: failed to save file. Check folder permissions.";
        continue;
    }

    // Check if a record already exists for this applicant + docType + year
    $check = $conn->prepare("
        SELECT a.application_id, a.document_id
        FROM application a
        JOIN documents d ON a.document_id = d.document_id
        WHERE a.applicant_id = ? AND d.document_name = ? AND a.year_id = ?
        LIMIT 1
    ");
    $check->bind_param("isi", $applicantId, $docType, $yearId);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        // Update the existing document record
        $stmt = $conn->prepare("
            UPDATE documents
            SET file_path       = ?,
                submission_date = ?
            WHERE document_id = ?
        ");
        $stmt->bind_param("ssi", $fileName, $today, $existing['document_id']);
        if (!$stmt->execute()) {
            $errors[] = "{$docType}: failed to update document — " . $stmt->error;
            $stmt->close();
            continue;
        }
        $stmt->close();

        // Update status in application table
        $stmt = $conn->prepare("
            UPDATE application
            SET status = 'Under Review'
            WHERE application_id = ?
        ");
        $stmt->bind_param("i", $existing['application_id']);
        if ($stmt->execute()) {
            $uploadedCount++;
        } else {
            $errors[] = "{$docType}: failed to update application status — " . $stmt->error;
        }
        $stmt->close();

    } else {
        // Insert new record into documents table
        $stmt = $conn->prepare("
            INSERT INTO documents (document_name, file_path, submission_date)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("sss", $docType, $fileName, $today);
        if (!$stmt->execute()) {
            $errors[] = "{$docType}: failed to insert document — " . $stmt->error;
            $stmt->close();
            continue;
        }
        $documentId = $stmt->insert_id;
        $stmt->close();

        // Insert into application table with the new document_id
        $stmt = $conn->prepare("
            INSERT INTO application (applicant_id, year_id, document_id, status)
            VALUES (?, ?, ?, 'Under Review')
        ");
        $stmt->bind_param("iii", $applicantId, $yearId, $documentId);
        if ($stmt->execute()) {
            $uploadedCount++;
        } else {
            $errors[] = "{$docType}: failed to insert application record — " . $stmt->error;
        }
        $stmt->close();
    }
}

if ($uploadedCount > 0) {
    $_SESSION['success'] = $uploadedCount . " document" . ($uploadedCount > 1 ? "s" : "") . " uploaded successfully.";
} elseif (!empty($errors)) {
    $_SESSION['success'] = "Upload failed: " . implode(" | ", $errors);
} else {
    $_SESSION['success'] = "No files were selected for upload.";
}

header("Location: app_status.php");
exit();