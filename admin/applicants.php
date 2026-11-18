<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_Id']) || ($_SESSION['role'] ?? '') !== 'admin') {
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
$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/profile.php';
require_once __DIR__ . '/../Controller/userController.php';

$db   = new Database($config);
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $applicantId     = isset($_POST['applicant_id']) ? (int) $_POST['applicant_id'] : null;
        $newStatus       = $_POST['new_status'];
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');
        $allowed         = ['Approved', 'Rejected'];
        if ($applicantId !== null && in_array($newStatus, $allowed)) {
            if ($newStatus === 'Rejected' && $rejectionReason !== '') {
                $stmt = $conn->prepare("UPDATE application SET status = ?, rejection_reason = ? WHERE applicant_id = ?");
                $stmt->bind_param("ssi", $newStatus, $rejectionReason, $applicantId);
            } else {
                $stmt = $conn->prepare("UPDATE application SET status = ? WHERE applicant_id = ?");
                $stmt->bind_param("si", $newStatus, $applicantId);
            }
            if (!$stmt->execute()) {
                die("Execute failed: " . $stmt->error);
            }
            $stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

include __DIR__ . '/../layout/admintop.php';

$profileController = new ProfileController(new Profile($conn));
$profile = $profileController->getAll();

$programs = [];
$progResult = $conn->query("SELECT program_id, program_name FROM programs");
if ($progResult) while ($row = $progResult->fetch_assoc())
    $programs[$row['program_id']] = $row['program_name'];

$applicantStatuses = [];
$statusResult = $conn->query("
    SELECT applicant_id, status FROM application
    WHERE (applicant_id, application_id) IN (
        SELECT applicant_id, MAX(application_id) FROM application GROUP BY applicant_id
    )
");
if ($statusResult) while ($row = $statusResult->fetch_assoc())
    $applicantStatuses[$row['applicant_id']] = $row['status'];

$applicantEmails = [];
$emailResult = $conn->query("
    SELECT a.applicant_id, u.email
    FROM applicants a
    LEFT JOIN user u ON u.user_id = a.user_id
");
if ($emailResult) while ($row = $emailResult->fetch_assoc())
    $applicantEmails[$row['applicant_id']] = $row['email'] ?? '';

$reportData = [];
if (!empty($profile)) {
    foreach ($profile as $row) {
        $pid    = (string)$row['program_id'];
        $pname  = $programs[$pid] ?? ('Program ' . $pid);
        $status = $applicantStatuses[$row['applicant_id']] ?? 'Pending';
        if (!isset($reportData[$pid]))
            $reportData[$pid] = ['program_id' => $pid, 'program_name' => $pname, 'applicants' => []];
        $reportData[$pid]['applicants'][] = [
            'id'     => $row['applicant_id'],
            'name'   => $row['Applicant_name'] ?? $row['applicant_name'] ?? 'N/A',
            'status' => $status,
        ];
    }
}

$schoolYears = [];
$syResult = $conn->query("SELECT * FROM school_year ORDER BY year_id DESC");
while ($row = $syResult->fetch_assoc()) $schoolYears[] = $row;
$currentSY = !empty($schoolYears) ? $schoolYears[0] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Applicants — NCC Admission Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --primary:#2563EB; --primary-light:#3B82F6;
    --primary-pale:rgba(37,99,235,0.08); --primary-border:rgba(37,99,235,0.18);
    --surface:#F1F5F9; --surface-2:#F8FAFC;
    --border:#E2E8F0; --border-strong:#CBD5E1;
    --text-primary:#0F172A; --text-secondary:#475569; --text-muted:#94A3B8;
    --gold:#D97706; --gold-light:#F59E0B; --gold-pale:rgba(217,119,6,0.08);
    --green:#059669; --green-pale:rgba(5,150,105,0.08);
    --red:#DC2626; --red-pale:rgba(220,38,38,0.08);
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'DM Sans',sans-serif;background:var(--surface);color:var(--text-primary);overflow-x:hidden;}
  .wrap{padding:6rem 2rem 3rem;max-width:1400px;margin:0 auto;}

  .page-header{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;}
  .page-eyebrow{font-size:0.65rem;font-weight:700;letter-spacing:0.22em;text-transform:uppercase;color:var(--primary);margin-bottom:0.4rem;display:flex;align-items:center;gap:0.5rem;}
  .page-eyebrow::before{content:'';display:inline-block;width:20px;height:2px;background:var(--primary);border-radius:2px;}
  .page-header h1{font-family:'Playfair Display',serif;font-size:1.9rem;font-weight:700;color:var(--text-primary);line-height:1.1;}
  .back-link{font-size:0.78rem;color:var(--primary);font-weight:600;text-decoration:none;display:flex;align-items:center;gap:0.4rem;border:1px solid var(--primary-border);border-radius:7px;padding:0.45rem 1rem;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.05);transition:background 0.2s;}
  .back-link:hover{background:var(--primary-pale);color:var(--primary);}

  .status-tabs{display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.25rem;}
  .stab{display:flex;align-items:center;gap:0.4rem;border:1px solid var(--border);border-radius:8px;padding:0.5rem 1.1rem;font-size:0.78rem;font-weight:600;cursor:pointer;background:#fff;color:var(--text-secondary);transition:all 0.15s;box-shadow:0 1px 3px rgba(0,0,0,0.04);}
  .stab .dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}
  .stab:hover{border-color:var(--border-strong);background:var(--surface-2);}
  .stab.active{background:var(--primary);color:#fff;border-color:var(--primary);}
  .stab.active .dot{background:#fff!important;}
  .stab[data-status="all"]      .dot{background:var(--primary);}
  .stab[data-status="pending"]  .dot{background:var(--gold-light);}
  .stab[data-status="accepted"] .dot{background:var(--green);}
  .stab[data-status="declined"] .dot{background:var(--red);}
  .stab .cnt{font-size:0.7rem;background:rgba(0,0,0,0.07);border-radius:20px;padding:0.1rem 0.5rem;}
  .stab.active .cnt{background:rgba(255,255,255,0.25);}

  .table-card{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.04);animation:fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;}
  .table-card-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);}
  .filter-bar{display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;width:100%;}
  .search-wrap{position:relative;flex:1;min-width:160px;}
  .search-wrap i{position:absolute;left:0.85rem;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.8rem;pointer-events:none;}
  .search-input{background:var(--surface-2);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);font-family:'DM Sans',sans-serif;font-size:0.83rem;padding:0.55rem 1rem 0.55rem 2.3rem;width:100%;outline:none;transition:border-color 0.2s;}
  .search-input::placeholder{color:var(--text-muted);}
  .search-input:focus{border-color:var(--primary);background:#fff;}
  .prog-select{background:var(--surface-2);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);font-family:'DM Sans',sans-serif;font-size:0.83rem;padding:0.55rem 1rem;outline:none;cursor:pointer;transition:border-color 0.2s;flex-shrink:0;}
  .prog-select:focus{border-color:var(--primary);background:#fff;}
  .btn-primary-action{background:var(--primary);border:none;border-radius:8px;color:#fff;font-family:'DM Sans',sans-serif;font-size:0.8rem;font-weight:700;padding:0.55rem 1.1rem;cursor:pointer;display:inline-flex;align-items:center;gap:0.4rem;transition:transform 0.15s,box-shadow 0.15s,background 0.15s;white-space:nowrap;flex-shrink:0;}
  .btn-primary-action:hover{background:#1D4ED8;transform:translateY(-1px);box-shadow:0 4px 14px rgba(37,99,235,0.25);color:#fff;}

  .applicants-table{width:100%;border-collapse:collapse;}
  .applicants-table thead tr{background:var(--surface-2);border-bottom:1px solid var(--border);}
  .applicants-table th{font-size:0.62rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-muted);padding:0.85rem 1.25rem;white-space:nowrap;}
  .applicants-table tbody tr{border-bottom:1px solid var(--border);transition:background 0.15s;}
  .applicants-table tbody tr:last-child{border-bottom:none;}
  .applicants-table tbody tr:hover{background:var(--surface-2);}
  .applicants-table td{padding:0.9rem 1.25rem;vertical-align:middle;font-size:0.85rem;}
  .id-badge{display:inline-flex;align-items:center;background:var(--primary-pale);border:1px solid var(--primary-border);border-radius:5px;padding:0.2rem 0.6rem;font-size:0.72rem;font-weight:700;color:var(--primary);font-family:monospace;}
  .name-text{font-weight:600;font-size:0.88rem;}
  .program-pill{display:inline-flex;align-items:center;gap:0.3rem;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:5px;padding:0.2rem 0.65rem;font-size:0.72rem;font-weight:600;color:#1D4ED8;}
  .contact-cell{color:var(--text-secondary);font-size:0.82rem;display:flex;align-items:center;gap:0.4rem;}
  .contact-cell i{color:var(--text-muted);font-size:0.75rem;}

  .status-badge{display:inline-flex;align-items:center;gap:0.35rem;border-radius:5px;padding:0.28rem 0.75rem;font-size:0.68rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;}
  .status-badge.pending  {background:#FFFBEB;color:#92400E;border:1px solid #FDE68A;}
  .status-badge.accepted {background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0;}
  .status-badge.declined {background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;}
  .status-dot{width:6px;height:6px;border-radius:50%;display:inline-block;flex-shrink:0;}
  .status-badge.pending  .status-dot{background:#F59E0B;}
  .status-badge.accepted .status-dot{background:#10B981;}
  .status-badge.declined .status-dot{background:#EF4444;}

  .action-btn{width:32px;height:32px;border-radius:7px;border:1px solid #BFDBFE;background:#EFF6FF;color:#1D4ED8;display:flex;align-items:center;justify-content:center;font-size:0.78rem;cursor:pointer;transition:transform 0.15s,box-shadow 0.15s;}
  .action-btn:hover{transform:translateY(-1px);background:#DBEAFE;box-shadow:0 3px 10px rgba(37,99,235,0.15);}
  .empty-state{padding:4rem 2rem;text-align:center;}
  .empty-state i{font-size:2.5rem;color:var(--text-muted);display:block;margin-bottom:1rem;}
  .empty-state p{color:var(--text-muted);font-size:0.88rem;}

  .pagination-wrap{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-top:1px solid var(--border);flex-wrap:wrap;gap:0.75rem;}
  .pagination-info{font-size:0.78rem;color:var(--text-muted);}
  .pagination-info strong{color:var(--text-primary);}
  .pagination-controls{display:flex;align-items:center;gap:0.35rem;flex-wrap:wrap;}
  .page-btn{min-width:32px;height:32px;border-radius:7px;border:1px solid var(--border);background:#fff;color:var(--text-secondary);font-family:'DM Sans',sans-serif;font-size:0.78rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0 0.5rem;transition:background 0.15s,border-color 0.15s;}
  .page-btn:hover:not(:disabled){background:var(--surface-2);border-color:var(--border-strong);}
  .page-btn.active{background:var(--primary);border-color:var(--primary);color:#fff;}
  .page-btn:disabled{opacity:0.4;cursor:not-allowed;}

  .light-modal .modal-content{background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden;color:var(--text-primary);box-shadow:0 20px 60px rgba(0,0,0,0.12);}
  .light-modal .modal-header{background:#fff;border-bottom:1px solid var(--border);padding:1.25rem 1.5rem;}
  .light-modal .modal-title{font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:0.6rem;}
  .light-modal .modal-title i{color:var(--primary);}
  .light-modal .modal-body{background:var(--surface-2);padding:1.5rem;}
  .light-modal .modal-footer{background:#fff;border-top:1px solid var(--border);padding:1rem 1.5rem;}

  .applicant-photo-wrap{display:flex;align-items:center;gap:1.25rem;background:#fff;border:1px solid var(--border);border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:1.25rem;flex-wrap:wrap;}
  .applicant-photo{width:80px;height:80px;border-radius:50%;object-fit:cover;border:2.5px solid var(--primary-border);background:var(--surface-2);flex-shrink:0;}
  .applicant-photo-placeholder{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#DBEAFE,#EFF6FF);border:2.5px solid var(--primary-border);display:flex;align-items:center;justify-content:center;font-size:1.75rem;font-weight:700;color:var(--primary);text-transform:uppercase;flex-shrink:0;}
  .applicant-photo-name{font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:700;color:var(--text-primary);margin-bottom:0.2rem;}
  .applicant-photo-sub{font-size:0.78rem;color:var(--text-muted);}

  .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.85rem;margin-bottom:1.5rem;}
  .info-item{background:#fff;border:1px solid var(--border);border-radius:10px;padding:0.85rem 1rem;}
  .info-label{font-size:0.62rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-muted);margin-bottom:0.3rem;}
  .info-value{font-size:0.88rem;font-weight:600;color:var(--text-primary);word-break:break-all;}
  .docs-section-title{font-size:0.65rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:0.85rem;padding-bottom:0.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:0.5rem;}
  .docs-section-title i{color:var(--primary);}

  /* ── Document cards ── */
  .doc-card{background:#fff;border:1px solid var(--border);border-radius:10px;overflow:hidden;height:100%;cursor:pointer;transition:box-shadow 0.2s,transform 0.2s;}
  .doc-card:hover{box-shadow:0 4px 16px rgba(37,99,235,0.13);transform:translateY(-2px);}
  .doc-card .doc-preview{width:100%;height:130px;object-fit:cover;display:block;border-bottom:1px solid var(--border);}
  .doc-card .doc-preview-icon{width:100%;height:130px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--surface-2);border-bottom:1px solid var(--border);font-size:2rem;color:var(--text-muted);gap:0.5rem;}
  .doc-card .doc-preview-icon span{font-size:0.72rem;font-weight:600;color:var(--text-muted);}
  .doc-card .doc-info{padding:0.6rem 0.85rem;font-size:0.75rem;color:var(--text-secondary);display:flex;align-items:center;justify-content:space-between;gap:0.5rem;}
  .doc-card .doc-name{font-weight:600;color:var(--text-primary);text-transform:capitalize;font-size:0.75rem;}
  .doc-open-hint{font-size:0.68rem;color:var(--primary);font-weight:600;white-space:nowrap;display:flex;align-items:center;gap:0.25rem;}
  .doc-date{padding:0 0.85rem 0.55rem;font-size:0.68rem;color:var(--text-muted);display:flex;align-items:center;gap:0.3rem;}
  .doc-date i{color:var(--primary);}

  /* ── Lightbox ── */
  #docLightbox{position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:9999;display:none;align-items:center;justify-content:center;padding:1rem;}
  #docLightbox.show{display:flex;}
  #docLightbox img{max-width:100%;max-height:90vh;object-fit:contain;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,0.5);}
  #docLightbox iframe{width:90vw;height:90vh;border:none;border-radius:8px;}
  .lightbox-close{position:fixed;top:1.25rem;right:1.25rem;width:40px;height:40px;border-radius:50%;background:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#333;box-shadow:0 2px 12px rgba(0,0,0,0.3);z-index:10000;transition:background 0.15s;}
  .lightbox-close:hover{background:#f1f5f9;}
  .lightbox-label{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);background:rgba(255,255,255,0.15);color:#fff;padding:0.4rem 1.2rem;border-radius:20px;font-size:0.78rem;font-weight:600;backdrop-filter:blur(6px);}

  /* ── Action buttons ── */
  .btn-accept{background:#059669;color:#fff;border:none;border-radius:8px;padding:0.6rem 1.5rem;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:0.5rem;transition:transform 0.15s,box-shadow 0.15s;}
  .btn-accept:hover{background:#047857;transform:translateY(-1px);box-shadow:0 4px 14px rgba(5,150,105,0.3);}
  .btn-reject{background:#DC2626;color:#fff;border:none;border-radius:8px;padding:0.6rem 1.5rem;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:0.5rem;transition:transform 0.15s,box-shadow 0.15s;}
  .btn-reject:hover{background:#B91C1C;transform:translateY(-1px);box-shadow:0 4px 14px rgba(220,38,38,0.3);}
  .btn-secondary{background:var(--surface-2);border:1px solid var(--border);border-radius:7px;color:var(--text-secondary);font-size:0.75rem;font-weight:600;padding:0.35rem 0.85rem;cursor:pointer;display:flex;align-items:center;gap:0.4rem;transition:background 0.2s;font-family:'DM Sans',sans-serif;}
  .btn-secondary:hover{background:var(--border);}

  /* ── Report ── */
  #reportPreviewArea{background:#fff;color:#000;font-family:Arial,sans-serif;padding:36px 52px 48px;min-height:400px;border-radius:6px;width:794px;max-width:100%;margin:0 auto;}
  .rpt-letterhead{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:4px;}
  .rpt-header-text{text-align:center;flex:1;line-height:1.5;}
  .rpt-republic{font-size:9.5px;} .rpt-school-name{font-size:13.5px;font-weight:900;text-transform:uppercase;}
  .rpt-campus{font-size:9.5px;} .rpt-address{font-size:8.5px;color:#333;}
  .rpt-header-divider{border:none;border-top:2.5px solid #000;margin:10px 0 6px;}
  .rpt-office-title{text-align:center;font-size:11.5px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;text-decoration:underline;margin-bottom:18px;}
  .rpt-program-section{margin-bottom:24px;}
  .rpt-program-title{font-size:14px;font-weight:900;text-align:center;text-transform:uppercase;margin-bottom:3px;}
  .rpt-names-columns{display:grid;grid-template-columns:1fr 1fr;column-gap:36px;}
  .rpt-name-item{font-size:10.5px;padding:2px 0;display:flex;gap:6px;align-items:baseline;}
  .rpt-name-num{min-width:30px;text-align:right;flex-shrink:0;}
  .rpt-program-divider{border:none;border-top:1px solid #ccc;margin:22px 0;}
  .rpt-no-approved{text-align:center;padding:2.5rem 1rem;color:#888;font-size:0.88rem;}
  .rpt-no-approved i{font-size:2.5rem;display:block;margin-bottom:0.75rem;opacity:0.3;}

  @keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

  @media (max-width:768px){
    .wrap{padding:5rem 1rem 2rem;}
    .page-header h1{font-size:1.4rem;}
    .status-tabs{gap:0.4rem;}
    .stab{padding:0.4rem 0.75rem;font-size:0.72rem;}
    .filter-bar{flex-direction:column;align-items:stretch;}
    .search-wrap{min-width:unset;}
    .prog-select{width:100%;}
    .btn-primary-action{width:100%;justify-content:center;}
    .applicants-table th:nth-child(4),.applicants-table td:nth-child(4){display:none;}
    .applicants-table th{font-size:0.58rem;padding:0.7rem 0.75rem;}
    .applicants-table td{padding:0.75rem 0.75rem;font-size:0.8rem;}
    .id-badge{font-size:0.65rem;padding:0.15rem 0.4rem;}
    .program-pill{font-size:0.65rem;padding:0.15rem 0.45rem;}
    .info-grid{grid-template-columns:1fr;}
    .modal-footer{flex-wrap:wrap;gap:0.5rem;}
    .btn-accept,.btn-reject{flex:1;justify-content:center;padding:0.6rem 1rem;}
    .applicant-photo-wrap{flex-direction:column;text-align:center;}
    #reportPreviewArea{padding:20px 16px;width:100%;}
    .rpt-names-columns{grid-template-columns:1fr;}
    .pagination-wrap{flex-direction:column;align-items:flex-start;}
  }
  @media (max-width:480px){
    .stab .cnt{display:none;}
    .applicants-table th:nth-child(3),.applicants-table td:nth-child(3){display:none;}
  }
</style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <div class="page-eyebrow">NCC Admission Portal</div>
      <h1>Applicants List</h1>
    </div>
    <a href="admin_dashboard.php" class="back-link"><i class="fa fa-home"></i></a>
  </div>

  <?php
  $total    = count($profile);
  $cPending = $cAccepted = $cDeclined = 0;
  foreach ($profile as $r) {
    $s = $applicantStatuses[$r['applicant_id']] ?? 'Pending';
    if ($s === 'Approved')  $cAccepted++;
    elseif ($s === 'Rejected') $cDeclined++;
    else $cPending++;
  }
  ?>

  <div class="status-tabs">
    <button class="stab active" data-status="all"      onclick="filterByStatus('all',this)">     <span class="dot"></span> All <span class="cnt"><?= $total ?></span></button>
    <button class="stab"        data-status="pending"  onclick="filterByStatus('pending',this)"> <span class="dot"></span> Pending <span class="cnt"><?= $cPending ?></span></button>
    <button class="stab"        data-status="accepted" onclick="filterByStatus('accepted',this)"><span class="dot"></span> Approved <span class="cnt"><?= $cAccepted ?></span></button>
    <button class="stab"        data-status="declined" onclick="filterByStatus('declined',this)"><span class="dot"></span> Rejected <span class="cnt"><?= $cDeclined ?></span></button>
  </div>

  <div class="table-card">
    <div class="table-card-header">
      <div class="filter-bar">
        <div class="search-wrap">
          <i class="fa fa-search"></i>
          <input type="text" id="searchInput" placeholder="Search applicants…" class="search-input" onkeyup="applyFilters()">
        </div>
        <select class="prog-select" id="programFilter" onchange="applyFilters()">
          <option value="">All Programs</option>
          <?php foreach ($programs as $pid => $pname): ?>
            <option value="<?= htmlspecialchars($pid) ?>"><?= htmlspecialchars($pname) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn-primary-action" onclick="openReportModal()">
          <i class="fa fa-file-export"></i> Generate Report
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="applicants-table" id="applicantsTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Applicant</th>
            <th>Program</th>
            <th>Contact</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($profile)): ?>
            <?php foreach ($profile as $row):
              $appStatus = $applicantStatuses[$row['applicant_id']] ?? 'Pending';

              if ($appStatus === 'Approved') {
                  $bc = 'accepted'; $fa = 'accepted'; $displayStatus = 'Approved';
              } elseif ($appStatus === 'Rejected') {
                  $bc = 'declined'; $fa = 'declined'; $displayStatus = 'Rejected';
              } else {
                  $bc = 'pending'; $fa = 'pending'; $displayStatus = 'Pending';
              }

              $programName = $programs[$row['program_id']] ?? $row['program_id'];
              $email       = $applicantEmails[$row['applicant_id']] ?? '';
              $rowWithEmail = array_merge($row, ['email_from_user' => $email]);
            ?>
            <tr data-status="<?= $fa ?>"
                data-program-id="<?= htmlspecialchars($row['program_id']) ?>"
                data-applicant-id="<?= htmlspecialchars($row['applicant_id']) ?>">
              <td><span class="id-badge"><?= htmlspecialchars($row['applicant_id']) ?></span></td>
              <td><span class="name-text"><?= htmlspecialchars($row['Applicant_name'] ?? '') ?></span></td>
              <td><span class="program-pill"><i class="fa fa-graduation-cap"></i> <?= htmlspecialchars($programName) ?></span></td>
              <td><div class="contact-cell"><i class="fa fa-phone"></i><?= htmlspecialchars($row['contact_number'] ?? '') ?></div></td>
              <td>
                <span class="status-badge <?= $bc ?>">
                  <span class="status-dot"></span><?= $displayStatus ?>
                </span>
              </td>
              <td>
                <button class="action-btn" title="View Application"
                  onclick="openApplicantModal(
                    <?= htmlspecialchars(json_encode($rowWithEmail)) ?>,
                    <?= htmlspecialchars(json_encode($appStatus)) ?>,
                    <?= htmlspecialchars(json_encode($programName)) ?>,
                    <?= htmlspecialchars(json_encode($displayStatus)) ?>
                  )">
                  <i class="fa fa-eye"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6"><div class="empty-state"><i class="fa fa-folder-open"></i><p>No applicants found.</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="pagination-wrap">
      <div class="pagination-info" id="paginationInfo"></div>
      <div class="pagination-controls" id="paginationControls"></div>
    </div>
  </div>

</div>

<!-- ── FULLSCREEN DOCUMENT LIGHTBOX ── -->
<div id="docLightbox" onclick="closeLightbox(event)">
  <button class="lightbox-close" onclick="closeLightboxBtn()"><i class="fa fa-times"></i></button>
  <div id="docLightboxContent"></div>
  <div class="lightbox-label" id="docLightboxLabel"></div>
</div>

<!-- ── APPLICANT DETAIL MODAL ── -->
<div class="modal fade light-modal" id="applicantModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="fa fa-user-circle"></i><span>Applicant Details</span></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="modalStatusBanner" style="border-radius:10px;padding:0.65rem 1rem;margin-bottom:1.25rem;font-size:0.8rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;"></div>
        <div class="applicant-photo-wrap">
          <div id="modalPhotoEl"></div>
          <div>
            <div class="applicant-photo-name" id="modalPhotoName"></div>
            <div class="applicant-photo-sub"  id="modalPhotoSub"></div>
          </div>
        </div>
        <div class="info-grid" id="modalInfoGrid"></div>
        <div class="docs-section-title">
          <i class="fa fa-file-alt"></i> Submitted Documents
          <span style="font-size:0.7rem;font-weight:400;color:var(--text-muted);margin-left:0.5rem;">(click to view full screen)</span>
        </div>
        <div class="row g-3" id="modalDocsGrid">
          <div class="col-12 text-center" style="color:var(--text-muted);font-size:0.85rem;padding:2rem;">Loading documents…</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-reject" id="btnReject" onclick="openRejectReasonModal()"><i class="fa fa-times-circle"></i> Reject</button>
        <button type="button" class="btn-accept" id="btnAccept" onclick="submitStatus('Approved')"><i class="fa fa-check-circle"></i> Accept</button>
      </div>
    </div>
  </div>
</div>

<!-- ── REJECTION REASON MODAL ── -->
<div class="modal fade light-modal" id="rejectReasonModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
    <div class="modal-content" style="border-color:#FECACA;">
      <div class="modal-header" style="border-bottom-color:#FEF2F2;">
        <div class="modal-title" style="color:#991B1B;"><i class="fa fa-times-circle" style="color:#DC2626;"></i> Reject Application</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="background:#FEF2F2;padding:2rem 1.75rem 1.5rem;">
        <div style="display:flex;align-items:flex-start;gap:0.75rem;background:#fff;border:1px solid #FECACA;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem;">
          <i class="fa fa-exclamation-circle" style="color:#DC2626;font-size:1.2rem;flex-shrink:0;margin-top:2px;"></i>
          <p style="color:var(--text-secondary);font-size:0.83rem;line-height:1.6;margin:0;">You are about to <strong style="color:#991B1B;">reject</strong> this application. Please provide a disqualification reason — it will be visible to the applicant on their status page.</p>
        </div>
        <label style="font-size:0.7rem;font-weight:700;letter-spacing:0.13em;text-transform:uppercase;color:var(--text-secondary);display:block;margin-bottom:0.5rem;">Disqualification Reason <span style="color:#DC2626;">*</span></label>
        <textarea id="rejectReasonText" rows="4"
          placeholder="e.g. Incomplete requirements — missing birth certificate and Form 138…"
          style="width:100%;border:1px solid #FECACA;border-radius:8px;padding:0.75rem 1rem;font-family:'DM Sans',sans-serif;font-size:0.85rem;color:var(--text-primary);background:#fff;resize:vertical;outline:none;"
          oninput="document.getElementById('rejectReasonError').style.display='none';"
          onfocus="this.style.borderColor='#DC2626';" onblur="this.style.borderColor='#FECACA';"></textarea>
        <div id="rejectReasonError" style="display:none;color:#DC2626;font-size:0.78rem;margin-top:0.4rem;"><i class="fa fa-exclamation-circle"></i> A disqualification reason is required.</div>
      </div>
      <div class="modal-footer" style="background:#FEF2F2;border-top-color:#FECACA;gap:0.75rem;flex-wrap:wrap;">
        <button type="button" class="btn-secondary" data-bs-dismiss="modal"><i class="fa fa-arrow-left"></i> Cancel</button>
        <button type="button" class="btn-reject" onclick="confirmReject()"><i class="fa fa-times-circle"></i> Confirm Reject</button>
      </div>
    </div>
  </div>
</div>

<!-- ── SELECT PROGRAM WARNING MODAL ── -->
<div class="modal fade light-modal" id="selectProgramModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
    <div class="modal-content" style="border-color:#BFDBFE;">
      <div class="modal-header" style="border-bottom-color:#EFF6FF;">
        <div class="modal-title" style="color:#1D4ED8;"><i class="fa fa-filter" style="color:var(--primary);"></i> Select a Program First</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="background:#EFF6FF;text-align:center;padding:2.5rem 2rem 2rem;">
        <div style="width:64px;height:64px;border-radius:50%;background:#DBEAFE;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;">
          <i class="fa fa-graduation-cap" style="font-size:1.75rem;color:var(--primary);"></i>
        </div>
        <p style="color:var(--text-primary);font-size:1rem;font-weight:700;margin-bottom:0.6rem;font-family:'Playfair Display',serif;">No Program Selected</p>
        <p style="color:var(--text-secondary);font-size:0.84rem;line-height:1.7;margin-bottom:0;">Please select a program from the dropdown before generating a report.</p>
      </div>
      <div class="modal-footer" style="justify-content:center;background:#EFF6FF;border-top-color:#DBEAFE;padding-bottom:1.5rem;">
        <button type="button" class="btn-primary-action" data-bs-dismiss="modal" style="min-width:140px;"><i class="fa fa-check"></i> Got It</button>
      </div>
    </div>
  </div>
</div>

<!-- ── REPORT MODAL ── -->
<div class="modal fade light-modal" id="reportModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="fa fa-file-alt"></i> Report Preview — Approved Applicants</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1.25rem;background:#D1D5DB;">
        <div id="reportPreviewArea"></div>
      </div>
      <div class="modal-footer" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
        <span id="reportFilterLabel" style="font-size:0.75rem;color:var(--text-muted);"></span>
        <div style="display:flex;gap:0.75rem;">
          <button type="button" class="btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn-primary-action" id="btnDownloadReport" onclick="downloadReport()"><i class="fa fa-download"></i> Download PDF</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Hidden status form -->
<form method="POST" id="statusForm" style="display:none;">
  <input type="hidden" name="action" value="update_status">
  <input type="hidden" name="applicant_id" id="statusApplicantId">
  <input type="hidden" name="new_status" id="statusNewStatus">
  <input type="hidden" name="rejection_reason" id="statusRejectionReason">
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const REPORT_DATA_BY_ID = <?= json_encode($reportData) ?>;
  const PROGRAMS_MAP      = <?= json_encode($programs) ?>;

  let currentFilter = 'all', currentProgramFilter = '', currentApplicantId = null;
  const ROWS_PER_PAGE = 10;
  let currentPage = 1, filteredRows = [];

  /* ── LIGHTBOX ── */
  function openLightbox(src, name, isPdf) {
    const lb      = document.getElementById('docLightbox');
    const content = document.getElementById('docLightboxContent');
    const label   = document.getElementById('docLightboxLabel');
    if (isPdf) {
      content.innerHTML = `<iframe src="${src}"></iframe>`;
    } else {
      content.innerHTML = `<img src="${src}" alt="${name}">`;
    }
    label.textContent = name;
    lb.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
  function closeLightbox(e) {
    if (e.target === document.getElementById('docLightbox')) closeLightboxBtn();
  }
  function closeLightboxBtn() {
    document.getElementById('docLightbox').classList.remove('show');
    document.getElementById('docLightboxContent').innerHTML = '';
    document.body.style.overflow = '';
  }
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightboxBtn(); });

  /* ── PAGINATION ── */
  function renderPagination() {
    const total = filteredRows.length, totalPages = Math.ceil(total / ROWS_PER_PAGE) || 1;
    const start = total === 0 ? 0 : (currentPage - 1) * ROWS_PER_PAGE + 1;
    const end   = Math.min(currentPage * ROWS_PER_PAGE, total);
    document.getElementById('paginationInfo').innerHTML =
      total === 0 ? 'No applicants found' : `Showing <strong>${start}–${end}</strong> of <strong>${total}</strong> applicants`;
    filteredRows.forEach((row, i) => {
      row.style.display = (i >= (currentPage-1)*ROWS_PER_PAGE && i < currentPage*ROWS_PER_PAGE) ? '' : 'none';
    });
    const ctrl = document.getElementById('paginationControls');
    ctrl.innerHTML = '';
    if (totalPages <= 1) return;
    const prev = document.createElement('button');
    prev.className = 'page-btn'; prev.innerHTML = '<i class="fa fa-chevron-left"></i>';
    prev.disabled = currentPage === 1; prev.onclick = () => goToPage(currentPage - 1);
    ctrl.appendChild(prev);
    pageRange(currentPage, totalPages).forEach((p, idx, arr) => {
      if (idx > 0 && p - arr[idx-1] > 1) {
        const ell = document.createElement('span');
        ell.textContent = '…'; ell.style.cssText = 'padding:0 0.35rem;color:var(--text-muted);font-size:0.78rem;';
        ctrl.appendChild(ell);
      }
      const btn = document.createElement('button');
      btn.className = 'page-btn' + (p === currentPage ? ' active' : '');
      btn.textContent = p; btn.onclick = () => goToPage(p);
      ctrl.appendChild(btn);
    });
    const next = document.createElement('button');
    next.className = 'page-btn'; next.innerHTML = '<i class="fa fa-chevron-right"></i>';
    next.disabled = currentPage === totalPages; next.onclick = () => goToPage(currentPage + 1);
    ctrl.appendChild(next);
  }
  function pageRange(current, total) {
    const delta = 2, pages = new Set([1, total]);
    for (let i = Math.max(2, current-delta); i <= Math.min(total-1, current+delta); i++) pages.add(i);
    return Array.from(pages).sort((a,b) => a-b);
  }
  function goToPage(page) {
    currentPage = Math.max(1, Math.min(page, Math.ceil(filteredRows.length/ROWS_PER_PAGE)||1));
    renderPagination();
    document.getElementById('applicantsTable').closest('.table-card').scrollIntoView({behavior:'smooth',block:'nearest'});
  }

  /* ── FILTERING ── */
  function filterByStatus(status, el) {
    currentFilter = status;
    document.querySelectorAll('.stab').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    applyFilters();
  }
  function applyFilters() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    currentProgramFilter = document.getElementById('programFilter').value;
    const allRows = Array.from(document.querySelectorAll('#applicantsTable tbody tr'));
    allRows.forEach(r => r.style.display = 'none');
    filteredRows = allRows.filter(row => {
      if (row.querySelector('.empty-state')) return false;
      const statusOk  = currentFilter === 'all' || row.dataset.status === currentFilter;
      const programOk = !currentProgramFilter || String(row.dataset.programId) === String(currentProgramFilter);
      const searchOk  = !q
        || row.querySelector('td:nth-child(1)')?.textContent.toLowerCase().includes(q)
        || row.querySelector('td:nth-child(2)')?.textContent.toLowerCase().includes(q);
      return statusOk && programOk && searchOk;
    });
    currentPage = 1;
    renderPagination();
  }

  /* ── REPORT ── */
  function openReportModal() {
    if (!currentProgramFilter) { new bootstrap.Modal(document.getElementById('selectProgramModal')).show(); return; }
    buildReportPreview(currentProgramFilter);
    document.getElementById('reportFilterLabel').textContent =
      'Program: ' + (PROGRAMS_MAP[currentProgramFilter] || '') + '  |  Approved applicants only';
    new bootstrap.Modal(document.getElementById('reportModal')).show();
  }
  function buildReportPreview(pid) {
    const match    = REPORT_DATA_BY_ID[String(pid)];
    const approved = match
      ? [{...match, applicants: match.applicants.filter(a => a.status === 'Approved')}].filter(p => p.applicants.length)
      : [];
    const meta = PROGRAMS_MAP[pid] || 'Unknown Program';
    const body = approved.length
      ? approved.map((prog, pi) =>
          `${pi > 0 ? '<hr class="rpt-program-divider">' : ''}<div class="rpt-program-section">
           <div class="rpt-program-title">${prog.program_name}</div>
           <div class="rpt-names-columns">${prog.applicants.map((a,i) =>
             `<div class="rpt-name-item"><span class="rpt-name-num">${i+1}.</span><span>${a.name}</span></div>`).join('')}
           </div></div>`).join('')
      : `<div class="rpt-no-approved"><i class="fa fa-user-slash"></i>No <strong>Approved</strong> applicants for <strong>${meta}</strong>.</div>`;
    document.getElementById('reportPreviewArea').innerHTML = `
      <div class="rpt-letterhead">
        <img src="../img/logo.png" alt="Logo" style="height:72px;">
        <div class="rpt-header-text">
          <div class="rpt-republic">Republic of the Philippines</div>
          <div class="rpt-school-name">Northeastern Cebu Colleges Inc.</div>
          <div class="rpt-campus">Main Campus</div>
          <div class="rpt-address">P.G. Almendras St., Danao City, Cebu | admin@ncc.edu.ph</div>
        </div>
      </div>
      <hr class="rpt-header-divider">
      <div class="rpt-office-title">Office of the Campus Admission</div>${body}`;
  }
  async function downloadReport() {
    const btn = document.getElementById('btnDownloadReport');
    btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating…';
    try {
      const {jsPDF} = window.jspdf, MARGIN = 10;
      const canvas  = await html2canvas(document.getElementById('reportPreviewArea'),
        {scale:3, useCORS:true, backgroundColor:'#ffffff', logging:false, windowWidth:794, width:794});
      const pdf     = new jsPDF({orientation:'portrait', unit:'mm', format:'a4'});
      const usableW = 210-MARGIN*2, usableH = 297-MARGIN*2;
      const imgW    = usableW, imgH = (canvas.height*imgW)/canvas.width;
      let remaining = imgH, srcY = 0, first = true;
      while (remaining > 0) {
        const pageH  = Math.min(remaining, usableH), sliceH = (pageH/imgH)*canvas.height;
        const sc     = document.createElement('canvas');
        sc.width     = canvas.width; sc.height = Math.ceil(sliceH);
        sc.getContext('2d').drawImage(canvas, 0, srcY, canvas.width, Math.ceil(sliceH), 0, 0, canvas.width, Math.ceil(sliceH));
        if (!first) pdf.addPage();
        pdf.addImage(sc.toDataURL('image/png'), 'PNG', MARGIN, MARGIN, imgW, pageH);
        srcY += Math.ceil(sliceH); remaining -= pageH; first = false;
      }
      const prog = (currentProgramFilter && PROGRAMS_MAP[currentProgramFilter])
        ? PROGRAMS_MAP[currentProgramFilter].replace(/\s+/g,'_').replace(/[^a-zA-Z0-9_\-]/g,'') : 'Program';
      pdf.save(`NCC_${prog}_Approved_${new Date().toISOString().slice(0,10)}.pdf`);
    } catch(e) { console.error(e); alert('Failed to generate PDF. Please try again.'); }
    btn.disabled = false; btn.innerHTML = '<i class="fa fa-download"></i> Download PDF';
  }

  /* ── APPLICANT MODAL ── */
  function openApplicantModal(row, status, programName, displayStatus) {
    currentApplicantId = row.applicant_id;

    // Status banner
    const cfg = {
      'Approved': {bg:'#ECFDF5', border:'#A7F3D0', color:'#065F46', icon:'fa-check-circle'},
      'Rejected': {bg:'#FEF2F2', border:'#FECACA', color:'#991B1B', icon:'fa-times-circle'},
      'Pending':  {bg:'#FFFBEB', border:'#FDE68A', color:'#92400E', icon:'fa-hourglass-half'},
    };
    const bc = cfg[status] || cfg['Pending'];
    const banner = document.getElementById('modalStatusBanner');
    banner.style.cssText = `background:${bc.bg};border:1px solid ${bc.border};color:${bc.color};border-radius:10px;padding:0.65rem 1rem;margin-bottom:1.25rem;font-size:0.8rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;`;
    banner.innerHTML = `<i class="fa ${bc.icon}"></i> Current Status: <strong>${displayStatus || status}</strong>`;

    // Photo hero
    const photoField = row.photo || row.Photo || row.photo_path || '';
    const photoEl    = document.getElementById('modalPhotoEl');
    const initials   = (row.Applicant_name || 'A').substring(0, 2).toUpperCase();
    if (photoField) {
      let clean = photoField.replace(/^[\/\.]+/, '');
      const src = clean.includes('/') ? `../${clean}` : `../uploads/photos/${clean}`;
      photoEl.innerHTML = `<img class="applicant-photo" src="${src}" alt="Photo"
        onerror="this.outerHTML='<div class=\\'applicant-photo-placeholder\\'>${initials}</div>'">`;
    } else {
      photoEl.innerHTML = `<div class="applicant-photo-placeholder">${initials}</div>`;
    }
    document.getElementById('modalPhotoName').textContent = row.Applicant_name || row.applicant_name || 'N/A';
    document.getElementById('modalPhotoSub').textContent  = programName + (row.student_type ? ' · ' + row.student_type : '');

    // Info grid
    const emailVal = row.email_from_user || row.email || row.Email || 'N/A';
    document.getElementById('modalInfoGrid').innerHTML = [
      {label:'Applicant ID',   value: row.applicant_id || 'N/A'},
      {label:'Student Type',   value: row.student_type || 'N/A'},
      {label:'Program',        value: programName || row.program_id || 'N/A'},
      {label:'Contact Number', value: row.contact_number || 'N/A'},
      {label:'Address',        value: row.address || row.Address || 'N/A'},
      {label:'Date of Birth',  value: row.birthdate || row.date_of_birth || row.Birthdate || 'N/A'},
      {label:'Gender',         value: row.gender || row.Gender || row.sex || 'N/A'},
      {label:'Email',          value: emailVal},
    ].map(f => `<div class="info-item"><div class="info-label">${f.label}</div><div class="info-value">${f.value}</div></div>`).join('');

    // ── Load documents via AJAX ──────────────────────────────────────────────
    document.getElementById('modalDocsGrid').innerHTML = `
      <div class="col-12 text-center" style="color:var(--text-muted);font-size:0.85rem;padding:2rem;">
        <i class="fa fa-spinner fa-spin" style="color:var(--primary);margin-bottom:0.5rem;display:block;font-size:1.5rem;"></i>
        Loading documents…
      </div>`;

    fetch(`get_applicants.php?applicant_id=${row.applicant_id}`)
      .then(r => {
        if (!r.ok) throw new Error('Network response was not ok');
        return r.json();
      })
      .then(docs => {
        if (!docs || !docs.length) {
          document.getElementById('modalDocsGrid').innerHTML = `
            <div class="col-12">
              <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:1.2rem 1.5rem;display:flex;align-items:center;gap:0.75rem;color:#92400E;font-size:0.84rem;">
                <i class="fa fa-exclamation-triangle" style="color:#F59E0B;font-size:1.1rem;flex-shrink:0;"></i>
                <div><strong>No documents uploaded yet.</strong></div>
              </div>
            </div>`;
          return;
        }

        const imgExts = ['jpg','jpeg','png','gif','webp'];

        document.getElementById('modalDocsGrid').innerHTML = docs.map(doc => {
          const src  = doc.web_path;
          const ext  = src.split('.').pop().toLowerCase().split('?')[0];
          const name = (doc.document_name || 'Document').replace(/_/g, ' ');
          const isPdf = ext === 'pdf';

          const safeSrc  = src.replace(/'/g, "\\'");
          const safeName = name.replace(/'/g, "\\'");
          const clickAttr = `onclick="openLightbox('${safeSrc}','${safeName}',${isPdf})"`;

          // Upload date row (shown below doc name if available)
          const dateHtml = doc.uploaded_at
            ? `<div class="doc-date"><i class="fa fa-calendar-alt"></i>${doc.uploaded_at}</div>`
            : '';

          let preview;
          if (isPdf) {
            preview = `<div class="doc-preview-icon" ${clickAttr}>
              <i class="fa fa-file-pdf" style="color:#DC2626;font-size:2.5rem;"></i>
              <span>Click to view PDF</span>
            </div>`;
          } else if (imgExts.includes(ext)) {
            preview = `<img src="${src}" class="doc-preview" alt="${name}" ${clickAttr}
              onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
              <div class="doc-preview-icon" style="display:none;" ${clickAttr}>
                <i class="fa fa-image" style="font-size:2.5rem;"></i>
                <span>Image unavailable</span>
              </div>`;
          } else {
            preview = `<div class="doc-preview-icon" ${clickAttr}>
              <i class="fa fa-file-alt" style="font-size:2.5rem;"></i>
              <span>Click to view</span>
            </div>`;
          }

          return `<div class="col-md-4 col-sm-6">
            <div class="doc-card" ${clickAttr}>
              ${preview}
              <div class="doc-info">
                <span class="doc-name">${name}</span>
                <span class="doc-open-hint"><i class="fa fa-expand-alt"></i> View</span>
              </div>
              ${dateHtml}
            </div>
          </div>`;
        }).join('');
      })
      .catch(err => {
        console.error('Docs fetch error:', err);
        document.getElementById('modalDocsGrid').innerHTML = `
          <div class="col-12">
            <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:1.2rem;text-align:center;color:#991B1B;">
              <i class="fa fa-exclamation-circle" style="margin-right:0.5rem;"></i>
              <strong>Failed to load documents.</strong> Check the browser console for details.
            </div>
          </div>`;
      });

    // Show Accept/Reject only for Pending
    const hideActions = status === 'Approved' || status === 'Rejected';
    document.getElementById('btnAccept').style.display = hideActions ? 'none' : 'flex';
    document.getElementById('btnReject').style.display = hideActions ? 'none' : 'flex';

    new bootstrap.Modal(document.getElementById('applicantModal')).show();
  }

  /* ── STATUS ACTIONS ── */
  function openRejectReasonModal() {
    bootstrap.Modal.getInstance(document.getElementById('applicantModal'))?.hide();
    document.getElementById('rejectReasonText').value = '';
    document.getElementById('rejectReasonError').style.display = 'none';
    setTimeout(() => new bootstrap.Modal(document.getElementById('rejectReasonModal')).show(), 300);
  }
  function confirmReject() {
    const reason = document.getElementById('rejectReasonText').value.trim();
    if (!reason) { document.getElementById('rejectReasonError').style.display = 'block'; return; }
    bootstrap.Modal.getInstance(document.getElementById('rejectReasonModal'))?.hide();
    document.getElementById('statusApplicantId').value     = currentApplicantId;
    document.getElementById('statusNewStatus').value       = 'Rejected';
    document.getElementById('statusRejectionReason').value = reason;
    document.getElementById('statusForm').submit();
  }
  function submitStatus(newStatus) {
    if (!currentApplicantId) return;
    if (newStatus === 'Approved') {
      if (!confirm('Are you sure you want to ACCEPT this applicant?')) return;
    }
    document.getElementById('statusApplicantId').value     = currentApplicantId;
    document.getElementById('statusNewStatus').value       = newStatus;
    document.getElementById('statusRejectionReason').value = '';
    document.getElementById('statusForm').submit();
  }

  /* ── INIT ── */
  document.addEventListener('DOMContentLoaded', () => {
    applyFilters();
    const id = new URLSearchParams(window.location.search).get('view_applicant');
    if (!id) return;
    const row = document.querySelector(`tr[data-applicant-id="${id}"]`);
    if (row) row.querySelector('.action-btn')?.click();
  });
</script>
    <?php include '../layout/footer.php'?>
</body>
</html>