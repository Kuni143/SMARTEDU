<?php
// ── result_univs.php ──────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$studentId = $_SESSION['student_id'] ?? null;
if (!$studentId) {
    header('Location: studform.php');
    exit;
}

$topCourses = [];
$username   = 'Student';
$pdo        = null;
$dbError    = null;

try {
    $pdo = getDB();

    $stmt = $pdo->prepare("
        SELECT s.grade, s.strand, s.gpa, u.username
        FROM students s
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.id = :sid
        LIMIT 1
    ");
    $stmt->execute([':sid' => $studentId]);
    $studentRow = $stmt->fetch();
    if ($studentRow && $studentRow['username']) {
        $username = $studentRow['username'];
    }

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

$topCoursesJson = json_encode(array_map(fn($r) => [
    'rank'        => (int)$r['rank'],
    'course_name' => $r['course_name'],
    'field_name'  => $r['field_name'],
    'score'       => round((float)$r['score'] * 100, 1),
], $topCourses));

$activeCourse     = $topCourses[0]['course_name'] ?? '';
$activeCourseJson = json_encode($activeCourse);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>University Results</title>
  <link rel="icon" type="image/png" href="pics/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    html, body {
      width: 100%; min-height: 100vh;
      font-family: 'Sora', sans-serif;
      background: #ced4df;
      color: #0f1f56;
      overflow-x: hidden;
    }
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.75); border-radius: 999px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.95); }
    * { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.75) transparent; }

    .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:300; }
    .sidebar-overlay.show { display:block; }

    .sidebar {
      position:fixed; top:0; right:-280px; width:220px; height:100vh;
      background:#fff; border-radius:20px 0 0 20px; z-index:400;
      display:flex; flex-direction:column; padding:24px 20px 28px;
      transition:right 0.3s cubic-bezier(0.4,0,0.2,1);
      box-shadow:-4px 0 24px rgba(0,0,0,0.1);
    }
    .sidebar.open { right:0; }
    .sidebar-close {
      align-self:flex-end; background:none; border:none;
      font-size:18px; color:#061685; cursor:pointer;
      padding:2px 4px; line-height:1; margin-bottom:18px;
    }
    .sidebar-top { display:flex; flex-direction:column; align-items:center; gap:10px; margin-bottom:28px; }
    .sidebar-logo { width:72px; height:72px; border-radius:50%; object-fit:cover; }
    .sidebar-username { font-size:14px; font-weight:600; color:#061685; text-align:center; }
    .sidebar-nav { display:flex; flex-direction:column; gap:4px; flex:1; }
    .sidebar-link {
      display:flex; align-items:center; gap:12px; padding:11px 14px;
      border-radius:12px; font-family:'Sora',sans-serif; font-size:14px;
      font-weight:600; color:#444; text-decoration:none;
      transition:background 0.18s, color 0.18s;
    }
    .sidebar-link svg { width:20px; height:20px; fill:#888; stroke:none; flex-shrink:0; transition:fill 0.18s; }
    .sidebar-link:hover { background:#eef0fb; color:#061685; }
    .sidebar-link:hover svg { fill:#061685; }
    .sidebar-link.active { background:#dde2f8; color:#061685; }
    .sidebar-link.active svg { fill:#061685; }
    .sidebar-link svg[data-stroke] { fill:none !important; stroke:#888; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .sidebar-link:hover svg[data-stroke], .sidebar-link.active svg[data-stroke] { stroke:#061685; }
    .sidebar-bottom { margin-top:auto; }
    .sidebar-logout {
      display:flex; align-items:center; gap:12px; padding:11px 14px;
      border-radius:12px; font-family:'Sora',sans-serif; font-size:14px;
      font-weight:600; color:#444; cursor:pointer; background:none; border:none;
      width:100%; transition:background 0.18s, color 0.18s;
    }
    .sidebar-logout svg { width:20px; height:20px; fill:none; stroke:#888; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
    .sidebar-logout:hover { background:#fdecea; color:#c0392b; }
    .sidebar-logout:hover svg { stroke:#c0392b; }

    .navbar {
      width:100%; height:72px; background:#ced4df;
      display:flex; align-items:center; justify-content:space-between;
      padding:0 32px; position:sticky; top:0; z-index:100;
    }
    .nav-logo {
      position:fixed; top:24px; left:36px;
      display:flex; align-items:center; gap:10px; z-index:100; text-decoration:none;
    }
    .nav-logo img { width:52px; height:52px; border-radius:50%; object-fit:cover; }
    .nav-logo span { font-size:15px; font-weight:600; color:#101d89; }
    .hamburger {
      display:flex; flex-direction:column; gap:5px;
      background:none; border:none; cursor:pointer; padding:6px; margin-left:auto;
    }
    .hamburger span { display:block; width:28px; height:3px; background:#101d89; border-radius:2px; transition:opacity 0.2s; }
    .hamburger:hover span { opacity:0.7; }

    .main { max-width:1100px; margin:0 auto; padding:16px 24px 48px; }

    .db-error-banner {
      background:#fdecea; color:#a32d2d; padding:12px 20px;
      border-radius:12px; font-family:'Inter',sans-serif; font-size:13px;
      margin-bottom:16px; border-left:4px solid #e24b4a;
    }

    .search-row { display:flex; align-items:center; gap:14px; margin-bottom:12px; }
    .search-wrap { flex:1; position:relative; }
    .search-icon {
      position:absolute; left:16px; top:50%; transform:translateY(-50%);
      width:20px; height:20px; stroke:#888; fill:none; stroke-width:2;
      stroke-linecap:round; pointer-events:none;
    }
    .search-wrap input {
      width:100%; height:52px; border-radius:30px; border:none; outline:none;
      padding:0 48px 0 46px; font-family:'Inter',sans-serif; font-size:15px;
      color:#333; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,0.08);
      transition:box-shadow 0.2s;
    }
    .search-wrap input:focus { box-shadow:0 0 0 3px rgba(6,22,133,0.15); }
    .search-wrap input::placeholder { color:#aaa; }
    .search-clear-btn {
      position:absolute; right:14px; top:50%; transform:translateY(-50%);
      background:#e8ecf5; border:none; border-radius:50%; width:26px; height:26px;
      display:flex; align-items:center; justify-content:center;
      cursor:pointer; transition:background 0.15s; padding:0;
    }
    .search-clear-btn:hover { background:#d0d8ee; }
    .search-clear-btn svg { width:12px; height:12px; stroke:#061685; fill:none; stroke-width:2.5; stroke-linecap:round; }

    .filter-wrap { position:relative; flex-shrink:0; }
    .filter-btn {
      width:52px; height:52px; border-radius:50%; border:none; background:#fff;
      cursor:pointer; display:flex; align-items:center; justify-content:center;
      box-shadow:0 1px 4px rgba(0,0,0,0.08); transition:background 0.2s;
    }
    .filter-btn:hover { background:#f0f2ff; }
    .funnel-icon { width:22px; height:22px; stroke:#101d89; fill:#101d89; stroke-width:1.5; }
    .filter-dropdown {
      display:none; position:absolute; top:60px; right:0; background:#fff;
      border-radius:14px; box-shadow:0 6px 24px rgba(0,0,0,0.13);
      padding:14px 16px 12px; min-width:210px; z-index:200;
    }
    .filter-dropdown.open { display:block; }
    .filter-header { display:flex; flex-direction:column; gap:6px; margin-bottom:4px; }
    .filter-type-label { font-family:'Sora',sans-serif; font-size:12px; font-weight:600; color:#888; }
    .filter-current {
      display:flex; align-items:center; justify-content:space-between;
      width:100%; height:38px; border-radius:8px; border:1px solid #ddd;
      background:#fff; padding:0 12px; font-family:'Sora',sans-serif;
      font-size:14px; font-weight:600; color:#061685; cursor:pointer;
    }
    .filter-current svg { width:16px; height:16px; stroke:#061685; fill:none; stroke-width:2.5; stroke-linecap:round; transition:transform 0.2s; }
    .filter-current svg.flipped { transform:rotate(180deg); }
    .type-dropdown { display:none; margin-top:8px; border:1px solid #e0e4f0; border-radius:10px; overflow:hidden; }
    .type-dropdown.open { display:block; }
    .type-dropdown-top {
      display:flex; justify-content:space-between; padding:8px 12px;
      background:#f7f8fc; border-bottom:1px solid #e0e4f0;
    }
    .type-dropdown-top button {
      background:none; border:none; font-family:'Inter',sans-serif;
      font-size:13px; font-weight:600; color:#061685; cursor:pointer;
    }
    .type-opt {
      display:flex; align-items:center; gap:10px; padding:9px 14px;
      font-family:'Inter',sans-serif; font-size:14px; color:#222; cursor:pointer;
      border-bottom:1px solid #f0f2f8; transition:background 0.15s;
    }
    .type-opt:last-of-type { border-bottom:none; }
    .type-opt:hover { background:#f0f2ff; }
    .type-opt input { accent-color:#061685; width:15px; height:15px; }
    .type-dropdown-btns {
      display:flex; justify-content:flex-end; gap:8px;
      padding:8px 12px; background:#f7f8fc; border-top:1px solid #e0e4f0;
    }
    .td-cancel { background:none; border:none; font-family:'Inter',sans-serif; font-size:13px; color:#666; cursor:pointer; padding:4px 8px; }
    .td-done {
      background:#061685; color:#fff; border:none; border-radius:20px;
      font-family:'Sora',sans-serif; font-size:13px; font-weight:600;
      padding:5px 18px; cursor:pointer; transition:opacity 0.2s;
    }
    .td-done:hover { opacity:0.88; }

    .tag-row {
      display:flex; gap:10px; margin-bottom:20px;
      flex-wrap:wrap; min-height:28px; align-items:center;
    }
    .tag {
      font-family:'Inter',sans-serif; font-size:13px; font-weight:600;
      color:#061685; padding:2px 0; border-bottom:2px solid transparent; white-space:nowrap;
    }
    .tag.active-tag { border-bottom:2px solid #061685; }
    .search-tag {
      display:inline-flex; align-items:center; gap:6px;
      background:#e8ecf5; color:#061685; border-radius:20px;
      padding:4px 12px 4px 14px; font-family:'Inter',sans-serif;
      font-size:12px; font-weight:600; cursor:pointer; border:none; transition:background 0.15s;
    }
    .search-tag:hover { background:#d0d8ee; }
    .search-tag svg { width:11px; height:11px; stroke:#061685; fill:none; stroke-width:2.5; stroke-linecap:round; }

    /* ── Active tag smooth transition ─────────── */
    #activeFilterTag { transition: opacity 0.2s ease; }

    .grid-wrapper { height:692px; overflow-y:auto; flex-shrink:0; }
    .school-grid {
      display:grid;
      grid-template-columns:repeat(4, 1fr);
      gap:16px; align-content:start;
      transition: opacity 0.25s ease;
    }
    .school-card {
      background:#fff; border-radius:18px; padding:22px 20px 18px;
      display:flex; flex-direction:column; gap:10px;
      box-shadow:0 1px 4px rgba(0,0,0,0.06);
      transition:box-shadow 0.2s, transform 0.15s; height:220px;
    }
    .school-card:hover { box-shadow:0 6px 20px rgba(6,22,133,0.12); transform:translateY(-2px); }
    .school-name {
      font-family:'Sora',sans-serif; font-size:15px; font-weight:700; color:#061685; line-height:1.3;
      display:-webkit-box; -webkit-line-clamp:2; line-clamp:2;
      -webkit-box-orient:vertical; overflow:hidden;
    }
    .school-type-badge {
      display:inline-block; background:#e8ecf5; color:#061685;
      font-family:'Inter',sans-serif; font-size:11px; font-weight:700;
      padding:3px 10px; border-radius:10px; margin-bottom:2px;
    }
    .school-desc {
      font-family:'Inter',sans-serif; font-size:12px; color:#555; line-height:1.55; flex:1;
      overflow:hidden; display:-webkit-box; -webkit-line-clamp:4; line-clamp:4;
      -webkit-box-orient:vertical;
    }
    .school-card-footer { display:flex; justify-content:flex-end; flex-shrink:0; }
    .btn-details {
      background:#061685; color:#fff; border:none; border-radius:20px;
      font-family:'Sora',sans-serif; font-size:13px; font-weight:600;
      padding:7px 20px; cursor:pointer; transition:opacity 0.2s, transform 0.15s;
    }
    .btn-details:hover { opacity:0.88; transform:translateY(-1px); }
    .no-results {
      grid-column:1/-1; text-align:center; padding:48px 0;
      font-family:'Inter',sans-serif; font-size:15px; color:#777;
    }

    .chathead {
      position:fixed; bottom:28px; right:70px; width:64px; height:64px;
      border-radius:50%; cursor:pointer; z-index:600;
      box-shadow:0 4px 18px rgba(6,22,133,0.22); overflow:hidden;
      transition:transform 0.2s, box-shadow 0.2s; background:#fff;
      display:flex; align-items:center; justify-content:center;
    }
    .chathead:hover { transform:scale(1.08); box-shadow:0 6px 24px rgba(6,22,133,0.3); }
    .chathead img { width:100%; height:100%; object-fit:cover; border-radius:50%; }

    .chat-popup {
      position:fixed; bottom:104px; right:70px; width:300px;
      background:#fff; border-radius:18px;
      box-shadow:0 8px 32px rgba(6,22,133,0.16); z-index:599;
      overflow:hidden; display:none; flex-direction:column;
      animation:popup-in 0.25s ease;
    }
    .chat-popup.open { display:flex; }
    @keyframes popup-in {
      from { opacity:0; transform:translateY(12px) scale(0.97); }
      to   { opacity:1; transform:translateY(0) scale(1); }
    }
    .chat-popup::before {
      content:''; display:block; width:36px; height:4px;
      background:#d0d8ee; border-radius:2px; margin:10px auto 0; flex-shrink:0;
    }
    .chat-popup-header { display:flex; justify-content:flex-end; padding:4px 12px 0; }
    .chat-popup-close {
      background:none; border:none; cursor:pointer; padding:4px;
      display:flex; align-items:center; opacity:0.5; transition:opacity 0.15s;
    }
    .chat-popup-close:hover { opacity:1; }
    .chat-popup-close svg { width:14px; height:14px; stroke:#333; stroke-width:2.5; fill:none; stroke-linecap:round; }
    .chat-popup-body { padding:0 20px 20px; }
    .chat-popup-body h3 {
      font-family:'Sora',sans-serif; font-size:15px; font-weight:700;
      color:#061685; margin-bottom:5px;
    }
    .chat-popup-body > p {
      font-family:'Inter',sans-serif; font-size:12px; color:#666;
      margin-bottom:14px; line-height:1.5;
    }
    .chat-popup-body ol {
      list-style:decimal; padding-left:18px;
      display:flex; flex-direction:column; gap:8px; margin-bottom:14px;
    }
    .chat-popup-body ol li { font-family:'Inter',sans-serif; font-size:12px; color:#888; }
    .chat-popup-body ol li button {
      background:none; border:none; cursor:pointer;
      font-family:'Inter',sans-serif; font-size:13px; font-weight:600;
      color:#061685; text-decoration:underline; padding:0;
      text-align:left; transition:color 0.15s; line-height:1.4;
    }
    .chat-popup-body ol li button:hover { color:#1E5ABC; }
    .course-score-badge {
      display:inline-block; font-size:11px; font-weight:700;
      color:#fff; background:#061685; border-radius:10px;
      padding:1px 8px; margin-left:6px; vertical-align:middle;
    }
    .course-field-label {
      display:block; font-size:11px; color:#aaa; font-family:'Inter',sans-serif;
      font-weight:400; margin-top:1px;
    }
    .redirecting-text {
      font-family:'Inter',sans-serif; font-size:12px; color:#aaa; font-style:italic;
    }

    .empty-course-state {
      grid-column:1/-1; text-align:center; padding:60px 24px;
      font-family:'Inter',sans-serif;
    }
    .empty-course-state h3 { font-size:18px; color:#061685; margin-bottom:8px; }
    .empty-course-state p { font-size:14px; color:#777; }

    .skeleton {
      background:linear-gradient(90deg,#e8ecf5 25%,#f4f6fb 50%,#e8ecf5 75%);
      background-size:200% 100%; border-radius:10px;
      animation:skeleton-shine 1.4s infinite;
    }
    @keyframes skeleton-shine { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

    .modal-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,0.25); z-index:500;
      align-items:center; justify-content:center;
    }
    .modal-overlay.show { display:flex; }
    .modal {
      background:#fff; border-radius:18px; padding:28px 28px 22px;
      width:340px; box-shadow:0 12px 48px rgba(6,22,133,0.18);
      position:relative; border-top:4px solid #6a8ef0;
    }
    .modal-close { position:absolute; top:14px; right:16px; background:none; border:none; font-size:20px; color:#6a8ef0; cursor:pointer; line-height:1; }
    .modal-body { display:flex; align-items:flex-start; gap:12px; margin-bottom:20px; }
    .modal-icon {
      width:28px; height:28px; flex-shrink:0; background:#6a8ef0; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      font-size:15px; color:#fff; font-weight:700; margin-top:2px;
    }
    .modal-text { font-family:'Inter',sans-serif; font-size:14px; color:#222; line-height:1.55; font-weight:500; }
    .modal-divider { height:1px; background:#e8ecf5; margin-bottom:16px; }
    .modal-actions { display:flex; justify-content:flex-end; gap:10px; }
    .btn-cancel { border-radius:20px; padding:8px 22px; font-family:'Sora',sans-serif; font-size:13px; font-weight:600; cursor:pointer; border:none; background:#8bb2fd; color:#fff; transition:opacity 0.2s; }
    .btn-cancel:hover { opacity:0.85; }
    .btn-confirm { border-radius:20px; padding:8px 22px; font-family:'Sora',sans-serif; font-size:13px; font-weight:600; cursor:pointer; border:none; background:transparent; color:#061685; }
    .btn-confirm:hover { text-decoration:underline; }

    @media (max-width:900px)  { .school-grid { grid-template-columns:repeat(3,1fr); } }
    @media (max-width:680px)  { .school-grid { grid-template-columns:repeat(2,1fr); } .main { padding:12px 14px 40px; } }
    @media (max-width:420px)  { .school-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMenu()"></div>

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
    <a href="result_univs.php" class="sidebar-link active">
      <svg data-stroke viewBox="0 0 24 24">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      Universities
    </a>
    <a href="result_hist.php" class="sidebar-link">
      <svg data-stroke viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 9 15"/></svg>
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

<nav class="navbar">
  <a class="nav-logo" href="result_univs.php">
    <img src="pics/logo.png" alt="SmartEdu Logo"/>
    <span>SmartEdu</span>
  </a>
  <button class="hamburger" onclick="toggleMenu()" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<main class="main">

  <?php if ($dbError): ?>
  <div class="db-error-banner">⚠️ Database error: <?= htmlspecialchars($dbError) ?></div>
  <?php endif; ?>

  <?php if (empty($topCourses)): ?>
  <div class="empty-course-state">
    <h3>No results found</h3>
    <p>It seems your form submission wasn't saved. Please <a href="studform.php" style="color:#061685;">retake the form</a>.</p>
  </div>
  <?php else: ?>

  <div class="search-row">
    <div class="search-wrap">
      <svg class="search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="searchInput" placeholder="Search universities…" oninput="handleSearch()"/>
      <button class="search-clear-btn" id="searchClearBtn" onclick="clearSearch()" style="display:none;" title="Clear search">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="filter-wrap">
      <button class="filter-btn" id="filterBtn" onclick="toggleFilter()">
        <svg viewBox="0 0 24 24" class="funnel-icon"><polygon points="3 5 10 14 10 21 14 19 14 14 21 5 3 5"/></svg>
      </button>
      <div class="filter-dropdown" id="filterDropdown">
        <div class="filter-header">
          <span class="filter-type-label">Institution Type:</span>
          <button class="filter-current" id="filterCurrentBtn" onclick="toggleTypeDropdown()">
            <span id="filterCurrentText">All</span>
            <svg viewBox="0 0 24 24" id="filterChevron"><polyline points="18 15 12 9 6 15"/></svg>
          </button>
        </div>
        <div class="type-dropdown" id="typeDropdown">
          <div class="type-dropdown-top">
            <button onclick="selectAll()">Select all</button>
            <button onclick="clearAll()">Clear</button>
          </div>
          <label class="type-opt"><input type="checkbox" value="All" onchange="handleTypeCheck(this)" checked> All</label>
          <label class="type-opt"><input type="checkbox" value="LUC" onchange="handleTypeCheck(this)"> LUC</label>
          <label class="type-opt"><input type="checkbox" value="OGS" onchange="handleTypeCheck(this)"> OGS</label>
          <label class="type-opt"><input type="checkbox" value="SUC" onchange="handleTypeCheck(this)"> SUC</label>
          <label class="type-opt"><input type="checkbox" value="Private" onchange="handleTypeCheck(this)"> Private</label>
          <div class="type-dropdown-btns">
            <button class="td-cancel" onclick="cancelFilter()">Cancel</button>
            <button class="td-done" onclick="applyFilter()">Done</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="tag-row" id="tagRow">
    <span class="tag active-tag" id="activeFilterTag"><?= htmlspecialchars($activeCourse) ?></span>
    <span class="tag search-tag" id="allSearchTag" onclick="clearSearch()" style="display:none;">
      Clear search
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </span>
  </div>

  <div class="grid-wrapper">
    <div class="school-grid" id="schoolGrid">
      <?php for ($i = 0; $i < 8; $i++): ?>
      <div class="school-card" style="gap:12px;">
        <div class="skeleton" style="height:20px;width:80%;"></div>
        <div class="skeleton" style="height:14px;width:40%;"></div>
        <div class="skeleton" style="height:60px;"></div>
        <div class="skeleton" style="height:30px;width:50%;align-self:flex-end;border-radius:20px;"></div>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <?php endif; ?>

</main>

<div class="chathead" id="chathead" onclick="toggleChatPopup()">
  <img src="pics/popup.png" alt="Top Courses" onerror="this.style.display='none';this.parentElement.innerHTML='<span style=\'font-size:24px;\'>🎓</span>';"/>
</div>

<div class="chat-popup" id="chatPopup">
  <div class="chat-popup-header">
    <button class="chat-popup-close" onclick="closeChatPopup()">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="chat-popup-body">
    <h3>Your Top Course Matches</h3>
    <p>Select a course to filter universities that offer it.</p>
    <ol id="top-courses-list"></ol>
    <p class="redirecting-text" id="redirecting-text" style="display:none;">Filtering universities…</p>
  </div>
</div>

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
var TOP_COURSES   = <?= $topCoursesJson ?>;
var ACTIVE_COURSE = <?= $activeCourseJson ?>;

var SCHOOLS      = [];
var activeTypes  = ['All'];
var pendingTypes = ['All'];
var searchQuery  = '';

// ── Fetch universities ─────────────────────────────────────────────────────
function fetchUniversitiesForCourse(courseName) {
  var grid = document.getElementById('schoolGrid');
  if (!grid) return;

  // Fade out current content
  grid.style.transition = 'opacity 0.25s ease';
  grid.style.opacity    = '0';

  setTimeout(function() {
    // Show skeletons
    grid.innerHTML = '';
    for (var i = 0; i < 8; i++) {
      grid.innerHTML += '<div class="school-card" style="gap:12px;">'
        + '<div class="skeleton" style="height:20px;width:80%;"></div>'
        + '<div class="skeleton" style="height:14px;width:40%;"></div>'
        + '<div class="skeleton" style="height:60px;"></div>'
        + '<div class="skeleton" style="height:30px;width:50%;align-self:flex-end;border-radius:20px;margin-top:auto;"></div>'
        + '</div>';
    }
    // Fade skeletons in
    grid.style.opacity = '1';

    fetch('api/get_universities.php?course=' + encodeURIComponent(courseName))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        // Fade out skeletons
        grid.style.opacity = '0';
        setTimeout(function() {
          if (data.success) {
            SCHOOLS = data.universities;
            applyVisibility();
          } else {
            grid.innerHTML = '<div class="no-results">Failed to load universities: ' + (data.error || 'Unknown error') + '</div>';
          }
          // Fade new cards in
          grid.style.opacity = '1';
        }, 200);
      })
      .catch(function(err) {
        grid.style.opacity = '0';
        setTimeout(function() {
          grid.innerHTML = '<div class="no-results">Network error. Please refresh and try again.</div>';
          grid.style.opacity = '1';
        }, 200);
        console.error('Fetch error:', err);
      });

  }, 250);
}

// ── Build / filter grid ────────────────────────────────────────────────────
function applyVisibility() {
  var grid = document.getElementById('schoolGrid');
  if (!grid) return;
  grid.innerHTML = '';

  var filtered = SCHOOLS.filter(function(s) {
    var matchType   = activeTypes.includes('All') || activeTypes.includes(s.type);
    var matchSearch = !searchQuery
      || s.name.toLowerCase().includes(searchQuery.toLowerCase())
      || (s.type || '').toLowerCase().includes(searchQuery.toLowerCase())
      || (s.location || '').toLowerCase().includes(searchQuery.toLowerCase())
      || (s.description || '').toLowerCase().includes(searchQuery.toLowerCase());
    return matchType && matchSearch;
  });

  if (!filtered.length) {
    grid.innerHTML = '<div class="no-results">No universities found for the selected filters.</div>';
    return;
  }

  filtered.forEach(function(s) {
    var card = document.createElement('div');
    card.className = 'school-card';
    card.innerHTML =
      '<div class="school-name">' + escHtml(s.name) + '</div>'
      + (s.type ? '<span class="school-type-badge">' + escHtml(s.type) + ' · ' + escHtml(s.location || '') + '</span>' : '')
      + '<div class="school-desc">' + escHtml(s.description || 'No description available.') + '</div>'
      + '<div class="school-card-footer">'
      + '<button class="btn-details" onclick="goDetails(' + JSON.stringify(s.name) + ')">Details</button>'
      + '</div>';
    grid.appendChild(card);
  });
}

function goDetails(name) {
  window.location.href = 'detail_univ.php?name=' + encodeURIComponent(name);
}

// ── Search ─────────────────────────────────────────────────────────────────
function handleSearch() {
  searchQuery = document.getElementById('searchInput').value.trim();
  var clearBtn  = document.getElementById('searchClearBtn');
  var searchTag = document.getElementById('allSearchTag');
  if (searchQuery) {
    clearBtn.style.display = 'flex';
    searchTag.style.display = 'inline-flex';
  } else {
    clearBtn.style.display = 'none';
    searchTag.style.display = 'none';
  }
  applyVisibility();
}

function clearSearch() {
  searchQuery = '';
  document.getElementById('searchInput').value = '';
  document.getElementById('searchClearBtn').style.display = 'none';
  document.getElementById('allSearchTag').style.display = 'none';
  applyVisibility();
}

// ── Filter ─────────────────────────────────────────────────────────────────
function toggleFilter() {
  var dd = document.getElementById('filterDropdown');
  if (!dd.classList.contains('open')) {
    pendingTypes = activeTypes.slice();
    syncCheckboxes();
  }
  dd.classList.toggle('open');
}

function toggleTypeDropdown() {
  document.getElementById('typeDropdown').classList.toggle('open');
  document.getElementById('filterChevron').classList.toggle('flipped');
}

function syncCheckboxes() {
  document.querySelectorAll('.type-opt input[type="checkbox"]').forEach(function(cb) {
    cb.checked = pendingTypes.includes(cb.value);
  });
  updateCurrentText();
}

function updateCurrentText() {
  var el = document.getElementById('filterCurrentText');
  el.textContent = (pendingTypes.includes('All') || pendingTypes.length === 0) ? 'All' : pendingTypes.join(', ');
}

function handleTypeCheck(cb) {
  if (cb.value === 'All') {
    pendingTypes = cb.checked ? ['All'] : [];
    document.querySelectorAll('.type-opt input[type="checkbox"]').forEach(function(b) {
      b.checked = (b.value === 'All' && cb.checked);
    });
  } else {
    var allBox = document.querySelector('.type-opt input[value="All"]');
    if (allBox) allBox.checked = false;
    pendingTypes = pendingTypes.filter(function(t) { return t !== 'All'; });
    if (cb.checked) { if (!pendingTypes.includes(cb.value)) pendingTypes.push(cb.value); }
    else { pendingTypes = pendingTypes.filter(function(t) { return t !== cb.value; }); }
  }
  updateCurrentText();
}

function selectAll() { pendingTypes = ['All']; syncCheckboxes(); }
function clearAll() {
  pendingTypes = [];
  document.querySelectorAll('.type-opt input[type="checkbox"]').forEach(function(b) { b.checked = false; });
  updateCurrentText();
}
function applyFilter() {
  activeTypes = pendingTypes.length ? pendingTypes.slice() : ['All'];
  closeFilterDropdown();
  applyVisibility();
}
function cancelFilter() {
  pendingTypes = activeTypes.slice();
  closeFilterDropdown();
}
function closeFilterDropdown() {
  document.getElementById('filterDropdown').classList.remove('open');
  document.getElementById('typeDropdown').classList.remove('open');
  document.getElementById('filterChevron').classList.remove('flipped');
}

document.addEventListener('click', function(e) {
  var dd  = document.getElementById('filterDropdown');
  var btn = document.getElementById('filterBtn');
  if (dd && dd.classList.contains('open') && !dd.contains(e.target) && !btn.contains(e.target)) {
    cancelFilter();
  }
});

// ── Chat popup ─────────────────────────────────────────────────────────────
function buildTopCoursesList() {
  var list = document.getElementById('top-courses-list');
  if (!list) return;
  list.innerHTML = '';

  TOP_COURSES.forEach(function(c) {
    var isActive = c.course_name === ACTIVE_COURSE;

    var li  = document.createElement('li');
    var btn = document.createElement('button');
    btn.style.textDecoration = isActive ? 'none'      : 'underline';
    btn.style.fontWeight     = isActive ? '800'       : '600';
    btn.style.opacity        = isActive ? '1'         : '0.75';
    btn.style.background     = 'none';
    btn.style.border         = 'none';
    btn.style.cursor         = 'pointer';
    btn.style.fontFamily     = 'Inter, sans-serif';
    btn.style.fontSize       = '13px';
    btn.style.color          = '#061685';
    btn.style.padding        = '0';
    btn.style.textAlign      = 'left';
    btn.style.lineHeight     = '1.4';
    btn.textContent          = c.course_name;
    btn.addEventListener('click', (function(name) {
      return function() { selectCourse(name); };
    })(c.course_name));

    var badge = document.createElement('span');
    badge.className   = 'course-score-badge';
    badge.textContent = c.score + '%';
    btn.appendChild(badge);

    var fieldLabel = document.createElement('span');
    fieldLabel.className   = 'course-field-label';
    fieldLabel.textContent = c.field_name || '';

    li.appendChild(btn);
    li.appendChild(fieldLabel);
    list.appendChild(li);
  });
}

function toggleChatPopup() {
  var popup = document.getElementById('chatPopup');
  if (popup.classList.contains('open')) {
    closeChatPopup();
  } else {
    buildTopCoursesList();
    popup.classList.add('open');
  }
}
function closeChatPopup() {
  document.getElementById('chatPopup').classList.remove('open');
  document.getElementById('redirecting-text').style.display = 'none';
}

function selectCourse(course) {
  ACTIVE_COURSE = course;

  // Fade tag out, swap text, fade back in
  var tag = document.getElementById('activeFilterTag');
  if (tag) {
    tag.style.opacity = '0';
    setTimeout(function() {
      tag.textContent = course;
      tag.classList.add('active-tag');
      tag.style.opacity = '1';
    }, 200);
  }

  closeChatPopup();
  fetchUniversitiesForCourse(course);
}

// ── Sidebar ────────────────────────────────────────────────────────────────
function toggleMenu() {
  var isOpening = !document.getElementById('sidebar').classList.contains('open');
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
  document.getElementById('chathead').style.display = isOpening ? 'none' : 'flex';
  document.getElementById('chatPopup').classList.remove('open');
}
function closeMenu() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('show');
  document.getElementById('chathead').style.display = 'flex';
}

// ── Logout modal ───────────────────────────────────────────────────────────
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

// ── Helper ─────────────────────────────────────────────────────────────────
function escHtml(s) {
  return String(s || '')
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;');
}

// ── Init ───────────────────────────────────────────────────────────────────
if (TOP_COURSES.length > 0) {
  fetchUniversitiesForCourse(ACTIVE_COURSE);
}
</script>
</body>
</html>