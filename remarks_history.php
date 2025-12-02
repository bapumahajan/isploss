<?php
// remarks_history.php

session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    echo "Access denied.";
    exit;
}

$sr_no = $_GET['sr_no'] ?? '';

if (empty($sr_no)) {
    echo "Invalid complaint ID.";
    exit;
}

// Fetch remarks history for the given sr_no
$sql = "SELECT * FROM complaints_remarks_history WHERE sr_no = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $sr_no);
$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Remarks History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 15px;
        }
        .table th, .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <h4 class="mb-4">Remarks History for Complaint ID: <strong><?php echo htmlspecialchars($sr_no); ?></strong></h4>

    <?php if ($result->num_rows > 0): ?>
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Username</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php $count = 1; ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $count++; ?></td>
                        <td><?php echo htmlspecialchars($row['date'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['time'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['username'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['status'] ?? ''); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($row['remark'] ?? '')); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-warning">No remarks found for this complaint.</div>
    <?php endif; ?>

    <div class="text-end mt-3">
        <button class="btn btn-secondary" onclick="window.close();">Close</button>
    </div>
</body>
</html>
