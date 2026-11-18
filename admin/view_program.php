<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_Id'])) {
    header("Location: ../pages/log_in.php");
    exit();
}
require_once __DIR__ . '/../database.php';
$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/program.php';
require_once __DIR__ . '/../Controller/programController.php';

$db   = new Database($config);
$conn = $db->getConnection();
$programController = new ProgramController(new Program($conn));
$programController->handleAjax();

include __DIR__ . '/../layout/admintop.php';

$limit = 10;
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page  = max($page, 1);

$search = trim($_GET['search'] ?? '');

$allPrograms = $programController->getAll();

if ($search !== '') {
    $allPrograms = array_filter($allPrograms, function($p) use ($search) {
        return stripos($p['program_name'], $search) !== false;
    });
    $allPrograms = array_values($allPrograms);
}

$totalPrograms = count($allPrograms);
$totalPages    = max(1, ceil($totalPrograms / $limit));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $limit;
$programs      = array_slice($allPrograms, $offset, $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Programs — Admin Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #2563EB;
      --primary-light: #3B82F6;
      --primary-pale: rgba(37,99,235,0.08);
      --primary-border: rgba(37,99,235,0.18);
      --surface: #F8FAFC;
      --surface-2: #F1F5F9;
      --surface-card: #FFFFFF;
      --border: #E2E8F0;
      --border-strong: #CBD5E1;
      --text-primary: #0F172A;
      --text-secondary: #475569;
      --text-muted: #94A3B8;
      --green: #059669;
      --green-pale: rgba(5,150,105,0.08);
      --red: #DC2626;
      --red-pale: rgba(220,38,38,0.08);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; }
    body {
      font-family: 'DM Sans', sans-serif; background: var(--surface);
      min-height: 100vh; color: var(--text-primary); overflow-x: hidden;
      display: flex; flex-direction: column;
    }
    .page-body { flex: 1 0 auto; }
    footer, .footer { flex-shrink: 0; }

    .dashboard-wrap { padding: 6rem 1.25rem 3rem; max-width: 1200px; margin: 0 auto; }

    /* Page header */
    .page-header {
      display: flex; align-items: flex-start; justify-content: space-between;
      margin-bottom: 1.75rem; flex-wrap: wrap; gap: 0.75rem;
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
    h1 {
      font-family: 'Playfair Display', serif; font-size: 1.6rem;
      font-weight: 700; color: var(--text-primary); line-height: 1.15;
    }

    .btn-add {
      display: inline-flex; align-items: center; gap: 0.45rem;
      background: var(--primary); color: #fff; font-family: 'DM Sans', sans-serif;
      font-size: 0.82rem; font-weight: 600; border: none; border-radius: 8px;
      padding: 0.55rem 1.1rem; cursor: pointer; white-space: nowrap;
      transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
    }
    .btn-add:hover { background: #1D4ED8; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(37,99,235,0.3); }

    .back-link {
      font-size: 0.78rem; color: var(--primary); font-weight: 600;
      text-decoration: none; display: flex; align-items: center; gap: 0.4rem;
      border: 1px solid var(--primary-border); border-radius: 7px;
      padding: 0.45rem 0.9rem; background: #fff;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: background 0.2s;
      white-space: nowrap; flex-shrink: 0;
    }
    .back-link:hover { background: var(--primary-pale); color: var(--primary); }

    /* Table card */
    .table-card {
      background: #fff; border: 1px solid var(--border); border-radius: 14px;
      overflow: hidden; animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
      box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }
    .table-card-header {
      background: #fff; border-bottom: 1px solid var(--border);
      padding: 1.1rem 1.25rem;
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 0.85rem;
    }
    .table-card-header h5 {
      font-family: 'Playfair Display', serif; font-size: 1rem;
      font-weight: 700; color: var(--text-primary); margin: 0;
      display: flex; align-items: center; gap: 0.55rem;
    }
    .table-card-header h5 i { color: var(--primary); font-size: 0.95rem; }

    /* Search + Add row */
    .header-actions {
      display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; width: 100%;
    }
    .search-wrap { position: relative; flex: 1; min-width: 140px; }
    .search-wrap i {
      position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%);
      color: var(--text-muted); font-size: 0.82rem; pointer-events: none;
    }
    .search-input {
      background: var(--surface-2); border: 1px solid var(--border); border-radius: 8px;
      color: var(--text-primary); font-family: 'DM Sans', sans-serif;
      font-size: 0.82rem; padding: 0.52rem 0.9rem 0.52rem 2.2rem;
      width: 100%; transition: border-color 0.2s, background 0.2s; outline: none;
    }
    .search-input::placeholder { color: var(--text-muted); }
    .search-input:focus { border-color: var(--primary); background: #fff; }

    /* Table — desktop */
    .programs-table { width: 100%; border-collapse: collapse; }
    .programs-table thead tr { background: var(--surface-2); border-bottom: 1px solid var(--border); }
    .programs-table th {
      font-size: 0.6rem; font-weight: 700; letter-spacing: 0.14em;
      text-transform: uppercase; color: var(--text-muted); padding: 0.8rem 1.1rem; white-space: nowrap;
    }
    .programs-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
    .programs-table tbody tr:last-child { border-bottom: none; }
    .programs-table tbody tr:hover { background: var(--surface-2); }
    .programs-table td { padding: 0.9rem 1.1rem; vertical-align: middle; font-size: 0.84rem; color: var(--text-primary); }

    .id-badge {
      display: inline-flex; align-items: center; background: var(--primary-pale);
      border: 1px solid var(--primary-border); border-radius: 5px;
      padding: 0.18rem 0.55rem; font-size: 0.7rem; font-weight: 700;
      color: var(--primary); font-family: monospace; letter-spacing: 0.05em;
    }
    .program-name-cell { display: flex; align-items: center; gap: 0.65rem; }
    .program-icon {
      width: 32px; height: 32px; border-radius: 7px;
      background: var(--primary-pale); border: 1px solid var(--primary-border);
      display: flex; align-items: center; justify-content: center;
      color: var(--primary); font-size: 0.82rem; flex-shrink: 0;
    }
    .program-name-text { font-weight: 600; color: var(--text-primary); font-size: 0.85rem; }
    .tuition-cell {
      display: inline-flex; align-items: center; gap: 0.35rem;
      background: var(--green-pale); border: 1px solid rgba(5,150,105,0.2);
      border-radius: 5px; padding: 0.22rem 0.65rem;
      font-size: 0.78rem; font-weight: 600; color: #065F46;
    }
    .action-group { display: flex; align-items: center; gap: 0.35rem; }
    .action-btn {
      width: 30px; height: 30px; border-radius: 7px; border: 1px solid transparent;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.76rem; cursor: pointer; transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
      background: none;
    }
    .action-btn:hover { transform: translateY(-1px); }
    .action-btn.edit { background: #FFFBEB; border-color: #FDE68A; color: #92400E; }
    .action-btn.edit:hover { background: #FEF3C7; box-shadow: 0 3px 10px rgba(245,158,11,0.15); }
    .action-btn.delete { background: var(--red-pale); border-color: rgba(220,38,38,0.2); color: #991B1B; }
    .action-btn.delete:hover { background: #FEE2E2; box-shadow: 0 3px 10px rgba(220,38,38,0.15); }

    .count-badge {
      display: inline-flex; align-items: center; background: var(--primary-pale);
      border: 1px solid var(--primary-border); border-radius: 99px;
      padding: 0.13rem 0.6rem; font-size: 0.68rem; font-weight: 700;
      color: var(--primary); margin-left: 0.4rem;
    }

    /* ── Mobile card layout (replaces table on small screens) ── */
    .mobile-card-list { display: none; padding: 0.75rem; }
    .mobile-prog-card {
      background: var(--surface-2); border: 1px solid var(--border); border-radius: 10px;
      padding: 0.85rem 1rem; margin-bottom: 0.65rem; display: flex; align-items: flex-start; gap: 0.75rem;
    }
    .mobile-prog-card:last-child { margin-bottom: 0; }
    .mobile-prog-icon {
      width: 36px; height: 36px; border-radius: 8px;
      background: var(--primary-pale); border: 1px solid var(--primary-border);
      display: flex; align-items: center; justify-content: center;
      color: var(--primary); font-size: 0.9rem; flex-shrink: 0; margin-top: 1px;
    }
    .mobile-prog-info { flex: 1; min-width: 0; }
    .mobile-prog-name { font-weight: 700; font-size: 0.88rem; color: var(--text-primary); margin-bottom: 0.3rem; }
    .mobile-prog-meta { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
    .mobile-prog-actions { display: flex; gap: 0.35rem; flex-shrink: 0; }

    .empty-state { padding: 3.5rem 1.5rem; text-align: center; }
    .empty-state i { font-size: 2.2rem; color: var(--text-muted); display: block; margin-bottom: 0.85rem; }
    .empty-state p { color: var(--text-muted); font-size: 0.85rem; }

    /* Pagination */
    .pagination-wrap {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.9rem 1.25rem; border-top: 1px solid var(--border);
      flex-wrap: wrap; gap: 0.65rem;
    }
    .pagination-info { font-size: 0.76rem; color: var(--text-muted); }
    .pagination-btns { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
    .pag-btn {
      font-size: 0.76rem; color: var(--text-secondary); font-weight: 600;
      background: var(--surface-2); border: 1px solid var(--border); border-radius: 7px;
      padding: 0.38rem 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;
      transition: all 0.15s; white-space: nowrap;
    }
    .pag-btn:hover { background: var(--primary-pale); border-color: var(--primary-border); color: var(--primary); }
    .pag-info-text { font-size: 0.76rem; color: var(--text-muted); white-space: nowrap; }

    /* Modals */
    .light-modal .modal-content {
      background: #fff; border: 1px solid var(--border); border-radius: 16px;
      overflow: hidden; color: var(--text-primary); box-shadow: 0 20px 60px rgba(0,0,0,0.12);
    }
    .light-modal .modal-header { background: #fff; border-bottom: 1px solid var(--border); padding: 1.1rem 1.4rem; }
    .light-modal .modal-title {
      font-family: 'Playfair Display', serif; font-size: 1rem;
      font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.55rem;
    }
    .light-modal .modal-title i { color: var(--primary); }
    .light-modal .modal-body { background: var(--surface-2); padding: 1.35rem; }
    .light-modal .modal-footer { background: #fff; border-top: 1px solid var(--border); padding: 0.9rem 1.4rem; }

    .form-label-custom {
      font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em;
      text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.35rem; display: block;
    }
    .form-field {
      width: 100%; background: #fff; border: 1px solid var(--border); border-radius: 8px;
      color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.88rem;
      padding: 0.6rem 0.9rem; outline: none; transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-field::placeholder { color: var(--text-muted); }
    .form-field:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    .input-group-addon {
      background: var(--surface-2); border: 1px solid var(--border); border-right: none;
      border-radius: 8px 0 0 8px; padding: 0.6rem 0.85rem; color: var(--text-secondary);
      font-size: 0.85rem; display: flex; align-items: center;
    }
    .input-group-wrap { display: flex; }
    .input-group-wrap .form-field { border-radius: 0 8px 8px 0; border-left: none; }
    .input-group-wrap .form-field:focus { border-left: 1px solid var(--primary); margin-left: -1px; }

    .btn-modal-save {
      background: var(--primary); color: #fff; font-family: 'DM Sans', sans-serif;
      font-weight: 600; font-size: 0.82rem; border: none; border-radius: 8px;
      padding: 0.55rem 1.3rem; cursor: pointer;
      transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
      display: inline-flex; align-items: center; gap: 0.4rem;
    }
    .btn-modal-save:hover { background: #1D4ED8; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(37,99,235,0.3); }
    .btn-modal-cancel {
      background: var(--surface-2); color: var(--text-secondary); font-family: 'DM Sans', sans-serif;
      font-weight: 500; font-size: 0.82rem; border: 1px solid var(--border); border-radius: 8px;
      padding: 0.55rem 1.1rem; cursor: pointer; transition: background 0.15s, border-color 0.15s;
    }
    .btn-modal-cancel:hover { background: var(--border); border-color: var(--border-strong); }
    .btn-modal-delete {
      background: var(--red-pale); color: #991B1B; font-family: 'DM Sans', sans-serif;
      font-weight: 600; font-size: 0.82rem; border: 1px solid rgba(220,38,38,0.2);
      border-radius: 8px; padding: 0.55rem 1.3rem; cursor: pointer;
      transition: background 0.15s, box-shadow 0.15s;
      display: inline-flex; align-items: center; gap: 0.4rem;
    }
    .btn-modal-delete:hover { background: #FEE2E2; box-shadow: 0 4px 14px rgba(220,38,38,0.2); }

    .delete-warning { text-align: center; padding: 0.75rem 0 0.25rem; }
    .delete-warning .warn-icon {
      width: 56px; height: 56px; border-radius: 50%; background: var(--red-pale);
      border: 1px solid rgba(220,38,38,0.2); display: flex; align-items: center;
      justify-content: center; margin: 0 auto 0.85rem; font-size: 1.3rem; color: #991B1B;
    }
    .delete-warning p { color: var(--text-secondary); font-size: 0.85rem; line-height: 1.6; }
    .delete-warning strong { color: var(--text-primary); font-weight: 600; }

    .toast-wrap {
      position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 9999;
      display: flex; flex-direction: column; gap: 0.55rem;
    }
    .toast-msg {
      background: #fff; border: 1px solid var(--border); border-radius: 10px;
      padding: 0.7rem 1rem; font-size: 0.82rem; color: var(--text-primary);
      display: flex; align-items: center; gap: 0.55rem;
      box-shadow: 0 8px 28px rgba(0,0,0,0.1);
      animation: slideIn 0.3s cubic-bezier(0.22,1,0.36,1); min-width: 200px;
      max-width: calc(100vw - 2.5rem);
    }
    .toast-msg.success i { color: var(--green); }
    .toast-msg.error i { color: var(--red); }

    @keyframes slideIn { from{opacity:0;transform:translateX(20px)} to{opacity:1;transform:translateX(0)} }
    @keyframes fadeUp  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

    /* ── Mobile breakpoints ── */
    @media (max-width: 640px) {
      .dashboard-wrap { padding: 5rem 0.85rem 2rem; }
      h1 { font-size: 1.35rem; }

      /* Show mobile cards, hide desktop table */
      .table-responsive { display: none !important; }
      .mobile-card-list { display: block; }

      /* Table card header: title on top, actions below */
      .table-card-header { flex-direction: column; align-items: flex-start; }
      .header-actions { flex-wrap: nowrap; }

      /* Pagination stacks */
      .pagination-wrap { flex-direction: column; align-items: flex-start; }
      .pagination-btns { width: 100%; justify-content: space-between; }

      /* Toast anchored to bottom center on mobile */
      .toast-wrap { right: 0.75rem; left: 0.75rem; bottom: 1rem; align-items: center; }
    }

    @media (min-width: 641px) {
      /* On desktop, always show table, hide mobile cards */
      .mobile-card-list { display: none !important; }
      .table-responsive { display: block !important; }
      .header-actions { width: auto; }
      .search-wrap { min-width: 200px; max-width: 240px; }
    }
  </style>
</head>
<body>
<div class="page-body">
<div class="dashboard-wrap">
  <div class="page-header">
    <div>
      <div class="page-eyebrow">NCC Admission Portal</div>
      <h1>Programs</h1>
    </div>
    <a href="admin_dashboard.php" class="back-link"><i class="fa fa-home"></i></a>
  </div>

  <div class="table-card">
    <div class="table-card-header">
      <h5>
        <i class="fa-solid fa-graduation-cap"></i>
        All Programs
        <span class="count-badge" id="countBadge"><?= $totalPrograms ?></span>
      </h5>
      <div class="header-actions">
        <div class="search-wrap">
          <i class="fa-solid fa-search"></i>
          <input
            class="search-input"
            id="searchInput"
            placeholder="Search program…"
            value="<?= htmlspecialchars($search) ?>"
            oninput="liveSearch(this.value)"
          >
        </div>
        <button class="btn-add" onclick="openAddModal()">
          <i class="fa-solid fa-plus"></i> Add Program
        </button>
      </div>
    </div>

    <!-- Desktop table -->
    <div class="table-responsive">
      <table class="programs-table" id="programsTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Program Name</th>
            <th>Tuition / Sem</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <?php if (!empty($programs)): ?>
            <?php foreach ($programs as $p): ?>
              <?php $t = (float) preg_replace('/[^0-9.]/', '', $p['tuition']); ?>
              <tr data-id="<?= (int)$p['program_id'] ?>"
                  data-name="<?= htmlspecialchars($p['program_name'], ENT_QUOTES) ?>"
                  data-tuition="<?= $t ?>">
                <td><span class="id-badge"><?= (int)$p['program_id'] ?></span></td>
                <td>
                  <div class="program-name-cell">
                    <div class="program-icon"><i class="fa-solid fa-book-open"></i></div>
                    <span class="program-name-text"><?= htmlspecialchars($p['program_name']) ?></span>
                  </div>
                </td>
                <td>
                  <span class="tuition-cell">
                    <i class="fa-solid fa-peso-sign"></i>
                    <span class="tuition-display"><?= number_format($t, 2) ?></span>
                  </span>
                </td>
                <td>
                  <div class="action-group">
                    <button class="action-btn edit" title="Edit" onclick="openEditModal(this)"><i class="fa-solid fa-pen-to-square"></i></button>
                    <button class="action-btn delete" title="Delete" onclick="openDeleteModal(this)"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr id="emptyRow"><td colspan="4">
              <div class="empty-state">
                <i class="fa-solid fa-folder-open"></i>
                <p><?= $search ? 'No programs matching "' . htmlspecialchars($search) . '".' : 'No programs found.' ?></p>
              </div>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile card list -->
    <div class="mobile-card-list" id="mobileCardList">
      <?php if (!empty($programs)): ?>
        <?php foreach ($programs as $p): ?>
          <?php $t = (float) preg_replace('/[^0-9.]/', '', $p['tuition']); ?>
          <div class="mobile-prog-card"
               data-id="<?= (int)$p['program_id'] ?>"
               data-name="<?= htmlspecialchars($p['program_name'], ENT_QUOTES) ?>"
               data-tuition="<?= $t ?>">
            <div class="mobile-prog-icon"><i class="fa-solid fa-book-open"></i></div>
            <div class="mobile-prog-info">
              <div class="mobile-prog-name"><?= htmlspecialchars($p['program_name']) ?></div>
              <div class="mobile-prog-meta">
                <span class="id-badge">#<?= (int)$p['program_id'] ?></span>
                <span class="tuition-cell" style="font-size:0.74rem;padding:0.18rem 0.55rem;">
                  <i class="fa-solid fa-peso-sign"></i><?= number_format($t, 2) ?>
                </span>
              </div>
            </div>
            <div class="mobile-prog-actions">
              <button class="action-btn edit" title="Edit" onclick="openEditModal(this)"><i class="fa-solid fa-pen-to-square"></i></button>
              <button class="action-btn delete" title="Delete" onclick="openDeleteModal(this)"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <i class="fa-solid fa-folder-open"></i>
          <p><?= $search ? 'No programs matching "' . htmlspecialchars($search) . '".' : 'No programs found.' ?></p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <div class="pagination-wrap" id="paginationWrap">
      <div class="pagination-info" id="paginationInfo">
        Showing <?= count($programs) ?> of <?= $totalPrograms ?> program<?= $totalPrograms !== 1 ? 's' : '' ?>
        <?= $search ? ' for <strong>' . htmlspecialchars($search) . '</strong>' : '' ?>
      </div>
      <div class="pagination-btns">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="pag-btn">
            <i class="fa fa-chevron-left"></i> Prev
          </a>
        <?php endif; ?>
        <?php if ($totalPages > 1): ?>
          <span class="pag-info-text">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="pag-btn">
            Next <i class="fa fa-chevron-right"></i>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal fade light-modal" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="fa-solid fa-plus-circle"></i> Add New Program</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label-custom">Program Name</label>
          <input type="text" class="form-field" id="addName" placeholder="e.g. Bachelor of Science in Nursing">
        </div>
        <div class="mb-1">
          <label class="form-label-custom">Tuition per Semester (₱)</label>
          <div class="input-group-wrap">
            <div class="input-group-addon"><i class="fa-solid fa-peso-sign"></i></div>
            <input type="number" class="form-field" id="addTuition" placeholder="0.00" min="0" step="0.01">
          </div>
        </div>
      </div>
      <div class="modal-footer" style="gap:.6rem;">
        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn-modal-save" id="btnSaveAdd">
          <i class="fa-solid fa-check"></i> Save Program
        </button>
      </div>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade light-modal" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Edit Program</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editId">
        <div class="mb-3">
          <label class="form-label-custom">Program Name</label>
          <input type="text" class="form-field" id="editName" placeholder="Program name">
        </div>
        <div class="mb-1">
          <label class="form-label-custom">Tuition per Semester (₱)</label>
          <div class="input-group-wrap">
            <div class="input-group-addon"><i class="fa-solid fa-peso-sign"></i></div>
            <input type="number" class="form-field" id="editTuition" placeholder="0.00" min="0" step="0.01">
          </div>
        </div>
      </div>
      <div class="modal-footer" style="gap:.6rem;">
        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn-modal-save" id="btnSaveEdit">
          <i class="fa-solid fa-check"></i> Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade light-modal" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title" style="color:#92400E;">
          <i class="fa-solid fa-triangle-exclamation" style="color:#F59E0B;"></i> Confirm Delete
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="background:#FFFBEB;">
        <input type="hidden" id="deleteId">
        <div class="delete-warning">
          <div class="warn-icon"><i class="fa-solid fa-trash"></i></div>
          <p>Are you sure you want to delete <strong id="deleteName"></strong>? This action cannot be undone.</p>
        </div>
      </div>
      <div class="modal-footer" style="justify-content:center;gap:.6rem;background:#FFFBEB;border-top-color:#FEF3C7;">
        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn-modal-delete" id="btnConfirmDelete">
          <i class="fa-solid fa-trash"></i> Delete
        </button>
      </div>
    </div>
  </div>
</div><!-- /.dashboard-wrap -->
<div class="toast-wrap" id="toastWrap"></div>
</div><!-- /.page-body -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const PAGE_URL = window.location.href.split('?')[0];

  const addModalEl    = document.getElementById('addModal');
  const editModalEl   = document.getElementById('editModal');
  const deleteModalEl = document.getElementById('deleteModal');

  const modals = {
    add:    new bootstrap.Modal(addModalEl,    { backdrop: 'static', keyboard: false }),
    edit:   new bootstrap.Modal(editModalEl,   { backdrop: 'static', keyboard: false }),
    delete: new bootstrap.Modal(deleteModalEl, { backdrop: 'static', keyboard: false })
  };

  let reloadAfterAdd  = false;
  let reloadAfterEdit = false;
  let pendingDeleteId = null;

  addModalEl.addEventListener('hidden.bs.modal', () => {
    if (reloadAfterAdd) { reloadAfterAdd = false; location.reload(); }
  });
  editModalEl.addEventListener('hidden.bs.modal', () => {
    if (reloadAfterEdit) { reloadAfterEdit = false; location.reload(); }
  });
  deleteModalEl.addEventListener('hidden.bs.modal', () => {
    if (pendingDeleteId !== null) {
      const id  = pendingDeleteId;
      pendingDeleteId = null;
      // Remove from desktop table
      const row = document.querySelector('#tableBody tr[data-id="' + id + '"]');
      if (row) row.remove();
      // Remove from mobile card list
      const card = document.querySelector('#mobileCardList [data-id="' + id + '"]');
      if (card) card.remove();
      const remaining = document.querySelectorAll('#tableBody tr[data-id]').length;
      document.getElementById('countBadge').textContent = remaining;
      if (remaining === 0) {
        document.getElementById('tableBody').innerHTML =
          '<tr id="emptyRow"><td colspan="4"><div class="empty-state"><i class="fa-solid fa-folder-open"></i><p>No programs found.</p></div></td></tr>';
        document.getElementById('mobileCardList').innerHTML =
          '<div class="empty-state"><i class="fa-solid fa-folder-open"></i><p>No programs found.</p></div>';
      }
    }
  });

  document.getElementById('btnSaveAdd').addEventListener('click', submitAdd);
  document.getElementById('btnSaveEdit').addEventListener('click', submitEdit);
  document.getElementById('btnConfirmDelete').addEventListener('click', submitDelete);

  function showToast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = 'toast-msg ' + type;
    el.innerHTML = '<i class="fa-solid fa-' + (type === 'success' ? 'circle-check' : 'circle-xmark') + '"></i> ' + msg;
    document.getElementById('toastWrap').appendChild(el);
    setTimeout(() => el.remove(), 3500);
  }

  async function apiCall(payload) {
    const res = await fetch(PAGE_URL, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    });
    return res.json();
  }

  // Live search
  let searchTimer = null;
  function liveSearch(query) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
      const q = query.trim();
      const newUrl = PAGE_URL + (q ? '?search=' + encodeURIComponent(q) : '');
      window.history.replaceState({}, '', newUrl);
      try {
        const res = await fetch(PAGE_URL + '?search=' + encodeURIComponent(q));
        const html = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        document.getElementById('tableBody').innerHTML     = doc.getElementById('tableBody').innerHTML;
        document.getElementById('mobileCardList').innerHTML = doc.getElementById('mobileCardList').innerHTML;
        document.getElementById('paginationWrap').innerHTML = doc.getElementById('paginationWrap').innerHTML;
        document.getElementById('countBadge').textContent  = doc.getElementById('countBadge').textContent;
      } catch (e) {
        showToast('Search failed. Please try again.', 'error');
      }
    }, 300);
  }

  function openAddModal() {
    document.getElementById('addName').value    = '';
    document.getElementById('addTuition').value = '';
    modals.add.show();
  }

  async function submitAdd() {
    const name    = document.getElementById('addName').value.trim();
    const tuition = parseFloat(document.getElementById('addTuition').value) || 0;
    if (!name) { showToast('Program name is required.', 'error'); return; }
    const btn = document.getElementById('btnSaveAdd');
    btn.disabled = true;
    try {
      const data = await apiCall({ action: 'create', program_name: name, tuition });
      if (data.success) {
        showToast('Program added successfully.');
        reloadAfterAdd = true;
        modals.add.hide();
      } else {
        showToast(data.message || 'Error adding program.', 'error');
        btn.disabled = false;
      }
    } catch (e) {
      showToast('Network error. Please try again.', 'error');
      btn.disabled = false;
    }
  }

  // openEditModal works for both table rows and mobile cards
  function openEditModal(btn) {
    const row = btn.closest('[data-id]');
    document.getElementById('editId').value      = row.dataset.id;
    document.getElementById('editName').value    = row.dataset.name;
    document.getElementById('editTuition').value = row.dataset.tuition;
    modals.edit.show();
  }

  async function submitEdit() {
    const id      = parseInt(document.getElementById('editId').value);
    const name    = document.getElementById('editName').value.trim();
    const tuition = parseFloat(document.getElementById('editTuition').value) || 0;
    if (!name) { showToast('Program name is required.', 'error'); return; }
    const btn = document.getElementById('btnSaveEdit');
    btn.disabled = true;
    try {
      const data = await apiCall({ action: 'update', program_id: id, program_name: name, tuition });
      if (data.success) {
        showToast('Program updated successfully.');
        reloadAfterEdit = true;
        modals.edit.hide();
      } else {
        showToast(data.message || 'Error updating program.', 'error');
        btn.disabled = false;
      }
    } catch (e) {
      showToast('Network error. Please try again.', 'error');
      btn.disabled = false;
    }
  }

  function openDeleteModal(btn) {
    const row = btn.closest('[data-id]');
    document.getElementById('deleteId').value         = row.dataset.id;
    document.getElementById('deleteName').textContent = row.dataset.name;
    modals.delete.show();
  }

  async function submitDelete() {
    const id  = parseInt(document.getElementById('deleteId').value);
    const btn = document.getElementById('btnConfirmDelete');
    btn.disabled = true;
    try {
      const data = await apiCall({ action: 'delete', program_id: id });
      if (data.success) {
        showToast('Program deleted.');
        pendingDeleteId = id;
        modals.delete.hide();
      } else {
        showToast(data.message || 'Error deleting program.', 'error');
        btn.disabled = false;
      }
    } catch (e) {
      showToast('Network error. Please try again.', 'error');
      btn.disabled = false;
    }
  }

  document.addEventListener('keydown', e => {
    if (e.key !== 'Enter') return;
    if (addModalEl.classList.contains('show'))       submitAdd();
    else if (editModalEl.classList.contains('show')) submitEdit();
  });
</script>
<?php include '../layout/footer.php'?>
</body>
</html>