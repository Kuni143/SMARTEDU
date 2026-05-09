<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/send_verification.php';   // helper – see send_verification.php

$errors   = [];
$success  = false;   // true  → insert OK, verification email sent
$pending  = false;   // alias kept for template readability

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $email    = trim($_POST['email']    ?? '');
  $pw1      = $_POST['pw1'] ?? '';
  $pw2      = $_POST['pw2'] ?? '';
  $terms    = $_POST['terms'] ?? '';

  // ── Validation ────────────────────────────────────────────────────────────
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

  if (!$terms) {
    $errors['terms'] = 'You must agree to the Terms and Conditions to sign up.';
  }

  // ── DB checks & insert (only if no validation errors) ────────────────────
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
        // Hash password
        $hash = password_hash($pw1, PASSWORD_BCRYPT);

        // Generate a cryptographically secure verification token
        $token     = bin2hex(random_bytes(32));   // 64-char hex string
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // ── Send email FIRST before touching the DB ───────────────────────────
        // This prevents orphaned unverified accounts when email is misconfigured.
        $sent = sendVerificationEmail($email, $username, $token);

        if (!$sent) {
          $errors['db'] = 'We could not send a verification email to that address. '
                        . 'Please double-check it and try again, or contact support.';
        } else {
          // Email sent OK — now safe to insert the user
          $pdo->prepare("
            INSERT INTO users (username, email, password_hash, is_verified, verification_token, token_expires_at)
            VALUES (:username, :email, :password_hash, 0, :token, :expires)
          ")->execute([
            ':username'      => $username,
            ':email'         => $email,
            ':password_hash' => $hash,
            ':token'         => $token,
            ':expires'       => $expiresAt,
          ]);

          $success = true;
          $pending = true;
        }
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

  <a class="logo" href="landpage.php">
    <img src="pics/logo.png" alt="SmartEdu Logo" onerror="this.style.display='none'"/>
    <span class="logo-name">SmartEdu</span>
  </a>

  <div class="left-panel">
    <div class="card">
      <h1>Start your journey today!</h1>

      <?php if ($success): ?>
        <!-- ── Pending-verification state ── -->
        <div class="success-banner">
          <div class="success-icon">✉️</div>
          <p><strong>Check your inbox!</strong></p>
          <p class="success-sub">
            We sent a verification link to<br/>
            <strong><?= h($_POST['email'] ?? '') ?></strong>
          </p>
          <p class="success-hint">
            Click the link in the email to activate your account.
            The link expires in&nbsp;<strong>24&nbsp;hours</strong>.
          </p>
          <p class="success-spam">Can't find it? Check your spam or junk folder.</p>
        </div>

      <?php else: ?>
        <p class="subtitle">Create an account and discover the right path.</p>

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
            <p class="error-msg visible" id="username-error"><?= h($errors['username']) ?></p>
          <?php else: ?>
            <p class="error-msg" id="username-error"></p>
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
          <?php else: ?>
            <p class="error-msg" id="email-error"></p>
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
          <?php else: ?>
            <p class="error-msg" id="pw1-error"></p>
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

          <!-- Terms and Conditions -->
          <div class="terms-row">
            <input type="checkbox" name="terms" id="terms" value="1" <?= !empty($_POST['terms']) ? 'checked' : '' ?> />
            <label for="terms">
              I have read and agree to the <button type="button" class="terms-link" onclick="openTerms()">Terms and Conditions</button>
            </label>
          </div>
          <?php if (isset($errors['terms'])): ?>
            <p class="error-msg visible" id="terms-error"><?= h($errors['terms']) ?></p>
          <?php else: ?>
            <p class="error-msg" id="terms-error"></p>
          <?php endif; ?>

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

<!-- ── Terms and Conditions Modal ── -->
<div class="modal-overlay" id="termsModal" onclick="closeTermsOutside(event)">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Terms and Conditions</h2>
      <button class="modal-close" onclick="closeTerms()" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body" id="termsModalBody">

      <p class="modal-intro">Please read these Terms and Conditions carefully before creating a SmartEdu account. By signing up, you acknowledge that you have read, understood, and agree to be bound by these terms.</p>

      <h3>1. Information We Collect</h3>
      <p>When you create an account and use SmartEdu, we collect the following information:</p>
      <ul>
        <li><strong>Account information:</strong> Your username and email address.</li>
        <li><strong>Academic profile:</strong> Your grade level (Grade 11 or 12), strand, and optionally your General Weighted Average (GWA).</li>
        <li><strong>Assessment responses:</strong> Your answers to 60 questions covering your interests, skills, academic strengths, strand alignment, and career preferences.</li>
      </ul>

      <h3>2. How We Use Your Information</h3>
      <p>Your information is used solely to provide and improve the SmartEdu recommendation service:</p>
      <ul>
        <li><strong>Personalized recommendations:</strong> Your assessment responses and academic profile are processed by our recommendation algorithm to generate a personalized list of universities and courses best suited to you.</li>
        <li><strong>Collaborative filtering:</strong> Your anonymized responses may be compared with those of other users who share similar answers. This helps us surface universities and courses that students with comparable profiles have found relevant.</li>
        <li><strong>Email communication:</strong> Your email address is used to send an account activation link upon registration and a one-time PIN (OTP) for password recovery purposes.</li>
      </ul>

      <h3>3. Assessment and Results</h3>
      <p>The SmartEdu assessment consists of 60 questions designed to evaluate the following areas:</p>
      <ul>
        <li>Personal interests and hobbies</li>
        <li>Skills and natural aptitudes</li>
        <li>Academic strengths and subject preferences</li>
        <li>Senior High School strand alignment</li>
        <li>Career goals and professional preferences</li>
      </ul>
      <p>Your results are generated based on your individual answers and are not a guarantee of admission to any university or program. Recommendations are intended as a guide to help you explore your options.</p>

      <h3>4. Data Sharing and Privacy</h3>
      <ul>
        <li>Your personal information (name, email, GWA) is <strong>never shared publicly</strong> or sold to third parties.</li>
        <li>Only anonymized, aggregated response data is used for collaborative recommendations — your identity remains protected at all times.</li>
        <li>We do not share your data with universities or external organizations.</li>
      </ul>

      <h3>5. Data Retention</h3>
      <p>Your account data and assessment results are stored securely for as long as your account is active. You may request deletion of your account and associated data at any time by contacting the SmartEdu team.</p>

      <h3>6. Your Consent</h3>
      <p>By checking the agreement box and creating an account, you explicitly consent to:</p>
      <ul>
        <li>The collection and use of your academic profile and assessment responses for generating recommendations.</li>
        <li>The use of your anonymized responses in our collaborative recommendation algorithm.</li>
        <li>Receiving transactional emails (account activation and password recovery) at the email address you provide.</li>
      </ul>

      <h3>7. Changes to These Terms</h3>
      <p>SmartEdu reserves the right to update these Terms and Conditions. Any significant changes will be communicated via your registered email address.</p>

      <p class="modal-footer-note">If you have any questions or concerns about how your data is handled, please contact the SmartEdu support team.</p>

    </div>

    <!-- Scroll-to-read nudge bar -->
    <div class="modal-scroll-hint" id="termsScrollHint">
      <span>↓ Scroll down to read all terms</span>
    </div>

    <div class="modal-footer">
      <button class="btn-modal-close" id="btnIUnderstand" onclick="acceptTerms()" disabled>I Understand</button>
    </div>
  </div>
</div>

<script src="JS/signup.js"></script>
</body>
</html>