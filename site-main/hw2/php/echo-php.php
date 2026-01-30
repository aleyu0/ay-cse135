<?php
header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=UTF-8");

function h($v) {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
$timestamp = date('c');
$hostname = gethostname();
$query = $_GET;
$post = $_POST;
$raw = file_get_contents("php://input");
?>

<html>
<head>
    <title>GET PHP</title>
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
    <p>Here are the details of your request.</p>
    <table>
        <tr>
            <td>Method</td>
            <td><?= h($method) ?></td>
        </tr>
        <tr>
            <td>Timestamp</td>
            <td><?= h($timestamp) ?></td>
        </tr>
        <tr>
            <td>IP</td>
            <td><?= h($ip) ?></td>
        </tr>
        <tr>
            <td>User Agent</td>
            <td><?= h($user_agent) ?></td>
        </tr>
        <tr>
            <td>Content-Type</td>
            <td><?= h($content_type) ?></td>
        </tr>
        <tr>
            <td>Hostname</td>
            <td><?= h($hostname) ?></td>
        </tr>
        <tr>
            <td>Get Query</td>
            <td><?= h($query) ?></td>
        </tr>
        <tr>
            <td>Post</td>
            <td><?= h($post) ?></td>
        </tr>
        <tr>
            <td>Raw content</td>
            <td><?= h($raw) ?></td>
        </tr>
    </table>
    <a href="javascript:history.back()">Go Back</a>
</body>
</html>
