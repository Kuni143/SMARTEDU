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
        SELECT u.id, u.name, u.type, u.location, u.description,
               u.campus_branches, u.tuition_fees,
               u.exam, u.requirements,
               u.enrollment_requirements, u.contact_links,
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
            campus_branches=:campus_branches,
            tuition_fees=:tuition_fees,
            exam=:exam, requirements=:requirements,
            enrollment_requirements=:enrollment_requirements,
            contact_links=:contact_links
        WHERE id=:id
      ")->execute([
        ':type'                    => $body['type']                    ?? null,
        ':location'                => $body['location']                ?? null,
        ':description'             => $body['description']             ?? null,
        ':campus_branches'         => $body['campus_branches']         ?? null,
        ':tuition_fees'            => $body['tuition_fees']            ?? null,
        ':exam'                    => $body['exam']                    ?? null,
        ':requirements'            => $body['requirements']            ?? null,
        ':enrollment_requirements' => $body['enrollment_requirements'] ?? null,
        ':contact_links'           => $body['contact_links']           ?? null,
        ':id'                      => $id,
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

<script src="JS/admin_univs.js"></script>
</body>
</html>