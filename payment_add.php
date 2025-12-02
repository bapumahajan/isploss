<?php
session_name('oss_portal');
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $circuit_id = $_POST['circuit_id'] ?? '';
    $transaction_id = $_POST['transaction_id'] ?? '';
    $paying_amount = floatval($_POST['paying_amount'] ?? 0);
    $payment_date = $_POST['payment_date'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $added_by = $_SESSION['username'] ?? 'system';
    $partial_reason = $_POST['partial_reason'] ?? '';

    // Cheque-specific fields
    $bank_name = $_POST['bank_name'] ?? '';
    $customer_account = $_POST['customer_account'] ?? '';
    $cheque_number = $_POST['cheque_number'] ?? '';

    $dataStmt = $pdo->prepare("SELECT bc.cost, IFNULL(bs.total_paid, 0) AS total_paid FROM billing_cycles bc LEFT JOIN billing_summary bs ON bc.circuit_id = bs.circuit_id WHERE bc.circuit_id = ?");
    $dataStmt->execute([$circuit_id]);
    $billing = $dataStmt->fetch(PDO::FETCH_ASSOC);

    if (!$billing) {
        $errors[] = "Invalid Circuit ID.";
    } else {
        $cost = floatval($billing['cost']);
        $paid = floatval($billing['total_paid']);
        $pending = $cost - $paid;

        if ($paying_amount <= 0) {
            $errors[] = "Enter a valid payment amount.";
        } elseif ($paying_amount > $pending) {
            $errors[] = "\u26a0\ufe0f Payment exceeds pending amount (₹$pending)";
        } else {
            $payment_status = ($paying_amount == $pending) ? 'Paid' : 'Partial';

            $stmt = $pdo->prepare("INSERT INTO payment_transactions (circuit_id, transaction_id, amount, payment_date, payment_method, payment_status, partial_reason, added_by, bank_name, customer_account, cheque_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$circuit_id, $transaction_id, $paying_amount, $payment_date, $payment_method, $payment_status, $partial_reason, $added_by, $bank_name, $customer_account, $cheque_number]);

            $totalPaidStmt = $pdo->prepare("SELECT SUM(amount) FROM payment_transactions WHERE circuit_id = ?");
            $totalPaidStmt->execute([$circuit_id]);
            $totalPaid = floatval($totalPaidStmt->fetchColumn());

            $checkSummary = $pdo->prepare("SELECT COUNT(*) FROM billing_summary WHERE circuit_id = ?");
            $checkSummary->execute([$circuit_id]);
            $exists = $checkSummary->fetchColumn();

            if ($exists) {
                $update = $pdo->prepare("UPDATE billing_summary SET total_paid = ? WHERE circuit_id = ?");
                $update->execute([$totalPaid, $circuit_id]);
            } else {
                $insert = $pdo->prepare("INSERT INTO billing_summary (circuit_id, billed_amount, total_paid) VALUES (?, ?, ?)");
                $insert->execute([$circuit_id, $cost, $totalPaid]);
            }

            header("Location: payment_view.php");
            exit;
        }
    }
}

$circuits = $pdo->query("SELECT bc.circuit_id, cbi.organization_name, bc.cost, bc.next_billing_date, IFNULL(bs.total_paid, 0) AS paid, (bc.cost - IFNULL(bs.total_paid, 0)) AS balance FROM billing_cycles bc JOIN customer_basic_information cbi ON bc.circuit_id = cbi.circuit_id LEFT JOIN billing_summary bs ON bc.circuit_id = bs.circuit_id WHERE bc.circuit_status = 'Active' AND bc.activation_date IS NOT NULL AND (bc.next_billing_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY) OR bc.next_billing_date < CURDATE()) AND (bc.cost - IFNULL(bs.total_paid, 0)) > 0")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Payment</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script>
    const billingMap = {};
    <?php foreach ($circuits as $c): ?>
        billingMap["<?= $c['circuit_id'] ?>"] = {
            balance: <?= json_encode($c['balance']) ?>,
            cost: <?= json_encode($c['cost']) ?>
        };
    <?php endforeach; ?>

    function onCircuitChange() {
        const cid = document.getElementById("circuit_id").value;
        const pendingBox = document.getElementById("pending_amount");
        const amountBox = document.getElementById("paying_amount");

        if (cid && billingMap[cid]) {
            pendingBox.value = billingMap[cid].balance;
            amountBox.value = billingMap[cid].balance;
        } else {
            pendingBox.value = '';
            amountBox.value = '';
        }
    }

    function onAmountInput() {
        const entered = parseFloat(document.getElementById("paying_amount").value || 0);
        const pending = parseFloat(document.getElementById("pending_amount").value || 0);
        document.getElementById("partial_reason_box").style.display = (entered < pending) ? "block" : "none";
    }

    function onPaymentMethodChange() {
        const method = document.getElementById("payment_method").value;
        document.getElementById("transaction_row").style.display = (method === "Cash") ? "none" : "block";
        document.getElementById("cheque_fields").style.display = (method === "Cheque") ? "block" : "none";
    }
    </script>
</head>
<body class="container mt-4">
    <h3>➕ Add Payment</h3>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label for="circuit_id" class="form-label">Circuit ID</label>
            <select class="form-select" name="circuit_id" id="circuit_id" onchange="onCircuitChange()" required>
                <option value="">Select Circuit</option>
                <?php foreach ($circuits as $c): ?>
                    <?php
                        $label = ($c['next_billing_date'] < date('Y-m-d')) ? '🔴 Overdue' : '📅 Upcoming';
                        $id = htmlspecialchars($c['circuit_id']);
                        $org = htmlspecialchars($c['organization_name']);
                    ?>
                    <option value="<?= $id ?>">
                        <?= $id ?> - <?= $org ?> [<?= $label ?>]
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="pending_amount" class="form-label">Pending Amount (₹)</label>
            <input type="number" class="form-control" id="pending_amount" readonly>
        </div>

        <div class="col-md-6">
            <label for="paying_amount" class="form-label">Amount Paying Now (₹)</label>
            <input type="number" step="0.01" class="form-control" name="paying_amount" id="paying_amount" oninput="onAmountInput()" required>
        </div>

        <div class="col-md-6">
            <label for="payment_date" class="form-label">Payment Date</label>
            <input type="date" class="form-control" name="payment_date" required>
        </div>

        <div class="col-md-6">
            <label for="payment_method" class="form-label">Payment Method</label>
            <select class="form-select" name="payment_method" id="payment_method" onchange="onPaymentMethodChange()" required>
                <option value="">Select</option>
                <option value="NEFT">NEFT</option>
                <option value="IMPS">IMPS</option>
                <option value="UPI">UPI</option>
                <option value="Cash">Cash</option>
                <option value="Cheque">Cheque</option>
                <option value="Bank Transfer">Bank Transfer</option>
            </select>
        </div>

        <div class="col-md-6" id="transaction_row" style="display:none;">
            <label for="transaction_id" class="form-label">Transaction ID</label>
            <input type="text" class="form-control" name="transaction_id">
        </div>

        <div class="col-md-12" id="partial_reason_box" style="display:none;">
            <label for="partial_reason" class="form-label">Reason for Partial Payment</label>
            <textarea name="partial_reason" class="form-control" rows="2" placeholder="Enter reason for partial payment..."></textarea>
        </div>

        <div id="cheque_fields" style="display:none;">
            <div class="col-md-6">
                <label class="form-label">Bank Name</label>
                <input type="text" class="form-control" name="bank_name">
            </div>
            <div class="col-md-6">
                <label class="form-label">Customer Account Number</label>
                <input type="text" class="form-control" name="customer_account">
            </div>
            <div class="col-md-6">
                <label class="form-label">Cheque Number</label>
                <input type="text" class="form-control" name="cheque_number">
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-success">💾 Save Payment</button>
            <a href="payment_view.php" class="btn btn-secondary">⬅ Back</a>
        </div>
    </form>
</body>
</html>
