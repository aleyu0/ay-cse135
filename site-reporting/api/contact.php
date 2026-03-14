<?php
require_once __DIR__ . '/auth.php';
require_auth();

header("Content-Type: application/json; charset=UTF-8");

if (!has_permission('view-contact-submissions') && !has_permission('view-behavioral')) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "Forbidden"]);
    exit;
}

$pdo = get_db();
$pdo->exec("SET timezone = 'America/Los_Angeles'");

$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$limit = min((int)($_GET['limit'] ?? 100), 500);

$where = [];
$params = [];
if ($from) { $where[] = "created_at >= :from_dt"; $params[':from_dt'] = $from . ' 00:00:00'; }
if ($to)   { $where[] = "created_at < :to_dt";    $params[':to_dt']   = $to . ' 23:59:59'; }

$sql = "SELECT id, name, email, item, priority, justification, ip, created_at FROM contact_submissions";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY id DESC LIMIT $limit";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));