<?php
require_once __DIR__ . '/api/auth.php';
require_auth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Event Log — The Absolute Essential</title>
  <link rel="stylesheet" href="assets/styles.css" />
</head>
<body class="page-dash">
    <aside class="sidebar">
    <div class="sidebar-brand">The Absolute Essential</div>
    <nav class="sidebar-nav">
      <a href="./dashboard.php">Dashboard</a>
      <a href="./table.php" class="active">Event Log</a>
    </nav>
    <div class="sidebar-bottom">
      <a href="./api/logout.php">Log out</a>
    </div>
  </aside>

  <div class="main">
    <h2>Event Log</h2>
    <p class="subtitle">Raw collected events from the analytics collector</p>

    <div class="table-controls">
      <select id="filter-type">
        <option value="">All types</option>
        <option value="static">static</option>
        <option value="performance">performance</option>
        <option value="activity">activity</option>
      </select>
      <input type="text" id="filter-session" placeholder="Filter by session…" />
      <select id="display-records">
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
      <!-- previous and next page -->
      <button id="prev-page" disabled>‹ Prev</button>
      <span id="page-index">Page 1</span>
      <button id="next-page" disabled>Next ›</button>
    </div>

    <div class="data-table-wrap">
      <table class="data-table" id="events-table">
        <thead>
          <tr>
            <th style="width:28px;"></th>
            <th>ID</th>
            <th>Type</th>
            <th>Page</th>
            <th>Client Time</th>
            <th>Summary</th>
          </tr>
        </thead>
        <tbody id="table-body">
          <tr><td colspan="6" class="empty-state">Loading…</td></tr>
        </tbody>
      </table>
    </div>

