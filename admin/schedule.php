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

$db   = new Database($config);
$conn = $db->getConnection();

// Handle POSTs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update_school_year') {
        $yearId     = intval($_POST['year_id'] ?? 0);
        $schoolYear = trim($_POST['school_year'] ?? '');
        $deadline   = trim($_POST['deadline'] ?? '');
        if ($yearId && $schoolYear && $deadline) {
            $stmt = $conn->prepare("UPDATE school_year SET school_year = ?, deadline = ? WHERE year_id = ?");
            $stmt->bind_param("ssi", $schoolYear, $deadline, $yearId);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if ($_POST['action'] === 'save_exam') {
        $examId    = intval($_POST['exam_id'] ?? 0);
        $programId = intval($_POST['program_id'] ?? 0);
        $yearId    = intval($_POST['year_id'] ?? 0);
        $examStart = trim($_POST['exam_start'] ?? '');
        $examEnd   = trim($_POST['exam_end'] ?? '');
        if ($programId && $yearId && $examStart && $examEnd) {
            if ($examId > 0) {
                $stmt = $conn->prepare("UPDATE exam SET program_id=?, year_id=?, exam_start=?, exam_end=? WHERE exam_id=?");
                $stmt->bind_param("iissi", $programId, $yearId, $examStart, $examEnd, $examId);
            } else {
                $stmt = $conn->prepare("INSERT INTO exam (program_id, year_id, exam_start, exam_end) VALUES (?,?,?,?)");
                $stmt->bind_param("iiss", $programId, $yearId, $examStart, $examEnd);
            }
            $stmt->execute();
            $stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

include __DIR__ . '/../layout/admintop.php';

$programs = [];
$progResult = $conn->query("SELECT program_id, program_name FROM programs");
if ($progResult) while ($row = $progResult->fetch_assoc())
    $programs[$row['program_id']] = $row['program_name'];

$schoolYears = [];
$syResult = $conn->query("SELECT * FROM school_year ORDER BY year_id DESC");
while ($row = $syResult->fetch_assoc()) $schoolYears[] = $row;
$currentSY = !empty($schoolYears) ? $schoolYears[0] : null;

$exams = [];
$examResult = $conn->query("SELECT * FROM exam");
if ($examResult) while ($row = $examResult->fetch_assoc())
    $exams[$row['program_id'] . '_' . $row['year_id']] = $row;

// Active year filter (default: current SY)
$activeYearId = $_GET['year_id'] ?? ($currentSY ? $currentSY['year_id'] : null);

// Pagination — 10 programs per page
$PROGRAMS_PER_PAGE = 10;
$programIds  = array_keys($programs);
$totalProgs  = count($programIds);
$totalPages  = max(1, (int)ceil($totalProgs / $PROGRAMS_PER_PAGE));
$currentPage = max(1, min($totalPages, intval($_GET['prog_page'] ?? 1)));
$pageOffset  = ($currentPage - 1) * $PROGRAMS_PER_PAGE;
$pagedIds    = array_slice($programIds, $pageOffset, $PROGRAMS_PER_PAGE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Schedule — NCC Admission Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --primary: #2563EB;
    --primary-light: #3B82F6;
    --primary-pale: rgba(37,99,235,0.08);
    --primary-border: rgba(37,99,235,0.18);
    --surface: #F1F5F9;
    --surface-2: #F8FAFC;
    --border: #E2E8F0;
    --border-strong: #CBD5E1;
    --text-primary: #0F172A;
    --text-secondary: #475569;
    --text-muted: #94A3B8;
    --gold: #D97706;
    --gold-light: #F59E0B;
    --gold-pale: rgba(217,119,6,0.08);
    --green: #059669;
    --green-pale: rgba(5,150,105,0.08);
    --red: #DC2626;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  /* ── Sticky footer fix ── */
  html, body { height: 100%; }
  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--surface);
    color: var(--text-primary);
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }
  .page-body { flex: 1 0 auto; }
  footer, .footer { flex-shrink: 0; }

  .wrap { padding: 6rem 1.25rem 3rem; max-width: 1100px; margin: 0 auto; }

  /* Page header */
  .page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1.75rem;
    gap: 0.75rem;
    flex-wrap: wrap;
  }
  .page-eyebrow {
    font-size: 0.62rem; font-weight: 700; letter-spacing: 0.22em;
    text-transform: uppercase; color: var(--primary); margin-bottom: 0.35rem;
    display: flex; align-items: center; gap: 0.5rem;
  }
  .page-eyebrow::before {
    content: ''; display: inline-block; width: 18px; height: 2px;
    background: var(--primary); border-radius: 2px;
  }
  .page-header h1 {
    font-family: 'Playfair Display', serif; font-size: 1.6rem;
    font-weight: 700; color: var(--text-primary); line-height: 1.15;
  }
  .back-link {
    font-size: 0.78rem; color: var(--primary); font-weight: 600;
    text-decoration: none; display: flex; align-items: center; gap: 0.4rem;
    border: 1px solid var(--primary-border); border-radius: 7px;
    padding: 0.45rem 0.9rem; background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: background 0.2s;
    white-space: nowrap; flex-shrink: 0;
  }
  .back-link:hover { background: var(--primary-pale); color: var(--primary); }

  /* Tabs */
  .tab-bar {
    display: flex; gap: 0; border-bottom: 1.5px solid var(--border);
    margin-bottom: 1.75rem; overflow-x: auto; -webkit-overflow-scrolling: touch;
  }
  .tab-bar::-webkit-scrollbar { display: none; }
  .tab-btn {
    background: none; border: none; border-bottom: 2.5px solid transparent;
    margin-bottom: -1.5px; color: var(--text-muted);
    font-family: 'DM Sans', sans-serif; font-size: 0.82rem; font-weight: 600;
    padding: 0.7rem 1rem; cursor: pointer;
    display: flex; align-items: center; gap: 0.45rem;
    transition: color 0.2s, border-color 0.2s; white-space: nowrap;
  }
  .tab-btn i { font-size: 0.82rem; }
  .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
  .tab-btn:hover { color: var(--text-primary); }
  .tab-pane { display: none; animation: fadeUp 0.35s cubic-bezier(0.22,1,0.36,1) both; }
  .tab-pane.active { display: block; }

  /* Section heading */
  .sec-heading {
    font-family: 'Playfair Display', serif; font-size: 1.05rem;
    font-weight: 700; color: var(--text-primary); margin-bottom: 1.1rem;
    display: flex; align-items: center; gap: 0.55rem;
  }
  .sec-heading i { color: var(--primary); font-size: 0.95rem; }

  /* Cards */
  .card-base {
    background: #fff; border: 1px solid var(--border); border-radius: 12px;
    overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.04); margin-bottom: 0.85rem;
  }
  .card-base-header {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1rem 1.25rem; flex-wrap: wrap;
  }
  .card-base-body {
    padding: 1.1rem 1.25rem; border-top: 1px solid var(--border);
    background: var(--surface-2); display: none;
  }
  .card-base-body.open { display: block; }

  /* School year row */
  .sy-badge {
    display: inline-flex; align-items: center; gap: 0.35rem;
    background: var(--primary-pale); border: 1px solid var(--primary-border);
    border-radius: 6px; padding: 0.25rem 0.7rem;
    font-size: 0.75rem; font-weight: 700; color: var(--primary);
  }
  .sy-current-tag {
    display: inline-flex; align-items: center; gap: 0.3rem;
    background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 5px;
    padding: 0.18rem 0.55rem; font-size: 0.68rem; font-weight: 700; color: #065F46;
  }
  .sy-deadline-info {
    font-size: 0.75rem; color: var(--text-secondary);
    display: flex; align-items: center; gap: 0.35rem;
  }
  .sy-deadline-info i { color: var(--gold); }
  .btn-edit {
    background: var(--surface-2); border: 1px solid var(--border); border-radius: 7px;
    color: var(--text-secondary); font-family: 'DM Sans', sans-serif;
    font-size: 0.73rem; font-weight: 600; padding: 0.35rem 0.8rem; cursor: pointer;
    display: inline-flex; align-items: center; gap: 0.35rem;
    transition: background 0.2s, border-color 0.2s; white-space: nowrap; margin-left: auto;
  }
  .btn-edit:hover { background: var(--border); border-color: var(--border-strong); }

  /* Form fields */
  .field-label {
    font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em;
    text-transform: uppercase; color: var(--text-muted);
    margin-bottom: 0.35rem; display: block;
  }
  .field-input {
    width: 100%; background: #fff; border: 1px solid var(--border);
    border-radius: 8px; color: var(--text-primary);
    font-family: 'DM Sans', sans-serif; font-size: 0.88rem;
    padding: 0.6rem 0.9rem; outline: none; transition: border-color 0.2s;
  }
  .field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-pale); }
  .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-bottom: 0.85rem; }
  .form-actions { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }
  .btn-save {
    background: var(--primary); border: none; border-radius: 8px; color: #fff;
    font-family: 'DM Sans', sans-serif; font-size: 0.83rem; font-weight: 700;
    padding: 0.55rem 1.25rem; cursor: pointer;
    display: inline-flex; align-items: center; gap: 0.45rem;
    transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
  }
  .btn-save:hover { background: #1D4ED8; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(37,99,235,0.25); }
  .btn-cancel {
    background: none; border: 1px solid var(--border); border-radius: 8px;
    color: var(--text-secondary); font-family: 'DM Sans', sans-serif;
    font-size: 0.83rem; font-weight: 600; padding: 0.55rem 1rem; cursor: pointer;
    display: inline-flex; align-items: center; gap: 0.45rem; transition: background 0.15s;
  }
  .btn-cancel:hover { background: var(--border); }

  /* Year filter pills */
  .year-filter-bar { display: flex; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 1.1rem; }
  .year-tab {
    border: 1px solid var(--border); border-radius: 8px;
    padding: 0.4rem 0.9rem; font-size: 0.76rem; font-weight: 600;
    cursor: pointer; background: #fff; color: var(--text-secondary); transition: all 0.15s;
  }
  .year-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }
  .year-tab:hover:not(.active) { border-color: var(--border-strong); background: var(--surface-2); }

  /* Exam cards */
  .exam-card {
    background: #fff; border: 1px solid var(--border); border-radius: 11px;
    margin-bottom: 0.75rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  }
  .exam-card-header {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.9rem 1.1rem; flex-wrap: wrap;
  }
  .exam-prog-name {
    font-size: 0.85rem; font-weight: 700; color: var(--text-primary);
    display: flex; align-items: center; gap: 0.45rem;
    flex: 1; min-width: 0;
  }
  .exam-prog-name i { color: var(--primary); flex-shrink: 0; }
  .exam-prog-name span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .exam-date-range {
    display: flex; align-items: center; gap: 0.35rem; font-size: 0.74rem;
    color: var(--text-secondary); background: var(--gold-pale);
    border: 1px solid rgba(217,119,6,0.2); border-radius: 6px; padding: 0.28rem 0.65rem;
    white-space: nowrap;
  }
  .exam-date-range i { color: var(--gold); }
  .exam-no-schedule { font-size: 0.72rem; color: var(--text-muted); font-style: italic; }
  .exam-card-body {
    padding: 1.1rem; border-top: 1px solid var(--border);
    background: var(--surface-2); display: none;
  }
  .exam-card-body.open { display: block; }

  /* Pagination */
  .pagination-bar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem;
    padding-top: 1.1rem; border-top: 1px solid var(--border);
  }
  .pagination-info { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
  .pagination-controls { display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap; }
  .page-btn {
    border: 1px solid var(--border); border-radius: 7px; background: #fff;
    color: var(--text-secondary); font-family: 'DM Sans', sans-serif;
    font-size: 0.75rem; font-weight: 600; padding: 0.38rem 0.75rem; cursor: pointer;
    display: inline-flex; align-items: center; gap: 0.3rem;
    transition: all 0.15s; text-decoration: none;
  }
  .page-btn:hover:not(.disabled):not(.active-page) {
    background: var(--primary-pale); border-color: var(--primary-border); color: var(--primary);
  }
  .page-btn.active-page {
    background: var(--primary); border-color: var(--primary); color: #fff; cursor: default;
  }
  .page-btn.disabled { opacity: 0.38; cursor: not-allowed; pointer-events: none; }

  .empty-notice { text-align: center; padding: 3rem 1rem; color: var(--text-muted); font-size: 0.85rem; }
  .empty-notice i { font-size: 2.2rem; display: block; margin-bottom: 0.65rem; opacity: 0.3; }

  @keyframes fadeUp { from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)} }

  /* ── Mobile ── */
  @media (max-width: 576px) {
    .wrap { padding: 5rem 1rem 2rem; }

    .page-header h1 { font-size: 1.35rem; }

    .form-grid-2 { grid-template-columns: 1fr; }

    /* School year card header stacks cleanly */
    .card-base-header { gap: 0.5rem; }
    .btn-edit { margin-left: 0; width: 100%; justify-content: center; }

    /* Exam card: stack program name, date, and button */
    .exam-card-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
    .exam-prog-name { width: 100%; }
    .exam-prog-name span { white-space: normal; }
    .exam-date-range { width: 100%; }
    .exam-card-header .btn-edit { width: 100%; justify-content: center; margin-left: 0; }

    /* Pagination stacks info above controls */
    .pagination-bar { flex-direction: column; align-items: flex-start; }
    .pagination-controls { width: 100%; justify-content: center; }

    /* Year filter scrolls horizontally */
    .year-filter-bar { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px; }
    .year-filter-bar::-webkit-scrollbar { display: none; }

    /* Deadline info wraps on small screens */
    .sy-deadline-info { flex-wrap: wrap; }
  }

  @media (max-width: 400px) {
    .page-btn { padding: 0.35rem 0.55rem; font-size: 0.7rem; }
  }
