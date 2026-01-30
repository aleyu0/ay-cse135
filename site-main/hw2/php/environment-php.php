<?php
$skip = ['AUTH_TYPE','PHP_AUTH_USER', 'PHP_AUTH_PW', 'HTTP_AUTHORIZATION'];

header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=UTF-8");

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

echo "<!doctype html><html><head><title>Environment (PHP)</title></head><body>";
echo "<h1>Environment Variables (PHP)</h1><hr/>";

echo "<h2>\$_SERVER</h2><pre>";
ksort($_SERVER);
foreach ($_SERVER as $k => $v) {
  if (in_array($k, $skip, true)) continue;
  echo h($k) . " = " . h($v) . "\n";
}
echo "</pre>";

echo "</body></html>";