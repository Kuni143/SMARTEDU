var timerInterval;

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

// ── Step 1: Send OTP ──────────────────────────────────
function goStep2() {
  var email   = document.getElementById('emailInput').value.trim();
  var errEl   = document.getElementById('emailErr');
  var errTxt  = document.getElementById('emailErrText');
  var inputEl = document.getElementById('emailInput');
  var btn     = document.getElementById('btnSendOtp');

  if (!email || !email.includes('@') || !email.includes('.')) {
    errTxt.textContent = 'Please enter a valid email address.';
    errEl.classList.add('visible');
    inputEl.classList.add('input-error');
    return;
  }

  errEl.classList.remove('visible');
  inputEl.classList.remove('input-error');
  btn.disabled    = true;
  btn.textContent = 'Sending…';

  fetch('forgetpass.php?ajax=send_otp', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ email: email })
  })
  .then(function(r){ return r.json(); })
  .then(function(json) {
    btn.disabled    = false;
    btn.textContent = 'Get OTP';

    if (json.success) {
      document.getElementById('step1').classList.remove('active');
      document.getElementById('step2').classList.add('active');
      startTimer(300);
    } else {
      if (json.error === 'not_found') {
        errTxt.textContent = "We couldn't find that email. Please check and re-enter.";
      } else if (json.error === 'too_soon') {
        errTxt.textContent = 'Please wait ' + json.wait + ' second(s) before requesting another code.';
      } else if (json.error === 'mail_failed') {
        errTxt.textContent = 'Failed to send email. Please try again later.';
      } else {
        errTxt.textContent = 'Something went wrong. Please try again.';
      }
      errEl.classList.add('visible');
      inputEl.classList.add('input-error');
    }
  })
  .catch(function() {
    btn.disabled    = false;
    btn.textContent = 'Get OTP';
    errTxt.textContent = 'Network error. Please check your connection.';
    errEl.classList.add('visible');
  });
}

// ── Step 2: Verify OTP ────────────────────────────────
function goStep3() {
  var boxes  = document.querySelectorAll('.otp-box');
  var otp    = Array.from(boxes).map(function(b){ return b.value; }).join('');
  var errEl  = document.getElementById('otpErr');
  var errTxt = document.getElementById('otpErrText');
  var btn    = document.getElementById('btnVerify');

  if (otp.length < 6 || otp.replace(/[0-9]/g,'').length > 0) {
    errTxt.textContent = 'Please enter all 6 digits.';
    errEl.classList.add('visible');
    boxes.forEach(function(b){ b.classList.add('otp-error'); });
    return;
  }

  errEl.classList.remove('visible');
  boxes.forEach(function(b){ b.classList.remove('otp-error'); });
  btn.disabled    = true;
  btn.textContent = 'Verifying…';

  fetch('forgetpass.php?ajax=verify_otp', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ otp: otp })
  })
  .then(function(r){ return r.json(); })
  .then(function(json) {
    btn.disabled    = false;
    btn.textContent = 'Verify';

    if (json.success) {
      clearInterval(timerInterval);
      document.getElementById('step2').classList.remove('active');
      document.getElementById('step3').classList.add('active');
    } else {
      if (json.error === 'wrong_otp' || json.error === 'expired') {
        errTxt.textContent = 'Incorrect or expired code. Please try again.';
      } else {
        errTxt.textContent = 'Something went wrong. Please restart the process.';
      }
      errEl.classList.add('visible');
      boxes.forEach(function(b){ b.classList.add('otp-error'); });
    }
  })
  .catch(function() {
    btn.disabled    = false;
    btn.textContent = 'Verify';
    errTxt.textContent = 'Network error. Please try again.';
    document.getElementById('otpErr').classList.add('visible');
  });
}

