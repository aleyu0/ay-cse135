<?php
// /api/log.php
// Minimal ingest endpoint: accepts JSON and appends to a server-side file.

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://test.alessioyu.xyz'];

if (in_array($origin, $allowed, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Vary: Origin");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Max-Age: 600");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
  http_response_code(204);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["ok" => false, "error" => "Method not allowed"]);
  exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "Invalid JSON"]);
  exit;
}

// Add server timestamp
$data["_server_ts"] = round(microtime(true) * 1000);

// Append as NDJSON
$logDir = __DIR__ . "/../_ingest";
if (!is_dir($logDir)) {
  mkdir($logDir, 0755, true);
}
$logFile = $logDir . "/events.ndjson";

$ok = file_put_contents($logFile, json_encode($data) . "\n", FILE_APPEND | LOCK_EX);
if ($ok === false) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "Failed to write log"]);
  exit;
}
file_put_contents($logFile, json_encode($data) . "\n", FILE_APPEND | LOCK_EX);

echo json_encode(["ok" => true]);