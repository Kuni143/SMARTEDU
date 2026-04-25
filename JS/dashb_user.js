  /* Chart */
  window.addEventListener('DOMContentLoaded', function () {
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
          tooltip: { enabled: true },
          datalabels: { display: false }
        }
      },
      plugins: [{
        id: 'pieLabels',
        afterDatasetsDraw: function(chart) {
          var ctx = chart.ctx;
          chart.data.datasets.forEach(function(dataset, i) {
            var meta = chart.getDatasetMeta(i);
            meta.data.forEach(function(element, index) {
              var value = dataset.data[index];
              var pos = element.tooltipPosition();
              ctx.save();
              ctx.fillStyle = '#fff';
              ctx.font = 'bold 14px Sora, sans-serif';
              ctx.textAlign = 'center';
              ctx.textBaseline = 'middle';
              ctx.fillText(value + '%', pos.x, pos.y);
              ctx.restore();
            });
          });
        }
      }]
    });
  });

  /* Sidebar */
  function toggleMenu() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
  }
  function closeMenu() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
  }

  /* Logout modal */
  function openLogoutModal() {
    closeMenu();
    document.getElementById('logoutModal').classList.add('show');
  }
  function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('show');
  }

  /* Retake modal */
  function openRetakeModal() {
    document.getElementById('retakeModal').classList.add('show');
  }
  function closeRetakeModal() {
    document.getElementById('retakeModal').classList.remove('show');
  }

  /* Close modals on overlay click */
  document.addEventListener('click', function(e) {
    if (e.target === document.getElementById('logoutModal')) closeLogoutModal();
    if (e.target === document.getElementById('retakeModal')) closeRetakeModal();
  });