<?php
header("Cache-Control: no-cache");
header("Content-Type: application/json; charset=UTF-8");

$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$raw = file_get_contents("php://input");

$response = [
  "language" => "PHP",
  "method" => $method,
  "timestamp" => date('c'),
  "hostname" => gethostname(),
  "ip" => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
  "user_agent" => $_SERVER['HTTP_USER_AGENT'] ?? '',
  "content_type" => $_SERVER['CONTENT_TYPE'] ?? '',
  "query" => $_GET,
  "post" => $_POST,
  "raw_body" => $raw
];

echo json_encode($response, JSON_PRETTY_PRINT);
