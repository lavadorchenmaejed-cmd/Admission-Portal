<?php
session_start();

// ── CSRF token generation ──────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Brute-force / rate-limit config ───────────────────────────────────────
define('MAX_ATTEMPTS',   5);
define('LOCKOUT_SECONDS', 300);

$ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$key = 'login_attempts_' . md5($ip);

if (!isset($_SESSION[$key])) {
    $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
}

$attempts = &$_SESSION[$key];

if ((time() - $attempts['first_attempt']) > LOCKOUT_SECONDS) {
    $attempts = ['count' => 0, 'first_attempt' => time()];
}

$isLockedOut   = $attempts['count'] >= MAX_ATTEMPTS;
$remainingTime = LOCKOUT_SECONDS - (time() - $attempts['first_attempt']);

$showAnnouncementModal = !isset($_SESSION['alert']);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../Model/user.php';
require_once __DIR__ . '/../Controller/userController.php';

$config = require __DIR__ . '/../config.php';
$db     = new Database($config);
$conn   = $db->getConnection();
$userController = new UserController(new User($conn));

// ── Fetch current school year and deadline from DB ─────────────────────────
$schoolYearLabel = 'N/A';
$deadlineDisplay = 'N/A';
$deadlineValue   = '';

$syStmt = $conn->prepare("SELECT school_year, deadline FROM school_year ORDER BY year_id DESC LIMIT 1");
$syStmt->execute();
$syRow = $syStmt->get_result()->fetch_assoc();
$syStmt->close();

if ($syRow) {
    $schoolYearLabel = htmlspecialchars($syRow['school_year']);
    if (!empty($syRow['deadline'])) {
        $deadlineValue   = $syRow['deadline'];
        $deadlineDisplay = date('F d, Y', strtotime($syRow['deadline']));
    }
}

// ──────────────────────────────────────────────────────────────────────────
$pattern = '/^[A-Za-z0-9_-]+$/';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ── CSRF validation ───────────────────────────────────────────────────
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $_SESSION['alert'] = 'invalid_request';
        header('Location: log_in.php');
        exit();
    }

    // ── Lockout check ─────────────────────────────────────────────────────
    if ($isLockedOut) {
        $_SESSION['alert'] = 'locked_out';
        header('Location: log_in.php');
        exit();
    }

    // ── Input sanitisation ────────────────────────────────────────────────
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (strlen($username) < 3 || strlen($username) > 60) {
        $_SESSION['alert'] = 'invalid_credentials';
        header('Location: log_in.php');
        exit();
    }

    if (strlen($password) < 1 || strlen($password) > 255) {
        $_SESSION['alert'] = 'invalid_credentials';
        header('Location: log_in.php');
        exit();
    }

    if (!preg_match($pattern, $username)) {
        $_SESSION['alert'] = 'invalid_username_format';
        header('Location: log_in.php');
        exit();
    }

    // ── Attempt login ─────────────────────────────────────────────────────
    $user = $userController->login($username, $password);

    if ($user) {
        $attempts = ['count' => 0, 'first_attempt' => time()];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $userId = $user['user_Id'] ?? $user['user_id'] ?? null;
        $role   = strtolower(trim($user['role'] ?? ''));

        $_SESSION['user_Id']  = $userId;
        $_SESSION['username'] = $user['username'] ?? '';
        $_SESSION['role']     = $role;

 $base = rtrim(dirname($_SERVER['PHP_SELF']), '/pages');

if ($role === 'admin') {
    header("Location: " . $base . "../admin/admin_dashboard.php");
    exit();
}

if ($role === 'applicant') {
    header("Location: " . $base . "../pages/admission_portal.php");
    exit();
}

// fallback safety
session_destroy();
header("Location: ../pages/log_in.php");
exit();
    }

    $attempts['count']++;
    $_SESSION['alert'] = ($attempts['count'] >= MAX_ATTEMPTS) ? 'locked_out' : 'invalid_credentials';
    header("Location: log_in.php");
    exit();
}

