<?php
// ── admin_univs.php ───────────────────────────────────
// Combined admin university management page.
// Handles all CRUD via ?action= AJAX calls and
// renders the full page on normal GET requests.

require_once __DIR__ . '/config/db.php';

// ── AJAX handlers ─────────────────────────────────────
if (isset($_GET['action'])) {
  header('Content-Type: application/json');
  $action = $_GET['action'];

  try {
    $pdo = getDB();

    // ── GET all universities ──────────────────────────
    if ($action === 'list') {
      $rows = $pdo->query("
        SELECT u.id, u.name, u.type, u.location, u.description, u.exam, u.requirements,
               GROUP_CONCAT(c.course_name ORDER BY c.course_name SEPARATOR '||') AS courses
        FROM universities u
        LEFT JOIN university_courses c ON c.university_id = u.id
        GROUP BY u.id
        ORDER BY u.name
      ")->fetchAll();

      foreach ($rows as &$r) {
        $r['courses'] = $r['courses'] ? explode('||', $r['courses']) : [];
      }
      echo json_encode(['success' => true, 'data' => $rows]);
      exit;
    }

    // ── ADD university ────────────────────────────────
    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $body = json_decode(file_get_contents('php://input'), true);
      $name = trim($body['name'] ?? '');
      $type = $body['type']     ?? null;
      $loc  = $body['location'] ?? null;
      $desc = trim($body['description'] ?? '');

      if (!$name || !$type || !$loc) {
        echo json_encode(['success' => false, 'error' => 'Name, type, and location are required.']);
        exit;
      }

      $stmt = $pdo->prepare("
        INSERT INTO universities (name, type, location, description)
        VALUES (:name, :type, :location, :description)
      ");
      $stmt->execute([':name'=>$name,':type'=>$type,':location'=>$loc,':description'=>$desc]);
      $id = (int) $pdo->lastInsertId();
      echo json_encode(['success' => true, 'id' => $id]);
      exit;
    }

    // ── SAVE / UPDATE university ──────────────────────
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $body    = json_decode(file_get_contents('php://input'), true);
      $id      = (int) ($body['id'] ?? 0);
      $courses = $body['courses'] ?? [];

      if (!$id) { echo json_encode(['success'=>false,'error'=>'Missing id.']); exit; }

      $pdo->prepare("
        UPDATE universities
        SET type=:type, location=:location, description=:description,
            exam=:exam, requirements=:requirements
        WHERE id=:id
      ")->execute([
        ':type'         => $body['type']         ?? null,
        ':location'     => $body['location']      ?? null,
        ':description'  => $body['description']   ?? null,
        ':exam'         => $body['exam']          ?? null,
        ':requirements' => $body['requirements']  ?? null,
        ':id'           => $id,
      ]);

      // Replace courses
      $pdo->prepare("DELETE FROM university_courses WHERE university_id = ?")->execute([$id]);
      if ($courses) {
        $ins = $pdo->prepare("INSERT IGNORE INTO university_courses (university_id, course_name) VALUES (?,?)");
        foreach ($courses as $c) {
          $c = trim($c);
          if ($c) $ins->execute([$id, $c]);
        }
      }

      echo json_encode(['success' => true]);
      exit;
    }

    // ── DELETE university ─────────────────────────────
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $body = json_decode(file_get_contents('php://input'), true);
      $id   = (int) ($body['id'] ?? 0);
      if (!$id) { echo json_encode(['success'=>false,'error'=>'Missing id.']); exit; }
      $pdo->prepare("DELETE FROM universities WHERE id = ?")->execute([$id]);
      echo json_encode(['success' => true]);
      exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);

  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Universities</title>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet"/>
  <link rel="icon" type="image/png" href="pics/logo.png"/>
  <link rel="stylesheet" href="CSS/admin_univs.css"/>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="topnav">
  <a class="topnav-logo" href="admin_univs.php">
    <img src="pics/logo.png" alt="SmartEdu Logo" onerror="this.style.display='none'"/>
    <span>SmartEdu</span>
  </a>
  <div class="topnav-links">
    <a href="dashb_admin.php"  class="topnav-link">Dashboard</a>
    <a href="admin_univs.php"  class="topnav-link active">University</a>
  </div>
  <button class="topnav-logout" onclick="window.location.href='admin_login.php'">
    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    Log out
  </button>
