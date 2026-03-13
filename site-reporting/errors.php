<?php
require_once __DIR__ . '/api/auth.php';
require_auth();
require_permission('view-errors');
date_default_timezone_set('America/Los_Angeles');
$date_today = date('Y-m-d');
$date_seven_days_ago = date('Y-m-d', strtotime('-7 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Errors | The Absolute Essential</title>
  <link rel="stylesheet" href="assets/styles.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="page-dash">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main">
    <div class="page-header">
      <div>
        <h2>Error Report</h2>
        <p class="subtitle">JS errors, resource failures, and unhandled rejections</p>
      </div>
      <div class="date-filter">
        <label for="date-from">From</label>
        <input type="date" id="date-from" value="<?php echo $date_seven_days_ago; ?>" />
        <label for="date-to">To</label>
        <input type="date" id="date-to" value="<?php echo $date_today; ?>" />
        <button class="filter-btn" id="apply-dates">Apply</button>
        <button class="filter-btn" id="gen-report" style="margin-left:8px;">
          <img src="assets/icons/report.svg" alt="" width="16" height="16"/>
          Generate Report
        </button>
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
            <th style="width:28px;"></th>
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
    const charts = {};
    function kill(id) {
        if(charts[id]){
            charts[id].destroy();
            delete charts[id];
        }
    }
    function shortPath(u) {
        try{
            return new URL(u).pathname||'/';
        }
        catch(e){
            return u;
        }
    }
    function esc(s) {
        const d=document.createElement('div');
        d.textContent=s; return d.innerHTML;
    }
    function dateFilter(events, from, to) {
      if (!from && !to) return events;
      return events.filter(e => {
        const d = tsToDate(e.client_ts);
        if (from && d < from) return false;
        if (to && d > to) return false;
        return true;
      });
    }

    let allErrors = [];

    async function load() {
      const from = document.getElementById('date-from').value;
      const to = document.getElementById('date-to').value;
      const r = await fetch('api/events.php?type=error&limit=5000&from=' + from + '&to=' + to);
      allErrors = await r.json();
      allErrors.forEach(e => { if (typeof e.payload === 'string') try { e.payload = JSON.parse(e.payload); } catch(x){} });
      render();
    }

    function render() {
        const from = document.getElementById('date-from').value;
        const to = document.getElementById('date-to').value;
        const errors = allErrors;

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
        errors.forEach(e => { const d=tsToDate(e.client_ts); if(d) dc[d]=(dc[d]||0)+1; });
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
            const key = errType + '::' + (d.message || d.src || d.tag || 'unknown');
            if (!groups[key]) groups[key] = {
              type: errType,
              message: d.message || d.src || d.tag || '—',
              count: 0,
              sessions: new Set(),
              lastSeen: '',
              page: '',
              file: d.file || d.src || null,
              stack: d.stack || null,
            };
            groups[key].count++;
            if (e.session_id) groups[key].sessions.add(e.session_id);
            const ts = tsToDate(e.client_ts);
            if (ts > groups[key].lastSeen) {
              groups[key].lastSeen = ts;
              groups[key].page = e.page || '';
            }
            // Keep the most recent stack trace
            if (d.stack && (!groups[key].stack || ts >= groups[key].lastSeen)) {
              groups[key].stack = d.stack;
              groups[key].file = d.file || d.src || groups[key].file;
            }
        });

        const tbody = document.getElementById('error-tbody');
        tbody.innerHTML = '';
        const rows = Object.values(groups).sort((a,b) => b.count - a.count);

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No errors recorded yet. That\'s a good thing.</td></tr>';
        } else {
            rows.forEach(g => {
              const tr = document.createElement('tr');
              tr.className = 'event-row';
              tr.style.cursor = 'pointer';
              tr.innerHTML =
                '<td><span class="expand-icon">›</span></td>' +
                '<td><span class="tag-type tag-error">' + esc(g.type) + '</span></td>' +
                '<td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + esc(g.message) + '">' + esc(g.message) + '</td>' +
                '<td class="mono">' + g.count + '</td>' +
                '<td class="mono">' + g.sessions.size + '</td>' +
                '<td class="mono">' + esc(g.lastSeen) + '</td>' +
                '<td>' + esc(shortPath(g.page)) + '</td>';

              const detailRow = document.createElement('tr');
              detailRow.className = 'detail-row';
              detailRow.style.display = 'none';
              const detailTd = document.createElement('td');
              detailTd.colSpan = 7;
              detailTd.className = 'detail-cell';

              // Build detail content
              let detail = '<div class="detail-grid">';
              detail += '<div class="detail-kv"><span class="detail-label">Error Type</span><span class="detail-value">' + esc(g.type) + '</span></div>';
              detail += '<div class="detail-kv"><span class="detail-label">Full Message</span><span class="detail-value" style="word-break:break-all;">' + esc(g.message) + '</span></div>';
              detail += '<div class="detail-kv"><span class="detail-label">Occurrences</span><span class="detail-value">' + g.count + '</span></div>';
              detail += '<div class="detail-kv"><span class="detail-label">Affected Sessions</span><span class="detail-value">' + g.sessions.size + '</span></div>';
              detail += '<div class="detail-kv"><span class="detail-label">Last Seen</span><span class="detail-value">' + esc(g.lastSeen) + '</span></div>';
              detail += '<div class="detail-kv"><span class="detail-label">Page</span><span class="detail-value">' + esc(g.page) + '</span></div>';
              if (g.file) detail += '<div class="detail-kv"><span class="detail-label">File</span><span class="detail-value" style="word-break:break-all;">' + esc(g.file) + '</span></div>';
              if (g.stack) detail += '<div class="detail-kv"><span class="detail-label">Stack Trace</span><pre class="payload-pre" style="margin:4px 0;white-space:pre-wrap;word-break:break-all;">' + esc(g.stack) + '</pre></div>';
              detail += '</div>';
              detailTd.innerHTML = detail;
              detailRow.appendChild(detailTd);

              tr.addEventListener('click', () => {
                const open = detailRow.style.display !== 'none';
                detailRow.style.display = open ? 'none' : 'table-row';
                tr.classList.toggle('expanded', !open);
              });

              tbody.appendChild(tr);
              tbody.appendChild(detailRow);
            }); 
      }
    }

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
    document.getElementById('apply-dates').addEventListener('click', load);
    load();
  </script>
</body>
</html>