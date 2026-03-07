<?php
require_once __DIR__ . '/api/auth.php';
require_auth();

// TEMP users are managed via a simple JSON file.
// TODO: MAKE THIS A DB W/ PASSWORD HASH
$usersFile = __DIR__ . '/api/users.json';

function loadUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) {
        // default admin
        $default = [
            ['username' => 'admin', 'role' => 'admin', 'created' => date('Y-m-d')],
            ['username' => 'grader', 'role' => 'viewer', 'created' => date('Y-m-d')]
        ];
        file_put_contents($usersFile, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    return json_decode(file_get_contents($usersFile), true) ?: [];
}

function saveUsers($users) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
}

$message = '';

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' && !empty($_POST['new_username'])) {
        $users = loadUsers();
        $newUser = trim($_POST['new_username']);
        $newRole = $_POST['new_role'] ?? 'viewer';
        // Check duplicate
        $exists = false;
        foreach ($users as $u) { if ($u['username'] === $newUser) $exists = true; }
        if ($exists) {
            $message = 'User "' . htmlspecialchars($newUser) . '" already exists.';
        } else {
            $users[] = ['username' => $newUser, 'role' => $newRole, 'created' => date('Y-m-d')];
            saveUsers($users);
            $message = 'User "' . htmlspecialchars($newUser) . '" added.';
        }
    }
    if ($_POST['action'] === 'remove' && !empty($_POST['rm_username'])) {
        $users = loadUsers();
        $rm = $_POST['rm_username'];
        if ($rm === 'admin') {
            $message = 'Cannot remove the admin user.';
        } else {
            $users = array_values(array_filter($users, fn($u) => $u['username'] !== $rm));
            saveUsers($users);
            $message = 'User "' . htmlspecialchars($rm) . '" removed.';
        }
    }
}

$users = loadUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Admin — The Absolute Essential</title>
  <link rel="stylesheet" href="assets/styles.css" />
</head>
<body class="page-dash">
  <aside class="sidebar">
    <div class="sidebar-brand">The Absolute Essential</div>
    <nav class="sidebar-nav">
      <a href="dashboard.php">Overview</a>
      <a href="table.php">Event Log</a>
      <a href="speed.php">Speed &amp; Vitals</a>
      <a href="errors.php">Errors</a>
      <a href="admin.php" class="active">Users</a>
    </nav>
    <div class="sidebar-bottom">
      <a href="api/logout.php">Log out</a>
    </div>
  </aside>

  <div class="main">
    <h2>User Administration</h2>
    <p class="subtitle">Manage dashboard access and roles</p>

    <?php if ($message): ?>
      <div class="admin-msg"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="data-table-wrap" style="margin-top:20px;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Username</th>
            <th>Role</th>
            <th>Created</th>
            <th style="width:80px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><span class="tag-type"><?= htmlspecialchars($u['role']) ?></span></td>
            <td class="mono"><?= htmlspecialchars($u['created'] ?? '—') ?></td>
            <td>
              <?php if ($u['username'] !== 'admin'): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this user?');">
                <input type="hidden" name="action" value="remove" />
                <input type="hidden" name="rm_username" value="<?= htmlspecialchars($u['username']) ?>" />
                <button type="submit" class="remove-btn">Remove</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="admin-add-form">
      <h3>Add User</h3>
      <form method="POST" class="add-user-row">
        <input type="hidden" name="action" value="add" />
        <input type="text" name="new_username" placeholder="Username" required />
        <select name="new_role">
          <option value="viewer">Viewer</option>
          <option value="admin">Admin</option>
        </select>
        <button type="submit" class="filter-btn">Add</button>
      </form>
    </div>
  </div>
</body>
</html>