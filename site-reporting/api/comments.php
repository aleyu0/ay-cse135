<?php
require_once __DIR__ . '/auth.php';
require_auth();

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Method not allowed"]);
    exit;
}

if (!has_permission('manage-comments')) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "Insufficient permissions"]);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
$page = preg_replace('/[^a-z0-9_]/', '', $data['page'] ?? '');
$comment = $data['comment'] ?? '';

if (!$page || strlen($comment) > 50000) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Invalid input"]);
    exit;
}

$dir = __DIR__ . '/../data';
if (!is_dir($dir)) mkdir($dir, 0755, true);
file_put_contents($dir . '/comments_' . $page . '.txt', $comment);

echo json_encode(["ok" => true]);