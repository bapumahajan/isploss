<?php
session_name('oss_portal');
session_start();
require_once 'auth_check.php';
require 'includes/config.php';

// Calculate total and per-site cost
$cost_rs = $conn->query("SELECT di.site_id, sm.site_name, SUM(di.device_price) as site_cost FROM device_inventory di LEFT JOIN site_master sm ON di.site_id = sm.site_id GROUP BY di.site_id");
$total_cost = 0;
$site_costs = [];
while ($row = $cost_rs->fetch_assoc()) {
    $site_costs[$row['site_id']] = [
        'name' => $row['site_name'] ?? 'Unknown',
        'cost' => floatval($row['site_cost'])
    ];
    $total_cost += floatval($row['site_cost']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Total Inventory Cost</title>
    <meta name="viewport" content="width=device-width">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h4>Total Inventory Cost By Site</h4>
    <table class="table table-bordered table-sm w-auto">
        <thead>
            <tr>
                <th>Site</th>
                <th>Total Cost (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($site_costs as $sid => $sc): ?>
                <tr>
                    <td><?= htmlspecialchars($sc['name']) ?></td>
                    <td><?= number_format($sc['cost'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="table-secondary">
                <td><strong>Grand Total</strong></td>
                <td><strong><?= number_format($total_cost, 2) ?></strong></td>
            </tr>
        </tbody>
    </table>
    <a href="device_inventory.php" class="btn btn-secondary btn-sm">Back to Inventory</a>
</div>
</body>
</html>