<?php
header("Cache-Control: no-cache");
header("Content-Type: application/json; charset=UTF-8");

$response = [
  "message" => "Hello JSON World",
  "author" => "Alessio",
  "language" => "PHP",
  "generated_at" => date('c'),
  "ip" => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];

echo json_encode($response, JSON_PRETTY_PRINT);