</nav>

<div class="page">
  <div class="welcome"><h1>Manage Universities</h1></div>

  <!-- Search + Filters + Add -->
  <div class="top-bar">
    <div class="search-wrapper">
      <input type="text" class="search-input" id="searchInput" placeholder="Search university..." oninput="renderList()"/>
      <svg class="search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </div>

    <!-- Institution Type Filter -->
    <div class="filter-wrapper" id="type-filter-wrapper">
      <button class="btn-filter" id="btn-type-filter" onclick="toggleTypeFilterDropdown()">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
        <span>Type:</span>
        <span class="filter-label" id="type-filter-label">All</span>
        <svg class="filter-chevron" id="type-filter-chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div class="filter-dropdown" id="type-filter-dropdown">
        <div class="filter-dropdown-header">
          <button class="fdd-link" onclick="selectAllTypes()">Select all</button>
          <button class="fdd-link" onclick="clearTypes()">Clear</button>
        </div>
        <div class="filter-options" id="type-filter-options">
          <label class="filter-option"><input type="checkbox" value="All" onchange="handleTypeCheck(this)" checked> All</label>
          <label class="filter-option"><input type="checkbox" value="LUC" onchange="handleTypeCheck(this)"> LUC</label>
          <label class="filter-option"><input type="checkbox" value="OGS" onchange="handleTypeCheck(this)"> OGS</label>
          <label class="filter-option"><input type="checkbox" value="SUC" onchange="handleTypeCheck(this)"> SUC</label>
          <label class="filter-option"><input type="checkbox" value="Private" onchange="handleTypeCheck(this)"> Private</label>
        </div>
        <div class="filter-dropdown-footer">
          <button class="btn-cancel" onclick="cancelTypeFilter()">Cancel</button>
          <button class="btn-filter-done" onclick="applyTypeFilter()">Done</button>
        </div>
      </div>
    </div>

    <!-- Location Filter -->
    <div class="filter-wrapper" id="loc-filter-wrapper">
      <button class="btn-filter" id="btn-loc-filter" onclick="toggleLocFilterDropdown()">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
        <span>Location:</span>
        <span class="filter-label" id="loc-filter-label">All</span>
        <svg class="filter-chevron" id="loc-filter-chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div class="filter-dropdown filter-dropdown-loc" id="loc-filter-dropdown">
        <div class="filter-dropdown-header">
          <button class="fdd-link" onclick="selectAllLocs()">Select all</button>
          <button class="fdd-link" onclick="clearLocs()">Clear</button>
        </div>
        <div class="filter-options filter-options-scroll" id="loc-filter-options">
          <label class="filter-option"><input type="checkbox" value="All" onchange="handleLocCheck(this)" checked> All</label>
          <label class="filter-option"><input type="checkbox" value="Quezon City" onchange="handleLocCheck(this)"> Quezon City</label>
          <label class="filter-option"><input type="checkbox" value="Manila" onchange="handleLocCheck(this)"> Manila</label>
          <label class="filter-option"><input type="checkbox" value="Makati" onchange="handleLocCheck(this)"> Makati</label>
          <label class="filter-option"><input type="checkbox" value="Pateros" onchange="handleLocCheck(this)"> Pateros</label>
          <label class="filter-option"><input type="checkbox" value="Taguig" onchange="handleLocCheck(this)"> Taguig</label>
          <label class="filter-option"><input type="checkbox" value="Las Pi&#241;as" onchange="handleLocCheck(this)"> Las Pi&#241;as</label>
          <label class="filter-option"><input type="checkbox" value="Para&#241;aque" onchange="handleLocCheck(this)"> Para&#241;aque</label>
          <label class="filter-option"><input type="checkbox" value="Caloocan" onchange="handleLocCheck(this)"> Caloocan</label>
          <label class="filter-option"><input type="checkbox" value="Muntinlupa" onchange="handleLocCheck(this)"> Muntinlupa</label>
          <label class="filter-option"><input type="checkbox" value="Pasay" onchange="handleLocCheck(this)"> Pasay</label>
          <label class="filter-option"><input type="checkbox" value="Valenzuela" onchange="handleLocCheck(this)"> Valenzuela</label>
          <label class="filter-option"><input type="checkbox" value="Malabon" onchange="handleLocCheck(this)"> Malabon</label>
          <label class="filter-option"><input type="checkbox" value="Marikina" onchange="handleLocCheck(this)"> Marikina</label>
          <label class="filter-option"><input type="checkbox" value="Pasig" onchange="handleLocCheck(this)"> Pasig</label>
          <label class="filter-option"><input type="checkbox" value="Mandaluyong" onchange="handleLocCheck(this)"> Mandaluyong</label>
          <label class="filter-option"><input type="checkbox" value="San Juan" onchange="handleLocCheck(this)"> San Juan</label>
        </div>
        <div class="filter-dropdown-footer">
          <button class="btn-cancel" onclick="cancelLocFilter()">Cancel</button>
          <button class="btn-filter-done" onclick="applyLocFilter()">Done</button>
        </div>
      </div>
    </div>

    <button class="btn-add" onclick="openAddModal()">+ Add University</button>
  </div>

  <div id="uni-list"><div class="list-loading">Loading universities…</div></div>
