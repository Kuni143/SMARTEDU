<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$studentId = $_SESSION['student_id'] ?? null;
if (!$studentId) {
    header('Location: studform.php');
    exit;
}

// Get username for sidebar
$username = 'Student';
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT u.username
        FROM students s
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.id = :sid LIMIT 1
    ");
    $stmt->execute([':sid' => $studentId]);
    $row = $stmt->fetch();
    if ($row && $row['username']) $username = $row['username'];
} catch (PDOException $e) {}

// Get university name from URL
$univName = trim($_GET['name'] ?? '');
$university = null;
$courses = [];
$error = '';

if ($univName === '') {
    $error = 'No university specified.';
} else {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("
            SELECT id, name, type, location, description,
                   campus_branches, tuition_fees, exam,
                   requirements, enrollment_requirements, contact_links
            FROM universities
            WHERE name = :name
            LIMIT 1
        ");
        $stmt->execute([':name' => $univName]);
        $university = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$university) {
            $error = 'University not found.';
        } else {
            $cStmt = $pdo->prepare("
                SELECT course_name FROM university_courses
                WHERE university_id = :id ORDER BY course_name ASC
            ");
            $cStmt->execute([':id' => $university['id']]);
            $courses = $cStmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Helper: render a text field as list items
function renderLines(string $text): string {
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    $out = '';
    foreach ($lines as $line) {
        $out .= '<li>' . htmlspecialchars($line) . '</li>';
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $university ? htmlspecialchars($university['name']) : 'University Details' ?></title>
  <link rel="icon" type="image/png" href="pics/logo.png">
  <link rel="stylesheet" href="CSS/detail_univ.css">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
</head>
<body>

<!-- Sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMenu()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <button class="sidebar-close" onclick="closeMenu()">&#x2715;</button>
  <div class="sidebar-top">
    <img src="pics/logo.png" alt="SmartEdu Logo" class="sidebar-logo"/>
    <p class="sidebar-username"><?= htmlspecialchars($username) ?></p>
  </div>
  <nav class="sidebar-nav">
    <a href="dashb_user.php" class="sidebar-link">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Dashboard
    </a>
    <a href="studprofile.php" class="sidebar-link">
      <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v1h20v-1c0-3.3-6.7-5-10-5z"/></svg>
      Profile
    </a>
    <a href="result_univs.php" class="sidebar-link active">
      <svg viewBox="0 0 24 24" style="fill:none;stroke:#061685;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      Universities
    </a>
    <a href="result_hist.php" class="sidebar-link">
      <svg viewBox="0 0 24 24" style="fill:none;stroke:#888;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 9 15"/>
      </svg>
      Result history
    </a>
  </nav>
  <div class="sidebar-bottom">
    <button class="sidebar-logout" onclick="openLogoutModal()">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </button>
  </div>
</aside>

<!-- Navbar -->
<nav class="navbar">
  <a class="nav-logo" href="result_univs.php">
    <img src="pics/logo.png" alt="SmartEdu Logo"/>
    <span>SmartEdu</span>
  </a>
  <button class="hamburger" onclick="toggleMenu()" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- Main -->
<main class="main">

<?php if ($error): ?>
  <div class="detail-card" style="text-align:center;padding:48px 24px;">
    <p style="color:#c0392b;font-size:16px;margin-bottom:16px;"><?= htmlspecialchars($error) ?></p>
    <a href="result_univs.php" style="color:#061685;font-weight:600;">← Back to Universities</a>
  </div>

<?php else: ?>

  <!-- Detail card -->
  <div class="detail-card">

    <!-- Top row -->
    <div class="detail-top">
      <button class="back-btn" onclick="window.history.back()" aria-label="Go back">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <h1 class="detail-title" id="univName"><?= htmlspecialchars($university['name']) ?></h1>
      <button class="bookmark-btn" aria-label="Bookmark">
        <svg viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
      </button>
    </div>

    <!-- Type · Location badge -->
    <?php if ($university['type'] || $university['location']): ?>
    <div style="margin-bottom:12px;">
      <span class="school-type-badge">
        <?= htmlspecialchars($university['type'] ?? '') ?>
        <?= ($university['type'] && $university['location']) ? ' · ' : '' ?>
        <?= htmlspecialchars($university['location'] ?? '') ?>
      </span>
    </div>
    <?php endif; ?>

    <!-- Intro bullets -->
    <?php if ($university['description']): ?>
    <ul class="detail-intro" id="introBullets">
      <?= renderLines($university['description']) ?>
    </ul>
    <?php endif; ?>

    <!-- Two-column body -->
    <div class="detail-cols">

      <!-- Left column -->
      <div>

        <?php if (!empty($courses)): ?>
        <div class="detail-section">
          <p class="section-heading">Courses Offered:</p>
          <ul class="section-list">
            <?php foreach ($courses as $c): ?>
            <li><?= htmlspecialchars($c) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <?php if ($university['campus_branches']): ?>
        <div class="detail-section">
          <p class="section-heading">Campus Branches:</p>
          <ul class="section-list"><?= renderLines($university['campus_branches']) ?></ul>
        </div>
        <?php endif; ?>

        <?php if ($university['tuition_fees']): ?>
        <div class="detail-section">
          <p class="section-heading">Tuition and Fees Info</p>
          <ul class="section-list"><?= renderLines($university['tuition_fees']) ?></ul>
        </div>
        <?php endif; ?>

        <?php if ($university['exam']): ?>
        <div class="detail-section">
          <p class="section-heading">Entrance Exam Details:</p>
          <ul class="section-list"><?= renderLines($university['exam']) ?></ul>
        </div>
        <?php endif; ?>

        <?php if ($university['enrollment_requirements']): ?>
        <div class="detail-section">
          <p class="section-heading">Enrollment Requirements</p>
          <ul class="section-list"><?= renderLines($university['enrollment_requirements']) ?></ul>
        </div>
        <?php endif; ?>

        <?php if ($university['contact_links']): ?>
        <div class="detail-section">
          <p class="section-heading">Contact / Official Links</p>
          <ul class="section-list"><?= renderLines($university['contact_links']) ?></ul>
        </div>
        <?php endif; ?>

      </div>

      <!-- Right column — Admission Requirements box -->
      <?php if ($university['requirements']): ?>
      <div class="admission-box">
        <p class="section-heading">Admission Requirements:</p>
        <ul class="section-list adm-step-list"><?= renderLines($university['requirements']) ?></ul>
      </div>
      <?php endif; ?>

    </div><!-- /detail-cols -->
  </div><!-- /detail-card -->

<?php endif; ?>

</main>

<!-- Logout modal -->
<div class="modal-overlay" id="logoutModal">
  <div class="modal">
    <button class="modal-close" onclick="closeLogoutModal()">&#x2715;</button>
    <div class="modal-body">
      <div class="modal-icon">i</div>
      <p class="modal-text">Are you sure you want to log out?</p>
    </div>
    <div class="modal-divider"></div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeLogoutModal()">Cancel</button>
      <button class="btn-confirm" onclick="window.location.href='logout.php'">Log Out</button>
    </div>
  </div>
</div>

<script>
function toggleMenu() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeMenu() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('show');
}
function openLogoutModal() {
  closeMenu();
  document.getElementById('logoutModal').classList.add('show');
}
function closeLogoutModal() {
  document.getElementById('logoutModal').classList.remove('show');
}
document.getElementById('logoutModal').addEventListener('click', function(e) {
  if (e.target === this) closeLogoutModal();
});
</script>

</body>
</html>