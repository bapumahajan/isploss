<?php
session_name('oss_portal');
session_start();

require 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function sanitize($data) {
    return htmlspecialchars(trim($data));
}

$message = "";

// Delete logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    $id = intval($_POST['delete_id']);
    $type = $_POST['type'];

    if ($type === 'switch') {
        $table = 'switch_inventory';
    } elseif ($type === 'pop') {
        $table = 'pop_inventory';
    } elseif ($type === 'third_party') {
        $table = 'third_party';
    } else {
        die("Invalid type.");
    }

    $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $message = "<div class='alert alert-success'>Record deleted successfully.</div>";
}

// Add or Update logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_record'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    $type = $_POST['type'];

    if ($type === 'third_party') {
        $name = sanitize($_POST['name']);
        $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : null;

        $errors = [];

        if (empty($name)) {
            $errors[] = "Third party name cannot be empty.";
        }

        // Duplicate check
        $query = "SELECT COUNT(*) FROM third_party WHERE Third_party_name = ?";
        $params = [$name];
        if ($edit_id) {
            $query .= " AND id != ?";
            $params[] = $edit_id;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Duplicate third party name detected.";
        }

        if (empty($errors)) {
            if ($edit_id) {
                $stmt = $pdo->prepare("UPDATE third_party SET Third_party_name = ? WHERE id = ?");
                $stmt->execute([$name, $edit_id]);
                $message = "<div class='alert alert-success'>Third party record updated successfully.</div>";
            } else {
                $stmt = $pdo->prepare("INSERT INTO third_party (Third_party_name) VALUES (?)");
                $stmt->execute([$name]);
                $message = "<div class='alert alert-success'>New third party added successfully.</div>";
            }
        } else {
            $message = "<div class='alert alert-warning'><ul><li>" . implode("</li><li>", $errors) . "</li></ul></div>";
        }
    } else {
        // Existing POP and Switch logic
        $name = sanitize($_POST['name']);
        $ip = sanitize($_POST['ip']);
        $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : null;

        $errors = [];

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $errors[] = "Invalid IP address.";
        }

        $table = $type === 'switch' ? 'switch_inventory' : 'pop_inventory';
        $ip_column = $type === 'switch' ? 'switch_ip' : 'pop_ip';

        $query = "SELECT COUNT(*) FROM $table WHERE $ip_column = ?";
        $params = [$ip];
        if ($edit_id) {
            $query .= " AND id != ?";
            $params[] = $edit_id;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Duplicate IP detected.";
        }

        if (empty($errors)) {
            if ($edit_id) {
                $column = $type === 'switch' ? 'switch_name' : 'pop_name';
                $stmt = $pdo->prepare("UPDATE $table SET $column = ?, $ip_column = ? WHERE id = ?");
                $stmt->execute([$name, $ip, $edit_id]);
                $message = "<div class='alert alert-success'>Record updated successfully.</div>";
            } else {
                $column = $type === 'switch' ? 'switch_name' : 'pop_name';
                $stmt = $pdo->prepare("INSERT INTO $table ($column, $ip_column) VALUES (?, ?)");
                $stmt->execute([$name, $ip]);
                $message = "<div class='alert alert-success'>New record added successfully.</div>";
            }
        } else {
            $message = "<div class='alert alert-warning'><ul><li>" . implode("</li><li>", $errors) . "</li></ul></div>";
        }
    }
}