</div>

<!-- ── Add University Modal ── -->
<div class="modal-overlay" id="add-modal" style="display:none;">
  <div class="modal">
    <h2>Add University</h2>
    <div class="modal-field">
      <label>University Name <span class="req">*</span></label>
      <input type="text" id="m-name" placeholder="e.g. De La Salle University"/>
      <p class="m-error" id="m-name-err">Please enter a university name.</p>
    </div>
    <div class="modal-field">
      <label>Institution Type <span class="req">*</span></label>
      <div class="select-wrapper">
        <select id="m-type">
          <option value="" disabled selected></option>
          <option>LUC</option><option>OGS</option><option>SUC</option><option>Private</option>
        </select>
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <p class="m-error" id="m-type-err">Please select an institution type.</p>
    </div>
    <div class="modal-field">
      <label>Location <span class="req">*</span></label>
      <div class="select-wrapper">
        <select id="m-location">
          <option value="" disabled selected></option>
          <option>Quezon City</option><option>Manila</option><option>Makati</option>
          <option>Pateros</option><option>Taguig</option><option>Las Pi&#241;as</option>
          <option>Caloocan</option><option>Muntinlupa</option><option>Pasig</option>
          <option>Mandaluyong</option><option>San Juan</option><option>Pasay</option>
          <option>Marikina</option><option>Para&#241;aque</option><option>Valenzuela</option>
          <option>Malabon</option>
        </select>
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <p class="m-error" id="m-loc-err">Please select a location.</p>
    </div>
    <div class="modal-field">
      <label>Description</label>
      <textarea id="m-description" rows="3" placeholder="Short description of the university..."></textarea>
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeAddModal()">Cancel</button>
      <button class="btn-save" onclick="addUniversity()">Add University</button>
    </div>
  </div>
</div>

<!-- ── Edit Courses Modal ── -->
<div class="modal-overlay" id="courses-modal" style="display:none;">
  <div class="modal">
    <h2>Edit Courses Offered</h2>
    <p style="font-size:13px;color:#3a4a7a;margin-bottom:16px;">Type a course and press Enter or comma to add.</p>
    <div class="courses-input-area" id="courses-input-area">
      <div class="courses-tags-edit" id="courses-tags-edit"></div>
      <input type="text" id="course-input" placeholder="Add course..." onkeydown="handleCourseInput(event)"/>
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeCoursesModal()">Cancel</button>
      <button class="btn-save" onclick="saveCoursesModal()">Save</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>

<script>
// ── State ─────────────────────────────────────────────
var universities = [];
var activeId     = null;
var isDirty      = false;
var tempCourses  = [];

// Institution Type filter state
var selectedTypes  = ['All'];
var pendingTypes   = ['All'];
var typeFilterOpen = false;

// Location filter state
var selectedLocs  = ['All'];
var pendingLocs   = ['All'];
var locFilterOpen = false;

// ── Load from PHP API ─────────────────────────────────
function loadUniversities() {
  fetch('admin_univs.php?action=list')
    .then(function(r){ return r.json(); })
    .then(function(json) {
      if (!json.success) throw new Error(json.error);
      universities = json.data;
      renderList();
    })
    .catch(function(err) {
      document.getElementById('uni-list').innerHTML =
        '<div class="empty-state">Failed to load universities. ' + err.message + '</div>';
    });
}

