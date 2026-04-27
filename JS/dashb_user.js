/* ─────────────────────────────────────────────────────────────
   dashb_user.js
   Expects two globals injected by dashb_user.php:
     FIELD_DATA  – array of { field, percent }
     TOP_COURSES – array of { rank, course_name, field_name, score }
   ───────────────────────────────────────────────────────────── */

/* ── Color palette (cycles if more than 5 fields) ── */
var CHART_PALETTE = ['#0d1b6e', '#8bb2fd', '#f5b731', '#4caf85', '#e07b5a'];

/* ── Build pie chart + dynamic legend ── */
window.addEventListener('DOMContentLoaded', function () {

  var canvas = document.getElementById('careerChart');
  if (!canvas) return;                     // no results — chart not rendered
  if (!FIELD_DATA || !FIELD_DATA.length) return;

  var labels = FIELD_DATA.map(function (f) { return f.field; });
  var values = FIELD_DATA.map(function (f) { return f.percent; });
  var colors = FIELD_DATA.map(function (_, i) { return CHART_PALETTE[i % CHART_PALETTE.length]; });

  /* Build legend */
  var legendEl = document.getElementById('chartLegend');
  if (legendEl) {
    legendEl.innerHTML = FIELD_DATA.map(function (f, i) {
      return '<div class="legend-item">'
        + '<div class="legend-dot" style="background:' + colors[i] + ';"></div>'
        + '<span class="legend-label">' + escHtml(f.field) + '</span>'
        + '</div>';
    }).join('');
  }

  /* Draw chart */
  var ctx = canvas.getContext('2d');
  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: labels,
      datasets: [{
        data: values,
        backgroundColor: colors,
        borderWidth: 0,
        hoverOffset: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend:  { display: false },
        tooltip: { enabled: true }
      }
    },
    plugins: [{
      id: 'pieLabels',
      afterDatasetsDraw: function (chart) {
        var c = chart.ctx;
        chart.data.datasets.forEach(function (dataset, i) {
          chart.getDatasetMeta(i).data.forEach(function (element, index) {
            var value = dataset.data[index];
            if (!value) return;
            var pos = element.tooltipPosition();
            c.save();
            c.fillStyle     = '#fff';
            c.font          = 'bold 14px Sora, sans-serif';
            c.textAlign     = 'center';
            c.textBaseline  = 'middle';
            c.fillText(value + '%', pos.x, pos.y);
            c.restore();
          });
        });
      }
    }]
  });
});

/* ── Sidebar ── */
function toggleMenu() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeMenu() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('show');
}

/* ── Logout modal ── */
function openLogoutModal() {
  closeMenu();
  document.getElementById('logoutModal').classList.add('show');
}
function closeLogoutModal() {
  document.getElementById('logoutModal').classList.remove('show');
}

/* ── Retake modal ── */
function openRetakeModal() {
  document.getElementById('retakeModal').classList.add('show');
}
function closeRetakeModal() {
  document.getElementById('retakeModal').classList.remove('show');
}

/* ── Close modals on overlay click ── */
document.addEventListener('click', function (e) {
  if (e.target === document.getElementById('logoutModal')) closeLogoutModal();
  if (e.target === document.getElementById('retakeModal')) closeRetakeModal();
});

/* ── Helper ── */
function escHtml(s) {
  return String(s || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}