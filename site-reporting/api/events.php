<?php
header("Content-Type: application/json; charset=UTF-8");

// DB creds from Apache env
$dbHost = getenv("CSE135_DB_HOST") ?: "127.0.0.1";
$dbName = getenv("CSE135_DB_NAME") ?: "cse135_analytics";
$dbUser = getenv("CSE135_DB_USER") ?: "cse135_user";
$dbPass = getenv("CSE135_DB_PASS") ?: "";

$dsn = "pgsql:host=$dbHost;port=5432;dbname=$dbName";
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>"DB connect failed"]);
  exit;
}

$path = $_SERVER["PATH_INFO"] ?? "";
$id = null;
if ($path !== "") {
  $parts = array_values(array_filter(explode("/", $path)));
  if (count($parts) >= 1 && ctype_digit($parts[0])) $id = (int)$parts[0];
}

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
  if ($id === null) {
    $type = $_GET["type"] ?? null;
    $session = $_GET["session"] ?? null;
    $limit = $_GET["limit"] ?? 50;
    $limit = (ctype_digit((string)$limit) ? min((int)$limit, 500) : 50);

    $where = [];
    $params = [];

    if ($type) { $where[] = "event_type = :type"; $params[":type"] = $type; }
    if ($session) { $where[] = "session_id = :session"; $params[":session"] = $session; }

    $sql = "SELECT id, received_at, session_id, event_type, page, client_ts, payload
            FROM events";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY id DESC LIMIT $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
  } else {
    $stmt = $pdo->prepare("SELECT id, received_at, session_id, event_type, page, client_ts, payload FROM events WHERE id = :id");
    $stmt->execute([":id"=>$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { http_response_code(404); echo json_encode(["ok"=>false,"error"=>"Not found"]); exit; }
    echo json_encode($row);
    exit;
  }
}

if ($method === "DELETE") {
  if ($id === null) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"ID required"]); exit; }
  $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
  $stmt->execute([":id"=>$id]);
  echo json_encode(["ok"=>true,"deleted"=>$stmt->rowCount()]);
  exit;
}

if ($method === "POST") {
  if ($id !== null) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"POST must not include ID"]); exit; }
  $raw = file_get_contents("php://input");
  $data = json_decode($raw, true);
  if (!is_array($data)) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"Invalid JSON"]); exit; }

  $stmt = $pdo->prepare("
    INSERT INTO events (session_id, event_type, page, client_ts, payload)
    VALUES (:session_id, :event_type, :page, :client_ts, :payload::jsonb)
    RETURNING id
  ");
  $stmt->execute([
    ":session_id" => $data["session_id"] ?? null,
    ":event_type" => $data["event_type"] ?? null,
    ":page"       => $data["page"] ?? null,
    ":client_ts"  => $data["client_ts"] ?? null,
    ":payload"    => json_encode($data["payload"] ?? $data),
  ]);
  $newId = $stmt->fetchColumn();
  http_response_code(201);
  echo json_encode(["ok"=>true,"id"=>$newId]);
  exit;
}

if ($method === "PUT") {
  if ($id === null) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"ID required"]); exit; }
  $raw = file_get_contents("php://input");
  $data = json_decode($raw, true);
  if (!is_array($data)) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"Invalid JSON"]); exit; }

  $stmt = $pdo->prepare("
    UPDATE events
    SET session_id = :session_id,
        event_type = :event_type,
        page       = :page,
        client_ts  = :client_ts,
        payload    = :payload::jsonb
    WHERE id = :id
  ");
  $stmt->execute([
    ":session_id" => $data["session_id"] ?? null,
    ":event_type" => $data["event_type"] ?? null,
    ":page"       => $data["page"] ?? null,
    ":client_ts"  => $data["client_ts"] ?? null,
    ":payload"    => json_encode($data["payload"] ?? $data),
    ":id"         => $id,
  ]);
  if ($stmt->rowCount() === 0) { http_response_code(404); echo json_encode(["ok"=>false,"error"=>"Not found"]); exit; }
  echo json_encode(["ok"=>true,"updated"=>$id]);
  exit;
}

http_response_code(405);
echo json_encode(["ok"=>false,"error"=>"Method not allowed"]);