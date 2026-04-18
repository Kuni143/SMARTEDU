var timerInterval;

function goStep2() {
  var email = document.getElementById('emailInput').value.trim();
  var errEl = document.getElementById('emailErr');
  var inputEl = document.getElementById('emailInput');

  if (!email || !email.includes('@') || !email.includes('.')) {
    errEl.classList.add('visible');
    inputEl.classList.add('input-error');
    return;
  }

  errEl.classList.remove('visible');
  inputEl.classList.remove('input-error');
  document.getElementById('step1').classList.remove('active');
  document.getElementById('step2').classList.add('active');
  startTimer(151);
}

function goStep3() {
  clearInterval(timerInterval);
  document.getElementById('step2').classList.remove('active');
  document.getElementById('step3').classList.add('active');
}

function handleDone() {
  var p1 = document.getElementById('np1').value;
  var p2 = document.getElementById('np2').value;
  var errEl = document.getElementById('resetErr');

  if (!p1 || p1 !== p2) {
    errEl.classList.add('visible');
    document.getElementById('np1').classList.add('input-error');
    document.getElementById('np2').classList.add('input-error');
    return;
  }

  window.location.href = 'login.html';
}

function clearResetErr() {
  document.getElementById('resetErr').classList.remove('visible');
  document.getElementById('np1').classList.remove('input-error');
  document.getElementById('np2').classList.remove('input-error');
}

function otpMove(el, idx) {
  el.value = el.value.replace(/[^0-9]/g, '');
  var boxes = document.querySelectorAll('.otp-box');
  if (el.value && idx < 5) boxes[idx + 1].focus();
}

function otpBack(e, idx) {
  var boxes = document.querySelectorAll('.otp-box');
  if (e.key === 'Backspace' && !boxes[idx].value && idx > 0) {
    boxes[idx - 1].focus();
  }
}

function startTimer(seconds) {
  clearInterval(timerInterval);
  var s = seconds;
  updateDisplay(s);
  timerInterval = setInterval(function () {
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

function resendOtp() {
  var boxes = document.querySelectorAll('.otp-box');
  boxes.forEach(function(b){ b.value = ''; });
  boxes[0].focus();
  startTimer(151);
}