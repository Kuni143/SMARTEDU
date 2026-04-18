    var eyeOpen = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    var eyeClosed = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';

    function togglePw(id, btn) {
      var input = document.getElementById(id);
      var svg = document.getElementById('eye-' + id);
      var isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      svg.innerHTML = isHidden ? eyeClosed : eyeOpen;
    }

    function checkPasswords() {
      var pw1 = document.getElementById('pw1').value;
      var pw2 = document.getElementById('pw2').value;
      var errorMsg = document.getElementById('pw-error');
      var pw2Input = document.getElementById('pw2');

      if (pw2.length > 0 && pw1 !== pw2) {
        errorMsg.classList.add('visible');
        pw2Input.classList.add('input-error');
      } else {
        errorMsg.classList.remove('visible');
        pw2Input.classList.remove('input-error');
      }
    }

    function handleNext() {
      var username = document.getElementById('username').value.trim();
      var email = document.getElementById('email').value.trim();
      var pw1 = document.getElementById('pw1').value;
      var pw2 = document.getElementById('pw2').value;
      var errorMsg = document.getElementById('pw-error');

      if (!username || !email || !pw1 || !pw2) {
        alert('Please fill in all fields.');
        return;
      }

      if (pw1 !== pw2) {
        errorMsg.classList.add('visible');
        document.getElementById('pw2').classList.add('input-error');
        return;
      }

      // Passwords match — proceed to next step
      // window.location.href = '/next-step.html';
      alert('Account created successfully! Redirecting...');
    }