<?php
// filename: billing_dashboard.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=()');

// Session timeout: 15 minutes
$timeout_duration = 900;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Authentication check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_USER', 'user');

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? ROLE_USER;

$roleHierarchy = [
    ROLE_USER => 1,
    ROLE_MANAGER => 2,
    ROLE_ADMIN => 3,
];

function hasRole($userRole, $requiredRole) {
    global $roleHierarchy;
    return isset($roleHierarchy[$userRole], $roleHierarchy[$requiredRole]) &&
           $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
}

function activeNav($page) {
    return basename($_SERVER['PHP_SELF']) === $page ? 'active' : '';
}

function get_value($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return floatval($stmt->fetchColumn() ?: 0);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return 0;
    }
}

// Auto-update statuses
try {
    $pdo->query("
        UPDATE billing_cycles bc
        LEFT JOIN (
            SELECT circuit_id, SUM(amount) AS total_paid
            FROM payment_transactions
            GROUP BY circuit_id
        ) pt ON bc.circuit_id = pt.circuit_id
        SET bc.payment_status = CASE
            WHEN IFNULL(pt.total_paid, 0) >= bc.cost THEN 'Paid'
            WHEN IFNULL(pt.total_paid, 0) > 0 AND bc.next_billing_date < CURDATE() THEN 'Overdue'
            WHEN IFNULL(pt.total_paid, 0) > 0 THEN 'Partial'
            WHEN bc.next_billing_date < CURDATE() THEN 'Overdue'
            ELSE 'Pending'
        END
    ");
} catch (PDOException $e) {
    error_log("Status update failed: " . $e->getMessage());
}

$total_billing = get_value($pdo, "SELECT COUNT(*) FROM billing_cycles");
$total_received = get_value($pdo, "SELECT SUM(amount) FROM payment_transactions");
$total_pending = get_value($pdo, "
    SELECT SUM(bc.cost - IFNULL(pt.total_paid, 0))
    FROM billing_cycles bc
    LEFT JOIN (
        SELECT circuit_id, SUM(amount) AS total_paid
        FROM payment_transactions
        GROUP BY circuit_id
    ) pt ON bc.circuit_id = pt.circuit_id
    WHERE (bc.cost - IFNULL(pt.total_paid, 0)) > 0
");
$upcoming_bills = get_value($pdo, "
    SELECT COUNT(*) FROM billing_cycles 
    WHERE next_billing_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)
");
$overdue_bills = get_value($pdo, "
    SELECT COUNT(*) FROM billing_cycles bc
    LEFT JOIN (
        SELECT circuit_id, SUM(amount) AS total_paid
        FROM payment_transactions
        GROUP BY circuit_id
    ) pt ON bc.circuit_id = pt.circuit_id
    WHERE bc.next_billing_date < CURDATE()
      AND (bc.cost - IFNULL(pt.total_paid, 0)) > 0
");

$overdue_class = $overdue_bills > 0 ? 'bg-danger text-white' : 'bg-secondary text-white';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Billing Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card:hover {
            transform: scale(1.02);
            transition: 0.2s ease-in-out;
        }
        .dashboard-icon {
            font-size: 1.4rem;
            margin-right: 10px;
        }
    </style>
</head>
<body class="container py-4">
    <a href="dashboard.php" class="btn btn-secondary btn-sm">⬅ Back to Dashboard</a>
    <h2 class="mb-4">📊 <strong>Billing Dashboard</strong></h2>

    <!-- Summary Cards -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="dashboard-icon bi bi-card-list"></i>Total Billing Records</h5>
                    <h3 class="fw-bold"><?= number_format($total_billing) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="dashboard-icon bi bi-currency-rupee"></i>Total Received</h5>
                    <h3 class="fw-bold">₹<?= number_format($total_received ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="dashboard-icon bi bi-wallet2"></i>Total Pending</h5>
                    <h3 class="fw-bold">₹<?= number_format($total_pending ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <div class="row mt-4 g-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title"><i class="dashboard-icon bi bi-calendar2-week"></i>Upcoming Bills (15 Days)</h5>
                    <h3 class="fw-bold"><?= $upcoming_bills ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 <?= $overdue_class ?>">
                <div class="card-body">
                    <h5 class="card-title"><i class="dashboard-icon bi bi-exclamation-circle"></i>Overdue Bills</h5>
                    <h3 class="fw-bold"><?= $overdue_bills ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="mt-5">
        <h5 class="mb-3">Quick Actions</h5>
        <div class="d-flex flex-wrap gap-3">
            <a href="billing_cycle_add.php" class="btn btn-outline-primary"><i class="bi bi-plus-circle"></i> Add Billing Entry</a>
            <a href="billing_cycle_view.php" class="btn btn-outline-dark"><i class="bi bi-receipt"></i> View Invoices</a>
            <a href="invoice_list.php?filter=overdue" class="btn btn-outline-danger"><i class="bi bi-exclamation-triangle"></i> Overdue Bills</a>
            <a href="payment_view.php" class="btn btn-outline-success"><i class="bi bi-cash-coin"></i> View Payments</a>
            <a href="payment_add.php" class="btn btn-outline-success"><i class="bi bi-credit-card"></i> Make Payment</a>
            <a href="#" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#exportModal"><i class="bi bi-download"></i> Custom Export</a>
            <a href="generate_invoice_pdf.php?id=123" class="btn btn-outline-secondary"><i class="bi bi-file-pdf"></i> Download PDF</a>
        </div>
    </div>

    <!-- Custom Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form id="exportForm" method="post" action="billing_export_custom.php" target="_blank">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Export Billing Data</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <label class="form-label mb-1">Columns to include:</label>
              <div class="mb-3">
                <label><input type="checkbox" name="columns[]" value="id" checked> Billing ID</label><br>
                <label><input type="checkbox" name="columns[]" value="circuit_id" checked> Circuit ID</label><br>
                <label><input type="checkbox" name="columns[]" value="activation_date" checked> Activation Date</label><br>
                <label><input type="checkbox" name="columns[]" value="start_billing_date" checked> Start Billing Date</label><br>
                <label><input type="checkbox" name="columns[]" value="cost" checked> Cost</label><br>
                <label><input type="checkbox" name="columns[]" value="billing_type" checked> Billing Type</label><br>
                <label><input type="checkbox" name="columns[]" value="next_billing_date" checked> Next Billing Date</label><br>
                <label><input type="checkbox" name="columns[]" value="circuit_status" checked> Circuit Status</label><br>
                <label><input type="checkbox" name="columns[]" value="payment_status" checked> Payment Status</label><br>
                <label><input type="checkbox" name="columns[]" value="remarks" checked> Remarks</label>
              </div>
              <label class="form-label mb-1">Date Range (Activation):</label>
              <div class="mb-3">
                <input type="date" name="from" class="form-control mb-1" placeholder="From">
                <input type="date" name="to" class="form-control" placeholder="To">
              </div>
              <label class="form-label mb-1">Format:</label>
              <select name="format" class="form-select mb-3">
                <option value="csv" selected>CSV</option>
                <option value="xlsx">Excel (.xlsx)</option>
              </select>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-success">Export</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>