<?php
require_once __DIR__ . '/config/db.php';

$allowedSentiments = [
  'Strongly Agree', 'Agree', 'Neutral',
  'Disagree', 'Strongly Disagree'
];
$allowedRanges = [
  '1-20'  => [1,  20],
  '21-40' => [21, 40],
  '41-60' => [41, 60],
];

function fetchTally(PDO $pdo, int $start, int $end, string $sentiment): array {
  $unions = [];
  for ($i = $start; $i <= $end; $i++) {
    $unions[] = "SELECT $i AS n";
  }
  $numbersSql = implode(' UNION ALL ', $unions);

  $sql = "
    SELECT
      nums.n                        AS question_no,
      COALESCE(COUNT(r.id), 0)     AS tally
    FROM ($numbersSql) AS nums
    LEFT JOIN responses r
      ON  r.question_no = nums.n
      AND r.sentiment   = :sentiment
    GROUP BY nums.n
    ORDER BY nums.n
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':sentiment' => $sentiment]);
  return $stmt->fetchAll();
}

if (isset($_GET['ajax'])) {
  header('Content-Type: application/json');

  $sentiment = $_GET['sentiment'] ?? 'Strongly Agree';
  $range     = $_GET['range']     ?? '1-20';

  if (!in_array($sentiment, $allowedSentiments, true)) {
    echo json_encode(['error' => 'Invalid sentiment.']); exit;
  }
  if (!array_key_exists($range, $allowedRanges)) {
    echo json_encode(['error' => 'Invalid range.']); exit;
  }

  [$qStart, $qEnd] = $allowedRanges[$range];

  try {
    $pdo  = getDB();
    $rows = fetchTally($pdo, $qStart, $qEnd, $sentiment);

    $labels = []; $data = [];
    foreach ($rows as $row) {
      $labels[] = (string) $row['question_no'];
      $data[]   = (int)    $row['tally'];
    }
    echo json_encode(['labels' => $labels, 'data' => $data, 'total' => array_sum($data)]);

  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
  }
  exit;
}

$initSentiment = 'Strongly Agree';
$initRange     = '1-20';
[$initStart, $initEnd] = $allowedRanges[$initRange];

$initLabels     = [];
$initData       = [];
$totalStudents  = 0;
$totalResponses = 0;
$mostCommon     = '—';

