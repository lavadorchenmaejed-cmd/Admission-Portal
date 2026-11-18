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

if (!isset($_SESSION['user_Id'])) {
    header("Location: ../pages/log_in.php");
    exit();
}
require_once __DIR__ . '/../database.php';

require __DIR__ . '/../PHPMailer-master/src/Exception.php';
require __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer-master/src/SMTP.php'; // Composer autoload for PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config = require __DIR__ . '/../config.php';
$db     = new Database($config);
$conn   = $db->getConnection();

$userId = $_SESSION['user_Id'] ?? $_SESSION['user_id'];

// ── Fetch current admin info ───────────────────────────────────────────────
$stmt = $conn->prepare("SELECT username, email FROM user WHERE user_Id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$username = $admin['username'] ?? '';
$email    = $admin['email']    ?? '';

// ── Feedback messages ─────────────────────────────────────────────────────
$successMsg = '';
$errorMsg   = '';

// ── Handle: update username ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_username') {
    $newUsername = trim($_POST['new_username'] ?? '');

    if (strlen($newUsername) < 3 || strlen($newUsername) > 60) {
        $errorMsg = 'Username must be between 3 and 60 characters.';
    } elseif (!preg_match('/^[\w\-. ]+$/u', $newUsername)) {
        $errorMsg = 'Username contains invalid characters.';
    } else {
        // Check uniqueness
        $chk = $conn->prepare("SELECT user_Id FROM user WHERE username = ? AND user_Id != ?");
        $chk->bind_param("si", $newUsername, $userId);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $errorMsg = 'That username is already taken. Please choose another.';
        } else {
            $upd = $conn->prepare("UPDATE user SET username = ? WHERE user_Id = ?");
            $upd->bind_param("si", $newUsername, $userId);
            $upd->execute();
            $upd->close();
            $_SESSION['username'] = $newUsername;
            $username = $newUsername;
            $successMsg = 'Username updated successfully.';
        }
        $chk->close();
    }
}

// ── Handle: send OTP for password change ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_otp') {
    if (empty($email)) {
        $errorMsg = 'No email address is associated with this account.';
    } else {
        $otp     = rand(100000, 999999);
        $expires = time() + 600; // 10-minute window

        $_SESSION['pw_otp']         = $otp;
        $_SESSION['pw_otp_expires'] = $expires;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $config['smtp_host']     ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['smtp_user']     ?? 'jedlavador@gmail.com';
            $mail->Password   = $config['smtp_pass']     ?? 'ijninpjkheyxdvbk';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $config['smtp_port']     ?? 587;

            $mail->setFrom($config['smtp_user'] ?? 'jedlavador@gmail.com', 'Admission Portal');
            $mail->addAddress($email, $username);
            $mail->isHTML(true);
            $mail->Subject = 'Your Password Change OTP — NCC Admin';
            $mail->Body    = '
                <div style="font-family:DM Sans,sans-serif;max-width:480px;margin:auto;padding:2rem;border:1px solid #E2E8F0;border-radius:12px;">
                    <h2 style="font-family:Georgia,serif;color:#0F172A;margin-bottom:0.5rem;">Password Change Request</h2>
                    <p style="color:#475569;font-size:0.9rem;">Use the OTP below to proceed. It expires in <strong>10 minutes</strong>.</p>
                    <div style="margin:1.5rem 0;text-align:center;">
                        <span style="display:inline-block;background:#EFF6FF;border:2px dashed #2563EB;border-radius:10px;padding:1rem 2.5rem;font-size:2rem;font-weight:700;letter-spacing:0.25em;color:#1D4ED8;">' . $otp . '</span>
                    </div>
                    <p style="color:#94A3B8;font-size:0.78rem;">If you did not request this, you can safely ignore this email.</p>
                </div>';
            $mail->AltBody = "Your OTP is: $otp — valid for 10 minutes.";
            $mail->send();
            $successMsg = "OTP sent to {$email}. Check your inbox.";
        } catch (Exception $e) {
            $errorMsg = 'Failed to send OTP: ' . $mail->ErrorInfo;
        }
    }
}

