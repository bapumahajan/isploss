<?php
// File: billing_cycle_add.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

function calculate_next_billing_date($activation_date, $billing_type) {
    $date = new DateTime($activation_date);
    switch (strtolower($billing_type)) {
        case 'monthly': $date->modify('+1 month'); break;
        case 'quarterly': $date->modify('+3 months'); break;
        case 'half-yearly': $date->modify('+6 months'); break;
        case 'yearly': $date->modify('+1 year'); break;
    }
    return $date->format('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $circuit_id = $_POST['circuit_id'] ?? '';
    $activation_date = $_POST['activation_date'] ?? '';
    $cost = $_POST['cost'] ?? 0;
    $billing_type = $_POST['billing_type'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $next_billing_date = calculate_next_billing_date($activation_date, $billing_type);

    $stmt = $pdo->prepare("INSERT INTO billing_cycles (circuit_id, activation_date, cost, billing_type, next_billing_date, remarks)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$circuit_id, $activation_date, $cost, $billing_type, $next_billing_date, $remarks]);
    header("Location: billing_cycle_view.php");
    exit;
}

$circuits = $pdo->query("SELECT circuit_id, organization_name FROM customer_basic_information")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Billing Cycle</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <h3>Add Billing Cycle</h3>
    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label for="circuit_id" class="form-label">Circuit ID</label>
            <select class="form-select" name="circuit_id" required>
                <option value="">Select Circuit</option>
                <?php foreach ($circuits as $c): ?>
                    <option value="<?= htmlspecialchars($c['circuit_id']) ?>">
                        <?= htmlspecialchars($c['circuit_id']) ?> - <?= htmlspecialchars($c['organization_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="activation_date" class="form-label">Activation Date</label>
            <input type="date" class="form-control" name="activation_date" required>
        </div>
        <div class="col-md-6">
            <label for="cost" class="form-label">Cost (₹)</label>
            <input type="number" step="0.01" class="form-control" name="cost" required>
        </div>
        <div class="col-md-6">
            <label for="billing_type" class="form-label">Billing Type</label>
            <select class="form-select" name="billing_type" required>
                <option value="">Select</option>
                <option value="Monthly">Monthly</option>
                <option value="Quarterly">Quarterly</option>
                <option value="Half-Yearly">Half-Yearly</option>
                <option value="Yearly">Yearly</option>
            </select>
        </div>
        <div class="col-md-12">
            <label for="remarks" class="form-label">Remarks</label>
            <textarea class="form-control" name="remarks" rows="3"></textarea>
        </div>
        <div class="col-md-12">
            <button type="submit" class="btn btn-success">Save Billing Cycle</button>
            <a href="billing_cycle_view.php" class="btn btn-secondary">Back</a>
        </div>
    </form>
</body>
</html>
