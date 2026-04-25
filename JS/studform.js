/* ── Question data ── */
const SECTIONS = [
  {
    name: 'Interests',
    prefix: 'A',
    startQ: 1,
    questions: [
      'I enjoy solving mathematical problems.',
      'I am interested in science experiments and discoveries.',
      'I like working with computers and technology.',
      'I enjoy helping and interacting with other people.',
      'I am interested in business, sales, or entrepreneurship.',
      'I like designing, drawing, or creating visual content.',
      'I enjoy writing stories, essays, or articles.',
      'I am interested in health and medical topics.',
      'I enjoy analyzing data and information.',
      'I like building or fixing things.',
      'I enjoy public speaking or presenting ideas.',
      'I am interested in law, politics, or social issues.',
      'I like organizing events or leading activities.',
      'I enjoy working outdoors or in fieldwork.',
      'I am interested in teaching or mentoring others.'
    ]
  },
  {
    name: 'Skills',
    prefix: 'B',
    startQ: 16,
    questions: [
      'I am good at solving logical or analytical problems.',
      'I have strong communication skills.',
      'I can work well in a team.',
      'I am good at managing my time and tasks.',
      'I can easily understand new concepts.',
      'I am skilled in using computers or digital tools.',
      'I am creative in thinking of new ideas.',
      'I can handle pressure and stressful situations.',
      'I am confident in making decisions.',
      'I am good at organizing information.',
      'I can lead or guide others effectively.',
      'I pay attention to details.',
      'I am good at problem-solving in real-life situations.',
      'I can adapt easily to new environments.',
      'I am skilled in research and information gathering.'
    ]
  },
  {
    name: 'Academic Strengths',
    prefix: 'C',
    startQ: 31,
    questions: [
      'I perform well in Mathematics subjects.',
      'I perform well in Science subjects.',
      'I perform well in English or communication subjects.',
      'I perform well in business-related subjects.',
      'I perform well in technical or ICT subjects.',
      'I perform well in arts or design subjects.',
      'I understand lessons quickly in most subjects.',
      'I consistently get good grades.',
      'I am confident in my academic abilities.',
      'I am motivated to study and learn new topics.'
    ]
  },
  {
    name: 'Strand Alignment',
    prefix: 'D',
    startQ: 41,
    questions: [
      'My SHS strand matches my interests.',
      'I chose my strand based on my skills.',
      'I feel prepared for college because of my strand.',
      'My strand helps me understand my future career path.',
      'I am willing to take courses related to my strand.',
      'I am open to courses outside my strand if needed.',
      'I believe my strand gives me an advantage in college.',
      'I understand the career opportunities related to my strand.',
      'I am confident in pursuing a course aligned with my strand.',
      'I have explored courses connected to my strand.'
    ]
  },
  {
    name: 'Career Preferences',
    prefix: 'E',
    startQ: 51,
    questions: [
      'I prefer a job that involves problem-solving.',
      'I prefer a job that involves helping people.',
      'I prefer a job with high income potential.',
      'I prefer a job that allows creativity.',
      'I prefer a stable and secure career.',
      'I prefer a job that involves leadership roles.',
      'I prefer a job that involves technology.',
      'I prefer a job with flexible working conditions.',
      'I prefer a job that involves continuous learning.',
      'I am willing to work hard to achieve my career goals.'
    ]
  }
];

const SCALE = ['Strongly Agree', 'Agree', 'Neutral', 'Disagree', 'Strongly Disagree'];
const SCALE_VALS = [5, 4, 3, 2, 1];

/* Build all question boxes on load */
(function buildQuestions() {
  SECTIONS.forEach(function(sec, si) {
    var box = document.getElementById('questions-box-' + si);
    var html = '';
    sec.questions.forEach(function(q, qi) {
      var qNum = sec.startQ + qi;
      var name = 'q' + qNum;
      html += '<div class="q-block">';
      html += '<p class="q-text">' + qNum + '. ' + q + '</p>';
      html += '<div class="q-options">';
      SCALE.forEach(function(label, li) {
        html += '<label class="radio-opt"><input type="radio" name="' + name + '" value="' + SCALE_VALS[li] + '"> ' + label + '</label>';
      });
      html += '</div></div>';
    });
    box.innerHTML = html;
  });
})();

