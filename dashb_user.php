<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

// ── Auth ──────────────────────────────────────────────────────────────────
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: login.php');
    exit;
}

// ── Resolve which student take to show ────────────────────────────────────
$requestedSid = isset($_GET['sid']) ? (int)$_GET['sid'] : null;
$studentId    = null;
$dbError      = null;

$topCourses = [];
$username   = 'Student';
$strand     = '';

try {
    $pdo = getDB();

    // 1. Verify requested sid belongs to this user
    if ($requestedSid) {
        $chk = $pdo->prepare("
            SELECT id FROM students
            WHERE id = :sid AND user_id = :uid
            LIMIT 1
        ");
        $chk->execute([':sid' => $requestedSid, ':uid' => $userId]);
        if ($chk->fetch()) {
            $studentId = $requestedSid;
        }
    }

    // Fall back to latest take
    if (!$studentId) {
        $latest = $pdo->prepare("
            SELECT id FROM students
            WHERE user_id = :uid
            ORDER BY submitted_at DESC
            LIMIT 1
        ");
        $latest->execute([':uid' => $userId]);
        $row = $latest->fetch();
        $studentId = $row ? (int)$row['id'] : null;
    }

    if (!$studentId) {
        header('Location: studform.php');
        exit;
    }

    // 2. Check if this is a historical view
    $latestCheck = $pdo->prepare("
        SELECT id FROM students
        WHERE user_id = :uid
        ORDER BY submitted_at DESC
        LIMIT 1
    ");
    $latestCheck->execute([':uid' => $userId]);
    $latestRow    = $latestCheck->fetch();
    $latestSid    = $latestRow ? (int)$latestRow['id'] : null;
    $isHistoricalView = ($latestSid && $latestSid !== $studentId);

    // 3. Fetch student info + username
    $stmt = $pdo->prepare("
        SELECT s.strand, s.grade, s.gpa, u.username
        FROM students s
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.id = :sid
        LIMIT 1
    ");
    $stmt->execute([':sid' => $studentId]);
    $studentRow = $stmt->fetch();

    if ($studentRow) {
        $username = $studentRow['username'] ?? 'Student';
        $strand   = $studentRow['strand']   ?? '';
    }

    // 4. Fetch top 5 course results for this take
    $stmt = $pdo->prepare("
        SELECT course_name, field_name, score, `rank`
        FROM student_results
        WHERE student_id = :sid
        ORDER BY `rank` ASC
        LIMIT 5
    ");
    $stmt->execute([':sid' => $studentId]);
    $topCourses = $stmt->fetchAll();

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

// ── Build field distribution for pie chart ────────────────────────────────
$fieldCounts = [];
foreach ($topCourses as $c) {
    $f = $c['field_name'] ?? 'Other';
    $fieldCounts[$f] = ($fieldCounts[$f] ?? 0) + 1;
}
$total     = count($topCourses) ?: 1;
$fieldData = [];
foreach ($fieldCounts as $field => $count) {
    $fieldData[] = [
        'field'   => $field,
        'percent' => round($count / $total * 100),
    ];
}

// ── JSON for JS ───────────────────────────────────────────────────────────
$topCoursesJson = json_encode(array_map(fn($r) => [
    'rank'        => (int)$r['rank'],
    'course_name' => $r['course_name'],
    'field_name'  => $r['field_name'],
    'score'       => round((float)$r['score'] * 100, 1),
], $topCourses), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

$fieldDataJson = json_encode($fieldData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

if ($topCoursesJson === false) $topCoursesJson = '[]';
if ($fieldDataJson  === false) $fieldDataJson  = '[]';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard</title>
  <link rel="icon" type="image/png" href="pics/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="CSS/dashb_user.css"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>

<!-- Sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMenu()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <button class="sidebar-close" onclick="closeMenu()">&#x2715;</button>
  <div class="sidebar-top">
    <div class="sidebar-avatar-wrap" id="sidebarAvatarWrap">
      <svg id="sidebarAvatarIcon" viewBox="0 0 24 24" class="sidebar-avatar-icon">
        <path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v1h20v-1c0-3.3-6.7-5-10-5z"/>
      </svg>
      <img id="sidebarAvatarImg" alt="Avatar" class="sidebar-avatar-img"/>
    </div>
    <p class="sidebar-username" id="sidebarUsername"><?= htmlspecialchars($username) ?></p>
  </div>
  <nav class="sidebar-nav">
    <a href="dashb_user.php<?= $isHistoricalView ? '?sid='.(int)$studentId : '' ?>" class="sidebar-link active">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Dashboard
    </a>
    <a href="studprofile.php<?= $isHistoricalView ? '?sid='.(int)$studentId : '' ?>" class="sidebar-link">
      <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v1h20v-1c0-3.3-6.7-5-10-5z"/></svg>
      Profile
    </a>
    <a href="result_univs.php?sid=<?= (int)$studentId ?>" class="sidebar-link">
      <svg viewBox="0 0 24 24" style="fill:none;stroke:#888;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      Universities
    </a>
    <a href="result_hist.php?sid=<?= (int)$studentId ?>" class="sidebar-link">
      <svg viewBox="0 0 24 24" style="fill:none;stroke:#888;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
        <circle cx="12" cy="12" r="10"/>
        <polyline points="12 6 12 12 9 15"/>
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
  <a class="nav-logo" href="dashb_user.php">
    <img src="pics/logo.png" alt="SmartEdu Logo"/>
    <span>SmartEdu</span>
  </a>
  <div class="navbar-right">
    <span class="nav-greeting">
      <?= htmlspecialchars(($_SESSION['greeting'] ?? 'Hi') . ', ' . $username . '!') ?>
    </span>
    <button class="hamburger" onclick="toggleMenu()" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- Main -->
<main class="main">

  <?php if ($dbError): ?>
  <div style="grid-column:1/-1;background:#fdecea;border-radius:12px;padding:14px 20px;color:#c0392b;font-size:13px;">
    ⚠️ Database error: <?= htmlspecialchars($dbError) ?>
  </div>
  <?php endif; ?>

  <?php if ($isHistoricalView): ?>
  <div style="grid-column:1/-1;background:#fff8e1;border-radius:12px;padding:14px 20px;color:#856404;font-size:13px;font-family:'Inter',sans-serif;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:4px;">
    <span>📋 You are viewing a <strong>past result</strong>. This is not your latest assessment.</span>
    <a href="dashb_user.php" style="color:#061685;font-weight:600;white-space:nowrap;text-decoration:none;">View Latest →</a>
  </div>
  <?php endif; ?>

  <!-- LEFT: Career Field Card -->
  <div class="career-card">
    <h2>Career Field match</h2>
    <p>Measures alignment between your skills, interests, and career paths.</p>

    <?php if (empty($topCourses)): ?>
      <div style="padding:40px 0;text-align:center;color:#888;font-size:13px;font-family:'Inter',sans-serif;">
        No results yet.<br>
        <a href="studform.php" style="color:#061685;font-weight:600;">Take the form to see your match.</a>
      </div>
    <?php else: ?>
      <div class="chart-container">
        <canvas id="careerChart"></canvas>
      </div>
      <div class="legend" id="chartLegend"></div>
    <?php endif; ?>
  </div>

  <!-- RIGHT COLUMN -->
  <div class="right-col">

    <!-- Retake / Growth card -->
    <div class="retake-card">
      <div class="retake-text">
        <h3>Growth changes direction.</h3>
        <p>Answer the questionnaire again and see updated recommendations.</p>
        <button class="btn-retake" onclick="openRetakeModal()">Retake Form</button>
      </div>
      <img src="pics/profile.png" alt="Retake illustration" class="retake-img"/>
    </div>

    <!-- Strand label -->
    <div class="strand-label">
      Strand Alignment: <?= htmlspecialchars($strand ?: '—') ?>
    </div>

    <!-- Courses card -->
    <div class="courses-card">
      <div class="top5-label">TOP 5: Result of Courses</div>

      <?php if (empty($topCourses)): ?>
        <div style="padding:18px 28px;color:#888;font-size:13px;font-family:'Inter',sans-serif;">
          No results yet. <a href="studform.php" style="color:#061685;">Take the form</a>.
        </div>
      <?php else: ?>
        <?php foreach ($topCourses as $c): ?>
          <a href="result_univs.php?sid=<?= (int)$studentId ?>&course=<?= urlencode($c['course_name']) ?>"
             class="course-item">
            <?= htmlspecialchars($c['course_name']) ?>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
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
      <button class="btn-confirm" onclick="doLogout()">Yes</button>
      <button class="btn-cancel" onclick="closeLogoutModal()">No</button>
    </div>
  </div>
</div>

<!-- RETAKE MODAL -->
<div class="modal-overlay" id="retakeModal">
  <div class="modal">
    <button class="modal-close" onclick="closeRetakeModal()">&#x2715;</button>
    <div class="modal-body">
      <div class="modal-icon">i</div>
      <p class="modal-text">Are you sure you want to take another pathfinder form? Your previous take will be saved on the results history.</p>
    </div>
    <div class="modal-divider"></div>
    <div class="modal-actions">
      <button class="btn-cancel btn-yes" onclick="window.location.href='studform.php'">Yes</button>
      <button class="btn-confirm btn-no" onclick="closeRetakeModal()">No</button>
    </div>
  </div>
</div>

<script>
  (function() {
    fetch('api/get_profile.php')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.success) return;
        var nameEl = document.getElementById('sidebarUsername');
        if (nameEl && data.username) nameEl.textContent = data.username;
        if (data.avatar_url) {
          var img  = document.getElementById('sidebarAvatarImg');
          var icon = document.getElementById('sidebarAvatarIcon');
          if (img && icon) {
            img.src            = data.avatar_url;
            img.style.display  = 'block';
            icon.style.display = 'none';
          }
        }
      })
      .catch(function() {});
  })();

  function doLogout() {
  closeLogoutModal();

  var toast = document.createElement('div');
  toast.style.cssText = 'position:fixed;top:20px;right:24px;display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:30px;border:2px solid #e24b4a;background:#fff;font-family:Sora,sans-serif;font-size:14px;font-weight:600;color:#a32d2d;min-width:220px;max-width:320px;box-shadow:0 4px 20px rgba(0,0,0,0.1);z-index:9999;animation:toast-in 0.3s ease;';
  toast.innerHTML =
    '<div style="width:26px;height:26px;border-radius:50%;background:#fdecea;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
    + '<svg viewBox="0 0 24 24" width="14" height="14"><circle cx="12" cy="12" r="10" fill="#e24b4a" stroke="none"/><line x1="12" y1="8" x2="12" y2="14" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/><circle cx="12" cy="17" r="1.2" fill="#fff"/></svg>'
    + '</div>'
    + '<span>Logging out…</span>';

  var style = document.createElement('style');
  style.textContent = '@keyframes toast-in { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:translateX(0); } }';
  document.head.appendChild(style);
  document.body.appendChild(toast);

  setTimeout(function() {
    window.location.href = 'logout.php';
  }, 1500);
}
</script>
<script>
  var FIELD_DATA  = <?= $fieldDataJson ?>;
  var TOP_COURSES = <?= $topCoursesJson ?>;
</script>
<script src="JS/dashb_user.js"></script>
</body>
</html>