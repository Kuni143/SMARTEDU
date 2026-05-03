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

  window.toggleMenu       = toggleMenu;
  window.closeMenu        = closeMenu;
  window.openLogoutModal  = openLogoutModal;
  window.closeLogoutModal = closeLogoutModal;

  document.getElementById('logoutModal').addEventListener('click', function (e) {
    if (e.target === this) closeLogoutModal();
  });

  // ── Helpers ───────────────────────────────────────────────────────────────
  function esc(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

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
    document.getElementById('univName').textContent = u.name || 'Unknown University';

    var meta = '';
    if (u.type || u.location) {
      meta = '<span class="school-type-badge">'
        + esc(u.type || '') + (u.type && u.location ? ' · ' : '') + esc(u.location || '')
        + '</span>';
    }
    document.getElementById('univMeta').innerHTML = meta;

    var introEl = document.getElementById('introBullets');
    if (u.description && u.description.trim()) {
      var lines = u.description.split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
      introEl.innerHTML = lines.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('');
    } else {
      introEl.innerHTML = '';
    }

    if (u.courses && u.courses.length) {
      var coursesHtml = u.courses.map(function (c) { return '<li>' + esc(c) + '</li>'; }).join('');
      showSection('sectionCourses', 'coursesList', coursesHtml);
    }

    if (u.campus_branches && u.campus_branches.trim()) {
      showSection('sectionBranches', 'branchesList', textToList(u.campus_branches));
    }

    if (u.tuition_fees && u.tuition_fees.trim()) {
      showSection('sectionTuition', 'tuitionList', textToList(u.tuition_fees));
    }

    if (u.exam && u.exam.trim()) {
      showSection('sectionExam', 'examContent', textToList(u.exam));
    }

    if (u.enrollment_requirements && u.enrollment_requirements.trim()) {
      showSection('sectionEnrollment', 'enrollmentContent', textToList(u.enrollment_requirements));
    }

    if (u.contact_links && u.contact_links.trim()) {
      showSection('sectionContact', 'contactContent', textToList(u.contact_links));
    }

    if (u.requirements && u.requirements.trim()) {
      var admBox = document.getElementById('admissionBox');
      var admEl  = document.getElementById('admissionContent');
      admEl.innerHTML      = textToList(u.requirements);
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

  var nameToLoad = (typeof UNIV_NAME !== 'undefined' && UNIV_NAME)
    ? UNIV_NAME
    : (new URLSearchParams(window.location.search)).get('name') || '';

  loadUniversity(nameToLoad);

  // ── Bookmark toast ────────────────────────────────────────────────────────
  var toastTimer;

  function showBookmarkToast(message, added) {
    var toast  = document.getElementById('bookmarkToast');
    var msgEl  = document.getElementById('bookmarkToastMsg');
    var iconEl = document.getElementById('bookmarkToastIcon');
    if (!toast || !msgEl || !iconEl) return;

    clearTimeout(toastTimer);
    msgEl.textContent = message;

    if (added) {
      // Checkmark icon for "Added"
      iconEl.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;display:block;">'
        + '<circle cx="12" cy="12" r="10" fill="#061685" stroke="none"/>'
        + '<polyline points="8 12 11 15 16 9" stroke="#fff"/>'
        + '</svg>';
    } else {
      // X icon for "Removed"
      iconEl.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;display:block;">'
        + '<circle cx="12" cy="12" r="10" fill="#061685" stroke="none"/>'
        + '<line x1="8" y1="8" x2="16" y2="16" stroke="#fff"/>'
        + '<line x1="16" y1="8" x2="8" y2="16" stroke="#fff"/>'
        + '</svg>';
    }

    toast.classList.add('show');
    toastTimer = setTimeout(function () {
      toast.classList.remove('show');
    }, 2800);
  }

  // ── Bookmark toggle ───────────────────────────────────────────────────────
  (function () {
    var btn = document.getElementById('bookmarkBtn');
    if (!btn) return;

    btn.addEventListener('click', function () {
      var universityId = parseInt(btn.getAttribute('data-id'), 10);
      if (!universityId || universityId === 0) return;

      btn.disabled = true;

      var formData = new FormData();
      formData.append('university_id', universityId);

      fetch('api/toggle_bookmark.php', {
        method: 'POST',
        body:   formData
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          var isNowBookmarked = btn.classList.toggle('bookmarked');
          showBookmarkToast(
            isNowBookmarked
              ? 'Added to your saved universities.'
              : 'Removed from university bookmarks.',
            isNowBookmarked
          );
        } else {
          alert(data.message || 'Could not update bookmark. Please try again.');
        }
      })
      .catch(function (err) {
        console.error('Fetch error:', err);
        alert('Network error. Please try again.');
      })
      .finally(function () {
        btn.disabled = false;
      });
    });
  })();

})();