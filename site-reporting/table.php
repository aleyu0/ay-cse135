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
      <a href="dashboard.php">Dashboard</a>
      <a href="table.php" class="active">Event Log</a>
    </nav>
    <div class="sidebar-bottom">
      <a href="api/logout.php">Log out</a>
    </div>
  </aside>

  <div class="main">
    <aside class="sidebar">
    <div class="sidebar-brand">The Absolute Essential</div>
    <nav class="sidebar-nav">
      <a href="dashboard.php">Dashboard</a>
      <a href="table.php" class="active">Event Log</a>
    </nav>
    <div class="sidebar-bottom">
      <a href="api/logout.php">Log out</a>
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
      <input type="text" id="filter-session" placeholder="Filter by session ID…" />
    </div>

    <div class="data-table-wrap">
      <table class="data-table" id="events-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Type</th>
            <th>Page</th>
            <th>Session</th>
            <th>Client Time</th>
            <th>Payload</th>
          </tr>
        </thead>
        <tbody id="table-body">
          <tr><td colspan="6" style="color:var(--fg2);padding:20px;">Loading…</td></tr>
        </tbody>
      </table>
    </div>

    <script>
      const tbody    = document.getElementById('table-body');
      const filterT  = document.getElementById('filter-type');
      const filterS  = document.getElementById('filter-session');

      function loadEvents() {
        let url = 'api/events.php?limit=500';
        const type = filterT.value;
        const sess = filterS.value.trim();
        if (type) url += '&type=' + encodeURIComponent(type);
        if (sess) url += '&session=' + encodeURIComponent(sess);

        tbody.innerHTML = '<tr><td colspan="6" style="color:var(--fg2);padding:20px;">Loading…</td></tr>';

        fetch(url)
          .then(r => r.json())
          .then(data => {
            tbody.innerHTML = '';

            if (!data.length) {
              tbody.innerHTML = '<tr><td colspan="6" style="color:var(--fg2);padding:20px;">No events found.</td></tr>';
              return;
            }

            data.forEach(e => {
              const tr = document.createElement('tr');

              // Format client_ts — could be unix seconds or ms
              let ts = e.client_ts || '';
              if (typeof ts === 'number') {
                const d = ts > 1e12 ? new Date(ts) : new Date(ts * 1000);
                ts = d.toISOString().replace('T', ' ').substring(0, 19);
              }

              // Payload: show compact JSON
              let payload = '';
              if (e.payload && typeof e.payload === 'object') {
                payload = JSON.stringify(e.payload);
              } else if (e.payload) {
                payload = String(e.payload);
              }

              tr.innerHTML =
                '<td class="mono">' + esc(String(e.id || '')) + '</td>' +
                '<td><span class="tag-type">' + esc(e.event_type || '') + '</span></td>' +
                '<td>' + esc(e.page || '') + '</td>' +
                '<td class="mono" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(e.session_id || '') + '</td>' +
                '<td class="mono">' + esc(ts) + '</td>' +
                '<td class="mono payload-cell">' + esc(payload) + '</td>';

              tbody.appendChild(tr);
            });
          })
          .catch(err => {
            console.error('Failed to load events:', err);
            tbody.innerHTML = '<tr><td colspan="6" style="color:#c0392b;">Error loading events.</td></tr>';
          });
      }

      // Debounced session filter
      let debounce;
      filterS.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(loadEvents, 400);
      });
      filterT.addEventListener('change', loadEvents);

      // Initial load
      loadEvents();

      function esc(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
      }
    </script>
  </div>
</body>
</html>