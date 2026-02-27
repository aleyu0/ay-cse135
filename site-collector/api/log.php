<?php
// /api/log.php
// Ingest endpoint: accepts JSON telemetry and inserts into PostgreSQL (credentials via Apache env vars).

// CORS (only allow the test site to post here)
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
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

// Read body + size cap
$raw = file_get_contents("php://input");
if (strlen($raw) > 100000) { // 100 KB cap
  http_response_code(413);
  echo json_encode(["ok" => false, "error" => "Payload too large"]);
  exit;
}

// Parse JSON (note: if you POST text/plain, this still works because body is JSON text)
$data = json_decode($raw, true);
if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "Invalid JSON"]);
  exit;
}

// Add server timestamp
$data["_server_ts"] = round(microtime(true) * 1000);

// --- DB credentials from env vars (Apache SetEnv) ---
$dbHost = getenv("CSE135_DB_HOST") ?: "127.0.0.1";
$dbName = getenv("CSE135_DB_NAME") ?: "cse135_analytics";
$dbUser = getenv("CSE135_DB_USER") ?: "cse135_user";
$dbPass = getenv("CSE135_DB_PASS") ?: "";

if ($dbPass === "") {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "DB password env var missing"]);
  exit;
}

// --- DB insert ---
$dsn = "pgsql:host=$dbHost;port=5432;dbname=$dbName";
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "DB connect failed"]);
  exit;
}

$sessionId = $data["sessionId"] ?? null;
$eventType = $data["type"] ?? null;
$page      = $data["page"] ?? null;
$clientTs  = $data["ts"] ?? null;

try {
  $stmt = $pdo->prepare("
    INSERT INTO events (session_id, event_type, page, client_ts, payload)
    VALUES (:session_id, :event_type, :page, :client_ts, :payload::jsonb)
  ");
  $stmt->execute([
    ":session_id" => $sessionId,
    ":event_type" => $eventType,
    ":page"       => $page,
    ":client_ts"  => $clientTs,
    ":payload"    => json_encode($data),
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "DB insert failed"]);
  exit;
}

echo json_encode(["ok" => true]);