try {
  $pdo  = getDB();
  $rows = fetchTally($pdo, $initStart, $initEnd, $initSentiment);
  foreach ($rows as $row) {
    $initLabels[] = (string) $row['question_no'];
    $initData[]   = (int)    $row['tally'];
  }

  $totalStudents  = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
  $totalResponses = (int) $pdo->query("SELECT COUNT(*) FROM responses")->fetchColumn();

  $topRow = $pdo->query("
    SELECT sentiment, COUNT(*) AS cnt
    FROM responses
    GROUP BY sentiment
    ORDER BY cnt DESC
    LIMIT 1
  ")->fetch();
  if ($topRow) $mostCommon = $topRow['sentiment'];

} catch (PDOException $e) {
  // Page still renders with empty/zero state
}

$initLabelsJson = json_encode($initLabels);
$initDataJson   = json_encode($initData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard</title>
  <link rel="icon" type="image/png" href="pics/logo.png"/>
  <link rel="stylesheet" href="CSS/dashb_admin.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>

<!-- ── Logout Confirmation Modal ── -->
<div id="logout-overlay" style="
  display:none;position:fixed;inset:0;
  background:rgba(6,22,133,0.18);
  z-index:9998;
  align-items:center;justify-content:center;
">
  <div style="
    background:#fff;border-radius:20px;
    padding:32px 28px 24px;
    width:100%;max-width:360px;
    box-shadow:0 8px 40px rgba(6,22,133,0.16);
    position:relative;
    font-family:'Sora',sans-serif;
  ">
    <button onclick="closeLogoutModal()" style="
      position:absolute;top:16px;right:18px;
      background:none;border:none;cursor:pointer;
      color:#8b9fd4;font-size:20px;line-height:1;
    ">&#x2715;</button>

    <div style="display:flex;align-items:center;gap:14px;margin-bottom:28px;">
      <span style="
        width:38px;height:38px;border-radius:50%;
        background:#dbe8fb;
        display:flex;align-items:center;justify-content:center;
        flex-shrink:0;
      ">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="#4a72c4" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="8"/>
          <line x1="12" y1="12" x2="12" y2="16"/>
        </svg>
      </span>
      <span style="font-size:15px;font-weight:600;color:#1a2140;">
        Are you sure you want to log out?
      </span>
    </div>

    <div style="height:1px;background:#e8ecf5;margin-bottom:20px;"></div>

    <div style="display:flex;align-items:center;justify-content:flex-end;gap:16px;">

      <button onclick="confirmLogout()" style="
        height:40px;padding:0 24px;border-radius:20px;
        border:none;background:none;
        font-family:'Sora',sans-serif;font-size:14px;font-weight:600;
        color:#061685;cursor:pointer;
        transition:opacity 0.15s;
      ">Yes</button>
      <button onclick="closeLogoutModal()" style="
        height:40px;padding:0 24px;border-radius:20px;
        border:none;background:#dbe8fb;
        font-family:'Sora',sans-serif;font-size:14px;font-weight:600;
        color:#4a72c4;cursor:pointer;
        transition:opacity 0.15s;
      ">No</button>
    </div>
  </div>
</div>

<!-- ── Logout Toast ── -->
<div id="lo-toast" style="
  position:fixed;top:24px;right:24px;z-index:9999;
  display:flex;align-items:center;gap:12px;
  background:#fff;border:2px solid #b23b3b;border-radius:999px;
  padding:12px 20px 12px 14px;
  font-family:'Sora',sans-serif;font-size:13.5px;font-weight:600;color:#2d4a30;
  box-shadow:0 4px 20px rgba(0,0,0,0.10);
  transform:translateY(-120px);opacity:0;
  transition:transform 0.45s cubic-bezier(0.34,1.56,0.64,1),opacity 0.35s ease;
  pointer-events:none;
" aria-live="polite">
  <span style="width:24px;height:24px;background:#b23b3b;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
    <!-- Replace your current SVG with this -->
  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
  </span>
  <span>Ending your session. Please wait..</span>
  <button onclick="loCloseToast()" style="background:none;border:none;cursor:pointer;font-size:15px;color:#b23b3b;margin-left:4px;line-height:1;pointer-events:all;">&#x2715;</button>
</div>

<!-- ── Navbar ── -->
<nav class="topnav">
  <a class="topnav-logo" href="dashb_admin.php">
    <img src="pics/logo.png" alt="SmartEdu Logo" onerror="this.style.display='none'"/>
    <span>SmartEdu</span>
  </a>
  <div class="topnav-links">
    <a href="dashb_admin.php" class="topnav-link active">Dashboard</a>
    <a href="admin_univs.php" class="topnav-link">University</a>
  </div>
  <button class="topnav-logout" onclick="adminLogout()">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
      <polyline points="16 17 21 12 16 7"/>
      <line x1="21" y1="12" x2="9" y2="12"/>
    </svg>
    Log out
  </button>
</nav>

<div class="page">

  <div class="welcome">
    <h1>Welcome Back, Admin!</h1>
  </div>

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon blue">
        <svg viewBox="0 0 24 24">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <div>
        <div class="stat-label">Total Students</div>
        <div class="stat-value"><?= number_format($totalStudents) ?></div>
        <div class="stat-sub">Submitted the questionnaire</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon green">
        <svg viewBox="0 0 24 24">
          <polyline points="9 11 12 14 22 4"/>
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
        </svg>
      </div>
      <div>
        <div class="stat-label">Total Responses</div>
        <div class="stat-value"><?= number_format($totalResponses) ?></div>
        <div class="stat-sub">Across all 60 questions</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon orange">
        <svg viewBox="0 0 24 24">
          <path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/>
        </svg>
      </div>
      <div>
        <div class="stat-label">Most Common Answer</div>
        <div class="stat-value small"><?= htmlspecialchars($mostCommon) ?></div>
        <div class="stat-sub">Overall top sentiment</div>
      </div>
    </div>
  </div>

  <div class="chart-card">
    <div class="chart-header">
      <span class="chart-title">Student Insights</span>
      <div class="filter-group">
        <select class="filter-sentiment" id="sentimentBtn" onchange="changeSentiment(this.value)">
          <?php foreach ($allowedSentiments as $s): ?>
            <option value="<?= $s ?>" <?= $s === $initSentiment ? 'selected' : '' ?>>
              <?= $s ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="range-group">
          <button class="range-btn active" onclick="setRange('1-20',this)">1–20</button>
          <button class="range-btn"        onclick="setRange('21-40',this)">21–40</button>
          <button class="range-btn"        onclick="setRange('41-60',this)">41–60</button>
        </div>
      </div>
    </div>

    <div class="chart-wrap">
      <canvas id="insightsChart"></canvas>
      <div class="empty-state" id="emptyState">
        <svg viewBox="0 0 24 24">
          <rect x="3" y="3" width="18" height="18" rx="3"/>
          <path d="M3 9h18M9 21V9"/>
        </svg>
        <p>No responses yet</p>
        <span>Student submissions will appear here once they complete the form.</span>
      </div>
      <div class="chart-loading" id="chartLoading">
        <div class="spinner"></div>
      </div>
    </div>
  </div>

</div>

<script>
// ── Chart ─────────────────────────────────────────────
var initLabels    = <?= $initLabelsJson ?>;
var initData      = <?= $initDataJson ?>;
var initSentiment = <?= json_encode($initSentiment) ?>;

var currentRange     = '1-20';
var currentSentiment = initSentiment;
var isFetching       = false;

var colors = {
  'Agree':             { stroke:'#6ab0c8', fill:'rgba(106,176,200,0.12)' },
  'Disagree':          { stroke:'#e07a7a', fill:'rgba(224,122,122,0.12)' },
  'Neutral':           { stroke:'#a0b8d0', fill:'rgba(160,184,208,0.12)' },
  'Strongly Agree':    { stroke:'#4a8a5a', fill:'rgba(74,138,90,0.12)'   },
  'Strongly Disagree': { stroke:'#c06030', fill:'rgba(192,96,48,0.12)'   },
};

var ctx   = document.getElementById('insightsChart').getContext('2d');
var c     = colors[currentSentiment];
var chart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: initLabels,
    datasets: [{
      label:                currentSentiment,
      data:                 initData,
      borderColor:          c.stroke,
      backgroundColor:      c.fill,
      borderWidth:          2.5,
      pointRadius:          4,
      pointBackgroundColor: c.stroke,
      pointBorderColor:     '#fff',
      pointBorderWidth:     2,
      tension:              0.45,
      fill:                 true,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#061685',
        titleFont: { family:'Sora', size:12 },
        bodyFont:  { family:'Sora', size:12 },
        padding: 10, cornerRadius: 10,
        callbacks: { label: function(ctx){ return ' ' + ctx.parsed.y + ' responses'; } }
      }
    },
    scales: {
      x: {
        grid:   { color:'rgba(100,120,200,0.10)' },
        ticks:  { font:{ family:'Inter', size:11 }, color:'#7a8ab0' },
        border: { display: false }
      },
      y: {
        min: 0,
        suggestedMax: Math.max(10, (Math.max.apply(null, initData) || 0) * 1.2),
        ticks: {
          stepSize: Math.max(1, Math.round((Math.max.apply(null, initData.concat([10]))) / 8)),
          font:  { family:'Inter', size:11 },
          color: '#7a8ab0'
        },
        grid:   { color:'rgba(100,120,200,0.10)' },
        border: { display: false }
      }
    }
  }
});

