<?php
require_once __DIR__ . '/auth.php';
require_auth();

header("Content-Type: application/json; charset=UTF-8");
$pdo = get_db();

$method = $_SERVER['REQUEST_METHOD'];

// GET — list or fetch saved reports (viewers can access)
if ($method === 'GET') {
    if (!has_permission('view-reports')) {
        http_response_code(403);
        echo json_encode(["ok" => false, "error" => "Forbidden"]);
        exit;
    }

    $id = $_GET['id'] ?? null;

    if ($id) {
        $stmt = $pdo->prepare("
            SELECT sr.*, u.username AS author
            FROM saved_reports sr
            LEFT JOIN users u ON u.id = sr.created_by
            WHERE sr.id = :id
        ");
        $stmt->execute([':id' => (int)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(["ok" => false, "error" => "Not found"]);
            exit;
        }

        // Check section access for analysts
        $user = get_auth_user();
        if ($user['role'] === 'analyst') {
            $sourcePermMap = [
                'dashboard' => 'view-dashboard',
                'speed' => 'view-performance',
                'errors' => 'view-errors',
                'customers' => 'view-behavioral',
            ];
            if (isset($sourcePermMap[$row['source']]) && !has_permission($sourcePermMap[$row['source']])) {
                http_response_code(403);
                echo json_encode(["ok" => false, "error" => "Forbidden"]);
                exit;
            }
        }

        echo json_encode($row);
    } else {
        // List all reports the user can see
        $user = get_auth_user();
        $sql = "SELECT sr.id, sr.source, sr.title, sr.date_from, sr.date_to, sr.created_at, u.username AS author
                FROM saved_reports sr
                LEFT JOIN users u ON u.id = sr.created_by
                ORDER BY sr.created_at DESC
                LIMIT 100";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // Filter by section access for analysts
        if ($user['role'] === 'analyst') {
            $sections = json_decode($user['allowed_sections'] ?? '[]', true) ?: [];
            $sectionSourceMap = [
                'performance' => 'speed',
                'errors' => 'errors',
                'behavioral' => 'customers',
                'logs' => 'dashboard',
            ];
            $allowedSources = ['dashboard']; // everyone sees dashboard reports
            foreach ($sections as $s) {
                if (isset($sectionSourceMap[$s])) $allowedSources[] = $sectionSourceMap[$s];
            }
            $rows = array_values(array_filter($rows, fn($r) => in_array($r['source'], $allowedSources)));
        }

        echo json_encode($rows);
    }
    exit;
}

// POST — save a new report (requires create-reports permission)
if ($method === 'POST') {
    if (!has_permission('create-reports')) {
        http_response_code(403);
        echo json_encode(["ok" => false, "error" => "Forbidden"]);
        exit;
    }

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["ok" => false, "error" => "Invalid JSON"]);
        exit;
    }

    $source = preg_replace('/[^a-z]/', '', $data['source'] ?? '');
    $title = trim($data['title'] ?? '');
    $dateFrom = $data['date_from'] ?? null;
    $dateTo = $data['date_to'] ?? null;
    $comment = $data['comment'] ?? '';
    $kpiData = $data['kpi_data'] ?? null;
    $chartImages = $data['chart_images'] ?? null;
    $tableHtml = $data['table_html'] ?? '';

    if (!$source || !$title) {
        http_response_code(400);
        echo json_encode(["ok" => false, "error" => "Source and title are required"]);
        exit;
    }

    $user = get_auth_user();
    $stmt = $pdo->prepare("
        INSERT INTO saved_reports (source, title, date_from, date_to, comment, kpi_data, chart_images, table_html, created_by)
        VALUES (:source, :title, :from, :to, :comment, :kpi::jsonb, :charts::jsonb, :table_html, :uid)
        RETURNING id
    ");
    $stmt->execute([
        ':source' => $source,
        ':title' => $title,
        ':from' => $dateFrom,
        ':to' => $dateTo,
        ':comment' => $comment,
        ':kpi' => json_encode($kpiData),
        ':charts' => json_encode($chartImages),
        ':table_html' => $tableHtml,
        ':uid' => $user['id'],
    ]);
    $newId = $stmt->fetchColumn();

    http_response_code(201);
    echo json_encode(["ok" => true, "id" => $newId]);
    exit;
}

// DELETE — remove a report (requires create-reports)
if ($method === 'DELETE') {
    if (!has_permission('create-reports')) {
        http_response_code(403);
        echo json_encode(["ok" => false, "error" => "Forbidden"]);
        exit;
    }
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(["ok" => false, "error" => "ID required"]);
        exit;
    }
    $pdo->prepare("DELETE FROM saved_reports WHERE id = :id")->execute([':id' => (int)$id]);
    echo json_encode(["ok" => true]);
    exit;
}

http_response_code(405);
echo json_encode(["ok" => false, "error" => "Method not allowed"]);