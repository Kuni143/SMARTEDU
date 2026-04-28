<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Profile</title>
  <link rel="icon" type="image/png" href="pics/logo.png">
  <link rel="stylesheet" href="CSS/studprofile.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
  <style>
    /* ── Edit modal override fixes ─────────────────────── */
    .edit-modal {
      width: min(480px, 92vw);
      max-height: 90vh;
      overflow-y: auto;
    }
    .edit-modal-body {
      display: flex;
      flex-direction: column;
      gap: 14px;
      padding: 20px 24px 0;
    }
    .edit-modal-title {
      font-size: 18px;
      font-weight: 700;
      color: #061685;
      margin: 0 0 4px;
    }

    /* Avatar in modal */
    .edit-avatar-wrap {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
    }
    .edit-avatar-circle {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: #e8eaf6;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      overflow: hidden;
      position: relative;
      border: 2px solid #c5cae9;
    }
    .edit-avatar-svg { width: 44px; height: 44px; fill: #9fa8da; }
    .edit-avatar-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: none;
    }
    .edit-avatar-hint {
      font-size: 12px;
      color: #888;
      cursor: pointer;
    }
    .edit-avatar-hint:hover { color: #061685; }

    /* Fields */
    .edit-field-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .edit-label {
      font-size: 13px;
      font-weight: 600;
      color: #444;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .edit-input {
      width: 100%;
      box-sizing: border-box;
      padding: 9px 12px;
      border: 1px solid #d0d5e8;
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      outline: none;
      transition: border-color 0.2s;
    }
    .edit-input:focus { border-color: #061685; }

    /* Read-only appearance for grade/strand */
    .edit-input--readonly,
    .edit-input[readonly] {
      background: #f0f2f8 !important;
      color: #666 !important;
      cursor: not-allowed !important;
      border-color: #dde0ee !important;
    }

    /* "from your form" tag */
    .edit-readonly-tag {
      font-size: 11px;
      font-weight: 500;
      background: #e8eaf6;
      color: #3949ab;
      border-radius: 4px;
      padding: 2px 6px;
    }

    /* Non-editable notice block */
    .edit-locked-notice {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      background: #f0f4ff;
      border: 1px solid #c5cae9;
      border-radius: 8px;
      padding: 10px 12px;
      font-size: 12.5px;
      color: #3949ab;
      line-height: 1.5;
    }
    .edit-locked-notice svg {
      flex-shrink: 0;
      width: 16px;
      height: 16px;
      margin-top: 1px;
      fill: #3949ab;
    }

    .edit-field-note {
      font-size: 12px;
      color: #888;
    }
  </style>
</head>
<body>

<!-- Sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMenu()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <button class="sidebar-close" onclick="closeMenu()" aria-label="Close">&#x2715;</button>
  <div class="sidebar-top">
    <img src="pics/logo.png" alt="SmartEdu Logo" class="sidebar-logo" id="sidebarLogo"/>
    <p class="sidebar-username" id="sidebarUsername">Loading...</p>
  </div>
  <nav class="sidebar-nav">
    <a href="dashb_user.php" class="sidebar-link">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Dashboard
    </a>
    <a href="studprofile.php" class="sidebar-link active">
      <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v1h20v-1c0-3.3-6.7-5-10-5z"/></svg>
      Profile
    </a>
    <a href="result_univs.php" class="sidebar-link">
      <svg viewBox="0 0 24 24" style="fill:none;stroke:#888;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      Universities
    </a>
    <a href="result_hist.php" class="sidebar-link">
      <svg viewBox="0 0 24 24" style="fill:none;stroke:#888;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 9 15"/></svg>
      Result History
    </a>
  </nav>
  <div class="sidebar-bottom">
    <button class="sidebar-logout" onclick="openLogoutModal()">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </button>
  </div>
</aside>

<!-- Navbar -->
<nav class="navbar">
  <a class="nav-logo" href="studprofile.php">
    <img src="pics/logo.png" alt="SmartEdu Logo"/>
    <span>SmartEdu</span>
  </a>
  <button class="hamburger" onclick="toggleMenu()" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- Main -->
<main class="main">

  <!-- Hidden file input for avatar upload -->
  <input type="file" id="avatarInput" accept="image/*" style="display:none;" onchange="handleAvatarChange(event)"/>

  <!-- Profile Card -->
  <div class="profile-card">
    <div class="avatar-wrap" onclick="document.getElementById('avatarInput').click()" title="Change photo">
      <svg class="avatar-icon" id="avatarIcon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v1h20v-1c0-3.3-6.7-5-10-5z"/>
      </svg>
      <img id="avatarImg" alt="Profile photo" style="display:none;"/>
      <div class="avatar-overlay">
        <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        <span>Change</span>
      </div>
    </div>

    <div class="profile-info">
      <div class="profile-field">
        <span class="profile-label">User name:</span>
        <span class="profile-value" id="displayName">—</span>
      </div>
      <div class="profile-field">
        <span class="profile-label">Grade Level:</span>
        <span class="profile-value" id="displayGrade">—</span>
      </div>
      <div class="profile-field">
        <span class="profile-label">Strand:</span>
        <span class="profile-value" id="displayStrand">—</span>
      </div>
    </div>

    <button class="edit-btn" onclick="openEditModal()" aria-label="Edit profile">
      <svg viewBox="0 0 24 24">
        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
      </svg>
    </button>
  </div>

  <!-- Three columns -->
  <div class="columns">

    <!-- Interest -->
    <div class="info-card">
      <div class="info-card-title">Interest</div>
      <ul class="info-list" id="interestList">
        <li class="loading-placeholder">Loading interests...</li>
      </ul>
    </div>

    <!-- Skills -->
    <div class="info-card">
      <div class="info-card-title">Skills</div>
      <ul class="info-list" id="skillList">
        <li class="loading-placeholder">Loading skills...</li>
      </ul>
    </div>

    <!-- University -->
    <div class="info-card">
      <div class="info-card-title">University</div>
      <ul class="univ-list" id="univList">
        <li class="loading-placeholder">Loading bookmarks...</li>
      </ul>
    </div>

  </div>
</main>

<!-- LOGOUT MODAL -->
<div class="modal-overlay" id="logoutModal">
  <div class="modal">
    <button class="modal-close" onclick="closeLogoutModal()">&#x2715;</button>
    <div class="modal-body">
      <div class="modal-icon">i</div>
      <p class="modal-text">Are you sure you want to log out?</p>
    </div>
    <div class="modal-divider"></div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeLogoutModal()">Cancel</button>
      <button class="btn-confirm" onclick="window.location.href='logout.php'">Log Out</button>
    </div>
  </div>
</div>

<!-- EDIT PROFILE MODAL (fixed layout) -->
<div class="modal-overlay" id="editModal">
  <div class="modal edit-modal">
    <button class="modal-close" onclick="closeEditModal()">&#x2715;</button>

    <div class="edit-modal-body">
      <p class="edit-modal-title">Edit Profile</p>

      <!-- Avatar picker -->
      <div class="edit-avatar-wrap">
        <div id="modalAvatarWrap" onclick="document.getElementById('avatarInput').click()" class="edit-avatar-circle">
          <svg id="modalAvatarIcon" viewBox="0 0 24 24" class="edit-avatar-svg">
            <path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v1h20v-1c0-3.3-6.7-5-10-5z"/>
          </svg>
          <img id="modalAvatarImg" alt="Preview" class="edit-avatar-img"/>
        </div>
        <span class="edit-avatar-hint" onclick="document.getElementById('avatarInput').click()">Click photo to change</span>
      </div>

      <!-- Username field -->
      <div class="edit-field-group">
        <label class="edit-label">User name</label>
        <input id="editName" type="text" placeholder="Enter username" class="edit-input" maxlength="50"/>
        <span id="usernameNote" class="edit-field-note"></span>
      </div>

      <!-- Grade Level — read-only, from studform data -->
      <div class="edit-field-group">
        <label class="edit-label">
          Grade Level
          <span class="edit-readonly-tag">from your form</span>
        </label>
        <input id="editGrade" type="text" class="edit-input edit-input--readonly" readonly tabindex="-1"/>
        <span class="edit-field-note" style="color:#3949ab;">
          ℹ This is taken from your submitted student form and cannot be edited here.
        </span>
      </div>

      <!-- Strand — read-only, from studform data -->
      <div class="edit-field-group">
        <label class="edit-label">
          Strand
          <span class="edit-readonly-tag">from your form</span>
        </label>
        <input id="editStrand" type="text" class="edit-input edit-input--readonly" readonly tabindex="-1"/>
        <span class="edit-field-note" style="color:#3949ab;">
          ℹ This is taken from your submitted student form and cannot be edited here.
        </span>
      </div>
    </div>

    <div class="modal-divider" style="margin-top:16px;"></div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeEditModal()">Cancel</button>
      <button class="btn-confirm" id="saveBtn" onclick="saveProfile()">Save</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast"></div>

<script src="JS/studprofile.js"></script>
</body>
</html>