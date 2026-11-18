<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admission Portal Navbar</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Google Fonts (matching login page) -->
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
        position: fixed; /* or sticky */
  top: 0;
  left: 0;
  right: 0;
  z-index: 1050;
    }

    /* Subtle gold bottom accent line */
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

    /* Subtle glow ring on logo */
    .brand-logo-wrap::after {
      content: '';
      position: absolute;
      inset: -3px;
      border-radius: 50%;
      border: 1px solid rgba(184,150,62,0.3);
    }

    .brand-text {
      display: flex;
      flex-direction: column;
      line-height: 1.1;
    }

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

    .navbar-nav .nav-link i {
      font-size: 0.9rem;
    }

    /* Vertical separator between links */
    .nav-sep {
      width: 1px;
      height: 18px;
      background: rgba(255,255,255,0.12);
      margin: 0 0.25rem;
      align-self: center;
    }

    /* ── PORTAL YEAR BADGE (desktop only) ── */
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

    .navbar-toggler-icon {
      filter: invert(1) brightness(0.85);
    }

    /* ── OFFCANVAS (mobile sidebar) ── */
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

    /* Footer inside offcanvas */
    .offcanvas-footer {
      padding: 1rem 1.25rem;
      border-top: 1px solid #E8E3DB;
      font-size: 0.7rem;
      color: #bbb;
      text-align: center;
    }
  </style>
</head>
<body>

<!-- ═══════════════════════════════════
     NAVBAR
════════════════════════════════════ -->
<nav class="navbar navbar-expand-lg shadow-sm fixed-top">
  <div class="container-fluid px-3 px-lg-4">
   

    <!-- ── Brand ── -->
    <a class="navbar-brand" href="#">
      <div class="brand-logo-wrap">
        <img src="../img/logo.png" alt="NCC Logo">
      </div>
      <div class="brand-text">
        <span class="brand-name">NCC Admission Portal</span>
        <span class="brand-sub">Northeastern Cebu Colleges</span>
      </div>
    </a>

    <!-- ── Hamburger (mobile) ── -->
    <button class="navbar-toggler border-0 d-lg-none"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#offcanvasNavbar"
            aria-label="Open menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- ── Desktop Links ── -->
    <div class="collapse navbar-collapse d-none d-lg-flex justify-content-end align-items-center">
      <ul class="navbar-nav align-items-center">

        <li class="nav-item">
          <a class="nav-link" href="../pages/log_in.php">
            <i class="bi bi-house-door-fill"></i> Home
          </a>
        </li>

        <div class="nav-sep"></div>

        <li class="nav-item">
          <a class="nav-link" href="https://www.facebook.com/profile.php?id=61576786349238" target="_blank" rel="noopener">
            <i class="bi bi-chat-dots-fill"></i> Contact
          </a>
        </li>
      
       <div class="nav-sep"></div>

        <li class="nav-item">
          <a class="nav-link" href="../pages/about.php" target="_blank" rel="noopener">
            <i class="bi bi-info-circle"></i> About
          </a>
        </li>

      </ul>

     

  </div>
</nav>

<!-- ═══════════════════════════════════
     MOBILE OFFCANVAS SIDEBAR
════════════════════════════════════ -->
<div class="offcanvas offcanvas-end d-lg-none" tabindex="-1" id="offcanvasNavbar">

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

  <div class="offcanvas-body d-flex flex-column">
    <p class="nav-section-label">Navigation</p>
    <ul class="navbar-nav flex-column">
      <li class="nav-item">
        <a class="nav-link" href="../pages/log_in.php">
          <i class="bi bi-house-door-fill"></i> Home
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="https://www.facebook.com/profile.php?id=61576786349238" target="_blank" rel="noopener">
          <i class="bi bi-chat-dots-fill"></i> Contact
        </a>
      </li>
        <li class="nav-item">
        <a class="nav-link" href="../pages/about.php" target="_blank" rel="noopener">
          <i class="bi bi-info-circle"></i> About
        </a>
      </li>
    </ul>

    <div class="mt-auto offcanvas-footer">
      &copy; <?= date('Y') ?> Northeastern Cebu Colleges, Inc.
    </div>
  </div>

</div>

<!-- Spacing for fixed navbar -->
<div style="height: 70px;"></div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Tooltips -->
<script>
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  })
</script>

</body>
</html>