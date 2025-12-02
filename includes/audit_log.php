<?php
session_name('oss_portal');
session_start();
if (!isset($_SESSION['username'])) { header('Location: login.php'); exit; }
include 'includes/db.php';

$stmt = $pdo->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 100");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Audit Log</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2>Audit Log</h2>
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>Date</th>
                <th>User</th>
                <th>Action</th>
                <th>Table</th>
                <th>Record ID</th>
                <th>Description</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log['created_at']) ?></td>
                <td><?= htmlspecialchars($log['user']) ?></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td><?= htmlspecialchars($log['table_name']) ?></td>
                <td><?= htmlspecialchars($log['record_id']) ?></td>
                <td><?= htmlspecialchars($log['description']) ?></td>
                <td><?= htmlspecialchars($log['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>