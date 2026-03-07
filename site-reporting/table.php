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
    <h2>Event Log</h2>
    <p class="subtitle">Raw collected events from the analytics collector</p>

    <div class="data-table-wrap">
      <table class="data-table" id="events-table">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>URL</th>
            <th>User Agent</th>
            <th>Event Type</th>
            <th>Data</th>
          </tr>
        </thead>
        <tbody id="table-body">
          <!-- Populated by JS from api/events.php -->
        </tbody>
      </table>
    </div>

    <script>
      fetch('api/events.php')
        .then(r => r.json())
        .then(data => {
          const tbody = document.getElementById('table-body');
          if (!data.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="color:var(--fg2);padding:20px;">No events recorded yet.</td></tr>';
            return;
          }
          data.forEach(e => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td class="mono">${esc(e.timestamp || e.date || '')}</td>
              <td>${esc(e.url || '')}</td>
              <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(e.userAgent || e.user_agent || '')}</td>
              <td>${esc(e.type || e.event || '')}</td>
              <td class="mono" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(JSON.stringify(e.data || ''))}</td>
            `;
            tbody.appendChild(tr);
          });
        })
        .catch(err => {
          document.getElementById('table-body').innerHTML =
            '<tr><td colspan="5" style="color:#c0392b;">Error loading events.</td></tr>';
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