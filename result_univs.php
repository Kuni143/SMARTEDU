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

// ── Resolve which student take to show (mirrors dashb_user.php logic) ─────
$requestedSid = isset($_GET['sid']) ? (int)$_GET['sid'] : null;
$studentId    = null;
$dbError      = null;
$topCourses   = [];
$username     = 'Student';
$isHistoricalView = false;

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
    $latestRow = $latestCheck->fetch();
    if ($latestRow && (int)$latestRow['id'] !== $studentId) {
        $isHistoricalView = true;
    }

    // 3. Fetch student info + username
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

    // 4. Fetch top 5 course results for this specific take
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

// ── Determine which course to highlight ───────────────────────────────────
$activeCourse = $topCourses[0]['course_name'] ?? '';

$requestedCourse = $_GET['course'] ?? null;
if ($requestedCourse) {
    $validCourses = array_column($topCourses, 'course_name');
    if (in_array($requestedCourse, $validCourses, true)) {
        $activeCourse = $requestedCourse;
    }
}

// JSON encode for JS
$topCoursesJson = json_encode(array_map(fn($r) => [
    'rank'        => (int)$r['rank'],
    'course_name' => $r['course_name'],
    'field_name'  => $r['field_name'],
    'score'       => round((float)$r['score'] * 100, 1),
], $topCourses), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

$activeCourseJson = json_encode($activeCourse, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

if ($topCoursesJson   === false) $topCoursesJson   = '[]';
if ($activeCourseJson === false) $activeCourseJson = '""';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>University Results</title>
  <link rel="icon" type="image/png" href="pics/logo.png">
  <link rel="stylesheet" href="CSS/result_univs.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMenu()"></div>

<aside class="sidebar" id="sidebar">
  <button class="sidebar-close" onclick="closeMenu()" aria-label="Close">&#x2715;</button>
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
    <a href="dashb_user.php<?= $isHistoricalView ? '?sid='.(int)$studentId : '' ?>" class="sidebar-link">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Dashboard
    </a>
    <a href="studprofile.php<?= $isHistoricalView ? '?sid='.(int)$studentId : '' ?>" class="sidebar-link">
      <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v1h20v-1c0-3.3-6.7-5-10-5z"/></svg>
      Profile
    </a>
    <a href="result_univs.php?sid=<?= (int)$studentId ?>" class="sidebar-link active">
      <svg data-stroke viewBox="0 0 24 24">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      Universities
    </a>
    <a href="result_hist.php?sid=<?= (int)$studentId ?>" class="sidebar-link">
      <svg data-stroke viewBox="0 0 24 24">
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

<nav class="navbar">
  <a class="nav-logo" href="result_univs.php?sid=<?= (int)$studentId ?>">
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

  <?php if ($isHistoricalView): ?>
  <div style="background:#fff8e1;border-radius:12px;padding:14px 20px;color:#856404;font-size:13px;font-family:'Inter',sans-serif;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;">
    <span>📋 You are viewing universities for a <strong>past result</strong>. This is not your latest assessment.</span>
    <a href="result_univs.php" style="color:#061685;font-weight:600;white-space:nowrap;text-decoration:none;">View Latest →</a>
  </div>
  <?php endif; ?>

  <?php if (empty($topCourses)): ?>
  <div class="empty-course-state">
    <h3>No results found</h3>
    <p>It seems your form submission wasn't saved. Please <a href="studform.php" style="color:#061685;">retake the form</a>.</p>
  </div>
  <?php else: ?>

  <!-- Search + Filter row -->
  <div class="search-row">
    <div class="search-wrap">
      <svg class="search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="searchInput" placeholder="Search universities…" oninput="handleSearch()"/>
      <button class="search-clear-btn" id="searchClearBtn" onclick="clearSearch()" style="display:none;" title="Clear search">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="filter-wrap" id="typeFilterWrap">
      <button class="filter-btn" id="typeFilterBtn" onclick="toggleTypeFilter()" title="Filter by type">
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
            <button onclick="selectAllTypes()">Select all</button>
            <button onclick="clearAllTypes()">Clear</button>
          </div>
          <label class="type-opt"><input type="checkbox" value="All" onchange="handleTypeCheck(this)" checked> All</label>
          <label class="type-opt"><input type="checkbox" value="LUC" onchange="handleTypeCheck(this)"> LUC</label>
          <label class="type-opt"><input type="checkbox" value="OGS" onchange="handleTypeCheck(this)"> OGS</label>
          <label class="type-opt"><input type="checkbox" value="SUC" onchange="handleTypeCheck(this)"> SUC</label>
          <label class="type-opt"><input type="checkbox" value="Private" onchange="handleTypeCheck(this)"> Private</label>
          <div class="type-dropdown-btns">
            <button class="td-cancel" onclick="cancelTypeFilter()">Cancel</button>
            <button class="td-done" onclick="applyTypeFilter()">Done</button>
          </div>
        </div>
      </div>
    </div>

    <div class="filter-wrap" id="locFilterWrap">
      <button class="filter-btn" id="locFilterBtn" onclick="toggleLocFilter()" title="Filter by location">
        <svg viewBox="0 0 24 24" fill="none" stroke="#101d89" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:22px;height:22px;">
          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
          <circle cx="12" cy="9" r="2.5"/>
        </svg>
      </button>
      <div class="loc-dropdown" id="locDropdown">
        <div class="filter-header">
          <span class="filter-type-label">Location:</span>
        </div>
        <div class="loc-opts-scroll">
          <div class="loc-dropdown-top">
            <button onclick="selectAllLocs()">Select all</button>
            <button onclick="clearAllLocs()">Clear</button>
          </div>
          <label class="loc-opt"><input type="checkbox" value="All" onchange="handleLocCheck(this)" checked> All</label>
          <label class="loc-opt"><input type="checkbox" value="Quezon City" onchange="handleLocCheck(this)"> Quezon City</label>
          <label class="loc-opt"><input type="checkbox" value="Manila" onchange="handleLocCheck(this)"> Manila</label>
          <label class="loc-opt"><input type="checkbox" value="Makati" onchange="handleLocCheck(this)"> Makati</label>
          <label class="loc-opt"><input type="checkbox" value="Pateros" onchange="handleLocCheck(this)"> Pateros</label>
          <label class="loc-opt"><input type="checkbox" value="Taguig" onchange="handleLocCheck(this)"> Taguig</label>
          <label class="loc-opt"><input type="checkbox" value="Las Pi&#241;as" onchange="handleLocCheck(this)"> Las Pi&#241;as</label>
          <label class="loc-opt"><input type="checkbox" value="Para&#241;aque" onchange="handleLocCheck(this)"> Para&#241;aque</label>
          <label class="loc-opt"><input type="checkbox" value="Caloocan" onchange="handleLocCheck(this)"> Caloocan</label>
          <label class="loc-opt"><input type="checkbox" value="Muntinlupa" onchange="handleLocCheck(this)"> Muntinlupa</label>
          <label class="loc-opt"><input type="checkbox" value="Pasay" onchange="handleLocCheck(this)"> Pasay</label>
          <label class="loc-opt"><input type="checkbox" value="Valenzuela" onchange="handleLocCheck(this)"> Valenzuela</label>
          <label class="loc-opt"><input type="checkbox" value="Malabon" onchange="handleLocCheck(this)"> Malabon</label>
          <label class="loc-opt"><input type="checkbox" value="Marikina" onchange="handleLocCheck(this)"> Marikina</label>
          <label class="loc-opt"><input type="checkbox" value="Pasig" onchange="handleLocCheck(this)"> Pasig</label>
          <label class="loc-opt"><input type="checkbox" value="Mandaluyong" onchange="handleLocCheck(this)"> Mandaluyong</label>
          <label class="loc-opt"><input type="checkbox" value="San Juan" onchange="handleLocCheck(this)"> San Juan</label>
        </div>
        <div class="loc-dropdown-btns">
          <button class="td-cancel" onclick="cancelLocFilter()">Cancel</button>
          <button class="td-done" onclick="applyLocFilter()">Done</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Tag row -->
  <div class="tag-row" id="tagRow">
    <span class="tag active-tag" id="activeFilterTag"><?= htmlspecialchars($activeCourse) ?></span>
    <span class="tag search-tag" id="allSearchTag" onclick="clearSearch()" style="display:none;">
      Clear search
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </span>
  </div>

  <!-- Grid -->
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

<!-- Floating chat head -->
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
      <button class="btn-confirm" onclick="window.location.href='logout.php'">Yes</button>
      <button class="btn-cancel" onclick="closeLogoutModal()">No</button>
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
</script>

<script>
var TOP_COURSES   = <?= $topCoursesJson ?>;
var ACTIVE_COURSE = <?= $activeCourseJson ?>;
var STUDENT_ID    = <?= (int)$studentId ?>;

var SCHOOLS      = [];
var activeTypes  = ['All'];
var pendingTypes = ['All'];
var activeLocs   = ['All'];
var pendingLocs  = ['All'];
var searchQuery  = '';

function fetchUniversitiesForCourse(courseName) {
  var grid = document.getElementById('schoolGrid');
  if (!grid) return;

  grid.style.transition = 'opacity 0.25s ease';
  grid.style.opacity    = '0';

  setTimeout(function () {
    grid.innerHTML = '';
    for (var i = 0; i < 8; i++) {
      grid.innerHTML += '<div class="school-card" style="gap:12px;">'
        + '<div class="skeleton" style="height:20px;width:80%;"></div>'
        + '<div class="skeleton" style="height:14px;width:40%;"></div>'
        + '<div class="skeleton" style="height:60px;"></div>'
        + '<div class="skeleton" style="height:30px;width:50%;align-self:flex-end;border-radius:20px;margin-top:auto;"></div>'
        + '</div>';
    }
    grid.style.opacity = '1';

    fetch('api/get_universities.php?course=' + encodeURIComponent(courseName))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        grid.style.opacity = '0';
        setTimeout(function () {
          if (data.success) {
            SCHOOLS = data.universities;
            applyVisibility();
          } else {
            grid.innerHTML = '<div class="no-results">Failed to load universities: ' + (data.error || 'Unknown error') + '</div>';
          }
          grid.style.opacity = '1';
        }, 200);
      })
      .catch(function (err) {
        grid.style.opacity = '0';
        setTimeout(function () {
          grid.innerHTML = '<div class="no-results">Network error. Please refresh and try again.</div>';
          grid.style.opacity = '1';
        }, 200);
        console.error('Fetch error:', err);
      });
  }, 250);
}