/* ── Main step logic ── */
var currentStep = 0;

function goNext() {
  var grade = document.getElementById('grade').value;
  var strand = document.getElementById('strand').value;
  var valid = true;

  document.getElementById('err-grade').classList.remove('visible');
  document.getElementById('err-strand').classList.remove('visible');

  if (!grade) { document.getElementById('err-grade').classList.add('visible'); valid = false; }
  if (!strand) { document.getElementById('err-strand').classList.add('visible'); valid = false; }
  if (!valid) return;

  setStep(1);
}

function goPrev() { setStep(0); }

function setStep(n) {
  document.getElementById('step-' + currentStep).style.display = 'none';
  currentStep = n;
  document.getElementById('step-' + n).style.display = 'flex';

  for (var i = 0; i < 2; i++) {
    var icon = document.getElementById('step-icon-' + i);
    var lbl  = document.getElementById('step-label-' + i);
    if (i === n) { icon.classList.add('active'); lbl.classList.add('active'); }
    else { icon.classList.remove('active'); lbl.classList.remove('active'); }
  }

  if (n === 1) { setCareerSection(0); }
}

/* ── Career sub-stepper ── */
var currentCareer = 0;

function setCareerSection(n) {
  document.getElementById('career-' + currentCareer).style.display = 'none';
  currentCareer = n;
  document.getElementById('career-' + n).style.display = 'flex';

  /* Always go back to compact when switching sections */
  if (isExpanded) {
    isExpanded = false;
    document.body.classList.remove('view-expanded');
    document.documentElement.classList.remove('view-expanded');
    var iconCompact  = document.getElementById('view-icon-compact');
    var iconExpanded = document.getElementById('view-icon-expanded');
    var label        = document.getElementById('view-toggle-label');
    var scrollBtn    = document.getElementById('scrollTopBtn');
    if (iconCompact)  iconCompact.style.display  = 'block';
    if (iconExpanded) iconExpanded.style.display = 'none';
    if (label)        label.textContent = 'Expand';
    if (scrollBtn)    scrollBtn.style.display = 'none';
  }

  for (var i = 0; i < 5; i++) {
    var dot = document.getElementById('cs-dot-' + i);
    var lbl = dot.nextElementSibling;
    var line = document.getElementById('cs-line-' + i);

    dot.classList.remove('active', 'done');
    if (lbl) lbl.classList.remove('active');

    if (i < n) {
      dot.classList.add('done');
      dot.textContent = '✓';
      if (line) line.classList.add('done');
    } else if (i === n) {
      dot.classList.add('active');
      dot.textContent = i + 1;
      if (lbl) lbl.classList.add('active');
      if (line) line.classList.remove('done');
    } else {
      dot.textContent = i + 1;
      if (line) line.classList.remove('done');
    }
  }

  var nextBtn = document.getElementById('btn-career-next');
  nextBtn.textContent = (n === 4) ? 'Submit' : 'Next';
}

function careerNext() {
  if (!validateSection(currentCareer)) return;
  if (currentCareer < 4) {
    setCareerSection(currentCareer + 1);
    document.getElementById('form-card').scrollIntoView({ behavior: 'smooth' });
  } else {
    handleSubmit();
  }
}

function careerPrev() {
  if (currentCareer > 0) {
    setCareerSection(currentCareer - 1);
    document.getElementById('form-card').scrollIntoView({ behavior: 'smooth' });
  } else {
    setStep(0);
  }
}

