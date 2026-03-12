<?php
require_once __DIR__ . '/api/auth.php';
require_auth();
require_permission('view-users');

$pdo = get_db();
$me = get_auth_user();
$myRole = $me['role'] ?? 'viewer';
$message = '';
$msgType = 'info';

// ── Handle POST actions ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ADD USER
    if ($action === 'add' && has_permission('make-users')) {
        $newUser = trim($_POST['new_username'] ?? '');
        $newPass = $_POST['new_password'] ?? '';
        $newRole = $_POST['new_role'] ?? 'viewer';
        $newSections = $_POST['new_sections'] ?? [];

        $validRoles = ['viewer', 'analyst', 'admin'];
        if ($myRole === 'super_admin') $validRoles[] = 'super_admin';

        if ($newUser === '' || strlen($newUser) > 100) {
            $message = 'Username is required (max 100 chars).';
            $msgType = 'error';
        } elseif (strlen($newPass) < 6) {
            $message = 'Password must be at least 6 characters.';
            $msgType = 'error';
        } elseif (!in_array($newRole, $validRoles)) {
            $message = 'Invalid role selected.';
            $msgType = 'error';
        } else {
            // Check duplicate
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u");
            $stmt->execute([':u' => $newUser]);
            if ($stmt->fetch()) {
                $message = 'User "' . htmlspecialchars($newUser) . '" already exists.';
                $msgType = 'error';
            } else {
                $sections = ($newRole === 'analyst' && !empty($newSections)) ? json_encode($newSections) : null;
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, allowed_sections) VALUES (:u, :p, :r, :s)");
                $stmt->execute([
                    ':u' => $newUser,
                    ':p' => password_hash($newPass, PASSWORD_BCRYPT),
                    ':r' => $newRole,
                    ':s' => $sections,
                ]);
                $message = 'User "' . htmlspecialchars($newUser) . '" created.';
                $msgType = 'success';
            }
        }
    }

    // REMOVE USER
    if ($action === 'remove' && isset($_POST['rm_id'])) {
        $rmId = (int) $_POST['rm_id'];
        // Can't remove yourself
        if ($rmId === (int) $me['id']) {
            $message = 'You cannot remove yourself.';
            $msgType = 'error';
        } else {
            // Check target role — only super_admin can remove admins/super_admins
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
            $stmt->execute([':id' => $rmId]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                $message = 'User not found.';
                $msgType = 'error';
            } elseif (in_array($target['role'], ['super_admin', 'admin']) && $myRole !== 'super_admin') {
                $message = 'Only super admins can remove admin accounts.';
                $msgType = 'error';
            } elseif (!has_permission('delete-users') && $target['role'] !== 'viewer') {
                $message = 'Insufficient permissions to remove this user.';
                $msgType = 'error';
            } else {
                $pdo->prepare("DELETE FROM sessions WHERE user_id = :id")->execute([':id' => $rmId]);
                $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $rmId]);
                $message = 'User removed.';
                $msgType = 'success';
            }
        }
    }

    // EDIT USER (change password, role, sections)
    if ($action === 'edit' && isset($_POST['edit_id'])) {
        $editId = (int) $_POST['edit_id'];
        $editPass = $_POST['edit_password'] ?? '';
        $editRole = $_POST['edit_role'] ?? '';
        $editSections = $_POST['edit_sections'] ?? [];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $editId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            $message = 'User not found.';
            $msgType = 'error';
        } else {
            $isSelf = ($editId === (int) $me['id']);
            $canEditRole = has_permission('edit-users') && !$isSelf;

            // Update password if provided
            if ($editPass !== '') {
                if (strlen($editPass) < 6) {
                    $message = 'Password must be at least 6 characters.';
                    $msgType = 'error';
                } else {
                    // Users can change their own password, admins can change others
                    if ($isSelf || (has_permission('edit-users') && !($target['role'] === 'super_admin' && $myRole !== 'super_admin'))) {
                        $pdo->prepare("UPDATE users SET password_hash = :p WHERE id = :id")
                            ->execute([':p' => password_hash($editPass, PASSWORD_BCRYPT), ':id' => $editId]);
                        $message = 'Password updated.';
                        $msgType = 'success';
                    } else {
                        $message = 'Insufficient permissions.';
                        $msgType = 'error';
                    }
                }
            }

            // Update role if changed and user has permission
            if ($target['role'] === 'super_admin' && $myRole !== 'super_admin') {
              $message = 'Only super admins can modify super admin accounts.';
              $msgType = 'error';
            } elseif ($editRole !== '' && $editRole !== $target['role'] && $canEditRole) {
              $validRoles = ['viewer', 'analyst', 'admin'];
              if ($myRole === 'super_admin') $validRoles[] = 'super_admin';
              if (in_array($editRole, $validRoles)) {
                  $sections = ($editRole === 'analyst' && !empty($editSections)) ? json_encode($editSections) : null;
                  $pdo->prepare("UPDATE users SET role = :r, allowed_sections = :s WHERE id = :id")
                      ->execute([':r' => $editRole, ':s' => $sections, ':id' => $editId]);
                  // Invalidate their sessions on role change
                  $pdo->prepare("DELETE FROM sessions WHERE user_id = :id")->execute([':id' => $editId]);
                  $message = 'User updated.';
                  $msgType = 'success';
              }
            }

            // Update sections only (for analysts)
            if ($editRole === '' && $target['role'] === 'analyst' && $canEditRole) {
                $sections = !empty($editSections) ? json_encode($editSections) : null;
                $pdo->prepare("UPDATE users SET allowed_sections = :s WHERE id = :id")
                    ->execute([':s' => $sections, ':id' => $editId]);
                $message = 'Sections updated.';
                $msgType = 'success';
            }
        }
    }
}

