<?php
require_once __DIR__ . '/api/auth.php';
require_auth(); // Redirects to login if not authenticated
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
      <a href="dashboard.php" class="active">Dashboard</a>
      <a href="table.php">Event Log</a>
    </nav>
    <div class="sidebar-bottom">
      <a href="api/logout.php">Log out</a>
    </div>
  </aside>

  <div class="main">
    <h2>Dashboard</h2>
    <p class="subtitle">Analytics from collected visitor data</p>

    <!-- Component 3: Charts go here -->
    <div class="chart-grid">
      <div class="chart-card">
        <h3>Page Views by URL</h3>
        <canvas id="chart-pages"></canvas>
      </div>
      <div class="chart-card">
        <h3>Events Over Time</h3>
        <canvas id="chart-timeline"></canvas>
      </div>
    </div>

    <!-- 
      Charts will be populated by fetching from api/events.php
      and rendering with Chart.js. See component 3 instructions.
    -->
    <script>
      fetch('api/events.php?limit=500')
        .then(r => {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.json();
        })
        .then(data => {
          if (!Array.isArray(data)) throw new Error('Unexpected API response');

          // Chart 1: Page views bar chart
          const pageCounts = {};
          data.forEach(e => {
            const page = e.page || 'unknown';
            pageCounts[page] = (pageCounts[page] || 0) + 1;
          });

          new Chart(document.getElementById('chart-pages'), {
            type: 'bar',
            data: {
              labels: Object.keys(pageCounts),
              datasets: [{
                label: 'Views',
                data: Object.values(pageCounts),
                backgroundColor: '#d35322',
                borderRadius: 3
              }]
            },
            options: {
              responsive: true,
              plugins: { legend: { display: false } },
              scales: {
                y: { beginAtZero: true, grid: { color: '#e8e8e8' } },
                x: { grid: { display: false } }
              }
            }
          });

          // Chart 2: Timeline line chart
          const dateCounts = {};
          data.forEach(e => {
            const d = dayKey(e.client_ts || e.received_at || '');
            if (d) dateCounts[d] = (dateCounts[d] || 0) + 1;
          });
          const sorted = Object.entries(dateCounts).sort((a, b) => a[0].localeCompare(b[0]));

          new Chart(document.getElementById('chart-timeline'), {
            type: 'line',
            data: {
              labels: sorted.map(s => s[0]),
              datasets: [{
                label: 'Events',
                data: sorted.map(s => s[1]),
                borderColor: '#1a1a1a',
                backgroundColor: 'rgba(26,26,26,0.05)',
                fill: true,
                tension: 0.3,
                pointRadius: 3
              }]
            },
            options: {
              responsive: true,
              plugins: { legend: { display: false } },
              scales: {
                y: { beginAtZero: true, grid: { color: '#e8e8e8' } },
                x: { grid: { display: false } }
              }
            }
          });
        })
        .catch(err => console.error('Failed to load event data:', err));

      function dayKey(ts) {
        if (!ts) return '';
        if (typeof ts === 'string' && ts.length >= 10) return ts.substring(0, 10);
        const d = new Date(ts);
        return Number.isNaN(d.getTime()) ? '' : d.toISOString().substring(0, 10);
      }
    </script>
  </div>
</body>
</html>