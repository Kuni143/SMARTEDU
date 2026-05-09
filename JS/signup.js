var eyeOpen   = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
var eyeClosed = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>'
              + '<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>'
              + '<line x1="1" y1="1" x2="23" y2="23"/>';

function togglePw(id, btn) {
  var input  = document.getElementById(id);
  var svg    = document.getElementById('eye-' + id);
  var hidden = input.type === 'password';
  input.type    = hidden ? 'text' : 'password';
  svg.innerHTML = hidden ? eyeClosed : eyeOpen;
}

function checkPasswords() {
  var pw1      = document.getElementById('pw1').value;
  var pw2      = document.getElementById('pw2').value;
  var errMsg   = document.getElementById('pw-error');
  var pw2Input = document.getElementById('pw2');

  if (pw2.length > 0 && pw1 !== pw2) {
    errMsg.textContent = 'Make sure you entered the same password.';
    errMsg.classList.add('visible');
    pw2Input.classList.add('input-error');
  } else {
    errMsg.classList.remove('visible');
    pw2Input.classList.remove('input-error');
  }
}

// ── Terms modal ───────────────────────────────────────────────────────────────
var termsScrolled = false;   // tracks whether the user has reached the bottom

function openTerms() {
  var modal = document.getElementById('termsModal');
  var body  = document.getElementById('termsModalBody');
  var btn   = document.getElementById('btnIUnderstand');
  var hint  = document.getElementById('termsScrollHint');

  if (!modal) return;

  // Reset scroll position every time modal is opened
  if (body) body.scrollTop = 0;

  // Reset button & hint state based on whether user has ever scrolled to bottom
  // (we reset each open so they must re-read if they close & reopen)
  termsScrolled = false;
  if (btn)  { btn.disabled = true;  btn.classList.remove('enabled'); }
  if (hint) { hint.classList.remove('hidden'); }

  modal.classList.add('open');

  // Attach scroll listener (remove any old one first to avoid duplicates)
  if (body) {
    body.removeEventListener('scroll', onTermsScroll);
    body.addEventListener('scroll', onTermsScroll);
    // Edge-case: content shorter than the container (nothing to scroll)
    checkTermsBottom(body);
  }
}

function onTermsScroll() {
  checkTermsBottom(this);
}

function checkTermsBottom(body) {
  // "Reached the end" = scrolled within 20px of the bottom
  var nearBottom = body.scrollTop + body.clientHeight >= body.scrollHeight - 20;
  if (nearBottom && !termsScrolled) {
    termsScrolled = true;
    var btn  = document.getElementById('btnIUnderstand');
    var hint = document.getElementById('termsScrollHint');
    if (btn)  { btn.disabled = false; btn.classList.add('enabled'); }
    if (hint) { hint.classList.add('hidden'); }
  }
}

/**
 * Called when "I Understand" is clicked.
 * Closes the modal and checks the Terms checkbox automatically.
 */
function acceptTerms() {
  if (!termsScrolled) return;   // guard — shouldn't be reachable but just in case

  // Auto-check the checkbox
  var checkbox = document.getElementById('terms');
  if (checkbox) {
    checkbox.checked = true;
    // Clear any visible terms error
    var termsErr = document.getElementById('terms-error');
    if (termsErr) { termsErr.classList.remove('visible'); termsErr.textContent = ''; }
  }

  closeTerms();
}

function closeTerms() {
  var modal = document.getElementById('termsModal');
  if (modal) modal.classList.remove('open');
}

function closeTermsOutside(e) {
  if (e.target === document.getElementById('termsModal')) {
    closeTerms();
  }
}

// ── Live username availability check ─────────────────────────────────────────
(function () {
  var usernameInput = document.getElementById('username');
  var debounceTimer;

  if (!usernameInput) return;

  usernameInput.addEventListener('input', function () {
    clearTimeout(debounceTimer);

    var val   = usernameInput.value.trim();
    var errEl = document.getElementById('username-error');

    if (val.length < 3) {
      usernameInput.classList.remove('input-error');
      errEl.textContent = '';
      errEl.classList.remove('visible');
      return;
    }

    debounceTimer = setTimeout(function () {
      fetch('check_username.php?username=' + encodeURIComponent(val))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.taken) {
            usernameInput.classList.add('input-error');
            errEl.textContent = 'This username is already taken.';
            errEl.classList.add('visible');
          } else {
            usernameInput.classList.remove('input-error');
            errEl.textContent = '';
            errEl.classList.remove('visible');
          }
        })
        .catch(function () {});
    }, 500);
  });
})();