function applyVisibility() {
  var grid = document.getElementById('schoolGrid');
  if (!grid) return;
  grid.innerHTML = '';

  var filtered = SCHOOLS.filter(function (s) {
    var matchType   = activeTypes.includes('All') || activeTypes.includes(s.type);
    var matchLoc    = activeLocs.includes('All')  || activeLocs.includes(s.location);
    var matchSearch = !searchQuery
      || s.name.toLowerCase().includes(searchQuery.toLowerCase())
      || (s.type     || '').toLowerCase().includes(searchQuery.toLowerCase())
      || (s.location || '').toLowerCase().includes(searchQuery.toLowerCase())
      || (s.description || '').toLowerCase().includes(searchQuery.toLowerCase());
    return matchType && matchLoc && matchSearch;
  });

  if (!filtered.length) {
    grid.innerHTML = '<div class="no-results">No universities found for the selected filters.</div>';
    return;
  }

  filtered.forEach(function (s) {
    var TYPE_FULL = {
      'LUC':     'Local Universities and Colleges',
      'OGS':     'Other Government Schools',
      'SUC':     'State Universities and Colleges',
      'Private': 'Private Universities and Colleges'
    };
    var card = document.createElement('div');
    card.className = 'school-card';
    card.innerHTML =
      '<div class="school-name">' + escHtml(s.name) + '</div>'
      + (s.type ? '<span class="school-type-badge">' + escHtml(TYPE_FULL[s.type] || s.type) + ' · ' + escHtml(s.location || '') + '</span>' : '')
      + '<div class="school-desc">' + escHtml(s.description || 'No description available.') + '</div>'
      + '<div class="school-card-footer">'
      + '<button class="btn-details" data-name="' + encodeURIComponent(s.name) + '" onclick="goDetailsFromBtn(this)">Details</button>'
      + '</div>';
    grid.appendChild(card);
  });
}

