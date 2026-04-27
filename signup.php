<?php
// ── signup.php ────────────────────────────────────────
// Handles both the signup form display (GET) and
// form submission / account creation (POST).

require_once __DIR__ . '/config/db.php';

$errors   = [];
$success  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $email    = trim($_POST['email']    ?? '');
  $pw1      = $_POST['pw1'] ?? '';
  $pw2      = $_POST['pw2'] ?? '';

  // ── Validation ────────────────────────────────────
  if (!$username) {
    $errors['username'] = 'Username is required.';
  } elseif (strlen($username) < 3) {
    $errors['username'] = 'Username must be at least 3 characters.';
  } elseif (strlen($username) > 50) {
    $errors['username'] = 'Username must not exceed 50 characters.';
  }

  if (!$email) {
    $errors['email'] = 'Email is required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
  }

  if (!$pw1) {
    $errors['pw1'] = 'Password is required.';
  } elseif (strlen($pw1) < 8) {
    $errors['pw1'] = 'Password must be at least 8 characters.';
  }

  if (!$pw2) {
    $errors['pw2'] = 'Please confirm your password.';
  } elseif ($pw1 !== $pw2) {
    $errors['pw2'] = 'Make sure you entered the same password.';
  }

  // ── DB checks & insert (only if no validation errors) ──
  if (empty($errors)) {
    try {
      $pdo = getDB();

      // Check username uniqueness
      $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
      $stmt->execute([$username]);
      if ($stmt->fetch()) {
        $errors['username'] = 'This username is already taken.';
      }

      // Check email uniqueness
      $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
      $stmt->execute([$email]);
      if ($stmt->fetch()) {
        $errors['email'] = 'An account with this email already exists.';
      }

      if (empty($errors)) {
        $hash = password_hash($pw1, PASSWORD_BCRYPT);
        $pdo->prepare("
          INSERT INTO users (username, email, password_hash)
          VALUES (:username, :email, :password_hash)
        ")->execute([
          ':username'      => $username,
          ':email'         => $email,
          ':password_hash' => $hash,
        ]);

        $success = true;
      }
    } catch (PDOException $e) {
      $errors['db'] = 'A server error occurred. Please try again later.';
    }
  }
}

// Helper: escape for HTML output
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Re-populate fields on error
$old_username = h($_POST['username'] ?? '');
$old_email    = h($_POST['email']    ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign Up</title>
  <link rel="icon" type="image/png" href="pics/logo.png"/>
  <link rel="stylesheet" href="CSS/signup.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600&family=Inter:wght@400;600&display=swap" rel="stylesheet"/>
</head>
<body>
<div class="page-wrapper">

  <a class="logo" href="landpage.html">
    <img src="pics/logo.png" alt="SmartEdu Logo" onerror="this.style.display='none'"/>
    <span class="logo-name">SmartEdu</span>
  </a>

  <div class="left-panel">
    <div class="card">
      <h1>Start your journey today!</h1>
      <p class="subtitle">Create an account and discover the right path.</p>

      <?php if ($success): ?>
        <!-- ── Success state ── -->
        <div class="success-banner">
          <p>🎉 Account created successfully!<br/>
          You can now <a href="login.php">log in here</a>.</p>
        </div>

      <?php else: ?>

        <?php if (!empty($errors['db'])): ?>
          <div class="db-error"><p><?= h($errors['db']) ?></p></div>
        <?php endif; ?>

        <form method="POST" action="signup.php" novalidate>

          <!-- Username -->
          <div class="input-group">
            <input
              type="text"
              name="username"
              id="username"
              placeholder="Username"
              value="<?= $old_username ?>"
              class="<?= isset($errors['username']) ? 'input-error' : '' ?>"
              autocomplete="username"
            />
          </div>
          <?php if (isset($errors['username'])): ?>
            <p class="error-msg visible"><?= h($errors['username']) ?></p>
          <?php endif; ?>

          <!-- Email -->
          <div class="input-group">
            <input
              type="email"
              name="email"
              id="email"
              placeholder="Email"
              value="<?= $old_email ?>"
              class="<?= isset($errors['email']) ? 'input-error' : '' ?>"
              autocomplete="email"
            />
          </div>
          <?php if (isset($errors['email'])): ?>
            <p class="error-msg visible"><?= h($errors['email']) ?></p>
          <?php endif; ?>

          <!-- Password -->
          <div class="input-group">
            <input
              type="password"
              name="pw1"
              id="pw1"
              placeholder="Password"
              class="<?= isset($errors['pw1']) ? 'input-error' : '' ?>"
              autocomplete="new-password"
            />
            <button type="button" class="toggle-btn" onclick="togglePw('pw1',this)" aria-label="Toggle password visibility">
              <svg id="eye-pw1" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <?php if (isset($errors['pw1'])): ?>
            <p class="error-msg visible"><?= h($errors['pw1']) ?></p>
          <?php endif; ?>

          <!-- Confirm Password -->
          <div class="input-group">
            <input
              type="password"
              name="pw2"
              id="pw2"
              placeholder="Confirm Password"
              class="<?= isset($errors['pw2']) ? 'input-error' : '' ?>"
              oninput="checkPasswords()"
              autocomplete="new-password"
            />
            <button type="button" class="toggle-btn" onclick="togglePw('pw2',this)" aria-label="Toggle confirm password visibility">
              <svg id="eye-pw2" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <p class="error-msg <?= isset($errors['pw2']) ? 'visible' : '' ?>" id="pw-error">
            <?= isset($errors['pw2']) ? h($errors['pw2']) : 'Make sure you entered the same password.' ?>
          </p>

          <button type="submit" class="btn-next">Sign Up</button>

        </form>

      <?php endif; ?>

      <p class="login-redirect">Already have an account? <a href="login.php">Log in!</a></p>
    </div>
  </div>

  <div class="illustration">
    <img src="pics/signup.png" alt=""/>
  </div>

</div>

<script src="JS/signup.js"></script>
</body>
</html>