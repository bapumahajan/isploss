<?php
// File: payment_edit.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$edited_by = $_SESSION['username'];
$payment_id = $_GET['id'] ?? null;
$errors = [];
$success = '';

// Fetch payment record
$stmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    echo "<div class='alert alert-danger'>Payment record not found.</div>";
    exit;
}

// Get billed amount (prefer billing_summary, fallback to billing_cycles)
$billedStmt = $pdo->prepare("
    SELECT COALESCE(bs.billed_amount, bc.cost) AS billed_amount
    FROM billing_cycles bc
    LEFT JOIN billing_summary bs ON bc.circuit_id = bs.circuit_id
    WHERE bc.circuit_id = ?
");
$billedStmt->execute([$payment['circuit_id']]);
$billedRow = $billedStmt->fetch(PDO::FETCH_ASSOC);
$billed = floatval($billedRow['billed_amount']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $payment_method = $_POST['payment_method'] ?? '';
    $payment_status = $_POST['payment_status'] ?? '';
    $payment_date = $_POST['payment_date'] ?? '';
    $partial_reason = $_POST['partial_reason'] ?? '';
    $edit_reason = $_POST['edit_reason'] ?? 'Manual edit';

    // Track changes
    $changes = [];
    $fields = ['amount', 'payment_method', 'payment_status', 'payment_date', 'partial_reason'];
    foreach ($fields as $field) {
        $old = $field === 'amount' ? floatval($payment[$field]) : $payment[$field];
        $new = $field === 'amount' ? $amount : $_POST[$field];
        if ($old != $new) {
            $changes[] = ['field' => $field, 'old' => $old, 'new' => $new];
        }
    }

    // Validate total against billed
    $sumOtherStmt = $pdo->prepare("SELECT SUM(amount) FROM payment_transactions WHERE circuit_id = ? AND id != ?");
    $sumOtherStmt->execute([$payment['circuit_id'], $payment_id]);
    $otherPaid = floatval($sumOtherStmt->fetchColumn());
    $newTotal = $otherPaid + $amount;

    if ($newTotal > $billed) {
        $errors[] = "⚠️ Total paid amount (₹$newTotal) exceeds the billed amount (₹$billed).";
    }

    if (empty($errors) && $changes) {
        // Update payment
        $update = $pdo->prepare("
            UPDATE payment_transactions 
            SET amount = ?, payment_method = ?, payment_status = ?, payment_date = ?, partial_reason = ?
            WHERE id = ?
        ");
        $update->execute([$amount, $payment_method, $payment_status, $payment_date, $partial_reason, $payment_id]);

        // Log changes
        foreach ($changes as $chg) {
            $log = $pdo->prepare("
                INSERT INTO payment_edit_log (payment_id, edited_by, field_changed, old_value, new_value, reason)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $log->execute([
                $payment_id,
                $edited_by,
                $chg['field'],
                $chg['old'],
                $chg['new'],
                $edit_reason
            ]);
        }

        // Recalculate total paid
        $totalPaidStmt = $pdo->prepare("SELECT SUM(amount) FROM payment_transactions WHERE circuit_id = ?");
        $totalPaidStmt->execute([$payment['circuit_id']]);
        $totalPaid = floatval($totalPaidStmt->fetchColumn());

        // Update billing_summary via UPSERT
        $upsert = $pdo->prepare("
            INSERT INTO billing_summary (circuit_id, billed_amount, total_paid)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE total_paid = VALUES(total_paid)
        ");
        $upsert->execute([$payment['circuit_id'], $billed, $totalPaid]);

        $success = "✅ Payment updated successfully!";
        $stmt->execute([$payment_id]); // Refresh record
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (empty($changes)) {
        $errors[] = "No changes detected.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Payment</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <h3>📝 Edit Payment</h3>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode("<br>", $errors) ?></div>
    <?php elseif (!empty($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Circuit ID</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($payment['circuit_id']) ?>" readonly>
        </div>

        <div class="col-md-6">
            <label class="form-label">Transaction ID</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($payment['transaction_id']) ?>" readonly>
        </div>

        <div class="col-md-6">
            <label class="form-label">Amount (₹)</label>
            <input type="number" step="0.01" class="form-control" name="amount" required value="<?= htmlspecialchars($payment['amount']) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">Payment Method</label>
            <select class="form-select" name="payment_method" required>
                <?php foreach (['NEFT','IMPS','UPI','Cash','Cheque','Bank Transfer'] as $method): ?>
                    <option value="<?= $method ?>" <?= $method === $payment['payment_method'] ? 'selected' : '' ?>><?= $method ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Payment Status</label>
            <select class="form-select" name="payment_status" required>
                <?php foreach (['Paid', 'Partial', 'Pending', 'Overdue'] as $status): ?>
                    <option value="<?= $status ?>" <?= $status === $payment['payment_status'] ? 'selected' : '' ?>><?= $status ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Payment Date</label>
            <input type="date" class="form-control" name="payment_date" value="<?= htmlspecialchars($payment['payment_date']) ?>" required>
        </div>

        <div class="col-md-12">
            <label class="form-label">Partial Payment Reason</label>
            <textarea name="partial_reason" class="form-control"><?= htmlspecialchars($payment['partial_reason']) ?></textarea>
        </div>

        <div class="col-md-12">
            <label class="form-label">Reason for Edit (Audit Log)</label>
            <input type="text" name="edit_reason" class="form-control" placeholder="Why are you changing this?" required>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">💾 Update Payment</button>
            <a href="payment_view.php" class="btn btn-secondary">⬅ Back</a>
        </div>
    </form>
</body>
</html>
