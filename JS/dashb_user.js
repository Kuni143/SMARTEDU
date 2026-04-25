  // ── Chart ──
  var ctx = document.getElementById('careerChart').getContext('2d');
  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: ['Information Technology Field', 'Business and Finance Field', 'Education Field'],
      datasets: [{
        data: [45, 35, 20],
        backgroundColor: ['#0d1b6e', '#8bb2fd', '#f5b731'],
        borderWidth: 0,
        hoverOffset: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(c) { return ' ' + c.label + ': ' + c.parsed + '%'; }
          },
          backgroundColor: '#061685',
          titleFont: { family: 'Sora' },
          bodyFont:  { family: 'Inter' },
          padding: 10, cornerRadius: 10
        },
        datalabels: false
      }
    },
    plugins: [{
      id: 'pieLabels',
      afterDatasetDraw(chart) {
        var { ctx, data } = chart;
        var meta = chart.getDatasetMeta(0);
        var labels = ['45%', '35%', '20%'];
        meta.data.forEach(function(arc, i) {
          var pos = arc.tooltipPosition();
          ctx.save();
          ctx.fillStyle = '#fff';
          ctx.font = 'bold 16px Sora, sans-serif';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillText(labels[i], pos.x, pos.y);
          ctx.restore();
        });
      }
    }]
  });

  // ── Top 3 popup ──
  function showTop3() {
    document.getElementById('top3Popup').classList.add('show');
  }
  function closeTop3() {
    document.getElementById('top3Popup').classList.remove('show');
  }
  function goToUnivs(course) {
    window.location.href = 'result_univs.html?course=' + encodeURIComponent(course);
  }

  // ── Logout modal ──
  function openLogoutModal() {
    closeMenu();
    document.getElementById('logoutModal').classList.add('show');
  }
  function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('show');
  }

  // ── Retake modal ──
  function openRetakeModal() {
    document.getElementById('retakeModal').classList.add('show');
  }
  function closeRetakeModal() {
    document.getElementById('retakeModal').classList.remove('show');
  }

  // ── Sidebar ──
  function toggleMenu() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
  }
  function closeMenu() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
  }

  document.getElementById('logoutModal').addEventListener('click', function(e) {
    if (e.target === this) closeLogoutModal();
  });
  document.getElementById('retakeModal').addEventListener('click', function(e) {
    if (e.target === this) closeRetakeModal();
  });