// Edit fetch
$edit_data = null;
if (isset($_GET['edit']) && isset($_GET['type'])) {
    $id = intval($_GET['edit']);
    $type = $_GET['type'];

    if ($type === 'switch') {
        $table = 'switch_inventory';
    } elseif ($type === 'pop') {
        $table = 'pop_inventory';
    } elseif ($type === 'third_party') {
        $table = 'third_party';
    } else {
        $table = null;
    }

    if ($table) {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Fetch all records
$pop_records = $pdo->query("SELECT * FROM pop_inventory ORDER BY created_at DESC")->fetchAll();
$switch_records = $pdo->query("SELECT * FROM switch_inventory ORDER BY created_at DESC")->fetchAll();
$third_party_records = $pdo->query("SELECT * FROM third_party ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Network Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">

    <a href="dashboard.php" class="btn btn-dark mb-4">← Dashboard</a>

    <?= $message ?>

    <div class="row g-4">
        <!-- POP Form -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?= $edit_data && $_GET['type'] === 'pop' ? "Edit POP" : "Add POP" ?></h5>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="type" value="pop">
                        <input type="hidden" name="save_record" value="1">
                        <?php if ($edit_data && $_GET['type'] === 'pop'): ?>
                            <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">POP Name</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['name'] ?? ($edit_data['pop_name'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">POP IP</label>
                            <input type="text" name="ip" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['ip'] ?? ($edit_data['pop_ip'] ?? '')) ?>">
                        </div>
                        <button class="btn btn-primary"><?= $edit_data && $_GET['type'] === 'pop' ? "Update" : "Add" ?></button>
                        <?php if ($edit_data): ?>
                            <a href="manage_inventory.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Switch Form -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?= $edit_data && $_GET['type'] === 'switch' ? "Edit Switch" : "Add Switch" ?></h5>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="type" value="switch">
                        <input type="hidden" name="save_record" value="1">
                        <?php if ($edit_data && $_GET['type'] === 'switch'): ?>
                            <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Switch Name</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['name'] ?? ($edit_data['switch_name'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Switch IP</label>
                            <input type="text" name="ip" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['ip'] ?? ($edit_data['switch_ip'] ?? '')) ?>">
                        </div>
                        <button class="btn btn-primary"><?= $edit_data && $_GET['type'] === 'switch' ? "Update" : "Add" ?></button>
                        <?php if ($edit_data): ?>
                            <a href="manage_inventory.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Third Party Form -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?= $edit_data && $_GET['type'] === 'third_party' ? "Edit Third Party" : "Add Third Party" ?></h5>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="type" value="third_party">
                        <input type="hidden" name="save_record" value="1">
                        <?php if ($edit_data && $_GET['type'] === 'third_party'): ?>
                            <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Third Party Name</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['name'] ?? ($edit_data['Third_party_name'] ?? '')) ?>">
                        </div>
                        <button class="btn btn-primary"><?= $edit_data && $_GET['type'] === 'third_party' ? "Update" : "Add" ?></button>
                        <?php if ($edit_data): ?>
                            <a href="manage_inventory.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- POP Table -->
    <h3 class="mt-5">POP Inventory</h3>
    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>POP Name</th>
                <th>POP IP</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pop_records as $pop): ?>
            <tr>
                <td><?= htmlspecialchars($pop['pop_name']) ?></td>
                <td><?= htmlspecialchars($pop['pop_ip']) ?></td>
                <td><?= $pop['created_at'] ?></td>
                <td>
                    <a href="?edit=<?= $pop['id'] ?>&type=pop" class="btn btn-sm btn-warning">Edit</a>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete POP?')">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="delete_id" value="<?= $pop['id'] ?>">
                        <input type="hidden" name="type" value="pop">
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($pop_records)): ?>
            <tr><td colspan="4" class="text-muted text-center">No POP records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Switch Table -->
    <h3 class="mt-5">Switch Inventory</h3>
    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>Switch Name</th>
                <th>Switch IP</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($switch_records as $switch): ?>
            <tr>
                <td><?= htmlspecialchars($switch['switch_name']) ?></td>
                <td><?= htmlspecialchars($switch['switch_ip']) ?></td>
                <td><?= $switch['created_at'] ?></td>
                <td>
                    <a href="?edit=<?= $switch['id'] ?>&type=switch" class="btn btn-sm btn-warning">Edit</a>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete Switch?')">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="delete_id" value="<?= $switch['id'] ?>">
                        <input type="hidden" name="type" value="switch">
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($switch_records)): ?>
            <tr><td colspan="4" class="text-muted text-center">No switch records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Third Party Table -->
    <h3 class="mt-5">Third Party Names</h3>
    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>Name</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($third_party_records as $party): ?>
            <tr>
                <td><?= htmlspecialchars($party['Third_party_name']) ?></td>
                <td><?= $party['created_at'] ?></td>
                <td>
                    <a href="?edit=<?= $party['id'] ?>&type=third_party" class="btn btn-sm btn-warning">Edit</a>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this third party?')">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="delete_id" value="<?= $party['id'] ?>">
                        <input type="hidden" name="type" value="third_party">
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($third_party_records)): ?>
            <tr><td colspan="3" class="text-muted text-center">No third party records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>