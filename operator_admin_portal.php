<?php
require_once 'config/database.php';

session_name('oss_portal');
session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=()');

// Session timeout and auth
$timeout_duration = 900;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'user';

$message = '';
$active_tab = $_GET['tab'] ?? 'operator';

// Add Operator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_operator'])) {
    $name = trim($_POST['operator_name']);
    $desc = trim($_POST['description']);

    // Check for duplicate operator
    $check = $pdo->prepare("SELECT id FROM operators WHERE name = ?");
    $check->execute([$name]);
    if ($check->rowCount() > 0) {
        $message = "<span class='text-danger'>Operator already exists!</span>";
        $active_tab = 'operator';
    } else {
        $stmt = $pdo->prepare("INSERT INTO operators (name, description, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$name, $desc]);
        $message = "<span class='text-success'>Operator added!</span>";
        $active_tab = 'operator';
    }
}

// Add Operator Table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_operator_table'])) {
    $operator_id = intval($_POST['operator_id']);
    $table_name = trim($_POST['table_name']);

    // Prevent duplicate table names per operator
    $stmt = $pdo->prepare("SELECT id FROM operator_tables WHERE operator_id = ? AND table_name = ?");
    $stmt->execute([$operator_id, $table_name]);
    if ($stmt->rowCount() > 0) {
        $message = "<span style='color:red;'>Table name already exists for this operator!</span>";
        $active_tab = 'tables';
    } else {
        // Validate table name (alphanumeric, underscores, hyphens)
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $table_name)) {
            $message = "<span style='color:red;'>Invalid table name! Use only letters, numbers, underscores, and hyphens.</span>";
            $active_tab = 'tables';
        } else {
            // Create metadata
            $stmt = $pdo->prepare("INSERT INTO operator_tables (operator_id, table_name, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$operator_id, $table_name]);

            // Create actual table with circuit_id as PK and FK
            $sql = "
                CREATE TABLE IF NOT EXISTS `$table_name` (
                    circuit_id VARCHAR(255) PRIMARY KEY,
                    FOREIGN KEY (circuit_id) REFERENCES customer_basic_information(circuit_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            try {
                $pdo->exec($sql);
                $message = "<span style='color:green;'>Table created for operator!</span>";
                $active_tab = 'tables';
            } catch (PDOException $e) {
                $message = "<span style='color:red;'>Error creating table: " . htmlspecialchars($e->getMessage()) . "</span>";
                // Rollback metadata if table creation fails
                $delete = $pdo->prepare("DELETE FROM operator_tables WHERE operator_id = ? AND table_name = ?");
                $delete->execute([$operator_id, $table_name]);
                $active_tab = 'tables';
            }
        }
    }
}

// Manage Operator Tables (delete)
if (isset($_GET['delete_table']) && is_numeric($_GET['delete_table'])) {
    $table_id = intval($_GET['delete_table']);

    // Get table name
    $stmt = $pdo->prepare("SELECT table_name FROM operator_tables WHERE id = ?");
    $stmt->execute([$table_id]);
    $row = $stmt->fetch();
    if ($row) {
        $table_name = $row['table_name'];

        // Drop actual table
        $pdo->exec("DROP TABLE IF EXISTS `$table_name`");

        // Delete metadata
        $pdo->prepare("DELETE FROM operator_fields WHERE table_id = ?")->execute([$table_id]);
        $pdo->prepare("DELETE FROM operator_tables WHERE id = ?")->execute([$table_id]);

        $message = "Table deleted!";
        $active_tab = 'tables';
    }
}

// Add/Manage Table Fields
$tables = $pdo->query("SELECT t.id, t.table_name, o.name as operator_name 
    FROM operator_tables t 
    JOIN operators o ON t.operator_id = o.id 
    ORDER BY o.name, t.table_name")->fetchAll();

$current_fields = [];
$selected_table_id = isset($_POST['table_id']) ? intval($_POST['table_id']) : 0;
if (isset($_GET['table_id'])) $selected_table_id = intval($_GET['table_id']);

// Normalize field name to use underscores
function normalize_field_name($name) {
    return preg_replace('/[\s\-]+/', '_', trim($name));
}

// Add Field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_field'])) {
    $table_id = intval($_POST['table_id']);
    $field_name = normalize_field_name($_POST['field_name']);
    $field_type = trim($_POST['field_type']);
    $required = isset($_POST['required']) ? 1 : 0;

    // Get table name
    $stmt = $pdo->prepare("SELECT table_name FROM operator_tables WHERE id = ?");
    $stmt->execute([$table_id]);
    $row = $stmt->fetch();
    if ($row) {
        $table_name = $row['table_name'];

        // Check if field already exists in metadata
        $stmt2 = $pdo->prepare("SELECT id FROM operator_fields WHERE table_id = ? AND field_name = ?");
        $stmt2->execute([$table_id, $field_name]);
        if ($stmt2->rowCount() > 0) {
            $message = "Field already exists in this table!";
            $active_tab = 'fields';
        } else {
            // Add to metadata
            $stmt3 = $pdo->prepare("INSERT INTO operator_fields (table_id, field_name, field_type, required) VALUES (?, ?, ?, ?)");
            $stmt3->execute([$table_id, $field_name, $field_type, $required]);
            // Handle ENUM field type
            if ($field_type == 'ENUM') {
                $enum_values = isset($_POST['enum_values']) ? trim($_POST['enum_values']) : '';
                $enum_array = array_map('trim', explode(',', $enum_values));
                if (count($enum_array) < 1 || empty($enum_array[0])) {
                    $message = "ENUM values required!";
                    $active_tab = 'fields';
                } else {
                    $enum_sql = "ENUM('" . implode("','", array_map('addslashes', $enum_array)) . "')";
                    $sql = "ALTER TABLE `$table_name` ADD `$field_name` $enum_sql " . ($required ? "NOT NULL" : "NULL");
                    $pdo->exec($sql);
                    $message = "ENUM Field added!";
                    $active_tab = 'fields';
                }
            } else {
                $sql = "ALTER TABLE `$table_name` ADD `$field_name` $field_type " . ($required ? "NOT NULL" : "NULL");
                $pdo->exec($sql);
                $message = "Field added!";
                $active_tab = 'fields';
            }
        }
    } else {
        $message = "Table not found.";
        $active_tab = 'fields';
    }
}

// Update Fields
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fields'])) {
    if (isset($_POST['fields']) && is_array($_POST['fields'])) {
        foreach ($_POST['fields'] as $fid => $fvals) {
            $field_id = intval($fid);
            $field_type = trim($fvals['type']);
            $required = isset($fvals['required']) ? 1 : 0;

            $stmt = $pdo->prepare("SELECT * FROM operator_fields WHERE id = ?");
            $stmt->execute([$field_id]);
            $field = $stmt->fetch();

            if ($field) {
                $stmt2 = $pdo->prepare("SELECT table_name FROM operator_tables WHERE id = ?");
                $stmt2->execute([$field['table_id']]);
                $row = $stmt2->fetch();
                if ($row) {
                    $table_name = $row['table_name'];
                    $field_name = $field['field_name'];
                    $stmt3 = $pdo->prepare("UPDATE operator_fields SET field_type = ?, required = ? WHERE id = ?");
                    $stmt3->execute([$field_type, $required, $field_id]);
                    if ($field_type == 'ENUM') {
                        $enum_values = isset($fvals['enum_values']) ? trim($fvals['enum_values']) : '';
                        $enum_array = array_map('trim', explode(',', $enum_values));
                        if (count($enum_array) < 1 || empty($enum_array[0])) {
                            $message = "ENUM values required!";
                            $active_tab = 'fields';
                        } else {
                            $enum_sql = "ENUM('" . implode("','", array_map('addslashes', $enum_array)) . "')";
                            $sql = "ALTER TABLE `$table_name` MODIFY `$field_name` $enum_sql " . ($required ? "NOT NULL" : "NULL");
                            $pdo->exec($sql);
                        }
                    } else {
                        $sql = "ALTER TABLE `$table_name` MODIFY `$field_name` $field_type " . ($required ? "NOT NULL" : "NULL");
                        $pdo->exec($sql);
                    }
                }
            }
        }
        $message = "Fields updated!";
        $active_tab = 'fields';
    }
}

