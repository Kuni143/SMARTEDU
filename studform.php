<?php
session_start();

// ── DB connection ──────────────────────────────────────────────────────────
$host = 'localhost';
$db   = 'smartedu';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
$db_error = null;

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    $db_error = $e->getMessage();
}

// ── Logged-in user (optional) ──────────────────────────────────────────────
$logged_in_user_id = $_SESSION['user_id'] ?? null;

// ── Check if this is a returning user (has existing results) ───────────────
$is_returning = false;
if ($logged_in_user_id && $pdo) {
    try {
        $chk = $pdo->prepare("
            SELECT s.id FROM students s
            INNER JOIN student_results sr ON sr.student_id = s.id
            WHERE s.user_id = :uid
            LIMIT 1
        ");
        $chk->execute([':uid' => $logged_in_user_id]);
        $is_returning = (bool) $chk->fetch();
    } catch (PDOException $e) {}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Pathfinder Form</title>
  <link rel="icon" type="image/png" href="pics/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="CSS/studform.css">
</head>
<body>

  <a class="logo" href="landpage.php">
    <img src="pics/logo.png" alt="SmartEdu Logo" />
    <span class="logo-name">SmartEdu</span>
  </a>

  <div class="page-header">
    <h1>Pathfinder Form</h1>
    <p>Find the right course for you!</p>
  </div>

  <?php if ($db_error): ?>
  <div style="background:#fdecea;color:#a32d2d;padding:12px 24px;text-align:center;font-family:Inter,sans-serif;font-size:13px;">
    ⚠️ Database connection error. Submissions will not be saved. (<?= htmlspecialchars($db_error) ?>)
  </div>
  <?php endif; ?>

  <div class="main-layout">

    <!-- Sidebar stepper -->
    <div class="stepper" id="stepper">
      <div class="step-item">
        <div class="step-icon active" id="step-icon-0">
          <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/></svg>
        </div>
        <span class="step-label active" id="step-label-0">Student<br>Form</span>
      </div>
      <div class="step-item">
        <div class="step-icon" id="step-icon-1">
          <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17.93V18c0-.55-.45-1-1-1s-1 .45-1 1v1.93C7.06 19.44 4.56 16.94 4.07 13H6c.55 0 1-.45 1-1s-.45-1-1-1H4.07C4.56 7.06 7.06 4.56 11 4.07V6c0 .55.45 1 1 1s1-.45 1-1V4.07c3.94.49 6.44 2.99 6.93 6.93H18c-.55 0-1 .45-1 1s.45 1 1 1h1.93c-.49 3.94-2.99 6.44-6.93 6.93z"/></svg>
        </div>
        <span class="step-label" id="step-label-1">Career<br>Form</span>
      </div>
    </div>

    <!-- Form card -->
    <div class="card" id="form-card">

      <!-- STEP 0: Student Form -->
      <div id="step-0" class="step-content">
        <h2>Student Form</h2>
        <p class="subtitle">Tell us about your academic background.</p>

        <div class="field">
          <label for="grade">Current Year/Grade Level <span class="req">*</span></label>
          <div class="select-wrapper">
            <select id="grade">
              <option value="" disabled selected></option>
              <option>Grade 11</option>
              <option>Grade 12</option>
            </select>
            <div class="chevron">
              <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
          </div>
          <div class="val-error" id="err-grade">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p>Almost there! Please fill in the required fields (*)</p>
          </div>
        </div>

        <div class="field">
          <label for="strand">Strand <span class="req">*</span></label>
          <div class="select-wrapper">
            <select id="strand">
              <option value="" disabled selected></option>
              <option>STEM</option>
              <option>ABM</option>
              <option>HUMSS</option>
              <option>GAS</option>
              <option>TVL</option>
              <option>Arts and Design</option>
            </select>
            <div class="chevron">
              <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
          </div>
          <div class="val-error" id="err-strand">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p>Almost there! Please fill in the required fields (*)</p>
          </div>
        </div>

        <div class="field">
          <label for="gpa">General Average / GPA (optional)</label>
          <input type="number" id="gpa" placeholder="e.g. 90 or 3.5" min="0" max="100" step="0.01" />
        </div>

        <div class="btn-row">
        <?php if ($is_returning): ?>
        <button class="btn-back-dash" onclick="showCancelConfirm()">Back to Dashboard</button>
        <?php endif; ?>
          <button class="btn-next" onclick="goNext()">Next</button>
        </div>
      </div>

      <!-- STEP 1: Career Form -->
      <div id="step-1" class="step-content" style="display:none;">
        <div class="career-form-title-row">
          <div>
            <h2>Career Form</h2>
            <p class="subtitle">Please read the instruction carefully and select one answer at a time.</p>
          </div>
          <button class="view-toggle-btn" id="view-toggle-btn" onclick="toggleView()" title="Toggle view">
            <svg id="view-icon-compact" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="5" rx="1"/>
              <rect x="3" y="10" width="18" height="5" rx="1"/>
              <rect x="3" y="17" width="18" height="4" rx="1"/>
            </svg>
            <svg id="view-icon-expanded" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
              <line x1="8" y1="6" x2="21" y2="6"/>
              <line x1="8" y1="12" x2="21" y2="12"/>
              <line x1="8" y1="18" x2="21" y2="18"/>
              <circle cx="3" cy="6" r="1" fill="currentColor"/>
              <circle cx="3" cy="12" r="1" fill="currentColor"/>
              <circle cx="3" cy="18" r="1" fill="currentColor"/>
            </svg>
            <span id="view-toggle-label">Expand</span>
          </button>
        </div>

        <p class="scale-note">
          <strong>Scale:</strong>
          &nbsp; Strongly Agree &nbsp;•&nbsp; Agree &nbsp;•&nbsp; Neutral &nbsp;•&nbsp; Disagree &nbsp;•&nbsp; Strongly Disagree
        </p>

        <!-- Career sub-stepper -->
        <div class="career-stepper">
          <div class="cs-item" id="cs-0">
            <div class="cs-dot active" id="cs-dot-0">1</div>
            <span class="cs-label">Interests</span>
          </div>
          <div class="cs-line" id="cs-line-0"></div>
          <div class="cs-item" id="cs-1">
            <div class="cs-dot" id="cs-dot-1">2</div>
            <span class="cs-label">Skills</span>
          </div>
          <div class="cs-line" id="cs-line-1"></div>
          <div class="cs-item" id="cs-2">
            <div class="cs-dot" id="cs-dot-2">3</div>
            <span class="cs-label">Academic<br>Strengths</span>
          </div>
          <div class="cs-line" id="cs-line-2"></div>
          <div class="cs-item" id="cs-3">
            <div class="cs-dot" id="cs-dot-3">4</div>
            <span class="cs-label">Strand<br>Alignment</span>
          </div>
          <div class="cs-line" id="cs-line-3"></div>
          <div class="cs-item" id="cs-4">
            <div class="cs-dot" id="cs-dot-4">5</div>
            <span class="cs-label">Career<br>Preferences</span>
          </div>
        </div>

        <!-- Section A: Interests -->
        <div class="career-section" id="career-0">
          <div class="questions-box" id="questions-box-0"></div>
        </div>
        <!-- Section B: Skills -->
        <div class="career-section" id="career-1" style="display:none;">
          <div class="questions-box" id="questions-box-1"></div>
        </div>
        <!-- Section C: Academic Strengths -->
        <div class="career-section" id="career-2" style="display:none;">
          <div class="questions-box" id="questions-box-2"></div>
        </div>
        <!-- Section D: Strand Alignment -->
        <div class="career-section" id="career-3" style="display:none;">
          <div class="questions-box" id="questions-box-3"></div>
        </div>
        <!-- Section E: Career Preferences -->
        <div class="career-section" id="career-4" style="display:none;">
          <div class="questions-box" id="questions-box-4"></div>
        </div>

        <div class="btn-row" style="margin-top:20px;">
          <button class="btn-prev" id="btn-career-prev" onclick="careerPrev()">Prev</button>
          <button class="btn-next" id="btn-career-next" onclick="careerNext()">Next</button>
        </div>
      </div>

    </div><!-- /card -->

    <button class="scroll-top-btn" id="scrollTopBtn" onclick="scrollToTop()" title="Back to top" style="display:none;">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="18 15 12 9 6 15"/>
      </svg>
    </button>

  </div><!-- /main-layout -->

  <div id="toast-container"></div>

  <script>
    // Pass PHP session user_id to JS (null if not logged in)
    var PHP_USER_ID = <?= json_encode($logged_in_user_id) ?>;
  </script>
  <script src="JS/studform.js"></script>
  
  <!-- Cancel retake confirmation toast -->
<div id="cancelConfirmToast" class="cancel-confirm-toast" style="display:none;">
  <p class="cancel-confirm-msg">Skip the retake and go back?</p>
  <div class="cancel-confirm-btns">
    <button class="cancel-confirm-yes" onclick="window.location.href='dashb_user.php'">Yes, go back</button>
    <button class="cancel-confirm-no" onclick="hideCancelConfirm()">No, continue</button>
  </div>
</div>
<div id="cancelConfirmBackdrop" class="cancel-confirm-backdrop" style="display:none;" onclick="hideCancelConfirm()"></div>

<script>
function showCancelConfirm() {
  document.getElementById('cancelConfirmToast').style.display = 'block';
  document.getElementById('cancelConfirmBackdrop').style.display = 'block';
}
function hideCancelConfirm() {
  document.getElementById('cancelConfirmToast').style.display = 'none';
  document.getElementById('cancelConfirmBackdrop').style.display = 'none';
}
</script>
</body>
</html>