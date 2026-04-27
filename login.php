<?php
require_once __DIR__ . '/config/db.php';
session_start();

// ── Constants ─────────────────────────────────────────
define('MAX_ATTEMPTS', 3);
define('LOCKOUT_SECONDS', 300); // 5 minutes

// ── Already logged in? ────────────────────────────────
if (!empty($_SESSION['user_id'])) {
  header('Location: studform.php');
  exit;
}
if (!empty($_SESSION['admin_id'])) {
  header('Location: dashb_admin.php');
  exit;
}

// ── Attempt tracking helpers ──────────────────────────
function getAttempts() {
  return $_SESSION['login_attempts'] ?? 0;
}
function getLockoutTime() {
  return $_SESSION['lockout_until'] ?? 0;
}
function isLockedOut() {
  return getLockoutTime() > time();
}
function incrementAttempts() {
  $_SESSION['login_attempts'] = getAttempts() + 1;
  if ($_SESSION['login_attempts'] >= MAX_ATTEMPTS) {
    $_SESSION['lockout_until'] = time() + LOCKOUT_SECONDS;
  }
}
function resetAttempts() {
  unset($_SESSION['login_attempts'], $_SESSION['lockout_until']);
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  // ── Blank fields ───────────────────────────────────
  if (!$username || !$password) {
    $error = 'Please enter your username and password.';

  // ── Locked out ────────────────────────────────────
  } elseif (isLockedOut()) {
    $remaining = getLockoutTime() - time();
    $mins = ceil($remaining / 60);
    $error = 'Too many failed attempts. Please try again in ' . $mins . ' minute(s).';

  } else {
    try {
      $pdo = getDB();
      $matched = false;

      // ── Check admins table first ───────────────────
      $stmt = $pdo->prepare("SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1");
      $stmt->execute([$username]);
      $admin = $stmt->fetch();

      if ($admin && password_verify($password, $admin['password_hash'])) {
        resetAttempts();
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $username;
        header('Location: dashb_admin.php');
        exit;
      }

      // ── Check users table ──────────────────────────
      $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ? LIMIT 1");
      $stmt->execute([$username]);
      $user = $stmt->fetch();

      if ($user && password_verify($password, $user['password_hash'])) {
        resetAttempts();
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $username;
        header('Location: studform.php');
        exit;
      }

      // ── Failed login ───────────────────────────────
      incrementAttempts();
      $attempts   = getAttempts();
      $remaining  = MAX_ATTEMPTS - $attempts;

      if ($attempts >= MAX_ATTEMPTS) {
        $mins  = ceil(LOCKOUT_SECONDS / 60);
        $error = $attempts . ' attempt(s) used. Account locked for ' . $mins . ' minute(s). Reset password or try later.';
      } else {
        $error = 'Incorrect username or password. ' . $remaining . ' attempt(s) remaining.';
      }

    } catch (PDOException $e) {
      $error = 'A server error occurred. Please try again later.';
    }
  }
}

$attempts_used = getAttempts();
$locked        = isLockedOut();

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$old_username = h($_POST['username'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Log In</title>
  <link rel="icon" type="image/png" href="pics/logo.png"/>
  <link rel="stylesheet" href="CSS/login.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600&family=Inter:wght@400;600&display=swap" rel="stylesheet"/>
</head>
<body>
<div class="page-wrapper">

  <a class="logo" href="landpage.php">
    <img src="pics/logo.png" alt="SmartEdu Logo" onerror="this.style.display='none'"/>
    <span class="logo-name">SmartEdu</span>
  </a>

  <span class="page-title">Your Future Starts Here!</span>

  <div class="illustration">
    <img src="pics/login.png" alt="Team high-five illustration"/>
  </div>

  <div class="right-panel">
    <div class="card">
      <h1>Welcome Back!</h1>
      <p class="subtitle">Log in to continue your journey.</p>

      <form method="POST" action="login.php" novalidate>

        <!-- Username -->
        <div class="input-group">
          <input
            type="text"
            name="username"
            id="username"
            placeholder="Username"
            value="<?= $old_username ?>"
            <?= $locked ? 'disabled' : '' ?>
            autocomplete="username"
          />
        </div>

        <!-- Password -->
        <div class="input-group">
          <input
            type="password"
            name="password"
            id="password"
            placeholder="Password"
            <?= $locked ? 'disabled' : '' ?>
            autocomplete="current-password"
          />
          <button type="button" class="toggle-btn" onclick="togglePw('password',this)" aria-label="Toggle password visibility">
            <svg id="eye-password" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>

        <!-- Server error -->
        <?php if ($error): ?>
          <div class="error-row visible" id="error-row">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <p id="error-text"><?= h($error) ?></p>
          </div>
        <?php else: ?>
          <div class="error-row" id="error-row">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <p id="error-text"></p>
          </div>
        <?php endif; ?>

        <!-- Attempt counter hint -->
        <?php if ($attempts_used > 0 && !$locked): ?>
          <p class="attempts-row visible"><?= $attempts_used ?>/<?= MAX_ATTEMPTS ?> attempts used</p>
        <?php else: ?>
          <p class="attempts-row" id="attempts-row"></p>
        <?php endif; ?>

        <a href="forgetpass.php" class="forgot">Forgot Password?</a>

        <div class="divider">
          <div class="divider-line"></div>
          <span class="divider-text">Don't have an account? <a href="signup.php">Create</a></span>
          <div class="divider-line"></div>
        </div>

        <button type="submit" class="btn-login" id="btn-login" <?= $locked ? 'disabled' : '' ?>>Log In</button>

      </form>

      <button class="btn-google" onclick="alert('Google login coming soon.')">
        <svg class="google-icon" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
          <path fill="#EA4335" d="M24 9.5c3.14 0 5.95 1.08 8.17 2.85l6.1-6.1C34.46 3.07 29.54 1 24 1 14.82 1 7.07 6.48 3.64 14.22l7.08 5.5C12.43 13.67 17.73 9.5 24 9.5z"/>
          <path fill="#4285F4" d="M46.54 24.5c0-1.64-.15-3.22-.42-4.75H24v9h12.67c-.55 2.9-2.2 5.36-4.67 7.02l7.17 5.57C43.36 37.28 46.54 31.36 46.54 24.5z"/>
          <path fill="#FBBC05" d="M10.72 28.28A14.6 14.6 0 0 1 9.5 24c0-1.49.26-2.93.72-4.28l-7.08-5.5A23.94 23.94 0 0 0 0 24c0 3.87.93 7.52 2.58 10.74l8.14-6.46z"/>
          <path fill="#34A853" d="M24 47c5.54 0 10.2-1.84 13.6-4.99l-7.17-5.57c-1.84 1.23-4.2 1.96-6.43 1.96-6.27 0-11.57-4.17-13.28-9.72l-8.14 6.46C7.07 41.52 14.82 47 24 47z"/>
        </svg>
        Log In with Google account
      </button>

    </div>
  </div>

</div>

<script src="JS/login.js"></script>
</body>
</html>