// ── Institution Type Filter ───────────────────────────
function toggleTypeFilterDropdown() {
  if (locFilterOpen) cancelLocFilter();
  typeFilterOpen = !typeFilterOpen;
  var dd = document.getElementById('type-filter-dropdown');
  var ch = document.getElementById('type-filter-chevron');
  if (typeFilterOpen) {
    pendingTypes = selectedTypes.slice();
    syncTypeCheckboxes();
    dd.style.display = 'block';
    ch.classList.add('open');
  } else {
    dd.style.display = 'none';
    ch.classList.remove('open');
  }
}

function syncTypeCheckboxes() {
  document.querySelectorAll('#type-filter-options input[type="checkbox"]').forEach(function(cb) {
    cb.checked = pendingTypes.includes(cb.value);
  });
  updateTypeFilterLabel();
}

function handleTypeCheck(cb) {
  if (cb.value === 'All') {
    pendingTypes = cb.checked ? ['All'] : [];
    document.querySelectorAll('#type-filter-options input[type="checkbox"]').forEach(function(b) {
      b.checked = (b.value === 'All' && cb.checked);
    });
  } else {
    var allBox = document.querySelector('#type-filter-options input[value="All"]');
    if (allBox) allBox.checked = false;
    pendingTypes = pendingTypes.filter(function(t) { return t !== 'All'; });
    if (cb.checked) {
      if (!pendingTypes.includes(cb.value)) pendingTypes.push(cb.value);
    } else {
      pendingTypes = pendingTypes.filter(function(t) { return t !== cb.value; });
    }
  }
  updateTypeFilterLabel();
}

function selectAllTypes() {
  pendingTypes = ['All'];
  syncTypeCheckboxes();
}

function clearTypes() {
  pendingTypes = [];
  document.querySelectorAll('#type-filter-options input[type="checkbox"]').forEach(function(b) { b.checked = false; });
  updateTypeFilterLabel();
}

function applyTypeFilter() {
  selectedTypes = pendingTypes.length ? pendingTypes.slice() : ['All'];
  typeFilterOpen = false;
  document.getElementById('type-filter-dropdown').style.display = 'none';
  document.getElementById('type-filter-chevron').classList.remove('open');
  updateTypeFilterLabel();
  renderList();
}

function cancelTypeFilter() {
  pendingTypes = selectedTypes.slice();
  typeFilterOpen = false;
  document.getElementById('type-filter-dropdown').style.display = 'none';
  document.getElementById('type-filter-chevron').classList.remove('open');
}

function updateTypeFilterLabel() {
  var lbl = document.getElementById('type-filter-label');
  if (!pendingTypes.length || pendingTypes.includes('All')) {
    lbl.textContent = 'All';
  } else if (pendingTypes.length === 1) {
    lbl.textContent = pendingTypes[0];
  } else {
    lbl.textContent = pendingTypes.join(', ');
  }
}

// ── Location Filter ───────────────────────────────────
function toggleLocFilterDropdown() {
  if (typeFilterOpen) cancelTypeFilter();
  locFilterOpen = !locFilterOpen;
  var dd = document.getElementById('loc-filter-dropdown');
  var ch = document.getElementById('loc-filter-chevron');
  if (locFilterOpen) {
    pendingLocs = selectedLocs.slice();
    syncLocCheckboxes();
    dd.style.display = 'block';
    ch.classList.add('open');
  } else {
    dd.style.display = 'none';
    ch.classList.remove('open');
  }
}

function syncLocCheckboxes() {
  document.querySelectorAll('#loc-filter-options input[type="checkbox"]').forEach(function(cb) {
    cb.checked = pendingLocs.includes(cb.value);
  });
  updateLocFilterLabel();
}

