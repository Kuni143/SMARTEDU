  // Pull school name from URL param if present
  var params = new URLSearchParams(window.location.search);
  var name = params.get('name');
  if (name) {
    document.getElementById('univName').textContent = decodeURIComponent(name);
  }

  // ── Sidebar / hamburger (from reference code) ──
  function toggleMenu() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
  }

  function closeMenu() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
  }