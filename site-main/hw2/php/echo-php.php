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
$post = $_POST
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
            <th>Method</th>
            <td><?= h($method) ?></td>
        </tr>
        <tr>
            <th>Timestamp</th>
            <td><?= h($timestamp) ?></td>
        </tr>
        <tr>
            <th>IP</th>
            <td><?= h($ip) ?></td>
        </tr>
        <tr>
            <th>User Agent</th>
            <td><?= h($user_agent) ?></td>
        </tr>
        <tr>
            <th>Content-Type</th>
            <td><?= h($content_type) ?></td>
        </tr>
        <tr>
            <th>Hostname</th>
            <td><?= h($hostname) ?></td>
        </tr>
        <tr>
            <th>Get Query</th>
            <td><?= h($query) ?></td>
        </tr>
        <tr>
            <th>Post</th>
            <td><?= h($post) ?></td>
        </tr>
        <tr>
            <th>Raw content</th>
            <td><?= h($raw) ?></td>
        </tr>
    </table>
    <a href="javascript:history.back()">Go Back</a>
</body>
</html>
