<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$user = authenticate($username, $password);

if ($user) {
    $_SESSION['authenticated'] = true;
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['token'] = create_session($user['id']);
    header('Location: ../dashboard.php');
    exit;
} else {
    header('Location: ../index.html?error=1');
    exit;
}