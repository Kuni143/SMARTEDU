var SCHOOLS = [
  { name: 'Universidad ng Pilipinas', type: 'SUC', desc: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.' },
  { name: 'Mapúa University', type: 'Private', desc: 'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.' },
  { name: 'De La Salle University', type: 'Private', desc: 'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.' },
  { name: 'Technological University of the Philippines', type: 'SUC', desc: 'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.' },
  { name: 'FEU Institute of Technology', type: 'Private', desc: 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium.' },
  { name: 'National University', type: 'Private', desc: 'Totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.' },
  { name: 'Pamantasan ng Lungsod ng Maynila', type: 'LUC', desc: 'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit.' },
  { name: 'Polytechnic University of the Philippines', type: 'SUC', desc: 'Neque porro quisquam est qui dolorem ipsum quia dolor sit amet, consectetur adipisci velit.' },
  { name: 'Ateneo de Manila University', type: 'Private', desc: 'At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti.' },
  { name: 'University of Santo Tomas', type: 'Private', desc: 'Nam libero tempore cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat.' },
  { name: 'Adamson University', type: 'Private', desc: 'Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates.' },
  { name: 'Pamantasan ng Lungsod ng Pasig', type: 'LUC', desc: 'Itaque earum rerum hic tenetur a sapiente delectus ut aut reiciendis voluptatibus maiores alias consequatur.' },
];

/* Top courses from the user's result — update these to match the actual result */
var TOP_COURSES = [
  'Information Technology',
  'Computer Science',
  'Industrial Engineer'
];

/* The active top course (first by default = what all cards are currently filtered for) */
var activeCourse = TOP_COURSES[0];

var activeTypes  = ['All'];
var pendingTypes = ['All'];
var searchQuery  = '';

/* ── Build grid ── */
function buildGrid() {
  var grid = document.getElementById('schoolGrid');
  grid.innerHTML = SCHOOLS.map(function(s, i) {
    return (
      '<div class="school-card" id="card-' + i + '">' +
        '<div class="school-name">' + s.name + '</div>' +
        '<div class="school-desc">' + s.desc + '</div>' +
        '<div class="school-card-footer">' +
          '<button class="btn-details" onclick="goDetails(\'' + encodeURIComponent(s.name) + '\')">Details</button>' +
        '</div>' +
      '</div>'
    );
  }).join('');
}

/* ── Apply visibility ── */
function applyVisibility() {
  var anyVisible = false;
  SCHOOLS.forEach(function(s, i) {
    var card = document.getElementById('card-' + i);
    if (!card) return;
    var matchType   = activeTypes.includes('All') || activeTypes.includes(s.type);
    var matchSearch = !searchQuery ||
      s.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      s.type.toLowerCase().includes(searchQuery.toLowerCase()) ||
      s.desc.toLowerCase().includes(searchQuery.toLowerCase());

    if (matchType && matchSearch) {
      card.style.display = 'flex';
      anyVisible = true;
    } else {
      card.style.display = 'none';
    }
  });

  var existing = document.getElementById('no-results-msg');
  if (!anyVisible) {
    if (!existing) {
      var msg = document.createElement('div');
      msg.id = 'no-results-msg';
      msg.className = 'no-results';
      msg.textContent = 'No universities found.';
      document.getElementById('schoolGrid').appendChild(msg);
    }
  } else {
    if (existing) existing.remove();
  }
}

function goDetails(name) {
  window.location.href = 'detail_univ.html?name=' + name;
}

/* ── Search ── */
function handleSearch() {
  searchQuery = document.getElementById('searchInput').value.trim();
  var clearBtn = document.getElementById('searchClearBtn');
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

/* ── Filter ── */
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
  if (dd.classList.contains('open') && !dd.contains(e.target) && !btn.contains(e.target)) {
    cancelFilter();
  }
});

/* ── Chathead / popup ── */
function toggleChatPopup() {
  var popup = document.getElementById('chatPopup');
  if (popup.classList.contains('open')) {
    closeChatPopup();
  } else {
    popup.classList.add('open');
    /* Rebuild top course list in case it changed */
    buildTopCoursesList();
  }
}

function closeChatPopup() {
  document.getElementById('chatPopup').classList.remove('open');
  document.getElementById('redirecting-text').style.display = 'none';
}

function buildTopCoursesList() {
  var list = document.getElementById('top-courses-list');
  list.innerHTML = TOP_COURSES.map(function(c) {
    return '<li><button onclick="selectCourse(\'' + c.replace(/'/g, "\\'") + '\')">' + c + '</button></li>';
  }).join('');
}

function selectCourse(course) {
  activeCourse = course;

  /* Update the active filter tag to show selected course */
  var tag = document.getElementById('activeFilterTag');
  tag.textContent = course;
  tag.classList.add('active-tag');

  /* Show redirecting message */
  document.getElementById('redirecting-text').style.display = 'block';

  /* Close popup after short delay */
  setTimeout(function() {
    closeChatPopup();
    /* Here you would normally filter by course — currently shows all since SCHOOLS
       don't have a courses array. When integrated with real data, filter by course. */
    applyVisibility();
  }, 1200);
}

/* ── Sidebar ── */
function toggleMenu() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeMenu() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('show');
}

/* ── Logout modal ── */
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

/* ── Init ── */
buildGrid();
applyVisibility();

/* Set initial tag to first top course */
document.getElementById('activeFilterTag').textContent = TOP_COURSES[0];