checkEmpty(initData);

function checkEmpty(data) {
  var empty = data.every(function(v){ return v === 0; });
  document.getElementById('emptyState').classList.toggle('visible', empty);
  document.getElementById('insightsChart').style.opacity = empty ? '0.15' : '1';
}

function setLoading(on) {
  document.getElementById('chartLoading').classList.toggle('visible', on);
  document.querySelectorAll('.range-btn, .filter-sentiment')
    .forEach(function(b){ b.disabled = on; });
}

function fetchChart() {
  if (isFetching) return;
  isFetching = true;
  setLoading(true);

  var params = new URLSearchParams({
    ajax:      1,
    sentiment: currentSentiment,
    range:     currentRange,
  });

  fetch('dashb_admin.php?' + params)
    .then(function(r){ return r.json(); })
    .then(function(json) {
      if (json.error) throw new Error(json.error);
      var data   = json.data;
      var labels = json.labels;
      var maxVal = Math.max.apply(null, data.concat([10]));
      var c      = colors[currentSentiment];
      chart.data.labels                            = labels;
      chart.data.datasets[0].data                 = data;
      chart.data.datasets[0].label                = currentSentiment;
      chart.data.datasets[0].borderColor          = c.stroke;
      chart.data.datasets[0].backgroundColor      = c.fill;
      chart.data.datasets[0].pointBackgroundColor = c.stroke;
      chart.options.scales.y.suggestedMax         = Math.ceil(maxVal * 1.2);
      chart.options.scales.y.ticks.stepSize       = Math.max(1, Math.round(maxVal / 8));
      chart.update();
      checkEmpty(data);
    })
    .catch(function(err){ console.error('Dashboard error:', err); })
    .finally(function(){ isFetching = false; setLoading(false); });
}

function setRange(range, btn) {
  currentRange = range;
  document.querySelectorAll('.range-btn')
    .forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  fetchChart();
}

function changeSentiment(val) {
  currentSentiment = val;
  fetchChart();
}

// ── Logout modal ──────────────────────────────────────
function adminLogout() {
  var overlay = document.getElementById('logout-overlay');
  overlay.style.display = 'flex';
}
function closeLogoutModal() {
  document.getElementById('logout-overlay').style.display = 'none';
}
document.getElementById('logout-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeLogoutModal();
});

// ── Confirmed: toast then redirect ───────────────────
var loTimer = null;
function confirmLogout() {
  closeLogoutModal();
  var t = document.getElementById('lo-toast');
  t.style.transform     = 'translateY(0)';
  t.style.opacity       = '1';
  t.style.pointerEvents = 'all';
  loTimer = setTimeout(function() {
    window.location.href = 'admin_logout.php';
  }, 1800);
}
function loCloseToast() {
  clearTimeout(loTimer);
  var t = document.getElementById('lo-toast');
  t.style.transform     = 'translateY(-120px)';
  t.style.opacity       = '0';
  t.style.pointerEvents = 'none';
}
</script>

</body>
</html>