/* ═══════════════════════════════════════════════════════════
   studprofile.js  –  SmartEdu Student Profile
   ═══════════════════════════════════════════════════════════ */

'use strict';

/* ── State ─────────────────────────────────────────────── */
let profileData = {
  username:           '',
  grade:              '',
  strand:             '',
  avatar_url:         null,
  can_change_username: true,
  days_remaining:     0,
};

let pendingAvatarFile = null;

/* ── Toast helper ───────────────────────────────────────── */
function showToast(msg, type = 'info') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast toast--' + type + ' toast--show';
  clearTimeout(el._timer);
  el._timer = setTimeout(() => { el.classList.remove('toast--show'); }, 3200);
}

/* ── Load profile data on page start ───────────────────── */
async function loadProfile() {
  try {
    const res  = await fetch('api/get_profile.php');
    const data = await res.json();
    if (!data.success) { showToast('Could not load profile.', 'error'); return; }

    profileData = { ...profileData, ...data };

    document.getElementById('displayName').textContent   = data.username  || '—';
    document.getElementById('displayGrade').textContent  = data.grade     || '—';
    document.getElementById('displayStrand').textContent = data.strand    || '—';
    document.getElementById('sidebarUsername').textContent = data.username || 'User';

    if (data.avatar_url) {
      setAvatarSrc(data.avatar_url);
    }
  } catch (e) {
    showToast('Network error loading profile.', 'error');
  }
}

/* ── Load interests & skills ────────────────────────────── */
async function loadInterestsSkills() {
  try {
    const res  = await fetch('api/get_interests_skills.php');
    const data = await res.json();
    if (!data.success) return;

    const interestList = document.getElementById('interestList');
    const skillList    = document.getElementById('skillList');

    interestList.innerHTML = '';
    skillList.innerHTML    = '';

    if (data.interests.length === 0) {
      interestList.innerHTML = '<li class="empty-note">No strong interests recorded yet.</li>';
    } else {
      data.interests.forEach(item => {
        const li = document.createElement('li');
        li.textContent = item;
        interestList.appendChild(li);
      });
    }

    if (data.skills.length === 0) {
      skillList.innerHTML = '<li class="empty-note">No strong skills recorded yet.</li>';
    } else {
      data.skills.forEach(item => {
        const li = document.createElement('li');
        li.textContent = item;
        skillList.appendChild(li);
      });
    }
  } catch (e) {
    document.getElementById('interestList').innerHTML = '<li class="empty-note">Could not load interests.</li>';
    document.getElementById('skillList').innerHTML    = '<li class="empty-note">Could not load skills.</li>';
  }
}

/* ── Load bookmarked universities ───────────────────────── */
async function loadBookmarks() {
  const univList = document.getElementById('univList');
  try {
    const res  = await fetch('api/get_bookmarks.php');
    const data = await res.json();
    if (!data.success) return;

    univList.innerHTML = '';

    if (data.bookmarks.length === 0) {
      univList.innerHTML = '<li class="empty-note">No bookmarked universities yet.<br><a href="result_univs.php" style="color:#061685;font-size:12px;">Browse universities →</a></li>';
      return;
    }

    data.bookmarks.forEach(u => {
      const li = document.createElement('li');
      const a  = document.createElement('a');
      // FIX: use ?name= to match what detail_univ.php expects
      a.href        = 'detail_univ.php?name=' + encodeURIComponent(u.name);
      a.textContent = u.name;
      li.appendChild(a);
      univList.appendChild(li);
    });
  } catch (e) {
    univList.innerHTML = '<li class="empty-note">Could not load bookmarks.</li>';
  }
}

/* ── Avatar helpers ─────────────────────────────────────── */
function setAvatarSrc(src) {
  const mainImg  = document.getElementById('avatarImg');
  const mainIcon = document.getElementById('avatarIcon');
  mainImg.src           = src;
  mainImg.style.display = 'block';
  mainIcon.style.display = 'none';

  const sidebarLogo = document.getElementById('sidebarLogo');
  if (sidebarLogo) sidebarLogo.src = src;

  syncModalAvatar(src, true);
}

function syncModalAvatar(src, show) {
  const modalImg  = document.getElementById('modalAvatarImg');
  const modalIcon = document.getElementById('modalAvatarIcon');
  if (show && src) {
    modalImg.src          = src;
    modalImg.style.display = 'block';
    modalIcon.style.display = 'none';
  } else {
    modalImg.style.display  = 'none';
    modalIcon.style.display = '';
  }
}

