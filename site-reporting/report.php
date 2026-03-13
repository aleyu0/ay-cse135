<?php
require_once __DIR__ . '/api/auth.php';
require_auth();
require_permission('export-reports');

$user = get_auth_user();
$source = $_GET['source'] ?? 'dashboard';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$validSources = [
  'dashboard' => 'Overview Report',
  'speed' => 'Speed & Web Vitals Report',
  'errors' => 'Error Report',
  'customers' => 'Customers & Behavior Report',
];

$title = $validSources[$source] ?? 'Report';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($title) ?> | The Absolute Essential</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: "Helvetica Neue", Arial, sans-serif;
      color: #1a1a1a;
      padding: 40px;
      max-width: 1000px;
      margin: 0 auto;
      font-size: 14px;
      line-height: 1.5;
    }
    .report-header {
      border-bottom: 2px solid #1a1a1a;
      padding-bottom: 16px;
      margin-bottom: 24px;
    }
    .report-header h1 {
      font-size: 24px;
      margin-bottom: 4px;
    }
    .report-meta {
      color: #666;
      font-size: 13px;
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
      margin-top: 6px;
    }
    .report-comment {
      margin-bottom: 24px;
    }
    .report-comment h2 {
      font-size: 16px;
      margin-bottom: 8px;
    }
    .report-comment textarea {
      width: 100%;
      min-height: 120px;
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-family: inherit;
      font-size: 13px;
      resize: vertical;
    }
    .report-section {
      margin-bottom: 28px;
    }
    .report-section h2 {
      font-size: 16px;
      margin-bottom: 12px;
      padding-bottom: 4px;
      border-bottom: 1px solid #ddd;
    }
    .report-charts {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 20px;
    }
    .report-chart-slot {
      border: 1px solid #eee;
      border-radius: 8px;
      padding: 12px;
      text-align: center;
    }
    .report-chart-slot h3 {
      font-size: 13px;
      color: #666;
      margin-bottom: 8px;
    }
    .report-chart-slot img {
      max-width: 100%;
      height: auto;
    }
    .report-kpi-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin-bottom: 20px;
    }
    .report-kpi {
      border: 1px solid #eee;
      border-radius: 8px;
      padding: 12px;
      text-align: center;
    }
    .report-kpi-label {
      font-size: 11px;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .report-kpi-value {
      font-size: 22px;
      font-weight: 700;
      margin-top: 2px;
    }
    .report-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      margin-top: 8px;
    }
    .report-table th,
    .report-table td {
      padding: 8px 10px;
      text-align: left;
      border-bottom: 1px solid #eee;
    }
    .report-table th {
      font-weight: 600;
      color: #666;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .toolbar {
      display: flex;
      gap: 10px;
      margin-bottom: 24px;
      align-items: center;
    }
    .toolbar button {
      padding: 8px 16px;
      border-radius: 6px;
      border: none;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-print {
      background: #1a1a1a;
      color: #fff;
    }
    .btn-back {
      background: #eee;
      color: #1a1a1a;
    }
    .report-footer {
      margin-top: 40px;
      padding-top: 16px;
      border-top: 1px solid #ddd;
      font-size: 11px;
      color: #999;
      display: flex;
      justify-content: space-between;
    }

    @media print {
      .toolbar { display: none !important; }
      .report-comment textarea {
        border: none;
        padding: 0;
        white-space: pre-wrap;
      }
      body { padding: 20px; }
      .report-charts { break-inside: avoid; }
      .report-section { break-inside: avoid; }
    }

    .btn-print, .btn-back {
      display: inline-flex;
      align-items: center;
    }
  </style>
</head>
<body>
    <div class="toolbar">
    <button class="btn-print" onclick="window.print()">
        <img src="assets/icons/print.svg" alt="" width="16" height="16" style="filter:brightness(0) invert(1); vertical-align:-3px; margin-right:4px;" />
        Print / Save as PDF
    </button>
    <button class="btn-back" onclick="window.close(); if(!window.closed) location.href='<?= htmlspecialchars($source) ?>.php';">
        <img src="assets/icons/dashboard.svg" alt="" width="16" height="16" style="filter:brightness(0); opacity:0.7; vertical-align:-3px; margin-right:4px;" />
        Back to Dashboard
    </button>
    <span style="color:#666; font-size:13px;">Tip: Use "Save as PDF" in the print dialog to export.</span>
    </div>

  <div class="report-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <div class="report-meta">
      <span>Date Range: <?= htmlspecialchars($from) ?> — <?= htmlspecialchars($to) ?></span>
      <span>Generated by: <?= htmlspecialchars($user['username'] ?? 'Unknown') ?></span>
      <span>Generated at: <?= date('m/d/Y h:i A') ?></span>
    </div>
  </div>

  <div class="report-comment">
    <h2>Analyst Commentary</h2>
    <textarea id="report-comment" placeholder="Write your analysis and interpretation of the data below..."><?php
      $commentFile = __DIR__ . '/data/comments_' . $source . '.txt';
      if (file_exists($commentFile)) echo htmlspecialchars(file_get_contents($commentFile));
    ?></textarea>
  </div>

  <div id="report-content">
    <p style="color:#666;">Loading report data...</p>
  </div>

  <div class="report-footer">
    <span>The Absolute Essential — Analytics Report</span>
    <span>Confidential</span>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
  <script>
    Chart.register(ChartDataLabels);
    Chart.defaults.set('plugins.datalabels', {
        color: '#444',
        font: { size: 11, weight: '600' },
        anchor: 'end',
        align: 'end',
        offset: 2,
        display: function(ctx) {
            return ctx.dataset.data[ctx.dataIndex] > 0;
        }
    });
    const SOURCE = '<?= htmlspecialchars($source) ?>';
    const FROM = '<?= htmlspecialchars($from) ?>';
    const TO = '<?= htmlspecialchars($to) ?>';
    const container = document.getElementById('report-content');

    function esc(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
    function shortPath(u) { try { return new URL(u).pathname||'/'; } catch { return u; } }
    function parseBrowser(ua) {
      if (!ua) return 'Unknown';
      if (/Firefox/.test(ua)) return 'Firefox';
      if (/Edg/.test(ua)) return 'Edge';
      if (/Safari/.test(ua) && !/Chrome/.test(ua)) return 'Safari';
      if (/Chrome/.test(ua)) return 'Chrome';
      return 'Other';
    }
    function parseOS(ua) {
      if (!ua) return 'Unknown';
      if (/iPhone|iPad/.test(ua)) return 'iOS';
      if (/Android/.test(ua)) return 'Android';
      if (/Mac/.test(ua)) return 'macOS';
      if (/Windows/.test(ua)) return 'Windows';
      if (/Linux/.test(ua)) return 'Linux';
      return 'Other';
    }
    function median(arr) {
      if(!arr.length) return null;
      const s=[...arr].sort((a,b)=>a-b);
      const m=Math.floor(s.length/2);
      return s.length%2?s[m]:Math.round((s[m-1]+s[m])/2);
    }
    function scoreLabel(name,val) {
      if(name==='lcp') return val<=2500?'Good':val<=4000?'Needs Improvement':'Poor';
      if(name==='cls') return val<=0.1?'Good':val<=0.25?'Needs Improvement':'Poor';
      if(name==='inp') return val<=200?'Good':val<=500?'Needs Improvement':'Poor';
      return '';
    }
    function tsToDate(raw) {
      if (typeof raw === 'number') {
        const d = new Date(raw > 1e12 ? raw : raw * 1000);
        return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
      }
      return raw ? String(raw).substring(0,10) : '';
    }
    function fmtDateUS(d) { const [y,m,day]=d.split('-'); return m+'/'+day+'/'+y; }

    // Create a chart, render it, convert to image, destroy chart
    function chartToImg(canvasId, config) {
      return new Promise(resolve => {
        const canvas = document.getElementById(canvasId);
        const chart = new Chart(canvas, config);
        setTimeout(() => {
          const img = document.createElement('img');
          img.src = canvas.toDataURL('image/png');
          img.style.maxWidth = '100%';
          chart.destroy();
          resolve(img);
        }, 500);
      });
    }

    async function buildReport() {
      const qs = `limit=5000&from=${FROM}&to=${TO}`;

      if (SOURCE === 'dashboard') {
        const r = await fetch('api/events.php?' + qs);
        const ev = await r.json();
        ev.forEach(e => { if (typeof e.payload === 'string') try { e.payload = JSON.parse(e.payload); } catch(x){} });

        const statics = ev.filter(e => e.event_type === 'static');
        const perfs = ev.filter(e => e.event_type === 'performance');
        const errors = ev.filter(e => e.event_type === 'error');
        const sessions = new Set(ev.map(e => e.session_id).filter(Boolean));
        const loads = perfs.map(e => e.payload?.data?.totalLoadMs).filter(v => v!=null);
        const avgLoad = loads.length ? Math.round(loads.reduce((a,b)=>a+b,0)/loads.length)+'ms' : '—';

        let html = '<div class="report-kpi-grid">';
        html += `<div class="report-kpi"><div class="report-kpi-label">Sessions</div><div class="report-kpi-value">${sessions.size}</div></div>`;
        html += `<div class="report-kpi"><div class="report-kpi-label">Page Views</div><div class="report-kpi-value">${statics.length}</div></div>`;
        html += `<div class="report-kpi"><div class="report-kpi-label">Avg Load</div><div class="report-kpi-value">${avgLoad}</div></div>`;
        html += `<div class="report-kpi"><div class="report-kpi-label">Errors</div><div class="report-kpi-value">${errors.length}</div></div>`;
        html += '</div>';
        html += '<div class="report-charts"><div class="report-chart-slot"><h3>Events Over Time</h3><canvas id="rc-timeline"></canvas></div>';
        html += '<div class="report-chart-slot"><h3>Top Pages</h3><canvas id="rc-pages"></canvas></div></div>';
        html += '<div class="report-charts"><div class="report-chart-slot"><h3>Browser Breakdown</h3><canvas id="rc-browsers"></canvas></div>';
        html += '<div class="report-chart-slot"><h3>Connection Speed</h3><canvas id="rc-connection"></canvas></div></div>';
        container.innerHTML = html;

        // Timeline
        const dc = {};
        ev.forEach(e => { const d = tsToDate(e.client_ts); if(d) dc[d]=(dc[d]||0)+1; });
        const sorted = Object.entries(dc).sort((a,b)=>a[0].localeCompare(b[0]));
        const palette = ['#1a1a1a','#d35322','#2B4949','#212E50','#A40607','#6b6b6b','#999'];

        new Chart(document.getElementById('rc-timeline'), {
          type:'line', data:{ labels:sorted.map(s=>fmtDateUS(s[0])), datasets:[{ data:sorted.map(s=>s[1]),
            borderColor:'#1a1a1a', backgroundColor:'rgba(26,26,26,0.05)', fill:true, tension:0.3, pointRadius:3 }] },
          options:{ responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true},x:{grid:{display:false}}} }
        });

        // Pages
        const knownPages = ['/index.html','/shop.html','/contact.html','/','/404.html'];
        const pc = {};
        statics.forEach(e => {
          let p; try { p = new URL(e.page||'').pathname||'/'; } catch { p = e.page||''; }
          if (p==='/404.html'||!knownPages.includes(p)) { pc['404']=(pc['404']||0)+1; } else { if(p==='/')p='/index.html'; pc[p]=(pc[p]||0)+1; }
        });
        const tp = Object.entries(pc).sort((a,b)=>b[1]-a[1]).slice(0,8);
        new Chart(document.getElementById('rc-pages'), {
          type:'bar', data:{ labels:tp.map(p=>p[0]), datasets:[{ data:tp.map(p=>p[1]), backgroundColor:'#d35322', borderRadius:3 }] },
          options:{ responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true},x:{grid:{display:false}}} }
        });

        // Browsers
        const br = {};
        statics.forEach(e => { const b=parseBrowser(e.payload?.data?.userAgent); br[b]=(br[b]||0)+1; });
        const be = Object.entries(br).sort((a,b)=>b[1]-a[1]);
        new Chart(document.getElementById('rc-browsers'), {
          type:'doughnut', data:{ labels:be.map(b=>b[0]), datasets:[{ data:be.map(b=>b[1]), backgroundColor:palette }] },
              options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 10 } } },
                    datalabels: {
                        color: '#fff',
                        font: { size: 11, weight: '700' },
                        anchor: 'center',
                        align: 'center',
                        formatter: function(value, ctx) {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((value / total) * 100).toFixed(0) : 0;
                            return pct > 5 ? pct + '%' : '';
                        }
                    }
                }
            }
        });

        // Connection
        const cn = {};
        statics.forEach(e => { let c=e.payload?.data?.connectionType; if(!c||c==='null')c='unknown'; cn[c]=(cn[c]||0)+1; });
        const ce = Object.entries(cn).sort((a,b)=>b[1]-a[1]);
        new Chart(document.getElementById('rc-connection'), {
          type:'doughnut', data:{ labels:ce.map(c=>c[0]), datasets:[{ data:ce.map(c=>c[1]), backgroundColor:palette.slice().reverse() }] },
          options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 10 } } },
                    datalabels: {
                        color: '#fff',
                        font: { size: 11, weight: '700' },
                        anchor: 'center',
                        align: 'center',
                        formatter: function(value, ctx) {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((value / total) * 100).toFixed(0) : 0;
                            return pct > 5 ? pct + '%' : '';
                        }
                    }
                }
            }
        });
      }

      if (SOURCE === 'speed') {
        const [rp, rv] = await Promise.all([
          fetch('api/events.php?type=performance&'+qs),
          fetch('api/events.php?type=vitals&'+qs)
        ]);
        const perfs = await rp.json(); const vitals = await rv.json();
        [perfs,vitals].forEach(arr=>arr.forEach(e=>{ if(typeof e.payload==='string') try{e.payload=JSON.parse(e.payload);}catch(x){} }));

        const loadTimes = perfs.map(e=>e.payload?.data?.totalLoadMs).filter(v=>v!=null);
        const lcpVals = vitals.map(e=>e.payload?.data?.lcp?.value).filter(v=>v!=null);
        const clsVals = vitals.map(e=>e.payload?.data?.cls?.value).filter(v=>v!=null);
        const inpVals = vitals.map(e=>e.payload?.data?.inp?.value).filter(v=>v!=null);
        const mLcp = median(lcpVals), mCls = median(clsVals), mInp = median(inpVals);
        const avgLoad = loadTimes.length ? Math.round(loadTimes.reduce((a,b)=>a+b,0)/loadTimes.length)+'ms' : '—';

        let html = '<div class="report-kpi-grid">';
        html += `<div class="report-kpi"><div class="report-kpi-label">LCP (median)</div><div class="report-kpi-value">${mLcp!=null?mLcp+'ms':'—'}</div></div>`;
        html += `<div class="report-kpi"><div class="report-kpi-label">CLS (median)</div><div class="report-kpi-value">${mCls!=null?mCls.toFixed(3):'—'}</div></div>`;
        html += `<div class="report-kpi"><div class="report-kpi-label">INP (median)</div><div class="report-kpi-value">${mInp!=null?mInp+'ms':'—'}</div></div>`;
        html += `<div class="report-kpi"><div class="report-kpi-label">Avg Load</div><div class="report-kpi-value">${avgLoad}</div></div>`;
        html += '</div>';
        html += '<div class="report-charts"><div class="report-chart-slot"><h3>Load Time Distribution</h3><canvas id="rc-load"></canvas></div>';
        html += '<div class="report-chart-slot"><h3>Web Vitals Scores</h3><canvas id="rc-vitals"></canvas></div></div>';
        container.innerHTML = html;

        const buckets = {'0-500ms':0,'500ms-1s':0,'1-2s':0,'2-3s':0,'3-5s':0,'5s+':0};
        loadTimes.forEach(v => { if(v<500)buckets['0-500ms']++;else if(v<1000)buckets['500ms-1s']++;else if(v<2000)buckets['1-2s']++;else if(v<3000)buckets['2-3s']++;else if(v<5000)buckets['3-5s']++;else buckets['5s+']++; });
        new Chart(document.getElementById('rc-load'), {
          type:'bar', data:{ labels:Object.keys(buckets), datasets:[{ data:Object.values(buckets),
            backgroundColor:['#1a8a4a','#1a8a4a','#c78c20','#c78c20','#c0392b','#c0392b'], borderRadius:3 }] },
          options:{ responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true},x:{grid:{display:false}}} }
        });

        function scoreCounts(vals,name) { let g=0,n=0,p=0; vals.forEach(v=>{const s=scoreLabel(name,v);if(s==='Good')g++;else if(s==='Needs Improvement')n++;else p++;}); return[g,n,p]; }
        const lcpS=scoreCounts(lcpVals,'lcp'),clsS=scoreCounts(clsVals,'cls'),inpS=scoreCounts(inpVals,'inp');
        new Chart(document.getElementById('rc-vitals'), {
          type:'bar', data:{ labels:['LCP','CLS','INP'], datasets:[
            {label:'Good',data:[lcpS[0],clsS[0],inpS[0]],backgroundColor:'#1a8a4a',borderRadius:3},
            {label:'Needs Improvement',data:[lcpS[1],clsS[1],inpS[1]],backgroundColor:'#c78c20',borderRadius:3},
            {label:'Poor',data:[lcpS[2],clsS[2],inpS[2]],backgroundColor:'#c0392b',borderRadius:3}
          ]}, options:{ responsive:true, plugins:{legend:{position:'bottom'}}, scales:{x:{stacked:true,grid:{display:false}},y:{stacked:true,beginAtZero:true}} }
        });
      }

      if (SOURCE === 'errors') {
        const r = await fetch('api/events.php?type=error&'+qs);
        const errors = await r.json();
        errors.forEach(e=>{ if(typeof e.payload==='string') try{e.payload=JSON.parse(e.payload);}catch(x){} });

        const runtime = errors.filter(e=>e.payload?.data?.errorType==='js_runtime');
        const resource = errors.filter(e=>e.payload?.data?.errorType==='resource_load');
        const promise = errors.filter(e=>e.payload?.data?.errorType==='promise_rejection');

        let html = '<div class="report-kpi-grid">';
        html += `<div class="report-kpi"><div class="report-kpi-label">Total Errors</div><div class="report-kpi-value">${errors.length}</div></div>`;
        html += `<div class="report-kpi"><div class="report-kpi-label">JS Runtime</div><div class="report-kpi-value">${runtime.length}</div></div>`;
        html += `<div class="report-kpi"><div class="report-kpi-label">Resource Failures</div><div class="report-kpi-value">${resource.length}</div></div>`;
        html += `<div class="report-kpi"><div class="report-kpi-label">Promise Rejections</div><div class="report-kpi-value">${promise.length}</div></div>`;
        html += '</div>';

        // Grouped errors table
        const groups = {};
        errors.forEach(e => {
          const d = e.payload?.data||{};
          const key = (d.errorType||'unknown')+'::'+(d.message||d.src||'unknown');
          if(!groups[key]) groups[key]={type:d.errorType||'unknown',message:d.message||d.src||'—',count:0,sessions:new Set()};
          groups[key].count++; if(e.session_id) groups[key].sessions.add(e.session_id);
        });
        const rows = Object.values(groups).sort((a,b)=>b.count-a.count);

        html += '<div class="report-section"><h2>Grouped Errors</h2>';
        html += '<table class="report-table"><thead><tr><th>Type</th><th>Message</th><th>Count</th><th>Sessions</th></tr></thead><tbody>';
        rows.forEach(g => {
          html += '<tr><td>'+esc(g.type)+'</td><td style="max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'+esc(g.message)+'</td><td>'+g.count+'</td><td>'+g.sessions.size+'</td></tr>';
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
      }

      if (SOURCE === 'customers') {
        const [evRes, ordRes] = await Promise.all([
          fetch('api/events.php?'+qs),
          fetch('api/orders.php?from='+FROM+'&to='+TO)
        ]);
        const events = await evRes.json();
        events.forEach(e=>{ if(typeof e.payload==='string') try{e.payload=JSON.parse(e.payload);}catch(x){} });
        const orders = await ordRes.json();

        const activities = events.filter(e=>e.event_type==='activity');
        const statics = events.filter(e=>e.event_type==='static');
        let cartAdds=0;
        const cartSessions = new Set();
        activities.forEach(e=>{
          const evts=e.payload?.data?.events||[];
          evts.forEach(ev=>{ if(ev.kind==='add_to_cart'){cartAdds++;cartSessions.add(e.session_id);} });
        });
        const orderSessions = new Set(orders.map(o=>o.session_id).filter(Boolean));
        const revenue = orders.reduce((s,o)=>s+parseFloat(o.total||0),0);
        const conversion = cartSessions.size>0?((orderSessions.size/cartSessions.size)*100).toFixed(1):'0';

        let html = '<div class="report-kpi-grid">';
        html += `<div class="report-kpi"><div class="report-kpi-label">Orders</div><div class="report-kpi-value">${orders.length}</div></div>`;
        html += `<div class="report-kpi"><div class="report-kpi-label">Revenue</div><div class="report-kpi-value">$${revenue.toFixed(2)}</div></div>`;
        html += `<div class="report-kpi"><div class="report-kpi-label">Cart Adds</div><div class="report-kpi-value">${cartAdds}</div></div>`;
        html += `<div class="report-kpi"><div class="report-kpi-label">Conversion</div><div class="report-kpi-value">${conversion}%</div></div>`;
        html += '</div>';

        html += '<div class="report-charts"><div class="report-chart-slot"><h3>Purchase Funnel</h3><canvas id="rc-funnel"></canvas></div>';
        html += '<div class="report-chart-slot"><h3>Top Products</h3><canvas id="rc-products"></canvas></div></div>';

        // Orders table
        html += '<div class="report-section"><h2>Orders</h2>';
        html += '<table class="report-table"><thead><tr><th>#</th><th>Customer</th><th>PID</th><th>Items</th><th>Total</th><th>Date</th></tr></thead><tbody>';
        orders.forEach(o => {
          let items = o.items; if(typeof items==='string') try{items=JSON.parse(items);}catch{items=[];}
          const itemStr = Array.isArray(items)?items.map(i=>(i.name||i.id)+'×'+(i.qty||1)).join(', '):'—';
          html += '<tr><td>'+o.id+'</td><td>'+esc(o.customer_name||'—')+'</td><td>'+esc(o.pid||'—')+'</td><td>'+esc(itemStr)+'</td><td>$'+parseFloat(o.total||0).toFixed(2)+'</td><td>'+esc((o.created_at||'').substring(0,10))+'</td></tr>';
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;

        // Funnel
        const allSessions = new Set(events.map(e=>e.session_id).filter(Boolean));
        new Chart(document.getElementById('rc-funnel'), {
          type:'bar', data:{ labels:['Visitors','Cart','Purchase'], datasets:[{
            data:[allSessions.size,cartSessions.size,orders.length], backgroundColor:['#2B4949','#d35322','#1a8a4a'], borderRadius:3
          }]}, options:{ indexAxis:'y', responsive:true, plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true},y:{grid:{display:false}}} }
        });

        // Products
        const pc = {};
        orders.forEach(o=>{ let items=o.items; if(typeof items==='string') try{items=JSON.parse(items);}catch{items=[];}
          if(Array.isArray(items)) items.forEach(i=>{ pc[i.name||i.id]=(pc[i.name||i.id]||0)+(i.qty||1); });
        });
        const tp = Object.entries(pc).sort((a,b)=>b[1]-a[1]).slice(0,8);
        new Chart(document.getElementById('rc-products'), {
          type:'bar', data:{ labels:tp.map(p=>p[0]), datasets:[{ data:tp.map(p=>p[1]), backgroundColor:'#d35322', borderRadius:3 }] },
          options:{ responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true},x:{grid:{display:false}}} }
        });
      }

      // Save comment on change
      document.getElementById('report-comment')?.addEventListener('input', () => {
        clearTimeout(window._commentTimer);
        window._commentTimer = setTimeout(async () => {
          const text = document.getElementById('report-comment').value;
          await fetch('api/comments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ page: SOURCE, comment: text })
          });
        }, 1000);
      });
    }

    buildReport();
  </script>
</body>
</html>