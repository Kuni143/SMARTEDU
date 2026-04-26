  var eyeOpen  = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  var eyeClosed = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';

  function togglePw(id) {
    var input = document.getElementById(id);
    var svg   = document.getElementById('eye-' + id);
    var isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    svg.innerHTML = isHidden ? eyeClosed : eyeOpen;
  }

  var toastTimer = null;

  function showToast() {
    var toast = document.getElementById('toast');
    toast.classList.add('show');
    toastTimer = setTimeout(function() { toast.classList.remove('show'); }, 3500);
  }

  function closeToast() {
    clearTimeout(toastTimer);
    document.getElementById('toast').classList.remove('show');
  }

  /* Client-side empty field check before form submits */
  function handleSubmit(e) {
    var username  = document.getElementById('username').value.trim();
    var password  = document.getElementById('password').value;
    var errorRow  = document.getElementById('error-row');
    var errorText = document.getElementById('error-text');

    if (!username || !password) {
      e.preventDefault();
      errorText.textContent = 'Please enter your username and password.';
      errorRow.classList.add('visible');
      return false;
    }

    /* Show loading state while form submits */
    var btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.textContent = 'Logging in...';
    return true;
  }

  /* Allow Enter key to trigger submit */
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      var form = document.querySelector('form');
      if (form) form.requestSubmit();
    }
  });