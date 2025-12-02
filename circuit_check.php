<?php
session_name('oss_portal');
session_start();
include 'includes/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$errorMessage = '';
$successMessage = '';
$highlight_circuit_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_circuit_id'])) {
    $circuit_id_to_delete = $_POST['delete_circuit_id'];
    try {
        $pdo->prepare("DELETE FROM network_details WHERE circuit_id = ?")->execute([$circuit_id_to_delete]);
        $pdo->prepare("DELETE FROM customer_basic_information WHERE circuit_id = ?")->execute([$circuit_id_to_delete]);
        $successMessage = "Circuit ID '$circuit_id_to_delete' deleted successfully.";
    } catch (PDOException $e) {
        $errorMessage = "Error deleting circuit: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['circuit_id_search'])) {
    $circuit_id_search = trim($_POST['circuit_id_search']);
    if ($circuit_id_search === '') {
        $errorMessage = "Circuit ID cannot be empty.";
    } else {
        $stmt = $pdo->prepare("SELECT 1 FROM customer_basic_information WHERE circuit_id = ?");
        $stmt->execute([$circuit_id_search]);
        $cust_exists = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT 1 FROM network_details WHERE circuit_id = ?");
        $stmt->execute([$circuit_id_search]);
        $net_exists = $stmt->fetchColumn();

        if ($cust_exists && !$net_exists) {
            $highlight_circuit_id = $circuit_id_search;
        } elseif ($cust_exists && $net_exists) {
            $successMessage = "Circuit ID '$circuit_id_search' exists in both tables.";
        } else {
            $errorMessage = "Circuit ID not found in basic information. Please add the customer first.";
        }
    }
}

$missing = [];
$missingStmt = $pdo->query("
    SELECT cbi.circuit_id, cbi.organization_name
    FROM customer_basic_information AS cbi
    LEFT JOIN network_details AS nd ON cbi.circuit_id = nd.circuit_id
    WHERE nd.circuit_id IS NULL
    ORDER BY cbi.circuit_id
");
if ($missingStmt && $missingStmt->rowCount() > 0) {
    $missing = $missingStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Check Circuit ID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
            font-size: 14px;
        }
        .card {
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .highlight-row {
            background-color: #fff3cd;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .table th, .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="card">
        <div class="topbar">
            <h4 class="mb-0">Check Circuit ID</h4>
            <a href="dashboard.php" class="btn btn-outline-secondary">← Back to Dashboard</a>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger text-center"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
        <?php if ($successMessage): ?>
            <div class="alert alert-success text-center"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <form method="POST" class="row g-3 mb-4">
            <div class="col-md-10">
                <input type="text" class="form-control" name="circuit_id_search" placeholder="Enter Circuit ID e.g. ISPL090525001" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>
        </form>

        <?php if (!empty($missing)): ?>
            <h6>Circuits Missing Network Details:</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Circuit ID</th>
                            <th>Organization</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($missing as $row): ?>
                        <?php
                            $is_highlighted = $highlight_circuit_id === $row['circuit_id'];
                            $row_class = $is_highlighted ? 'highlight-row' : '';
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td><?= htmlspecialchars($row['circuit_id']) ?></td>
                            <td><?= htmlspecialchars($row['organization_name']) ?></td>
                            <td class="text-center">
                                <a href="add_network.php?circuit_id=<?= urlencode($row['circuit_id']) ?>" class="btn btn-success btn-sm">Add Network</a>
                                <form method="POST" action="circuit_check.php" style="display:inline;" onsubmit="return confirm('Delete this circuit?');">
                                    <input type="hidden" name="delete_circuit_id" value="<?= htmlspecialchars($row['circuit_id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm ms-2">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">All circuits have network details.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
