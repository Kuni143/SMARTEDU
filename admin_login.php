<?php
session_start();

/* ── DB config ── */
$host   = 'localhost';
$dbname = 'smartedu';
$user   = 'root';
$pass   = '';

$loginError   = '';
$loginSuccess = false;
$locked       = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $loginError = 'Please enter your username and password.';

    } else {

        /* ── Rate limiting via session ── */
        if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
        if (!isset($_SESSION['lockout_until']))  $_SESSION['lockout_until']  = 0;

        $maxAttempts = 3;
        $lockoutSecs = 5 * 60; // 5 minutes

        if (time() < $_SESSION['lockout_until']) {
            $remaining  = ceil(($_SESSION['lockout_until'] - time()) / 60);
            $loginError = 'Too many failed attempts. Please try again in ' . $remaining . ' minute(s).';
            $locked     = true;

        } else {
            /* ── Connect to DB ── */
            try {
                $pdo = new PDO(
                    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                    $user, $pass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin && password_verify($password, $admin['password_hash'])) {
                    /* ── Success ── */
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['lockout_until']  = 0;
                    $_SESSION['admin_id']       = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $loginSuccess = true;

                } else {
                    /* ── Failed attempt ── */
                    $_SESSION['login_attempts']++;
                    $used      = $_SESSION['login_attempts'];
                    $remaining = $maxAttempts - $used;

                    if ($used >= $maxAttempts) {
                        $_SESSION['lockout_until'] = time() + $lockoutSecs;
                        $loginError = $used . ' attempts used. Please try again later or Reset password.';
                        $locked     = true;
                    } else {
                        $loginError = 'Incorrect username or password. ' . $remaining . ' attempt(s) remaining.';
                    }
                }

            } catch (PDOException $e) {
                $loginError = 'Database connection failed. Please try again later.';
            }
        }
    }

    /* Redirect on success — no HTML rendered */
    if ($loginSuccess) {
        header('Location: dashb_admin.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="icon" type="image/png" href="pics/logo.png">
  <title>Admin Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="CSS/admin_login.css">
</head>

<body>

<!-- Toast -->
<div class="toast" id="toast">
  <div class="toast-icon">
    <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="20 6 9 17 4 12"/>
    </svg>
  </div>
  <div class="toast-body">
    <p>Login successful.<br>Welcome back, Admin!</p>
  </div>
  <button class="toast-close" onclick="closeToast()">&#x2715;</button>
</div>

<div class="page-wrapper">

  <!-- Logo -->
  <a class="logo" href="admin_login.php">
    <img src="pics/logo.png" alt="SmartEdu Logo" />
    <span class="logo-name">SmartEdu</span>
  </a>

  <!-- Left illustration -->
  <div class="illustration">
    <img src="pics/login.png" alt="Login illustration" />
  </div>

  <!-- Right card -->
  <div class="right-panel">
    <div class="card">
      <h1>Welcome Back<br>Admin!</h1>

      <form method="POST" action="admin_login.php" onsubmit="return handleSubmit(event)">

        <div class="input-group">
          <input
            type="text"
            name="username"
            placeholder="Username:"
            id="username"
            autocomplete="username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
          />
        </div>

        <div class="input-group">
          <input
            type="password"
            name="password"
            placeholder="Password:"
            id="password"
            autocomplete="current-password"
          />
          <button type="button" class="toggle-btn" onclick="togglePw('password', this)" aria-label="Toggle password visibility">
            <svg id="eye-password" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>

        <!-- Error row — populated by PHP on page load or JS on empty check -->
        <div class="error-row<?= $loginError ? ' visible' : '' ?>" id="error-row">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <p id="error-text"><?= htmlspecialchars($loginError) ?></p>
        </div>

        <a href="forgetpass.html" class="forgot">Forgot Password?</a>

        <button
          type="submit"
          class="btn-login"
          id="loginBtn"
          <?= $locked ? 'disabled' : '' ?>
        ><?= $locked ? 'Locked Out' : 'Log In' ?></button>

      </form>
    </div>
  </div>

</div>

<script src="JS/admin_login.js"></script>

</body>
</html>