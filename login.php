<?php
require_once __DIR__ . '/config/db.php';
session_start();

// ── Constants ─────────────────────────────────────────
define('MAX_ATTEMPTS', 3);
define('LOCKOUT_SECONDS', 300); // 5 minutes

// ── Already logged in? ────────────────────────────────
if (!empty($_SESSION['user_id'])) {
    try {
        $pdo = getDB();
        $checkStmt = $pdo->prepare("
            SELECT s.id
            FROM students s
            INNER JOIN student_results sr ON sr.student_id = s.id
            WHERE s.user_id = :uid
            LIMIT 1
        ");
        $checkStmt->execute([':uid' => $_SESSION['user_id']]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $_SESSION['student_id'] = $existing['id'];
            header('Location: dashb_user.php');
        } else {
            header('Location: studform.php');
        }
    } catch (PDOException $e) {
        header('Location: studform.php');
    }
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

$error          = '';
$success        = false;
$lockoutSeconds = 0;
$loginSuccess   = false;
$redirectTarget = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  // ── Blank fields ───────────────────────────────────
  if (!$username || !$password) {
    $error = 'Please enter your username and password.';

  // ── Locked out ────────────────────────────────────
  } elseif (isLockedOut()) {
    $lockoutSeconds = getLockoutTime() - time();
    $error = 'Too many failed attempts. Please try again in <span id="lockout-timer"></span>';

  } else {
    try {
      $pdo = getDB();

      // ── Check admins table first ───────────────────
      $stmt = $pdo->prepare("SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1");
      $stmt->execute([$username]);
      $admin = $stmt->fetch();

      if ($admin && password_verify($password, $admin['password_hash'])) {
        resetAttempts();
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $username;
        $loginSuccess   = true;
        $redirectTarget = 'dashb_admin.php';
      }

      // ── Check users table ──────────────────────────
      if (!$loginSuccess) {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
          resetAttempts();
          $_SESSION['user_id']  = $user['id'];
          $_SESSION['username'] = $username;

          // ── Check if user already completed the form ───
          $checkStmt = $pdo->prepare("
              SELECT s.id
              FROM students s
              INNER JOIN student_results sr ON sr.student_id = s.id
              WHERE s.user_id = :uid
              LIMIT 1
          ");
          $checkStmt->execute([':uid' => $user['id']]);
          $existing = $checkStmt->fetch();

          if ($existing) {
              $_SESSION['student_id'] = $existing['id'];
              $redirectTarget = 'dashb_user.php';
          } else {
              $redirectTarget = 'studform.php';
          }
          $loginSuccess = true;
        }
      }

      // ── Failed login ───────────────────────────────
      if (!$loginSuccess) {
        incrementAttempts();
        $attempts  = getAttempts();
        $remaining = MAX_ATTEMPTS - $attempts;

        if ($attempts >= MAX_ATTEMPTS) {
          $lockoutSeconds = LOCKOUT_SECONDS;
          $error = 'Too many failed attempts. Please try again in <span id="lockout-timer"></span>';
        } else {
          $error = 'Incorrect username or password. ' . $remaining . ' attempt(s) remaining.';
        }
      }

    } catch (PDOException $e) {
      $error = 'A server error occurred. Please try again later.';
    }
  }
}

// Re-check lockout for page load (e.g. on refresh while locked)
if (!$error && !$loginSuccess && isLockedOut()) {
  $lockoutSeconds = getLockoutTime() - time();
  $error = 'Too many failed attempts. Please try again in <span id="lockout-timer"></span>';
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
          <div class="error-row visible" id="error-row" data-lockout="<?= (int)$lockoutSeconds ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <p id="error-text"><?= $error ?></p>
          </div>
        <?php else: ?>
          <div class="error-row" id="error-row" data-lockout="0">
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
    </div>
  </div>

  <!-- Login success toast -->
  <div class="login-toast" id="loginToast" style="display:none;">
    <div class="login-toast-inner">
      <span class="login-toast-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10" fill="#22c55e" stroke="none"/>
          <polyline points="8 12 11 15 16 9" stroke="#fff"/>
        </svg>
      </span>
      <span class="login-toast-msg">Login successful! Redirecting…</span>
      <button class="login-toast-close" onclick="document.getElementById('loginToast').style.display='none'">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/>
          <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
  </div>

  <?php if ($loginSuccess): ?>
  <span id="loginRedirect" data-href="<?= h($redirectTarget) ?>" style="display:none;"></span>
  <?php endif; ?>

</div>

<script src="JS/login.js"></script>
</body>
</html>