// Fetch all users
$users = $pdo->query("SELECT id, username, role, allowed_sections, created_at FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$canMake = has_permission('make-users');
$canEdit = has_permission('edit-users');
$canDelete = has_permission('delete-users');
$allSections = ['performance', 'behavioral', 'errors', 'logs'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Admin | The Absolute Essential</title>
  <link rel="stylesheet" href="assets/styles.css" />
</head>
<body class="page-dash">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="main">
    <div class="page-header">
      <div>
        <h2>User Administration</h2>
        <p class="subtitle">Manage dashboard access and roles</p>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="admin-msg <?= $msgType === 'success' ? 'msg-success' : ($msgType === 'error' ? 'msg-error' : '') ?>">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- Users table -->
    <div class="data-table-wrap" style="margin-top:20px;">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Sections</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <?php
            $isSelf = ((int)$u['id'] === (int)$me['id']);
            $sections = json_decode($u['allowed_sections'] ?? '[]', true) ?: [];
          ?>
          <tr>
            <td class="mono"><?= (int)$u['id'] ?></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td>
              <span class="tag-type tag-<?= htmlspecialchars($u['role']) ?>">
                <?= htmlspecialchars($u['role']) ?>
              </span>
            </td>
            <td>
              <?php if ($sections): ?>
                <?php foreach ($sections as $s): ?>
                  <span class="tag-type"><?= htmlspecialchars($s) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="mono" style="opacity:0.5;">—</span>
              <?php endif; ?>
            </td>
            <td class="mono"><?= htmlspecialchars(substr($u['created_at'] ?? '', 0, 10)) ?></td>
            <td>
              <div style="display:flex; gap:6px; flex-wrap:wrap;">
                <?php if ($isSelf || ($canEdit && !($u['role'] === 'super_admin' && $myRole !== 'super_admin'))): ?>
                  <button class="filter-btn" onclick="openEdit(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['role'], ENT_QUOTES) ?>', <?= htmlspecialchars(json_encode($sections)) ?>)">Edit</button>
                <?php endif; ?>
                <?php if ($canDelete && !$isSelf): ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Remove <?= htmlspecialchars($u['username'], ENT_QUOTES) ?>?');">
                    <input type="hidden" name="action" value="remove" />
                    <input type="hidden" name="rm_id" value="<?= (int)$u['id'] ?>" />
                    <button type="submit" class="remove-btn">Remove</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($canMake): ?>
    <!-- Add user form -->
    <div class="admin-add-form">
      <h3>Add User</h3>
      <form method="POST" class="add-user-form">
        <input type="hidden" name="action" value="add" />
        <div class="add-user-row">
          <input type="text" name="new_username" placeholder="Username" required />
          <input type="password" name="new_password" placeholder="Password (min 6)" required />
          <select name="new_role" id="add-role" onchange="toggleAddSections()">
            <option value="viewer">Viewer</option>
            <option value="analyst">Analyst</option>
            <option value="admin">Admin</option>
            <?php if ($myRole === 'super_admin'): ?>
            <option value="super_admin">Super Admin</option>
            <?php endif; ?>
          </select>
          <button type="submit" class="filter-btn">Add User</button>
        </div>
        <div id="add-sections" style="display:none; margin-top:10px;">
          <label style="font-size:0.88rem; color:var(--text-muted);">Allowed sections:</label>
          <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:4px;">
            <?php foreach ($allSections as $s): ?>
              <label style="display:flex; align-items:center; gap:4px; font-size:0.88rem;">
                <input type="checkbox" name="new_sections[]" value="<?= $s ?>" /> <?= $s ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Edit modal -->
    <div class="modalOverlay" id="edit-overlay" style="display:none;">
      <div class="modal" style="max-width:460px;">
        <div class="modalHeader">
          <h2 id="edit-title">Edit User</h2>
          <button class="btn" onclick="closeEdit()">Close</button>
        </div>
        <form method="POST" style="margin-top:14px;">
          <input type="hidden" name="action" value="edit" />
          <input type="hidden" name="edit_id" id="edit-id" />

          <div style="margin-bottom:10px;">
            <label>New Password <span style="font-size:0.82rem; color:var(--text-muted);">(leave blank to keep current)</span></label>
            <input type="password" name="edit_password" placeholder="New password" />
          </div>

          <?php if ($canEdit): ?>
          <div style="margin-bottom:10px;">
            <label>Role</label>
            <select name="edit_role" id="edit-role" onchange="toggleEditSections()">
              <option value="">— No change —</option>
              <option value="viewer">Viewer</option>
              <option value="analyst">Analyst</option>
              <option value="admin">Admin</option>
              <?php if ($myRole === 'super_admin'): ?>
              <option value="super_admin">Super Admin</option>
              <?php endif; ?>
            </select>
          </div>

          <div id="edit-sections" style="display:none; margin-bottom:10px;">
            <label style="font-size:0.88rem;">Allowed sections:</label>
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:4px;">
              <?php foreach ($allSections as $s): ?>
                <label style="display:flex; align-items:center; gap:4px; font-size:0.88rem;">
                  <input type="checkbox" name="edit_sections[]" value="<?= $s ?>" class="edit-section-cb" /> <?= $s ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <button type="submit" class="filter-btn" style="margin-top:6px;">Save Changes</button>
        </form>
      </div>
    </div>
  </div>

  <script>
    function openEdit(id, username, role, sections) {
      document.getElementById('edit-id').value = id;
      document.getElementById('edit-title').textContent = 'Edit: ' + username;
      const roleSelect = document.getElementById('edit-role');
      if (roleSelect) roleSelect.value = '';
      // Pre-check sections
      document.querySelectorAll('.edit-section-cb').forEach(cb => {
        cb.checked = sections.includes(cb.value);
      });
      if (role === 'analyst') toggleEditSections(true);
      document.getElementById('edit-overlay').style.display = 'flex';
    }
    function closeEdit() {
      document.getElementById('edit-overlay').style.display = 'none';
    }
    document.getElementById('edit-overlay')?.addEventListener('click', function(e) {
      if (e.target === this) closeEdit();
    });
    function toggleAddSections() {
      const role = document.getElementById('add-role').value;
      document.getElementById('add-sections').style.display = role === 'analyst' ? 'block' : 'none';
    }
    function toggleEditSections(force) {
      const role = document.getElementById('edit-role')?.value;
      const show = force === true || role === 'analyst';
      document.getElementById('edit-sections').style.display = show ? 'block' : 'none';
    }
  </script>
</body>
</html>