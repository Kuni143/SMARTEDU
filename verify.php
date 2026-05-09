<?php
/**
 * verify.php
 * ──────────
 * Handles the email-verification link:
 *   /verify.php?token=<64-char-hex>
 *
 * On success : marks the user as verified and redirects to login.php
 * On failure : shows a friendly error with a "resend" option
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/send_verification.php';

$status  = '';   // 'success' | 'expired' | 'invalid' | 'already'
$message = '';

$token = trim($_GET['token'] ?? '');

// ── Handle "Resend verification" POST ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['resend_email'])) {
    $resendEmail = trim($_POST['resend_email']);
    $resent      = false;

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("
            SELECT id, username, is_verified
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$resendEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !$user['is_verified']) {
            // Generate a fresh token
            $newToken  = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $pdo->prepare("
                UPDATE users
                SET verification_token = ?, token_expires_at = ?
                WHERE id = ?
            ")->execute([$newToken, $expiresAt, $user['id']]);

            $resent = sendVerificationEmail($resendEmail, $user['username'], $newToken);
        }
    } catch (PDOException $e) {
        // silently fail — generic message shown below
    }

    $status  = 'resent';
    $message = $resent
        ? 'A new verification link has been sent. Please check your inbox.'
        : 'We could not send a new link. Please try again later or contact support.';
}

// ── Verify the token ─────────────────────────────────────────────────────────
if ($status === '' && $token !== '') {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("
            SELECT id, is_verified, token_expires_at
            FROM users
            WHERE verification_token = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $status  = 'invalid';
            $message = 'This verification link is invalid.';
        } elseif ($user['is_verified']) {
            $status  = 'already';
            $message = 'Your account is already verified. You can log in below.';
        } elseif (strtotime($user['token_expires_at']) < time()) {
            $status  = 'expired';
            $message = 'This verification link has expired.';
        } else {
            // ── Activate the account ──────────────────────────────────────────
            $pdo->prepare("
                UPDATE users
                SET is_verified          = 1,
                    verification_token   = NULL,
                    token_expires_at     = NULL
                WHERE id = ?
            ")->execute([$user['id']]);

            $status  = 'success';
            $message = 'Your email has been verified! You can now log in.';
        }
    } catch (PDOException $e) {
        $status  = 'invalid';
        $message = 'A server error occurred. Please try again later.';
    }
} elseif ($status === '' && $token === '') {
    $status  = 'invalid';
    $message = 'No verification token was provided.';
}

// Helper
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Email Verification</title>
  <link rel="icon" type="image/png" href="pics/logo.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600&family=Inter:wght@400;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html, body { width:100%; height:100%; }
    body {
      font-family:'Sora',sans-serif;
      background:#ced4df;
      display:flex;
      align-items:center;
      justify-content:center;
      min-height:100vh;
      padding:24px;
    }

    .logo {
      position:fixed; top:24px; left:36px;
      display:flex; align-items:center; gap:10px;
      text-decoration:none; z-index:10;
    }
    .logo img { width:52px; height:52px; object-fit:cover; }
    .logo-name { font-family:'Sora',sans-serif; font-size:15px; font-weight:600; color:#101d89; }

    .card {
      background:#061685;
      border-radius:22px;
      padding:40px 36px 36px;
      width:100%;
      max-width:440px;
      display:flex;
      flex-direction:column;
      align-items:center;
      text-align:center;
      gap:16px;
    }

    .status-icon { font-size:48px; line-height:1; }

    .card h1 {
      font-family:'Sora',sans-serif;
      font-size:clamp(18px,2vw,22px);
      font-weight:600;
      color:#FBFCFF;
      line-height:1.3;
    }

    .card p {
      font-family:'Inter',sans-serif;
      font-size:13.5px;
      color:rgba(251,252,255,0.8);
      line-height:1.65;
    }

    .alert-success { color:#86efac; }
    .alert-info    { color:#93c5fd; }
    .alert-warning { color:#fcd34d; }
    .alert-error   { color:#fca5a5; }

    .btn {
      display:inline-block;
      margin-top:4px;
      padding:12px 36px;
      background:#1E5ABC;
      border:none;
      border-radius:25px;
      font-family:'Sora',sans-serif;
      font-size:15px;
      font-weight:600;
      color:#F2EEE9;
      cursor:pointer;
      text-decoration:none;
      transition:opacity .2s, transform .15s;
    }
    .btn:hover { opacity:.88; transform:translateY(-1px); }

    /* Resend form */
    .resend-form {
      width:100%;
      display:flex;
      flex-direction:column;
      gap:10px;
      align-items:center;
    }
    .resend-form p {
      font-size:12px;
      color:rgba(251,252,255,0.55);
    }
    .resend-row {
      display:flex;
      gap:8px;
      width:100%;
    }
    .resend-row input {
      flex:1;
      height:44px;
      border-radius:22px;
      border:none;
      outline:none;
      padding:0 18px;
      font-family:'Inter',sans-serif;
      font-size:13px;
      color:rgba(6,22,133,0.7);
      background:#fff;
    }
    .resend-row input:focus { box-shadow:0 0 0 3px rgba(139,178,253,0.5); }
    .resend-row button {
      height:44px;
      padding:0 20px;
      background:#1E5ABC;
      border:none;
      border-radius:22px;
      font-family:'Sora',sans-serif;
      font-size:13px;
      font-weight:600;
      color:#fff;
      cursor:pointer;
      white-space:nowrap;
      transition:opacity .2s;
    }
    .resend-row button:hover { opacity:.88; }

    .divider {
      width:100%;
      height:1px;
      background:rgba(255,255,255,0.12);
    }

    .login-link {
      font-family:'Inter',sans-serif;
      font-size:12.5px;
      color:rgba(251,252,255,0.6);
    }
    .login-link a { color:#8BB2FD; font-weight:600; text-decoration:none; }
    .login-link a:hover { text-decoration:underline; }
  </style>
</head>
<body>

<a class="logo" href="landpage.php">
  <img src="pics/logo.png" alt="SmartEdu Logo" onerror="this.style.display='none'"/>
  <span class="logo-name">SmartEdu</span>
</a>

<div class="card">

  <?php if ($status === 'success'): ?>
    <div class="status-icon">✅</div>
    <h1>Email Verified!</h1>
    <p class="alert-success"><?= h($message) ?></p>
    <a class="btn" href="login.php">Log In Now</a>

  <?php elseif ($status === 'already'): ?>
    <div class="status-icon">👍</div>
    <h1>Already Verified</h1>
    <p class="alert-info"><?= h($message) ?></p>
    <a class="btn" href="login.php">Log In</a>

  <?php elseif ($status === 'resent'): ?>
    <div class="status-icon">📨</div>
    <h1>Check your inbox</h1>
    <p class="alert-info"><?= h($message) ?></p>
    <div class="divider"></div>
    <p class="login-link">Back to <a href="signup.php">Sign Up</a></p>

  <?php elseif ($status === 'expired'): ?>
    <div class="status-icon">⏰</div>
    <h1>Link Expired</h1>
    <p class="alert-warning"><?= h($message) ?> Enter your email below and we'll send you a new one.</p>

    <form class="resend-form" method="POST" action="verify.php">
      <div class="resend-row">
        <input type="email" name="resend_email" placeholder="your@email.com" required autocomplete="email"/>
        <button type="submit">Resend</button>
      </div>
    </form>

    <div class="divider"></div>
    <p class="login-link">Remember your password? <a href="login.php">Log In</a></p>

  <?php else: /* invalid */ ?>
    <div class="status-icon">❌</div>
    <h1>Invalid Link</h1>
    <p class="alert-error"><?= h($message) ?></p>
    <p style="font-size:12.5px;color:rgba(251,252,255,0.55);">
      The link may have already been used or may be malformed.
    </p>

    <form class="resend-form" method="POST" action="verify.php">
      <p>Need a new verification link?</p>
      <div class="resend-row">
        <input type="email" name="resend_email" placeholder="your@email.com" required autocomplete="email"/>
        <button type="submit">Resend</button>
      </div>
    </form>

    <div class="divider"></div>
    <p class="login-link">New here? <a href="signup.php">Sign Up</a></p>
  <?php endif; ?>

</div>

</body>
</html>