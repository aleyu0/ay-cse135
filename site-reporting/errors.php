<?php
require_once __DIR__ . '/api/auth.php';
require_auth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Errors — The Absolute Essential</title>
  <link rel="stylesheet" href="assets/styles.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="page-dash">
  <aside class="sidebar">
    <div class="sidebar-brand">The Absolute Essential</div>
    <nav class="sidebar-nav">
      <a href="dashboard.php">Overview</a>
      <a href="table.php">Event Log</a>
      <a href="speed.php">Speed &amp; Vitals</a>
      <a href="errors.php" class="active">Errors</a>
      <a href="admin.php">Users</a>
    </nav>
    <div class="sidebar-bottom">
      <a href="api/logout.php">Log out</a>
    </div>
  </aside>

  <div class="main">
    <div class="page-header">
      <div>
        <h2>Error Report</h2>
        <p class="subtitle">JS errors, resource failures, and unhandled rejections</p>
      </div>
      <div class="date-filter">
        <label for="date-from">From</label>
        <input type="date" id="date-from" />
        <label for="date-to">To</label>
        <input type="date" id="date-to" />
        <button class="filter-btn" id="apply-dates">Apply</button>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi-card">
        <span class="kpi-label">Total Errors</span>
        <span class="kpi-value" id="kpi-total">—</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">JS Runtime</span>
        <span class="kpi-value" id="kpi-runtime">—</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">Resource Failures</span>
        <span class="kpi-value" id="kpi-resource">—</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">Promise Rejections</span>
        <span class="kpi-value" id="kpi-promise">—</span>
      </div>
    </div>

    <div class="chart-grid">
      <div class="chart-card">
        <h3>Errors Over Time</h3>
        <canvas id="chart-error-timeline"></canvas>
      </div>
      <div class="chart-card">
        <h3>Error Types</h3>
        <canvas id="chart-error-types"></canvas>
      </div>
    </div>

    <h3 style="margin-top:28px;">Grouped Errors</h3>
    <p class="subtitle">Grouped by message, sorted by frequency. Highest-impact errors first.</p>
    <div class="data-table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Type</th>
            <th>Message / Source</th>
            <th>Count</th>
            <th>Sessions</th>
            <th>Last Seen</th>
            <th>Page</th>
          </tr>
        </thead>
        <tbody id="error-tbody"></tbody>
      </table>
    </div>
  </div>

  <script>
    const charts = {};
    function kill(id) { if(charts[id]){charts[id].destroy();delete charts[id];} }
    function shortPath(u) { try{return new URL(u).pathname||'/';}catch(e){return u;} }
    function esc(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
    function dateFilter(events, from, to) {
      if (!from && !to) return events;
      return events.filter(e => {
        const d = (e.client_ts || '').substring(0, 10);
        if (from && d < from) return false;
        if (to && d > to) return false;
        return true;
      });
    }

    let allErrors = [];

    async function load() {
      const r = await fetch('api/events.php?type=error&limit=500');
      allErrors = await r.json();
      allErrors.forEach(e => { if (typeof e.payload === 'string') try { e.payload = JSON.parse(e.payload); } catch(x){} });
      render();
    }

    function render() {
      const from = document.getElementById('date-from').value;
      const to = document.getElementById('date-to').value;
      const errors = dateFilter(allErrors, from, to);

      // KPIs
      const runtime = errors.filter(e => e.payload?.data?.errorType === 'js_runtime');
      const resource = errors.filter(e => e.payload?.data?.errorType === 'resource_load');
      const promise = errors.filter(e => e.payload?.data?.errorType === 'promise_rejection');

      document.getElementById('kpi-total').textContent = errors.length;
      document.getElementById('kpi-runtime').textContent = runtime.length;
      document.getElementById('kpi-resource').textContent = resource.length;
      document.getElementById('kpi-promise').textContent = promise.length;

      // Timeline
      const dc = {};
      errors.forEach(e => { const d=(e.client_ts||'').substring(0,10); if(d) dc[d]=(dc[d]||0)+1; });
      const sorted = Object.entries(dc).sort((a,b)=>a[0].localeCompare(b[0]));
      kill('error-timeline');
      charts['error-timeline'] = new Chart(document.getElementById('chart-error-timeline'), {
        type:'line', data:{ labels:sorted.map(s=>s[0]), datasets:[{
          label:'Errors', data:sorted.map(s=>s[1]), borderColor:'#c0392b', backgroundColor:'rgba(192,57,43,0.08)',
          fill:true, tension:0.3, pointRadius:3 }] },
        options:{ responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{color:'#e8e8e8'}},x:{grid:{display:false}}} }
      });

      // Error type doughnut
      const types = { 'js_runtime': runtime.length, 'resource_load': resource.length, 'promise_rejection': promise.length };
      const tEntries = Object.entries(types).filter(t => t[1] > 0);
      kill('error-types');
      if (tEntries.length) {
        charts['error-types'] = new Chart(document.getElementById('chart-error-types'), {
          type:'doughnut', data:{ labels:tEntries.map(t=>t[0]), datasets:[{
            data:tEntries.map(t=>t[1]), backgroundColor:['#c0392b','#c78c20','#212E50'] }] },
          options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{font:{size:11}} } } }
        });
      }

      // Grouped errors table
      const groups = {};
      errors.forEach(e => {
        const d = e.payload?.data || {};
        const errType = d.errorType || 'unknown';
        // Group key: type + message (or src for resource errors)
        const key = errType + '::' + (d.message || d.src || d.tag || 'unknown');
        if (!groups[key]) groups[key] = { type: errType, message: d.message || d.src || d.tag || '—', count: 0, sessions: new Set(), lastSeen: '', page: '' };
        groups[key].count++;
        if (e.session_id) groups[key].sessions.add(e.session_id);
        const ts = e.client_ts || '';
        if (ts > groups[key].lastSeen) { groups[key].lastSeen = ts; groups[key].page = e.page || ''; }
      });

      const tbody = document.getElementById('error-tbody');
      tbody.innerHTML = '';
      const rows = Object.values(groups).sort((a,b) => b.count - a.count);

      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No errors recorded yet. That\'s a good thing.</td></tr>';
      } else {
        rows.forEach(g => {
          const tr = document.createElement('tr');
          tr.innerHTML =
            '<td><span class="tag-type tag-error">' + esc(g.type) + '</span></td>' +
            '<td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + esc(g.message) + '">' + esc(g.message) + '</td>' +
            '<td class="mono">' + g.count + '</td>' +
            '<td class="mono">' + g.sessions.size + '</td>' +
            '<td class="mono">' + esc(g.lastSeen) + '</td>' +
            '<td>' + esc(shortPath(g.page)) + '</td>';
          tbody.appendChild(tr);
        });
      }
    }

    document.getElementById('apply-dates').addEventListener('click', render);
    load();
  </script>
</body>
</html>