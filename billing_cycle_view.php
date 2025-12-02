<?php
// File: billing_cycle_view.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php'; // make sure this defines $pdo (not $conn)

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'user';
$isAdmin = ($role === 'admin');

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function showval($val) {
    return htmlspecialchars(($val !== null && $val !== '') ? $val : 'NA');
}
function showdate($val) {
    if (!$val || $val === '0000-00-00') return 'NA';
    $d = DateTime::createFromFormat('Y-m-d', $val);
    return $d ? strtoupper($d->format('d-M-Y')) : 'NA';
}

$billing_types = ['Monthly', 'Quarterly', 'Half-Yearly', 'Yearly'];

$stmt = $pdo->query("
    SELECT bc.*, cbi.organization_name 
    FROM billing_cycles bc
    LEFT JOIN customer_basic_information cbi ON bc.circuit_id = cbi.circuit_id
    ORDER BY bc.next_billing_date DESC
");
$billing_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Billing Cycle View</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <h3>Customer Billing Cycles</h3>
    <a href="billing_dashboard.php" class="btn btn-secondary btn-sm mb-3">⬅ Back to Dashboard</a>

    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>Billing ID</th>
                <th>Circuit ID</th>
                <th>Organization</th>
                <th>Activation Date</th>
                <th>Start Billing Date</th>
                <th>Cost (₹)</th>
                <th>Billing Type</th>
                <th>Next Billing</th>
                <th>Status</th>
                <?php if ($isAdmin): ?>
                    <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($billing_data as $row): ?>
            <tr data-id="<?= $row['id'] ?>">
                <td><?= $row['id'] ?></td>
                <td><?= showval($row['circuit_id']) ?></td>
                <td><?= showval($row['organization_name']) ?></td>
                <td><?= showdate($row['activation_date']) ?></td>
                <td>
                    <input type="date" class="form-control form-control-sm start-billing-date"
                        value="<?= htmlspecialchars($row['start_billing_date']) ?>" />
                </td>
                <td>
                    <?php if ($isAdmin && $row['next_billing_date'] >= date('Y-m-d')): ?>
                        <input type="number" class="form-control form-control-sm cost-input"
                            value="<?= $row['cost'] ?>" data-original="<?= $row['cost'] ?>" />
                    <?php else: ?>
                        ₹<?= showval($row['cost']) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isAdmin && $row['next_billing_date'] >= date('Y-m-d')): ?>
                        <select class="form-select form-select-sm billing-type-input" data-original="<?= htmlspecialchars($row['billing_type']) ?>">
                            <?php foreach ($billing_types as $bt): ?>
                                <option value="<?= $bt ?>" <?= ($row['billing_type'] === $bt) ? 'selected' : '' ?>><?= $bt ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <?= showval($row['billing_type']) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isAdmin && $row['next_billing_date'] >= date('Y-m-d')): ?>
                        <input type="date" class="form-control form-control-sm next-billing-input"
                               value="<?= htmlspecialchars($row['next_billing_date']) ?>" data-original="<?= htmlspecialchars($row['next_billing_date']) ?>" />
                    <?php else: ?>
                        <?= showdate($row['next_billing_date']) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isAdmin && $row['next_billing_date'] >= date('Y-m-d')): ?>
                        <select class="form-select form-select-sm status-input" data-original="<?= $row['circuit_status'] ?>">
                            <?php foreach (['Active', 'Disconnected', 'Suspended'] as $status): ?>
                                <option value="<?= $status ?>" <?= $row['circuit_status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <?= showval($row['circuit_status']) ?>
                    <?php endif; ?>
                </td>
                <?php if ($isAdmin): ?>
                    <td>
                        <?php if ($row['next_billing_date'] >= date('Y-m-d')): ?>
                            <button class="btn btn-sm btn-primary save-btn">Save</button>
                        <?php else: ?>
                            <span class="text-muted">Locked</span>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<script>
document.querySelectorAll('tr').forEach(row => {
    const billingType = row.querySelector('.billing-type-input');
    const startBilling = row.querySelector('.start-billing-date');
    const nextBilling = row.querySelector('.next-billing-input');

    if (billingType && startBilling && nextBilling) {
        billingType.addEventListener('change', () => {
            const start = new Date(startBilling.value);
            if (isNaN(start.getTime())) return;

            let next = new Date(start);
            const type = billingType.value;

            switch (type) {
                case 'Monthly': next.setMonth(next.getMonth() + 1); break;
                case 'Quarterly': next.setMonth(next.getMonth() + 3); break;
                case 'Half-Yearly': next.setMonth(next.getMonth() + 6); break;
                case 'Yearly': next.setFullYear(next.getFullYear() + 1); break;
            }

            nextBilling.value = next.toISOString().slice(0, 10);
        });
    }
});

document.querySelectorAll('.save-btn').forEach(button => {
    button.addEventListener('click', async function () {
        const row = this.closest('tr');
        const id = row.dataset.id;

        const cost = parseFloat(row.querySelector('.cost-input').value);
        const billing_type = row.querySelector('.billing-type-input').value;
        const start_billing_date = row.querySelector('.start-billing-date').value;
        const next_billing = row.querySelector('.next-billing-input').value;
        const status = row.querySelector('.status-input').value;

        const justification = prompt("Enter justification for the change:");
        if (!justification) return;

        try {
            const res = await fetch('billing_cycle_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id,
                    cost,
                    billing_type,
                    start_billing_date,
                    next_billing,
                    circuit_status: status,
                    justification,
                    csrf_token: "<?= $csrf_token ?>"
                })
            });

            const data = await res.json();
            if (data.success) {
                alert("Updated successfully.");
                location.reload();
            } else {
                alert(data.message || data.error || "Update failed.");
            }
        } catch (err) {
            alert("Error: " + err.message);
        }
    });
});
</script>
</body>
</html>