function handleLocCheck(cb) {
  if (cb.value === 'All') {
    pendingLocs = cb.checked ? ['All'] : [];
    document.querySelectorAll('#loc-filter-options input[type="checkbox"]').forEach(function(b) {
      b.checked = (b.value === 'All' && cb.checked);
    });
  } else {
    var allBox = document.querySelector('#loc-filter-options input[value="All"]');
    if (allBox) allBox.checked = false;
    pendingLocs = pendingLocs.filter(function(l) { return l !== 'All'; });
    if (cb.checked) {
      if (!pendingLocs.includes(cb.value)) pendingLocs.push(cb.value);
    } else {
      pendingLocs = pendingLocs.filter(function(l) { return l !== cb.value; });
    }
  }
  updateLocFilterLabel();
}

function selectAllLocs() {
  pendingLocs = ['All'];
  syncLocCheckboxes();
}

function clearLocs() {
  pendingLocs = [];
  document.querySelectorAll('#loc-filter-options input[type="checkbox"]').forEach(function(b) { b.checked = false; });
  updateLocFilterLabel();
}

function applyLocFilter() {
  selectedLocs = pendingLocs.length ? pendingLocs.slice() : ['All'];
  locFilterOpen = false;
  document.getElementById('loc-filter-dropdown').style.display = 'none';
  document.getElementById('loc-filter-chevron').classList.remove('open');
  updateLocFilterLabel();
  renderList();
}

function cancelLocFilter() {
  pendingLocs = selectedLocs.slice();
  locFilterOpen = false;
  document.getElementById('loc-filter-dropdown').style.display = 'none';
  document.getElementById('loc-filter-chevron').classList.remove('open');
}

function updateLocFilterLabel() {
  var lbl = document.getElementById('loc-filter-label');
  if (!pendingLocs.length || pendingLocs.includes('All')) {
    lbl.textContent = 'All';
  } else if (pendingLocs.length === 1) {
    lbl.textContent = pendingLocs[0];
  } else {
    lbl.textContent = pendingLocs.length + ' selected';
  }
}

// Close both dropdowns when clicking outside
document.addEventListener('click', function(e) {
  if (typeFilterOpen && !document.getElementById('type-filter-wrapper').contains(e.target)) {
    cancelTypeFilter();
  }
  if (locFilterOpen && !document.getElementById('loc-filter-wrapper').contains(e.target)) {
    cancelLocFilter();
  }
});

// ── Render list ───────────────────────────────────────
function renderList() {
  var list   = document.getElementById('uni-list');
  var search = document.getElementById('searchInput').value.toLowerCase();
  list.innerHTML = '';

  var filtered = universities.filter(function(u) {
    var matchSearch = !search || u.name.toLowerCase().includes(search);
    var matchType   = selectedTypes.includes('All') || selectedTypes.some(function(s) { return s === u.type; });
    var matchLoc    = selectedLocs.includes('All')  || selectedLocs.some(function(l) { return l === u.location; });
    return matchSearch && matchType && matchLoc;
  });

  if (!filtered.length) {
    list.innerHTML = '<div class="empty-state">No universities found.</div>';
    return;
  }

  filtered.forEach(function(u) {
    var row = document.createElement('div');
    row.className = 'uni-row' + (u.id == activeId ? ' active' : '');
    row.onclick   = function(){ toggleDetail(u.id); };
    var left = '<div class="uni-row-left"><span>' + escHtml(u.name) + '</span>'
      + (u.type ? '<span class="type-badge">' + escHtml(u.type) + '</span>' : '') + '</div>';
    row.innerHTML = left;
    list.appendChild(row);
    if (u.id == activeId) list.appendChild(buildDetail(u));
  });
}

// ── Toggle detail ─────────────────────────────────────
function toggleDetail(id) {
  activeId = (activeId == id) ? null : id;
  isDirty  = false;
  renderList();
}

