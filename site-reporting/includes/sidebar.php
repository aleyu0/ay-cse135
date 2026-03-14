<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$currentUser = get_auth_user();
$userName = $_SESSION['username'] ?? ($currentUser['username'] ?? 'Unknown');
$userRole = $_SESSION['role'] ?? ($currentUser['role'] ?? 'viewer');
?>
<button class="sidebar-toggle" id="sidebar-toggle" aria-label="Open menu">☰</button>
<aside class="sidebar">
  <div class="sidebar-brand">The Absolute Essential</div>
  <nav class="sidebar-nav">
    <?php if (has_permission('view-dashboard')): ?>
    <a href="dashboard.php" <?= $currentPage === 'dashboard.php' ? 'class="active"' : '' ?>>
      <img src="assets/icons/dashboard.svg" alt="" width="16" height="16" class="nav-icon" />
      Overview
    </a>
    <?php endif; ?>

    <?php if (has_permission('view-logs')): ?>
    <a href="table.php" <?= $currentPage === 'table.php' ? 'class="active"' : '' ?>>
      <img src="assets/icons/log.svg" alt="" width="16" height="16" class="nav-icon" />
      Event Log
    </a>
    <?php endif; ?>

    <?php if (has_permission('view-performance')): ?>
    <a href="speed.php" <?= $currentPage === 'speed.php' ? 'class="active"' : '' ?>>
      <img src="assets/icons/speed.svg" alt="" width="16" height="16" class="nav-icon" />
      Speed &amp; Vitals
    </a>
    <?php endif; ?>

    <?php if (has_permission('view-errors')): ?>
    <a href="errors.php" <?= $currentPage === 'errors.php' ? 'class="active"' : '' ?>>
      <img src="assets/icons/errors.svg" alt="" width="16" height="16" class="nav-icon" />
      Errors
    </a>
    <?php endif; ?>

    <?php if (has_permission('view-behavioral')): ?>
    <a href="customers.php" <?= $currentPage === 'customers.php' ? 'class="active"' : '' ?>>
      <img src="assets/icons/customers.svg" alt="" width="16" height="16" class="nav-icon" />
      Customers
    </a>
    <?php endif; ?>

    <?php if (has_permission('view-reports')): ?>
    <a href="reports.php" <?= $currentPage === 'reports.php' ? 'class="active"' : '' ?>>
      <img src="assets/icons/report.svg" alt="" width="16" height="16" class="nav-icon" />
      Reports
    </a>
    <?php endif; ?>

    <a href="admin.php" <?= $currentPage === 'admin.php' ? 'class="active"' : '' ?>>
      <img src="assets/icons/users.svg" alt="" width="16" height="16" class="nav-icon" />
      <?= has_permission('view-users') ? 'Users' : 'My Account' ?>
    </a>
  </nav>
  <div class="sidebar-bottom">
    <span class="sidebar-user">
    <?= htmlspecialchars($userName) ?>
    <span class="tag-type"><?= htmlspecialchars($userRole) ?></span>
    </span>
    <a href="api/logout.php">Log out</a>
  </div>
</aside>
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>
<script>
(function() {
  const toggle = document.getElementById('sidebar-toggle');
  const sidebar = document.querySelector('.sidebar');
  const backdrop = document.getElementById('sidebar-backdrop');
  if (!toggle || !sidebar || !backdrop) return;

  toggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    backdrop.classList.toggle('open');
    toggle.textContent = sidebar.classList.contains('open') ? '✕' : '☰';
  });
  backdrop.addEventListener('click', () => {
    sidebar.classList.remove('open');
    backdrop.classList.remove('open');
    toggle.textContent = '☰';
  });
})();
</script>