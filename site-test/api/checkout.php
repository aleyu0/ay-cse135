<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Method not allowed"]);
    exit;
}

$dbHost = getenv("CSE135_DB_HOST") ?: "127.0.0.1";
$dbName = getenv("CSE135_DB_NAME") ?: "cse135_analytics";
$dbUser = getenv("CSE135_DB_USER") ?: "cse135_user";
$dbPass = getenv("CSE135_DB_PASS") ?: "";

try {
    $pdo = new PDO("pgsql:host=$dbHost;port=5432;dbname=$dbName", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "DB connection failed"]);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Invalid JSON"]);
    exit;
}

$sessionId = trim($data['session_id'] ?? '');
$pid       = trim($data['pid'] ?? '');
$items     = $data['items'] ?? [];
$total     = floatval($data['total'] ?? 0);

// Validation
if ($pid === '' || !preg_match('/^[A-Za-z0-9]{1,20}$/', $pid)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Valid PID is required (alphanumeric, max 20 chars)"]);
    exit;
}
if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Cart is empty"]);
    exit;
}
if ($total <= 0 || $total > 10000) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Invalid total"]);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO orders (session_id, pid, items, total, status)
    VALUES (:session_id, :pid, :items::jsonb, :total, 'completed')
    RETURNING id
");
$stmt->execute([
    ":session_id" => $sessionId ?: null,
    ":pid"        => $pid,
    ":items"      => json_encode($items),
    ":total"      => $total,
]);
$newId = $stmt->fetchColumn();

http_response_code(201);
echo json_encode(["ok" => true, "id" => $newId]);