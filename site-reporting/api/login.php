<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html');
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if ($username === AUTH_USER && $password === AUTH_PASS) {
    $_SESSION['authenticated'] = true;
    $_SESSION['user'] = $username;
    header('Location: ../dashboard.php');
    exit;
} else {
    // Redirect back to login with error flag
    header('Location: ../index.html?error=1');
    exit;
}