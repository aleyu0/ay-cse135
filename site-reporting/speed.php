<?php
require_once __DIR__ . '/api/auth.php';
require_auth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Speed &amp; Vitals — The Absolute Essential</title>
  <link rel="stylesheet" href="assets/styles.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="page-dash">
  <aside class="sidebar">
    <div class="sidebar-brand">The Absolute Essential</div>
    <nav class="sidebar-nav">
      <a href="dashboard.php">Overview</a>
      <a href="table.php">Event Log</a>
      <a href="speed.php" class="active">Speed &amp; Vitals</a>
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
        <h2>Speed &amp; Web Vitals</h2>
        <p class="subtitle">Performance metrics and Core Web Vitals from real user data</p>
      </div>
      <div class="date-filter">
        <label for="date-from">From</label>
        <input type="date" id="date-from" />
        <label for="date-to">To</label>
        <input type="date" id="date-to" />
        <button class="filter-btn" id="apply-dates">Apply</button>
      </div>
    </div>

    <!-- Web Vitals KPI cards -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <span class="kpi-label">LCP (median)</span>
        <span class="kpi-value" id="kpi-lcp">—</span>
        <span class="kpi-sub" id="kpi-lcp-score"></span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">CLS (median)</span>
        <span class="kpi-value" id="kpi-cls">—</span>
        <span class="kpi-sub" id="kpi-cls-score"></span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">INP (median)</span>
        <span class="kpi-value" id="kpi-inp">—</span>
        <span class="kpi-sub" id="kpi-inp-score"></span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">Avg Load</span>
        <span class="kpi-value" id="kpi-load">—</span>
      </div>
    </div>

    <!-- Charts -->
    <div class="chart-grid">
      <div class="chart-card">
        <h3>Load Time Distribution</h3>
        <canvas id="chart-load-dist"></canvas>
      </div>
      <div class="chart-card">
        <h3>Web Vitals Scores</h3>
        <canvas id="chart-vitals-scores"></canvas>
      </div>
    </div>

    <!-- Performance table for each page -->
    <h3 style="margin-top:28px;">Page Performance</h3>
    <p class="subtitle">Sorted by slowest load time</p>
    <div class="data-table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Page</th>
            <th>Views</th>
            <th>Avg Load</th>
            <th>Avg TTFB</th>
            <th>Avg DCL</th>
            <th>DNS</th>
            <th>TLS</th>
          </tr>
        </thead>
        <tbody id="perf-tbody"></tbody>
      </table>
    </div>
  </div>

  <script>
    const charts = {};
    function kill(id) { if(charts[id]){charts[id].destroy();delete charts[id];} }
    function shortPath(u) { try{return new URL(u).pathname||'/';}catch(e){return u;} }
    function median(arr) { if(!arr.length)return null; const s=[...arr].sort((a,b)=>a-b); const m=Math.floor(s.length/2); return s.length%2?s[m]:Math.round((s[m-1]+s[m])/2); }
    function p90(arr) { if(!arr.length)return null; const s=[...arr].sort((a,b)=>a-b); return s[Math.floor(s.length*0.9)]; }
    function vitalColor(name,val) {
      if(name==='lcp') return val<=2500?'#1a8a4a':val<=4000?'#c78c20':'#c0392b';
      if(name==='cls') return val<=0.1?'#1a8a4a':val<=0.25?'#c78c20':'#c0392b';
      if(name==='inp') return val<=200?'#1a8a4a':val<=500?'#c78c20':'#c0392b';
      return '#6b6b6b';
    }
    function scoreLabel(name,val) {
      if(name==='lcp') return val<=2500?'Good':val<=4000?'Needs Improvement':'Poor';
      if(name==='cls') return val<=0.1?'Good':val<=0.25?'Needs Improvement':'Poor';
      if(name==='inp') return val<=200?'Good':val<=500?'Needs Improvement':'Poor';
      return '';
    }
    function dateFilter(events, from, to) {
      if (!from && !to) return events;
      return events.filter(e => {
        const d = (e.client_ts || '').substring(0, 10);
        if (from && d < from) return false;
        if (to && d > to) return false;
        return true;
      });
    }
    function esc(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

    let allPerf = [], allVitals = [];

    async function load() {
      const [rp, rv] = await Promise.all([
        fetch('api/events.php?type=performance&limit=500'),
        fetch('api/events.php?type=vitals&limit=500')
      ]);
      allPerf = await rp.json();
      allVitals = await rv.json();
      [allPerf, allVitals].forEach(arr => arr.forEach(e => {
        if (typeof e.payload === 'string') try { e.payload = JSON.parse(e.payload); } catch(x){}
      }));
      render();
    }

    function render() {
      const from = document.getElementById('date-from').value;
      const to = document.getElementById('date-to').value;
      const perfs = dateFilter(allPerf, from, to);
      const vitals = dateFilter(allVitals, from, to);

      // Extract values
      const loadTimes = perfs.map(e => e.payload?.data?.totalLoadMs).filter(v => v!=null);
      const lcpVals = vitals.map(e => e.payload?.data?.lcp?.value).filter(v => v!=null);
      const clsVals = vitals.map(e => e.payload?.data?.cls?.value).filter(v => v!=null);
      const inpVals = vitals.map(e => e.payload?.data?.inp?.value).filter(v => v!=null);

      // KPIs
      const mLcp = median(lcpVals);
      const mCls = median(clsVals);
      const mInp = median(inpVals);
      const avgLoad = loadTimes.length ? Math.round(loadTimes.reduce((a,b)=>a+b,0)/loadTimes.length) : null;

      document.getElementById('kpi-lcp').textContent = mLcp != null ? mLcp + 'ms' : '—';
      document.getElementById('kpi-cls').textContent = mCls != null ? mCls.toFixed(3) : '—';
      document.getElementById('kpi-inp').textContent = mInp != null ? mInp + 'ms' : '—';
      document.getElementById('kpi-load').textContent = avgLoad != null ? avgLoad + 'ms' : '—';

      if (mLcp != null) {
        const el = document.getElementById('kpi-lcp-score');
        el.textContent = scoreLabel('lcp', mLcp);
        el.style.color = vitalColor('lcp', mLcp);
      }
      if (mCls != null) {
        const el = document.getElementById('kpi-cls-score');
        el.textContent = scoreLabel('cls', mCls);
        el.style.color = vitalColor('cls', mCls);
      }
      if (mInp != null) {
        const el = document.getElementById('kpi-inp-score');
        el.textContent = scoreLabel('inp', mInp);
        el.style.color = vitalColor('inp', mInp);
      }

      // Load time distribution histogram
      const buckets = { '0-500ms':0, '500ms-1s':0, '1-2s':0, '2-3s':0, '3-5s':0, '5s+':0 };
      loadTimes.forEach(v => {
        if(v<500) buckets['0-500ms']++;
        else if(v<1000) buckets['500ms-1s']++;
        else if(v<2000) buckets['1-2s']++;
        else if(v<3000) buckets['2-3s']++;
        else if(v<5000) buckets['3-5s']++;
        else buckets['5s+']++;
      });
      kill('load-dist');
      charts['load-dist'] = new Chart(document.getElementById('chart-load-dist'), {
        type:'bar',
        data:{ labels:Object.keys(buckets), datasets:[{ label:'Pages', data:Object.values(buckets),
          backgroundColor:['#1a8a4a','#1a8a4a','#c78c20','#c78c20','#c0392b','#c0392b'], borderRadius:3 }] },
        options:{ responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{color:'#e8e8e8'}},x:{grid:{display:false}}} }
      });

      // Vitals scores stacked bar
      function scoreCounts(vals, name) {
        let good=0, needs=0, poor=0;
        vals.forEach(v => {
          const s = scoreLabel(name, v);
          if(s==='Good') good++; else if(s==='Needs Improvement') needs++; else poor++;
        });
        return [good, needs, poor];
      }
      const lcpS = scoreCounts(lcpVals, 'lcp');
      const clsS = scoreCounts(clsVals, 'cls');
      const inpS = scoreCounts(inpVals, 'inp');

      kill('vitals-scores');
      charts['vitals-scores'] = new Chart(document.getElementById('chart-vitals-scores'), {
        type:'bar',
        data:{
          labels:['LCP','CLS','INP'],
          datasets:[
            { label:'Good', data:[lcpS[0],clsS[0],inpS[0]], backgroundColor:'#1a8a4a', borderRadius:3 },
            { label:'Needs Improvement', data:[lcpS[1],clsS[1],inpS[1]], backgroundColor:'#c78c20', borderRadius:3 },
            { label:'Poor', data:[lcpS[2],clsS[2],inpS[2]], backgroundColor:'#c0392b', borderRadius:3 },
          ]
        },
        options:{ responsive:true, plugins:{legend:{position:'bottom',labels:{font:{size:11}}}}, scales:{x:{stacked:true,grid:{display:false}},y:{stacked:true,beginAtZero:true,grid:{color:'#e8e8e8'}}} }
      });

      // Per-page performance table
      const byPage = {};
      perfs.forEach(e => {
        const p = shortPath(e.page || '');
        if (!byPage[p]) byPage[p] = { views:0, loads:[], ttfbs:[], dcls:[], dns:[], tls:[] };
        byPage[p].views++;
        const d = e.payload?.data;
        if (d?.totalLoadMs != null) byPage[p].loads.push(d.totalLoadMs);
        if (d?.timing?.ttfb != null) byPage[p].ttfbs.push(d.timing.ttfb);
        else if (d?.timing?.responseEnd != null) byPage[p].ttfbs.push(Math.round(d.timing.responseEnd));
        if (d?.timing?.domContentLoaded != null) byPage[p].dcls.push(Math.round(d.timing.domContentLoaded));
        if (d?.timing?.dns != null) byPage[p].dns.push(d.timing.dns);
        if (d?.timing?.tls != null) byPage[p].tls.push(d.timing.tls);
      });

      const tbody = document.getElementById('perf-tbody');
      tbody.innerHTML = '';
      const avg = arr => arr.length ? Math.round(arr.reduce((a,b)=>a+b,0)/arr.length) : null;
      const rows = Object.entries(byPage).sort((a,b) => (avg(b[1].loads)||0) - (avg(a[1].loads)||0));

      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No performance data yet.</td></tr>';
      } else {
        rows.forEach(([page, d]) => {
          const tr = document.createElement('tr');
          const ms = v => v != null ? v + 'ms' : '—';
          tr.innerHTML =
            '<td>' + esc(page) + '</td>' +
            '<td>' + d.views + '</td>' +
            '<td class="mono">' + ms(avg(d.loads)) + '</td>' +
            '<td class="mono">' + ms(avg(d.ttfbs)) + '</td>' +
            '<td class="mono">' + ms(avg(d.dcls)) + '</td>' +
            '<td class="mono">' + ms(avg(d.dns)) + '</td>' +
            '<td class="mono">' + ms(avg(d.tls)) + '</td>';
          tbody.appendChild(tr);
        });
      }
    }

    document.getElementById('apply-dates').addEventListener('click', render);
    load();
  </script>
</body>
</html>