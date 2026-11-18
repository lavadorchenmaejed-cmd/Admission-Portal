<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_Id']) || ($_SESSION['role'] ?? '') !== 'applicant') {
    header("Location: ../pages/log_in.php");
    exit();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_Id'])) {
    header("Location: ../pages/log_in.php");
    exit();
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../Model/profile.php';
require_once __DIR__ . '/../Model/user.php';
require_once __DIR__ . '/../Model/program.php';
require_once __DIR__ . '/../Model/application.php';
require_once __DIR__ . '/../Controller/userController.php';
require_once __DIR__ . '/../Controller/programController.php';
require_once __DIR__ . '/../Controller/applicationController.php';
$config = require __DIR__ . '/../config.php';

$db   = new Database($config);
$conn = $db->getConnection();

$profileController    = new ProfileController(new Profile($conn));
$userController       = new UserController(new User($conn));
$programController    = new ProgramController(new Program($conn));
$applicatonController = new ApplicationController(new Application($conn));

$user_Id = $_SESSION['user_id'] ?? $_SESSION['user_Id'] ?? null;
$applicant = null;

// ── Load existing applicant record ──
if (!empty($_SESSION['applicant_id'])) {
    $applicant = $profileController->getById($_SESSION['applicant_id']);
} elseif ($user_Id) {
    $stmt = $conn->prepare("SELECT * FROM applicants WHERE user_Id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $user_Id);
        $stmt->execute();
        $applicant = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($applicant && !empty($applicant['applicant_id'])) {
            $_SESSION['applicant_id'] = $applicant['applicant_id'];
        }
    }
}

// ── Re-fetch photo ──
if ($applicant && !empty($applicant['applicant_id'])) {
    $photoStmt = $conn->prepare("SELECT photo FROM applicants WHERE applicant_id = ? LIMIT 1");
    if ($photoStmt) {
        $photoStmt->bind_param('i', $applicant['applicant_id']);
        $photoStmt->execute();
        $photoRow = $photoStmt->get_result()->fetch_assoc();
        $photoStmt->close();
        if ($photoRow && isset($photoRow['photo'])) {
            $applicant['photo'] = $photoRow['photo'];
        }
    }
}

// ── Fetch current user email ──
$currentUser = $userController->getByUserId($user_Id);
$userEmail   = $currentUser['email'] ?? '';

// ── Fetch all programs ──
$programs = $programController->getAll();

// ── Documents logic ──
$uploadedDocs      = [];
$totalRequired     = 0;
$documentsUploaded = 0;
$progressPercent   = 0;
$applicationStatus = 'Pending';
$uploadedFiles     = [];
$requiredDocs      = [];
$rejectionReason   = null;

if ($applicant && !empty($applicant['applicant_id'])) {
    $applicantId = $applicant['applicant_id'];

    if ($applicant['student_type'] === 'Transferee') {
        $requiredDocs = [
            'tor'                   => 'Transcript of Records (TOR)',
            'good_moral_transferee' => 'Good Moral Certificate (ORIGINAL)',
            'psa_transferee'        => 'NSO/PSA Birth Certificate (ORIGINAL)'
        ];
    } elseif ($applicant['student_type'] === '1st Year') {
        $requiredDocs = [
            'report_card'          => 'Report Card (ORIGINAL)',
            'good_moral_firstyear' => 'Good Moral Certificate (ORIGINAL)',
            'psa_firstyear'        => 'NSO/PSA Birth Certificate (ORIGINAL)'
        ];
    }

    $totalRequired = count($requiredDocs);

    $stmt = $conn->prepare("
        SELECT d.document_name, d.file_path, a.status, a.rejection_reason
        FROM application a
        JOIN documents d ON a.document_id = d.document_id
        WHERE a.applicant_id = ?
    ");
    $stmt->bind_param("i", $applicantId);
    $stmt->execute();
    $result   = $stmt->get_result();
    $dbStatus = null;
    while ($row = $result->fetch_assoc()) {
        $uploadedFiles[$row['document_name']] = $row['file_path'];
        if (!empty($row['status'])) {
            $dbStatus = $row['status'];
        }
        if (!empty($row['rejection_reason'])) {
            $rejectionReason = $row['rejection_reason'];
        }
    }
    $stmt->close();

    if ($dbStatus !== null) {
        $applicationStatus = $dbStatus;
    }

    foreach ($requiredDocs as $docType => $docName) {
        $uploadedDocs[$docType] = isset($uploadedFiles[$docType]);
        if ($uploadedDocs[$docType]) $documentsUploaded++;
    }

    if ($totalRequired > 0) {
        $progressPercent = min(100, ($documentsUploaded / $totalRequired) * 100);
    }

    if ($documentsUploaded === 0 && $dbStatus === null) {
        $applicationStatus = 'Pending';
    }
}

$isSubmitted = in_array($applicationStatus, ['Under Review', 'Approved', 'Rejected']);

$step = 'form';
if ($applicant) {
    if ($isSubmitted) {
        $step = 'status';
    } elseif (isset($_GET['step']) && $_GET['step'] === 'review') {
        $step = 'review';
    } elseif (isset($_GET['mode']) && $_GET['mode'] === 'edit') {
        $step = 'form';
    } else {
        $step = 'review'; // has applicant but not submitted → review mode
    }
}

// ── POST: Save/Update profile ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'update') {
        $Applicant_name = $_POST["Applicant_name"] ?? '';
        $Father_name    = $_POST["Father_name"] ?? '';
        $Mother_name    = $_POST["Mother_name"] ?? '';
        $birthdate      = $_POST["birthdate"] ?? '';
        $gender         = $_POST["gender"] ?? '';
        $contact_number = $_POST["contact_number"] ?? '';
        $address        = $_POST["address"] ?? '';
        $religion       = $_POST["religion"] ?? '';
        $nationality    = $_POST["nationality"] ?? '';
        $student_type   = $_POST["student_type"] ?? null;
        $program_id     = $_POST["program_id"] ?? null;
        $email          = trim($_POST["email"] ?? '');

        $photoPath = $applicant['photo'] ?? null;

        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $targetDir = __DIR__ . "/../uploads/photos/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $fileName   = uniqid() . "_" . basename($_FILES["photo"]["name"]);
            $targetFile = $targetDir . $fileName;
            if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFile)) {
                $photoPath = "../uploads/photos/" . $fileName;
            }
        }

        $pattern = '/^[\p{L}0-9 ]*(?:-[\p{L}0-9 ]*)?$/u';
        $fields_to_validate = [
            'Applicant_name' => $Applicant_name,
            'Father_name'    => $Father_name,
            'Mother_name'    => $Mother_name,
            'address'        => $address,
            'religion'       => $religion,
            'nationality'    => $nationality,
        ];
        foreach ($fields_to_validate as $field => $val) {
            if (!preg_match($pattern, $val)) {
                $_SESSION['error'] = ucfirst(str_replace('_', ' ', $field)) . ": only letters, numbers, spaces and at most one hyphen (-) are allowed.";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            }
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Please enter a valid email address.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }

        if ($action === 'add') {
            $existing = $profileController->getByContactNumber($contact_number);
            if ($existing) {
                $_SESSION['duplicate_contact'] = true;
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            $applicant_id = $profileController->store(
                $Applicant_name, $user_Id, $Father_name, $Mother_name,
                $birthdate, $gender, $contact_number, $address,
                $religion, $nationality, $photoPath, $student_type, $program_id
            );
            if (!empty($email)) $userController->updateEmail($user_Id, $email);
            $_SESSION['applicant_id'] = $applicant_id;
            $_SESSION['success'] = "Profile saved! Please review your details before submitting.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?step=review");
            exit();
        }

        if ($action === 'update' && isset($_SESSION['applicant_id'])) {
            $existing = $profileController->getByContactNumber($contact_number);
            if ($existing && $existing['applicant_id'] != $_SESSION['applicant_id']) {
                $_SESSION['duplicate_contact'] = true;
                header("Location: " . $_SERVER['PHP_SELF'] . "?mode=edit");
                exit();
            }
            $profileController->update(
                $Applicant_name, $Father_name, $Mother_name, $birthdate,
                $gender, $contact_number, $address, $religion, $nationality,
                $_SESSION['applicant_id']
            );
            if (!empty($email)) $userController->updateEmail($user_Id, $email);
            $_SESSION['success'] = "Profile updated! Review your details before submitting.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?step=review");
            exit();
        }
    }

    // ── Submit Application (uploads + set Under Review) ──
    if ($action === 'submit_application' && isset($_SESSION['applicant_id'])) {
        $applicantId = $_SESSION['applicant_id'];
        // Re-fetch applicant for student_type
        $applicant = $profileController->getById($applicantId);
        if ($applicant['student_type'] === 'Transferee') {
            $docs = ['tor', 'good_moral_transferee', 'psa_transferee'];
        } else {
            $docs = ['report_card', 'good_moral_firstyear', 'psa_firstyear'];
        }

        $targetDir = __DIR__ . "/../uploads/photos/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        foreach ($docs as $docType) {
            if (!empty($_FILES[$docType]['name']) && $_FILES[$docType]['error'] === UPLOAD_ERR_OK) {
                $fileName   = uniqid() . "_" . basename($_FILES[$docType]["name"]);
                $targetFile = $targetDir . $fileName;
                if (move_uploaded_file($_FILES[$docType]["tmp_name"], $targetFile)) {

                    // ── INSERT into documents with uploaded_at timestamp ──
                    $docStmt = $conn->prepare("INSERT INTO documents (document_name, file_path, submission_date) VALUES (?, ?, NOW())");
                    $docStmt->bind_param("ss", $docType, $fileName);
                    $docStmt->execute();
                    $docId = $conn->insert_id;
                    $docStmt->close();

                    $appStmt = $conn->prepare("INSERT INTO application (applicant_id, document_id, status) VALUES (?, ?, 'Under Review') ON DUPLICATE KEY UPDATE document_id=VALUES(document_id), status='Under Review'");
                    $appStmt->bind_param("ii", $applicantId, $docId);
                    $appStmt->execute();
                    $appStmt->close();
                }
            }
        }

        // Ensure status is set even if re-submitting
        $statusStmt = $conn->prepare("UPDATE application SET status='Under Review' WHERE applicant_id=?");
        $statusStmt->bind_param("i", $applicantId);
        $statusStmt->execute();
        $statusStmt->close();

        $_SESSION['success'] = "Application submitted successfully! We will review your documents.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ── Status modal logic ──
