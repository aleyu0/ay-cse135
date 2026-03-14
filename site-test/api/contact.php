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

// DB connection
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

// rate limiting max 5 submissions per IP per hour
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_submissions WHERE ip = :ip AND created_at > NOW() - INTERVAL '1 hour'");
$stmt->execute([":ip" => $ip]);
if ((int) $stmt->fetchColumn() >= 5) {
    http_response_code(429);
    echo json_encode(["ok" => false, "error" => "Too many submissions. Please try again later."]);
    exit;
}

// Parse and validate
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Invalid JSON"]);
    exit;
}

$name          = trim($data['name'] ?? '');
$email         = trim($data['email'] ?? '');
$item          = trim($data['item'] ?? '');
$priority      = trim($data['priority'] ?? '');
$justification = trim($data['justification'] ?? '');

// validation
$errors = [];
if ($name === '' || strlen($name) > 255)          $errors[] = "Name is required (max 255 chars)";
if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = "Valid email is required";
if ($item === '' || strlen($item) > 255)           $errors[] = "Item is required (max 255 chars)";
$normalPriority = str_replace(["\u{2019}", "\u{2018}"], "'", $priority);
if (!in_array($normalPriority, ['Minor inconvenience', 'Major inconvenience', "I can't survive"])) {
    $errors[] = "Invalid priority";
}
if ($justification === '' || strlen($justification) > 5000) $errors[] = "Justification is required (max 5000 chars)";

if ($errors) {
    http_response_code(400);
    echo json_encode(["ok" => false, "errors" => $errors]);
    exit;
}

// insert
$stmt = $pdo->prepare("
    INSERT INTO contact_submissions (name, email, item, priority, justification, ip)
    VALUES (:name, :email, :item, :priority, :justification, :ip)
    RETURNING id
");
$stmt->execute([
    ":name"          => $name,
    ":email"         => $email,
    ":item"          => $item,
    ":priority"      => $priority,
    ":justification" => $justification,
    ":ip"            => $ip,
]);
$newId = $stmt->fetchColumn();

http_response_code(201);
echo json_encode(["ok" => true, "id" => $newId]);