// ── Handle: verify OTP + change password ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $inputOtp   = trim($_POST['otp']          ?? '');
    $newPass    = $_POST['new_password']       ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($_SESSION['pw_otp'])) {
        $errorMsg = 'No OTP was requested. Please request one first.';
    } elseif (time() > ($_SESSION['pw_otp_expires'] ?? 0)) {
        $errorMsg = 'The OTP has expired. Please request a new one.';
        unset($_SESSION['pw_otp'], $_SESSION['pw_otp_expires']);
    } elseif ((string)$inputOtp !== (string)$_SESSION['pw_otp']) {
        $errorMsg = 'Incorrect OTP. Please try again.';
    } elseif (strlen($newPass) < 8) {
        $errorMsg = 'Password must be at least 8 characters.';
    } elseif ($newPass !== $confirmPass) {
        $errorMsg = 'Passwords do not match.';
    } else {
        $hashed = password_hash($newPass, PASSWORD_BCRYPT);
        $upd = $conn->prepare("UPDATE user SET password = ? WHERE user_Id = ?");
        $upd->bind_param("si", $hashed, $userId);
        $upd->execute();
        $upd->close();
        unset($_SESSION['pw_otp'], $_SESSION['pw_otp_expires']);
        $successMsg = 'Password changed successfully.';
    }
}

include __DIR__ . '/../layout/admintop.php';