$showStatusModal = false;
if (in_array($applicationStatus, ['Approved', 'Rejected'])) {
    if (!isset($_SESSION['last_known_status']) || $_SESSION['last_known_status'] !== $applicationStatus) {
        $showStatusModal = true;
    }
}
$_SESSION['last_known_status'] = $applicationStatus;

// ── Exam schedule ──
$examSchedule = null;
if ($applicationStatus === 'Approved' && !empty($applicant['program_id'])) {
    $syStmt = $conn->prepare("SELECT year_id FROM school_year ORDER BY year_id DESC LIMIT 1");
    $syStmt->execute();
    $syRow = $syStmt->get_result()->fetch_assoc();
    $syStmt->close();
    if ($syRow) {
        $examStmt = $conn->prepare("
            SELECT e.exam_start, e.exam_end, p.program_name
            FROM exam e JOIN programs p ON e.program_id = p.program_id
            WHERE e.program_id = ? AND e.year_id = ? LIMIT 1
        ");
        $examStmt->bind_param("ii", $applicant['program_id'], $syRow['year_id']);
        $examStmt->execute();
        $examSchedule = $examStmt->get_result()->fetch_assoc();
        $examStmt->close();
    }
}

// ── Resolve photo ──
$photoPath = null;
if (!empty($applicant['photo'])) {
    $raw = trim($applicant['photo']);
    if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
        $photoPath = $raw;
    } else {
        $photoPath = '../uploads/photos/' . basename($raw);
    }
}

// ── Fetch program name ──
$programName = null;
if (!empty($applicant['program_id'])) {
    $prog = $programController->getById($applicant['program_id']);
    $programName = $prog['program_name'] ?? null;
}

$successMsg = $_SESSION['success'] ?? null;
if ($successMsg) unset($_SESSION['success']);

$statusConfig = [
    'Pending'      => ['icon' => 'hourglass-split',  'class' => 'pending',  'color' => '#B8963E', 'label' => 'Pending'],
    'Under Review' => ['icon' => 'search',            'class' => 'review',   'color' => '#3b6fd4', 'label' => 'Under Review'],
    'Approved'     => ['icon' => 'check-circle-fill', 'class' => 'approved', 'color' => '#22a861', 'label' => 'Approved'],
    'Rejected'     => ['icon' => 'x-circle-fill',     'class' => 'rejected', 'color' => '#d43b3b', 'label' => 'Rejected'],
];
$sc = $statusConfig[$applicationStatus] ?? $statusConfig['Pending'];

include __DIR__ . '/../layout/topbar2.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>NCC Admission Portal</title>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">

<style>
/* ════════════════════════════════════════════
   VARIABLES & RESET
════════════════════════════════════════════ */
:root {
    --gold:        #B8963E;
    --gold-light:  #D4AF68;
    --gold-pale:   #F5EDD8;
    --deep:        #1A1A2E;
    --navy:        #16213E;
    --maroon:      #7B1C1C;
    --maroon-mid:  #9B2929;
    --cream:       #FAF7F2;
    --border:      #E8E2D9;
    --muted:       #888;
    --success:     #22a861;
    --danger:      #d43b3b;
    --info:        #3b6fd4;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--cream);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ════════════════════════════════════════════
   STEP WIZARD BAR
════════════════════════════════════════════ */
.wizard-bar {
    background: #fff;
    border-bottom: 1px solid var(--border);
    padding: 1rem 2rem;
    position: sticky;
    top: 64px;
    z-index: 100;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.wizard-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    max-width: 640px;
    margin: 0 auto;
}

.wizard-step {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex: 1;
    position: relative;
}

.wizard-step:not(:last-child)::after {
    content: '';
    flex: 1;
    height: 2px;
    background: var(--border);
    margin: 0 0.75rem;
    transition: background 0.4s;
}

.wizard-step.completed:not(:last-child)::after { background: var(--gold); }
.wizard-step.active:not(:last-child)::after { background: var(--border); }

.step-circle {
    width: 34px; height: 34px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; font-weight: 700;
    border: 2px solid var(--border);
    background: #fff;
    color: var(--muted);
    transition: all 0.3s;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

.wizard-step.active .step-circle {
    border-color: var(--gold);
    background: var(--gold);
    color: #fff;
    box-shadow: 0 0 0 4px rgba(184,150,62,0.2);
}

.wizard-step.completed .step-circle {
    border-color: var(--gold);
    background: var(--gold-pale);
    color: var(--gold);
}

.step-label {
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    color: var(--muted);
    white-space: nowrap;
}

.wizard-step.active .step-label { color: var(--deep); }
.wizard-step.completed .step-label { color: var(--gold); }

/* ════════════════════════════════════════════
   PAGE CONTENT
════════════════════════════════════════════ */
.page-content { flex: 1; padding: 2.5rem 1rem 4rem; }
.inner-wrap { max-width: 860px; margin: 0 auto; }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

.anim { animation: fadeUp 0.45s cubic-bezier(0.22,1,0.36,1) both; }
.anim-d1 { animation-delay: 0.05s; }
.anim-d2 { animation-delay: 0.10s; }
.anim-d3 { animation-delay: 0.15s; }
.anim-d4 { animation-delay: 0.20s; }

/* ════════════════════════════════════════════
   FORM CARD (Step 1)
════════════════════════════════════════════ */
.form-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.1);
    overflow: hidden;
    border: 1px solid var(--border);
}

.form-header-band {
    background: linear-gradient(135deg, var(--deep) 0%, var(--navy) 100%);
    padding: 1.75rem 2rem 1.5rem;
    display: flex; align-items: center; gap: 1.25rem;
    position: relative;
    border-bottom: 2px solid rgba(184,150,62,0.35);
}

.form-header-band::after {
    content:''; position:absolute; bottom:0; left:0;
    width:100%; height:2px;
    background: linear-gradient(to right, transparent, var(--gold), transparent);
    opacity:0.6;
}

.header-logo { width:60px; height:60px; border-radius:50%; border:2px solid var(--gold); object-fit:cover; flex-shrink:0; }

