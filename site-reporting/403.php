<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>403 Forbidden | The Absolute Essential</title>
  <link rel="stylesheet" href="assets/styles.css" />
</head>
<body class="page-dash">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <div class="main" style="display:flex; align-items:center; justify-content:center; min-height:80vh;">
    <div style="text-align:center;">
      <h1 style="font-size:3rem; margin-bottom:8px;">403</h1>
      <p class="subtitle">You don't have permission to access this page.</p>
      <a href="dashboard.php" class="filter-btn" style="display:inline-block; margin-top:16px; text-decoration:none;">Back to Dashboard</a>
    </div>
  </div>
</body>
</html>