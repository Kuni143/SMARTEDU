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

// Client-side empty-field check before submit
document.querySelector('form').addEventListener('submit', function(e) {
  var username  = document.getElementById('username').value.trim();
  var password  = document.getElementById('password').value;
  var errorRow  = document.getElementById('error-row');
  var errorText = document.getElementById('error-text');

  if (!username || !password) {
    e.preventDefault();
    errorText.textContent = 'Please enter your username and password.';
    errorRow.classList.add('visible');
  }
});

// Lockout countdown timer
(function () {
  var errorRow = document.getElementById('error-row');
  if (!errorRow) return;

  var seconds = parseInt(errorRow.getAttribute('data-lockout') || '0', 10);
  if (seconds <= 0) return;

  var timerEl = document.getElementById('lockout-timer');
  if (!timerEl) return;

  function fmt(s) {
    var m   = Math.floor(s / 60);
    var sec = s % 60;
    return m + ':' + (sec < 10 ? '0' : '') + sec;
  }

  timerEl.textContent = fmt(seconds);

  var interval = setInterval(function () {
    seconds--;
    if (seconds <= 0) {
      clearInterval(interval);
      window.location.reload();
    } else {
      timerEl.textContent = fmt(seconds);
    }
  }, 1000);
})();

// Login success toast + redirect
(function () {
  var toast    = document.getElementById('loginToast');
  var redirect = document.getElementById('loginRedirect');
  if (!toast || !redirect) return;

  var target = redirect.getAttribute('data-href');
  if (!target) return;

  // Remove inline display:none and trigger the CSS animation via class
  toast.removeAttribute('style');
  toast.classList.add('show');

  setTimeout(function () {
    window.location.href = target;
  }, 1800);
})();