// ── Step 3: Save new password ─────────────────────────
function handleDone() {
  var p1     = document.getElementById('np1').value;
  var p2     = document.getElementById('np2').value;
  var errEl  = document.getElementById('resetErr');
  var errTxt = document.getElementById('resetErrText');
  var btn    = document.getElementById('btnDone');

  errEl.classList.remove('visible');
  document.getElementById('np1').classList.remove('input-error');
  document.getElementById('np2').classList.remove('input-error');

  if (!p1 || p1.length < 8) {
    errTxt.textContent = 'Password must be at least 8 characters.';
    errEl.classList.add('visible');
    document.getElementById('np1').classList.add('input-error');
    return;
  }
  if (p1 !== p2) {
    errTxt.textContent = 'Both passwords must match.';
    errEl.classList.add('visible');
    document.getElementById('np1').classList.add('input-error');
    document.getElementById('np2').classList.add('input-error');
    return;
  }

  btn.disabled    = true;
  btn.textContent = 'Saving…';

  fetch('forgetpass.php?ajax=reset_password', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ password: p1 })
  })
  .then(function(r){ return r.json(); })
  .then(function(json) {
    btn.disabled    = false;
    btn.textContent = 'Done';

    if (json.success) {
      window.location.href = 'login.php';
    } else {
      if (json.error === 'session_expired') {
        errTxt.textContent = 'Session expired. Please start over.';
      } else if (json.error === 'too_short') {
        errTxt.textContent = 'Password must be at least 8 characters.';
        document.getElementById('np1').classList.add('input-error');
      } else {
        errTxt.textContent = 'Something went wrong. Please try again.';
      }
      errEl.classList.add('visible');
    }
  })
  .catch(function() {
    btn.disabled    = false;
    btn.textContent = 'Done';
    errTxt.textContent = 'Network error. Please try again.';
    document.getElementById('resetErr').classList.add('visible');
  });
}

function clearResetErr() {
  document.getElementById('resetErr').classList.remove('visible');
  document.getElementById('np1').classList.remove('input-error');
  document.getElementById('np2').classList.remove('input-error');
}

// ── OTP box navigation ────────────────────────────────
function otpMove(el, idx) {
  el.value = el.value.replace(/[^0-9]/g, '');
  var boxes = document.querySelectorAll('.otp-box');
  boxes.forEach(function(b){ b.classList.remove('otp-error'); });
  document.getElementById('otpErr').classList.remove('visible');
  if (el.value && idx < 5) boxes[idx + 1].focus();
}

function otpBack(e, idx) {
  var boxes = document.querySelectorAll('.otp-box');
  if (e.key === 'Backspace' && !boxes[idx].value && idx > 0) {
    boxes[idx - 1].focus();
  }
}

// ── Timer ─────────────────────────────────────────────
function startTimer(seconds) {
  clearInterval(timerInterval);
  var s = seconds;
  updateDisplay(s);
  timerInterval = setInterval(function() {
    s--;
    if (s <= 0) {
      clearInterval(timerInterval);
      document.getElementById('countdown').textContent = '0:00';
      return;
    }
    updateDisplay(s);
  }, 1000);
}

function updateDisplay(s) {
  var m = Math.floor(s / 60);
  var r = s % 60;
  document.getElementById('countdown').textContent = m + ':' + (r < 10 ? '0' : '') + r;
}

// ── Resend OTP ────────────────────────────────────────
function resendOtp() {
  var boxes = document.querySelectorAll('.otp-box');
  boxes.forEach(function(b){ b.value = ''; b.classList.remove('otp-error'); });
  document.getElementById('otpErr').classList.remove('visible');
  boxes[0].focus();

  fetch('forgetpass.php?ajax=send_otp', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ email: '__resend__' })
  })
  .then(function(r){ return r.json(); })
  .then(function(json) {
    if (json.success) {
      startTimer(300);
    } else if (json.error === 'too_soon') {
      document.getElementById('otpErrText').textContent = 'Please wait ' + json.wait + ' second(s) before resending.';
      document.getElementById('otpErr').classList.add('visible');
    } else {
      document.getElementById('otpErrText').textContent = 'Failed to resend. Please try again.';
      document.getElementById('otpErr').classList.add('visible');
    }
  })
  .catch(function() {
    document.getElementById('otpErrText').textContent = 'Network error. Please try again.';
    document.getElementById('otpErr').classList.add('visible');
  });
}