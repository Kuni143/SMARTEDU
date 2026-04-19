  // ── Data sets ──
  var sentiments = ['Strongly Agree', 'Agree', 'Neutral', 'Disagree', 'Strongly Disagree'];
  var sentimentIdx = 0;

  var ranges = {
    '1-20':  [190,570,100,840,590,390,730,160,160,260,350,160,250,200,190,170,180,185,195,110],
    '21-40': [310,430,220,660,510,280,590,200,220,380,410,210,290,310,260,240,290,310,270,180],
    '41-60': [150,490,80,760,530,350,680,145,150,240,320,145,230,185,175,155,170,172,182,100]
  };

  var colors = {
    'Agree':            { stroke: '#6ab0c8', fill: 'rgba(106,176,200,0.12)' },
    'Disagree':         { stroke: '#e07a7a', fill: 'rgba(224,122,122,0.12)' },
    'Neutral':          { stroke: '#a0b8d0', fill: 'rgba(160,184,208,0.12)' },
    'Strongly Agree':   { stroke: '#4a8a5a', fill: 'rgba(74,138,90,0.12)'  },
    'Strongly Disagree':{ stroke: '#c06030', fill: 'rgba(192,96,48,0.12)'  }
  };

  var currentRange = '41-60';
  var currentSentiment = 'Strongly Agree';

  // Build x-axis labels
  function buildLabels(range) {
    var parts = range.split('-');
    var start = parseInt(parts[0]);
    var end   = parseInt(parts[1]);
    var labels = [];
    for (var i = start; i <= end; i++) labels.push(String(i));
    return labels;
  }

  // Init chart
  var ctx = document.getElementById('insightsChart').getContext('2d');
  var chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: buildLabels(currentRange),
      datasets: [{
        label: currentSentiment,
        data: ranges[currentRange],
        borderColor: colors[currentSentiment].stroke,
        backgroundColor: colors[currentSentiment].fill,
        borderWidth: 2.5,
        pointRadius: 4,
        pointBackgroundColor: colors[currentSentiment].stroke,
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        tension: 0.45,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#061685',
          titleFont: { family: 'Sora', size: 12 },
          bodyFont:  { family: 'Sora', size: 12 },
          padding: 10,
          cornerRadius: 10
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(100,120,200,0.10)' },
          ticks: {
            font: { family: 'Inter', size: 11 },
            color: '#7a8ab0'
          },
          border: { display: false }
        },
        y: {
          min: 0,
          max: 900,
          ticks: {
            stepSize: 100,
            font: { family: 'Inter', size: 11 },
            color: '#7a8ab0'
          },
          grid: { color: 'rgba(100,120,200,0.10)' },
          border: { display: false }
        }
      }
    }
  });

  function updateChart() {
    var c = colors[currentSentiment];
    chart.data.labels = buildLabels(currentRange);
    chart.data.datasets[0].data = ranges[currentRange];
    chart.data.datasets[0].label = currentSentiment;
    chart.data.datasets[0].borderColor = c.stroke;
    chart.data.datasets[0].backgroundColor = c.fill;
    chart.data.datasets[0].pointBackgroundColor = c.stroke;
    chart.update();
  }

  function setRange(range, btn) {
    currentRange = range;
    document.querySelectorAll('.range-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    updateChart();
  }

  function changeSentiment(val) {
    currentSentiment = val;
    updateChart();
  }