require_once __DIR__ . '/../layout/topbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admission Portal — NCC</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../css/colors.css" rel="stylesheet">

    <style>
        :root {
            --gold: #B8963E;
            --gold-light: #D4AF68;
            --gold-pale: #F5EDD8;
            --deep: #1A1A2E;
            --navy: #16213E;
            --maroon: #7B1C1C;
            --maroon-mid: #9B2929;
            --cream: #FAF7F2;
            --line: rgba(184,150,62,0.25);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            background: var(--deep);
            overflow-x: hidden;
        }

        .page-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
        }

        .left-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem 3.5rem;
            background: var(--navy);
            overflow: hidden;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('../img/school.png') center/cover no-repeat;
            opacity: 0.12;
        }

        .left-panel::after {
            content: '';
            position: absolute;
            top: 0; right: -60px;
            width: 120px;
            height: 100%;
            background: linear-gradient(to bottom right, var(--gold) 0%, transparent 60%);
            opacity: 0.08;
            transform: skewX(-8deg);
        }

        .left-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .school-seal {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 2px solid var(--gold);
            padding: 6px;
            margin-bottom: 2rem;
            box-shadow: 0 0 40px rgba(184,150,62,0.2);
            animation: sealGlow 3s ease-in-out infinite alternate;
        }

        @keyframes sealGlow {
            from { box-shadow: 0 0 20px rgba(184,150,62,0.15); }
            to   { box-shadow: 0 0 50px rgba(184,150,62,0.35); }
        }

        .school-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.65rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.25;
            margin-bottom: 0.5rem;
            letter-spacing: 0.01em;
        }

        .school-address {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.45);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 2.5rem;
        }

        .gold-rule {
            width: 60px;
            height: 2px;
            background: linear-gradient(to right, transparent, var(--gold), transparent);
            margin: 0 auto 2.5rem;
        }

        .portal-label {
            display: inline-block;
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--gold-light);
            border: 1px solid var(--line);
            border-radius: 2px;
            padding: 0.35rem 1rem;
            margin-bottom: 1.25rem;
        }

        .portal-heading {
            font-family: 'Playfair Display', serif;
            font-size: 2.4rem;
            font-weight: 600;
            color: #fff;
            line-height: 1.15;
            margin-bottom: 1rem;
        }

        .portal-sub {
            font-size: 0.88rem;
            color: rgba(255,255,255,0.4);
            line-height: 1.7;
            max-width: 300px;
            margin: 0 auto;
        }

        .info-pills {
            display: flex;
            gap: 1rem;
            margin-top: 3rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .info-pill {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--line);
            border-radius: 50px;
            padding: 0.45rem 1rem;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.55);
        }

        .info-pill i { color: var(--gold); font-size: 0.85rem; }

        .right-panel {
            background: var(--cream);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem 3.5rem;
            position: relative;
        }

        .right-panel::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--gold), var(--maroon));
        }

        .login-box { width: 100%; max-width: 400px; }

        .login-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--deep);
            margin-bottom: 0.35rem;
        }

        .login-subtitle {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 2.5rem;
            font-weight: 400;
        }

        .step-bar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2.5rem;
        }

        .step-dot {
            width: 28px; height: 28px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .step-dot.active  { background: var(--gold); color: #fff; }
        .step-dot.inactive { background: #E8E4DF; color: #aaa; }

        .step-line { flex: 1; height: 1px; background: #E0DAD2; }

        .step-text { font-size: 0.7rem; color: #aaa; margin-left: 0.25rem; }

        .field-group { margin-bottom: 1.4rem; }

        .field-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 0.55rem;
        }

        .field-wrap { position: relative; }

        .field-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold);
            font-size: 0.95rem;
            pointer-events: none;
        }

        .field-input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            border: 1.5px solid #DDD8D0;
            border-radius: 6px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            color: var(--deep);
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            appearance: none;
        }

        .field-input::placeholder { color: #BDB8B0; }
        .field-input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(184,150,62,0.12);
        }

        .toggle-pw {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #bbb;
            font-size: 0.95rem;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            transition: color 0.2s;
        }
		



        .toggle-pw:hover { color: var(--gold); }

        /* ── CAPTCHA styles ── */
        .captcha-group {
            margin-bottom: 1.4rem;
            background: #fff;
            border: 1.5px solid #DDD8D0;
            border-radius: 8px;
            padding: 0.9rem 1rem 0.75rem;
        }

        .captcha-canvas-wrap {
            position: relative;
            margin-bottom: 0.6rem;
        }

        #captcha-canvas {
            display: block;
            width: 100%;
            height: 72px;
            border: 1px solid #E8E4DD;
            border-radius: 6px;
            background: #f4f3f0;
        }

        #captcha-refresh {
            position: absolute;
            top: 5px; right: 7px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 17px;
            color: #aaa;
            padding: 0;
            line-height: 1;
            transition: color 0.2s;
        }

        #captcha-refresh:hover { color: var(--gold); }

        .captcha-input-wrap { position: relative; }

        #captcha-input {
            width: 100%;
            padding: 0.7rem 1rem 0.7rem 2.6rem;
            border: 1.5px solid #DDD8D0;
            border-radius: 6px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--deep);
            background: #fff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        #captcha-input::placeholder { letter-spacing: 0; text-transform: none; color: #BDB8B0; }
        #captcha-input:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(184,150,62,0.12); }
        #captcha-input.is-invalid { border-color: #e24b4a; }

        .captcha-icon {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold);
            font-size: 0.9rem;
            pointer-events: none;
        }

        .captcha-hint {
            font-size: 0.68rem;
            color: #aaa;
            margin-top: 0.4rem;
        }

        #captcha-error {
            font-size: 0.72rem;
            color: #e24b4a;
            margin-top: 0.35rem;
            display: none;
        }

        /* ── end CAPTCHA styles ── */

        .btn-submit {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, var(--maroon-mid), var(--maroon));
            color: #fff;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s, background 0.2s;
            margin-top: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, var(--maroon), #5c1111);
            box-shadow: 0 6px 24px rgba(123,28,28,0.3);
            transform: translateY(-1px);
        }

        .btn-submit:active { transform: translateY(0); }

        .btn-submit::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0.06);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .btn-submit:hover::after { opacity: 1; }

        .links-row {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
            font-size: 0.8rem;
        }

        .links-row a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .links-row a:hover { color: var(--maroon); }

        .form-divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
            font-size: 0.75rem;
            color: #ccc;
        }

        .form-divider::before,
        .form-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #E0DAD2;
        }

        .register-cta { text-align: center; font-size: 0.82rem; color: #999; }

        .register-cta a {
            color: var(--maroon-mid);
            font-weight: 600;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s;
        }

        .register-cta a:hover { border-color: var(--maroon-mid); }

        .panel-footer {
            position: absolute;
            bottom: 1.5rem;
            font-size: 0.7rem;
            color: #C5BFBA;
            letter-spacing: 0.05em;
        }

        .modal-content { border-radius: 12px !important; overflow: hidden; }

        .ann-header {
            background: linear-gradient(135deg, var(--navy), var(--deep));
            padding: 2rem 2rem 1.5rem;
            text-align: center;
            position: relative;
        }

        .ann-header img {
            width: 64px;
            border-radius: 50%;
            border: 2px solid rgba(184,150,62,0.5);
            margin-bottom: 0.75rem;
        }

        .ann-header h5 {
            font-family: 'Playfair Display', serif;
            color: #fff;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .ann-header small { color: rgba(255,255,255,0.45); font-size: 0.75rem; }

        .ann-header .btn-close {
            position: absolute;
            top: 1rem; right: 1rem;
            filter: invert(1);
        }

        .ann-badge {
            display: inline-block;
            background: var(--gold);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            padding: 0.25rem 0.75rem;
            border-radius: 2px;
            margin-bottom: 0.5rem;
        }

        .ann-body { padding: 2rem 2rem 1.5rem; background: var(--cream); }

        .req-card {
            background: #fff;
            border: 1px solid #EAE5DD;
            border-radius: 8px;
            padding: 1.25rem 1.5rem;
            height: 100%;
        }

        .req-card h6 {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--maroon-mid);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #EAE5DD;
        }

        .req-item {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            margin-bottom: 0.65rem;
            font-size: 0.85rem;
            color: #444;
        }

        .req-item i { color: var(--gold); margin-top: 2px; flex-shrink: 0; }

        .deadline-pill {
            background: #fff;
            border: 1px solid #EAE5DD;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .deadline-icon {
            width: 40px; height: 40px;
            background: var(--gold-pale);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--gold);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .deadline-pill h6 { font-size: 0.8rem; font-weight: 700; color: var(--deep); margin-bottom: 0.1rem; }
        .deadline-pill small { color: #999; font-size: 0.75rem; }

        .ann-note {
            text-align: center;
            font-size: 0.78rem;
            color: #999;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #EAE5DD;
        }

        .attempt-warning {
            background: #FFF8EC;
            border: 1px solid #F5CBA7;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.8rem;
            color: #7D5A00;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        @media (max-width: 768px) {
            .page-wrapper { grid-template-columns: 1fr; }
            .left-panel { display: none; }
            .right-panel { padding: 2rem 1.5rem; }
            .right-panel::before { display: none; }
        }

        @media (max-width: 576px) {
  #announcementModal .modal-dialog {
    margin: 1rem;   /* adds space on left and right */
    max-width: calc(100% - 2rem); /* keeps it responsive */
  }
}

        .login-box { animation: slideUp 0.5s cubic-bezier(0.22,1,0.36,1) both; }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .left-content { animation: fadeIn 0.7s ease both 0.1s; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body class="mt-5 d-flex flex-column min-vh-100">

<!-- ═══════════════════════════════════
     ANNOUNCEMENT MODAL
════════════════════════════════════ -->
<div class="modal fade" id="announcementModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content border-0 shadow-lg">
      <div class="ann-header">
        <img src="../img/logo.png" alt="NCC Seal">
        <div class="ann-badge">Admission Announcement</div>
        <h5>Northeastern Cebu Colleges, Inc.</h5>
        <small>P.G. Almendras St., Danao City, Cebu</small>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="ann-body" style="overflow-y: auto;">
        <p style="font-size:0.82rem;color:#aaa;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;margin-bottom:1rem;">
          School Year <?php echo $schoolYearLabel; ?>
        </p>
        <div class="deadline-pill">
          <div class="deadline-icon"><i class="bi bi-calendar-event"></i></div>
          <div>
            <h6>Application Deadline</h6>
            <small>Submit your admission application before the deadline.</small>
          </div>
          <div class="ms-auto text-end">
            <?php if ($deadlineValue): ?>
              <span style="font-size:0.95rem;font-weight:700;color:var(--maroon-mid);">
                <?php echo $deadlineDisplay; ?>
              </span>
            <?php else: ?>
              <span style="font-size:0.85rem;color:#aaa;">TBA</span>
            <?php endif; ?>
          </div>
        </div>
        <p style="font-size:0.72rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#666;margin-bottom:0.85rem;">
          Admission Requirements
        </p>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="req-card">
              <h6>Incoming 1st Year</h6>
              <div class="req-item"><i class="bi bi-check2-circle"></i><span>Report Card (Original)</span></div>
              <div class="req-item"><i class="bi bi-check2-circle"></i><span>Good Moral Certificate</span></div>
              <div class="req-item"><i class="bi bi-check2-circle"></i><span>PSA Birth Certificate</span></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="req-card">
              <h6>Transferees</h6>
              <div class="req-item"><i class="bi bi-check2-circle"></i><span>Transcript of Records (TOR)</span></div>
              <div class="req-item"><i class="bi bi-check2-circle"></i><span>Good Moral Certificate</span></div>
              <div class="req-item"><i class="bi bi-check2-circle"></i><span>PSA Birth Certificate</span></div>
            </div>
          </div>
        </div>
        <p class="ann-note">
          <i class="bi bi-info-circle me-1"></i>
          Please prepare all required documents before submitting your application.
        </p>
        <div class="text-center mt-3">
          <a href="../pages/register.php" class="btn btn-sm px-4 py-2 rounded-pill fw-600"
             style="background:linear-gradient(135deg,#9B2929,#7B1C1C);color:#fff;font-family:'DM Sans',sans-serif;font-size:0.82rem;font-weight:600;text-decoration:none;">
            <i class="bi bi-pencil-square me-1"></i>Register as New Applicant
          </a>
            
            
          <div class="mt-3 fs-6 fw-bold" style="font-size:0.75rem;color:#666;">or CLOSE THIS WINDOW to Log in</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Alert modals ───────────────────────────────────────────────────────── -->
<?php
$alertType = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);
$attemptsLeft = max(0, MAX_ATTEMPTS - $attempts['count']);
?>

<?php if ($alertType === 'invalid_credentials' || $alertType === 'invalid_username_format'): ?>
<div class="modal fade" id="failModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-body text-center p-5">
        <div class="mb-3"><i class="bi bi-x-circle-fill text-danger display-3"></i></div>
        <h4 class="fw-bold text-danger mb-2">Login Failed</h4>
        <p class="text-muted mb-1">Invalid username or password. Please try again.</p>
        <?php if ($attemptsLeft <= 2 && $attemptsLeft > 0): ?>
          <p class="text-warning small mb-4">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?php echo $attemptsLeft; ?> attempt<?php echo $attemptsLeft !== 1 ? 's' : ''; ?> remaining before lockout.
          </p>
        <?php else: ?>
          <div class="mb-4"></div>
        <?php endif; ?>
        <button class="btn btn-danger px-4 rounded-pill" data-bs-dismiss="modal">Try Again</button>
      </div>
    </div>
  </div>
</div>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    new bootstrap.Modal(document.getElementById("failModal")).show();
  });
