// Eye icons
var eyeOpen  = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
var eyeClosed = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';

// Toggle password visibility
function togglePw(id, btn) {
  var input = document.getElementById(id);
  var svg   = document.getElementById('eye-' + id);
  var isHidden = input.type === 'password';

  input.type = isHidden ? 'text' : 'password';
  svg.innerHTML = isHidden ? eyeClosed : eyeOpen;
}

// Login attempts
var attempts = 0;
var maxAttempts = 3;

// Login function
function handleLogin() {
  var username = document.getElementById('username').value.trim();
  var password = document.getElementById('password').value;
  var errorRow = document.getElementById('error-row');
  var errorText = document.getElementById('error-text');

  // Empty input check
  if (!username || !password) {
    errorText.textContent = 'Please enter your username and password.';
    errorRow.classList.add('visible');
    return;
  }

  // ✅ SUCCESS LOGIN (you can change this later)
  if (username === "admin" && password === "1234") {
    window.location.href = "studform.html"; // redirect
    return;
  }

  // ❌ Failed login (your original logic)
  attempts++;
  var remaining = maxAttempts - attempts;

  if (attempts >= maxAttempts) {
    errorText.textContent = attempts + ' attempts used. Please try again later or Reset password.';
    errorRow.classList.add('visible');
  } else {
    errorText.textContent = 'Incorrect username or password. ' + remaining + ' attempt(s) remaining.';
    errorRow.classList.add('visible');
  }
}