// ── Build detail card ─────────────────────────────────
function buildDetail(u) {
  var LOCATIONS = ['Quezon City','Manila','Makati','Pateros','Taguig','Las Pi\u00f1as',
    'Caloocan','Muntinlupa','Pasig','Mandaluyong','San Juan','Pasay',
    'Marikina','Para\u00f1aque','Valenzuela','Malabon'];

  var typeOpts = ['LUC','OGS','SUC','Private'].map(function(t) {
    return '<option' + (t===u.type?' selected':'') + '>' + t + '</option>';
  }).join('');
  var locOpts = LOCATIONS.map(function(l) {
    return '<option' + (l===u.location?' selected':'') + '>' + escHtml(l) + '</option>';
  }).join('');
  var courseTags = (u.courses||[]).map(function(c) {
    return '<span class="course-tag">' + escHtml(c) + '</span>';
  }).join('');

  var card = document.createElement('div');
  card.className = 'detail-card';
  card.innerHTML = `
    <div class="detail-section">
      <div class="detail-row">
        <span class="detail-label">University name</span>
        <span class="detail-value bold">${escHtml(u.name)}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Institution Type</span>
        <div class="select-wrapper">
          <select id="d-type" onchange="markDirty()"><option value="">— Select —</option>${typeOpts}</select>
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>
      <div class="detail-row">
        <span class="detail-label">Location</span>
        <div class="select-wrapper">
          <select id="d-location" onchange="markDirty()"><option value="">— Select —</option>${locOpts}</select>
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>
      <div class="detail-row">
        <span class="detail-label">Edit Description</span>
        <textarea id="d-description" rows="3" oninput="markDirty()">${escHtml(u.description||'')}</textarea>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-row align-top">
        <span class="detail-label">Course Offered</span>
        <div class="courses-area">
          <div class="courses-tags" id="d-courses">${courseTags}</div>
          <button class="btn-icon" onclick="openCoursesModal()" title="Edit courses">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
          </button>
        </div>
      </div>
      <div class="detail-row align-top">
        <span class="detail-label">Entrance Exam</span>
        <textarea id="d-exam" rows="5" oninput="markDirty()">${escHtml(u.exam||'')}</textarea>
      </div>
      <div class="detail-row align-top">
        <span class="detail-label">Requirements</span>
        <textarea id="d-requirements" rows="4" oninput="markDirty()">${escHtml(u.requirements||'')}</textarea>
      </div>
    </div>
    <div class="detail-actions">
      <button class="btn-cancel" onclick="cancelEdit()">Cancel</button>
      <button class="btn-save" id="btn-save" onclick="saveUniversity()" disabled>Save Changes</button>
      <button class="btn-delete" onclick="deleteUniversity()">Delete University</button>
    </div>`;
  return card;
}

function markDirty() {
  isDirty = true;
  var btn = document.getElementById('btn-save');
  if (btn) btn.disabled = false;
}
function cancelEdit() { activeId=null; isDirty=false; renderList(); }

// ── Save ──────────────────────────────────────────────
function saveUniversity() {
  var u = universities.find(function(x){ return x.id==activeId; });
  if (!u) return;
  var payload = {
    id:           u.id,
    type:         document.getElementById('d-type').value,
    location:     document.getElementById('d-location').value,
    description:  document.getElementById('d-description').value,
    exam:         document.getElementById('d-exam').value,
    requirements: document.getElementById('d-requirements').value,
    courses:      u.courses || [],
  };
  fetch('admin_univs.php?action=save', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  })
  .then(function(r){ return r.json(); })
  .then(function(json) {
    if (!json.success) throw new Error(json.error);
    u.type=payload.type; u.location=payload.location;
    u.description=payload.description; u.exam=payload.exam;
    u.requirements=payload.requirements;
    isDirty=false;
    showToast('success','University saved successfully.');
    renderList();
  })
  .catch(function(err){ showToast('error','Save failed: '+err.message); });
}

// ── Delete ────────────────────────────────────────────
function deleteUniversity() {
  if (!confirm('Are you sure you want to delete this university?')) return;
  fetch('admin_univs.php?action=delete', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({id: activeId})
  })
  .then(function(r){ return r.json(); })
  .then(function(json) {
    if (!json.success) throw new Error(json.error);
    universities = universities.filter(function(u){ return u.id!=activeId; });
    activeId=null;
    showToast('success','University deleted.');
    renderList();
  })
  .catch(function(err){ showToast('error','Delete failed: '+err.message); });
}