</script>

<?php elseif ($alertType === 'locked_out'): ?>
<div class="modal fade" id="lockModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-body text-center p-5">
        <div class="mb-3"><i class="bi bi-shield-lock-fill text-warning display-3"></i></div>
        <h4 class="fw-bold text-warning mb-2">Account Temporarily Locked</h4>
        <p class="text-muted mb-4">
          Too many failed login attempts. Please wait
          <strong><?php echo ceil($remainingTime / 60); ?> minute<?php echo $remainingTime > 60 ? 's' : ''; ?></strong>
          before trying again.
        </p>
        <button class="btn btn-warning px-4 rounded-pill" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    new bootstrap.Modal(document.getElementById("lockModal")).show();
  });
</script>

<?php elseif ($alertType === 'invalid_request'): ?>
<div class="modal fade" id="csrfModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-body text-center p-5">
        <div class="mb-3"><i class="bi bi-exclamation-triangle-fill text-danger display-3"></i></div>
        <h4 class="fw-bold text-danger mb-2">Invalid Request</h4>
        <p class="text-muted mb-4">Your session may have expired. Please refresh the page and try again.</p>
        <button class="btn btn-danger px-4 rounded-pill" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    new bootstrap.Modal(document.getElementById("csrfModal")).show();
  });
