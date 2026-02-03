<?php
session_start();

header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=UTF-8");

function h($v) {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["clear"])) {
  unset($_SESSION["favorite_ice_cream"]);
}

$current = $_SESSION["favorite_ice_cream"] ?? "";
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>PHP State - View Favorite Ice Cream</title>
  <style>
    html { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", system-ui, sans-serif; }
    body { margin: 0; max-width: 960px; margin: 0 auto; padding: 5rem 1.5rem 6rem; }
    .card { border: 1px solid #e5e5e5; border-radius: 12px; padding: 16px; }
    .big { font-size: 1.1rem; font-weight: 700; }
    .muted { opacity: 0.75; }
    button { width: 100%; padding: 10px; margin-top: 12px; }
    a { display:inline-block; margin-top: 16px; }
  </style>
</head>
<body>
  <h1>PHP State Demo</h1>
  <p class="muted">This page reads the saved value from your server-side session.</p>

  <div class="card">
    <?php if ($current !== ""): ?>
      <p class="big">Your favorite ice cream is: <?= h($current) ?></p>
    <?php else: ?>
      <p class="big">No favorite saved yet.</p>
      <p class="muted">Go set one on the other page.</p>
    <?php endif; ?>

    <form method="POST" action="/hw2/php/state-view-php.php">
      <button type="submit" name="clear" value="1">Clear</button>
    </form>

    <a href="/hw2/php/state-form-php.php">← Back to set page</a>
  </div>
</body>
</html>
