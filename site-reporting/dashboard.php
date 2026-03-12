<?php
require_once __DIR__ . '/api/auth.php';
require_auth();
require_permission('view-dashboard');
date_default_timezone_set('America/Los_Angeles');
$date_today = date('Y-m-d');
$date_seven_days_ago = date('Y-m-d', strtotime('-7 days'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard | The Absolute Essential</title>
  <link rel="stylesheet" href="assets/styles.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="page-dash">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
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
        <h3>Client Connection Speed</h3>
        <canvas id="chart-connection"></canvas>
      </div>
    </div>
  </div>

  <script>
    function tsToDate(raw) {
      if (typeof raw === 'number') {
        const d = new Date(raw > 1e12 ? raw : raw * 1000);
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
      }
      if (raw) return String(raw).substring(0, 10);
      return '';
    }

    function fmtDateUS(d) {
      const [y, m, day] = d.split('-');
      return m + '/' + day + '/' + y;
    }

    const chartOpts = {
      responsive: true,
      maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: '#e8e8e8' } },
        x: { offset: true, grid: { display: false }, ticks: { maxRotation: 45, minRotation: 0, autoSkip: true, font: { size: 11 } } }
      }
    };
    const charts = {};
    function kill(id) { if (charts[id]) { charts[id].destroy(); delete charts[id]; } }

    function shortPath(u) {
      try {
        let p = new URL(u).pathname || '/';
        if (p.length > 20) p = '…' + p.slice(-18);
        return p;
      } catch(e) { return u; }
    }

    function parseBrowser(ua) {
      if (!ua) return 'Unknown';
      if (/CriOS/.test(ua)) return 'Chrome iOS';
      if (/Firefox\/([\d]+)/.test(ua)) return 'Firefox ' + ua.match(/Firefox\/([\d]+)/)[1];
      if (/Edg\/([\d]+)/.test(ua)) return 'Edge ' + ua.match(/Edg\/([\d]+)/)[1];
      if (/Safari/.test(ua) && !/Chrome/.test(ua)) {
        const v = ua.match(/Version\/([\d]+)/);
        return v ? 'Safari ' + v[1] : 'Safari';
      }
      const c = ua.match(/Chrome\/([\d]+)/);
      if (c) return 'Chrome ' + c[1];
      return 'Other';
    }

    const palette = ['#1a1a1a','#d35322','#2B4949','#212E50','#A40607','#6b6b6b','#999'];

    async function load() {
      const from = document.getElementById('date-from').value;
      const to = document.getElementById('date-to').value;
      const qs = `limit=5000&from=${from}&to=${to}`;

      const r = await fetch('api/events.php?' + qs);
      const all = await r.json();
      all.forEach(e => { if (typeof e.payload === 'string') try { e.payload = JSON.parse(e.payload); } catch(x){} });
      render(all);
    }

    function render(ev) {
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
      ev.forEach(e => { const d = tsToDate(e.client_ts); if(d) dc[d]=(dc[d]||0)+1; });
      const sorted = Object.entries(dc).sort((a,b) => a[0].localeCompare(b[0]));
      kill('timeline');
      charts['timeline'] = new Chart(document.getElementById('chart-timeline'), {
        type:'line', data:{ labels:sorted.map(s=>fmtDateUS(s[0])), datasets:[{ label:'Events', data:sorted.map(s=>s[1]),
          borderColor:'#1a1a1a', backgroundColor:'rgba(26,26,26,0.05)', fill:true, tension:0.3, pointRadius:3 }] },
        options: chartOpts
      });

      // top pages
      const pc = {};
      let notFoundCount = 0;
      statics.forEach(e => {
        const fullUrl = e.page || '';
        const p = shortPath(fullUrl);
        // Check if the page is a known page or a 404
        const knownPages = ['/', '/index.html', '/shop.html', '/contact.html', '/404.html'];
        if (p === '/404.html') {
          notFoundCount++;
        } else if (knownPages.includes(p) || p.startsWith('/api/')) {
          pc[p] = (pc[p] || 0) + 1;
        } else {
          // Unknown path — likely redirected to 404
          notFoundCount++;
        }
      });
      if (notFoundCount > 0) pc['404 (not found)'] = notFoundCount;
      const tp = Object.entries(pc).sort((a,b) => b[1] - a[1]).slice(0, 8);

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
      statics.forEach(e => {
        let c = e.payload?.data?.connectionType;
        if (!c || c === 'null') c = 'unknown';
        const labels = { '4g': '4g (fast)', '3g': '3g (moderate)', '2g': '2g (slow)', 'slow-2g': 'slow-2g' };
        cn[labels[c] || c] = (cn[labels[c] || c] || 0) + 1;
      });
      const ce = Object.entries(cn).sort((a,b)=>b[1]-a[1]);
      kill('connection');
      charts['connection'] = new Chart(document.getElementById('chart-connection'), {
        type:'doughnut', data:{ labels:ce.map(c=>c[0]), datasets:[{ data:ce.map(c=>c[1]), backgroundColor:palette.slice().reverse() }] },
        options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } } } }
      });
    }

    document.getElementById('apply-dates').addEventListener('click', load);
    load();

    // date carry over 
    function syncDates() {
      const from = document.getElementById('date-from');
      const to = document.getElementById('date-to');
      if (!from || !to) return;

      // Load saved range if it exists
      const saved = sessionStorage.getItem('dateRange');
      if (saved) {
        try {
          const r = JSON.parse(saved);
          if (r.from) from.value = r.from;
          if (r.to) to.value = r.to;
        } catch(e) {}
      }

      // save on change date
      function save() {
        sessionStorage.setItem('dateRange', JSON.stringify({ from: from.value, to: to.value }));
      }
      from.addEventListener('change', save);
      to.addEventListener('change', save);

      const applyBtn = document.getElementById('apply-dates');
      if (applyBtn) applyBtn.addEventListener('click', save);
    }
    syncDates();
  </script>
</body>
</html>