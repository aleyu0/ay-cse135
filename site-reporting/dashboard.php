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

  <p>You're in an authenticated page. Dashboard.</p>
  

</body>
</html>