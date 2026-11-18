<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../PHPMailer-master/src/Exception.php';
require __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer-master/src/SMTP.php';

session_start();
require_once __DIR__ . '/../database.php';
$config = require __DIR__ . '/../config.php';
$db     = new Database($config);
$conn   = $db->getConnection();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting: max 5 attempts per 10 minutes per session
$now     = time();
$window  = 600;
$max_att = 5;
if (!isset($_SESSION['fp_attempts'])) $_SESSION['fp_attempts'] = [];
$_SESSION['fp_attempts'] = array_filter($_SESSION['fp_attempts'], fn($t) => ($now - $t) < $window);

$error = $info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die("Invalid request.");
    }

    // Rate limit
    if (count($_SESSION['fp_attempts']) >= $max_att) {
        $error = "Too many attempts. Please wait a few minutes and try again.";
    } else {
        $_SESSION['fp_attempts'][] = $now;

        $email    = trim($_POST['email']);
        $username = trim($_POST['username']);

        // Always show the same message to prevent email/username enumeration
        $info = "If that account exists, a reset link has been generated.";

        if (!empty($email) && !empty($username)) {
            // Verify BOTH email AND username match the same account
            $stmt = $conn->prepare("SELECT user_id, email, username FROM user WHERE email = ? AND username = ?");
            $stmt->bind_param("ss", $email, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $token   = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

                $stmt = $conn->prepare("UPDATE user SET reset_token = ?, reset_expires = ? WHERE email = ? AND username = ?");
                $stmt->bind_param("ssss", $token, $expires, $email, $username);
                

                
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    // Build reset link
                    $resetLink = "https://nccadmissionportal.infinityfreeapp.com/forgot_password_system/reset_password.php?token=" . urlencode($token);

                    // Send email with PHPMailer
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'jedlavador@gmail.com';
                        $mail->Password   = 'ijninpjkheyxdvbk';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        $mail->setFrom('jedlavador@gmail.com', 'Admission Portal');
                        $mail->addAddress($email);

                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset Request';
                        $mail->Body    = "Hello,<br><br>Click the link below to reset your password:<br>
                                          <a href='$resetLink'>$resetLink</a><br><br>
                                          This link will expire in 1 hour.";

                        $mail->send();
                        $info = "If that account exists, a reset link has been sent to the email provided.";
                    } catch (Exception $e) {
                        $error = "Mailer Error: " . $mail->ErrorInfo;
                    }
                } else {
                    $error = "Database update failed: " . $stmt->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Forgot Password — NCC Admission Portal</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --gold:    #B8963E;
            --maroon:  #7B1C1C;
            --maroon-d:#611515;
            --deep:    #1A1A2E;
            --cream:   #FAF7F2;
            --border:  rgba(0,0,0,0.1);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            background: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        /* ── Card ── */
        .fp-card {
            background: #fff;
            border-radius: 14px;
            border: 0.5px solid rgba(0,0,0,0.08);
            box-shadow: 0 2px 24px rgba(0,0,0,0.06);
            width: 100%;
            max-width: 420px;
            padding: 2.75rem 2.5rem 2.25rem;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.45s cubic-bezier(0.22,1,0.36,1) both;
        }

        .fp-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(to right, var(--gold), var(--maroon));
            border-radius: 14px 14px 0 0;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Icon ── */
        .fp-icon {
            width: 54px; height: 54px;
            border-radius: 50%;
            background: #FAF7F2;
            border: 0.5px solid rgba(184,150,62,0.25);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .fp-icon svg {
            width: 22px; height: 22px;
            stroke: var(--gold); fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }

        /* ── Headings ── */
        .fp-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.65rem;
            font-weight: 700;
            color: var(--deep);
            text-align: center;
            margin-bottom: 0.35rem;
        }

        .fp-sub {
            font-size: 0.84rem;
            color: #888;
            text-align: center;
            line-height: 1.65;
            margin-bottom: 2rem;
        }

        /* ── Steps ── */
        .steps {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .step { display: flex; flex-direction: column; align-items: center; gap: 5px; }

        .step-dot {
            width: 28px; height: 28px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 600;
        }

        .step-dot.active  { background: var(--maroon); color: #fff; }
        .step-dot.pending { background: #F0EDE8; color: #aaa; border: 0.5px solid #E0DAD2; }

        .step-lbl {
            font-size: 10px; font-weight: 500;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: #bbb;
        }

        .step-lbl.active { color: var(--maroon); }

        .step-line {
            width: 36px; height: 1px;
            background: #E0DAD2;
            margin-bottom: 18px;
        }

        /* ── Alerts ── */
        .alert {
            border-radius: 8px;
            padding: 0.8rem 1rem;
            font-size: 0.83rem;
            margin-bottom: 1.35rem;
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            line-height: 1.55;
        }

        .alert svg {
            width: 15px; height: 15px;
            fill: none; stroke-width: 2;
            stroke-linecap: round; stroke-linejoin: round;
            flex-shrink: 0; margin-top: 1px;
        }

        .alert-success { background: #EDFBF3; border: 1px solid #A7F3D0; color: #065F46; }
        .alert-success svg { stroke: #059669; }

        .alert-error { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; }
        .alert-error svg { stroke: #DC2626; }

        .alert-warning { background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; }
        .alert-warning svg { stroke: #D97706; }

        /* ── Fields ── */
        .field { margin-bottom: 1.15rem; }

        .field-label {
            display: block;
            font-size: 11px; font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .field-wrap { position: relative; display: flex; align-items: center; }

        .field-ico {
            position: absolute; left: 12px;
            display: flex; align-items: center;
            pointer-events: none; z-index: 2;
        }

        .field-ico svg {
            width: 15px; height: 15px;
            stroke: var(--gold); fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }

        .field-input {
            width: 100%;
            padding: 0.78rem 0.9rem 0.78rem 2.5rem;
            border: 1px solid #DDD8D0;
            border-radius: 7px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            color: var(--deep);
            background: #fff;
            outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
        }

        .field-input::placeholder { color: #C0BAB2; }

        .field-input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(184,150,62,0.1);
        }

        /* ── Submit ── */
        .btn-submit {
            width: 100%;
            padding: 0.85rem;
            margin-top: 0.6rem;
            background: var(--maroon);
            color: #fff;
            border: none;
            border-radius: 7px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            font-weight: 500;
            letter-spacing: 0.04em;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            gap: 8px;
            transition: background 0.18s, transform 0.12s;
        }

        .btn-submit:hover { background: var(--maroon-d); transform: translateY(-1px); }
        .btn-submit:active { transform: translateY(0); }

        .btn-submit svg {
            width: 14px; height: 14px;
            stroke: #fff; fill: none;
            stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round;
        }

        /* ── Rate info ── */
        .rate-info {
            display: flex; align-items: center; justify-content: center;
            gap: 5px;
            font-size: 11px; color: #bbb;
            margin-top: 0.65rem;
        }

        .rate-info svg {
            width: 11px; height: 11px;
            stroke: var(--gold); fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }

        /* ── Divider ── */
        .divider {
            display: flex; align-items: center;
            gap: 1rem; margin: 1.5rem 0 1.25rem;
            font-size: 11px; color: #ccc;
        }

        .divider::before, .divider::after {
            content: ''; flex: 1;
            height: 0.5px; background: #E8E2D9;
        }

        /* ── Back link ── */
        .back-row { text-align: center; font-size: 0.83rem; color: #999; }

        .back-row a {
            color: var(--maroon); font-weight: 600;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 5px;
            border-bottom: 1px solid transparent;
            transition: border-color 0.18s;
        }

        .back-row a:hover { border-color: var(--maroon); }

        .back-row a svg {
            width: 13px; height: 13px;
            stroke: var(--maroon); fill: none;
            stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
        }

        /* ── Security note ── */
        .sec-note {
            display: flex; align-items: flex-start; gap: 6px;
            font-size: 11px; color: #bbb;
            line-height: 1.55;
            margin-top: 1.5rem;
            padding-top: 1.1rem;
            border-top: 1px dashed #E8E2D9;
        }

        .sec-note svg {
            width: 13px; height: 13px;
            stroke: var(--gold); fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
            flex-shrink: 0; margin-top: 1px;
        }

        /* ── Success state ── */
        .success-state { text-align: center; padding: 0.5rem 0; animation: slideUp 0.4s cubic-bezier(0.22,1,0.36,1) both; }

        .success-ico {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: #EDFBF3;
            border: 1px solid #A7F3D0;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .success-ico svg {
            width: 30px; height: 30px;
            stroke: #059669; fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }

        .success-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.45rem; font-weight: 700;
            color: var(--deep); margin-bottom: 0.6rem;
        }

        .success-msg {
            font-size: 0.84rem; color: #888;
            line-height: 1.7; max-width: 310px;
            margin: 0 auto 1.75rem;
        }

        /* ── Responsive ── */
        @media (max-width: 480px) {
            .fp-card { padding: 2rem 1.5rem 1.75rem; }
        }
    </style>
</head>

<body>

    <div class="fp-card">

        <!-- Icon -->
        <div class="fp-icon">
            <svg viewBox="0 0 24 24"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
        </div>

        <?php if ($info && !$error): ?>

            <!-- ── SUCCESS STATE ── -->
            <div class="success-state">
                <div class="success-ico">
                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>

                <div class="success-title">Check your inbox</div>
                <p class="success-msg">
                    If a matching account was found, a password reset link has been sent to the email address provided. The link will expire in <strong>1 hour</strong>.
                </p>

                <div class="alert alert-success" style="text-align:left;">
                    <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span><?= $info ?></span>
                </div>

                <div class="alert alert-warning" style="text-align:left; margin-bottom:0;">
                    <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>Didn't receive the email? Check your spam folder or wait a few minutes before trying again.</span>
                </div>
            </div>

        <?php else: ?>

            <!-- ── FORM STATE ── -->
            <div class="fp-title">Forgot password?</div>
            <p class="fp-sub">Enter your username and registered email<br>to receive a secure reset link.</p>

            <!-- Steps -->
            <div class="steps">
                <div class="step">
                    <div class="step-dot active">1</div>
                    <span class="step-lbl active">Verify</span>
                </div>
                <div class="step-line"></div>
                <div class="step">
                    <div class="step-dot pending">2</div>
                    <span class="step-lbl">Email</span>
                </div>
                <div class="step-line"></div>
                <div class="step">
                    <div class="step-dot pending">3</div>
                    <span class="step-lbl">Reset</span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php
            $attemptsUsed = count($_SESSION['fp_attempts']);
            $attemptsLeft = $max_att - $attemptsUsed;
            if ($attemptsUsed >= 3 && $attemptsLeft > 0):
            ?>
                <div class="alert alert-warning">
                    <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span><?= $attemptsLeft ?> attempt<?= $attemptsLeft !== 1 ? 's' : '' ?> remaining before you are temporarily locked out.</span>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="field">
                    <label class="field-label" for="username">Username</label>
                    <div class="field-wrap">
                        <span class="field-ico">
                            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <input type="text"
                               class="field-input"
                               id="username"
                               name="username"
                               placeholder="Enter your username"
                               maxlength="100"
                               required
                               autocomplete="off">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="email">Email address</label>
                    <div class="field-wrap">
                        <span class="field-ico">
                            <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        </span>
                        <input type="email"
                               class="field-input"
                               id="email"
                               name="email"
                               placeholder="Enter your registered email"
                               maxlength="255"
                               required
                               autocomplete="off">
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Send reset link
                </button>

                <div class="rate-info">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Maximum <?= $max_att ?> attempts per 10 minutes for your security.
                </div>
            </form>

        <?php endif; ?>

        <div class="divider">or</div>

        <div class="back-row">
            <a href="../pages/log_in.php">
                <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to sign in
            </a>
        </div>

        <div class="sec-note">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Both your username and email must match a registered account. Reset links expire after 1 hour and can only be used once.
        </div>

    </div><!-- /.fp-card -->

</body>
</html>