</script>
<?php endif; ?>

<!-- ═══════════════════════════════════
     MAIN PAGE
════════════════════════════════════ -->
<main class="flex-grow-1">
    <div class="page-wrapper">

  <!-- LEFT: Branding panel -->
  <div class="left-panel">
    <div class="left-content">
      <img src="../img/logo.png" class="school-seal" alt="NCC Seal">
      <div class="school-name">Northeastern Cebu<br>Colleges, Inc.</div>
      <div class="school-address">P.G. Almendras St. · Danao City · Cebu</div>
      <div class="gold-rule"></div>
      <div class="portal-label">Online Admission Portal</div>
      <div class="portal-heading">Begin Your<br>Academic Journey</div>
      <p class="portal-sub">
        Apply for admission for School Year <?php echo $schoolYearLabel; ?> through our secure online platform.
      </p>
      <div class="info-pills">
        <div class="info-pill"><i class="bi bi-shield-check"></i> Secure Portal</div>
        <div class="info-pill"><i class="bi bi-clock-history"></i> SY <?php echo $schoolYearLabel; ?></div>
        <div class="info-pill"><i class="bi bi-file-earmark-text"></i> Online Application</div>
      </div>
    </div>
  </div>

  <!-- RIGHT: Login form -->
  <div class="right-panel">
    <div class="login-box">

      <p class="login-title">Welcome Back!</p>
      <p class="login-subtitle">Log in to access the Admission Portal</p>

      <div class="step-bar">
        <div class="step-dot active">1</div>
        <div class="step-line"></div>
        <div class="step-dot inactive">2</div>
        <div class="step-line"></div>
        <div class="step-dot inactive">3</div>
        <span class="step-text">Login → Application → Status</span>
      </div>

      <?php if ($isLockedOut): ?>
        <div class="attempt-warning">
          <i class="bi bi-shield-lock-fill"></i>
          Too many failed attempts. Please wait <?php echo ceil($remainingTime / 60); ?> minute(s) before trying again.
        </div>
      <?php elseif ($attempts['count'] >= 3): ?>
        <div class="attempt-warning">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <?php echo MAX_ATTEMPTS - $attempts['count']; ?> attempt(s) remaining before temporary lockout.
        </div>
      <?php endif; ?>

      <form method="POST" id="loginForm" onsubmit="return validateCaptcha(event)">
        <!-- CSRF hidden field -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="field-group">
          <label class="field-label" for="username">Username</label>
          <div class="field-wrap">
            <i class="bi bi-person field-icon"></i>
            <input type="text"
                   class="field-input"
                   id="username"
                   name="username"
                   placeholder="Enter your username"
                   maxlength="60"
                   required
                   autocomplete="username"
                   <?php echo $isLockedOut ? 'disabled' : ''; ?>>
          </div>
        </div>

        <div class="field-group">
          <label class="field-label" for="password">Password</label>
          <div class="field-wrap">
            <i class="bi bi-lock field-icon"></i>
            <input type="password"
                   class="field-input"
                   id="password"
                   name="password"
                   placeholder="Enter your password"
                   maxlength="255"
                   required
                   autocomplete="current-password"
                   <?php echo $isLockedOut ? 'disabled' : ''; ?>>
            <button type="button" class="toggle-pw" id="togglePw" aria-label="Toggle password">
              <i class="bi bi-eye" id="pwIcon"></i>
            </button>
          </div>
        </div>

        <!-- ── CAPTCHA ── -->
        <div class="field-group">
          <label class="field-label">
            <i class="bi bi-shield-check me-1" style="color:var(--gold);"></i>Human Verification
          </label>
          <div class="captcha-group">
            <div class="captcha-canvas-wrap">
              <canvas id="captcha-canvas" width="340" height="72"></canvas>
              <button type="button" id="captcha-refresh" title="Generate new code" aria-label="Refresh CAPTCHA">
                &#8635;
              </button>
            </div>
            <div class="captcha-input-wrap">
              <i class="bi bi-keyboard captcha-icon"></i>
              <input type="text"
                     id="captcha-input"
                     placeholder="Type the 6 characters above"
                     maxlength="6"
                     autocomplete="off"
                     spellcheck="false"
                     <?php echo $isLockedOut ? 'disabled' : ''; ?>>
            </div>
            <div class="captcha-hint">Not case-sensitive &nbsp;·&nbsp; 6 characters</div>
            <div id="captcha-error">Incorrect code — a new one has been generated.</div>
          </div>
        </div>
        <!-- ── end CAPTCHA ── -->

        <button type="submit" class="btn-submit" <?php echo $isLockedOut ? 'disabled' : ''; ?>>
          <i class="bi bi-box-arrow-in-right me-2"></i>Log In
        </button>
      </form>

      <div class="links-row">
        <a href="../forgot_password_system/forgot_password.php"><i class="bi bi-key me-1"></i>Forgot password?</a>
      </div>

      <div class="form-divider">or</div>

      <div class="register-cta">
        New applicant? <a href="../pages/register.php" id="registerLink">Register here to start your application →</a>
      </div>

    </div>
  </div>

 </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ── Password toggle ──────────────────────────────────────────────────────
