/* ── Sample result history data ── */
var RESULTS = [
  {
    date: 'April 27, 2026',
    isLatest: true,
    topCourse: 'Computer Science',
    otherCourses: ['Information Technology', 'Software Engineering'],
    universities: ['University of the Philippines', 'De La Salle University']
  },
  {
    date: 'March 31, 2026',
    isLatest: false,
    topCourse: 'Business Management',
    otherCourses: ['Information Technology', 'Software Engineering'],
    universities: ['University of the Philippines', 'De La Salle University']
  },
  {
    date: 'April 12, 2026',
    isLatest: false,
    topCourse: 'Civil Engineering',
    otherCourses: ['Information Technology', 'Software Engineering'],
    universities: ['University of the Philippines', 'De La Salle University']
  },
  {
    date: 'March 04, 2026',
    isLatest: false,
    topCourse: 'Digital Media and Arts',
    otherCourses: ['Information Technology', 'Software Engineering'],
    universities: ['University of the Philippines', 'De La Salle University']
  }
];

/* ── Build cards ── */
function buildResults() {
  var grid = document.getElementById('resultsGrid');
  grid.innerHTML = RESULTS.map(function(r) {
    var latestBadge = r.isLatest
      ? '<span class="latest-badge">Latest</span>'
      : '';

    var otherTags = r.otherCourses.map(function(c) {
      return '<span class="result-tag">' + c + '</span>';
    }).join('');

    var uniTags = r.universities.map(function(u) {
      return '<span class="result-tag">' + u + '</span>';
    }).join('');

    return (
      '<div class="result-card">' +
        latestBadge +
        '<div>' +
          '<div class="result-date-label">Assessment Date:</div>' +
          '<div class="result-date">' + r.date + '</div>' +
        '</div>' +
        '<div class="result-top-rec">' +
          '<div class="result-top-rec-label">Top  Recommendation:</div>' +
          '<div class="result-top-rec-name">' + r.topCourse + '</div>' +
        '</div>' +
        '<div>' +
          '<div class="result-section-label">Other Suggested Courses:</div>' +
          '<div class="result-tags">' + otherTags + '</div>' +
        '</div>' +
        '<div>' +
          '<div class="result-section-label">Recommended Universities:</div>' +
          '<div class="result-tags">' + uniTags + '</div>' +
        '</div>' +
        '<a href="result_univs.html" class="btn-view-result">View Full Result</a>' +
      '</div>'
    );
  }).join('');
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
buildResults();