.header-text h4 { font-family:'Playfair Display',serif; font-size:1.1rem; font-weight:700; color:#fff; margin-bottom:0.15rem; }
.header-text small { font-size:0.7rem; color:rgba(255,255,255,0.45); }

.header-badge {
    display:inline-flex; align-items:center; gap:0.4rem;
    background:rgba(184,150,62,0.12); border:1px solid rgba(184,150,62,0.35);
    border-radius:4px; padding:0.25rem 0.75rem;
    font-size:0.63rem; font-weight:700; letter-spacing:0.18em;
    text-transform:uppercase; color:var(--gold-light); margin-top:0.45rem;
}

.photo-zone { flex-shrink:0; display:flex; flex-direction:column; align-items:center; gap:0.4rem; }

.photo-frame {
    width:84px; height:84px; border-radius:10px;
    border:2px dashed rgba(184,150,62,0.5);
    background:rgba(255,255,255,0.06); overflow:hidden;
    display:flex; align-items:center; justify-content:center;
}

.photo-frame img { width:100%; height:100%; object-fit:cover; }
.photo-placeholder { color:rgba(255,255,255,0.2); font-size:1.8rem; }

.btn-upload-photo {
    background:rgba(184,150,62,0.15); border:1px solid rgba(184,150,62,0.35);
    color:var(--gold-light); font-size:0.65rem; font-weight:600;
    letter-spacing:0.08em; text-transform:uppercase;
    padding:0.25rem 0.65rem; border-radius:4px; cursor:pointer;
}

.form-body { padding:1.75rem 2rem 2rem; }

.section-label {
    font-size:0.63rem; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;
    color:var(--gold); margin-bottom:0.9rem; padding-bottom:0.4rem;
    border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:0.5rem;
}

.field-label { font-size:0.66rem; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:var(--muted); margin-bottom:0.35rem; display:block; }

.form-modern {
    font-family:'DM Sans',sans-serif; font-size:0.88rem; color:var(--deep);
    border:1.5px solid var(--border); border-radius:7px; padding:0.65rem 0.9rem;
    width:100%; background:#fff; transition:border-color 0.2s, box-shadow 0.2s;
    outline:none; appearance:none;
}

.form-modern::placeholder { color:#C5BEB5; }
.form-modern:focus { border-color:var(--gold); box-shadow:0 0 0 3px rgba(184,150,62,0.12); }
.form-modern:disabled { background:#FAFAF8; color:#666; border-color:#EDE9E3; cursor:default; }

select.form-modern {
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%23B8963E' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 0.85rem center; padding-right:2.25rem;
}

.radio-group { display:flex; gap:1.5rem; padding:0.5rem 0; }
.radio-opt { display:flex; align-items:center; gap:0.4rem; font-size:0.88rem; color:var(--deep); cursor:pointer; }
.radio-opt input[type="radio"] { accent-color:var(--gold); width:15px; height:15px; }

/* ════════════════════════════════════════════
   DOCUMENT UPLOAD ROWS
════════════════════════════════════════════ */
.doc-upload-row {
    background: #FAFAF8;
    border: 1.5px solid var(--border);
    border-radius: 9px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: border-color 0.2s;
}

.doc-upload-row:has(input:focus) { border-color: var(--gold); }

.doc-status-dot {
    width: 30px; height: 30px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; flex-shrink: 0;
}

.doc-status-dot.done    { background: #EDFBF3; color: var(--success); }
.doc-status-dot.pending { background: #FFF8EC; color: var(--gold); }

.doc-upload-label { flex: 1; font-size: 0.85rem; font-weight: 600; color: var(--deep); }
.doc-upload-label small { font-size: 0.72rem; color: var(--muted); font-weight: 400; display: block; margin-top: 0.1rem; }

.doc-file-input {
    font-size: 0.78rem;
    border: 1.5px solid var(--border);
    border-radius: 6px;
    padding: 0.3rem 0.5rem;
    background: #fff;
    width: 100%;
    max-width: 220px;
    cursor: pointer;
}

.doc-file-input:focus { outline: none; border-color: var(--gold); }

/* ════════════════════════════════════════════
   BUTTONS
════════════════════════════════════════════ */
.btn-primary-ncc {
    background: linear-gradient(135deg, var(--maroon-mid), var(--maroon));
    color: #fff; border: none; border-radius: 8px;
    padding: 0.75rem 2rem; font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem; font-weight: 600; letter-spacing: 0.05em;
    cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;
    transition: transform 0.15s, box-shadow 0.15s;
}

.btn-primary-ncc:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(123,28,28,0.3); }
.btn-primary-ncc:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

.btn-gold-outline {
    background: transparent; color: var(--gold);
    border: 1.5px solid rgba(184,150,62,0.4);
    border-radius: 8px; padding: 0.65rem 1.5rem;
    font-family: 'DM Sans', sans-serif; font-size: 0.85rem; font-weight: 600;
    cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;
    text-decoration: none; transition: background 0.2s;
}

.btn-gold-outline:hover { background: var(--gold-pale); color: var(--maroon-mid); }

.btn-success-ncc {
    background: linear-gradient(135deg, #1a6b40, #22a861);
    color: #fff; border: none; border-radius: 8px;
    padding: 0.75rem 2rem; font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem; font-weight: 600; letter-spacing: 0.05em;
    cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;
    transition: transform 0.15s, box-shadow 0.15s;
}

.btn-success-ncc:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(34,168,97,0.3); }

/* ════════════════════════════════════════════
   REVIEW STEP (Step 2)
════════════════════════════════════════════ */
.review-hero {
    background: linear-gradient(135deg, var(--deep) 0%, var(--navy) 100%);
    border-radius: 14px;
    padding: 1.75rem 2rem;
    display: flex; align-items: center; gap: 1.5rem;
    margin-bottom: 1.5rem;
    position: relative; overflow: hidden;
    box-shadow: 0 12px 40px rgba(0,0,0,0.18);
}

.review-hero::after {
    content: '';
    position: absolute; bottom: 0; left: 0;
    width: 100%; height: 2px;
    background: linear-gradient(to right, var(--gold), transparent);
    opacity: 0.5;
}

.review-photo {
    width: 72px; height: 72px; border-radius: 50%;
    border: 2.5px solid var(--gold);
    object-fit: cover; flex-shrink: 0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.35), 0 0 0 4px rgba(184,150,62,0.15);
}

.review-photo-placeholder {
    width: 72px; height: 72px; border-radius: 50%;
    border: 2.5px solid rgba(184,150,62,0.35);
    background: rgba(255,255,255,0.06);
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,0.25); font-size: 1.8rem; flex-shrink: 0;
}

.hero-name { font-family: 'Playfair Display', serif; font-size: 1.4rem; font-weight: 700; color: #fff; margin-bottom: 0.3rem; }
.hero-id-badge {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: rgba(184,150,62,0.12); border: 1px solid rgba(184,150,62,0.3);
    border-radius: 4px; padding: 0.2rem 0.65rem;
    font-size: 0.68rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--gold-light);
}

.review-section {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 1.25rem;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,0.05);
}

.review-section-header {
    background: linear-gradient(90deg, var(--deep), var(--navy));
    padding: 0.85rem 1.5rem;
    display: flex; align-items: center; gap: 0.6rem;
    border-bottom: 2px solid rgba(184,150,62,0.2);
}

.review-section-header h6 { font-family: 'Playfair Display', serif; font-size: 0.88rem; font-weight: 700; color: #fff; margin: 0; }
.review-section-header i  { color: var(--gold-light); }

.review-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }

.review-field {
    padding: 0.85rem 1.5rem;
    border-bottom: 1px solid var(--border);
    border-right: 1px solid var(--border);
}

.review-field:nth-child(even) { border-right: none; }
.review-field:nth-last-child(-n+2) { border-bottom: none; }
.review-field.full { grid-column: 1 / -1; border-right: none; }

.review-field-label { font-size: 0.63rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); margin-bottom: 0.25rem; }
.review-field-value { font-size: 0.9rem; font-weight: 500; color: var(--deep); }

.doc-preview-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; padding: 1.25rem 1.5rem; }

.doc-preview-card {
    background: #FAFAF8;
    border: 1px solid var(--border);
    border-radius: 9px; overflow: hidden;
}

.doc-preview-card .doc-img-wrap {
    height: 120px; background: #EEF4FF;
    display: flex; align-items: center; justify-content: center;
    border-bottom: 1px solid var(--border); overflow: hidden;
}

.doc-preview-card .doc-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.doc-preview-card .doc-img-wrap i   { font-size: 2rem; color: var(--info); }

.doc-preview-card .doc-foot { padding: 0.6rem 0.85rem; }
.doc-preview-card .doc-foot span { font-size: 0.72rem; font-weight: 600; color: var(--deep); text-transform: capitalize; display: block; }

.doc-missing {
    background: #FFFBF2;
    border: 1.5px dashed rgba(184,150,62,0.4);
    border-radius: 9px;
    height: 100%;
    min-height: 80px;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.35rem;
    color: var(--gold); font-size: 0.72rem; font-weight: 600; text-align: center; padding: 0.75rem;
}

.review-warn-banner {
    background: #FFF8EC;
    border: 1px solid rgba(184,150,62,0.35);
    border-left: 4px solid var(--gold);
    border-radius: 8px;
    padding: 0.85rem 1.25rem;
    display: flex; align-items: flex-start; gap: 0.75rem;
    margin-bottom: 1.25rem;
    font-size: 0.82rem; color: #6b4f1c;
}

.review-warn-banner i { color: var(--gold); flex-shrink: 0; margin-top: 1px; }

/* ════════════════════════════════════════════
   STATUS STEP (Step 3)
════════════════════════════════════════════ */
.applicant-hero {
    background: linear-gradient(135deg, var(--deep) 0%, var(--navy) 100%);
    border-radius: 14px;
    padding: 1.75rem 2rem;
    display: flex; align-items: center; gap: 1.5rem;
    margin-bottom: 1.5rem;
    position: relative; overflow: hidden;
    box-shadow: 0 12px 40px rgba(0,0,0,0.18);
}