/* ── Avatar file selected (local preview only) ──────────── */
function handleAvatarChange(e) {
  const file = e.target.files[0];
  if (!file) return;

  pendingAvatarFile = file;

  const reader = new FileReader();
  reader.onload = function(ev) {
    const src = ev.target.result;
    setAvatarSrc(src);
  };
  reader.readAsDataURL(file);
  e.target.value = '';
}

/* ── Upload avatar to server ────────────────────────────── */
async function uploadAvatar() {
  if (!pendingAvatarFile) return true;

  const formData = new FormData();
  formData.append('avatar', pendingAvatarFile);

  const res  = await fetch('api/upload_avatar.php', { method: 'POST', body: formData });
  const data = await res.json();

  if (data.success) {
    profileData.avatar_url = data.avatar_url;
    pendingAvatarFile = null;
    return true;
  } else {
    showToast(data.message || 'Avatar upload failed.', 'error');
    return false;
  }
}

/* ── Sidebar ─────────────────────────────────────────────── */
function toggleMenu() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeMenu() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('show');
}

/* ── Logout modal ────────────────────────────────────────── */
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

/* ── Edit profile modal ──────────────────────────────────── */
function openEditModal() {
  const nameInput = document.getElementById('editName');
  nameInput.value = profileData.username || '';

  document.getElementById('editGrade').value  = profileData.grade  || '';
  document.getElementById('editStrand').value = profileData.strand || '';

  const note = document.getElementById('usernameNote');
  if (!profileData.can_change_username) {
    nameInput.setAttribute('readonly', true);
    nameInput.style.background = '#f4f4f4';
    nameInput.style.cursor     = 'not-allowed';
    note.textContent = `⚠ You can change your username in ${profileData.days_remaining} day(s).`;
    note.style.color = '#c0392b';
  } else {
    nameInput.removeAttribute('readonly');
    nameInput.style.background = '';
    nameInput.style.cursor     = '';
    note.textContent = 'You can change your username once every 30 days.';
    note.style.color = '#888';
  }

  const mainImg = document.getElementById('avatarImg');
  if (mainImg.style.display === 'block') {
    syncModalAvatar(mainImg.src, true);
  } else {
    syncModalAvatar(null, false);
  }

  document.getElementById('editModal').classList.add('show');
}

function closeEditModal() {
  document.getElementById('editModal').classList.remove('show');
  pendingAvatarFile = null;

  if (profileData.avatar_url) {
    setAvatarSrc(profileData.avatar_url);
  } else {
    const mainImg  = document.getElementById('avatarImg');
    const mainIcon = document.getElementById('avatarIcon');
    mainImg.style.display  = 'none';
    mainIcon.style.display = '';
  }
}

document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditModal();
});

/* ── Save profile ────────────────────────────────────────── */
async function saveProfile() {
  const saveBtn     = document.getElementById('saveBtn');
  const newUsername = document.getElementById('editName').value.trim();

  saveBtn.disabled    = true;
  saveBtn.textContent = 'Saving…';

  try {
    const avatarOk = await uploadAvatar();
    if (!avatarOk) {
      saveBtn.disabled    = false;
      saveBtn.textContent = 'Save';
      return;
    }

    if (newUsername && newUsername !== profileData.username) {
      if (!profileData.can_change_username) {
        showToast(`Username locked for ${profileData.days_remaining} more day(s).`, 'error');
        saveBtn.disabled    = false;
        saveBtn.textContent = 'Save';
        return;
      }

      const res  = await fetch('api/update_username.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ username: newUsername }),
      });
      const data = await res.json();

      if (!data.success) {
        showToast(data.message || 'Username update failed.', 'error');
        saveBtn.disabled    = false;
        saveBtn.textContent = 'Save';
        return;
      }

      profileData.username            = data.username;
      profileData.can_change_username = false;
      profileData.days_remaining      = 30;

      document.getElementById('displayName').textContent     = data.username;
      document.getElementById('sidebarUsername').textContent = data.username;
    }

    showToast('Profile saved!', 'success');
    closeEditModal();

  } catch (err) {
    showToast('Network error. Please try again.', 'error');
  } finally {
    saveBtn.disabled    = false;
    saveBtn.textContent = 'Save';
  }
}

/* ── Init ────────────────────────────────────────────────── */
(async function init() {
  await loadProfile();
  loadInterestsSkills();
  loadBookmarks();
})();