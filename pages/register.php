<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once '../database.php';
require_once '../Model/user.php';
require_once '../Controller/userController.php';

$config = require '../config.php';
$db = new Database($config);
$userController = new UserController(new User($db->getConnection()));

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username        = trim($_POST["username"]);
    $email           = trim($_POST["email"]);
    $password        = $_POST["password"];
    $repeat_password = $_POST["repeat_password"];

    $usernamePattern = '/^[A-Za-z0-9_-]+$/';
    if (!preg_match($usernamePattern, $username)) {
        $_SESSION['registration_status'] = 'invalid_username';
        header("Location: register.php");
        exit();
    }

    if (strlen($username) < 5) {
        $_SESSION['registration_status'] = 'username_too_short';
        header("Location: register.php");
        exit();
    }

    $passwordPattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';
    if (!preg_match($passwordPattern, $password)) {
        $_SESSION['registration_status'] = 'weak_password';
        header("Location: register.php");
        exit();
    }

    $existingUser = $userController->findByUsername($username);
    if ($existingUser) {
        $_SESSION['registration_status'] = 'username_exists';
        header("Location: register.php");
        exit();
    }

    if ($password !== $repeat_password) {
        $_SESSION['registration_status'] = 'passwords_mismatch';
        header("Location: register.php");
        exit();
    }

    $result = $userController->store($username, $password, $email);
    if ($result) {
        $_SESSION['registration_status'] = 'success';
        header("Location: register.php");
        exit();
    } else {
        $_SESSION['registration_status'] = 'failed';
        header("Location: register.php");
        exit();
    }
}

