<?php
session_start();

header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=UTF-8");

function h($v) {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$choices = ["Vanilla", "Chocolate", "Strawberry", "Pistachio", "Mint Chip", "Cookies & Cream"];
$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (isset($_POST["clear"])) {
    unset($_SESSION["favorite_ice_cream"]);
    $msg = "Cleared.";
  } else {
    $fav = $_POST["favorite"] ?? "";
    if ($fav !== "") {
      $_SESSION["favorite_ice_cream"] = $fav;
      $msg = "Saved.";
    } else {
      $msg = "Pick one first.";
    }
  }
}

$current = $_SESSION["favorite_ice_cream"] ?? "";
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>PHP State - Set Favorite Ice Cream</title>
  <style>
    html { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", system-ui, sans-serif; }
    body { margin: 0; max-width: 960px; margin: 0 auto; padding: 5rem 1.5rem 6rem; }
    .card { border: 1px solid #e5e5e5; border-radius: 12px; padding: 16px; }
    label { display:block; margin-top: 12px; font-weight: 600; }
    select, button { width: 100%; padding: 10px; margin-top: 6px; }
    .row { display:flex; gap: 12px; margin-top: 12px; }
    .row button { width: 100%; }
    .muted { opacity: 0.75; }
    .msg { margin: 12px 0 0; font-weight: 600; }
    a { display:inline-block; margin-top: 16px; }
  </style>
</head>
<body>
  <h1>PHP State Demo</h1>
  <p class="muted">This saves your favorite ice cream on the server using a session.</p>

  <div class="card">
    <form method="POST" action="/hw2/php/state-form-php.php">
      <label for="favorite">Favorite ice cream</label>
      <select id="favorite" name="favorite">
        <option value="">-- choose one --</option>
        <?php foreach ($choices as $c): ?>
          <option value="<?= h($c) ?>" <?= ($current === $c ? "selected" : "") ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="row">
        <button type="submit" name="save" value="1">Save</button>
        <button type="submit" name="clear" value="1">Clear</button>
      </div>

      <?php if ($msg !== ""): ?>
        <div class="msg"><?= h($msg) ?></div>
      <?php endif; ?>
    </form>

    <a href="/hw2/php/state-view-php.php">Go to view page →</a>
  </div>
</body>
</html>
