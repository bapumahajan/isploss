<?php
// complaints_history.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['rid'])) {
    die('Invalid request: Missing Complaint ID.');
}

$rid = intval($_GET['rid']);

$stmt = $pdo->prepare("SELECT * FROM complaint_history WHERE rid = ? ORDER BY updated_time DESC");
$stmt->execute([$rid]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Complaint History</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container py-3">
    <h4 class="mb-3">Complaint History (RID: <?= htmlspecialchars($rid) ?>)</h4>

    <?php if ($history): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Next Update Time</th>
                        <th>Customer Communications</th>
                        <th>Fault</th>
                        <th>RFO</th>
                        <th>Updated By</th>
                        <th>Updated Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $entry): ?>
                        <tr>
                            <td><?= htmlspecialchars($entry['incident_status']) ?></td>
                            <td style="white-space:pre-wrap;max-width:200px;"> <?= htmlspecialchars($entry['remarks']) ?></td>
                            <td><?= htmlspecialchars($entry['next_update_time']) ?></td>
                            <td style="white-space:pre-wrap;max-width:200px;"> <?= htmlspecialchars($entry['customer_communications_call']) ?></td>
                            <td style="white-space:pre-wrap;max-width:200px;"> <?= htmlspecialchars($entry['Fault']) ?></td>
                            <td style="white-space:pre-wrap;max-width:200px;"> <?= htmlspecialchars($entry['RFO']) ?></td>
                            <td><?= htmlspecialchars($entry['updated_by']) ?></td>
                            <td><?= htmlspecialchars($entry['updated_time']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">No history found for this complaint.</div>
    <?php endif; ?>

    <a href="complaints_dashboard.php" class="btn btn-secondary btn-sm">Back to Dashboard</a>
</div>
</body>
</html>