/* ── Data store ── */
var universities = [
  {
    id: 1,
    name: 'Universidad ng Pilipinas',
    location: 'Quezon City',
    type: 'SUC',
    description: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
    courses: ['BS Biology', 'BA Comm', 'BS CS', 'BS Nursing'],
    exam: 'Date or period when entrance exam is conducted\nExam requirements (ID, application form, etc.)\nOnline or in-person format\nSample:\n  • UPCAT held annually in August\n  • Online application submission required by June',
    requirements: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam.'
  },
  {
    id: 2,
    name: 'De La Salle University',
    location: 'Manila',
    type: 'Private',
    description: 'A private Catholic research university in Manila offering a wide range of undergraduate and graduate programs.',
    courses: ['BS Accountancy', 'BS Civil Engineering', 'BS Computer Science', 'BS Management'],
    exam: 'DLSUCET held from October to January\nRequires birth certificate and high school report card\nOnline registration via admissions.dlsu.edu.ph',
    requirements: 'High school diploma or its equivalent. Must pass DLSUCET. Submission of complete application documents.'
  }
];

var nextId = 3;
var activeId = null;
var isDirty = false;
var tempCourses = [];

/* ── Filter state ── */
var TYPES = ['All', 'LUC', 'OGS', 'SUC', 'Private'];
var selectedTypes = ['All'];
var pendingTypes  = ['All'];
var filterOpen    = false;

/* ── Build filter options ── */
function buildFilterOptions() {
  var container = document.getElementById('filter-options');
  container.innerHTML = '';
  TYPES.forEach(function(type) {
    var div = document.createElement('div');
    div.className = 'filter-option' + (pendingTypes.includes(type) ? ' checked' : '');
    div.dataset.type = type;
    div.innerHTML =
      '<svg class="filter-check" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>' +
      type;
    div.onclick = function() { toggleType(type); };
    container.appendChild(div);
  });
}

function toggleType(type) {
  if (type === 'All') {
    pendingTypes = ['All'];
  } else {
    pendingTypes = pendingTypes.filter(function(t) { return t !== 'All'; });
    if (pendingTypes.includes(type)) {
      pendingTypes = pendingTypes.filter(function(t) { return t !== type; });
      if (pendingTypes.length === 0) pendingTypes = ['All'];
    } else {
      pendingTypes.push(type);
    }
  }
  buildFilterOptions();
}

function selectAllTypes() {
  pendingTypes = ['All'];
  buildFilterOptions();
}

function clearTypes() {
  pendingTypes = [];
  buildFilterOptions();
}

function toggleFilterDropdown() {
  filterOpen = !filterOpen;
  var dropdown = document.getElementById('filter-dropdown');
  var chevron  = document.getElementById('filter-chevron');
  if (filterOpen) {
    pendingTypes = selectedTypes.slice();
    buildFilterOptions();
    dropdown.style.display = 'block';
    chevron.classList.add('open');
  } else {
    dropdown.style.display = 'none';
    chevron.classList.remove('open');
  }
}

function applyFilter() {
  selectedTypes = pendingTypes.length ? pendingTypes.slice() : ['All'];
  filterOpen = false;
  document.getElementById('filter-dropdown').style.display = 'none';
  document.getElementById('filter-chevron').classList.remove('open');
  updateFilterLabel(selectedTypes);
  renderList();
}

function cancelFilter() {
  filterOpen = false;
  document.getElementById('filter-dropdown').style.display = 'none';
  document.getElementById('filter-chevron').classList.remove('open');
}

function updateFilterLabel(types) {
  var t = types || selectedTypes;
  var lbl = document.getElementById('filter-label');
  if (!t || t.includes('All') || t.length === 0) {
    lbl.textContent = 'All';
  } else if (t.length === 1) {
    lbl.textContent = t[0];
  } else {
    lbl.textContent = t.join(', ');
  }
}

/* Close dropdown on outside click */
document.addEventListener('click', function(e) {
  if (filterOpen && !document.getElementById('filter-wrapper').contains(e.target)) {
    cancelFilter();
  }
});

