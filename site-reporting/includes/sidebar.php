<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<aside class="sidebar">
  <div class="sidebar-brand">The Absolute Essential</div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" <?= $currentPage === 'dashboard.php' ? 'class="active"' : '' ?>>
      <img src="assets/icons/dashboard.svg" alt="" width="16" height="16" class="nav-icon" />
      Overview
    </a>
    <a href="table.php" <?= $currentPage === 'table.php' ? 'class="active"' : '' ?>>
      <img src="assets/icons/log.svg" alt="" width="16" height="16" class="nav-icon" />
      Event Log
    </a>
    <a href="speed.php" <?= $currentPage === 'speed.php' ? 'class="active"' : '' ?>>
      <img src="assets/icons/speed.svg" alt="" width="16" height="16" class="nav-icon" />
      Speed &amp; Vitals
    </a>
    <a href="errors.php" <?= $currentPage === 'errors.php' ? 'class="active"' : '' ?>>
      <img src="assets/icons/errors.svg" alt="" width="16" height="16" class="nav-icon" />
      Errors
    </a>
    <a href="admin.php" <?= $currentPage === 'admin.php' ? 'class="active"' : '' ?>>
      <img src="assets/icons/users.svg" alt="" width="16" height="16" class="nav-icon" />
      Users
    </a>
  </nav>
  <div class="sidebar-bottom">
    <a href="api/logout.php">Log out</a>
  </div>
</aside>