// Avatar initials
$initials = strtoupper(substr($username, 0, 1));
if (strpos($username, ' ') !== false) {
    $parts = explode(' ', $username);
    $initials = strtoupper($parts[0][0] . end($parts)[0]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Profile — NCC Admission Portal</title>
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
    --gold-pale: rgba(217,119,6,0.08);
    --green: #059669;
    --green-pale: rgba(5,150,105,0.1);
    --green-border: rgba(5,150,105,0.25);
    --red: #DC2626;
    --red-pale: rgba(220,38,38,0.08);
    --red-border: rgba(220,38,38,0.2);
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DM Sans', sans-serif; background: var(--surface); color: var(--text-primary); overflow-x: hidden; }

  .wrap { padding: 6rem 2rem 3rem; max-width: 900px; margin: 0 auto; }

  /* Page header */
  .page-eyebrow { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; color: var(--primary); margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.5rem; }
  .page-eyebrow::before { content: ''; display: inline-block; width: 20px; height: 2px; background: var(--primary); border-radius: 2px; }
  .page-header h1 { font-family: 'Playfair Display', serif; font-size: 1.9rem; font-weight: 700; color: var(--text-primary); line-height: 1.1; margin-bottom: 2rem; }
  .back-link{font-size:0.78rem;color:var(--primary);font-weight:600;text-decoration:none;display:flex;align-items:center;gap:0.4rem;border:1px solid var(--primary-border);border-radius:7px;padding:0.45rem 1rem;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.05);transition:background 0.2s;}
  .back-link:hover{background:var(--primary-pale);color:var(--primary);}
    
  /* Alert banners */
  .alert-banner { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.9rem 1.1rem; border-radius: 10px; font-size: 0.85rem; font-weight: 500; margin-bottom: 1.5rem; animation: fadeUp 0.3s ease both; }
  .alert-success { background: var(--green-pale); border: 1px solid var(--green-border); color: #065F46; }
  .alert-error   { background: var(--red-pale);   border: 1px solid var(--red-border);   color: #991B1B; }
  .alert-banner i { margin-top: 1px; flex-shrink: 0; }

  /* Profile hero card */
  .profile-hero {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 2rem 2rem 1.75rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    display: flex;
    align-items: center;
    gap: 1.75rem;
    flex-wrap: wrap;
  }

  .avatar-circle {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: var(--primary-pale);
    border: 2px solid var(--primary-border);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--primary);
    flex-shrink: 0;
    letter-spacing: 0.02em;
  }

  .hero-info { flex: 1; min-width: 160px; }
  .hero-username { font-family: 'Playfair Display', serif; font-size: 1.4rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
  .hero-email { font-size: 0.82rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem; }
  .hero-email i { color: var(--gold); }
  .hero-badge { display: inline-flex; align-items: center; gap: 0.35rem; background: var(--primary-pale); border: 1px solid var(--primary-border); border-radius: 5px; padding: 0.2rem 0.7rem; font-size: 0.7rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.1em; margin-top: 0.6rem; }

  /* Section cards */
  .section-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    margin-bottom: 1.25rem;
  }

  .section-card-header {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1.1rem 1.5rem;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
  }
  .section-card-header:hover { background: var(--surface-2); }

  .section-icon {
    width: 34px; height: 34px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
  }
  .section-icon.blue { background: var(--primary-pale); color: var(--primary); }
  .section-icon.gold { background: var(--gold-pale); color: var(--gold); }

  .section-title { font-weight: 700; font-size: 0.92rem; color: var(--text-primary); flex: 1; }
  .section-sub   { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem; }

  .chevron { color: var(--text-muted); font-size: 0.8rem; transition: transform 0.25s; }
  .chevron.open { transform: rotate(180deg); }

  .section-body { padding: 1.5rem; background: var(--surface-2); display: none; }
  .section-body.open { display: block; animation: fadeUp 0.3s cubic-bezier(0.22,1,0.36,1) both; }

  /* Form elements */
  .field-label { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.4rem; display: block; }
  .field-wrap { position: relative; }
  .field-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem; pointer-events: none; }
  .field-input {
    width: 100%;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    padding: 0.7rem 1rem 0.7rem 2.5rem;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  .field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-pale); }
  .field-hint { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.35rem; }

  .form-row { margin-bottom: 1.1rem; }
  .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.1rem; }

  .btn-primary-action {
    background: var(--primary);
    border: none;
    border-radius: 8px;
    color: #fff;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    font-weight: 700;
    padding: 0.65rem 1.5rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
  }
  .btn-primary-action:hover { background: #1D4ED8; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(37,99,235,0.25); }
  .btn-primary-action:active { transform: translateY(0); }

  .btn-secondary-action {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-secondary);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.65rem 1.25rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: background 0.15s, border-color 0.15s;
  }
  .btn-secondary-action:hover { background: var(--surface); border-color: var(--border-strong); }

  .btn-gold {
    background: var(--gold);
    border: none;
    border-radius: 8px;
    color: #fff;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    font-weight: 700;
    padding: 0.65rem 1.5rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
  }
  .btn-gold:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(217,119,6,0.3); }

  .form-actions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-top: 0.25rem; }

  /* OTP step */
  .otp-step { display: none; margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid var(--border); animation: fadeUp 0.3s ease both; }
  .otp-step.visible { display: block; }
  .otp-sent-info { display: flex; align-items: center; gap: 0.6rem; background: var(--primary-pale); border: 1px solid var(--primary-border); border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.82rem; color: #1e40af; margin-bottom: 1.1rem; }

  /* Toggle password eye */
  .field-eye { position: absolute; right: 0.9rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); font-size: 0.85rem; cursor: pointer; padding: 0; line-height: 1; transition: color 0.2s; }
  .field-eye:hover { color: var(--primary); }
  .field-input.with-eye { padding-right: 2.5rem; }

  /* Divider */
  .inner-divider { height: 1px; background: var(--border); margin: 1.25rem 0; }

  /* Password strength meter */
  .strength-bar { height: 4px; border-radius: 2px; background: var(--border); margin-top: 0.5rem; overflow: hidden; }
  .strength-fill { height: 100%; border-radius: 2px; width: 0; transition: width 0.3s, background 0.3s; }
  .strength-label { font-size: 0.68rem; color: var(--text-muted); margin-top: 0.3rem; }

  @keyframes fadeUp { from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)} }
  @media(max-width:600px){ .form-grid-2{grid-template-columns:1fr} .profile-hero{flex-direction:column;align-items:flex-start;gap:1rem} }
</style>
</head>
<body>
<div class="wrap">

  <!-- Page header -->
<div class="page-header d-flex justify-content-between align-items-center mb-4">
  <div>
    <div class="page-eyebrow">NCC Admission Portal</div>
    <h1 class="mb-0">Admin Profile</h1>
  </div>
  <a href="admin_dashboard.php" class="back-link">
    <i class="fa fa-home"></i>
  </a>