// ── Navigate to detail page — passes sid + active course for round-trip ──
function goDetailsFromBtn(btn) {
  var name = decodeURIComponent(btn.getAttribute('data-name'));
  sessionStorage.setItem('lastActiveCourse', ACTIVE_COURSE);
  sessionStorage.setItem('lastStudentId', STUDENT_ID);
  window.location.href = 'detail_univ.php?name=' + encodeURIComponent(name)
    + '&course=' + encodeURIComponent(ACTIVE_COURSE)
    + (STUDENT_ID ? '&sid=' + STUDENT_ID : '');
}

function handleSearch() {
  searchQuery = document.getElementById('searchInput').value.trim();
  var clearBtn  = document.getElementById('searchClearBtn');
  var searchTag = document.getElementById('allSearchTag');
  clearBtn.style.display  = searchQuery ? 'flex'        : 'none';
  searchTag.style.display = searchQuery ? 'inline-flex' : 'none';
  applyVisibility();
}
function clearSearch() {
  searchQuery = '';
  document.getElementById('searchInput').value           = '';
  document.getElementById('searchClearBtn').style.display = 'none';
  document.getElementById('allSearchTag').style.display   = 'none';
  applyVisibility();
}

function toggleTypeFilter() {
  document.getElementById('locDropdown').classList.remove('open');
  document.getElementById('locFilterBtn').classList.remove('active-filter');
  var dd = document.getElementById('filterDropdown');
  if (!dd.classList.contains('open')) { pendingTypes = activeTypes.slice(); syncTypeCheckboxes(); }
  dd.classList.toggle('open');
}
function toggleTypeDropdown() {
  document.getElementById('typeDropdown').classList.toggle('open');
  document.getElementById('filterChevron').classList.toggle('flipped');
}
function syncTypeCheckboxes() {
  document.querySelectorAll('#filterDropdown .type-opt input[type="checkbox"]').forEach(function (cb) {
    cb.checked = pendingTypes.includes(cb.value);
  });
  updateTypeText();
}
function updateTypeText() {
  document.getElementById('filterCurrentText').textContent =
    (pendingTypes.includes('All') || !pendingTypes.length) ? 'All' : pendingTypes.join(', ');
}
function handleTypeCheck(cb) {
  if (cb.value === 'All') {
    pendingTypes = cb.checked ? ['All'] : [];
    document.querySelectorAll('#filterDropdown .type-opt input[type="checkbox"]').forEach(function (b) {
      b.checked = (b.value === 'All' && cb.checked);
    });
  } else {
    var allBox = document.querySelector('#filterDropdown .type-opt input[value="All"]');
    if (allBox) allBox.checked = false;
    pendingTypes = pendingTypes.filter(function (t) { return t !== 'All'; });
    if (cb.checked) { if (!pendingTypes.includes(cb.value)) pendingTypes.push(cb.value); }
    else { pendingTypes = pendingTypes.filter(function (t) { return t !== cb.value; }); }
  }
  updateTypeText();
}
function selectAllTypes() { pendingTypes = ['All']; syncTypeCheckboxes(); }
function clearAllTypes() {
  pendingTypes = [];
  document.querySelectorAll('#filterDropdown .type-opt input[type="checkbox"]').forEach(function (b) { b.checked = false; });
  updateTypeText();
}
function applyTypeFilter() {
  activeTypes = pendingTypes.length ? pendingTypes.slice() : ['All'];
  document.getElementById('typeFilterBtn').classList.toggle('active-filter', !activeTypes.includes('All'));
  closeTypeFilterDropdown();
  applyVisibility();
}
function cancelTypeFilter() { pendingTypes = activeTypes.slice(); closeTypeFilterDropdown(); }
function closeTypeFilterDropdown() {
  document.getElementById('filterDropdown').classList.remove('open');
  document.getElementById('typeDropdown').classList.remove('open');
  document.getElementById('filterChevron').classList.remove('flipped');
}