// Delete Field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_field'])) {
    $field_id = intval($_POST['delete_field']);
    $stmt = $pdo->prepare("SELECT * FROM operator_fields WHERE id = ?");
    $stmt->execute([$field_id]);
    $field = $stmt->fetch();

    if ($field) {
        $stmt2 = $pdo->prepare("SELECT table_name FROM operator_tables WHERE id = ?");
        $stmt2->execute([$field['table_id']]);
        $row = $stmt2->fetch();
        if ($row) {
            $table_name = $row['table_name'];
            $field_name = $field['field_name'];
            $sql = "ALTER TABLE `$table_name` DROP COLUMN `$field_name`";
            $pdo->exec($sql);
            $stmt3 = $pdo->prepare("DELETE FROM operator_fields WHERE id = ?");
            $stmt3->execute([$field_id]);
            $message = "Field deleted!";
            $selected_table_id = $field['table_id'];
            $active_tab = 'fields';
        }
    }
}

// Get fields for selected table
if ($selected_table_id) {
    $fields_stmt = $pdo->prepare("SELECT * FROM operator_fields WHERE table_id = ? ORDER BY id ASC");
    $fields_stmt->execute([$selected_table_id]);
    $current_fields = $fields_stmt->fetchAll();
}

// Get operators for dropdown
$operators = $pdo->query("SELECT id, name FROM operators ORDER BY name")->fetchAll();