</div>


  <!-- Feedback banners -->
  <?php if ($successMsg): ?>
  <div class="alert-banner alert-success">
    <i class="fa fa-check-circle"></i><?= htmlspecialchars($successMsg) ?>
  </div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
  <div class="alert-banner alert-error">
    <i class="fa fa-exclamation-circle"></i><?= htmlspecialchars($errorMsg) ?>
  </div>
  <?php endif; ?>

  <!-- Profile hero -->
  <div class="profile-hero">
    <div class="avatar-circle"><?= htmlspecialchars($initials) ?></div>
    <div class="hero-info">
      <div class="hero-username"><?= htmlspecialchars($username) ?></div>
      <div class="hero-email"><i class="fa fa-envelope"></i><?= htmlspecialchars($email ?: 'No email on record') ?></div>
      <div class="hero-badge"><i class="fa fa-shield-halved"></i> Administrator</div>
    </div>
  </div>

  <!-- ── Section 1: Change Username ───────────────────────────────────── -->
  <div class="section-card">
    <div class="section-card-header" onclick="toggleSection('sec-username', this)">
      <div class="section-icon blue"><i class="fa fa-user-pen"></i></div>
      <div>
        <div class="section-title">Edit Username</div>
        <div class="section-sub">Update your display name for the admin portal</div>
      </div>
      <i class="fa fa-chevron-down chevron" id="chev-sec-username"></i>
    </div>
    <div class="section-body" id="sec-username">
      <form method="POST">
        <input type="hidden" name="action" value="update_username">
        <div class="form-row">
          <label class="field-label">Current username</label>
          <div class="field-wrap">
            <i class="fa fa-user field-icon"></i>
            <input type="text" class="field-input" value="<?= htmlspecialchars($username) ?>" disabled>
          </div>
        </div>
        <div class="form-row">
          <label class="field-label" for="new_username">New username</label>
          <div class="field-wrap">
            <i class="fa fa-pen field-icon"></i>
            <input type="text" id="new_username" name="new_username" class="field-input"
                   placeholder="Enter new username" maxlength="60" required
                   value="<?= htmlspecialchars($_POST['new_username'] ?? '') ?>">
          </div>
          <div class="field-hint">3–60 characters. Letters, numbers, spaces, hyphens, dots allowed.</div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary-action"><i class="fa fa-save"></i> Save Username</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Section 2: Change Password ───────────────────────────────────── -->
  <div class="section-card">
    <div class="section-card-header" onclick="toggleSection('sec-password', this)">
      <div class="section-icon gold"><i class="fa fa-key"></i></div>
      <div>
        <div class="section-title">Change Password</div>
        <div class="section-sub">An OTP will be sent to your registered email</div>
      </div>
      <i class="fa fa-chevron-down chevron" id="chev-sec-password"></i>
    </div>
    <div class="section-body" id="sec-password">

      <!-- Step 1: Request OTP -->
      <div id="step-request-otp">
        <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:1.1rem;line-height:1.6;">
          To change your password, we'll send a one-time passcode to
          <strong><?= htmlspecialchars($email ?: 'your registered email') ?></strong>.
          The code is valid for <strong>10 minutes</strong>.
        </p>
        <form method="POST" id="otpRequestForm">
          <input type="hidden" name="action" value="send_otp">
          <button type="submit" class="btn-gold"><i class="fa fa-paper-plane"></i> Send OTP to My Email</button>
        </form>
      </div>

      <!-- Step 2: Enter OTP + new password (visible when OTP was just sent) -->
      <?php
        $otpJustSent = ($successMsg && strpos($successMsg, 'OTP sent') !== false);
        $otpPending  = !empty($_SESSION['pw_otp']) && time() <= ($_SESSION['pw_otp_expires'] ?? 0);
      ?>
      <div class="otp-step <?= ($otpJustSent || $otpPending) ? 'visible' : '' ?>" id="step-enter-otp">
        <div class="otp-sent-info">
          <i class="fa fa-circle-info"></i>
          OTP sent to <strong style="margin-left:0.25rem;"><?= htmlspecialchars($email) ?></strong>. Enter it below within 10 minutes.
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="change_password">
          <div class="form-row">
            <label class="field-label" for="otp">One-time passcode</label>
            <div class="field-wrap">
              <i class="fa fa-hashtag field-icon"></i>
              <input type="text" id="otp" name="otp" class="field-input"
                     placeholder="6-digit code" maxlength="6"
                     autocomplete="one-time-code" required
                     style="letter-spacing:0.2em;font-weight:700;">
            </div>
          </div>
          <div class="inner-divider"></div>
          <div class="form-grid-2">
            <div>
              <label class="field-label" for="new_password">New password</label>
              <div class="field-wrap">
                <i class="fa fa-lock field-icon"></i>
                <input type="password" id="new_password" name="new_password"
                       class="field-input with-eye" placeholder="Min. 8 characters"
                       oninput="checkStrength(this.value)" required>
                <button type="button" class="field-eye" onclick="togglePw('new_password', this)">
                  <i class="fa fa-eye"></i>
                </button>
              </div>
              <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
              <div class="strength-label" id="strength-label"></div>
            </div>
            <div>
              <label class="field-label" for="confirm_password">Confirm new password</label>
              <div class="field-wrap">
                <i class="fa fa-lock field-icon"></i>
                <input type="password" id="confirm_password" name="confirm_password"
                       class="field-input with-eye" placeholder="Repeat password" required>
                <button type="button" class="field-eye" onclick="togglePw('confirm_password', this)">
                  <i class="fa fa-eye"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary-action"><i class="fa fa-shield-halved"></i> Change Password</button>
            <button type="button" class="btn-secondary-action" onclick="hideOtpStep()">
              <i class="fa fa-times"></i> Cancel
            </button>
          </div>
        </form>
      </div>

    </div><!-- /.section-body -->
  </div><!-- /.section-card -->

