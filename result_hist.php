<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: login.php'); exit; }

$username = 'Student';
$takes    = [];
$dbError  = null;

try {
    $pdo = getDB();

    // Get username
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch();
    if ($row) $username = $row['username'];

    // Get ALL student takes for this user, newest first
    $stmt = $pdo->prepare("
        SELECT s.id AS student_id, s.grade, s.strand, s.gpa, s.submitted_at
        FROM students s
        WHERE s.user_id = :uid
        ORDER BY s.submitted_at DESC
    ");
    $stmt->execute([':uid' => $userId]);
    $students = $stmt->fetchAll();

    foreach ($students as $i => $stu) {
        $sid = $stu['student_id'];

        // Get top 5 results for this take
        $rStmt = $pdo->prepare("
            SELECT course_name, field_name, score, `rank`
            FROM student_results
            WHERE student_id = :sid
            ORDER BY `rank` ASC
            LIMIT 5
        ");
        $rStmt->execute([':sid' => $sid]);
        $courses = $rStmt->fetchAll();

        $takes[] = [
            'student_id'  => $sid,
            'is_latest'   => ($i === 0),
            'submitted_at'=> $stu['submitted_at'],
            'strand'      => $stu['strand'],
            'grade'       => $stu['grade'],
            'courses'     => $courses,
        ];
    }
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Result History</title>
  <link rel="icon" type="image/png" href="pics/logo.png">
  <link rel="stylesheet" href="CSS/result_hist.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
</head>
<body>

<!-- Sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMenu()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <button class="sidebar-close" onclick="closeMenu()" aria-label="Close">&#x2715;</button>
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
    <a href="result_univs.php" class="sidebar-link">
      <svg viewBox="0 0 24 24" style="fill:none;stroke:#888;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      Universities
    </a>
    <a href="result_hist.php" class="sidebar-link active">
      <svg viewBox="0 0 24 24" style="fill:none;stroke:#061685;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
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
  <a class="nav-logo" href="result_hist.php">
    <img src="pics/logo.png" alt="SmartEdu Logo"/>
    <span>SmartEdu</span>
  </a>
  <button class="hamburger" onclick="toggleMenu()" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- Main -->
<main class="main">

  <div class="page-header">
    <h1>Result History</h1>
    <p>Review your past career assessment and track your evolving interests.</p>
  </div>

  <?php if ($dbError): ?>
    <div style="background:#fdecea;border-radius:12px;padding:14px 20px;color:#c0392b;font-size:13px;margin-bottom:24px;">
      ⚠️ Database error: <?= htmlspecialchars($dbError) ?>
    </div>
  <?php elseif (empty($takes)): ?>
    <div style="text-align:center;padding:60px 20px;color:#888;font-family:'Inter',sans-serif;">
      <p style="margin-bottom:12px;">No assessments taken yet.</p>
      <a href="studform.php" style="color:#061685;font-weight:600;">Take the form now →</a>
    </div>
  <?php else: ?>

  <div class="results-grid">
    <?php foreach ($takes as $take):
      $topCourse    = $take['courses'][0] ?? null;
      $otherCourses = array_slice($take['courses'], 1, 2);
      $date         = date('F d, Y', strtotime($take['submitted_at']));
      $sid          = $take['student_id'];
    ?>
    <div class="result-card">

      <?php if ($take['is_latest']): ?>
        <span class="latest-badge">Latest</span>
      <?php endif; ?>

      <div>
        <div class="result-date-label">Assessment Date:</div>
        <div class="result-date"><?= htmlspecialchars($date) ?></div>
      </div>

      <?php if ($topCourse): ?>
        <div class="result-top-rec">
          <div class="result-top-rec-label">Top Recommendation:</div>
          <div class="result-top-rec-name"><?= htmlspecialchars($topCourse['course_name']) ?></div>
        </div>

        <?php if (!empty($otherCourses)): ?>
        <div>
          <div class="result-section-label">Other Suggested Courses:</div>
          <div class="result-tags">
            <?php foreach ($otherCourses as $c): ?>
              <span class="result-tag"><?= htmlspecialchars($c['course_name']) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      <?php else: ?>
        <div style="color:#aaa;font-size:13px;font-family:'Inter',sans-serif;padding:8px 0;">
          No course results saved for this take.
        </div>
      <?php endif; ?>

      <!--
        View Full Result:
        - Passes ?sid= so dashb_user.php loads THIS specific take's data
        - dashb_user.php will verify the sid belongs to the logged-in user
      -->
      <a href="dashb_user.php?sid=<?= (int)$sid ?>" class="btn-view-result">View Full Result</a>

    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>
</main>

<!-- LOGOUT MODAL -->
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