/* ── Render list ── */
function renderList() {
  var list   = document.getElementById('uni-list');
  var search = document.getElementById('searchInput').value.toLowerCase();
  list.innerHTML = '';

  var filtered = universities.filter(function(u) {
    var matchSearch = !search || u.name.toLowerCase().includes(search);
    var matchType   = selectedTypes.includes('All') || selectedTypes.length === 0 || selectedTypes.includes(u.type || '');
    return matchSearch && matchType;
  });

  if (filtered.length === 0) {
    list.innerHTML = '<div class="empty-state">No universities found.</div>';
    return;
  }

  filtered.forEach(function(u) {
    var row = document.createElement('div');
    row.className = 'uni-row' + (u.id === activeId ? ' active' : '');
    row.dataset.id = u.id;
    row.onclick = function() { toggleDetail(u.id); };

    var left = document.createElement('div');
    left.className = 'uni-row-left';
    left.innerHTML = '<span>' + u.name + '</span>';

    if (u.type) {
      var badge = document.createElement('span');
      badge.className = 'type-badge';
      badge.textContent = u.type;
      left.appendChild(badge);
    }

    row.appendChild(left);
    list.appendChild(row);

    if (u.id === activeId) {
      var detail = buildDetail(u);
      list.appendChild(detail);
    }
  });
}

/* ── Toggle detail ── */
function toggleDetail(id) {
  activeId = (activeId === id) ? null : id;
  isDirty = false;
  renderList();
}

/* ── Build detail card ── */
function buildDetail(u) {
  var tmpl  = document.getElementById('detail-template');
  var clone = tmpl.content.cloneNode(true);
  var card  = clone.querySelector('.detail-card');

  card.querySelector('#d-name').textContent = u.name;

  var typeSel = card.querySelector('#d-type');
  for (var i = 0; i < typeSel.options.length; i++) {
    if (typeSel.options[i].value === u.type) { typeSel.selectedIndex = i; break; }
  }

  var locSel = card.querySelector('#d-location');
  for (var j = 0; j < locSel.options.length; j++) {
    if (locSel.options[j].text === u.location) { locSel.selectedIndex = j; break; }
  }

  card.querySelector('#d-description').value  = u.description  || '';
  card.querySelector('#d-exam').value          = u.exam         || '';
  card.querySelector('#d-requirements').value  = u.requirements || '';

  renderCourseTags(card.querySelector('#d-courses'), u.courses);
  return card;
}

function renderCourseTags(container, courses) {
  container.innerHTML = '';
  (courses || []).forEach(function(c) {
    var tag = document.createElement('span');
    tag.className = 'course-tag';
    tag.textContent = c;
    container.appendChild(tag);
  });
}

/* ── Mark dirty ── */
function markDirty() {
  isDirty = true;
  var btn = document.getElementById('btn-save');
  if (btn) btn.disabled = false;
}

/* ── Save ── */
function saveUniversity() {
  var u = universities.find(function(x) { return x.id === activeId; });
  if (!u) return;

  u.type         = document.getElementById('d-type').value;
  u.location     = document.getElementById('d-location').value;
  u.description  = document.getElementById('d-description').value;
  u.exam         = document.getElementById('d-exam').value;
  u.requirements = document.getElementById('d-requirements').value;

  isDirty = false;
  showToast('success', 'University saved successfully.');
  renderList();
}

/* ── Cancel edit ── */
function cancelEdit() {
  activeId = null;
  isDirty = false;
  renderList();
}

/* ── Delete ── */
function deleteUniversity() {
  if (!confirm('Are you sure you want to delete this university?')) return;
  universities = universities.filter(function(u) { return u.id !== activeId; });
  activeId = null;
  showToast('success', 'University deleted.');
  renderList();
}

