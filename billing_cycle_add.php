<?php
// File: billing_cycle_add.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

function calculate_next_billing_date($start_billing_date, $billing_type) {
    $date = new DateTime($start_billing_date);
    switch (strtolower($billing_type)) {
        case 'monthly': $date->modify('+1 month'); break;
        case 'quarterly': $date->modify('+3 months'); break;
        case 'half-yearly': $date->modify('+6 months'); break;
        case 'yearly': $date->modify('+1 year'); break;
    }
    return $date->format('Y-m-d');
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $circuit_id = $_POST['circuit_id'] ?? '';
    $activation_date = $_POST['activation_date'] ?? '';
    $start_billing_date = $_POST['start_billing_date'] ?? '';
    $cost = $_POST['cost'] ?? 0;
    $billing_type = $_POST['billing_type'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $circuit_status = 'Active';

    if (strtotime($activation_date) > time()) {
        die("⚠️ Activation date cannot be in the future.");
    }
    if (strtotime($start_billing_date) > time()) {
        die("⚠️ Start Billing date cannot be in the future.");
    }
    if (strtotime($start_billing_date) < strtotime($activation_date)) {
        die("⚠️ Start Billing date cannot be before activation date.");
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM billing_cycles WHERE circuit_id = ?");
    $stmt->execute([$circuit_id]);
    if ($stmt->fetchColumn() > 0) {
        die("⚠️ Billing cycle already exists for this circuit.");
    }

    $next_billing_date = calculate_next_billing_date($start_billing_date, $billing_type);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO billing_cycles (circuit_id, activation_date, start_billing_date, cost, billing_type, next_billing_date, remarks, circuit_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$circuit_id, $activation_date, $start_billing_date, $cost, $billing_type, $next_billing_date, $remarks, $circuit_status]);

        $update = $pdo->prepare("UPDATE network_details SET circuit_status = ? WHERE circuit_id = ?");
        $update->execute([$circuit_status, $circuit_id]);

        $pdo->commit();
        header("Location: billing_cycle_view.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("❌ Error: " . $e->getMessage());
    }
}

// Fetch eligible circuits: not billed + active in network_details
$circuits = $pdo->query("
    SELECT cbi.circuit_id, cbi.organization_name
    FROM customer_basic_information cbi
    JOIN network_details nd ON cbi.circuit_id = nd.circuit_id
    WHERE nd.circuit_status = 'Active'
    AND cbi.circuit_id NOT IN (SELECT circuit_id FROM billing_cycles)
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Billing Cycle</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        #circuitSearch { float: right; width: 250px; margin-bottom: 10px; }
    </style>
</head>
<body class="container mt-4">
    <h3>Add Billing Cycle</h3>
    <a href="billing_dashboard.php" class="btn btn-secondary btn-sm mb-3">⬅ Back to Dashboard</a>

    <input type="text" id="circuitSearch" class="form-control" placeholder="Search Circuit ID or Organization">

    <form method="post" id="billingForm" class="mt-3" style="display:none;">
        <input type="hidden" name="circuit_id" id="selectedCircuit">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Activation Date</label>
                <input type="date" name="activation_date" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Start Billing Date</label>
                <input type="date" name="start_billing_date" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Cost (₹)</label>
                <input type="number" step="0.01" name="cost" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Billing Type</label>
                <select name="billing_type" class="form-select" required>
                    <option value="">Select</option>
                    <option value="Monthly">Monthly</option>
                    <option value="Quarterly">Quarterly</option>
                    <option value="Half-Yearly">Half-Yearly</option>
                    <option value="Yearly">Yearly</option>
                </select>
            </div>
            <div class="col-md-12">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" rows="3" class="form-control"></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-success" type="submit">💾 Save Billing</button>
                <a href="billing_cycle_view.php" class="btn btn-secondary">⬅ Back</a>
            </div>
        </div>
    </form>

    <table class="table table-bordered table-striped mt-3" id="circuitTable">
        <thead>
            <tr>
                <th>#</th>
                <th>Circuit ID</th>
                <th>Organization</th>
                <th>Select</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($circuits as $index => $c): ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($c['circuit_id']) ?></td>
                <td><?= htmlspecialchars($c['organization_name']) ?></td>
                <td>
                    <button type="button" class="btn btn-sm btn-primary select-btn"
                        data-cid="<?= htmlspecialchars($c['circuit_id']) ?>">
                        Select
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
    document.querySelectorAll(".select-btn").forEach(button => {
        button.addEventListener("click", () => {
            const circuitId = button.getAttribute("data-cid");
            document.getElementById("selectedCircuit").value = circuitId;
            document.getElementById("billingForm").style.display = 'block';
            window.scrollTo(0, document.getElementById("billingForm").offsetTop);
        });
    });

    document.getElementById("circuitSearch").addEventListener("keyup", function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll("#circuitTable tbody tr");
        rows.forEach(row => {
            const id = row.cells[1].textContent.toLowerCase();
            const org = row.cells[2].textContent.toLowerCase();
            row.style.display = id.includes(filter) || org.includes(filter) ? "" : "none";
        });
    });
    </script>
</body>
</html>