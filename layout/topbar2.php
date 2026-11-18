<?php
// ✅ Logout MUST be first — before ANY HTML output
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

    // Redirect to login page
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
  }

  /* ── NAVBAR BASE ── */
  .navbar {
    background: linear-gradient(90deg, var(--deep) 0%, var(--navy) 100%);
    border-bottom: 1px solid rgba(184,150,62,0.2);
    padding: 0.6rem 0;
    font-family: 'DM Sans', sans-serif;
    position: relative;
  }

  .navbar::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0;
    width: 100%; height: 2px;
    background: linear-gradient(to right, transparent, var(--gold), transparent);
    opacity: 0.5;
  }

  /* ── BRAND ── */
  .navbar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
  }

  .brand-logo-wrap {
    position: relative;
    width: 44px; height: 44px;
    flex-shrink: 0;
  }

  .brand-logo-wrap img {
    width: 44px; height: 44px;
    border-radius: 50%;
    border: 2px solid var(--gold);
    display: block;
    object-fit: cover;
  }

  .brand-logo-wrap::after {
    content: '';
    position: absolute;
    inset: -3px;
    border-radius: 50%;
    border: 1px solid rgba(184,150,62,0.3);
  }

  .brand-text { display: flex; flex-direction: column; line-height: 1.1; }

  .brand-text .brand-name {
    font-family: 'Playfair Display', serif;
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.01em;
  }

  .brand-text .brand-sub {
    font-size: 0.65rem;
    font-weight: 500;
    color: var(--gold-light);
    letter-spacing: 0.12em;
    text-transform: uppercase;
  }

  /* ── DESKTOP NAV LINKS ── */
  .navbar-nav .nav-link {
    font-family: 'DM Sans', sans-serif;
    font-size: 0.83rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.65) !important;
    padding: 0.5rem 1rem !important;
    border-radius: 4px;
    transition: color 0.2s, background 0.2s;
    display: flex;
    align-items: center;
    gap: 0.4rem;
  }

  .navbar-nav .nav-link:hover {
    color: var(--gold-light) !important;
    background: var(--gold-pale);
  }

  .navbar-nav .nav-link i { font-size: 0.9rem; }

  /* Logout link distinct style */
  .nav-link-logout {
    color: rgba(255,255,255,0.45) !important;
    border: 1px solid rgba(255,255,255,0.1);
  }

  .nav-link-logout:hover {
    color: #ff8080 !important;
    background: rgba(155,41,41,0.15) !important;
    border-color: rgba(155,41,41,0.3);
  }

  /* Vertical separator */
  .nav-sep {
    width: 1px;
    height: 18px;
    background: rgba(255,255,255,0.12);
    margin: 0 0.25rem;
    align-self: center;
  }

  /* SY Badge */
  .portal-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(184,150,62,0.1);
    border: 1px solid rgba(184,150,62,0.25);
    border-radius: 4px;
    padding: 0.3rem 0.75rem;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--gold-light);
    margin-left: 1rem;
  }

  .portal-badge i { font-size: 0.75rem; }

  /* ── HAMBURGER ── */
  .navbar-toggler {
    border: 1px solid rgba(184,150,62,0.35) !important;
    border-radius: 6px !important;
    padding: 0.35rem 0.6rem;
    transition: background 0.2s;
  }

  .navbar-toggler:hover { background: var(--gold-pale); }
  .navbar-toggler-icon { filter: invert(1) brightness(0.85); }

  /* ── OFFCANVAS ── */
  .offcanvas {
    width: 78% !important;
    max-width: 320px;
    border-left: 1px solid rgba(184,150,62,0.2) !important;
  }

  .offcanvas-header {
    background: linear-gradient(135deg, var(--deep), var(--navy));
    border-bottom: 1px solid rgba(184,150,62,0.2);
    padding: 1.25rem 1.5rem;
  }

  .offcanvas-header .offcanvas-school {
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .offcanvas-header img {
    width: 38px; height: 38px;
    border-radius: 50%;
    border: 2px solid var(--gold);
  }

  .offcanvas-header .off-name {
    font-family: 'Playfair Display', serif;
    font-size: 0.9rem;
    color: #fff;
    font-weight: 700;
    line-height: 1.2;
  }

  .offcanvas-header .off-sub {
    font-size: 0.65rem;
    color: var(--gold-light);
    letter-spacing: 0.1em;
    text-transform: uppercase;
  }

  .offcanvas-body {
    background: var(--cream);
    padding: 1.5rem 1.25rem;
    display: flex;
    flex-direction: column;
  }

  .offcanvas-body .nav-section-label {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: #bbb;
    margin-bottom: 0.5rem;
    padding-left: 0.5rem;
  }

  .offcanvas-body .nav-link {
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--deep) !important;
    padding: 0.7rem 0.75rem !important;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    transition: background 0.2s, color 0.2s;
    margin-bottom: 0.25rem;
    text-transform: none;
    letter-spacing: normal;
    border: none;
  }

  .offcanvas-body .nav-link i {
    font-size: 1rem;
    color: var(--gold);
    width: 20px;
    text-align: center;
  }

  .offcanvas-body .nav-link:hover {
    background: rgba(184,150,62,0.1);
    color: var(--maroon-mid) !important;
  }

  .offcanvas-body .nav-link:hover i { color: var(--maroon-mid); }

  /* Logout item in offcanvas */
  .offcanvas-body .nav-link-logout {
    color: #999 !important;
    margin-top: 0.5rem;
    border-top: 1px solid #E8E3DB;
    padding-top: 1rem !important;
    border-radius: 0;
  }

  .offcanvas-body .nav-link-logout i { color: #ccc; }

  .offcanvas-body .nav-link-logout:hover {
    background: rgba(155,41,41,0.08) !important;
    color: var(--maroon-mid) !important;
  }

  .offcanvas-body .nav-link-logout:hover i { color: var(--maroon-mid); }

  .offcanvas-footer {
    padding: 1rem 0.25rem 0;
    border-top: 1px solid #E8E3DB;
    font-size: 0.7rem;
    color: #bbb;
    text-align: center;
    margin-top: auto;
  }
</style>

<!-- ═══════════════════════════════════
     NAVBAR
════════════════════════════════════ -->
<nav class="navbar navbar-expand-lg shadow-sm fixed-top">
  <div class="container-fluid px-3 px-lg-4">

    <!-- Brand -->
    <a class="navbar-brand" href="#">
      <div class="brand-logo-wrap">
        <img src="../img/logo.png" alt="NCC Logo">
      </div>
      <div class="brand-text">
        <span class="brand-name">NCC Admission Portal</span>
        <span class="brand-sub">Northeastern Cebu Colleges</span>
      </div>
    </a>

    <!-- Hamburger (mobile) -->
    <button class="navbar-toggler border-0 d-lg-none"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#sidebarMenu"
            aria-label="Open menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Desktop Links -->
    <div class="collapse navbar-collapse d-none d-lg-flex justify-content-end align-items-center">
      <ul class="navbar-nav align-items-center">

        <div class="nav-sep"></div>

        <li class="nav-item">
          <a class="nav-link nav-link-logout" href="?action=logout">
            <i class="bi bi-box-arrow-right"></i> Logout
          </a>
        </li>

      </ul>
    </div>

  </div>
</nav>

<!-- ═══════════════════════════════════
     MOBILE OFFCANVAS SIDEBAR
════════════════════════════════════ -->
<div class="offcanvas offcanvas-end d-lg-none" tabindex="-1" id="sidebarMenu">

  <div class="offcanvas-header">
    <div class="offcanvas-school">
      <img src="../img/logo.png" alt="NCC Logo">
      <div>
        <div class="off-name">NCC Admission</div>
        <div class="off-sub">Portal</div>
      </div>
    </div>
    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas"></button>
  </div>

  <div class="offcanvas-body">
    <p class="nav-section-label">Navigation</p>
    <ul class="navbar-nav flex-column">


      <li class="nav-item">
        <a class="nav-link nav-link-logout" href="?action=logout">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
      </li>

    </ul>

    <div class="offcanvas-footer">
      &copy; <?= date('Y') ?> Northeastern Cebu Colleges, Inc.
    </div>
  </div>

</div>