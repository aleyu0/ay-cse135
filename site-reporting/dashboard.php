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
      <a href="./dashboard.php">Dashboard</a>
      <a href="./table.php" class="active">Event Log</a>
    </nav>
    <div class="sidebar-bottom">
      <a href="./api/logout.php">Log out</a>
    </div>
  </aside>

  <div class="main">
      <p>You're in an authenticated page. Dashboard.</p>

  </div>
</body>
</html>