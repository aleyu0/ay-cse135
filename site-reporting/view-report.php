<?php
require_once __DIR__ . '/api/auth.php';
require_auth();
require_permission('view-reports');

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT sr.*, u.username AS author
    FROM saved_reports sr
    LEFT JOIN users u ON u.id = sr.created_by
    WHERE sr.id = :id
");
$stmt->execute([':id' => $id]);
$rpt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rpt) {
    http_response_code(404);
    echo "Report not found.";
    exit;
}

// Section-level permission check for analysts
$user = get_auth_user();
if ($user['role'] === 'analyst') {
    $sourcePermMap = [
        'dashboard' => 'view-dashboard',
        'speed' => 'view-performance',
        'errors' => 'view-errors',
        'customers' => 'view-behavioral',
    ];
    if (isset($sourcePermMap[$rpt['source']]) && !has_permission($sourcePermMap[$rpt['source']])) {
        http_response_code(403);
        include __DIR__ . '/403.php';
        exit;
    }
}

$kpiData = is_string($rpt['kpi_data']) ? json_decode($rpt['kpi_data'], true) : ($rpt['kpi_data'] ?? []);
$chartImages = is_string($rpt['chart_images']) ? json_decode($rpt['chart_images'], true) : ($rpt['chart_images'] ?? []);
$comment = $rpt['comment'] ?? '';
$tableHtml = $rpt['table_html'] ?? '';

$validSources = [
    'dashboard' => 'Overview Report',
    'speed' => 'Speed & Web Vitals Report',
    'errors' => 'Error Report',
    'customers' => 'Customers & Behavior Report',
];
$sourceTitle = $validSources[$rpt['source']] ?? 'Report';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($rpt['title']) ?> | The Absolute Essential</title>
  <link rel="icon" href="assets/icons/absoluteessential-fav-teal.png" type="image/x-icon">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: "Helvetica Neue", Arial, sans-serif;
      color: #1a1a1a;
      padding: 40px;
      max-width: 1000px;
      margin: 0 auto;
      font-size: 14px;
      line-height: 1.5;
    }
    .toolbar {
      display: flex;
      gap: 10px;
      margin-bottom: 24px;
      align-items: center;
    }
    .toolbar button {
      display: inline-flex;
      align-items: center;
      padding: 8px 16px;
      border-radius: 6px;
      border: none;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-print { background: #1a1a1a; color: #fff; }
    .btn-back { background: #eee; color: #1a1a1a; }
    .report-header {
      border-bottom: 2px solid #1a1a1a;
      padding-bottom: 16px;
      margin-bottom: 24px;
    }
    .report-header h1 { font-size: 24px; margin-bottom: 4px; }
    .report-meta {
      color: #666; font-size: 13px;
      display: flex; gap: 20px; flex-wrap: wrap; margin-top: 6px;
    }
    .comment-box {
      background: #f8f8f8; border-radius: 8px; padding: 14px; margin-bottom: 24px;
    }
    .comment-box h3 { font-size: 14px; margin-bottom: 6px; color: #666; }
    .comment-box p { white-space: pre-wrap; margin: 0; color: #1a1a1a; }
    .kpi-grid {
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px;
    }
    .kpi-card {
      border: 1px solid #eee; border-radius: 8px; padding: 12px; text-align: center;
    }
    .kpi-label { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.03em; }
    .kpi-value { font-size: 22px; font-weight: 700; margin-top: 2px; }
    .chart-grid {
      display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;
    }
    .chart-slot {
      border: 1px solid #eee; border-radius: 8px; padding: 12px; text-align: center;
    }
    .chart-slot img { max-width: 100%; height: auto; }
    .report-section { margin-bottom: 28px; }
    .report-section h2 { font-size: 16px; margin-bottom: 12px; padding-bottom: 4px; border-bottom: 1px solid #ddd; }
    .report-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px; }
    .report-table th, .report-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #eee; }
    .report-table th { font-weight: 600; color: #666; font-size: 11px; text-transform: uppercase; }
    .report-footer {
      margin-top: 40px; padding-top: 16px; border-top: 1px solid #ddd;
      font-size: 11px; color: #999; display: flex; justify-content: space-between;
    }
    @media print {
      .toolbar { display: none !important; }
      body { padding: 20px; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button class="btn-print" onclick="window.print()">
      <img src="assets/icons/print.svg" alt="" width="16" height="16" style="filter:brightness(0) invert(1); margin-right:6px;" />
      Print / Save as PDF
    </button>
    <button class="btn-back" onclick="window.close(); if(!window.closed) location.href='reports.php';">
      <img src="assets/icons/dashboard.svg" alt="" width="16" height="16" style="filter:brightness(0); opacity:0.7; margin-right:6px;" />
      Back to Reports
    </button>
  </div>

  <div class="report-header">
    <h1><?= htmlspecialchars($rpt['title']) ?></h1>
    <div class="report-meta">
      <span>Date Range: <?= htmlspecialchars($rpt['date_from'] ?? '') ?> — <?= htmlspecialchars($rpt['date_to'] ?? '') ?></span>
      <span>Author: <?= htmlspecialchars($rpt['author'] ?? '—') ?></span>
      <span>Created: <?= date('m/d/Y h:i A', strtotime($rpt['created_at'])) ?></span>
    </div>
  </div>

  <?php if ($comment): ?>
  <div class="comment-box">
    <h3>Analyst Commentary</h3>
    <p><?= htmlspecialchars($comment) ?></p>
  </div>
  <?php endif; ?>

  <?php if ($kpiData && count($kpiData)): ?>
  <div class="kpi-grid">
    <?php foreach ($kpiData as $label => $value): ?>
    <div class="kpi-card">
      <div class="kpi-label"><?= htmlspecialchars($label) ?></div>
      <div class="kpi-value"><?= htmlspecialchars($value) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($chartImages && count($chartImages)): ?>
  <div class="chart-grid">
    <?php foreach ($chartImages as $id => $src): ?>
    <div class="chart-slot">
      <img src="<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($id) ?>" />
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($tableHtml): ?>
  <?= $tableHtml ?>
  <?php endif; ?>

  <div class="report-footer">
    <span>The Absolute Essential — Analytics Report</span>
    <span>Confidential</span>
  </div>
</body>
</html>