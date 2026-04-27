// ── JS/detail_univ.js ─────────────────────────────────────────────────────
// Fetches university details from api/get_university_detail.php
// and renders them into detail_univ.php.
// ─────────────────────────────────────────────────────────────────────────

(function () {

  // ── Sidebar ──────────────────────────────────────────────────────────────
  function toggleMenu() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
  }

  function closeMenu() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
  }

  // ── Logout modal ─────────────────────────────────────────────────────────
  function openLogoutModal() {
    closeMenu();
    document.getElementById('logoutModal').classList.add('show');
  }

  function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('show');
  }

  // Expose to inline onclick handlers
  window.toggleMenu      = toggleMenu;
  window.closeMenu       = closeMenu;
  window.openLogoutModal = openLogoutModal;
  window.closeLogoutModal = closeLogoutModal;

  document.getElementById('logoutModal').addEventListener('click', function (e) {
    if (e.target === this) closeLogoutModal();
  });

  // ── Helpers ───────────────────────────────────────────────────────────────

  /** Escape HTML to prevent XSS */
  function esc(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /**
   * Convert a plain-text block (newline-separated lines) into
   * an unordered list. Each non-empty line becomes a <li>.
   * If only one line, returns a plain <p> instead.
   */
  function textToList(text) {
    if (!text || !text.trim()) return '';
    var lines = text.split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
    if (lines.length === 1) {
      return '<p class="section-freetext-p">' + esc(lines[0]) + '</p>';
    }
    return '<ul class="section-list">'
      + lines.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('')
      + '</ul>';
  }

  /** Show a section container and inject HTML into its content element */
  function showSection(sectionId, contentId, html) {
    if (!html) return;
    var sec = document.getElementById(sectionId);
    var el  = document.getElementById(contentId);
    if (!sec || !el) return;
    el.innerHTML = html;
    sec.style.display = '';
  }

  // ── States ────────────────────────────────────────────────────────────────
  function showError(msg) {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('detailCard').style.display   = 'none';
    var errEl = document.getElementById('errorState');
    errEl.style.display = '';
    document.getElementById('errorMsg').textContent = msg || 'University not found.';
  }

  function showCard() {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('errorState').style.display   = 'none';
    document.getElementById('detailCard').style.display   = '';
  }

  // ── Render ────────────────────────────────────────────────────────────────
  function renderUniversity(u) {
    // Title
    document.getElementById('univName').textContent = u.name || 'Unknown University';

    // Meta badge: type · location
    var meta = '';
    if (u.type || u.location) {
      meta = '<span class="school-type-badge">'
        + esc(u.type || '') + (u.type && u.location ? ' · ' : '') + esc(u.location || '')
        + '</span>';
    }
    document.getElementById('univMeta').innerHTML = meta;

    // Intro bullets — description split by newlines, or single bullet
    var introEl = document.getElementById('introBullets');
    if (u.description && u.description.trim()) {
      var lines = u.description.split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
      introEl.innerHTML = lines.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('');
    } else {
      introEl.innerHTML = '';
    }

    // Courses offered
    if (u.courses && u.courses.length) {
      var coursesHtml = u.courses.map(function (c) { return '<li>' + esc(c) + '</li>'; }).join('');
      showSection('sectionCourses', 'coursesList', coursesHtml);
    }

    // Campus branches
    if (u.campus_branches && u.campus_branches.trim()) {
      showSection('sectionBranches', 'branchesList', textToList(u.campus_branches));
    }

    // Tuition & fees
    if (u.tuition_fees && u.tuition_fees.trim()) {
      showSection('sectionTuition', 'tuitionList', textToList(u.tuition_fees));
    }

    // Entrance exam
    if (u.exam && u.exam.trim()) {
      showSection('sectionExam', 'examContent', textToList(u.exam));
    }

    // Enrollment requirements
    if (u.enrollment_requirements && u.enrollment_requirements.trim()) {
      showSection('sectionEnrollment', 'enrollmentContent', textToList(u.enrollment_requirements));
    }

    // Contact / links
    if (u.contact_links && u.contact_links.trim()) {
      showSection('sectionContact', 'contactContent', textToList(u.contact_links));
    }

    // Admission requirements (right column)
    if (u.requirements && u.requirements.trim()) {
      var admBox = document.getElementById('admissionBox');
      var admEl  = document.getElementById('admissionContent');
      admEl.innerHTML     = textToList(u.requirements);
      admBox.style.display = '';
    }

    showCard();
  }

  // ── Fetch ─────────────────────────────────────────────────────────────────
  function loadUniversity(name) {
    if (!name || !name.trim()) {
      showError('No university specified.');
      return;
    }

    fetch('api/get_university_detail.php?name=' + encodeURIComponent(name))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success && data.university) {
          renderUniversity(data.university);
        } else {
          showError(data.error || 'University not found.');
        }
      })
      .catch(function (err) {
        console.error('Fetch error:', err);
        showError('Network error. Please go back and try again.');
      });
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  // UNIV_NAME is injected by detail_univ.php via a <script> block.
  // Fallback: read from URL param if accessed directly.
  var nameToLoad = (typeof UNIV_NAME !== 'undefined' && UNIV_NAME)
    ? UNIV_NAME
    : (new URLSearchParams(window.location.search)).get('name') || '';

  loadUniversity(nameToLoad);

})();