</div><!-- /.wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  /* Toggle accordion sections */
  function toggleSection(id, header) {
    const body  = document.getElementById(id);
    const chev  = document.getElementById('chev-' + id);
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    if (chev) chev.classList.toggle('open', !isOpen);
  }

  /* Show OTP step without page reload (after send request) */
  <?php if ($otpJustSent): ?>
  document.addEventListener('DOMContentLoaded', function () {
    toggleSection('sec-password', null);
    document.getElementById('chev-sec-password').classList.add('open');
  });
  <?php endif; ?>

  /* Hide OTP step */
  function hideOtpStep() {
    document.getElementById('step-enter-otp').classList.remove('visible');
  }

  /* Toggle password visibility */
  function togglePw(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'fa fa-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'fa fa-eye';
    }
  }

  /* Password strength meter */
  function checkStrength(val) {
    const fill  = document.getElementById('strength-fill');
    const label = document.getElementById('strength-label');
    let score = 0;
    if (val.length >= 8)               score++;
    if (/[A-Z]/.test(val))             score++;
    if (/[0-9]/.test(val))             score++;
    if (/[^A-Za-z0-9]/.test(val))      score++;

    const levels = [
      { w: '0%',   color: 'var(--border)',   text: '' },
      { w: '25%',  color: '#DC2626',          text: 'Weak' },
      { w: '50%',  color: '#D97706',          text: 'Fair' },
      { w: '75%',  color: '#2563EB',          text: 'Good' },
      { w: '100%', color: '#059669',          text: 'Strong' },
    ];
    const lvl = val.length === 0 ? levels[0] : levels[score];
    fill.style.width      = lvl.w;
    fill.style.background = lvl.color;
    label.textContent     = lvl.text;
    label.style.color     = lvl.color;
  }

  /* Auto-open password section if OTP is pending from a previous send */
  <?php if ($otpPending && !$otpJustSent): ?>
  document.addEventListener('DOMContentLoaded', function () {
    const body = document.getElementById('sec-password');
    const chev = document.getElementById('chev-sec-password');
    body.classList.add('open');
    chev.classList.add('open');
  });
  <?php endif; ?>
</script>
    <?php include '../layout/footer.php'?>
</body>
</html>