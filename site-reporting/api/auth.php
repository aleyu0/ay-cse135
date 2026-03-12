<?php
// authenticate reporting site access
session_start();

function get_db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $host = getenv("CSE135_DB_HOST") ?: "127.0.0.1";
    $name = getenv("CSE135_DB_NAME") ?: "cse135_analytics";
    $user = getenv("CSE135_DB_USER") ?: "cse135_user";
    $pass = getenv("CSE135_DB_PASS") ?: "";
    $pdo = new PDO("pgsql:host=$host;port=5432;dbname=$name", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    return $pdo;
}

// Seed default users if none exist.
function seed_default_users() {
    $pdo = get_db();
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ((int)$count > 0) return;

    $defaults = [
        ['username' => 'superadmin', 'password' => 'super135!',   'role' => 'super_admin'],
        ['username' => 'admin',      'password' => 'admin135',    'role' => 'admin'],
        ['username' => 'sam',        'password' => 'analyst135',  'role' => 'analyst', 'sections' => ['performance', 'errors']],
        ['username' => 'sally',      'password' => 'analyst135',  'role' => 'analyst', 'sections' => ['performance', 'behavioral']],
        ['username' => 'grader',     'password' => 'grader135',   'role' => 'viewer'],
    ];

    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, allowed_sections) VALUES (:u, :p, :r, :s)");
    foreach ($defaults as $u) {
        $stmt->execute([
            ':u' => $u['username'],
            ':p' => password_hash($u['password'], PASSWORD_BCRYPT),
            ':r' => $u['role'],
            ':s' => isset($u['sections']) ? json_encode($u['sections']) : null,
        ]);
    }
}

// Authenticate a user by username and password. Returns user row or false.
function authenticate($username, $password) {
    $pdo = get_db();
    seed_default_users();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    return $user;
}

// Create a new session for a user and return the session token.
function create_session($userId) {
    $pdo = get_db();
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 7200); // 2 hours

    // Clean up expired sessions for this user
    $pdo->prepare("DELETE FROM sessions WHERE user_id = :uid AND expires_at < NOW()")
        ->execute([':uid' => $userId]);

    $stmt = $pdo->prepare("INSERT INTO sessions (user_id, token, expires_at) VALUES (:uid, :token, :exp)");
    $stmt->execute([':uid' => $userId, ':token' => $token, ':exp' => $expires]);

    return $token;
}

// Get the current logged-in user based on the session. Returns user row or null.
function get_auth_user() {
    if (!isset($_SESSION['user_id'])) return null;

    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Require authentication. Redirects to login if not authenticated.
function require_auth() {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        header('Location: ../index.html');
        exit;
    }
}

// Check if the current user has a specific permission.
function has_permission($permName) {
    $user = get_auth_user();
    if (!$user) return false;

    // Super admin and admin bypass section checks
    if (in_array($user['role'], ['super_admin', 'admin'])) {
        $pdo = get_db();
        $stmt = $pdo->prepare("
            SELECT 1 FROM role_permissions rp
            JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role = :role AND p.name = :perm
            LIMIT 1
        ");
        $stmt->execute([':role' => $user['role'], ':perm' => $permName]);
        return (bool) $stmt->fetchColumn();
    }

    // For analysts, check section scoping on view permissions
    if ($user['role'] === 'analyst') {
        $sectionMap = [
            'view-performance' => 'performance',
            'view-behavioral'  => 'behavioral',
            'view-errors'      => 'errors',
            'view-logs'        => 'logs',
        ];

        // If this permission maps to a section, check allowed_sections
        if (isset($sectionMap[$permName])) {
            $sections = json_decode($user['allowed_sections'] ?? '[]', true) ?: [];
            if (!in_array($sectionMap[$permName], $sections)) {
                return false;
            }
        }
    }

    // Standard role-permission check
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT 1 FROM role_permissions rp
        JOIN permissions p ON p.id = rp.permission_id
        WHERE rp.role = :role AND p.name = :perm
        LIMIT 1
    ");
    $stmt->execute([':role' => $user['role'], ':perm' => $permName]);
    return (bool) $stmt->fetchColumn();
}

// Check if the current user has access to a specific section (for analysts).
function has_section_access($section) {
    $user = get_auth_user();
    if (!$user) return false;

    // Super admin and admin see everything
    if (in_array($user['role'], ['super_admin', 'admin'])) return true;

    // Analysts are scoped
    if ($user['role'] === 'analyst') {
        $sections = json_decode($user['allowed_sections'] ?? '[]', true);
        return in_array($section, $sections ?: []);
    }

    // Viewers only see reports (handled by permission check)
    return false;
}

// Require a specific permission, or show 403 if not allowed.
function require_permission($permName) {
    if (!has_permission($permName)) {
        http_response_code(403);
        include __DIR__ . '/../403.php';
        exit;
    }
}