<?php
// ============================================================
// includes/header.php — Shared HTML head + navigation
// $pageTitle must be set by the including file before this include.
// ============================================================
$pageTitle = $pageTitle ?? 'MidnightBuilds';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — MidnightBuilds</title>
    <link rel="stylesheet" href="/midnightbuilds/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a href="/midnightbuilds/" class="logo">
            <span class="logo-icon">🌙</span>
            <span class="logo-text">MidnightBuilds</span>
        </a>
        <nav class="main-nav" id="main-nav">
            <a href="/midnightbuilds/"        class="nav-link">Home</a>
            <a href="/midnightbuilds/ideas.php"  class="nav-link">Browse Ideas</a>
            <a href="/midnightbuilds/submit.php" class="nav-link nav-cta">+ Submit Idea</a>
            <a href="/midnightbuilds/about.php"  class="nav-link">About</a>
        </nav>
        <button class="hamburger" id="hamburger" aria-label="Toggle navigation">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>
<main class="site-main">
