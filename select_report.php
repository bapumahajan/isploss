<?php
require_once 'includes/db.php'; // DB connection

// Fetch unique values for dropdown filters dynamically
function getDistinctValues($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT $column FROM $table WHERE $column IS NOT NULL AND $column != '' ORDER BY $column ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        echo "Error fetching distinct values: " . $e->getMessage();
        return [];
    }
}

// Filters (only filter if selected)
$filters = [
    'circuit_id' => $_GET['circuit_id'] ?? '',
    'pop' => $_GET['pop'] ?? '',
    'status' => $_GET['status'] ?? '',
    'product_type' => $_GET['product_type'] ?? '',
];

// Build base query with joins
$query = "
    SELECT 
        cbi.*, nd.*, cnd.*
    FROM customer_basic_information cbi
    LEFT JOIN network_details nd ON cbi.circuit_id = nd.circuit_id
    LEFT JOIN circuit_network_details cnd ON cbi.circuit_id = cnd.circuit_id
    WHERE 1=1
";
$params = [];
foreach ($filters as $field => $value) {
    if (!empty($value)) {
        $query .= " AND cbi.$field = :$field";
        $params[$field] = $value;
    }
}
$query .= " ORDER BY cbi.circuit_id DESC LIMIT 100";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error executing query: " . $e->getMessage();
    $results = [];
}

// Get filter options
$popList = getDistinctValues($pdo, 'customer_basic_information', 'pop');
$statusList = getDistinctValues($pdo, 'customer_basic_information', 'status');
$productTypes = getDistinctValues($pdo, 'customer_basic_information', 'product_type');
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Circuit Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style> td, th { font-size: 13px; } </style>
</head>
<body class="bg-light">
<div class="container-fluid mt-4">
    <h4 class="mb-4">Customer Circuit Report</h4>

    <!-- Filters -->
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="pop" class="form-select">
                <option value="">-- POP --</option>
                <?php foreach ($popList as $item): ?>
                    <option value="<?= $item ?>" <?= ($filters['pop'] === $item) ? 'selected' : '' ?>><?= $item ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">-- Status --</option>
                <?php foreach ($statusList as $item): ?>
                    <option value="<?= $item ?>" <?= ($filters['status'] === $item) ? 'selected' : '' ?>><?= $item ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="product_type" class="form-select">
                <option value="">-- Product Type --</option>
                <?php foreach ($productTypes as $item): ?>
                    <option value="<?= $item ?>" <?= ($filters['product_type'] === $item) ? 'selected' : '' ?>><?= $item ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <!-- Results Table -->
    <?php if ($results): ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover table-sm">
            <thead class="table-dark">
                <tr>
                    <?php foreach (array_keys($results[0]) as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?= htmlspecialchars($cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="alert alert-warning">No records found with selected filters.</div>
    <?php endif; ?>
</div>
</body>
</html>
