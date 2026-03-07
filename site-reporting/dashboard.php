<?php
require_once __DIR__ . '/api/auth.php';
require_auth();

date_default_timezone_set('America/Los_Angeles');
$date_today = date('Y-m-d');
$date_seven_days_ago = date('Y-m-d', strtotime('-7 days'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard — The Absolute Essential</title>
  <link rel="stylesheet" href="assets/styles.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="page-dash">
  <aside class="sidebar">
    <div class="sidebar-brand">The Absolute Essential</div>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="active">Overview</a>
      <a href="table.php">Event Log</a>
      <a href="speed.php">Speed &amp; Vitals</a>
      <a href="errors.php">Errors</a>
      <a href="admin.php">Users</a>
    </nav>
    <div class="sidebar-bottom">
      <a href="api/logout.php">Log out</a>
    </div>
  </aside>

  <div class="main">
    <div class="page-header">
      <div>
        <h2>Overview</h2>
        <p class="subtitle">Analytics summary from collected visitor data</p>
      </div>
      <div class="date-filter">
        <label for="date-from">From</label>
        <input type="date" id="date-from" value="<?php echo $date_seven_days_ago; ?>" />
        <label for="date-to">To</label>
        <input type="date" id="date-to" value="<?php echo $date_today; ?>" />
        <button class="filter-btn" id="apply-dates">Apply</button>
      </div>
    </div>

    <div class="kpi-grid" id="kpi-grid">
      <div class="kpi-card">
        <span class="kpi-label">Sessions</span>
        <span class="kpi-value" id="kpi-sessions">—</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">Page Views</span>
        <span class="kpi-value" id="kpi-pageviews">—</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">Avg Load Time</span>
        <span class="kpi-value" id="kpi-loadtime">—</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">Errors</span>
        <span class="kpi-value" id="kpi-errors">—</span>
      </div>
    </div>

    <div class="chart-grid">
      <div class="chart-card">
        <h3>Events Over Time</h3>
        <canvas id="chart-timeline"></canvas>
      </div>
      <div class="chart-card">
        <h3>Top Pages</h3>
        <canvas id="chart-pages"></canvas>
      </div>
      <div class="chart-card">
        <h3>Browser Breakdown</h3>
        <canvas id="chart-browsers"></canvas>
      </div>
      <div class="chart-card">
        <h3>Connection Type</h3>
        <canvas id="chart-connection"></canvas>
      </div>
    </div>
  </div>

  <script>
    const chartOpts = {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: '#e8e8e8' } },
        x: { grid: { display: false } }
      }
    };
    const charts = {};
    function kill(id) { if (charts[id]) { charts[id].destroy(); delete charts[id]; } }
    function shortPath(u) { try { return new URL(u).pathname || '/'; } catch(e) { return u; } }
    function parseBrowser(ua) {
      if (!ua) return 'Unknown';
      const c = ua.match(/Chrome\/([\d]+)/); if (c) return 'Chrome ' + c[1];
      const f = ua.match(/Firefox\/([\d]+)/); if (f) return 'Firefox ' + f[1];
      const s = ua.match(/Version\/([\d]+).*Safari/); if (s) return 'Safari ' + s[1];
      return 'Other';
    }
    function dateFilter(events, from, to) {
      if (!from && !to) return events;
      return events.filter(e => {
        const raw = e.client_ts;
        const d = raw ? String(raw).substring(0, 10) : '';
        if (from && d < from) return false;
        if (to && d > to) return false;
        return true;
      });
    }

    let all = [];
    const palette = ['#1a1a1a','#d35322','#2B4949','#212E50','#A40607','#6b6b6b','#999'];

    async function load() {
      const r = await fetch('api/events.php?limit=500');
      all = await r.json();
      all.forEach(e => { if (typeof e.payload === 'string') try { e.payload = JSON.parse(e.payload); } catch(x){} });
      render();
    }

    function render() {
      const from = document.getElementById('date-from').value;
      const to = document.getElementById('date-to').value;
      const ev = dateFilter(all, from, to);

      const sessions = new Set(ev.map(e => e.session_id).filter(Boolean));
      const statics = ev.filter(e => e.event_type === 'static');
      const perfs = ev.filter(e => e.event_type === 'performance');
      const errors = ev.filter(e => e.event_type === 'error');

      document.getElementById('kpi-sessions').textContent = sessions.size;
      document.getElementById('kpi-pageviews').textContent = statics.length;
      document.getElementById('kpi-errors').textContent = errors.length;

      let avgLoad = '—';
      const loads = perfs.map(e => e.payload?.data?.totalLoadMs).filter(v => v != null);
      if (loads.length) avgLoad = Math.round(loads.reduce((a,b) => a+b, 0) / loads.length) + 'ms';
      document.getElementById('kpi-loadtime').textContent = avgLoad;

      // timeline
      const dc = {};
      ev.forEach(e => { const d = (e.client_ts||'').substring(0,10); if(d) dc[d]=(dc[d]||0)+1; });
      const sorted = Object.entries(dc).sort((a,b) => a[0].localeCompare(b[0]));
      kill('timeline');
      charts['timeline'] = new Chart(document.getElementById('chart-timeline'), {
        type:'line', data:{ labels:sorted.map(s=>s[0]), datasets:[{ label:'Events', data:sorted.map(s=>s[1]),
          borderColor:'#1a1a1a', backgroundColor:'rgba(26,26,26,0.05)', fill:true, tension:0.3, pointRadius:3 }] },
        options: chartOpts
      });

      // top pages
      const pc = {};
      statics.forEach(e => { const p=shortPath(e.page||''); pc[p]=(pc[p]||0)+1; });
      const tp = Object.entries(pc).sort((a,b)=>b[1]-a[1]).slice(0,8);
      kill('pages');
      charts['pages'] = new Chart(document.getElementById('chart-pages'), {
        type:'bar', data:{ labels:tp.map(p=>p[0]), datasets:[{ label:'Views', data:tp.map(p=>p[1]),
          backgroundColor:'#d35322', borderRadius:3 }] },
        options: chartOpts
      });

      // browsers
      const br = {};
      statics.forEach(e => { const b=parseBrowser(e.payload?.data?.userAgent); br[b]=(br[b]||0)+1; });
      const be = Object.entries(br).sort((a,b)=>b[1]-a[1]);
      kill('browsers');
      charts['browsers'] = new Chart(document.getElementById('chart-browsers'), {
        type:'doughnut', data:{ labels:be.map(b=>b[0]), datasets:[{ data:be.map(b=>b[1]), backgroundColor:palette }] },
        options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } } } }
      });

      // connection
      const cn = {};
      statics.forEach(e => { const c=e.payload?.data?.connectionType||'unknown'; cn[c]=(cn[c]||0)+1; });
      const ce = Object.entries(cn).sort((a,b)=>b[1]-a[1]);
      kill('connection');
      charts['connection'] = new Chart(document.getElementById('chart-connection'), {
        type:'doughnut', data:{ labels:ce.map(c=>c[0]), datasets:[{ data:ce.map(c=>c[1]), backgroundColor:palette.slice().reverse() }] },
        options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } } } }
      });
    }

    document.getElementById('apply-dates').addEventListener('click', render);
    load();
  </script>
</body>
</html>