include "../layout/topbar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Register — NCC Admission Portal</title>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../css/colors.css" rel="stylesheet">

    <style>
        :root {
            --gold:       #B8963E;
            --gold-light: #D4AF68;
            --gold-pale:  #F5EDD8;
            --deep:       #1A1A2E;
            --navy:       #16213E;
            --maroon:     #7B1C1C;
            --maroon-mid: #9B2929;
            --cream:      #FAF7F2;
            --border:     #E8E2D9;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            background: var(--deep);
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        .page-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            flex: 1;
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
            width: 120px; height: 100%;
            background: linear-gradient(to bottom right, var(--gold) 0%, transparent 60%);
            opacity: 0.08;
            transform: skewX(-8deg);
        }

        .left-content { position: relative; z-index: 2; text-align: center; }

        .school-seal {
            width: 100px; height: 100px;
            border-radius: 50%;
            border: 2px solid var(--gold);
            padding: 6px;
            margin-bottom: 2rem;
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
        }

        .school-address {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.45);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 2.5rem;
        }

        .gold-rule {
            width: 60px; height: 2px;
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
            border: 1px solid rgba(184,150,62,0.3);
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
            border: 1px solid rgba(184,150,62,0.2);
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
            width: 4px; height: 100%;
            background: linear-gradient(to bottom, var(--gold), var(--maroon));
        }

        .register-box {
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .register-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--deep);
            margin-bottom: 0.35rem;
        }

        .register-subtitle {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 2.5rem;
            font-weight: 400;
        }

        .step-bar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .step-dot {
            width: 28px; height: 28px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .step-dot.active   { background: var(--gold); color: #fff; }
        .step-dot.inactive { background: #E8E4DF; color: #aaa; }

        .step-line { flex: 1; height: 1px; background: #E0DAD2; }
        .step-text { font-size: 0.7rem; color: #aaa; margin-left: 0.25rem; }

        .field-group { margin-bottom: 1.25rem; }

        .field-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 0.55rem;
        }

        .field-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .field-icon {
            position: absolute;
            left: 1rem;
            color: var(--gold);
            font-size: 0.95rem;
            pointer-events: none;
            z-index: 2;
        }

        .field-input {
            width: 100%;
            padding: 0.85rem 3rem 0.85rem 2.8rem;
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

        .strength-wrap { margin-top: 0.5rem; }

        .strength-track {
            height: 5px;
            background: #E8E4DF;
            border-radius: 99px;
            overflow: hidden;
            margin-bottom: 0.3rem;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 99px;
            transition: width 0.35s ease, background-color 0.35s ease;
        }

        .strength-label {
            font-size: 0.68rem;
            color: #aaa;
            font-weight: 600;
        }

        .toggle-pw {
            position: absolute;
            right: 0.9rem;
            background: none;
            border: none;
            color: #bbb;
            font-size: 1rem;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            z-index: 2;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-pw:hover { color: var(--gold); }

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
            transition: transform 0.15s, box-shadow 0.15s, opacity 0.2s;
            margin-top: 0.5rem;
        }

        .btn-submit:hover:not(:disabled) {
            box-shadow: 0 6px 24px rgba(123,28,28,0.3);
            transform: translateY(-1px);
        }

        .btn-submit:active:not(:disabled) { transform: translateY(0); }

        .btn-submit:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .terms-notice {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.4rem;
            font-size: 0.72rem;
            color: #bbb;
            margin-top: 0.6rem;
            justify-content: center;
        }

        .terms-notice.agreed { color: #22a861; }
        .terms-notice i { font-size: 0.75rem; }

        .reopen-terms {
            background: none;
            border: none;
            padding: 0;
            font-size: 0.72rem;
            color: var(--maroon-mid);
            cursor: pointer;
            text-decoration: underline;
            font-family: 'DM Sans', sans-serif;
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.82rem;
            color: #999;
        }

        .login-link a {
            color: var(--maroon-mid);
            font-weight: 600;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s;
        }

        .login-link a:hover { border-color: var(--maroon-mid); }

        .field-hint {
            font-size: 0.7rem;
            color: #bbb;
            margin-top: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            min-height: 1.2rem;
        }

        .field-hint i { font-size: 0.7rem; }

        /* ── PRIVACY MODAL ── */
        .privacy-modal-body { padding: 0 !important; background: var(--cream); }

        .privacy-modal-header {
            background: var(--navy);
            padding: 1.5rem 1.75rem 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            border-radius: 12px 12px 0 0;
        }

        .privacy-shield-icon {
            width: 44px; height: 44px;
            background: rgba(184,150,62,0.15);
            border: 1px solid rgba(184,150,62,0.3);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            color: var(--gold-light);
            flex-shrink: 0;
        }

        .privacy-modal-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 0.2rem;
        }

        .privacy-modal-sub {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.45);
            margin: 0;
        }

        .privacy-scroll-area {
            max-height: 280px;
            overflow-y: auto;
            padding: 1.5rem 1.75rem;
            background: #fff;
            border-top: 1px solid #E8E2D9;
            border-bottom: 1px solid #E8E2D9;
        }

        .privacy-scroll-area::-webkit-scrollbar { width: 5px; }
        .privacy-scroll-area::-webkit-scrollbar-track { background: #f1f1f1; }
        .privacy-scroll-area::-webkit-scrollbar-thumb { background: #ccc; border-radius: 99px; }

        .privacy-section-title {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--deep);
            margin: 0 0 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .privacy-section-title i { color: var(--gold); font-size: 0.8rem; }

        .privacy-text {
            font-size: 0.82rem;
            color: #666;
            line-height: 1.7;
            margin: 0 0 1.25rem;
        }

        .privacy-footer {
            padding: 1.25rem 1.75rem;
            background: var(--cream);
            border-radius: 0 0 12px 12px;
        }

        .privacy-checkbox-row {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #fff;
            border: 1.5px solid #E8E2D9;
            border-radius: 6px;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .privacy-checkbox-row:hover { border-color: var(--gold); }
        .privacy-checkbox-row.checked { border-color: #22a861; background: #EDFBF3; }

        .privacy-checkbox-row input[type="checkbox"] {
            width: 16px; height: 16px;
            margin-top: 2px;
            accent-color: var(--maroon-mid);
            cursor: pointer;
            flex-shrink: 0;
        }

        .privacy-checkbox-label {
            font-size: 0.82rem;
            color: #555;
            line-height: 1.5;
            cursor: pointer;
        }

        .btn-agree {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, var(--maroon-mid), var(--maroon));
            color: #fff;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s, opacity 0.2s;
        }

        .btn-agree:disabled { opacity: 0.4; cursor: not-allowed; }

        .btn-agree:hover:not(:disabled) {
            box-shadow: 0 6px 24px rgba(123,28,28,0.3);
            transform: translateY(-1px);
        }

        /* ── STATUS MODALS ── */
        .modal-content { border-radius: 12px !important; overflow: hidden; }

        .modal-icon-wrap {
            width: 64px; height: 64px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1.25rem;
        }

        .icon-success { background: #EDFBF3; color: #22a861; }
        .icon-warning { background: #FFF8EC; color: var(--gold); }
        .icon-danger  { background: #FFF0F0; color: var(--maroon-mid); }

        .modal-title-styled {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .modal-body-styled {
            padding: 2.5rem 2rem;
            text-align: center;
            background: var(--cream);
        }

        .modal-msg {
            font-size: 0.88rem;
            color: #888;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .btn-modal-action {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 6px;
            padding: 0.65rem 2rem;
            border: none;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-modal-action:hover { transform: translateY(-1px); }
        .btn-modal-success { background: #22a861; color: #fff; }
        .btn-modal-success:hover { box-shadow: 0 4px 16px rgba(34,168,97,0.3); }
        .btn-modal-warn    { background: var(--gold); color: #fff; }
        .btn-modal-warn:hover { box-shadow: 0 4px 16px rgba(184,150,62,0.3); }
        .btn-modal-danger  { background: var(--maroon-mid); color: #fff; }
        .btn-modal-danger:hover { box-shadow: 0 4px 16px rgba(155,41,41,0.3); }

        @media (max-width: 768px) {
            .page-wrapper { grid-template-columns: 1fr; }
            .left-panel { display: none; }
            .right-panel { padding: 2rem 1.5rem; }
            .right-panel::before { display: none; }
        }
    </style>
</head>

<body class="mt-5">

<!-- PRIVACY MODAL -->
<div class="modal fade" id="privacyModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body privacy-modal-body p-0">
        <div class="privacy-modal-header">
          <div class="privacy-shield-icon"><i class="bi bi-shield-lock-fill"></i></div>
          <div>
            <p class="privacy-modal-title">Privacy Policy & Terms of Use</p>
            <p class="privacy-modal-sub">NCC Admission Portal &mdash; School Year 2026&ndash;2027</p>
          </div>
        </div>
        <div class="privacy-scroll-area">
          <p class="privacy-section-title"><i class="bi bi-database-fill-lock"></i> 1. Data Collection</p>
          <p class="privacy-text">Northeastern Cebu Colleges, Inc. collects personal information you provide during the admission process — including your name, contact details, academic records, and supporting documents — solely for the purpose of processing your admission application.</p>
          <p class="privacy-section-title"><i class="bi bi-people-fill"></i> 2. Use of Information</p>
          <p class="privacy-text">Your information will be used exclusively by authorized NCC personnel to evaluate your application. We do not sell, trade, or share your personal data with third parties, except where required by law or as necessary to complete the enrollment process.</p>
          <p class="privacy-section-title"><i class="bi bi-lock-fill"></i> 3. Data Security</p>
          <p class="privacy-text">We take reasonable precautions to protect the information you submit through this portal. Passwords are encrypted using industry-standard hashing. However, no online system can guarantee absolute security; you are responsible for keeping your credentials confidential.</p>
          <p class="privacy-section-title"><i class="bi bi-pencil-square"></i> 4. Accuracy of Information</p>
          <p class="privacy-text">By submitting an application, you confirm that all information provided is accurate, complete, and truthful. The submission of falsified or misleading documents is grounds for immediate disqualification and may be subject to applicable legal sanctions.</p>
          <p class="privacy-section-title"><i class="bi bi-clock-history"></i> 5. Data Retention</p>
          <p class="privacy-text">Application records are retained in accordance with NCC's institutional policies. Applicants who are not enrolled after the admissions period may request deletion of their data by contacting the Registrar's Office.</p>
          <p class="privacy-section-title"><i class="bi bi-envelope-fill"></i> 6. Contact</p>
          <p class="privacy-text" style="margin-bottom:0;">For questions or concerns regarding your personal data, please contact the NCC Registrar's Office at P.G. Almendras St., Danao City, Cebu, or through the official school communication channels.</p>
        </div>
        <div class="privacy-footer">
          <label class="privacy-checkbox-row" id="privacyCheckRow" for="privacyAgreeCheck">
            <input type="checkbox" id="privacyAgreeCheck">
            <span class="privacy-checkbox-label">I have read and fully understood the <strong>Privacy Policy</strong> and <strong>Terms of Use</strong> of the NCC Admission Portal, and I agree to its provisions.</span>
          </label>
          <button class="btn-agree" id="btnAgree" disabled>
            <i class="bi bi-shield-check me-2"></i>I Agree — Continue to Register
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- STATUS MODALS -->
<div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body-styled modal-body">
        <div class="modal-icon-wrap icon-success"><i class="bi bi-check-lg"></i></div>
        <div class="modal-title-styled" style="color:#22a861;">Registration Successful</div>
        <p class="modal-msg">Your account has been created. You can now log in to the Admission Portal.</p>
        <button class="btn-modal-action btn-modal-success" onclick="window.location.href='log_in.php'">
          <i class="bi bi-box-arrow-in-right me-1"></i> Go to Login
        </button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="usernameExistsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body-styled modal-body">
        <div class="modal-icon-wrap icon-warning"><i class="bi bi-person-fill-exclamation"></i></div>
        <div class="modal-title-styled" style="color:#b07a1a;">Username Already Exists</div>
        <p class="modal-msg">That username is already taken. Please choose a different username and try again.</p>
        <button class="btn-modal-action btn-modal-warn" data-bs-dismiss="modal">Try Again</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="usernameTooShortModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body-styled modal-body">
        <div class="modal-icon-wrap icon-warning"><i class="bi bi-person-fill-exclamation"></i></div>
        <div class="modal-title-styled" style="color:#b07a1a;">Username Too Short</div>
        <p class="modal-msg">Your username must be at least <strong>5 characters</strong> long.</p>
        <button class="btn-modal-action btn-modal-warn" data-bs-dismiss="modal">Try Again</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="invalidUsernameModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body-styled modal-body">
        <div class="modal-icon-wrap icon-warning"><i class="bi bi-person-x-fill"></i></div>
        <div class="modal-title-styled" style="color:#b07a1a;">Invalid Username</div>
        <p class="modal-msg">Usernames may only contain <strong>letters, numbers, underscores (_), and hyphens (-)</strong>. Spaces and special characters are not allowed.</p>
        <button class="btn-modal-action btn-modal-warn" data-bs-dismiss="modal">Try Again</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="weakPasswordModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body-styled modal-body">
        <div class="modal-icon-wrap icon-danger"><i class="bi bi-shield-exclamation"></i></div>
        <div class="modal-title-styled" style="color:var(--maroon-mid);">Password Too Weak</div>
        <p class="modal-msg">Your password must be at least <strong>8 characters</strong> and include an <strong>uppercase letter</strong>, a <strong>number</strong>, and a <strong>special character</strong>.</p>
        <button class="btn-modal-action btn-modal-danger" data-bs-dismiss="modal">Try Again</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="passwordMismatchModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body-styled modal-body">
        <div class="modal-icon-wrap icon-danger"><i class="bi bi-x-circle-fill"></i></div>
        <div class="modal-title-styled" style="color:var(--maroon-mid);">Passwords Do Not Match</div>
        <p class="modal-msg">Please make sure both password fields are identical before submitting.</p>
        <button class="btn-modal-action btn-modal-danger" data-bs-dismiss="modal">Try Again</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="failModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body-styled modal-body">
        <div class="modal-icon-wrap icon-danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="modal-title-styled" style="color:var(--maroon-mid);">Registration Failed</div>
        <p class="modal-msg">Something went wrong on our end. Please try again.</p>
        <button class="btn-modal-action btn-modal-danger" data-bs-dismiss="modal">Try Again</button>
      </div>
    </div>
  </div>
</div>

<!-- MAIN PAGE -->
<div class="page-wrapper">

  <div class="left-panel">
    <div class="left-content">
      <img src="../img/logo.png" class="school-seal" alt="NCC Seal">
      <div class="school-name">Northeastern Cebu<br>Colleges, Inc.</div>
      <div class="school-address">P.G. Almendras St. · Danao City · Cebu</div>
      <div class="gold-rule"></div>
      <div class="portal-label">Online Admission Portal</div>
      <div class="portal-heading">Create Your<br>Account</div>
      <p class="portal-sub">Register to begin your admission application for School Year 2026–2027.</p>
      <div class="info-pills">
        <div class="info-pill"><i class="bi bi-shield-check"></i> Secure</div>
        <div class="info-pill"><i class="bi bi-clock-history"></i> SY 2026–2027</div>
        <div class="info-pill"><i class="bi bi-person-check"></i> Free to Register</div>
      </div>
    </div>
  </div>

  <div class="right-panel">
    <div class="register-box">

      <p class="register-title">Create Account</p>
      <p class="register-subtitle">Fill in the details below to register</p>

      <div class="step-bar">
        <div class="step-dot active">1</div>
        <div class="step-line"></div>
        <div class="step-dot inactive">2</div>
        <div class="step-line"></div>
        <div class="step-dot inactive">3</div>
        <span class="step-text">Register → Application → Status</span>
      </div>

      <form method="POST" action="" id="registerForm">

        <!-- USERNAME -->
        <div class="field-group">
          <label class="field-label" for="username">Username</label>
          <div class="field-wrap">
            <i class="bi bi-person field-icon"></i>
            <input type="text" class="field-input" id="username" name="username"
                   placeholder="Choose a username (min. 5 characters)"
                   minlength="5" required autocomplete="username">
          </div>
          <div class="field-hint" id="usernameHint" style="display:none;"></div>
        </div>

        <!-- EMAIL -->
        <div class="field-group">
          <label class="field-label" for="email">Email</label>
          <div class="field-wrap">
            <i class="bi bi-envelope field-icon"></i>
            <input type="email" class="field-input" id="email" name="email"
                   placeholder="Enter your email" required autocomplete="email">
          </div>
        </div>

        <!-- PASSWORD -->
        <div class="field-group">
          <label class="field-label" for="password">Password</label>
          <div class="field-wrap">
            <i class="bi bi-lock field-icon"></i>
            <input type="password" class="field-input" id="password" name="password"
                   placeholder="Create a password" required autocomplete="new-password">
            <button type="button" class="toggle-pw" id="togglePw1" tabindex="-1">
              <i class="bi bi-eye" id="pwIcon1"></i>
            </button>
          </div>
          <div class="strength-wrap">
            <div class="strength-track">
              <div class="strength-fill" id="strengthFill"></div>
            </div>
            <span class="strength-label" id="strengthLabel">Enter a password</span>
          </div>
          <div class="field-hint" style="color:#bbb;">
            <i class="bi bi-info-circle"></i> Min 8 chars, one uppercase, one number, one special character
          </div>
        </div>

        <!-- CONFIRM PASSWORD -->
        <div class="field-group">
          <label class="field-label" for="repeat_password">Confirm Password</label>
          <div class="field-wrap">
            <i class="bi bi-lock-fill field-icon"></i>
            <input type="password" class="field-input" id="repeat_password" name="repeat_password"
                   placeholder="Repeat your password" required autocomplete="new-password">
            <button type="button" class="toggle-pw" id="togglePw2" tabindex="-1">
              <i class="bi bi-eye" id="pwIcon2"></i>
            </button>
          </div>
          <div class="field-hint" id="matchHint" style="display:none;"></div>
        </div>

        <button type="submit" class="btn-submit" id="btnSubmit" disabled>
          <i class="bi bi-person-plus me-2"></i>Create Account
        </button>

        <div class="terms-notice" id="termsNotice">
          <i class="bi bi-exclamation-circle"></i>
          You must agree to the
          <button type="button" class="reopen-terms" id="btnReopenTerms">Privacy Policy &amp; Terms</button>
          to register.
        </div>

      </form>

      <div class="login-link">
        Already have an account? <a href="log_in.php">Log in here →</a>
      </div>

    </div>
  </div>

</div>

<?php include "../layout/footer.php"; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

  // ── Grab all elements up front ────────────────────
  var pwInput      = document.getElementById('password');
  var rptInput     = document.getElementById('repeat_password');
  var strengthFill = document.getElementById('strengthFill');
  var strengthLbl  = document.getElementById('strengthLabel');
  var matchHint    = document.getElementById('matchHint');
  var usernameInp  = document.getElementById('username');
  var usernameHint = document.getElementById('usernameHint');
  var btnSubmit    = document.getElementById('btnSubmit');
  var termsNotice  = document.getElementById('termsNotice');

  // ── PRIVACY MODAL ─────────────────────────────────
  var privacyModal = new bootstrap.Modal(document.getElementById('privacyModal'), {
    backdrop: 'static', keyboard: false
  });

  var hasAgreed = false;

  <?php if (isset($_SESSION['registration_status'])): ?>
    hasAgreed = true;
    updateAgreedState();
    (function () {
      var statusMap = {
        'success':            'successModal',
        'username_exists':    'usernameExistsModal',
        'username_too_short': 'usernameTooShortModal',
        'invalid_username':   'invalidUsernameModal',
        'weak_password':      'weakPasswordModal',
        'passwords_mismatch': 'passwordMismatchModal',
        'failed':             'failModal'
      };
      var status  = <?php echo json_encode($_SESSION['registration_status']); ?>;
      var modalId = statusMap[status];
      if (modalId) {
        setTimeout(function () {
          var el = document.getElementById(modalId);
          if (el) new bootstrap.Modal(el).show();
        }, 300);
      }
    })();
    <?php unset($_SESSION['registration_status']); ?>
  <?php else: ?>
    privacyModal.show();
  <?php endif; ?>

  document.getElementById('privacyAgreeCheck').addEventListener('change', function () {
    document.getElementById('btnAgree').disabled = !this.checked;
    document.getElementById('privacyCheckRow').classList.toggle('checked', this.checked);
  });

  document.getElementById('btnAgree').addEventListener('click', function () {
    hasAgreed = true;
    privacyModal.hide();
    updateAgreedState();
  });

  // Use event delegation for reopen-terms so it works even after innerHTML swap
  document.addEventListener('click', function (e) {
    if (e.target && e.target.id === 'btnReopenTerms') {
      privacyModal.show();
    }
  });

  function updateAgreedState() {
    if (hasAgreed) {
      btnSubmit.disabled = false;
      termsNotice.innerHTML =
        '<i class="bi bi-shield-check"></i> You have agreed to the Privacy Policy &amp; Terms of Use.';
      termsNotice.classList.add('agreed');
    } else {
      btnSubmit.disabled = true;
      termsNotice.innerHTML =
        '<i class="bi bi-exclamation-circle"></i> You must agree to the ' +
        '<button type="button" class="reopen-terms" id="btnReopenTerms">Privacy Policy &amp; Terms</button> to register.';
      termsNotice.classList.remove('agreed');
    }
  }

  // ── USERNAME HINT ─────────────────────────────────
  usernameInp.addEventListener('input', function () {
    var len = this.value.length;
    if (len === 0) {
      usernameHint.style.display = 'none';
      return;
    }
    usernameHint.style.display = 'flex';
    if (len < 5) {
      usernameHint.style.color = 'var(--maroon-mid)';
      usernameHint.innerHTML =
        '<i class="bi bi-x-circle"></i> Username must be at least 5 characters (' + len + '/5)';
    } else {
      usernameHint.style.color = '#22a861';
      usernameHint.innerHTML =
        '<i class="bi bi-check-circle"></i> Username looks good';
    }
  });

  // ── SHOW / HIDE PASSWORD ──────────────────────────
  document.getElementById('togglePw1').addEventListener('click', function (e) {
    e.preventDefault();
    var isText = pwInput.type === 'text';
    pwInput.type = isText ? 'password' : 'text';
    document.getElementById('pwIcon1').className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
  });

  document.getElementById('togglePw2').addEventListener('click', function (e) {
    e.preventDefault();
    var isText = rptInput.type === 'text';
    rptInput.type = isText ? 'password' : 'text';
    document.getElementById('pwIcon2').className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
  });

  // ── PASSWORD STRENGTH ─────────────────────────────
  pwInput.addEventListener('input', function () {
    updateStrength(this.value);
    updateMatch();
  });

  function updateStrength(val) {
    if (val.length === 0) {
      strengthFill.style.width           = '0%';
      strengthFill.style.backgroundColor = '#E8E4DF';
      strengthLbl.textContent            = 'Enter a password';
      strengthLbl.style.color            = '#aaa';
      return;
    }

    var score = 0;
    if (val.length >= 8)            score++;
    if (/[A-Z]/.test(val))          score++;
    if (/[0-9]/.test(val))          score++;
    if (/[^A-Za-z0-9]/.test(val))   score++;

    var cfg = [
      { w: '15%',  bg: '#e55353', lbl: 'Weak',   col: '#e55353' },
      { w: '25%',  bg: '#e55353', lbl: 'Weak',   col: '#e55353' },
      { w: '50%',  bg: '#B8963E', lbl: 'Fair',   col: '#B8963E' },
      { w: '75%',  bg: '#f5a623', lbl: 'Good',   col: '#f5a623' },
      { w: '100%', bg: '#22a861', lbl: 'Strong', col: '#22a861' }
    ];

    strengthFill.style.width           = cfg[score].w;
    strengthFill.style.backgroundColor = cfg[score].bg;
    strengthLbl.textContent            = cfg[score].lbl;
    strengthLbl.style.color            = cfg[score].col;
  }

  // ── PASSWORD MATCH ────────────────────────────────
  rptInput.addEventListener('input', updateMatch);

  function updateMatch() {
    var pw  = pwInput.value;
    var rpt = rptInput.value;

    if (rpt.length === 0) {
      matchHint.style.display = 'none';
      return;
    }

    matchHint.style.display = 'flex';
    if (pw === rpt) {
      matchHint.style.color = '#22a861';
      matchHint.innerHTML   = '<i class="bi bi-check-circle"></i> Passwords match';
    } else {
      matchHint.style.color = 'var(--maroon-mid)';
      matchHint.innerHTML   = '<i class="bi bi-x-circle"></i> Passwords do not match';
    }
  }

});
</script>

</body>
</html>