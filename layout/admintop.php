<?php
// ✅ Logout MUST run before ANY HTML output
if (isset($_GET['action']) && $_GET['action'] === 'logout') {

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Clear session
    $_SESSION = [];

    // Destroy session
    session_destroy();

    // Remove session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Prevent browser caching
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Redirect to login
    header("Location: ../pages/log_in.php");
    exit();
}
?>

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<style>
  :root {
    --gold:       #B8963E;
    --gold-light: #D4AF68;
    --gold-pale:  rgba(184,150,62,0.12);
    --deep:       #1A1A2E;
    --navy:       #16213E;
    --maroon:     #7B1C1C;
    --maroon-mid: #9B2929;
    --cream:      #FAF7F2;
    --sidebar-w:  260px;
  }

  /* ── TOP NAVBAR ── */
  .admin-navbar {
    background: linear-gradient(90deg, var(--deep) 0%, var(--navy) 100%);
    border-bottom: 1px solid rgba(184,150,62,0.2);
    padding: 0.6rem 1rem;
    font-family: 'DM Sans', sans-serif;
    position: relative;
  }

  .admin-navbar::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0;
    width: 100%; height: 2px;
    background: linear-gradient(to right, transparent, var(--gold), transparent);
    opacity: 0.5;
  }

  /* Sidebar toggle button */
  .btn-sidebar-toggle {
    background: rgba(184,150,62,0.1);
    border: 1px solid rgba(184,150,62,0.3);
    border-radius: 6px;
    color: var(--gold-light);
    padding: 0.4rem 0.7rem;
    font-size: 0.9rem;
    transition: background 0.2s, color 0.2s;
    line-height: 1;
  }

  .btn-sidebar-toggle:hover {
    background: rgba(184,150,62,0.22);
    color: #fff;
  }

  /* Brand */
  .admin-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
  }

  .admin-brand-logo-wrap {
    position: relative;
    width: 40px; height: 40px;
    flex-shrink: 0;
  }

  .admin-brand-logo-wrap img {
    width: 40px; height: 40px;
    border-radius: 50%;
    border: 2px solid var(--gold);
    object-fit: cover;
    display: block;
  }

  .admin-brand-logo-wrap::after {
    content: '';
    position: absolute;
    inset: -3px;
    border-radius: 50%;
    border: 1px solid rgba(184,150,62,0.3);
  }

  .admin-brand-text { display: flex; flex-direction: column; line-height: 1.15; }

  .admin-brand-text .brand-name {
    font-family: 'Playfair Display', serif;
    font-size: 0.95rem;
    font-weight: 700;
    color: #fff;
  }

  .admin-brand-text .brand-role {
    font-size: 0.62rem;
    font-weight: 600;
    color: var(--gold-light);
    letter-spacing: 0.15em;
    text-transform: uppercase;
  }

  /* Admin badge */
  .admin-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(155,41,41,0.15);
    border: 1px solid rgba(155,41,41,0.3);
    border-radius: 4px;
    padding: 0.28rem 0.75rem;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #ff9999;
    margin-left: auto;
  }

  .admin-badge i { font-size: 0.75rem; }

  /* ── OFFCANVAS SIDEBAR ── */
  #offcanvasSidebar {
    width: var(--sidebar-w) !important;
    background: var(--deep) !important;
    border-right: 1px solid rgba(184,150,62,0.15) !important;
    font-family: 'DM Sans', sans-serif;
  }

  .sidebar-header {
    background: linear-gradient(135deg, var(--deep), var(--navy));
    border-bottom: 1px solid rgba(184,150,62,0.2);
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .sidebar-header img {
    width: 40px; height: 40px;
    border-radius: 50%;
    border: 2px solid var(--gold);
    object-fit: cover;
  }

  .sidebar-header .sh-title {
    font-family: 'Playfair Display', serif;
    font-size: 0.95rem;
    color: #fff;
    font-weight: 700;
    line-height: 1.2;
  }

  .sidebar-header .sh-sub {
    font-size: 0.62rem;
    color: var(--gold-light);
    letter-spacing: 0.12em;
    text-transform: uppercase;
  }

  /* Nav section label */
  .sidebar-section-label {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.25);
    padding: 1.25rem 1.25rem 0.4rem;
  }

  /* Nav items */
  .sidebar-nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.25rem;
    color: rgba(255,255,255,0.55);
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: color 0.2s, background 0.2s, border-color 0.2s;
    margin: 0.1rem 0;
  }

  .sidebar-nav-item i {
    font-size: 0.95rem;
    color: rgba(184,150,62,0.5);
    width: 18px;
    text-align: center;
    transition: color 0.2s;
  }

  .sidebar-nav-item:hover {
    color: #fff;
    background: rgba(184,150,62,0.08);
    border-left-color: var(--gold);
  }

  .sidebar-nav-item:hover i { color: var(--gold-light); }

  /* Active state */
  .sidebar-nav-item.active {
    color: #fff;
    background: rgba(184,150,62,0.1);
    border-left-color: var(--gold);
    font-weight: 600;
  }

  .sidebar-nav-item.active i { color: var(--gold); }

  /* Sidebar divider */
  .sidebar-divider {
    height: 1px;
    background: rgba(255,255,255,0.06);
    margin: 0.75rem 1.25rem;
  }

  /* Sidebar footer */
  .sidebar-footer {
    padding: 1rem 1.25rem;
    border-top: 1px solid rgba(255,255,255,0.06);
    font-size: 0.68rem;
    color: rgba(255,255,255,0.2);
    letter-spacing: 0.05em;
    margin-top: auto;
  }

  /* Logout link in sidebar */
  .sidebar-logout {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.7rem 1.25rem;
    color: rgba(255,120,120,0.55);
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.2s, background 0.2s;
    border-left: 3px solid transparent;
  }

  .sidebar-logout i { font-size: 0.95rem; width: 18px; text-align: center; }

  .sidebar-logout:hover {
    color: #ff9999;
    background: rgba(155,41,41,0.12);
    border-left-color: var(--maroon-mid);
  }
