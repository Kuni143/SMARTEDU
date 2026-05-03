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
    var TYPE_FULL = {
      'LUC':     'Local Universities and Colleges',
      'OGS':     'Other Government Schools',
      'SUC':     'State Universities and Colleges',
      'Private': 'Private Universities and Colleges'
    };
    var left = '<div class="uni-row-left"><span>' + escHtml(u.name) + '</span>'
      + (u.type ? '<span class="type-badge">' + escHtml(TYPE_FULL[u.type] || u.type) + '</span>' : '') + '</div>';
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

  var TYPE_MAP = [
    { value: 'LUC',     label: 'Local Universities and Colleges' },
    { value: 'OGS',     label: 'Other Government Schools' },
    { value: 'SUC',     label: 'State Universities and Colleges' },
    { value: 'Private', label: 'Private Universities and Colleges' },
  ];
  var typeOpts = TYPE_MAP.map(function(t) {
    return '<option value="' + t.value + '"' + (t.value === u.type ? ' selected' : '') + '>'
      + t.label + '</option>';
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
    <!-- ── Section 1: Basic Info ── -->
    <div class="detail-section">
      <div class="detail-section-title">Basic Information</div>

      <div class="detail-row">
        <span class="detail-label">University Name</span>
        <input
          type="text"
          id="d-name"
          class="detail-name-input"
          value="${escHtml(u.name)}"
          oninput="markDirty()"
          placeholder="University name..."
        />
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
      <div class="detail-row align-top">
        <span class="detail-label">Description</span>
        <textarea id="d-description" rows="3" oninput="markDirty()" placeholder="Short description of the university...">${escHtml(u.description||'')}</textarea>
      </div>
      <div class="detail-row align-top">
        <span class="detail-label">Campus Branches</span>
        <textarea id="d-campus-branches" rows="3" oninput="markDirty()" placeholder="List campus branches or satellite offices...">${escHtml(u.campus_branches||'')}</textarea>
      </div>
    </div>

    <!-- ── Section 2: Courses ── -->
    <div class="detail-section">
      <div class="detail-section-title">Programs Offered</div>

      <div class="detail-row align-top">
        <span class="detail-label">Courses Offered</span>
        <div class="courses-area">
          <div class="courses-tags" id="d-courses">${courseTags || '<span class="courses-empty">No courses added yet.</span>'}</div>
          <button class="btn-icon" onclick="openCoursesModal()" title="Edit courses">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
          </button>
        </div>
      </div>
      <div class="detail-row align-top">
        <span class="detail-label">Tuition &amp; Fees Info</span>
        <textarea id="d-tuition-fees" rows="3" oninput="markDirty()" placeholder="Estimated tuition ranges, payment schemes, scholarships...">${escHtml(u.tuition_fees||'')}</textarea>
      </div>
    </div>

    <!-- ── Section 3: Admissions ── -->
    <div class="detail-section">
      <div class="detail-section-title">Admissions</div>

      <div class="detail-row align-top">
        <span class="detail-label">Entrance Exam</span>
        <textarea id="d-exam" rows="4" oninput="markDirty()" placeholder="Exam schedule, registration steps, coverage...">${escHtml(u.exam||'')}</textarea>
      </div>
      <div class="detail-row align-top">
        <span class="detail-label">Admission Requirements</span>
        <textarea id="d-requirements" rows="4" oninput="markDirty()" placeholder="Documents required to apply and qualify...">${escHtml(u.requirements||'')}</textarea>
      </div>
      <div class="detail-row align-top">
        <span class="detail-label">Enrollment Requirements</span>
        <textarea id="d-enrollment-requirements" rows="4" oninput="markDirty()" placeholder="Documents and steps required upon enrollment...">${escHtml(u.enrollment_requirements||'')}</textarea>
      </div>
    </div>

    <!-- ── Section 4: Contact ── -->
    <div class="detail-section">
      <div class="detail-section-title">Contact &amp; Links</div>

      <div class="detail-row align-top">
        <span class="detail-label">Official Links</span>
        <textarea id="d-contact-links" rows="3" oninput="markDirty()" placeholder="Website, admissions portal, Facebook page, email...">${escHtml(u.contact_links||'')}</textarea>
      </div>
    </div>

    <div class="detail-actions">
      <button class="btn-cancel" onclick="cancelEdit()">Cancel</button>
      <button class="btn-save" id="btn-save" onclick="saveUniversity()" disabled>Save Changes</button>
      <button class="btn-delete" onclick="openDeleteModal()">Delete University</button>
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

  var nameInput = document.getElementById('d-name');
  var newName   = nameInput ? nameInput.value.trim() : u.name;
  if (!newName) {
    nameInput.focus();
    showToast('error', 'University name cannot be empty.');
    return;
  }

  var payload = {
    id:                      u.id,
    name:                    newName,
    type:                    document.getElementById('d-type').value,
    location:                document.getElementById('d-location').value,
    description:             document.getElementById('d-description').value,
    campus_branches:         document.getElementById('d-campus-branches').value,
    tuition_fees:            document.getElementById('d-tuition-fees').value,
    exam:                    document.getElementById('d-exam').value,
    requirements:            document.getElementById('d-requirements').value,
    enrollment_requirements: document.getElementById('d-enrollment-requirements').value,
    contact_links:           document.getElementById('d-contact-links').value,
    courses:                 u.courses || [],
  };
  fetch('admin_univs.php?action=save', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  })
  .then(function(r){ return r.json(); })
  .then(function(json) {
    if (!json.success) throw new Error(json.error);
    u.name                    = payload.name;
    u.type                    = payload.type;
    u.location                = payload.location;
    u.description             = payload.description;
    u.campus_branches         = payload.campus_branches;
    u.tuition_fees            = payload.tuition_fees;
    u.exam                    = payload.exam;
    u.requirements            = payload.requirements;
    u.enrollment_requirements = payload.enrollment_requirements;
    u.contact_links           = payload.contact_links;
    isDirty = false;
    showToast('success','University saved successfully.');
    renderList();
  })
  .catch(function(err){ showToast('error','Save failed: '+err.message); });
}

// ── Delete modal ──────────────────────────────────────
function openDeleteModal() {
  var u = universities.find(function(x){ return x.id==activeId; });
  if (!u) return;
  // Set the university name in the modal
  var nameEl = document.getElementById('delete-modal-name');
  if (nameEl) nameEl.textContent = u.name;
  document.getElementById('delete-modal').style.display = 'flex';
}
function closeDeleteModal() {
  document.getElementById('delete-modal').style.display = 'none';
}
function confirmDelete() {
  closeDeleteModal();
  fetch('admin_univs.php?action=delete', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({id: activeId})
  })
  .then(function(r){ return r.json(); })
  .then(function(json) {
    if (!json.success) throw new Error(json.error);
    universities = universities.filter(function(u){ return u.id!=activeId; });
    activeId = null;
    showToast('success','University deleted successfully.');
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
    universities.push({
      id:json.id, name, type, location:loc, description:desc,
      campus_branches:'', tuition_fees:'',
      courses:[], exam:'', requirements:'',
      enrollment_requirements:'', contact_links:''
    });
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
    container.innerHTML = tempCourses.length
      ? tempCourses.map(function(c){ return '<span class="course-tag">'+escHtml(c)+'</span>'; }).join('')
      : '<span class="courses-empty">No courses added yet.</span>';
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
document.getElementById('delete-modal').addEventListener('click',function(e){ if(e.target===this) closeDeleteModal(); });

// ── Init ──────────────────────────────────────────────
updateTypeFilterLabel();
updateLocFilterLabel();
loadUniversities();