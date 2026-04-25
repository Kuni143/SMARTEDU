  /* ── Avatar upload ── */
  function handleAvatarChange(e) {
    var file = e.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(ev) {
      var src = ev.target.result;
      /* Update main profile card */
      var mainImg = document.getElementById('avatarImg');
      mainImg.src = src;
      mainImg.style.display = 'block';
      document.querySelector('.avatar-icon').style.display = 'none';
      /* Update modal preview */
      var modalImg  = document.getElementById('modalAvatarImg');
      var modalIcon = document.getElementById('modalAvatarIcon');
      modalImg.src = src;
      modalImg.style.display = 'block';
      modalIcon.style.display = 'none';
      /* Update sidebar logo */
      document.querySelector('.sidebar-logo').src = src;
    };
    reader.readAsDataURL(file);
    /* Reset so same file can be re-selected */
    e.target.value = '';
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

  /* ── Edit profile modal ── */
  function openEditModal() {
    document.getElementById('editName').value   = document.getElementById('displayName').textContent.replace('—','');
    document.getElementById('editGrade').value  = document.getElementById('displayGrade').textContent.replace('—','');
    document.getElementById('editStrand').value = document.getElementById('displayStrand').textContent.replace('—','');
    /* Sync avatar preview in modal */
    var mainImg   = document.getElementById('avatarImg');
    var modalImg  = document.getElementById('modalAvatarImg');
    var modalIcon = document.getElementById('modalAvatarIcon');
    if (mainImg.style.display === 'block') {
      modalImg.src = mainImg.src;
      modalImg.style.display = 'block';
      modalIcon.style.display = 'none';
    } else {
      modalImg.style.display = 'none';
      modalIcon.style.display = '';
    }
    document.getElementById('editModal').classList.add('show');
  }
  function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
  }
  function saveProfile() {
    var name   = document.getElementById('editName').value.trim();
    var grade  = document.getElementById('editGrade').value.trim();
    var strand = document.getElementById('editStrand').value.trim();
    document.getElementById('displayName').textContent   = name   || '—';
    document.getElementById('displayGrade').textContent  = grade  || '—';
    document.getElementById('displayStrand').textContent = strand || '—';
    /* Also update sidebar username */
    document.querySelector('.sidebar-username').textContent = name || 'User Name here';
    closeEditModal();
  }
  document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
  });