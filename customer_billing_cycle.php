<?php
// Filename: customer_billing_cycle.php
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
    if (!$d) $d = DateTime::createFromFormat('Y-m-d H:i:s', $val);
    return $d ? strtoupper($d->format('d-M-Y')) : 'NA';
}

$circuit_id = $_GET['circuit_id'] ?? '';
if (!$circuit_id) {
    echo "Invalid request.";
    exit;
}

// Billing info query
$stmt = $pdo->prepare("SELECT * FROM billing_cycles WHERE circuit_id = ?");
$stmt->execute([$circuit_id]);
$billing = $stmt->fetch(PDO::FETCH_ASSOC);

// Transactions
$payments = [];
$stmt2 = $pdo->prepare("SELECT * FROM payment_transactions WHERE circuit_id = ? ORDER BY payment_date DESC");
$stmt2->execute([$circuit_id]);
$payments = $stmt2->fetchAll(PDO::FETCH_ASSOC);
$total_received = array_sum(array_column($payments, 'amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing Cycle - <?= showval($circuit_id) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
    <h3>Billing Information for Circuit ID: <?= showval($circuit_id) ?></h3>
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">Billing Cycle Details</div>
        <div class="card-body">
            <p><strong>Activation Date:</strong> <?= showdate($billing['activation_date'] ?? null) ?></p>
            <p><strong>Cost (per cycle):</strong> ₹<?= showval($billing['cost']) ?></p>
            <p><strong>Billing Type:</strong> <?= showval($billing['billing_type']) ?> (Monthly / Quarterly / Half-Yearly / Yearly)</p>
            <p><strong>Next Billing Date:</strong> <?= showdate($billing['next_billing_date'] ?? null) ?></p>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-success text-white">Payment Transactions</div>
        <div class="card-body">
            <?php if ($payments): ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Amount (₹)</th>
                            <th>Payment Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td><?= showval($pay['transaction_id']) ?></td>
                            <td><?= showval($pay['amount']) ?></td>
                            <td><?= showdate($pay['payment_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><strong>Total Received:</strong> ₹<?= number_format($total_received, 2) ?></p>
            <?php else: ?>
                <p class="text-muted">No payments recorded yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>