<?php
require_once __DIR__ . '/api/auth.php';
require_auth();
require_permission('view-behavioral');
date_default_timezone_set('America/Los_Angeles');
$date_today = date('Y-m-d');
$date_thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customers | The Absolute Essential</title>
  <link rel="stylesheet" href="assets/styles.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="page-dash">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="main">
    <div class="page-header">
      <div>
        <h2>Customers &amp; Behavior</h2>
        <p class="subtitle">Purchase activity, cart abandonment, and visitor profiles</p>
      </div>
      <div class="date-filter">
        <label for="date-from">From</label>
        <input type="date" id="date-from" value="<?= $date_thirty_days_ago ?>" />
        <label for="date-to">To</label>
        <input type="date" id="date-to" value="<?= $date_today ?>" />
        <button class="filter-btn" id="apply-dates">Apply</button>
        <button class="filter-btn" id="gen-report" style="margin-left:8px;">
          <img src="assets/icons/report.svg" alt="" width="16" height="16"/>
          Generate Report
        </button>
      </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
      <div class="kpi-card">
        <span class="kpi-label">Completed Orders</span>
        <span class="kpi-value" id="kpi-orders">—</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">Revenue</span>
        <span class="kpi-value" id="kpi-revenue">—</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">Cart Adds</span>
        <span class="kpi-value" id="kpi-cart-adds">—</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">Conversion Rate</span>
        <span class="kpi-value" id="kpi-conversion">—</span>
      </div>
    </div>

    <!-- Charts -->
    <div class="chart-grid">
      <div class="chart-card">
        <h3>Purchase Funnel</h3>
        <div id="chart-funnel"></div>
      </div>
      <div class="chart-card">
        <h3>Orders Over Time</h3>
        <canvas id="chart-orders-time"></canvas>
      </div>
      <div class="chart-card">
        <h3>Top Products</h3>
        <canvas id="chart-products"></canvas>
      </div>
      <div class="chart-card">
        <h3>Device Breakdown</h3>
        <canvas id="chart-devices"></canvas>
      </div>
    </div>

    <!-- Orders table -->
    <h3 style="margin-top:28px;">Recent Orders</h3>
    <div class="data-table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width:28px;"></th>
            <th>Order #</th>
            <th>Customer</th>
            <th>PID</th>
            <th>Items</th>
            <th>Total</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody id="orders-tbody"></tbody>
      </table>
    </div>

    <!-- Contact Submissions -->
    <h3 style="margin-top:28px;">Procurement Requests</h3>
    <p class="subtitle">Contact form submissions from the test site</p>
    <div class="data-table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width:28px;"></th>
            <th>Name</th>
            <th>Item</th>
            <th>Priority</th>
            <th>Email</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody id="contact-tbody"></tbody>
      </table>
    </div>

    <!-- Session profiles -->
    <h3 style="margin-top:28px;">Visitor Profiles</h3>
    <p class="subtitle">Session-level view linking browsing behavior to device and activity</p>
    <div class="data-table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th style="width:28px;"></th>
            <th>Session</th>
            <th>Pages Viewed</th>
            <th>Cart Adds</th>
            <th>Checkout</th>
            <th>Browser</th>
            <th>Device</th>
          </tr>
        </thead>
        <tbody id="sessions-tbody"></tbody>
      </table>
    </div>
  </div>

  <script>
    function tsToDate(raw) {
      if (typeof raw === 'number') {
        const d = new Date(raw > 1e12 ? raw : raw * 1000);
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
      }
      return raw ? String(raw).substring(0,10) : '';
    }
    function fmtDateUS(d) { const [y,m,day]=d.split('-'); return m+'/'+day+'/'+y; }
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

    const charts = {};
    function kill(id) { if(charts[id]){charts[id].destroy();delete charts[id];} }
    const palette = ['#1a1a1a','#d35322','#2B4949','#212E50','#A40607','#6b6b6b','#999'];

    let allEvents = [], allOrders = [], allContacts = [];

    async function load() {
      const from = document.getElementById('date-from').value;
      const to = document.getElementById('date-to').value;
      const qs = `limit=5000&from=${from}&to=${to}`;

      const [evRes, ordRes, conRes] = await Promise.all([
        fetch('api/events.php?' + qs),
        fetch('api/orders.php?from=' + from + '&to=' + to),
        fetch('api/contact.php?from=' + from + '&to=' + to)
      ]);
      allEvents = await evRes.json();
      allEvents.forEach(e => { if (typeof e.payload === 'string') try { e.payload = JSON.parse(e.payload); } catch(x){} });
      allOrders = await ordRes.json();
      try { allContacts = await conRes.json(); } catch(e) { allContacts = []; }
      render();
    }

    function render() {
      const events = allEvents;
      const orders = allOrders;

      // Activity events for cart tracking
      const activities = events.filter(e => e.event_type === 'activity');
      const statics = events.filter(e => e.event_type === 'static');

      // Count cart actions from activity events
      let cartAdds = 0, beginCheckouts = 0;
      activities.forEach(e => {
        const evts = e.payload?.data?.events || [];
        evts.forEach(ev => {
          if (ev.kind === 'add_to_cart') cartAdds++;
          if (ev.kind === 'begin_checkout') beginCheckouts++;
        });
      });

      const completedOrders = orders.length;
      const revenue = orders.reduce((s, o) => s + parseFloat(o.total || 0), 0);

      // Sessions that had any cart activity
      const cartSessions = new Set();
      activities.forEach(e => {
        const evts = e.payload?.data?.events || [];
        if (evts.some(ev => ev.kind === 'add_to_cart')) cartSessions.add(e.session_id);
      });
      const orderSessions = new Set(orders.map(o => o.session_id).filter(Boolean));
      const conversionRate = cartSessions.size > 0 ? ((orderSessions.size / cartSessions.size) * 100).toFixed(1) : '0';

      // KPIs
      document.getElementById('kpi-orders').textContent = completedOrders;
      document.getElementById('kpi-revenue').textContent = '$' + revenue.toFixed(2);
      document.getElementById('kpi-cart-adds').textContent = cartAdds;
      document.getElementById('kpi-conversion').textContent = conversionRate + '%';

      // Funnel chart (SVG)
      const allSessions = new Set(events.map(e => e.session_id).filter(Boolean));
      const funnelContainer = document.getElementById('chart-funnel');
      const funnelSteps = [
        { label: 'All Visitors', value: allSessions.size, color: '#2B4949' },
        { label: 'Added to Cart', value: cartSessions.size, color: '#212E50' },
        { label: 'Began Checkout', value: beginCheckouts, color: '#d35322' },
        { label: 'Completed', value: completedOrders, color: '#1a8a4a' },
      ];

      const fW = 460, fH = 220;
      const stepH = fH / funnelSteps.length;
      const maxWidth = 160;
      const minWidth = 40;
      const labelSpace = 170;
      const funnelCenter = (fW - labelSpace) / 2 + 10;

      // Force each step to be visually narrower than the one above
      const widths = [];
      funnelSteps.forEach((step, i) => {
        if (i === 0) {
          widths.push(maxWidth);
        } else {
          const parentW = widths[i - 1];
          const ratio = funnelSteps[0].value > 0 ? step.value / funnelSteps[0].value : 0;
          const natural = Math.max(ratio * maxWidth, minWidth);
          // Never wider than the step above, and shrink at least a little
          widths.push(Math.min(natural, parentW - 16));
        }
      });

      let svg = `<svg viewBox="0 0 ${fW} ${fH}" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:auto;">`;
      funnelSteps.forEach((step, i) => {
        const topW = i === 0 ? widths[0] : widths[i - 1];
        const botW = widths[i];
        const y = i * stepH;

        svg += `<polygon points="${funnelCenter-topW/2},${y} ${funnelCenter+topW/2},${y} ${funnelCenter+botW/2},${y+stepH-2} ${funnelCenter-botW/2},${y+stepH-2}" fill="${step.color}" opacity="0.88"/>`;
        const textY = y + stepH / 2;
        const pct = i > 0 && funnelSteps[0].value > 0 ? ' (' + ((step.value / funnelSteps[0].value) * 100).toFixed(0) + '%)' : '';
        const labelX = funnelCenter + topW / 2 + 14;
        svg += `<text x="${labelX}" y="${textY - 4}" fill="${step.color}" font-size="12" font-weight="700">${esc(step.label)}</text>`;
        svg += `<text x="${labelX}" y="${textY + 12}" fill="var(--muted, #666)" font-size="11">${step.value}${pct}</text>`;
      });
      svg += '</svg>';
      funnelContainer.innerHTML = svg;

      // Orders over time
      const ordersByDate = {};
      orders.forEach(o => {
        const d = o.created_at ? o.created_at.substring(0, 10) : '';
        if (d) ordersByDate[d] = (ordersByDate[d] || 0) + 1;
      });
      const sortedDates = Object.entries(ordersByDate).sort((a,b) => a[0].localeCompare(b[0]));
      kill('orders-time');
      charts['orders-time'] = new Chart(document.getElementById('chart-orders-time'), {
        type: 'bar',
        data: { labels: sortedDates.map(s => fmtDateUS(s[0])), datasets: [{
          label: 'Orders', data: sortedDates.map(s => s[1]), backgroundColor: '#1a8a4a', borderRadius: 3
        }] },
        options: { responsive: true, plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, grid: { color: '#e8e8e8' } }, x: { grid: { display: false } } } }
      });

      // Top products
      const productCounts = {};
      orders.forEach(o => {
        let items = o.items;
        if (typeof items === 'string') try { items = JSON.parse(items); } catch { items = []; }
        if (Array.isArray(items)) {
          items.forEach(i => {
            const name = i.name || i.id || 'unknown';
            productCounts[name] = (productCounts[name] || 0) + (i.qty || 1);
          });
        }
      });
      const topProducts = Object.entries(productCounts).sort((a,b) => b[1] - a[1]).slice(0, 8);
      kill('products');
      charts['products'] = new Chart(document.getElementById('chart-products'), {
        type: 'bar',
        data: { labels: topProducts.map(p => p[0]), datasets: [{
          label: 'Sold', data: topProducts.map(p => p[1]), backgroundColor: '#d35322', borderRadius: 3
        }] },
        options: { responsive: true, plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, grid: { color: '#e8e8e8' } }, x: { grid: { display: false } } } }
      });

      // Device breakdown from static events
      const devices = {};
      statics.forEach(e => {
        const ua = e.payload?.data?.userAgent || '';
        const os = parseOS(ua);
        devices[os] = (devices[os] || 0) + 1;
      });
      const devEntries = Object.entries(devices).sort((a,b) => b[1] - a[1]);
      kill('devices');
      charts['devices'] = new Chart(document.getElementById('chart-devices'), {
        type: 'doughnut',
        data: { labels: devEntries.map(d => d[0]), datasets: [{ data: devEntries.map(d => d[1]), backgroundColor: palette }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
      });

      // Orders table
      const ordersTbody = document.getElementById('orders-tbody');
      ordersTbody.innerHTML = '';
      if (!orders.length) {
        ordersTbody.innerHTML = '<tr><td colspan="7" class="empty-state">No orders yet.</td></tr>';
      } else {
        orders.forEach(o => {
          let items = o.items;
          if (typeof items === 'string') try { items = JSON.parse(items); } catch { items = []; }
          const itemNames = Array.isArray(items) ? items.map(i => (i.name || i.id) + ' ×' + (i.qty||1)).join(', ') : '—';

          const tr = document.createElement('tr');
          tr.className = 'event-row';
          tr.innerHTML =
            '<td><span class="expand-icon">›</span></td>' +
            '<td class="mono">#' + esc(String(o.id)) + '</td>' +
            '<td>' + esc(o.customer_name || '—') + '</td>' +
            '<td class="mono">' + esc(o.pid || '—') + '</td>' +
            '<td>' + esc(itemNames) + '</td>' +
            '<td class="mono">$' + parseFloat(o.total||0).toFixed(2) + '</td>' +
            '<td class="mono">' + esc((o.created_at||'').substring(0,10)) + '</td>';

          const detailRow = document.createElement('tr');
          detailRow.className = 'detail-row';
          detailRow.style.display = 'none';
          const detailTd = document.createElement('td');
          detailTd.colSpan = 7;
          detailTd.className = 'detail-cell';
          detailTd.innerHTML =
            '<div class="detail-grid">' +
            '<div class="detail-kv"><span class="detail-label">Email</span><span class="detail-value">' + esc(o.customer_email||'—') + '</span></div>' +
            '<div class="detail-kv"><span class="detail-label">Session</span><span class="detail-value mono">' + esc(o.session_id||'—') + '</span></div>' +
            '<div class="detail-kv"><span class="detail-label">Status</span><span class="detail-value">' + esc(o.status||'—') + '</span></div>' +
            '</div>';
          detailRow.appendChild(detailTd);

          tr.addEventListener('click', () => {
            const open = detailRow.style.display !== 'none';
            detailRow.style.display = open ? 'none' : 'table-row';
            tr.classList.toggle('expanded', !open);
          });

          ordersTbody.appendChild(tr);
          ordersTbody.appendChild(detailRow);
        });
      }

      // Session profiles table
      const sessionMap = {};
      events.forEach(e => {
        const sid = e.session_id;
        if (!sid) return;
        if (!sessionMap[sid]) sessionMap[sid] = { pages: new Set(), cartAdds: 0, checkout: false, browser: '', os: '', ua: '' };

        if (e.event_type === 'static') {
          sessionMap[sid].pages.add(shortPath(e.page || ''));
          const ua = e.payload?.data?.userAgent || '';
          if (ua) {
            sessionMap[sid].browser = parseBrowser(ua);
            sessionMap[sid].os = parseOS(ua);
            sessionMap[sid].ua = ua;
          }
        }
        if (e.event_type === 'activity') {
          const evts = e.payload?.data?.events || [];
          evts.forEach(ev => {
            if (ev.kind === 'add_to_cart') sessionMap[sid].cartAdds++;
            if (ev.kind === 'checkout_complete') sessionMap[sid].checkout = true;
          });
        }
      });
      // Mark sessions with orders
      orders.forEach(o => {
        if (o.session_id && sessionMap[o.session_id]) sessionMap[o.session_id].checkout = true;
      });

      const sessionsTbody = document.getElementById('sessions-tbody');
      sessionsTbody.innerHTML = '';
      const sessionEntries = Object.entries(sessionMap).sort((a,b) => b[1].cartAdds - a[1].cartAdds);

      if (!sessionEntries.length) {
        sessionsTbody.innerHTML = '<tr><td colspan="7" class="empty-state">No sessions found.</td></tr>';
      } else {
        sessionEntries.slice(0, 50).forEach(([sid, s]) => {
          const tr = document.createElement('tr');
          tr.className = 'event-row';
          const shortSid = sid.length > 16 ? sid.substring(0, 16) + '…' : sid;
          tr.innerHTML =
            '<td><span class="expand-icon">›</span></td>' +
            '<td class="mono" title="' + esc(sid) + '">' + esc(shortSid) + '</td>' +
            '<td>' + s.pages.size + '</td>' +
            '<td>' + s.cartAdds + '</td>' +
            '<td>' + (s.checkout ? '<span style="color:#1a8a4a;">✓ Yes</span>' : '<span style="color:#c0392b;">✗ No</span>') + '</td>' +
            '<td>' + esc(s.browser) + '</td>' +
            '<td>' + esc(s.os) + '</td>';

          const detailRow = document.createElement('tr');
          detailRow.className = 'detail-row';
          detailRow.style.display = 'none';
          const detailTd = document.createElement('td');
          detailTd.colSpan = 7;
          detailTd.className = 'detail-cell';
          const pagesList = [...s.pages].map(p => esc(p)).join(', ');
          detailTd.innerHTML =
            '<div class="detail-grid">' +
            '<div class="detail-kv"><span class="detail-label">Full Session ID</span><span class="detail-value mono" style="word-break:break-all;">' + esc(sid) + '</span></div>' +
            '<div class="detail-kv"><span class="detail-label">Pages Visited</span><span class="detail-value">' + pagesList + '</span></div>' +
            '<div class="detail-kv"><span class="detail-label">User Agent</span><span class="detail-value" style="word-break:break-all;">' + esc(s.ua) + '</span></div>' +
            '</div>';
          detailRow.appendChild(detailTd);

          tr.addEventListener('click', () => {
            const open = detailRow.style.display !== 'none';
            detailRow.style.display = open ? 'none' : 'table-row';
            tr.classList.toggle('expanded', !open);
          });

          sessionsTbody.appendChild(tr);
          sessionsTbody.appendChild(detailRow);
        });
      }

      // Contact submissions table
      const contactTbody = document.getElementById('contact-tbody');
      contactTbody.innerHTML = '';
      if (!allContacts.length) {
        contactTbody.innerHTML = '<tr><td colspan="6" class="empty-state">No submissions yet.</td></tr>';
      } else {
        allContacts.forEach(c => {
          const tr = document.createElement('tr');
          tr.className = 'event-row';
          tr.innerHTML =
            '<td><span class="expand-icon">›</span></td>' +
            '<td>' + esc(c.name || '—') + '</td>' +
            '<td>' + esc(c.item || '—') + '</td>' +
            '<td><span class="tag-type">' + esc(c.priority || '—') + '</span></td>' +
            '<td>' + esc(c.email || '—') + '</td>' +
            '<td class="mono">' + esc((c.created_at || '').substring(0, 10)) + '</td>';

          const detailRow = document.createElement('tr');
          detailRow.className = 'detail-row';
          detailRow.style.display = 'none';
          const detailTd = document.createElement('td');
          detailTd.colSpan = 6;
          detailTd.className = 'detail-cell';
          detailTd.innerHTML =
            '<div class="detail-grid">' +
            '<div class="detail-kv"><span class="detail-label">Justification</span><span class="detail-value" style="white-space:pre-wrap;">' + esc(c.justification || '—') + '</span></div>' +
            '<div class="detail-kv"><span class="detail-label">IP Address</span><span class="detail-value mono">' + esc(c.ip || '—') + '</span></div>' +
            '<div class="detail-kv"><span class="detail-label">Submitted</span><span class="detail-value">' + esc(c.created_at || '—') + '</span></div>' +
            '</div>';
          detailRow.appendChild(detailTd);

          tr.addEventListener('click', () => {
            const open = detailRow.style.display !== 'none';
            detailRow.style.display = open ? 'none' : 'table-row';
            tr.classList.toggle('expanded', !open);
          });

          contactTbody.appendChild(tr);
          contactTbody.appendChild(detailRow);
        });
      }
    }

    // Save analyst comment
    document.getElementById('save-comment')?.addEventListener('click', async () => {
      const text = document.getElementById('analyst-comment').value;
      const r = await fetch('api/comments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ page: 'customers', comment: text })
      });
      const res = await r.json();
      alert(res.ok ? 'Comment saved.' : 'Failed to save.');
    });

    // date sync
    function syncDates() {
      const from = document.getElementById('date-from');
      const to = document.getElementById('date-to');
      if (!from || !to) return;
      const saved = sessionStorage.getItem('dateRange');
      if (saved) { try { const r = JSON.parse(saved); if (r.from) from.value = r.from; if (r.to) to.value = r.to; } catch(e) {} }
      function save() { sessionStorage.setItem('dateRange', JSON.stringify({ from: from.value, to: to.value })); }
      from.addEventListener('change', save);
      to.addEventListener('change', save);
      document.getElementById('apply-dates')?.addEventListener('click', save);
    }

    syncDates();
    document.getElementById('apply-dates').addEventListener('click', load);
    load();

    document.getElementById('gen-report')?.addEventListener('click', () => {
      const from = document.getElementById('date-from').value;
      const to = document.getElementById('date-to').value;
      window.open('report.php?source=customers&from=' + from + '&to=' + to, '_blank');
    });
  </script>
</body>
</html>