<script>
      const tbody   = document.getElementById('table-body');
      const filterT = document.getElementById('filter-type');
      const filterS = document.getElementById('filter-session');
      const limitSel = document.getElementById('display-records');
      const prevBtn = document.getElementById('prev-page');
      const nextBtn = document.getElementById('next-page');
      const pageIdx = document.getElementById('page-index');

      let allData = [];
      let currentPage = 0;

      function getLimit() { return parseInt(limitSel.value, 10); }

      function totalPages() { return Math.max(1, Math.ceil(allData.length / getLimit())); }

      function updatePagination() {
        const tp = totalPages();
        currentPage = Math.min(currentPage, tp - 1);
        pageIdx.textContent = 'Page ' + (currentPage + 1) + ' of ' + tp;
        prevBtn.disabled = currentPage <= 0;
        nextBtn.disabled = currentPage >= tp - 1;
      }

      function renderPage() {
        tbody.innerHTML = '';
        const limit = getLimit();
        const start = currentPage * limit;
        const slice = allData.slice(start, start + limit);

        if (!slice.length) {
          tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No events found.</td></tr>';
          updatePagination();
          return;
        }

        slice.forEach(e => {
          if (typeof e.payload === 'string') {
            try { e.payload = JSON.parse(e.payload); } catch(err) {}
          }

          const tr = document.createElement('tr');
          tr.className = 'event-row';
          tr.innerHTML =
            '<td class="expand-cell"><span class="expand-icon">›</span></td>' +
            '<td class="mono">' + esc(String(e.id || '')) + '</td>' +
            '<td><span class="tag-type tag-' + esc(e.event_type || '') + '">' + esc(e.event_type || '') + '</span></td>' +
            '<td class="page-cell">' + esc(shortUrl(e.page || '')) + '</td>' +
            '<td class="mono">' + esc(e.client_ts || '') + '</td>' +
            '<td class="summary-cell">' + summarize(e) + '</td>';

          const detailRow = document.createElement('tr');
          detailRow.className = 'detail-row';
          detailRow.style.display = 'none';
          const detailTd = document.createElement('td');
          detailTd.colSpan = 6;
          detailTd.className = 'detail-cell';
          detailRow.appendChild(detailTd);

          let loaded = false;
          tr.addEventListener('click', () => {
            const open = detailRow.style.display !== 'none';
            detailRow.style.display = open ? 'none' : 'table-row';
            tr.classList.toggle('expanded', !open);
            if (!loaded) {
              detailTd.innerHTML = detailHTML(e);
              loaded = true;
            }
          });

          tbody.appendChild(tr);
          tbody.appendChild(detailRow);
        });

        updatePagination();
      }

      /* ── Extract useful info per event type ──────── */
      function summarize(e) {
        const p = e.payload;
        if (!p || typeof p !== 'object') return '—';
        const d = p.data || {};

        if (e.event_type === 'static') {
          const parts = [];
          if (d.userAgent) {
            const ua = d.userAgent;
            const chrome  = ua.match(/Chrome\/([\d]+)/);
            const firefox = ua.match(/Firefox\/([\d]+)/);
            const safari  = ua.match(/Version\/([\d]+).*Safari/);
            const browser = chrome ? 'Chrome ' + chrome[1]
                          : firefox ? 'Firefox ' + firefox[1]
                          : safari ? 'Safari ' + safari[1] : '—';
            const os = ua.includes('Mac') ? 'macOS'
                     : ua.includes('Windows') ? 'Windows'
                     : ua.includes('Linux') ? 'Linux'
                     : ua.includes('Android') ? 'Android'
                     : ua.includes('iPhone') ? 'iOS' : '';
            parts.push(browser + (os ? ' / ' + os : ''));
          }
          if (d.screen) parts.push(d.screen.width + '×' + d.screen.height);
          if (d.connectionType) parts.push(d.connectionType);
          return parts.join(' · ') || '—';
        }

        if (e.event_type === 'performance') {
          const parts = [];
          if (d.totalLoadMs != null) parts.push(d.totalLoadMs + 'ms load');
          if (d.timing) {
            if (d.timing.domContentLoaded != null) parts.push('DCL ' + d.timing.domContentLoaded + 'ms');
            if (d.timing.type) parts.push(d.timing.type);
          }
          return parts.join(' · ') || '—';
        }

        if (e.event_type === 'activity') {
          const events = d.events || [];
          if (!events.length) return '—';
          const counts = {};
          let leaveReason = null;
          let idleMs = null;
          events.forEach(ev => {
            const k = ev.kind || 'unknown';
            counts[k] = (counts[k] || 0) + 1;
            if (k === 'page_leave' && ev.reason) leaveReason = ev.reason;
            if (k === 'idle_end' && ev.idleDurationMs) idleMs = ev.idleDurationMs;
          });
          const parts = [];
          for (const [k, v] of Object.entries(counts)) {
            parts.push(v + ' ' + k);
          }
          if (idleMs) parts.push('idle ' + (idleMs / 1000).toFixed(1) + 's');
          if (leaveReason) parts.push('(' + leaveReason + ')');
          return parts.join(' · ');
        }

        return '—';
      }

      /* ── Render detail panel per event type ──────── */
      function detailHTML(e) {
        const p = e.payload;
        if (!p || typeof p !== 'object') return '<pre class="payload-pre">{}</pre>';
        const d = p.data || {};
        let html = '';

        if (e.event_type === 'static' && d) {
          html += '<div class="detail-grid">';
          html += kv('User Agent', d.userAgent);
          html += kv('Screen', d.screen ? d.screen.width + '×' + d.screen.height : '');
          html += kv('Window', d.window ? d.window.width + '×' + d.window.height : '');
          html += kv('Language', d.language);
          html += kv('Connection', d.connectionType);
          html += kv('Cookies', d.cookiesEnabled != null ? String(d.cookiesEnabled) : '');
          html += kv('JavaScript', d.allowsJS != null ? String(d.allowsJS) : '');
          html += kv('CSS', d.allowsCSS != null ? String(d.allowsCSS) : '');
          html += kv('Images', d.allowsImages != null ? String(d.allowsImages) : '');
          html += '</div>';
        }

        if (e.event_type === 'performance' && d) {
          html += '<div class="detail-grid">';
          html += kv('Total Load', d.totalLoadMs != null ? d.totalLoadMs + 'ms' : '');
          if (d.timing) {
            html += kv('Nav Type', d.timing.type);
            html += kv('DOM Content Loaded', d.timing.domContentLoaded != null ? d.timing.domContentLoaded + 'ms' : '');
            html += kv('Response End', d.timing.responseEnd != null ? Math.round(d.timing.responseEnd) + 'ms' : '');
          }
          if (d.navigationEntry) {
            const n = d.navigationEntry;
            html += kv('DNS Lookup', n.domainLookupEnd != null && n.domainLookupStart != null ? Math.round(n.domainLookupEnd - n.domainLookupStart) + 'ms' : '');
            html += kv('TLS Handshake', n.connectEnd != null && n.secureConnectionStart ? Math.round(n.connectEnd - n.secureConnectionStart) + 'ms' : '');
            html += kv('Transfer Size', n.transferSize != null ? fmtBytes(n.transferSize) : '');
            html += kv('Decoded Size', n.decodedBodySize != null ? fmtBytes(n.decodedBodySize) : '');
            html += kv('Protocol', n.nextHopProtocol);
            html += kv('HTTP Status', n.responseStatus);
          }
          html += '</div>';
        }

        if (e.event_type === 'activity' && d.events) {
          html += '<table class="mini-table"><thead><tr><th>Time</th><th>Kind</th><th>Details</th></tr></thead><tbody>';
          d.events.forEach(ev => {
            const ts = ev.ts ? new Date(ev.ts).toLocaleTimeString() : '';
            let detail = '';
            if (ev.kind === 'mousemove' || ev.kind === 'scroll' || ev.kind === 'click')
              detail = ev.x + ', ' + ev.y;
            else if (ev.kind === 'keydown') detail = ev.key || '';
            else if (ev.kind === 'page_leave') detail = ev.reason || '';
            else if (ev.kind === 'page_enter') detail = shortUrl(ev.page || '');
            else if (ev.kind === 'idle_end') detail = ev.idleDurationMs ? ev.idleDurationMs + 'ms' : '';
            html += '<tr><td class="mono">' + esc(ts) + '</td><td>' + esc(ev.kind || '') + '</td><td class="mono">' + esc(detail) + '</td></tr>';
          });
          html += '</tbody></table>';
        }

        html += '<details class="raw-toggle"><summary>Raw payload</summary>';
        html += '<pre class="payload-pre">' + esc(JSON.stringify(p, null, 2)) + '</pre>';
        html += '</details>';

        html += '<div class="detail-meta">';
        html += '<span>Session: <span class="mono">' + esc(e.session_id || '') + '</span></span>';
        if (p._server_ts) html += '<span>Received: <span class="mono">' + new Date(p._server_ts).toLocaleString() + '</span></span>';
        html += '</div>';

        return html;
      }

      function kv(label, val) {
        if (!val && val !== 0) return '';
        return '<div class="detail-kv"><span class="detail-label">' + esc(label) + '</span><span class="detail-value">' + esc(String(val)) + '</span></div>';
      }

      function shortUrl(url) {
        try { return new URL(url).pathname; } catch(e) { return url; }
      }

      function fmtBytes(b) {
        if (b < 1024) return b + ' B';
        return (b / 1024).toFixed(1) + ' KB';
      }

      /* ── Fetch all matching events, then paginate client-side ── */
      function loadEvents() {
        let url = 'api/events.php?limit=500';
        const type = filterT.value;
        const sess = filterS.value.trim();
        if (type) url += '&type=' + encodeURIComponent(type);
        if (sess) url += '&session=' + encodeURIComponent(sess);

        tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Loading…</td></tr>';

        fetch(url)
          .then(r => r.json())
          .then(data => {
            allData = data;
            currentPage = 0;
            renderPage();
          })
          .catch(err => {
            console.error('Failed to load events:', err);
            tbody.innerHTML = '<tr><td colspan="6" class="empty-state" style="color:#c0392b;">Error loading events.</td></tr>';
          });
      }

      document.addEventListener('DOMContentLoaded', () => {
        let debounce;
        filterS.addEventListener('input', () => {
          clearTimeout(debounce);
          debounce = setTimeout(loadEvents, 400);
        });
        filterT.addEventListener('change', loadEvents);
        limitSel.addEventListener('change', () => { currentPage = 0; renderPage(); });
        prevBtn.addEventListener('click', () => { if (currentPage > 0) { currentPage--; renderPage(); } });
        nextBtn.addEventListener('click', () => { if (currentPage < totalPages() - 1) { currentPage++; renderPage(); } });
        loadEvents();
      });

      function esc(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
      }
    </script>
  </div>
</body>
</html>