/* ── Add modal ── */
function openAddModal() {
  document.getElementById('m-name').value = '';
  document.getElementById('m-type').selectedIndex = 0;
  document.getElementById('m-location').selectedIndex = 0;
  document.getElementById('m-description').value = '';
  ['m-name-err', 'm-type-err', 'm-loc-err'].forEach(function(id) {
    document.getElementById(id).classList.remove('visible');
  });
  document.getElementById('add-modal').style.display = 'flex';
}

function closeAddModal() {
  document.getElementById('add-modal').style.display = 'none';
}

function addUniversity() {
  var name = document.getElementById('m-name').value.trim();
  var type = document.getElementById('m-type').value;
  var loc  = document.getElementById('m-location').value;
  var desc = document.getElementById('m-description').value.trim();
  var valid = true;

  ['m-name-err', 'm-type-err', 'm-loc-err'].forEach(function(id) {
    document.getElementById(id).classList.remove('visible');
  });

  if (!name) { document.getElementById('m-name-err').classList.add('visible'); valid = false; }
  if (!type) { document.getElementById('m-type-err').classList.add('visible'); valid = false; }
  if (!loc)  { document.getElementById('m-loc-err').classList.add('visible');  valid = false; }
  if (!valid) return;

  var newU = { id: nextId++, name: name, type: type, location: loc, description: desc, courses: [], exam: '', requirements: '' };
  universities.push(newU);
  activeId = newU.id;
  closeAddModal();
  showToast('success', 'University added successfully.');
  renderList();
}

/* ── Courses modal ── */
function openCoursesModal() {
  var u = universities.find(function(x) { return x.id === activeId; });
  if (!u) return;
  tempCourses = (u.courses || []).slice();
  renderCourseTagsEdit();
  document.getElementById('course-input').value = '';
  document.getElementById('courses-modal').style.display = 'flex';
}

function closeCoursesModal() {
  document.getElementById('courses-modal').style.display = 'none';
}

function renderCourseTagsEdit() {
  var area = document.getElementById('courses-tags-edit');
  area.innerHTML = '';
  tempCourses.forEach(function(c, i) {
    var tag = document.createElement('div');
    tag.className = 'course-tag-edit';
    tag.innerHTML = c + '<button onclick="removeTempCourse(' + i + ')" title="Remove">×</button>';
    area.appendChild(tag);
  });
}

function removeTempCourse(i) {
  tempCourses.splice(i, 1);
  renderCourseTagsEdit();
}

function handleCourseInput(e) {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    var val = document.getElementById('course-input').value.replace(',', '').trim();
    if (val && !tempCourses.includes(val)) {
      tempCourses.push(val);
      renderCourseTagsEdit();
    }
    document.getElementById('course-input').value = '';
  }
}

function saveCoursesModal() {
  var val = document.getElementById('course-input').value.replace(',', '').trim();
  if (val && !tempCourses.includes(val)) tempCourses.push(val);
  var u = universities.find(function(x) { return x.id === activeId; });
  if (u) { u.courses = tempCourses.slice(); }
  closeCoursesModal();
  markDirty();
  var container = document.getElementById('d-courses');
  if (container) renderCourseTags(container, u.courses);
}

/* ── Toast ── */
var TOAST_ICONS = {
  success: '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
  error:   '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
};

function showToast(type, message) {
  var c = document.getElementById('toast-container');
  var t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.innerHTML =
    '<div class="toast-icon">' + TOAST_ICONS[type] + '</div>' +
    '<span class="toast-msg">' + message + '</span>' +
    '<button class="toast-close" onclick="closeToast(this.parentElement)"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
  c.appendChild(t);
  setTimeout(function() { closeToast(t); }, 3500);
}

function closeToast(el) {
  if (!el || el.classList.contains('fade-out')) return;
  el.classList.add('fade-out');
  setTimeout(function() { el.remove(); }, 350);
}

/* Close modals on overlay click */
document.getElementById('add-modal').addEventListener('click', function(e) {
  if (e.target === this) closeAddModal();
});
document.getElementById('courses-modal').addEventListener('click', function(e) {
  if (e.target === this) closeCoursesModal();
});

/* ── Init ── */
updateFilterLabel(selectedTypes);
renderList();