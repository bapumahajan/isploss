<?php
// File: payment_view.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Helper functions
function showval($val) {
    return htmlspecialchars(($val !== null && $val !== '') ? $val : 'NA');
}
function showdate($val) {
    if (!$val || $val === '0000-00-00') return 'NA';
    $d = DateTime::createFromFormat('Y-m-d', $val);
    return $d ? strtoupper($d->format('d-M-Y')) : 'NA';
}

function formatAmount($amt) {
    return '₹' . number_format((float)$amt, 2);
}

$stmt = $pdo->query("
    SELECT pt.*, cbi.organization_name 
    FROM payment_transactions pt
    JOIN customer_basic_information cbi ON pt.circuit_id = cbi.circuit_id
    ORDER BY pt.payment_date DESC
");

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>💰 Payment Transactions</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>💰 Payment Transactions</h3>
        <a href="billing_dashboard.php" class="btn btn-secondary btn-sm">⬅ Back to Dashboard</a>
    </div>

    <?php if (count($payments) > 0): ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Transaction ID</th>
                    <th>Circuit ID</th>
                    <th>Organization</th>
                    <th>Amount</th>
                    <th>Payment Date</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Partial Reason</th>
                    <th>Added By</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $index => $row): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= showval($row['transaction_id']) ?></td>
                    <td><?= showval($row['circuit_id']) ?></td>
                    <td><?= showval($row['organization_name']) ?></td>
                    <td><?= formatAmount($row['amount']) ?></td>
                    <td><?= showdate($row['payment_date']) ?></td>
                    <td><?= showval($row['payment_method']) ?></td>
                    <td>
                        <span class="badge bg-<?= match($row['payment_status']) {
                            'Paid' => 'success',
                            'Partial' => 'warning',
                            'Pending' => 'secondary',
                            'Overdue' => 'danger',
                            default => 'light'
                        } ?>">
                            <?= showval($row['payment_status']) ?>
                        </span>
                    </td>
                    <td>
                        <?= $row['payment_status'] === 'Partial' ? showval($row['partial_reason']) : '-' ?>
                    </td>
                    <td><?= showval($row['added_by']) ?></td>
                    <td><?= date('d-M-Y H:i', strtotime($row['created_at'])) ?></td>
                    <td>
                        <?php if ($row['payment_status'] === 'Partial'): ?>
                            <a href="payment_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="alert alert-info">No payment records found.</div>
    <?php endif; ?>
</body>
</html>