// For manage tables
$tables_manage = $tables;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Operator Management Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        body { background: #f9fafb; font-size: 13px;}
        .navbar-custom { background-color: #003049; }
        .navbar-custom .navbar-brand, .navbar-custom .nav-link { color: #f1faee !important; }
        .navbar-custom .nav-link.active { background-color: #f77f00; border-radius: 5px; color: #fff !important; }
        .card { border: none; box-shadow: 0 4px 10px rgba(0,0,0,0.08); margin-bottom: 18px;}
        .card-title { color: #003049; font-size: 1.1rem; }
        .form-label { font-weight: 500; color: #003049; font-size: 13px;}
        .btn, .form-control, .form-select { font-size: 13px !important; }
        .section-title { color: #003049; font-size: 1.1rem;}
        .table th, .table td { font-size: 13px !important; }
        .table th { background: #003049; color: #fff;}
        .table-actions button { margin-right: 3px; }
        .alert { font-size: 13px; padding: 8px 14px; }
        .nav-pills .nav-link.active { background: #f77f00; }
        .nav-pills .nav-link { color: #003049; }
        #enum_values_field { min-width: 200px;}
        .top-link {
            position: absolute;
            top: 13px;
            right: 16px;
            z-index: 10;
        }
        @media (max-width: 700px) {
            .top-link { font-size: 12px; right: 10px; top: 8px; }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
    <div class="container-fluid px-4 position-relative">
        <a class="navbar-brand" href="operator_portal.php"><i class="bi bi-diagram-3 me-2"></i>Operator Admin Portal</a>
        <a href="operator_portal.php" class="top-link btn btn-sm btn-outline-warning" title="Go to Operator Portal">
            <i class="bi bi-house-door-fill"></i> Operator Portal
        </a>
    </div>
</nav>
<div class="container my-5">
    <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab=='operator'?'active':'' ?>" href="?tab=operator">Add Operator</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab=='tables'?'active':'' ?>" href="?tab=tables">Add/Manage Tables</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $active_tab=='fields'?'active':'' ?>" href="?tab=fields">Add/Manage Table Fields</a>
        </li>
    </ul>
    <?php if ($message) echo "<div class='alert alert-info mb-3'>$message</div>"; ?>
    <div class="tab-content" id="pills-tabContent">
        <!-- Add Operator -->
        <div class="tab-pane fade <?= $active_tab=='operator'?'show active':'' ?>" id="add-operator">
            <div class="card p-3">
                <h4 class="card-title mb-3"><i class="bi bi-person-plus-fill"></i> Add Third-Party Operator</h4>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="add_operator" value="1">
                    <div class="mb-3">
                        <label class="form-label">Operator Name</label>
                        <input type="text" name="operator_name" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg"></i> Add Operator</button>
                </form>
            </div>
        </div>
        <!-- Add/Manage Operator Tables -->
        <div class="tab-pane fade <?= $active_tab=='tables'?'show active':'' ?>" id="manage-tables">
            <div class="card p-3 mb-3">
                <h4 class="card-title mb-3"><i class="bi bi-table"></i> Add Operator Table</h4>
                <form method="post">
                    <input type="hidden" name="add_operator_table" value="1">
                    <div class="mb-3">
                        <label class="form-label">Select Operator:</label>
                        <select name="operator_id" class="form-select" required>
                            <option value="">Select</option>
                            <?php foreach ($operators as $op): ?>
                                <option value="<?= $op['id'] ?>"><?= htmlspecialchars($op['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Table Name:</label>
                        <input type="text" name="table_name" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Table</button>
                </form>
            </div>
            <div class="card p-3">
                <h4 class="card-title mb-3"><i class="bi bi-gear"></i> Manage Operator Tables</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Operator</th>
                                <th>Table Name</th>
                                <th style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tables_manage as $t): ?>
                            <tr>
                                <td><?= htmlspecialchars($t['operator_name']) ?></td>
                                <td><?= htmlspecialchars($t['table_name']) ?></td>
                                <td style="text-align:center;">
                                    <a href="?tab=fields&table_id=<?= $t['id'] ?>" class="btn btn-sm btn-warning">Add/Manage Fields</a>
                                    <a href="?tab=tables&delete_table=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure to delete this table?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Add/Manage Table Fields -->
        <div class="tab-pane fade <?= $active_tab=='fields'?'show active':'' ?>" id="manage-fields">
            <div class="card p-3">
                <form method="post" class="mb-3">
                    <label class="form-label">Select Table:</label>
                    <select name="table_id" class="form-select form-select-sm" required style="max-width: 350px; display: inline-block;" onchange="this.form.submit()">
                        <option value="">Select</option>
                        <?php foreach ($tables as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $selected_table_id == $t['id'] ? "selected" : "" ?>>
                                <?= htmlspecialchars($t['operator_name'] . ' - ' . $t['table_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($selected_table_id): ?>
                    <div class="mb-3">
                        <h4 class="section-title"><i class="bi bi-list-ul"></i> Existing Fields</h4>
                        <form method="post">
                        <input type="hidden" name="table_id" value="<?= $selected_table_id ?>">
                        <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Field Name</th>
                                    <th>Type</th>
                                    <th>ENUM Values</th>
                                    <th>Required</th>
                                    <th style="text-align:center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($current_fields as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['field_name']) ?></td>
                                    <td>
                                        <select name="fields[<?= $f['id'] ?>][type]" class="form-select form-select-sm enum-type-select">
                                            <option value="VARCHAR(255)" <?= $f['field_type'] == "VARCHAR(255)" ? "selected" : "" ?>>Text</option>
                                            <option value="INT" <?= $f['field_type'] == "INT" ? "selected" : "" ?>>Integer</option>
                                            <option value="DATE" <?= $f['field_type'] == "DATE" ? "selected" : "" ?>>Date</option>
                                            <option value="DATETIME" <?= $f['field_type'] == "DATETIME" ? "selected" : "" ?>>Datetime</option>
                                            <option value="FLOAT" <?= $f['field_type'] == "FLOAT" ? "selected" : "" ?>>Float</option>
                                            <option value="TEXT" <?= $f['field_type'] == "TEXT" ? "selected" : "" ?>>Long Text</option>
                                            <option value="ENUM" <?= $f['field_type'] == "ENUM" ? "selected" : "" ?>>ENUM</option>
                                        </select>
                                    </td>
                                    <td>
                                        <?php if ($f['field_type'] == "ENUM"): ?>
                                            <input type="text" name="fields[<?= $f['id'] ?>][enum_values]" class="form-control form-control-sm" placeholder="e.g. Active,Inactive">
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <input type="checkbox" name="fields[<?= $f['id'] ?>][required]" value="1" <?= $f['required'] ? "checked" : "" ?>>
                                    </td>
                                    <td class="table-actions" style="text-align:center;">
                                        <button type="submit" name="delete_field" value="<?= $f['id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete field? This will remove the column and all its data!')"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <div class="mt-2">
                            <button type="submit" name="update_fields" class="btn btn-sm btn-primary"><i class="bi bi-check2-square"></i> Update Selected Fields</button>
                        </div>
                        </form>
                    </div>
                    <div>
                        <h4 class="section-title"><i class="bi bi-plus-circle"></i> Add New Field</h4>
                        <form method="post" class="form-inline row g-2 align-items-end">
                            <input type="hidden" name="table_id" value="<?= $selected_table_id ?>">
                            <div class="col-auto">
                                <label class="form-label">Field Name:</label>
                                <input type="text" name="field_name" class="form-control form-control-sm" required placeholder="Only letters, numbers, underscore">
                            </div>
                            <div class="col-auto">
                                <label class="form-label">Field Type:</label>
                                <select name="field_type" class="form-select form-select-sm" required id="add_field_type">
                                    <option value="VARCHAR(255)">Text</option>
                                    <option value="INT">Integer</option>
                                    <option value="DATE">Date</option>
                                    <option value="DATETIME">Datetime</option>
                                    <option value="FLOAT">Float</option>
                                    <option value="TEXT">Long Text</option>
                                    <option value="ENUM">ENUM</option>
                                </select>
                            </div>
                            <div class="col-auto" id="enum_values_field" style="display:none;">
                                <label class="form-label">ENUM values (comma separated, e.g. Active,Inactive):</label>
                                <input type="text" name="enum_values" class="form-control form-control-sm" placeholder="e.g. Active,Inactive">
                            </div>
                            <div class="col-auto">
                                <label class="form-label">Required?</label>
                                <input type="checkbox" name="required" value="1">
                            </div>
                            <div class="col-auto">
                                <input type="submit" name="add_field" class="btn btn-success btn-sm" value="Add Field">
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('add_field_type')?.addEventListener('change', function() {
    document.getElementById('enum_values_field').style.display = this.value === 'ENUM' ? '' : 'none';
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>