function toggleLocFilter() {
  closeTypeFilterDropdown();
  var dd = document.getElementById('locDropdown');
  if (!dd.classList.contains('open')) { pendingLocs = activeLocs.slice(); syncLocCheckboxes(); }
  dd.classList.toggle('open');
}
function syncLocCheckboxes() {
  document.querySelectorAll('#locDropdown .loc-opt input[type="checkbox"]').forEach(function (cb) {
    cb.checked = pendingLocs.includes(cb.value);
  });
}
function handleLocCheck(cb) {
  if (cb.value === 'All') {
    pendingLocs = cb.checked ? ['All'] : [];
    document.querySelectorAll('#locDropdown .loc-opt input[type="checkbox"]').forEach(function (b) {
      b.checked = (b.value === 'All' && cb.checked);
    });
  } else {
    var allBox = document.querySelector('#locDropdown .loc-opt input[value="All"]');
    if (allBox) allBox.checked = false;
    pendingLocs = pendingLocs.filter(function (l) { return l !== 'All'; });
    if (cb.checked) { if (!pendingLocs.includes(cb.value)) pendingLocs.push(cb.value); }
    else { pendingLocs = pendingLocs.filter(function (l) { return l !== cb.value; }); }
  }
}
function selectAllLocs() { pendingLocs = ['All']; syncLocCheckboxes(); }
function clearAllLocs() {
  pendingLocs = [];
  document.querySelectorAll('#locDropdown .loc-opt input[type="checkbox"]').forEach(function (b) { b.checked = false; });
}
function applyLocFilter() {
  activeLocs = pendingLocs.length ? pendingLocs.slice() : ['All'];
  document.getElementById('locFilterBtn').classList.toggle('active-filter', !activeLocs.includes('All'));
  document.getElementById('locDropdown').classList.remove('open');
  applyVisibility();
}
function cancelLocFilter() {
  pendingLocs = activeLocs.slice();
  document.getElementById('locDropdown').classList.remove('open');
}

