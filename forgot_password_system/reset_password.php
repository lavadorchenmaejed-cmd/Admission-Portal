<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../database.php';
$config = require __DIR__ . '/../config.php';
$db     = new Database($config);
$conn   = $db->getConnection();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$token = '';
$error = $success = '';

// ── GET: Show the reset form (token comes from the URL) ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim($_GET['token'] ?? '');

    if (empty($token)) {
        header("Location: forgot_password.php");
        exit;
    }

    // Validate token exists and is not expired
    $stmt = $conn->prepare("SELECT user_Id, username FROM user WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Invalid or expired reset link. <a href='forgot_password.php'>Request a new one</a>.");
    }

    $row = $result->fetch_assoc();

    // Store in session so it's never re-read from user input on POST
    $_SESSION['reset_token']    = $token;
    $_SESSION['reset_user_id']  = $row['user_Id'];
    $_SESSION['reset_username'] = $row['username'];
    session_regenerate_id(true);
}

// ── POST: Handle the password reset ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die("Invalid request.");
    }

    // Session token check — token is NEVER trusted from POST body
    if (empty($_SESSION['reset_token']) || empty($_SESSION['reset_user_id'])) {
        header("Location: forgot_password.php");
        exit;
    }

    // Re-validate token against DB (ensures it hasn't expired or been used)
    $sessionToken = $_SESSION['reset_token'];
    $stmt = $conn->prepare("SELECT user_Id, username FROM user WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $sessionToken);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Reset link expired. <a href='forgot_password.php'>Request a new one</a>.");
    }

    $row      = $result->fetch_assoc();
    $userId   = $row['user_Id'];
    $username = $row['username'];

    // Verify username input matches the account tied to the token
    $enteredUsername = trim($_POST['username'] ?? '');
    if (!hash_equals($username, $enteredUsername)) {
        $error = "Username does not match.";
    } else {
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword !== $confirmPassword) {
            $error = "Passwords do not match.";
        } elseif (strlen($newPassword) < 8) {
            $error = "Password must be at least 8 characters.";
        } else {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update password and invalidate token in one query
            $stmt = $conn->prepare("UPDATE user SET password = ?, reset_token = NULL, reset_expires = NULL WHERE user_Id = ?");
            $stmt->bind_param("si", $hashed, $userId);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Clear reset session data — token is single-use
                unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_username'], $_SESSION['csrf_token']);
                session_regenerate_id(true);
                $success = true;
            } else {
                $error = "Error updating password. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — NCC Admission Portal</title>

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* Reuse SAME design system */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --gold:#B8963E;
    --maroon:#7B1C1C;
    --maroon-d:#611515;
    --deep:#1A1A2E;
    --cream:#FAF7F2;
}

body {
    font-family:'DM Sans',sans-serif;
    background:var(--cream);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:2rem 1rem;
}

/* Card */
.fp-card {
    background:#fff;
    border-radius:14px;
    box-shadow:0 2px 24px rgba(0,0,0,0.06);
    max-width:420px;
    width:100%;
    padding:2.5rem;
    position:relative;
}

.fp-card::before {
    content:'';
    position:absolute;
    top:0; left:0; right:0;
    height:3px;
    background:linear-gradient(to right,var(--gold),var(--maroon));
}

/* Title */
.fp-title {
    font-family:'Playfair Display',serif;
    font-size:1.6rem;
    text-align:center;
    margin-bottom:0.4rem;
}

.fp-sub {
    text-align:center;
    font-size:0.85rem;
    color:#777;
    margin-bottom:1.8rem;
}

/* Steps */
.steps {
    display:flex;
    justify-content:center;
    align-items:center;
    margin-bottom:2rem;
}

.step-dot {
    width:28px;height:28px;
    border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:11px;
}

.active { background:var(--maroon); color:#fff; }
.pending { background:#eee; color:#aaa; }

.step-line {
    width:35px;
    height:1px;
    background:#ddd;
}

/* Alerts */
.alert {
    padding:0.8rem;
    border-radius:8px;
    font-size:0.85rem;
    margin-bottom:1rem;
}

.alert-error { background:#FEF2F2; color:#991B1B; }
.alert-success { background:#EDFBF3; color:#065F46; }

/* Inputs */
.field { margin-bottom:1rem; }

.field-input {
    width:100%;
    padding:0.75rem;
    border:1px solid #ddd;
    border-radius:6px;
}

/* Button */
.btn-submit {
    width:100%;
    padding:0.85rem;
    background:var(--maroon);
    color:#fff;
    border:none;
    border-radius:6px;
    cursor:pointer;
}

.btn-submit:hover { background:var(--maroon-d); }

/* Success */
.success-state {
    text-align:center;
}

.success-title {
    font-family:'Playfair Display',serif;
    font-size:1.4rem;
    margin-bottom:0.5rem;
}

.success-msg {
    font-size:0.85rem;
    color:#777;
    margin-bottom:1.5rem;
}
</style>
</head>

<body>

<div class="fp-card">

<?php if ($success): ?>

    <!-- SUCCESS -->
    <div class="success-state">
        <div class="success-title">Password Updated</div>
        <p class="success-msg">
            Your password has been successfully changed. You can now log in using your new credentials.
        </p>

        <div class="alert alert-success">
            Password reset successful.
        </div>

        <a href="../pages/log_in.php" class="btn-submit">Go to Login</a>
    </div>

<?php else: ?>

    <!-- FORM -->
    <div class="fp-title">Reset password</div>
    <div class="fp-sub">Enter your new password to complete the reset process.</div>

    <!-- Steps -->
    <div class="steps">
        <div class="step-dot active">1</div>
        <div class="step-line"></div>
        <div class="step-dot active">2</div>
        <div class="step-line"></div>
        <div class="step-dot active">3</div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="field">
            <input type="text" name="username" placeholder="Confirm username" required class="field-input">
        </div>

        <div class="field">
            <input type="password" name="new_password" placeholder="New password" required class="field-input">
        </div>

        <div class="field">
            <input type="password" name="confirm_password" placeholder="Confirm password" required class="field-input">
        </div>

        <button type="submit" class="btn-submit">Reset Password</button>
    </form>

<?php endif; ?>

</div>

</body>
</html>