// ── Add modal ─────────────────────────────────────────
function openAddModal() {
  document.getElementById('m-name').value='';
  document.getElementById('m-type').selectedIndex=0;
  document.getElementById('m-location').selectedIndex=0;
  document.getElementById('m-description').value='';
  ['m-name-err','m-type-err','m-loc-err'].forEach(function(id){
    document.getElementById(id).classList.remove('visible');
  });
  document.getElementById('add-modal').style.display='flex';
}
function closeAddModal() { document.getElementById('add-modal').style.display='none'; }
function addUniversity() {
  var name = document.getElementById('m-name').value.trim();
  var type = document.getElementById('m-type').value;
  var loc  = document.getElementById('m-location').value;
  var desc = document.getElementById('m-description').value.trim();
  var valid=true;
  ['m-name-err','m-type-err','m-loc-err'].forEach(function(id){
    document.getElementById(id).classList.remove('visible');
  });
  if (!name){ document.getElementById('m-name-err').classList.add('visible'); valid=false; }
  if (!type){ document.getElementById('m-type-err').classList.add('visible'); valid=false; }
  if (!loc) { document.getElementById('m-loc-err').classList.add('visible');  valid=false; }
  if (!valid) return;

  fetch('admin_univs.php?action=add', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({name,type,location:loc,description:desc})
  })
  .then(function(r){ return r.json(); })
  .then(function(json) {
    if (!json.success) throw new Error(json.error);
    universities.push({id:json.id, name, type, location:loc, description:desc, courses:[], exam:'', requirements:''});
    activeId=json.id;
    closeAddModal();
    showToast('success','University added successfully.');
    renderList();
  })
  .catch(function(err){ showToast('error','Add failed: '+err.message); });
}

// ── Courses modal ─────────────────────────────────────
function openCoursesModal() {
  var u = universities.find(function(x){ return x.id==activeId; });
  if (!u) return;
  tempCourses = (u.courses||[]).slice();
  renderCourseTagsEdit();
  document.getElementById('course-input').value='';
  document.getElementById('courses-modal').style.display='flex';
}
function closeCoursesModal() { document.getElementById('courses-modal').style.display='none'; }
function renderCourseTagsEdit() {
  var area = document.getElementById('courses-tags-edit');
  area.innerHTML='';
  tempCourses.forEach(function(c,i) {
    var tag = document.createElement('div');
    tag.className='course-tag-edit';
    tag.innerHTML=escHtml(c)+'<button onclick="removeTempCourse('+i+')" title="Remove">×</button>';
    area.appendChild(tag);
  });
}
function removeTempCourse(i) { tempCourses.splice(i,1); renderCourseTagsEdit(); }
function handleCourseInput(e) {
  if (e.key==='Enter'||e.key===',') {
    e.preventDefault();
    var val = document.getElementById('course-input').value.replace(',','').trim();
    if (val && !tempCourses.includes(val)) { tempCourses.push(val); renderCourseTagsEdit(); }
    document.getElementById('course-input').value='';
  }
}
function saveCoursesModal() {
  var val = document.getElementById('course-input').value.replace(',','').trim();
  if (val && !tempCourses.includes(val)) tempCourses.push(val);
  var u = universities.find(function(x){ return x.id==activeId; });
  if (u) u.courses=tempCourses.slice();
  closeCoursesModal();
  markDirty();
  var container=document.getElementById('d-courses');
  if (container) {
    container.innerHTML=tempCourses.map(function(c){
      return '<span class="course-tag">'+escHtml(c)+'</span>';
    }).join('');
  }
}

// ── Helpers ───────────────────────────────────────────
function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Toast ─────────────────────────────────────────────
var TOAST_ICONS = {
  success:'<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
  error:  '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
};
function showToast(type, message) {
  var c=document.getElementById('toast-container');
  var t=document.createElement('div');
  t.className='toast toast-'+type;
  t.innerHTML='<div class="toast-icon">'+TOAST_ICONS[type]+'</div><span class="toast-msg">'+message+'</span>'
    +'<button class="toast-close" onclick="closeToast(this.parentElement)"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
  c.appendChild(t);
  setTimeout(function(){ closeToast(t); },3500);
}
function closeToast(el) {
  if (!el||el.classList.contains('fade-out')) return;
  el.classList.add('fade-out');
  setTimeout(function(){ el.remove(); },350);
}

// Close modals on overlay click
document.getElementById('add-modal').addEventListener('click',function(e){ if(e.target===this) closeAddModal(); });
document.getElementById('courses-modal').addEventListener('click',function(e){ if(e.target===this) closeCoursesModal(); });

// ── Init ──────────────────────────────────────────────
updateTypeFilterLabel();
updateLocFilterLabel();
loadUniversities();
</script>
</body>
</html>