function validateSection(si) {
  var sec = SECTIONS[si];
  var firstMissed = null;

  for (var qi = 0; qi < sec.questions.length; qi++) {
    var qNum = sec.startQ + qi;
    var checked = document.querySelector('input[name="q' + qNum + '"]:checked');
    if (!checked) {
      if (!firstMissed) firstMissed = qNum;
    }
  }

  if (firstMissed !== null) {
    showToast('error', 'Please answer all questions before continuing.');

    /* If compact, expand first so the question is reachable */
    if (!isExpanded) {
      /* Scroll the questions-box to the unanswered question */
      var box = document.getElementById('questions-box-' + si);
      var questionEl = document.querySelector('input[name="q' + firstMissed + '"]');
      if (questionEl && box) {
        var block = questionEl.closest('.q-block');
        if (block) {
          /* Delay slightly so toast renders first */
          setTimeout(function() {
            block.scrollIntoView({ behavior: 'smooth', block: 'center' });
            /* Highlight the missed question briefly */
            block.classList.add('q-highlight');
            setTimeout(function() { block.classList.remove('q-highlight'); }, 1800);
          }, 150);
        }
      }
    } else {
      /* Expanded mode — scroll the page */
      var questionElExp = document.querySelector('input[name="q' + firstMissed + '"]');
      if (questionElExp) {
        var blockExp = questionElExp.closest('.q-block');
        if (blockExp) {
          setTimeout(function() {
            blockExp.scrollIntoView({ behavior: 'smooth', block: 'center' });
            blockExp.classList.add('q-highlight');
            setTimeout(function() { blockExp.classList.remove('q-highlight'); }, 1800);
          }, 150);
        }
      }
    }
    return false;
  }
  return true;
}

function handleSubmit() {
  showToast('loading', 'Analyzing your responses, please wait...');
  setTimeout(function() {
    dismissAllToasts();
    showToast('success', 'Analysis complete! Redirecting to your results...');
    setTimeout(function() {
      window.location.href = 'result_univs.html';
    }, 2000);
  }, 2500);
}

/* ── View toggle (compact ↔ expanded) ── */
var isExpanded = false;

function toggleView() {
  isExpanded = !isExpanded;

  var iconCompact  = document.getElementById('view-icon-compact');
  var iconExpanded = document.getElementById('view-icon-expanded');
  var label        = document.getElementById('view-toggle-label');
  var scrollBtn    = document.getElementById('scrollTopBtn');

  if (isExpanded) {
    document.body.classList.add('view-expanded');
    document.documentElement.classList.add('view-expanded');
    iconCompact.style.display  = 'none';
    iconExpanded.style.display = 'block';
    label.textContent = 'Compact';
    if (scrollBtn) scrollBtn.style.display = 'flex';
  } else {
    document.body.classList.remove('view-expanded');
    document.documentElement.classList.remove('view-expanded');
    iconCompact.style.display  = 'block';
    iconExpanded.style.display = 'none';
    label.textContent = 'Expand';
    if (scrollBtn) scrollBtn.style.display = 'none';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

/* ── Scroll to top ── */
function scrollToTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* Show/hide scroll-to-top button */
window.addEventListener('scroll', function() {
  var btn = document.getElementById('scrollTopBtn');
  if (!btn) return;
  if (isExpanded || window.scrollY > 200) {
    btn.style.display = 'flex';
  } else {
    btn.style.display = 'none';
  }
});

/* ── Toast system ── */
var ICONS = {
  loading: '<svg viewBox="0 0 24 24"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>',
  success: '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
  error:   '<svg viewBox="0 0 24 24" style="fill:#e24b4a;stroke:none"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16" style="stroke:white;stroke-width:2;fill:none"/><circle cx="12" cy="17" r="1" style="fill:white"/></svg>',
  warning: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
};

function showToast(type, message, duration) {
  var c = document.getElementById('toast-container');
  var t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.innerHTML =
    '<div class="toast-icon">' + ICONS[type] + '</div>' +
    '<span class="toast-msg">' + message + '</span>' +
    '<button class="toast-close" onclick="closeToast(this.parentElement)">' +
    '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
  c.appendChild(t);
  if (type !== 'loading') {
    var d = duration || 4000;
    setTimeout(function() { closeToast(t); }, d);
  }
  return t;
}

function closeToast(el) {
  if (!el || el.classList.contains('fade-out')) return;
  el.classList.add('fade-out');
  setTimeout(function() { el.remove(); }, 350);
}

function dismissAllToasts() {
  document.querySelectorAll('.toast').forEach(closeToast);
}