</style>
</head>
<body>

<div class="page-body">
<div class="wrap">

  <div class="page-header">
    <div>
      <div class="page-eyebrow">NCC Admission Portal</div>
      <h1>School Year &amp; Exam Schedule</h1>
    </div>
    <a href="admin_dashboard.php" class="back-link"><i class="fa fa-home"></i></a>
  </div>

  <!-- Tabs -->
  <div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('exam', this)"><i class="fa fa-clock"></i> Exam Schedules</button>
    <button class="tab-btn"        onclick="switchTab('sy', this)"><i class="fa fa-graduation-cap"></i> School Years</button>
  </div>

  <!-- ── EXAM SCHEDULES TAB ── -->
  <div class="tab-pane active" id="tab-exam">
    <div class="sec-heading"><i class="fa fa-calendar-check"></i> Exam Schedules by Program</div>

    <?php if (!empty($programs)): ?>

      <?php if (count($schoolYears) > 1): ?>
      <div class="year-filter-bar">
        <?php foreach ($schoolYears as $sy): ?>
          <button class="year-tab <?= $sy['year_id'] == $activeYearId ? 'active' : '' ?>"
                  onclick="filterByYear(<?= $sy['year_id'] ?>, this)">
            SY <?= htmlspecialchars($sy['school_year']) ?>
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php foreach ($schoolYears as $sy):
        $isVisible = $sy['year_id'] == $activeYearId;
      ?>
      <div class="year-section" data-year="<?= $sy['year_id'] ?>" style="<?= $isVisible ? '' : 'display:none;' ?>">

        <?php foreach ($pagedIds as $progId):
          $progName = $programs[$progId];
          $key    = $progId . '_' . $sy['year_id'];
          $exam   = $exams[$key] ?? null;
          $examId = $exam ? ($exam['exam_id'] ?? $exam['exm_id'] ?? 0) : 0;
          $eStart = $exam ? $exam['exam_start'] : '';
          $eEnd   = $exam ? $exam['exam_end']   : '';
          $formId = 'exam-form-' . $progId . '_' . $sy['year_id'];
        ?>
        <div class="exam-card">
          <div class="exam-card-header">
            <div class="exam-prog-name">
              <i class="fa fa-graduation-cap"></i>
              <span><?= htmlspecialchars($progName) ?></span>
            </div>
            <?php if ($exam): ?>
              <div class="exam-date-range">
                <i class="fa fa-calendar-alt"></i>
                <?= date('M d, Y', strtotime($eStart)) ?> &mdash; <?= date('M d, Y', strtotime($eEnd)) ?>
              </div>
            <?php else: ?>
              <span class="exam-no-schedule">No schedule set</span>
            <?php endif; ?>
            <button class="btn-edit" onclick="toggleCardBody('<?= $formId ?>', this)">
              <i class="fa fa-<?= $exam ? 'pen' : 'plus' ?>"></i> <?= $exam ? 'Edit' : 'Set Schedule' ?>
            </button>
          </div>
          <div class="exam-card-body" id="<?= $formId ?>">
            <form method="POST">
              <input type="hidden" name="action" value="save_exam">
              <input type="hidden" name="exam_id" value="<?= $examId ?>">
              <input type="hidden" name="program_id" value="<?= $progId ?>">
              <input type="hidden" name="year_id" value="<?= $sy['year_id'] ?>">
              <div class="form-grid-2">
                <div>
                  <label class="field-label">Exam Start Date</label>
                  <input type="date" name="exam_start" class="field-input"
                         value="<?= $eStart ? date('Y-m-d', strtotime($eStart)) : '' ?>" required>
                </div>
                <div>
                  <label class="field-label">Exam End Date</label>
                  <input type="date" name="exam_end" class="field-input"
                         value="<?= $eEnd ? date('Y-m-d', strtotime($eEnd)) : '' ?>" required>
                </div>
              </div>
              <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> Save Schedule</button>
                <button type="button" class="btn-cancel" onclick="toggleCardBody('<?= $formId ?>', this)">
                  <i class="fa fa-times"></i> Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
        <?php endforeach; ?>

      </div>
      <?php endforeach; ?>

      <!-- Pagination -->
      <?php if ($totalPages > 1):
        $baseQuery = $_GET;
        unset($baseQuery['prog_page']);
        $baseUrl = '?' . http_build_query($baseQuery) . '&prog_page=';
      ?>
      <div class="pagination-bar">
        <div class="pagination-info">
          Programs <?= $pageOffset + 1 ?>–<?= min($pageOffset + $PROGRAMS_PER_PAGE, $totalProgs) ?> of <?= $totalProgs ?>
        </div>
        <div class="pagination-controls">
          <a class="page-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>"
             href="<?= $baseUrl . ($currentPage - 1) ?>">
            <i class="fa fa-chevron-left"></i> Prev
          </a>
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="page-btn <?= $p === $currentPage ? 'active-page' : '' ?>"
               href="<?= $baseUrl . $p ?>"><?= $p ?></a>
          <?php endfor; ?>
          <a class="page-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>"
             href="<?= $baseUrl . ($currentPage + 1) ?>">
            Next <i class="fa fa-chevron-right"></i>
          </a>
        </div>
      </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="empty-notice"><i class="fa fa-book-open"></i>No programs found.</div>
    <?php endif; ?>
  </div>

  <!-- ── SCHOOL YEARS TAB ── -->
  <div class="tab-pane" id="tab-sy">
    <div class="sec-heading"><i class="fa fa-graduation-cap"></i> Manage School Years</div>

    <?php if (!empty($schoolYears)): foreach ($schoolYears as $idx => $sy):
      $isCurrent = $currentSY && $sy['year_id'] == $currentSY['year_id'];
    ?>
    <div class="card-base">
      <div class="card-base-header">
        <div class="sy-badge"><i class="fa fa-calendar-alt"></i> SY <?= htmlspecialchars($sy['school_year']) ?></div>
        <?php if ($isCurrent): ?>
          <div class="sy-current-tag"><i class="fa fa-check-circle"></i> Current</div>
        <?php endif; ?>
        <div class="sy-deadline-info">
          <i class="fa fa-clock"></i>
          Deadline: <?= !empty($sy['deadline']) ? date('F d, Y', strtotime($sy['deadline'])) : 'N/A' ?>
        </div>
        <button class="btn-edit" onclick="toggleCardBody('sy-body-<?= $sy['year_id'] ?>', this)">
          <i class="fa fa-pen"></i> Edit
        </button>
      </div>
      <div class="card-base-body" id="sy-body-<?= $sy['year_id'] ?>">
        <form method="POST">
          <input type="hidden" name="action" value="update_school_year">
          <input type="hidden" name="year_id" value="<?= $sy['year_id'] ?>">
          <div class="form-grid-2">
            <div>
              <label class="field-label">School Year</label>
              <input type="text" name="school_year" class="field-input"
                     value="<?= htmlspecialchars($sy['school_year']) ?>" placeholder="e.g. 2026-2027" required>
            </div>
            <div>
              <label class="field-label">Application Deadline</label>
              <input type="date" name="deadline" class="field-input"
                     value="<?= htmlspecialchars($sy['deadline']) ?>" required>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-save"><i class="fa fa-save"></i> Save Changes</button>
            <button type="button" class="btn-cancel" onclick="toggleCardBody('sy-body-<?= $sy['year_id'] ?>', this)">
              <i class="fa fa-times"></i> Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; else: ?>
      <div class="empty-notice"><i class="fa fa-folder-open"></i>No school years found.</div>
    <?php endif; ?>
  </div>

</div><!-- /.wrap -->
</div><!-- /.page-body -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function switchTab(name, el) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
  }

  function toggleCardBody(id, btn) {
    const body = document.getElementById(id);
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    if (btn) {
      const icon = btn.querySelector('i');
      if (icon) icon.className = isOpen ? 'fa fa-pen' : 'fa fa-times';
    }
  }

  function filterByYear(yearId, btn) {
    document.querySelectorAll('.year-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.year-section').forEach(section => {
      section.style.display = String(section.dataset.year) === String(yearId) ? '' : 'none';
    });
    document.querySelectorAll('.pagination-controls .page-btn').forEach(a => {
      if (!a.href) return;
      const url = new URL(a.href, location.href);
      url.searchParams.set('year_id', yearId);
      a.href = url.toString();
    });
  }
</script>
<?php include '../layout/footer.php'?>
</body>
</html>