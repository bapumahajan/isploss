<?php
// import_export.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_name('oss_portal');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'includes/auth.php';
require_roles(['admin', 'manager']);

require_once 'includes/db.php';

$tables = [
    'customer_basic_information',
    'network_details',
    'circuit_network_details',
    'pop_inventory',
    'switch_inventory',
    'circuit_ips'
];

$fieldGroups = [];
foreach ($tables as $table) {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($columns) {
        $fieldGroups[$table] = $columns;
    }
}

$importMsg = '';
if (isset($_GET['import_status'])) {
    if ($_GET['import_status'] === 'success') {
        $importMsg = "<div class='alert alert-success'>Import completed successfully.</div>";
    } else {
        $error = htmlspecialchars($_GET['error'] ?? 'Unknown error');
        $importMsg = "<div class='alert alert-danger'>Import failed. $error</div>";
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Bulk Import/Export Customers & Circuits</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        /* Sticky header for fields checkbox container */
        .fields-container {
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 12px;
            border-radius: 6px;
            background: #fafafa;
        }
        .fields-table-name {
            cursor: pointer;
            user-select: none;
        }
        .fields-table-columns {
            margin-top: 6px;
            padding-left: 15px;
        }
        /* Make label text a bit more readable */
        .form-check-label {
            user-select: none;
        }
    </style>
</head>
<body>
<div class="container my-5">
    <h2 class="mb-4">Bulk Import & Export (Customers + Circuits)</h2>

    <?= $importMsg ?>

    <!-- Export Form -->
    <div class="card shadow-sm mb-5">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0">Export Customers & Circuits</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="export_customers_circuits.php" class="row g-3" novalidate>
                <div class="col-md-3">
                    <label for="from_date" class="form-label">From Date</label>
                    <input type="date" id="from_date" name="from_date" class="form-control" />
                </div>
                <div class="col-md-3">
                    <label for="to_date" class="form-label">To Date</label>
                    <input type="date" id="to_date" name="to_date" class="form-control" />
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Search (Org Name or IP)</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Enter organization name or IP address" />
                </div>
                <div class="col-md-2">
                    <label for="format" class="form-label">Export Format</label>
                    <select name="format" id="format" class="form-select" required>
                        <option value="csv">CSV</option>
                        <option value="excel">Excel</option>
                        <option value="pdf">PDF</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold mb-2">Select Fields to Export</label>
                    <div class="fields-container">
                        <?php foreach ($fieldGroups as $table => $columns): ?>
                            <div class="mb-3">
                                <div class="fields-table-name fw-bold text-primary" data-bs-toggle="collapse" data-bs-target="#fields-<?= $table ?>" aria-expanded="true" aria-controls="fields-<?= $table ?>">
                                    <?= htmlspecialchars($table) ?>
                                    <small class="text-muted">(click to toggle)</small>
                                </div>
                                <div id="fields-<?= $table ?>" class="fields-table-columns collapse show">
                                    <?php foreach ($columns as $column): ?>
                                        <?php
                                        $id = $table . '_' . $column;
                                        $val = $table . '.' . $column;
                                        ?>
                                        <div class="form-check form-check-sm">
                                            <input class="form-check-input" type="checkbox" name="fields[]" value="<?= $val ?>" id="<?= $id ?>" />
                                            <label class="form-check-label" for="<?= $id ?>">
                                                <?= htmlspecialchars($column) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-success px-4">Generate Export</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Form -->
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Import Customers & Circuits from CSV</h4>
        </div>
        <div class="card-body">
            <form action="import_customers_circuits.php" method="post" enctype="multipart/form-data" novalidate>
                <div class="mb-3">
                    <label for="csv_file" class="form-label">Select CSV file</label>
                    <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required />
                    <div class="form-text">
                        <b>Required columns (order):</b><br>
                        circuit_id, organization_name, customer_address, city, contact_person_name, product_type, <br>
                        pop_name, pop_ip, switch_name, switch_ip, switch_port, bandwidth, circuit_status, vlan
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-4">Import CSV</button>
                </div>
            </form>
            <div class="mt-3">
                <a href="sample_customers_circuits.csv" class="btn btn-link">Download Sample CSV</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