</style>

<!-- ═══════════════════════════════════
     TOP NAVBAR
════════════════════════════════════ -->
<nav class="navbar admin-navbar shadow-sm fixed-top">
 <div class="container-fluid d-flex align-items-center">
	
     <div class="d-flex align-items-center gap-2">

    <!-- Sidebar toggle -->
    <button class="btn-sidebar-toggle"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#offcanvasSidebar"
            aria-controls="offcanvasSidebar"
            aria-label="Open sidebar">
      <i class="fa fa-bars"></i>
    </button>

    <!-- Brand -->
    <a class="admin-brand" href="#">
      <div class="admin-brand-logo-wrap">
        <img src="../img/logo.png" alt="NCC Logo">
      </div>
      <div class="admin-brand-text">
        <span class="brand-name">NCC Admission Portal</span>
        <span class="brand-role">Administrator</span>
      </div>
    </a>

  </div>
    </div>
</nav>

<!-- ═══════════════════════════════════
     OFFCANVAS SIDEBAR
════════════════════════════════════ -->
<div class="offcanvas offcanvas-start p-0"
     tabindex="-1"
     id="offcanvasSidebar"
     aria-labelledby="offcanvasSidebarLabel">

  <!-- Header -->
  <div class="sidebar-header">
    <img src="../img/logo.png" alt="NCC Logo">
    <div>
      <div class="sh-title">Admin Panel</div>
      <div class="sh-sub">NCC Admission</div>
    </div>
    <button type="button"
            class="btn-close btn-close-white ms-auto"
            data-bs-dismiss="offcanvas"
            aria-label="Close"></button>
  </div>

  <!-- Nav body -->
  <div class="offcanvas-body p-0 d-flex flex-column">

    <p class="sidebar-section-label" style="padding-top:0.5rem;">Admin</p>
      
          <a href="../admin/profile.php" class="sidebar-nav-item">
      <i class="fa-solid fa-user-circle"></i> Profile
    </a>
    <div class="sidebar-divider"></div>
      
    <p class="sidebar-section-label">Main Menu</p>

    <a href="../admin/admin_dashboard.php" class="sidebar-nav-item">
      <i class="fa fa-chart-line"></i> Dashboard
    </a>

    <a href="../admin/view_program.php" class="sidebar-nav-item">
      <i class="fa fa-book"></i> Programs
    </a>
      
     <a href="../admin/applicants.php" class="sidebar-nav-item">
      <i class="fa-solid fa-address-card"></i> Applicants
    </a>

     <a href="../admin/schedule.php" class="sidebar-nav-item">
      <i class="fa-solid fa-calendar-days"></i> Schedules
    </a>

      
    <div class="sidebar-divider"></div>

    <p class="sidebar-section-label" style="padding-top:0.5rem;">Account</p>

    <a href="?action=logout" class="sidebar-logout">
      <i class="fa fa-right-from-bracket"></i> Logout
    </a>

    <div class="sidebar-footer mt-auto">
      &copy; <?= date('Y') ?> Northeastern Cebu Colleges, Inc.
    </div>

  </div>
</div>