.applicant-hero::after {
    content:''; position:absolute; bottom:0; left:0;
    width:100%; height:2px;
    background: linear-gradient(to right, var(--gold), transparent);
    opacity:0.5;
}

.hero-photo { width:72px; height:72px; border-radius:50%; border:2.5px solid var(--gold); object-fit:cover; flex-shrink:0; box-shadow:0 4px 18px rgba(0,0,0,0.35),0 0 0 4px rgba(184,150,62,0.15); }
.hero-photo-placeholder { width:72px; height:72px; border-radius:50%; border:2.5px solid rgba(184,150,62,0.35); background:rgba(255,255,255,0.06); display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,0.25); font-size:1.8rem; flex-shrink:0; }

.status-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.07);
    padding: 1.5rem 1.75rem;
    margin-bottom: 1.25rem;
    display: flex; align-items: center; gap: 1.75rem;
    border: 1px solid var(--border);
}

.status-icon-wrap { width:60px; height:60px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.6rem; flex-shrink:0; }
.status-icon-wrap.pending  { background:#FFF8EC; color:var(--gold); }
.status-icon-wrap.review   { background:#EEF4FF; color:var(--info); }
.status-icon-wrap.approved { background:#EDFBF3; color:var(--success); }
.status-icon-wrap.rejected { background:#FDEAEA; color:var(--danger); }

.status-label { font-size:0.63rem; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; color:var(--muted); margin-bottom:0.2rem; }
.status-value { font-family:'Playfair Display',serif; font-size:1.3rem; font-weight:700; color:var(--deep); margin-bottom:0.55rem; }

.status-pill { display:inline-flex; align-items:center; gap:0.35rem; border-radius:20px; padding:0.2rem 0.75rem; font-size:0.7rem; font-weight:700; letter-spacing:0.06em; margin-left:0.4rem; vertical-align:middle; }
.pill-pending  { background:#FFF8EC; color:#B8963E; border:1px solid #f0d894; }
.pill-review   { background:#EEF4FF; color:#3b6fd4; border:1px solid #bcd1f5; }
.pill-approved { background:#EDFBF3; color:#22a861; border:1px solid #b2eecf; }
.pill-rejected { background:#FDEAEA; color:#d43b3b; border:1px solid #f5bcbc; }

.prog-bar-track { height:7px; background:#EDE9E3; border-radius:99px; overflow:hidden; margin-bottom:0.3rem; }
.prog-bar-fill  { height:100%; border-radius:99px; background:linear-gradient(to right,var(--gold),var(--gold-light)); transition:width 0.6s ease; }
.prog-bar-fill.review   { background:linear-gradient(to right,#3b6fd4,#6b9ff5); }
.prog-bar-fill.approved { background:linear-gradient(to right,#22a861,#38d68a); }
.prog-bar-fill.rejected { background:linear-gradient(to right,#d43b3b,#e86060); }
.prog-bar-label { font-size:0.73rem; color:var(--muted); }

.program-pill { display:flex; align-items:center; gap:0.5rem; background:var(--gold-pale); border:1px solid rgba(184,150,62,0.25); border-radius:6px; padding:0.55rem 1.1rem; font-size:0.8rem; font-weight:600; color:#6b4f1c; flex-shrink:0; }
.program-pill i { color:var(--gold); }

.notice-bar {
    border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.25rem;
    display: flex; align-items: flex-start; gap: 0.75rem;
}

.notice-bar.approved { background:#f0fdf6; border:1px solid #b2eecf; border-left:4px solid var(--success); }
.notice-bar.rejected { background:#fff5f5; border:1px solid #f5bcbc; border-left:4px solid var(--danger); }
.notice-bar.review   { background:#EEF4FF; border:1px solid #bcd1f5; border-left:4px solid var(--info); }

.notice-bar i { font-size:1.1rem; margin-top:1px; flex-shrink:0; }
.notice-bar.approved i { color:var(--success); }
.notice-bar.rejected i { color:var(--danger); }
.notice-bar.review   i { color:var(--info); }

.notice-bar strong { font-size:0.85rem; }
.notice-bar.approved strong { color:#1a6b40; }
.notice-bar.rejected strong { color:#7a1c1c; }
.notice-bar.review   strong { color:#1a2f5a; }

.notice-bar p { font-size:0.82rem; margin:0.2rem 0 0; line-height:1.6; }
.notice-bar.approved p { color:#2d7a50; }
.notice-bar.rejected p { color:#a44; }
.notice-bar.review   p { color:#2a4a8a; }

.rejection-reason-box {
    background: #fff;
    border: 1px solid #f5bcbc;
    border-radius: 7px;
    padding: 0.75rem 1rem;
    margin-top: 0.75rem;
    font-size: 0.82rem;
    color: #7a1c1c;
}

.rejection-reason-box strong { display: block; font-size: 0.68rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--danger); margin-bottom: 0.25rem; }

.timeline {
    display: flex;
    flex-direction: column;
    gap: 0;
    padding: 1.25rem 1.5rem;
}

.timeline-item {
    display: flex; gap: 1rem; position: relative;
    padding-bottom: 1.25rem;
}

.timeline-item:last-child { padding-bottom: 0; }

.timeline-item::before {
    content: '';
    position: absolute;
    left: 14px; top: 28px;
    width: 2px; height: calc(100% - 14px);
    background: var(--border);
}

.timeline-item:last-child::before { display: none; }
.timeline-item.done::before { background: var(--gold); }

.tl-dot {
    width: 30px; height: 30px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; flex-shrink: 0;
    border: 2px solid var(--border); background: #fff; color: var(--muted);
    transition: all 0.3s;
}

.timeline-item.done  .tl-dot { border-color: var(--gold); background: var(--gold-pale); color: var(--gold); }
.timeline-item.active .tl-dot { border-color: var(--info); background: var(--info); color: #fff; animation: pulse 2s infinite; }

@keyframes pulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(59,111,212,0.4); }
    50%      { box-shadow: 0 0 0 6px rgba(59,111,212,0); }
}

.tl-content { flex: 1; padding-top: 0.2rem; }
.tl-title { font-size: 0.85rem; font-weight: 600; color: var(--deep); margin-bottom: 0.15rem; }
.tl-desc  { font-size: 0.75rem; color: var(--muted); }

/* ════════════════════════════════════════════
   MODALS
════════════════════════════════════════════ */
.modal-content { border-radius: 14px !important; overflow: hidden; border: none !important; }

.modal-hdr { padding: 1.2rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
.modal-hdr h5 { font-family: 'Playfair Display', serif; font-size: 0.95rem; font-weight: 700; color: #fff; margin: 0; display: flex; align-items: center; gap: 0.5rem; }
.modal-hdr .btn-close { filter: invert(1) brightness(0.8); }
.modal-hdr.green-hdr { background: linear-gradient(135deg, #1a4731, #22a861); }
.modal-hdr.red-hdr   { background: linear-gradient(135deg, #7B1C1C, #d43b3b); }

/* ════════════════════════════════════════════
   ALERTS & TOASTS
════════════════════════════════════════════ */
.alert-form-error {
    background:#FFF5F5; border:1px solid rgba(155,41,41,0.25);
    border-left:3px solid var(--maroon-mid); border-radius:7px;
    padding:0.8rem 1rem; font-size:0.85rem; color:var(--maroon-mid);
    margin-bottom:1.5rem; display:flex; align-items:center; gap:0.6rem;
}

/* ════════════════════════════════════════════
   MISC / UTILS
════════════════════════════════════════════ */
.form-section { margin-bottom: 2rem; }

.locked-overlay {
    text-align: center; padding: 2.5rem 1.5rem;
    color: var(--muted);
}

.locked-overlay i { font-size: 2.5rem; color: var(--gold); opacity: 0.5; display: block; margin-bottom: 0.75rem; }
.locked-overlay p { font-size: 0.88rem; }

@media (max-width: 768px) {
    /* ── Wizard bar ── */
    .wizard-bar { padding: 0.75rem 1rem; top: 56px; }
    .wizard-step .step-label { display: none; }
    .step-circle { width: 30px; height: 30px; font-size: 0.75rem; }

    /* ── Page content ── */
    .page-content { padding: 1.25rem 0.75rem 3rem; }

    /* ── Form card header ── */
    .form-header-band {
        flex-wrap: wrap;
        gap: 0.75rem;
        padding: 1.25rem 1rem 1rem;
    }
    .header-text { flex: 1 1 calc(100% - 70px); min-width: 0; }
    .photo-zone {
        width: 100%;
        flex-direction: row;
        align-items: center;
        gap: 1rem;
        border-top: 1px solid rgba(255,255,255,0.1);
        padding-top: 0.75rem;
    }
    .form-body { padding: 1.1rem; }

    /* ── All action button rows ── */
    .text-end.d-flex,
    .d-flex.gap-2.justify-content-between,
    .d-flex.gap-2.justify-content-end {
        flex-direction: column !important;
        gap: 0.6rem !important;
    }

    /* Buttons full-width on mobile */
    .btn-primary-ncc,
    .btn-gold-outline,
    .btn-success-ncc {
        width: 100%;
        justify-content: center;
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
    }

    /* ── Review & status hero ── */
    .review-hero,
    .applicant-hero {
        flex-direction: column;
        text-align: center;
        gap: 0.85rem;
        padding: 1.25rem 1rem;
    }
    .review-hero .ms-auto { margin: 0 auto !important; }
    .applicant-hero .d-none.d-md-flex { display: flex !important; justify-content: center; }

    /* ── Review info grid ── */
    .review-grid { grid-template-columns: 1fr; }
    .review-field { border-right: none; }
    .review-field:nth-last-child(-n+2) { border-bottom: 1px solid var(--border); }
    .review-field:last-child { border-bottom: none; }

    /* ── Document upload rows ── */
    .doc-upload-row {
        flex-wrap: wrap;
        gap: 0.6rem;
        padding: 0.85rem;
    }
    .doc-file-input {
        max-width: 100%;
        width: 100%;
    }

    /* ── Doc preview grid ── */
    .doc-preview-grid { grid-template-columns: 1fr 1fr; padding: 0.85rem; gap: 0.65rem; }

    /* ── Status card ── */
    .status-card { flex-direction: column; gap: 1rem; padding: 1.1rem; }

    /* ── Review section footer ── */
    .review-section .d-flex.gap-2.justify-content-between {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.75rem !important;
        padding: 1rem !important;
    }
    .review-section .d-flex.gap-2.justify-content-between span {
        text-align: center;
        font-size: 0.74rem;
    }
    .review-section .d-flex.gap-2.justify-content-between .d-flex {
        flex-direction: column !important;
        gap: 0.6rem !important;
    }

    /* ── Hero badges wrap nicely ── */
    .hero-id-badge { font-size: 0.62rem; }
    .d-flex.flex-wrap.gap-2.mt-2 { justify-content: center; }

    /* ── Notice bars ── */
    .notice-bar { padding: 0.85rem 1rem; }

    /* ── Timeline ── */
    .timeline { padding: 1rem; }

    /* ── Program pill ── */
    .program-pill { font-size: 0.75rem; padding: 0.45rem 0.85rem; }

    /* ── Review warn banner ── */
    .review-warn-banner { padding: 0.75rem 1rem; font-size: 0.8rem; }
}

@media (max-width: 400px) {
    .doc-preview-grid { grid-template-columns: 1fr; }
    .hero-name { font-size: 1.1rem; }
    .header-text h4 { font-size: 0.95rem; }
}
</style>
</head>
<body class="mt-5">

<!-- ══ SUCCESS TOAST ══ -->
<?php if (!empty($successMsg)): ?>
<div class="position-fixed top-0 end-0 p-3" style="z-index:9999;">
    <div id="successToast" class="toast align-items-center text-white border-0"
         style="background:linear-gradient(135deg,#22a861,#38d68a);" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($successMsg); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ══ DUPLICATE CONTACT MODAL ══ -->
<?php if (!empty($_SESSION['duplicate_contact'])): unset($_SESSION['duplicate_contact']); ?>
<div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background:rgba(0,0,0,0.55);z-index:9990;" id="dupModal">
    <div style="background:#fff;border-radius:14px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.35);padding:2rem;text-align:center;">
        <div style="width:52px;height:52px;border-radius:50%;background:#FFF5F5;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.4rem;color:var(--maroon-mid);">
            <i class="bi bi-telephone-x-fill"></i>
        </div>
        <h5 style="font-family:'Playfair Display',serif;font-weight:700;color:var(--deep);margin-bottom:0.5rem;">Contact Number Already Exists</h5>
        <p style="font-size:0.85rem;color:#666;margin-bottom:1.5rem;">An applicant with this contact number is already registered. Please use a different one.</p>
        <button class="btn-primary-ncc" onclick="document.getElementById('dupModal').remove()">
            <i class="bi bi-arrow-left"></i> Go Back &amp; Edit
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ══ WIZARD PROGRESS BAR ══ -->
<div class="wizard-bar">
    <div class="wizard-steps">
        <div class="wizard-step <?php echo $step === 'form' ? 'active' : 'completed'; ?>">
            <div class="step-circle">
                <?php echo ($step !== 'form') ? '<i class="bi bi-check-lg"></i>' : '1'; ?>
            </div>
            <span class="step-label">Fill Application</span>
        </div>
        <div class="wizard-step <?php echo $step === 'review' ? 'active' : ($step === 'status' ? 'completed' : ''); ?>">
            <div class="step-circle">
                <?php echo ($step === 'status') ? '<i class="bi bi-check-lg"></i>' : '2'; ?>
            </div>
            <span class="step-label">Review &amp; Upload</span>
        </div>
        <div class="wizard-step <?php echo $step === 'status' ? 'active' : ''; ?>">
            <div class="step-circle">3</div>
            <span class="step-label">Track Status</span>
        </div>
    </div>
</div>

<div class="page-content">
<div class="inner-wrap">

<?php /* ══════════════════════════════════════════════════════
   STEP 1 — ADMISSION FORM
══════════════════════════════════════════════════════ */ ?>
<?php if ($step === 'form'): ?>

<div class="form-card anim">
    <div class="form-header-band">
        <img src="../img/logo.png" class="header-logo" alt="NCC Logo">
        <div class="header-text" style="flex:1;">
            <h4>Northeastern Cebu Colleges, Inc.</h4>
            <small>P. G. Almendras St., Danao City, Cebu</small>
            <div>
                <span class="header-badge"><i class="bi bi-file-earmark-text"></i> Admission Form — SY 2026–2027</span>
            </div>
        </div>
        <div class="photo-zone">
            <div class="photo-frame" id="photoFrame">
                <img id="previewImage"
                     src="<?php echo htmlspecialchars($applicant['photo'] ?? ''); ?>"
                     style="<?php echo empty($applicant['photo']) ? 'display:none;' : ''; ?>"
                     alt="Profile Photo">
                <?php if (empty($applicant['photo'])): ?>
                    <i class="bi bi-person photo-placeholder"></i>
                <?php endif; ?>
            </div>
            <label for="fileInput" class="btn-upload-photo">
                <i class="bi bi-camera me-1"></i>Upload Photo
            </label>
        </div>
    </div>

    <div class="form-body">
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert-form-error">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="file" id="fileInput" class="d-none" accept="image/*" name="photo" required>

            <!-- Personal Info -->
            <div class="form-section">
                <div class="section-label"><i class="bi bi-person-lines-fill"></i> Personal Information</div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="field-label">Full Name *</label>
                        <input type="text" class="form-modern" name="Applicant_name" placeholder="Full Legal Name" required
                               value="<?php echo htmlspecialchars($applicant['Applicant_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="field-label">Father's Name *</label>
                        <input type="text" class="form-modern" name="Father_name" placeholder="Father's Full Name" required
                               value="<?php echo htmlspecialchars($applicant['Father_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="field-label">Mother's Name *</label>
                        <input type="text" class="form-modern" name="Mother_name" placeholder="Mother's Full Name" required
                               value="<?php echo htmlspecialchars($applicant['Mother_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="field-label">Birthdate *</label>
                        <input type="date" class="form-modern" name="birthdate" required
                               value="<?php echo htmlspecialchars($applicant['birthdate'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="field-label">Gender *</label>
                        <div class="radio-group">
                            <label class="radio-opt">
                                <input type="radio" name="gender" value="male"
                                    <?php if (($applicant['Gender'] ?? '') === 'male') echo 'checked'; ?> required> Male
                            </label>
                            <label class="radio-opt">
                                <input type="radio" name="gender" value="female"
                                    <?php if (($applicant['Gender'] ?? '') === 'female') echo 'checked'; ?>required> Female
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact & Background -->
            <div class="form-section">
                <div class="section-label"><i class="bi bi-geo-alt-fill"></i> Contact &amp; Background</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="field-label">Home Address *</label>
                        <input type="text" class="form-modern" name="address" placeholder="Complete Address" required
                               value="<?php echo htmlspecialchars($applicant['address'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="field-label">Contact Number *</label>
                        <input type="text" class="form-modern" name="contact_number" placeholder="09XXXXXXXXX" required maxlength="11"
                               value="<?php echo htmlspecialchars($applicant['contact_number'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="field-label">Religion *</label>
                        <input type="text" class="form-modern" name="religion" placeholder="Religion" required
                               value="<?php echo htmlspecialchars($applicant['Religion'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="field-label">Nationality *</label>
                        <input type="text" class="form-modern" name="nationality" placeholder="Nationality" required
                               value="<?php echo htmlspecialchars($applicant['Nationality'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label class="field-label">Email Address</label>
                        <input type="email" class="form-modern" name="email" placeholder="you@example.com"
                               value="<?php echo htmlspecialchars($userEmail); ?>">
                    </div>
                </div>
            </div>

            <!-- Academic Info -->
            <div class="form-section">
                <div class="section-label"><i class="bi bi-mortarboard-fill"></i> Academic Information</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="field-label">Program *</label>
                        <select class="form-modern" name="program_id" required>
                            <option value="" disabled <?php if (!$applicant || !isset($applicant['program_id'])) echo 'selected'; ?>>Select Program</option>
                            <?php foreach ($programs as $row): ?>
                                <option value="<?php echo htmlspecialchars($row['program_id']); ?>"
                                    <?php if ($applicant && isset($applicant['program_id']) && $applicant['program_id'] == $row['program_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($row['program_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="field-label">Student Type *</label>
                        <select class="form-modern" name="student_type" required>
                            <option value="" disabled <?php if (!$applicant || !isset($applicant['student_type'])) echo 'selected'; ?>>Select Type</option>
                            <option value="1st Year" <?php if (($applicant['student_type'] ?? '') === '1st Year') echo 'selected'; ?>>1st Year</option>
                            <option value="Transferee" <?php if (($applicant['student_type'] ?? '') === 'Transferee') echo 'selected'; ?>>Transferee</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="text-end pt-1 d-flex gap-2 justify-content-end">
                <?php if (!$applicant): ?>
                    <button type="submit" name="action" value="add" class="btn-primary-ncc">
                        <i class="bi bi-arrow-right"></i> Save &amp; Continue to Review
                    </button>
                <?php else: ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?step=review" class="btn-gold-outline">
                        <i class="bi bi-x"></i> Cancel
                    </a>
                    <button type="submit" name="action" value="update" class="btn-primary-ncc">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php /* ══════════════════════════════════════════════════════
   STEP 2 — REVIEW & UPLOAD DOCUMENTS
══════════════════════════════════════════════════════ */ ?>
<?php elseif ($step === 'review'): ?>

<!-- Hero -->
<div class="review-hero anim">
    <?php if ($photoPath): ?>
        <img src="<?php echo htmlspecialchars($photoPath); ?>" class="review-photo" alt="Photo"
             onerror="this.outerHTML='<div class=\'review-photo-placeholder\'><i class=\'bi bi-person\'></i></div>'">
    <?php else: ?>
        <div class="review-photo-placeholder"><i class="bi bi-person"></i></div>
    <?php endif; ?>
    <div>
        <div class="hero-name"><?php echo htmlspecialchars($applicant['Applicant_name'] ?? '—'); ?></div>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <span class="hero-id-badge">Applicant ID: <?php echo htmlspecialchars($applicant['applicant_id'] ?? '—'); ?></span>
            <?php if ($programName): ?>
                <span class="hero-id-badge"><i class="bi bi-mortarboard"></i> <?php echo htmlspecialchars($programName); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="ms-auto">
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>?mode=edit" class="btn-gold-outline" style="font-size:0.78rem;padding:0.4rem 1rem;">
            <i class="bi bi-pencil"></i> Edit Form
        </a>
    </div>
</div>

<!-- Warn if docs missing -->
<?php if ($documentsUploaded < $totalRequired): ?>
<div class="review-warn-banner anim anim-d1">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
        <strong>Documents Required:</strong> Please upload all required documents before submitting.
        <strong><?php echo $documentsUploaded; ?>/<?php echo $totalRequired; ?></strong> documents provided.
    </div>
</div>
<?php endif; ?>

<!-- Personal Info Review -->
<div class="review-section anim anim-d1">
    <div class="review-section-header">
        <i class="bi bi-person-lines-fill"></i>
        <h6>Personal &amp; Academic Information</h6>
    </div>
    <div class="review-grid">
        <div class="review-field">
            <div class="review-field-label">Full Name</div>
            <div class="review-field-value"><?php echo htmlspecialchars($applicant['Applicant_name'] ?? '—'); ?></div>
        </div>
        <div class="review-field">
            <div class="review-field-label">Birthdate</div>
            <div class="review-field-value"><?php echo !empty($applicant['birthdate']) ? date('F d, Y', strtotime($applicant['birthdate'])) : '—'; ?></div>
        </div>
        <div class="review-field">
            <div class="review-field-label">Father's Name</div>
            <div class="review-field-value"><?php echo htmlspecialchars($applicant['Father_name'] ?? '—'); ?></div>
        </div>
        <div class="review-field">
            <div class="review-field-label">Mother's Name</div>
            <div class="review-field-value"><?php echo htmlspecialchars($applicant['Mother_name'] ?? '—'); ?></div>
        </div>
        <div class="review-field">
            <div class="review-field-label">Gender</div>
            <div class="review-field-value"><?php echo ucfirst(htmlspecialchars($applicant['Gender'] ?? '—')); ?></div>
        </div>
        <div class="review-field">
            <div class="review-field-label">Contact Number</div>
            <div class="review-field-value"><?php echo htmlspecialchars($applicant['contact_number'] ?? '—'); ?></div>
        </div>
        <div class="review-field">
            <div class="review-field-label">Address</div>
            <div class="review-field-value"><?php echo htmlspecialchars($applicant['address'] ?? '—'); ?></div>
        </div>
        <div class="review-field">
            <div class="review-field-label">Religion</div>
            <div class="review-field-value"><?php echo htmlspecialchars($applicant['Religion'] ?? '—'); ?></div>
        </div>
        <div class="review-field">
            <div class="review-field-label">Nationality</div>
            <div class="review-field-value"><?php echo htmlspecialchars($applicant['Nationality'] ?? '—'); ?></div>
        </div>
        <div class="review-field">
            <div class="review-field-label">Email</div>
            <div class="review-field-value"><?php echo htmlspecialchars($userEmail ?: '—'); ?></div>
        </div>
        <div class="review-field">
            <div class="review-field-label">Program</div>
            <div class="review-field-value"><?php echo htmlspecialchars($programName ?? '—'); ?></div>
        </div>
        <div class="review-field">
            <div class="review-field-label">Student Type</div>
            <div class="review-field-value"><?php echo htmlspecialchars($applicant['student_type'] ?? '—'); ?></div>
        </div>
    </div>
</div>

<!-- Document Upload -->
<div class="review-section anim anim-d2">
    <div class="review-section-header">
        <i class="bi bi-cloud-upload-fill"></i>
        <h6>Upload Required Documents</h6>
    </div>
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="d-flex flex-column gap-3 p-4" style="background:var(--cream);">
            <?php if (!empty($requiredDocs)): ?>
                <?php foreach ($requiredDocs as $docType => $docName): ?>
                    <div class="doc-upload-row">
                        <div class="doc-status-dot <?php echo !empty($uploadedDocs[$docType]) ? 'done' : 'pending'; ?>">
                            <i class="bi bi-<?php echo !empty($uploadedDocs[$docType]) ? 'check-lg' : 'clock'; ?>"></i>
                        </div>
                        <div class="doc-upload-label">
                            <?php echo htmlspecialchars($docName); ?>
                            <?php if (!empty($uploadedDocs[$docType])): ?>
                                <small style="color:var(--success);"><i class="bi bi-check-circle-fill me-1"></i>Already uploaded</small>
                            <?php else: ?>
                                <small>Not yet uploaded — file required</small>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="<?php echo $docType; ?>" class="doc-file-input"
                               accept="image/*,.pdf"
                               <?php echo empty($uploadedDocs[$docType]) ? 'required' : ''; ?>>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center;padding:2rem;color:var(--muted);">
                    <i class="bi bi-exclamation-circle fs-3" style="color:var(--gold);"></i>
                    <p class="mt-2 mb-0">No documents required for selected student type.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Uploaded docs preview -->
        <?php if (!empty($uploadedFiles)): ?>
        <div style="border-top:1px solid var(--border);padding:1rem 1.5rem 0.5rem;">
            <div class="section-label" style="margin-bottom:0.75rem;"><i class="bi bi-images"></i> Previously Uploaded</div>
        </div>
        <div class="doc-preview-grid" style="padding-top:0;">
            <?php foreach ($uploadedFiles as $doc => $path):
                $filePath = '../uploads/photos/' . basename($path);
                $ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $isImage  = in_array($ext, ['jpg','jpeg','png','gif','webp']);
            ?>
                <div class="doc-preview-card">
                    <div class="doc-img-wrap">
                        <?php if ($isImage): ?>
                            <img src="<?php echo htmlspecialchars($filePath); ?>" alt="doc">
                        <?php else: ?>
                            <i class="bi bi-file-earmark-text"></i>
                        <?php endif; ?>
                    </div>
                    <div class="doc-foot">
                        <span><?php echo htmlspecialchars(str_replace('_', ' ', $doc)); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="d-flex gap-2 justify-content-between align-items-center p-4" style="border-top:1px solid var(--border);">
            <span style="font-size:0.78rem;color:var(--muted);">
                <i class="bi bi-info-circle me-1"></i>
                Once submitted, you cannot edit your application.
            </span>
            <div class="d-flex gap-2">
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>?mode=edit" class="btn-gold-outline">
                    <i class="bi bi-pencil"></i> Edit Details
                </a>
                <button type="submit" name="action" value="submit_application" class="btn-success-ncc"
                        onclick="return confirm('Are you sure you want to submit your application? You cannot make changes after submission.');">
                    <i class="bi bi-send-fill"></i> Submit Application
                </button>
            </div>
        </div>
    </form>
</div>

<?php /* ══════════════════════════════════════════════════════
   STEP 3 — APPLICATION STATUS
══════════════════════════════════════════════════════ */ ?>
<?php elseif ($step === 'status'): ?>

<!-- Applicant Hero -->
<div class="applicant-hero anim">
    <?php if ($photoPath): ?>
        <img src="<?php echo htmlspecialchars($photoPath); ?>" class="hero-photo" alt="Photo"
             onerror="this.outerHTML='<div class=\'hero-photo-placeholder\'><i class=\'bi bi-person\'></i></div>'">
    <?php else: ?>
        <div class="hero-photo-placeholder"><i class="bi bi-person"></i></div>
    <?php endif; ?>
    <div style="flex:1;">
        <div class="hero-name"><?php echo htmlspecialchars($applicant['Applicant_name'] ?? '—'); ?></div>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <span class="hero-id-badge">Applicant ID: <?php echo htmlspecialchars($applicant['applicant_id'] ?? '—'); ?></span>
            <?php if ($programName): ?>
                <span class="hero-id-badge"><i class="bi bi-mortarboard"></i> <?php echo htmlspecialchars($programName); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($applicant['student_type'] ?? ''): ?>
        <div class="program-pill d-none d-md-flex">
            <i class="bi bi-person-badge-fill"></i>
            <?php echo htmlspecialchars($applicant['student_type']); ?>
        </div>
    <?php endif; ?>
</div>

<!-- Status notices -->
<?php if ($applicationStatus === 'Under Review'): ?>
<div class="notice-bar review anim anim-d1">
    <i class="bi bi-search"></i>
    <div>
        <strong>Application Under Review</strong>
        <p>Your documents are being reviewed by the admissions office. Please wait for the result. You will be notified once a decision has been made.</p>
    </div>
</div>
<?php elseif ($applicationStatus === 'Approved'): ?>
<div class="notice-bar approved anim anim-d1">
    <i class="bi bi-check-circle-fill"></i>
    <div style="flex:1;">
        <strong>Application Approved — Congratulations!</strong>
        <p>You have been accepted. Please proceed to the school personally for your entrance examination. Bring a valid ID and a copy of this confirmation.</p>
        <?php if (!empty($examSchedule)): ?>
        <div style="margin-top:0.65rem;display:inline-flex;align-items:center;gap:0.5rem;background:#fff;border:1px solid #b2eecf;border-radius:6px;padding:0.35rem 0.85rem;font-size:0.78rem;color:#1a6b40;font-weight:600;">
            <i class="bi bi-calendar-event" style="color:var(--success);"></i>
            Exam: <?php echo date('M d', strtotime($examSchedule['exam_start'])); ?> – <?php echo date('M d, Y', strtotime($examSchedule['exam_end'])); ?>
            &nbsp;(8:30 AM – 4:30 PM)
        </div>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($applicationStatus === 'Rejected'): ?>
<div class="notice-bar rejected anim anim-d1">
    <i class="bi bi-x-circle-fill"></i>
    <div>
        <strong>Application Not Approved</strong>
        <p>Your application was reviewed and unfortunately was not approved for this school year. Please contact the admissions office for guidance or re-apply next school year.</p>
        <?php if (!empty($rejectionReason)): ?>
        <div class="rejection-reason-box">
            <strong><i class="bi bi-clipboard-x me-1"></i>Disqualification Reason</strong>
            <?php echo htmlspecialchars($rejectionReason); ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Status + Progress -->
<div class="status-card anim anim-d2">
    <div class="status-icon-wrap <?php echo $sc['class']; ?>">
        <i class="bi bi-<?php echo $sc['icon']; ?>"></i>
    </div>
    <div style="flex:1;">
        <div class="status-label">Application Status</div>
        <div class="status-value">
            <?php echo htmlspecialchars($applicationStatus); ?>
            <span class="status-pill pill-<?php echo $sc['class']; ?>">
                <i class="bi bi-<?php echo $sc['icon']; ?>"></i>
                <?php echo htmlspecialchars($applicationStatus); ?>
            </span>
        </div>
        <div class="prog-bar-track">
            <div class="prog-bar-fill <?php echo $sc['class']; ?>" style="width:<?php echo $progressPercent; ?>%"></div>
        </div>
        <div class="prog-bar-label">
            <strong><?php echo $documentsUploaded; ?></strong> of <strong><?php echo $totalRequired; ?></strong> documents submitted &mdash; <?php echo round($progressPercent); ?>% complete
        </div>
    </div>
</div>

<!-- Application Timeline -->
<div class="review-section anim anim-d3">
    <div class="review-section-header">
        <i class="bi bi-diagram-3-fill"></i>
        <h6>Application Journey</h6>
    </div>
    <div class="timeline">
        <?php
        $steps = [
            ['title' => 'Form Submitted', 'desc' => 'Your application form and details have been recorded.', 'done' => true],
            ['title' => 'Documents Uploaded', 'desc' => 'Required documents have been submitted (' . $documentsUploaded . '/' . $totalRequired . ').', 'done' => $documentsUploaded >= $totalRequired],
            ['title' => 'Under Review', 'desc' => 'The admissions team is evaluating your application.', 'done' => in_array($applicationStatus, ['Approved', 'Rejected']), 'active' => $applicationStatus === 'Under Review'],
            ['title' => 'Decision Released', 'desc' => $applicationStatus === 'Approved' ? 'Congratulations — you have been accepted!' : ($applicationStatus === 'Rejected' ? 'Application was not approved this school year.' : 'Awaiting final decision.'), 'done' => in_array($applicationStatus, ['Approved', 'Rejected'])],
        ];
        if ($applicationStatus === 'Approved') {
            $steps[] = ['title' => 'Entrance Examination', 'desc' => !empty($examSchedule) ? 'Scheduled: ' . date('M d', strtotime($examSchedule['exam_start'])) . ' – ' . date('M d, Y', strtotime($examSchedule['exam_end'])) : 'Schedule to be announced.', 'done' => false, 'active' => true];
        }
        foreach ($steps as $tl):
            $cls = ($tl['done'] ?? false) ? 'done' : (($tl['active'] ?? false) ? 'active' : '');
        ?>
        <div class="timeline-item <?php echo $cls; ?>">
            <div class="tl-dot">
                <?php if ($tl['done'] ?? false): ?>
                    <i class="bi bi-check-lg"></i>
                <?php elseif ($tl['active'] ?? false): ?>
                    <i class="bi bi-clock-fill"></i>
                <?php else: ?>
                    <i class="bi bi-circle"></i>
                <?php endif; ?>
            </div>
            <div class="tl-content">
                <div class="tl-title"><?php echo $tl['title']; ?></div>
                <div class="tl-desc"><?php echo $tl['desc']; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Submitted Documents (read-only view) -->
<div class="review-section anim anim-d4">
    <div class="review-section-header">
        <i class="bi bi-images"></i>
        <h6>Submitted Documents</h6>
    </div>
    <?php if (!empty($uploadedFiles)): ?>
        <div class="doc-preview-grid">
            <?php foreach ($uploadedFiles as $doc => $path):
                $filePath = '../uploads/photos/' . basename($path);
                $ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $isImage  = in_array($ext, ['jpg','jpeg','png','gif','webp']);
            ?>
                <div class="doc-preview-card">
                    <div class="doc-img-wrap">
                        <?php if ($isImage): ?>
                            <img src="<?php echo htmlspecialchars($filePath); ?>" alt="doc">
                        <?php else: ?>
                            <i class="bi bi-file-earmark-text"></i>
                        <?php endif; ?>
                    </div>
                    <div class="doc-foot">
                        <span><?php echo htmlspecialchars(str_replace('_', ' ', $doc)); ?></span>
                        <?php if (!$isImage): ?>
                            <a href="<?php echo htmlspecialchars($filePath); ?>" target="_blank" style="font-size:0.7rem;color:var(--info);">
                                <i class="bi bi-box-arrow-up-right"></i> Open
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="locked-overlay">
            <i class="bi bi-folder2-open"></i>
            <p>No documents found.</p>
        </div>
    <?php endif; ?>
</div>

<?php endif; /* end step === status */ ?>

</div><!-- /inner-wrap -->
</div><!-- /page-content -->

<!-- ══ APPROVED MODAL ══ -->
<div class="modal fade" id="approvedModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div style="height:5px;background:linear-gradient(to right,#22a861,#38d68a);"></div>
      <div class="modal-body text-center p-5">
        <div style="width:76px;height:76px;border-radius:50%;background:linear-gradient(135deg,#1a6b40,#22a861);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;box-shadow:0 8px 32px rgba(34,168,97,0.3);">
          <i class="bi bi-check-lg" style="font-size:1.9rem;color:#fff;"></i>
        </div>
        <h4 style="font-family:'Playfair Display',serif;font-weight:700;color:var(--deep);margin-bottom:0.35rem;">Congratulations!</h4>
        <p style="font-size:0.72rem;color:var(--muted);letter-spacing:0.12em;text-transform:uppercase;font-weight:700;margin-bottom:1.25rem;">Application Accepted</p>

        <?php if (!empty($examSchedule['program_name'])): ?>
        <div style="display:inline-flex;align-items:center;gap:0.5rem;background:#EDFBF3;border:1px solid #b2eecf;border-radius:6px;padding:0.45rem 1.1rem;font-size:0.8rem;color:#1a6b40;font-weight:700;margin-bottom:1.1rem;">
            <i class="bi bi-mortarboard-fill" style="color:var(--success);"></i>
            <?php echo htmlspecialchars($examSchedule['program_name']); ?>
        </div>
        <?php endif; ?>

        <div style="background:#f0fdf6;border:1px solid #b2eecf;border-radius:10px;padding:1.1rem 1.25rem;margin-bottom:1rem;text-align:left;">
            <p style="font-size:0.85rem;font-weight:700;color:#1a6b40;margin:0 0 0.35rem;"><i class="bi bi-mortarboard-fill me-2"></i>Proceed for Entrance Examination</p>
            <p style="font-size:0.8rem;color:#2d7a50;margin:0 0 0.85rem;line-height:1.65;">Visit <strong>Northeastern Cebu Colleges, Inc.</strong> personally. Bring a valid ID and a copy of this confirmation.</p>
            <?php if (!empty($examSchedule)): ?>
            <div style="background:#fff;border:1px solid #b2eecf;border-radius:8px;padding:0.85rem;display:flex;gap:1rem;flex-wrap:wrap;">
                <div style="flex:1;min-width:90px;">
                    <div style="font-size:0.62rem;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.2rem;">Start</div>
                    <div style="font-size:0.9rem;font-weight:700;color:#1a6b40;"><?php echo date('F d, Y', strtotime($examSchedule['exam_start'])); ?></div>
                </div>
                <div style="color:#b2eecf;align-self:center;">→</div>
                <div style="flex:1;min-width:90px;">
                    <div style="font-size:0.62rem;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.2rem;">End</div>
                    <div style="font-size:0.9rem;font-weight:700;color:#1a6b40;"><?php echo date('F d, Y', strtotime($examSchedule['exam_end'])); ?></div>
                </div>
                <div style="flex-basis:100%;font-size:0.72rem;color:#2d7a50;"><i class="bi bi-clock me-1"></i>8:30 AM to 4:30 PM only</div>
            </div>
            <?php else: ?>
            <div style="font-size:0.8rem;color:#2d7a50;"><i class="bi bi-clock me-1"></i>Exam schedule to be announced. Check back or contact admissions.</div>
            <?php endif; ?>
        </div>
        <div style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--gold-pale);border:1px solid var(--border);border-radius:6px;padding:0.45rem 0.9rem;font-size:0.76rem;color:#6b4f1c;font-weight:600;margin-bottom:1.5rem;">
            <i class="bi bi-geo-alt-fill" style="color:var(--gold);"></i>
            P.G. Almendras St., Danao City, Cebu
        </div>
        <div>
            <button type="button" class="btn-success-ncc" data-bs-dismiss="modal">
                <i class="bi bi-check2-circle"></i> Got it, Thank you!
            </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══ REJECTED MODAL ══ -->
<div class="modal fade" id="rejectedModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div style="height:5px;background:linear-gradient(to right,#d43b3b,#e86060);"></div>
      <div class="modal-body text-center p-5">
        <div style="width:76px;height:76px;border-radius:50%;background:linear-gradient(135deg,#7B1C1C,#d43b3b);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;box-shadow:0 8px 32px rgba(212,59,59,0.3);">
          <i class="bi bi-x-lg" style="font-size:1.9rem;color:#fff;"></i>
        </div>
        <h4 style="font-family:'Playfair Display',serif;font-weight:700;color:var(--deep);margin-bottom:0.35rem;">We're Sorry</h4>
        <p style="font-size:0.72rem;color:var(--muted);letter-spacing:0.12em;text-transform:uppercase;font-weight:700;margin-bottom:1.1rem;">Application Not Approved</p>

        <?php if (!empty($rejectionReason)): ?>
        <div style="background:#fff5f5;border:1px solid #f5bcbc;border-radius:9px;padding:1rem 1.25rem;margin-bottom:1rem;text-align:left;">
            <div style="font-size:0.65rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--danger);margin-bottom:0.4rem;"><i class="bi bi-clipboard-x me-1"></i>Disqualification Reason</div>
            <p style="font-size:0.85rem;color:#7a1c1c;margin:0;line-height:1.6;"><?php echo htmlspecialchars($rejectionReason); ?></p>
        </div>
        <?php endif; ?>

        <div style="background:#fff5f5;border:1px solid #f5bcbc;border-radius:9px;padding:1rem 1.25rem;margin-bottom:1rem;text-align:left;">
            <div style="display:flex;align-items:flex-start;gap:0.75rem;">
                <i class="bi bi-info-circle-fill" style="color:var(--danger);font-size:1rem;flex-shrink:0;margin-top:2px;"></i>
                <div>
                    <p style="font-size:0.85rem;font-weight:700;color:#7a1c1c;margin:0 0 0.3rem;">Your application was not accepted this school year</p>
                    <p style="font-size:0.8rem;color:#a44;margin:0;line-height:1.6;">You may re-apply for the <strong>next school year</strong>. Visit the admissions office for guidance on re-application.</p>
                </div>
            </div>
        </div>

        <div style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--gold-pale);border:1px solid var(--border);border-radius:6px;padding:0.45rem 0.9rem;font-size:0.76rem;color:#6b4f1c;font-weight:600;margin-bottom:1.5rem;">
            <i class="bi bi-facebook" style="color:var(--gold);"></i>
            <a href="https://www.facebook.com/NortheasternCebuCollegesInc" target="_blank" style="color:#6b4f1c;text-decoration:none;">Contact the Admissions Office</a>
        </div>
        <div>
            <button type="button" class="btn-primary-ncc" data-bs-dismiss="modal">
                <i class="bi bi-arrow-left"></i> Close
            </button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . "/../layout/footer.php"; ?>

<script>
// ── Photo preview ──
const fileInput    = document.getElementById('fileInput');
const previewImage = document.getElementById('previewImage');
if (fileInput && previewImage) {
    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => {
                previewImage.src = e.target.result;
                previewImage.style.display = 'block';
                const ph = document.querySelector('.photo-placeholder');
                if (ph) ph.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    });
}

// ── Input sanitizer ──
function sanitizeValue(value, isEmail) {
    if (isEmail) return value;
    let s = value.replace(/[^A-Za-z0-9 \-]/g, '');
    const firstHyphen = s.indexOf('-');
    if (firstHyphen !== -1) {
        s = s.slice(0, firstHyphen + 1) + s.slice(firstHyphen + 1).replace(/-/g, '');
    }
    return s;
}

function attachSanitizer(el) {
    if (!el) return;
    const isEmail = el.type === 'email';
    el.addEventListener('input', function () {
        const clean = sanitizeValue(this.value, isEmail);
        if (clean !== this.value) {
            const pos = this.selectionStart;
            this.value = clean;
            try { this.setSelectionRange(Math.min(pos, clean.length), Math.min(pos, clean.length)); } catch(e) {}
        }
    });
}
document.querySelectorAll('input[type="text"], input[type="tel"], input[type="email"]').forEach(attachSanitizer);

// ── Toast ──
<?php if (!empty($successMsg)): ?>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('successToast');
    if (el) new bootstrap.Toast(el, { delay: 4500 }).show();
});
<?php endif; ?>

// ── Status modals ──
<?php if ($showStatusModal && $applicationStatus === 'Approved'): ?>
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('approvedModal')).show();
});
<?php endif; ?>

<?php if ($showStatusModal && $applicationStatus === 'Rejected'): ?>
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('rejectedModal')).show();
});
<?php endif; ?>
</script>

</body>
</html>