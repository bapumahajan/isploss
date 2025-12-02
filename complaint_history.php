<?php
// complaint_history.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_name('oss_portal');
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=()');

$timeout_duration = 900;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];
require_once 'includes/db.php';
$complaint_id = intval($_GET['id'] ?? 0);

// Fetch complaint info
$stmtC = $pdo->prepare("SELECT organization_name, circuit_id, docket_no FROM complaints WHERE id = ?");
$stmtC->execute([$complaint_id]);
$complaint = $stmtC->fetch(PDO::FETCH_ASSOC);

// Error handling: complaint not found
if (!$complaint) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Complaint Not Found</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #f6f8fa; font-family: 'Inter', Arial, sans-serif; }
        </style>
    </head>
    <body>
    <div class="container py-5">
        <div class="alert alert-danger mt-4 shadow-sm">
            <strong>Complaint not found.</strong>
        </div>
        <div class="text-center mt-3">
            <a href="javascript:window.close();" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-circle"></i> Close Window
            </a>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Fetch history for this complaint
$stmt = $pdo->prepare("SELECT * FROM complaint_history WHERE rid = ? ORDER BY id DESC");
$stmt->execute([$complaint_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Complaint History</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Google Fonts for modern look -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Fira+Mono&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="favicon.png">
    <style>
        body {
            background: #f6f8fa;
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            font-size: 14px;
            color: #222;
        }
        .complaint-card {
            border-radius: 14px;
            background: linear-gradient(135deg, #f8fafc 65%, #e0e7ef 100%);
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            padding: 1.2rem 1.5rem;
        }
        .complaint-label {
            color: #495057;
            font-weight: 600;
            font-size: 13px;
        }
        .complaint-data {
            color: #0d6efd;
            font-family: 'Fira Mono', monospace;
            font-size: 1.05rem;
            margin-left: 0.2em;
        }
        .history-title {
            font-size: 1.15rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #0d6efd;
        }
        .history-table th, .history-table td {
            font-size: 13px;
            vertical-align: middle;
        }
        .history-table th {
            background: #e9f0fa;
            color: #2a3b4d;
            font-weight: 600;
            border-bottom: 2px solid #b6c6e3;
        }
        .history-table td {
            background: #fff;
        }
        .history-table tbody tr:hover {
            background: #f0f6ff;
        }
        .badge {
            font-size: 12px;
            padding: 0.3em 0.7em;
            border-radius: 1em;
            font-weight: 500;
            letter-spacing: 0.01em;
        }
        .no-history {
            text-align: center;
            color: #888;
            font-style: italic;
        }
        .bi {
            font-size: 1.1em;
            vertical-align: -0.1em;
            margin-right: 0.2em;
            color: #6c757d;
        }
        .btn-outline-secondary {
            font-size: 13px;
            border-radius: 0.3em;
        }
        .breadcrumb {
            background: none;
            font-size: 13px;
            margin-bottom: 1.2rem;
        }
        @media (max-width: 768px) {
            .complaint-card { padding: 1rem 0.7rem; }
            .history-table th, .history-table td { font-size: 12px; }
        }
    </style>
</head>
<body>
<div class="container py-4">
    <!-- Breadcrumb / Back -->
    <nav aria-label="breadcrumb">
            </nav>
    <!-- Complaint Info Card -->
    <div class="complaint-card mb-4">
        <div class="row align-items-center gy-2">
            <div class="col-md-4">
                <span class="complaint-label"><i class="bi bi-receipt"></i> Docket No:</span>
                <span class="complaint-data"><?= htmlspecialchars($complaint['docket_no'] ?? '-') ?></span>
            </div>
            <div class="col-md-4">
                <span class="complaint-label"><i class="bi bi-building"></i> Organization:</span>
                <span class="complaint-data"><?= htmlspecialchars($complaint['organization_name'] ?? '-') ?></span>
            </div>
            <div class="col-md-4">
                <span class="complaint-label"><i class="bi bi-diagram-3"></i> Circuit ID:</span>
                <span class="complaint-data"><?= htmlspecialchars($complaint['circuit_id'] ?? '-') ?></span>
            </div>
        </div>
    </div>
    <!-- Complaint History Table -->
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white border-bottom-0 history-title">
            <i class="bi bi-clock-history"></i> Complaint History
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-bordered history-table mb-0">
                <thead>
                    <tr>
                        <th scope="col"><i class="bi bi-flag"></i> Status</th>
                        <th scope="col"><i class="bi bi-chat-left-text"></i> Remarks</th>
                        <th scope="col"><i class="bi bi-clock"></i> Next Update</th>
                        <th scope="col"><i class="bi bi-telephone"></i> Customer Call</th>
                        <th scope="col"><i class="bi bi-bell"></i> Alarm Status</th>
                        <th scope="col"><i class="bi bi-person"></i> Updated By</th>
                        <th scope="col"><i class="bi bi-calendar-event"></i> Update Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($history): ?>
                    <?php foreach($history as $row): ?>
                    <tr>
                        <td>
                            <?php
                            $status = strtolower($row['incident_status'] ?? '');
                            $badge = 'secondary'; $icon = 'bi-dot';
                            if($status == 'open') { $badge = 'success'; $icon = 'bi-check-circle'; }
                            elseif($status == 'hold') { $badge = 'warning'; $icon = 'bi-pause-circle'; }
                            elseif($status == 'pending with customer') { $badge = 'info'; $icon = 'bi-hourglass-split'; }
                            elseif($status == 'closed') { $badge = 'secondary'; $icon = 'bi-check2-square'; }
                            ?>
                            <span class="badge bg-<?= $badge ?>" title="<?= ucfirst($status) ?>">
                                <i class="bi <?= $icon ?>"></i>
                                <?= htmlspecialchars($row['incident_status'] ?? '') ?>
                            </span>
                        </td>
                        <td><?= nl2br(htmlspecialchars($row['remarks'] ?? '')) ?></td>
                        <td>
                            <?= $row['next_update_time'] && $row['next_update_time'] != '0000-00-00 00:00:00'
                                ? date('d-M-Y H:i', strtotime($row['next_update_time']))
                                : '-' ?>
                        </td>
                        <td><?= htmlspecialchars($row['customer_communications_call'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['alarm_status'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['updated_by'] ?? '-') ?></td>
                        <td>
                            <?= $row['updated_time'] && $row['updated_time'] != '0000-00-00 00:00:00'
                                ? date('d-M-Y H:i', strtotime($row['updated_time']))
                                : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="no-history">No complaint history found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <a href="javascript:window.close();" class="btn btn-outline-secondary btn-sm" aria-label="Close Window">
            <i class="bi bi-x-circle"></i> Close Window
        </a>
    </div>
    <footer class="text-center text-muted mt-4" style="font-size:12px;">
        &copy; <?= date('Y') ?> Your Company Name. All rights reserved.
    </footer>
</div>
</body>
</html>