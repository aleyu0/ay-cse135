<?php
// ── Auth Configuration ──────────────────────────
// Temporary hardcoded credentials (no signup needed)
define('AUTH_USER', 'admin');
define('AUTH_PASS', 'admin135');

session_start();

/**
 * Check if the current session is authenticated.
 * Called at the top of every protected page.
 * Redirects to login if not authenticated.
 */
function require_auth() {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        header('Location: /site-reporting/index.html');
        exit;
    }
}