document.getElementById('togglePw').addEventListener('click', function () {
    const pw   = document.getElementById('password');
    const icon = document.getElementById('pwIcon');
    if (pw.type === 'password') {
        pw.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        pw.type = 'password';
        icon.className = 'bi bi-eye';
    }
});

// ── CAPTCHA ──────────────────────────────────────────────────────────────
const CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
let captchaCode = '';

function randInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function generateCaptcha() {
    captchaCode = '';
    for (let i = 0; i < 6; i++) {
        captchaCode += CHARS[randInt(0, CHARS.length - 1)];
    }
    drawCaptcha();
    const inp = document.getElementById('captcha-input');
    inp.value = '';
    inp.classList.remove('is-invalid');
    document.getElementById('captcha-error').style.display = 'none';
}

function drawCaptcha() {
    const canvas = document.getElementById('captcha-canvas');
    const ctx    = canvas.getContext('2d');
    const W = canvas.width, H = canvas.height;

    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = '#f0ede8';
    ctx.fillRect(0, 0, W, H);

    // noise lines
    for (let i = 0; i < 8; i++) {
        ctx.beginPath();
        ctx.moveTo(randInt(0, W), randInt(0, H));
        ctx.lineTo(randInt(0, W), randInt(0, H));
        ctx.strokeStyle = `rgba(${randInt(80,160)},${randInt(80,160)},${randInt(80,160)},0.3)`;
        ctx.lineWidth = 1.2;
        ctx.stroke();
    }

    // noise dots
    for (let i = 0; i < 40; i++) {
        ctx.beginPath();
        ctx.arc(randInt(0, W), randInt(0, H), 1.4, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(${randInt(60,180)},${randInt(60,180)},${randInt(60,180)},0.35)`;
        ctx.fill();
    }

    // characters
    const colors = ['#3C3489','#0C447C','#3B6D11','#993C1D','#722B3E','#0F6E56'];
    const fonts  = ['bold 26px serif','bold 26px monospace','bold 28px sans-serif'];
    const charW  = W / 6;

    for (let i = 0; i < captchaCode.length; i++) {
        ctx.save();
        ctx.translate(charW * i + charW / 2, H / 2 + randInt(-6, 6));
        ctx.rotate((randInt(-18, 18) * Math.PI) / 180);
        ctx.font = fonts[i % fonts.length];
        ctx.fillStyle = colors[i % colors.length];
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(captchaCode[i], 0, 0);
        ctx.restore();
    }
}

function validateCaptcha(e) {
    const input = document.getElementById('captcha-input').value.trim().toUpperCase();
    const errEl = document.getElementById('captcha-error');
    const inp   = document.getElementById('captcha-input');

    if (!input) {
        inp.classList.add('is-invalid');
        errEl.textContent = 'Please enter the characters shown above.';
        errEl.style.display = 'block';
        e.preventDefault();
        return false;
    }

    if (input !== captchaCode) {
        inp.classList.add('is-invalid');
        errEl.textContent = 'Incorrect code — a new one has been generated.';
        errEl.style.display = 'block';
        generateCaptcha();
        e.preventDefault();
        return false;
    }

    // CAPTCHA passed — allow form submission
    return true;
}

// Refresh button
document.getElementById('captcha-refresh').addEventListener('click', generateCaptcha);

// Allow Enter key in captcha field to trigger form submission
document.getElementById('captcha-input').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('loginForm').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
});

// Generate on page load
generateCaptcha();
</script>

<?php if ($showAnnouncementModal): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    var modalEl = document.getElementById('announcementModal');
    var myModal = new bootstrap.Modal(modalEl, { backdrop: true });
    myModal.show();
});
</script>
<?php endif; ?>
<?php include "../layout/footer.php"; ?>
</body>
</html>