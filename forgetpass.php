<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_samesite', 'Lax');
    session_name('SMARTEDU_SESSION');
    session_start();
}

date_default_timezone_set('UTC');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USER', 'smartedu.fr@gmail.com');
define('MAIL_PASS', 'fqxb minm zsji wwcd');
define('MAIL_PORT', 587);
define('MAIL_FROM', 'smartedu.fr@gmail.com');
define('MAIL_NAME', 'SmartEdu');

define('OTP_TTL', 300);
define('OTP_RESEND', 60);

function jsonResponse($arr) {
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}

if (isset($_GET['ajax'])) {

    $pdo = getDB();
    $pdo->exec("SET time_zone = '+00:00'");
    $action = $_GET['ajax'];

    // ── SEND OTP ─────────────────────────────
    if ($action === 'send_otp' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $body  = json_decode(file_get_contents('php://input'), true);
        $email = trim($body['email'] ?? '');

        if ($email === '__resend__') {
            $email = $_SESSION['reset_email'] ?? '';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'error' => 'invalid_email']);
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'error' => 'not_found']);
        }

        $stmt = $pdo->prepare("
            SELECT created_at FROM password_resets
            WHERE email = ? AND used = 0
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$email]);
        $recent = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($recent) {
            $age = time() - strtotime($recent['created_at']);
            if ($age < OTP_RESEND) {
                jsonResponse(['success' => false, 'error' => 'too_soon', 'wait' => OTP_RESEND - $age]);
            }
        }

        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0")->execute([$email]);

        $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash    = password_hash($otp, PASSWORD_BCRYPT);
        $expires = date('Y-m-d H:i:s', time() + OTP_TTL);

        $pdo->prepare("
            INSERT INTO password_resets (email, otp_hash, expires_at, used)
            VALUES (?, ?, ?, 0)
        ")->execute([$email, $hash, $expires]);

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
            $mail->setFrom(MAIL_FROM, MAIL_NAME);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'SmartEdu: Your Password Reset Code';
            $mail->Body    = '
              <div style="font-family:Sora,sans-serif;max-width:480px;margin:0 auto;padding:32px;background:#f7f8fc;border-radius:16px;">
                <h2 style="color:#061685;margin-bottom:8px;">Password Reset</h2>
                <p style="color:#3a4a7a;margin-bottom:24px;">Use the code below to reset your SmartEdu password. It expires in <strong>5 minutes</strong>.</p>
                <div style="background:#061685;border-radius:12px;padding:20px 32px;text-align:center;letter-spacing:12px;font-size:32px;font-weight:700;color:#fff;margin-bottom:24px;">' . htmlspecialchars($otp) . '</div>
                <p style="color:#8b9fd4;font-size:13px;">If you did not request this, you can safely ignore this email.</p>
              </div>';
            $mail->AltBody = 'Your SmartEdu password reset code is: ' . $otp . '. It expires in 5 minutes.';
            $mail->send();
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'mail_failed']);
        }

        $_SESSION['reset_email'] = $email;
        jsonResponse(['success' => true]);
    }

    // ── VERIFY OTP ───────────────────────────
    if ($action === 'verify_otp' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $body  = json_decode(file_get_contents('php://input'), true);
        $otp   = trim($body['otp'] ?? '');
        $email = $_SESSION['reset_email'] ?? '';

        if (!$email || !preg_match('/^\d{6}$/', $otp)) {
            jsonResponse(['success' => false, 'error' => 'invalid']);
        }

        $current = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            SELECT id, otp_hash, expires_at
            FROM password_resets
            WHERE email = ? AND used = 0
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            jsonResponse(['success' => false, 'error' => 'wrong_otp']);
        }

        if ($row['expires_at'] < $current) {
            jsonResponse(['success' => false, 'error' => 'expired']);
        }

        if (!password_verify($otp, $row['otp_hash'])) {
            jsonResponse(['success' => false, 'error' => 'wrong_otp']);
        }

        $_SESSION['reset_verified'] = true;
        $_SESSION['reset_otp_id']   = $row['id'];

        jsonResponse(['success' => true]);
    }

    // ── RESET PASSWORD ───────────────────────
    if ($action === 'reset_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $body  = json_decode(file_get_contents('php://input'), true);
        $pw    = $body['password'] ?? '';
        $email = $_SESSION['reset_email']    ?? '';
        $ok    = $_SESSION['reset_verified'] ?? false;
        $otpId = $_SESSION['reset_otp_id']   ?? null;

        if (!$email || !$ok || !$otpId) {
            jsonResponse(['success' => false, 'error' => 'session_expired']);
        }

        if (strlen($pw) < 8) {
            jsonResponse(['success' => false, 'error' => 'too_short']);
        }

        $hash = password_hash($pw, PASSWORD_BCRYPT);

        $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?")
            ->execute([$hash, $email]);

        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")
            ->execute([$otpId]);

        unset($_SESSION['reset_email'], $_SESSION['reset_verified'], $_SESSION['reset_otp_id']);

        jsonResponse(['success' => true]);
    }

    jsonResponse(['success' => false, 'error' => 'invalid_action']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password – SmartEdu</title>
  <link rel="icon" type="image/png" href="pics/logo.png"/>
  <link rel="stylesheet" href="css/forgetpass.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600&family=Inter:wght@400;600&display=swap" rel="stylesheet"/>
</head>
<body>
<div class="page-wrapper">

  <a class="logo" href="landpage.html">
    <img src="pics/logo.png" alt="SmartEdu Logo" onerror="this.style.display='none'"/>
    <span class="logo-name">SmartEdu</span>
  </a>

  <div class="left-panel">
    <img src="pics/fpass.png" alt="Forgot password illustration"/>
  </div>

  <div class="right-panel">
    <div class="card">

      <!-- ── STEP 1: Email ── -->
      <div class="step-panel active" id="step1">
        <div class="stepper">
          <div class="step-dot active">1</div>
          <div class="step-line"></div>
          <div class="step-dot inactive">2</div>
          <div class="step-line"></div>
          <div class="step-dot inactive">3</div>
        </div>
        <h2>Forgot Your Password?</h2>
        <p class="subtitle">Enter your email to receive a verification code.</p>
        <div class="input-group">
          <input type="email" placeholder="Email" id="emailInput"/>
        </div>
        <div class="error-line" id="emailErr">
          <div class="err-icon">!</div>
          <span id="emailErrText">We couldn't find that email. Please check and re-enter.</span>
        </div>
        <button class="btn-primary" id="btnSendOtp" onclick="goStep2()">Get OTP</button>
        <p class="bottom-link"><i>Back to <a href="login.php">Log In</a></i></p>
      </div>

      <!-- ── STEP 2: OTP ── -->
      <div class="step-panel" id="step2">
        <div class="stepper">
          <div class="step-dot done">&#10003;</div>
          <div class="step-line done"></div>
          <div class="step-dot active">2</div>
          <div class="step-line"></div>
          <div class="step-dot inactive">3</div>
        </div>
        <h2>Check your Email</h2>
        <p class="subtitle">Enter the 6-digit code sent to your email. It expires in 5 minutes.</p>
        <div class="otp-row">
          <input type="text" inputmode="numeric" maxlength="1" class="otp-box" oninput="otpMove(this,0)" onkeydown="otpBack(event,0)"/>
          <input type="text" inputmode="numeric" maxlength="1" class="otp-box" oninput="otpMove(this,1)" onkeydown="otpBack(event,1)"/>
          <input type="text" inputmode="numeric" maxlength="1" class="otp-box" oninput="otpMove(this,2)" onkeydown="otpBack(event,2)"/>
          <input type="text" inputmode="numeric" maxlength="1" class="otp-box" oninput="otpMove(this,3)" onkeydown="otpBack(event,3)"/>
          <input type="text" inputmode="numeric" maxlength="1" class="otp-box" oninput="otpMove(this,4)" onkeydown="otpBack(event,4)"/>
          <input type="text" inputmode="numeric" maxlength="1" class="otp-box" oninput="otpMove(this,5)" onkeydown="otpBack(event,5)"/>
        </div>
        <div class="error-line" id="otpErr">
          <div class="err-icon">!</div>
          <span id="otpErrText">Incorrect or expired code. Please try again.</span>
        </div>
        <p class="timer-text">Code expires in: <span id="countdown">5:00</span></p>
        <button class="btn-white" id="btnVerify" onclick="goStep3()">Verify</button>
        <p class="bottom-link"><i>Didn't get the code? <a href="#" onclick="resendOtp(); return false;">Resend</a></i></p>
      </div>

      <!-- ── STEP 3: Reset Password ── -->
      <div class="step-panel" id="step3">
        <div class="stepper">
          <div class="step-dot done">&#10003;</div>
          <div class="step-line done"></div>
          <div class="step-dot done">&#10003;</div>
          <div class="step-line done"></div>
          <div class="step-dot active">3</div>
        </div>
        <h2>Reset your Password</h2>
        <p class="subtitle">You're almost done! Create your new password (min. 8 characters).</p>
        <div class="input-group">
          <input type="password" placeholder="New Password" id="np1" oninput="clearResetErr()"/>
          <button type="button" class="toggle-btn" onclick="togglePw('np1', this)" aria-label="Toggle password visibility">
            <svg id="eye-np1" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <div class="input-group">
          <input type="password" placeholder="Confirm Password" id="np2" oninput="clearResetErr()"/>
          <button type="button" class="toggle-btn" onclick="togglePw('np2', this)" aria-label="Toggle password visibility">
            <svg id="eye-np2" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <div class="error-line" id="resetErr">
          <div class="err-icon">!</div>
          <span id="resetErrText">Both passwords must match and be at least 8 characters.</span>
        </div>
        <button class="btn-white" id="btnDone" onclick="handleDone()">Done</button>
      </div>

    </div>
  </div>
</div>

<script src="js/forgetpass.js"></script>
</body>
</html>