document.addEventListener('click', function (e) {
  var typeWrap = document.getElementById('typeFilterWrap');
  var locWrap  = document.getElementById('locFilterWrap');
  if (typeWrap && !typeWrap.contains(e.target)) cancelTypeFilter();
  if (locWrap  && !locWrap.contains(e.target))  cancelLocFilter();
});

function buildTopCoursesList() {
  var list = document.getElementById('top-courses-list');
  if (!list) return;
  list.innerHTML = '';

  TOP_COURSES.forEach(function (c) {
    var isActive = (c.course_name === ACTIVE_COURSE);
    var li  = document.createElement('li');
    var btn = document.createElement('button');
    btn.style.textDecoration = isActive ? 'none'  : 'underline';
    btn.style.fontWeight     = isActive ? '800'   : '600';
    btn.style.opacity        = isActive ? '1'     : '0.75';
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
    btn.addEventListener('click', (function (name) {
      return function () { selectCourse(name); };
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
  if (popup.classList.contains('open')) { closeChatPopup(); }
  else { buildTopCoursesList(); popup.classList.add('open'); }
}
function closeChatPopup() {
  document.getElementById('chatPopup').classList.remove('open');
  document.getElementById('redirecting-text').style.display = 'none';
}

function selectCourse(course) {
  ACTIVE_COURSE = course;
  sessionStorage.setItem('lastActiveCourse', ACTIVE_COURSE);
  var tag = document.getElementById('activeFilterTag');
  if (tag) {
    tag.style.opacity = '0';
    setTimeout(function () {
      tag.textContent = course;
      tag.classList.add('active-tag');
      tag.style.opacity = '1';
    }, 200);
  }
  closeChatPopup();
  fetchUniversitiesForCourse(course);
}

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

function openLogoutModal() {
  closeMenu();
  document.getElementById('logoutModal').classList.add('show');
}
function closeLogoutModal() {
  document.getElementById('logoutModal').classList.remove('show');
}
document.getElementById('logoutModal').addEventListener('click', function (e) {
  if (e.target === this) closeLogoutModal();
});

function escHtml(s) {
  return String(s || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// ── Initialise on page load ───────────────────────────────────────────────
// Priority: ?course= URL param → sessionStorage → PHP default
if (TOP_COURSES.length > 0) {
  var urlCourse  = (new URLSearchParams(window.location.search)).get('course');
  var validNames = TOP_COURSES.map(function(c) { return c.course_name; });

  if (urlCourse && validNames.includes(urlCourse)) {
    ACTIVE_COURSE = urlCourse;
    sessionStorage.setItem('lastActiveCourse', ACTIVE_COURSE);
  } else {
    var savedCourse = sessionStorage.getItem('lastActiveCourse');
    if (savedCourse && validNames.includes(savedCourse)) {
      ACTIVE_COURSE = savedCourse;
    }
  }

  var tag = document.getElementById('activeFilterTag');
  if (tag) tag.textContent = ACTIVE_COURSE;
  fetchUniversitiesForCourse(ACTIVE_COURSE);
}
</script>
</body>
</html>