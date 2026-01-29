<?php
header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=UTF-8");

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$date = date('c');
?>

<html>
<head>
    <title>Hello HTML (PHP)</title>
    <style>
        html{
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text",
                system-ui, sans-serif;
            font-style: normal;
        }
        body{
            margin: 0;
            max-width: 960px;
            margin: 0 auto;
            padding: 5rem 1.5rem 6rem;
        }        
    </style>
</head>
<body>
    <h1>Hi there!</h1>
    <p>This page was written by Alessio in PHP for CSE 135, homework 2.</p>
    <p>Generated at: <?= htmlspecialchars($date) ?></p>
    <p>Your IP: <?= htmlspecialchars($ip) ?></p>
    <a href="javascript:history.back()">Go Back</a>
</body>
</html>