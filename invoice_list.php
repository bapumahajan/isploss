<?php
// File: invoice_list.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

function showval($val) {
    return htmlspecialchars(($val !== null && $val !== '') ? $val : 'NA');
}
function showdate($val) {
    if (!$val || $val === '0000-00-00') return 'NA';
    $d = DateTime::createFromFormat('Y-m-d', $val);
    return $d ? strtoupper($d->format('d-M-Y')) : 'NA';
}

// Handle filter
$filter = $_GET['filter'] ?? '';

$sql = "
    SELECT bc.*, cbi.organization_name,
           pt.amount AS paid_amount,
           pt.payment_status
    FROM billing_cycles bc
    JOIN customer_basic_information cbi ON bc.circuit_id = cbi.circuit_id
    LEFT JOIN (
        SELECT circuit_id, SUM(amount) AS amount, MAX(payment_status) AS payment_status
        FROM payment_transactions
        GROUP BY circuit_id
    ) pt ON bc.circuit_id = pt.circuit_id
";

// Apply overdue filter correctly
if ($filter === 'overdue') {
    $sql .= "
        WHERE bc.next_billing_date < CURDATE()
        AND (bc.cost - IFNULL(pt.amount, 0)) > 0
    ";
}

$sql .= " ORDER BY bc.next_billing_date ASC";

$stmt = $pdo->query($sql);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Invoices</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <h3>🧾 Invoice List <?= $filter === 'overdue' ? '(Overdue Only)' : '' ?></h3>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="billing_dashboard.php" class="btn btn-secondary btn-sm">⬅ Back to Dashboard</a>
        <div>
            <a href="invoice_list.php" class="btn btn-outline-secondary btn-sm <?= $filter === '' ? 'active' : '' ?>">All</a>
            <a href="invoice_list.php?filter=overdue" class="btn btn-outline-danger btn-sm <?= $filter === 'overdue' ? 'active' : '' ?>">Overdue Only</a>
        </div>
    </div>

    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>Circuit ID</th>
                <th>Organization</th>
                <th>Billing Type</th>
                <th>Amount (₹)</th>
                <th>Paid (₹)</th>
                <th>Status</th>
                <th>Next Billing</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($invoices) > 0): ?>
                <?php foreach ($invoices as $row): ?>
                <tr>
                    <td><?= showval($row['circuit_id']) ?></td>
                    <td><?= showval($row['organization_name']) ?></td>
                    <td><?= showval($row['billing_type']) ?></td>
                    <td>₹<?= number_format($row['cost'], 2) ?></td>
                    <td>₹<?= number_format($row['paid_amount'] ?? 0, 2) ?></td>
                    <td>
                        <span class="badge bg-<?= match($row['payment_status'] ?? 'Pending') {
                            'Paid' => 'success',
                            'Partial' => 'warning',
                            'Pending' => 'secondary',
                            'Overdue' => 'danger',
                            default => 'light'
                        } ?>">
                            <?= $row['payment_status'] ?? 'Pending' ?>
                        </span>
                    </td>
                    <td><?= showdate($row['next_billing_date']) ?></td>
                    <td><?= showval($row['remarks']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">No records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
