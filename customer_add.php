<?php
session_name('oss_portal');
session_start();
include 'includes/db.php';

// Helper function
function fetchOptions($conn, $table, $column) {
    $result = $conn->query("SELECT DISTINCT $column FROM $table ORDER BY $column ASC");
    $options = [];
    while ($row = $result->fetch_assoc()) {
        $options[] = $row[$column];
    }
    return $options;
}

// Fetch dropdown values
$linkTypes = fetchOptions($conn, 'link_types', 'link_type');
$statuses = fetchOptions($conn, 'statuses', 'status');
$productTypes = fetchOptions($conn, 'product_types', 'product_type');
$zones = fetchOptions($conn, 'zones', 'zone_name');

// Fetch POPs and Switches
$pops = [];
$switches = [];
$result = $conn->query("SELECT * FROM network_inventory");
while ($row = $result->fetch_assoc()) {
    if ($row['type'] === 'POP') {
        $pops[] = ['name' => $row['name'], 'ip' => $row['ip']];
    } elseif ($row['type'] === 'SWITCH') {
        $switches[] = ['name' => $row['name'], 'ip' => $row['ip']];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $circuit_id = $_POST['circuit_id'] ?? '';
    $customer_name = $_POST['customer_name'] ?? '';
    $status = $_POST['status'] ?? '';
    $pop = $_POST['pop'] ?? '';
    $switch = $_POST['switch'] ?? '';

    $stmt = $pdo->prepare("INSERT INTO customer_circuits (circuit_id, customer_name, status, pop, switch) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$circuit_id, $customer_name, $status, $pop, $switch]);

    // Redirect to view page (optional)
    header("Location: view_customer.php?id=" . $pdo->lastInsertId());
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Customer Circuit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h2>Add Customer Circuit</h2>
    <form method="post">
        <ul class="nav nav-tabs" id="formTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button">Basic Info</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="network-tab" data-bs-toggle="tab" data-bs-target="#network" type="button">Network Info</button>
            </li>
        </ul>
        <div class="tab-content pt-3">
            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                <div class="mb-3">
                    <label for="circuit_id" class="form-label">Circuit ID</label>
                    <input type="text" class="form-control" id="circuit_id" name="circuit_id" required>
                </div>
                <div class="mb-3">
                    <label for="customer_name" class="form-label">Customer Name</label>
                    <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                </div>
                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" name="status" id="status" required>
                        <option value="">Select Status</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="tab-pane fade" id="network" role="tabpanel">
                <div class="mb-3">
                    <label for="pop" class="form-label">POP Name</label>
                    <select class="form-select" name="pop" id="pop">
                        <option value="">Select POP</option>
                        <?php foreach ($pops as $pop): ?>
                            <option value="<?= htmlspecialchars($pop['name']) ?>"><?= htmlspecialchars($pop['name']) ?> (<?= htmlspecialchars($pop['ip']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="switch" class="form-label">Switch Name</label>
                    <select class="form-select" name="switch" id="switch">
                        <option value="">Select Switch</option>
                        <?php foreach ($switches as $switch): ?>
                            <option value="<?= htmlspecialchars($switch['name']) ?>"><?= htmlspecialchars($switch['name']) ?> (<?= htmlspecialchars($switch['ip']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Submit</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
