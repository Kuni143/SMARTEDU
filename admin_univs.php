<?php
require_once __DIR__ . '/config/db.php';

if (isset($_GET['action'])) {
  header('Content-Type: application/json');
  $action = $_GET['action'];

  try {
    $pdo = getDB();

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

    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $body    = json_decode(file_get_contents('php://input'), true);
      $id      = (int) ($body['id'] ?? 0);
      $courses = $body['courses'] ?? [];
      $name    = trim($body['name'] ?? '');

      if (!$id)   { echo json_encode(['success'=>false,'error'=>'Missing id.']);   exit; }
      if (!$name) { echo json_encode(['success'=>false,'error'=>'Name is required.']); exit; }

      $pdo->prepare("
        UPDATE universities
        SET name=:name, type=:type, location=:location, description=:description,
            campus_branches=:campus_branches,
            tuition_fees=:tuition_fees,
            exam=:exam, requirements=:requirements,
            enrollment_requirements=:enrollment_requirements,
            contact_links=:contact_links
        WHERE id=:id
      ")->execute([
        ':name'                    => $name,
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
  <style>
    /* ── Editable name input in detail card ── */
    .detail-name-input {
      width: 100%;
      border: 1.5px solid #d0d8ee;
      border-radius: 22px;
      outline: none;
      padding: 0 16px;
      height: 44px;
      font-family: 'Sora', sans-serif;
      font-size: 15px;
      font-weight: 700;
      color: #061685;
      background: #fff;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .detail-name-input:focus {
      border-color: #061685;
      box-shadow: 0 0 0 3px rgba(6,22,133,0.10);
    }
    .detail-name-input::placeholder {
      color: rgba(6,22,133,0.35);
      font-weight: 400;
    }
  </style>
</head>
<body>

<!-- ── Logout Confirmation Modal ── -->
<div id="logout-overlay" style="
  display:none;position:fixed;inset:0;
  background:rgba(6,22,133,0.18);
  z-index:9998;
  align-items:center;justify-content:center;
">
  <div style="
    background:#fff;border-radius:20px;
    padding:32px 28px 24px;
    width:100%;max-width:360px;
    box-shadow:0 8px 40px rgba(6,22,133,0.16);
    position:relative;
    font-family:'Sora',sans-serif;
  ">
    <button onclick="closeLogoutModal()" style="
      position:absolute;top:16px;right:18px;
      background:none;border:none;cursor:pointer;
      color:#8b9fd4;font-size:20px;line-height:1;
    ">&#x2715;</button>

    <div style="display:flex;align-items:center;gap:14px;margin-bottom:28px;">
      <span style="
        width:38px;height:38px;border-radius:50%;
        background:#dbe8fb;
        display:flex;align-items:center;justify-content:center;
        flex-shrink:0;
      ">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="#4a72c4" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="8"/>
          <line x1="12" y1="12" x2="12" y2="16"/>
        </svg>
      </span>
      <span style="font-size:15px;font-weight:600;color:#1a2140;">
        Are you sure you want to log out?
      </span>
    </div>

    <div style="height:1px;background:#e8ecf5;margin-bottom:20px;"></div>

    <div style="display:flex;align-items:center;justify-content:flex-end;gap:16px;">
      <button onclick="closeLogoutModal()" style="
        height:40px;padding:0 24px;border-radius:20px;
        border:none;background:#dbe8fb;
        font-family:'Sora',sans-serif;font-size:14px;font-weight:600;
        color:#4a72c4;cursor:pointer;
        transition:opacity 0.15s;
      ">Cancel</button>
      <button onclick="confirmLogout()" style="
        height:40px;padding:0 24px;border-radius:20px;
        border:none;background:none;
        font-family:'Sora',sans-serif;font-size:14px;font-weight:600;
        color:#061685;cursor:pointer;
        transition:opacity 0.15s;
      ">Log Out</button>
    </div>
  </div>
</div>

<!-- ── Delete University Confirmation Modal ── -->
<div id="delete-modal" style="
  display:none;position:fixed;inset:0;
  background:rgba(6,22,133,0.18);
  z-index:9998;
  align-items:center;justify-content:center;
">
  <div style="
    background:#fff;border-radius:20px;
    padding:32px 28px 24px;
    width:100%;max-width:380px;
    box-shadow:0 8px 40px rgba(6,22,133,0.16);
    position:relative;
    font-family:'Sora',sans-serif;
  ">
    <button onclick="closeDeleteModal()" style="
      position:absolute;top:16px;right:18px;
      background:none;border:none;cursor:pointer;
      color:#8b9fd4;font-size:20px;line-height:1;
    ">&#x2715;</button>

    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
      <span style="
        width:38px;height:38px;border-radius:50%;
        background:#fdecea;
        display:flex;align-items:center;justify-content:center;
        flex-shrink:0;
      ">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="#e24b4a" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"/>
          <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
          <path d="M10 11v6"/><path d="M14 11v6"/>
          <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
        </svg>
      </span>
      <span style="font-size:15px;font-weight:600;color:#1a2140;">
        Delete University
      </span>
    </div>

    <p style="font-size:13.5px;color:#5a6a9a;margin-bottom:8px;line-height:1.6;">
      Are you sure you want to delete
    </p>
    <p id="delete-modal-name" style="
      font-size:14px;font-weight:700;color:#061685;
      margin-bottom:24px;line-height:1.4;
      background:#e8ecf5;border-radius:10px;
      padding:8px 14px;
    "></p>

    <p style="font-size:12px;color:#e24b4a;margin-bottom:20px;">
      This action cannot be undone. All data for this university will be permanently removed.
    </p>

    <div style="height:1px;background:#e8ecf5;margin-bottom:20px;"></div>

    <div style="display:flex;align-items:center;justify-content:flex-end;gap:16px;">
      <button onclick="closeDeleteModal()" style="
        height:40px;padding:0 24px;border-radius:20px;
        border:none;background:#e8ecf5;
        font-family:'Sora',sans-serif;font-size:14px;font-weight:600;
        color:#061685;cursor:pointer;
        transition:opacity 0.15s;
      ">Cancel</button>
      <button onclick="confirmDelete()" style="
        height:40px;padding:0 24px;border-radius:20px;
        border:none;background:#e24b4a;
        font-family:'Sora',sans-serif;font-size:14px;font-weight:600;
        color:#fff;cursor:pointer;
        transition:opacity 0.15s;
      ">Delete</button>
    </div>
  </div>
</div>

<!-- ── Logout Toast ── -->
<div id="lo-toast" style="
  position:fixed;top:24px;right:24px;z-index:9999;
  display:flex;align-items:center;gap:12px;
  background:#fff;border:2px solid #b23b3b;border-radius:999px;
  padding:12px 20px 12px 14px;
  font-family:'Sora',sans-serif;font-size:13.5px;font-weight:600;color:#2d4a30;
  box-shadow:0 4px 20px rgba(0,0,0,0.10);
  transform:translateY(-120px);opacity:0;
  transition:transform 0.45s cubic-bezier(0.34,1.56,0.64,1),opacity 0.35s ease;
  pointer-events:none;
" aria-live="polite">
  <span style="width:24px;height:24px;background:#b23b3b;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
      <polyline points="16 17 21 12 16 7"/>
      <line x1="21" y1="12" x2="9" y2="12"/>
    </svg>
  </span>
  <span>Ending your session. Please wait..</span>
  <button onclick="loCloseToast()" style="background:none;border:none;cursor:pointer;font-size:15px;color:#b23b3b;margin-left:4px;line-height:1;pointer-events:all;">&#x2715;</button>
</div>

<!-- ── Navbar ── -->
<nav class="topnav">
  <a class="topnav-logo" href="admin_univs.php">
    <img src="pics/logo.png" alt="SmartEdu Logo" onerror="this.style.display='none'"/>
    <span>SmartEdu</span>
  </a>
  <div class="topnav-links">
    <a href="dashb_admin.php" class="topnav-link">Dashboard</a>
    <a href="admin_univs.php" class="topnav-link active">University</a>
  </div>
  <button class="topnav-logout" onclick="adminLogout()">
    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    Log out
  </button>
</nav>

<div class="page">
  <div class="welcome"><h1>Manage Universities</h1></div>

  <div class="top-bar">
    <div class="search-wrapper">
      <input type="text" class="search-input" id="searchInput" placeholder="Search university..." oninput="renderList()"/>
      <svg class="search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </div>

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
          <option value="LUC">Local Universities and Colleges</option>
          <option value="OGS">Other Government Schools</option>
          <option value="SUC">State Universities and Colleges</option>
          <option value="Private">Private Universities and Colleges</option>
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
// ── Logout modal ──────────────────────────────────────
function adminLogout() {
  document.getElementById('logout-overlay').style.display = 'flex';
}
function closeLogoutModal() {
  document.getElementById('logout-overlay').style.display = 'none';
}
document.getElementById('logout-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeLogoutModal();
});

var loTimer = null;
function confirmLogout() {
  closeLogoutModal();
  var t = document.getElementById('lo-toast');
  t.style.transform     = 'translateY(0)';
  t.style.opacity       = '1';
  t.style.pointerEvents = 'all';
  loTimer = setTimeout(function() {
    window.location.href = 'admin_logout.php';
  }, 1800);
}
function loCloseToast() {
  clearTimeout(loTimer);
  var t = document.getElementById('lo-toast');
  t.style.transform     = 'translateY(-120px)';
  t.style.opacity       = '0';
  t.style.pointerEvents = 'none';
}
</script>
<script src="JS/admin_univs.js"></script>
</body>
</html>