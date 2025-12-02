<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_name('oss_portal');
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'includes/db.php';
require_once 'includes/audit.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

function retain_arr($key, $i) {
    return isset($_POST[$key][$i]) ? htmlspecialchars($_POST[$key][$i]) : '';
}

$username = $_SESSION['username'];
$errors = [];
$message = '';
$show_table_view = false;

$step = 'customer'; // default step

// --- Step 1: Customer Creation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_submit'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    }
    $organization_name     = trim($_POST['organization_name']);
    $customer_address      = trim($_POST['customer_address']);
    $city                  = trim($_POST['city']);
    $contact_person_name   = trim($_POST['contact_person_name']);
    $contact_numbers       = $_POST['contact_number'] ?? [];
    $ce_email_ids          = $_POST['ce_email_id'] ?? [];

    if (!preg_match('/^[a-zA-Z\s\.\-&()]+$/', $organization_name)) $errors[] = "Organization name contains invalid characters.";
    if (!preg_match('/^[a-zA-Z\s\.\-&()]+$/', $contact_person_name)) $errors[] = "Contact person name contains invalid characters.";
    if (!preg_match('/^[a-zA-Z\s\.\-&()]+$/', $city)) $errors[] = "City contains invalid characters.";

    foreach($contact_numbers as $num) {
        if (!preg_match('/^\d{10,15}$/', $num)) {
            $errors[] = "Each contact number must be 10–15 digits.";
            break;
        }
    }
    foreach($ce_email_ids as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Each email must be valid.";
            break;
        }
    }

    $circuit_mode = $_POST['circuit_mode'] ?? 'auto';

    if ($circuit_mode === 'manual') {
        $manual_circuit_id = trim($_POST['manual_circuit_id']);
        if (!preg_match('/^[A-Za-z0-9\-]{5,100}$/', $manual_circuit_id)) {
            $errors[] = "Invalid manual circuit ID. Only alphanumeric and dashes, 5–100 chars.";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM customer_basic_information WHERE circuit_id = ?");
            $stmt->execute([$manual_circuit_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Circuit ID already exists. Please choose a unique ID.";
            } else {
                $circuit_id = $manual_circuit_id;
            }
        }
    } else {
        $date = date('dmy');
        $prefix = "ISPL";
        $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM customer_basic_information WHERE circuit_id LIKE ?");
        $stmt->execute(["$prefix$date%"]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
        $circuit_id = $prefix . $date . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    if (empty($errors)) {
        try {
            $new_customer = [
                'circuit_id'           => $circuit_id,
                'organization_name'    => $organization_name,
                'customer_address'     => $customer_address,
                'city'                 => $city,
                'contact_person_name'  => $contact_person_name
            ];
            $new_contacts = array_values(array_filter(array_map('trim', $contact_numbers)));
            $new_emails   = array_values(array_filter(array_map('trim', $ce_email_ids)));

            $stmt = $pdo->prepare("INSERT INTO customer_basic_information 
                (circuit_id, organization_name, customer_address, city, contact_person_name) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $circuit_id,
                $organization_name,
                $customer_address,
                $city,
                $contact_person_name
            ]);

            $stmt_contact = $pdo->prepare("INSERT INTO customer_contacts (circuit_id, contact_number) VALUES (?, ?)");
            foreach ($new_contacts as $num) {
                if ($num !== '') {
                    $stmt_contact->execute([$circuit_id, $num]);
                }
            }

            $stmt_email = $pdo->prepare("INSERT INTO customer_emails (circuit_id, ce_email_id) VALUES (?, ?)");
            foreach ($new_emails as $email) {
                if ($email !== '') {
                    $stmt_email->execute([$circuit_id, $email]);
                }
            }

            log_activity($pdo, $username, 'insert', 'customer_basic_information', $circuit_id, "Added: " . json_encode(['old'=>null, 'new'=>$new_customer]));
            if (!empty($new_contacts)) {
                log_activity($pdo, $username, 'insert', 'customer_contacts', $circuit_id, "Added: " . json_encode(['old'=>null, 'new'=>$new_contacts]));
            }
            if (!empty($new_emails)) {
                log_activity($pdo, $username, 'insert', 'customer_emails', $circuit_id, "Added: " . json_encode(['old'=>null, 'new'=>$new_emails]));
            }

            // Redirect to the same file with circuit_id
            unset($_SESSION['csrf_token']);
            header("Location: customer_and_add-nni_detail.php?circuit_id=$circuit_id");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// --- Step 2: Third Party Circuit Addition ---
if (isset($_GET['circuit_id']) && $_GET['circuit_id']) {
    $step = 'third_party';
    $circuit_id = $_GET['circuit_id'];
    $stmt = $pdo->prepare("SELECT circuit_id, organization_name, customer_address, city, contact_person_name 
                           FROM customer_basic_information WHERE circuit_id = ?");
    $stmt->execute([$circuit_id]);
    $customer_details = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Always fetch operators before rendering Operator dropdown!
$operators = $pdo->query("SELECT id, name FROM operators ORDER BY name")->fetchAll();

// --- AJAX handler for loading operator fields (handles ENUMs) ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fields' && isset($_GET['operator_id'])) {
    $operator_id = intval($_GET['operator_id']);
    $table = $pdo->prepare("SELECT id, table_name FROM operator_tables WHERE operator_id = ?");
    $table->execute([$operator_id]);
    $table_row = $table->fetch();
    if (!$table_row) {
        echo json_encode([]);
        exit;
    }
    $fields = $pdo->prepare("SELECT field_name, field_type, required FROM operator_fields WHERE table_id = ?");
    $fields->execute([$table_row['id']]);
    $fields_arr = [];
    foreach ($fields->fetchAll() as $field) {
        // If ENUM, get possible values
        if (strtoupper($field['field_type']) === 'ENUM') {
            $col = $pdo->query("SHOW COLUMNS FROM `{$table_row['table_name']}` LIKE '{$field['field_name']}'")->fetch();
            $enum_options = [];
            if ($col && preg_match("/^enum\((.*)\)$/i", $col['Type'], $matches)) {
                $enum_options = array_map(function($v) { return trim($v,"'"); }, explode(",", $matches[1]));
            }
            $field['enum_options'] = $enum_options;
        }
        $fields_arr[] = $field;
    }
    echo json_encode([
        'table_name' => $table_row['table_name'],
        'fields' => $fields_arr
    ]);
    exit;
}

// --- Handle Third Party form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['third_party_submit'])) {
    $circuit_id = $_POST['circuit_id'];
    $operator_id = intval($_POST['operator_id']);

    // Get operator table
    $table = $pdo->prepare("SELECT id, table_name FROM operator_tables WHERE operator_id = ?");
    $table->execute([$operator_id]);
    $table_row = $table->fetch();
    if (!$table_row) {
        $message = "<span class='text-danger'>Operator table not found!</span>";
    } else {
        $error = false;

        // 1. Check if circuit_id exists in customer_basic_information
        $validCheck = $pdo->prepare("SELECT COUNT(*) FROM customer_basic_information WHERE circuit_id = ?");
        $validCheck->execute([$circuit_id]);
        if ($validCheck->fetchColumn() == 0) {
            $message = "<span class='text-danger'>Selected Circuit ID does not exist in customer records.</span>";
            $error = true;
        }

        // 2. Check for duplicate circuit_id in ANY operator table
        if (!$error) {
            $tables = $pdo->query("SELECT table_name FROM operator_tables")->fetchAll(PDO::FETCH_COLUMN);
            $is_used = false;
            foreach ($tables as $t) {
                $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM `$t` WHERE circuit_id = ?");
                $dupCheck->execute([$circuit_id]);
                if ($dupCheck->fetchColumn() > 0) {
                    $is_used = true;
                    break;
                }
            }
            if ($is_used) {
                $message = "<span class='text-danger'>This Circuit ID is already added for another operator!</span>";
                $error = true;
            }
        }

        // 3. Prepare field data for insert (only if no errors above)
        if (!$error) {
            $fields_stmt = $pdo->prepare("SELECT field_name, field_type, required FROM operator_fields WHERE table_id = ?");
            $fields_stmt->execute([$table_row['id']]);
            $fields = $fields_stmt->fetchAll();
            $field_names = [];
            $placeholders = [];
            $values = [];

            foreach ($fields as $f) {
                $fname = $f['field_name'];
                $ftype = strtolower($f['field_type']);
                $frequired = $f['required'];
                $input_val = $_POST[$fname] ?? '';

                // Required check
                if ($frequired == "1" && empty($input_val)) {
                    $message = "<span class='text-danger'>Field '{$fname}' is required and cannot be empty.</span>";
                    $error = true;
                    break;
                }

                // Type checks
                if (!empty($input_val)) {
                    if ($ftype === "int" && !preg_match('/^\d+$/', $input_val)) {
                        $message = "<span class='text-danger'>Field '{$fname}' should be a number.</span>";
                        $error = true;
                        break;
                    }
                    if ($ftype === "date" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input_val)) {
                        $message = "<span class='text-danger'>Field '{$fname}' should be a date (YYYY-MM-DD).</span>";
                        $error = true;
                        break;
                    }
                    if ($ftype === "varchar" && strlen($input_val) > 255) {
                        $message = "<span class='text-danger'>Field '{$fname}' should be max 255 characters.</span>";
                        $error = true;
                        break;
                    }
                    if ($ftype === "enum") {
                        $col = $pdo->query("SHOW COLUMNS FROM `{$table_row['table_name']}` LIKE '{$fname}'")->fetch();
                        $enum_options = [];
                        if ($col && preg_match("/^enum\((.*)\)$/i", $col['Type'], $matches)) {
                            $enum_options = array_map(function($v) { return trim($v,"'"); }, explode(",", $matches[1]));
                        }
                        if (!in_array($input_val, $enum_options)) {
                            $message = "<span class='text-danger'>Field '{$fname}' has invalid value.</span>";
                            $error = true;
                            break;
                        }
                    }
                }

                // Defaults for date
                if ($ftype == "date" && empty($input_val)) {
                    $input_val = date('Y-m-d');
                }

                $field_names[] = "`$fname`";
                $placeholders[] = "?";
                $values[] = $input_val;
            }

            // Add circuit_id as first column (as PK)
            $field_names[] = "`circuit_id`";
            $placeholders[] = "?";
            $values[] = $circuit_id;

            // 4. Insert only if all checks pass
            if (!$error) {
                $sql = "INSERT INTO `{$table_row['table_name']}` (" . implode(",", $field_names) . ") VALUES (" . implode(",", $placeholders) . ")";
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                    $message = "<span class='text-success'>Third party details saved!</span>";
                    $show_table_view = true;
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $message = "<span class='text-danger'>Duplicate entry or invalid circuit_id (referential integrity failed)!</span>";
                    } else {
                        $message = "<span class='text-danger'>Database error: " . htmlspecialchars($e->getMessage()) . "</span>";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= ($step === 'customer') ? 'Add Customer' : 'Add NNI Partner Details' ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <style>
        body { background-color: #f8f9fa; font-size: 0.9rem; }
        .container { max-width: 720px; }
        .form-label { margin-bottom: 0.2rem; }
        .card { border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.05); }
        .navbar-custom { background-color: #003049; }
        .navbar-custom .navbar-brand, .navbar-custom .nav-link { color: #f1faee !important; }
        .navbar-custom .nav-link.active { background-color: #f77f00; border-radius: 5px; color: #fff !important; }
        .card-title { color: #003049; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="operator_portal.php"><i class="bi bi-diagram-3 me-2"></i>Operator Portal</a>
    </div>
</nav>
<div class="container mt-4">
    <?php if ($step === 'customer'): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Add New Customer</h4>
            <a href="dashboard.php" class="btn btn-sm btn-secondary">← Back to Dashboard</a>
        </div>
        <div class="card p-4">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="POST" onsubmit="return validateForm();">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="mb-2">
                    <label class="form-label">Circuit ID Option</label>
                    <select id="circuit_mode" class="form-select" name="circuit_mode" onchange="toggleCircuitInput()">
                        <option value="auto" <?= (isset($_POST['circuit_mode']) && $_POST['circuit_mode'] === 'manual') ? '' : 'selected' ?>>Auto-generate</option>
                        <option value="manual" <?= (isset($_POST['circuit_mode']) && $_POST['circuit_mode'] === 'manual') ? 'selected' : '' ?>>Manual</option>
                    </select>
                </div>
                <div class="mb-2" id="manual_circuit_group" style="display: <?= (isset($_POST['circuit_mode']) && $_POST['circuit_mode'] === 'manual') ? 'block' : 'none' ?>;">
                    <label class="form-label">Enter Circuit ID</label>
                    <input type="text" class="form-control" name="manual_circuit_id"
                           value="<?= isset($_POST['manual_circuit_id']) ? htmlspecialchars($_POST['manual_circuit_id']) : '' ?>"
                           pattern="[A-Za-z0-9\-]{5,100}" title="Alphanumeric, 5–100 chars" maxlength="100">
                </div>
                <div class="mb-2">
                    <label class="form-label">Organization Name</label>
                    <input type="text" class="form-control" name="organization_name" maxlength="100" required
                           pattern="[a-zA-Z\s\.\-&()]{2,100}" title="Only letters, spaces, .-&() allowed"
                           value="<?= isset($_POST['organization_name']) ? htmlspecialchars($_POST['organization_name']) : '' ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label">Customer Address</label>
                    <textarea class="form-control" name="customer_address" maxlength="255" required><?= isset($_POST['customer_address']) ? htmlspecialchars($_POST['customer_address']) : '' ?></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label">City</label>
                    <input type="text" class="form-control" name="city" maxlength="50" required
                           pattern="[a-zA-Z\s\.\-&()]{2,50}" title="Only letters, spaces, .-&() allowed"
                           value="<?= isset($_POST['city']) ? htmlspecialchars($_POST['city']) : '' ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label">Contact Person Name</label>
                    <input type="text" class="form-control" name="contact_person_name" maxlength="100" required
                           pattern="[a-zA-Z\s\.\-&()]{2,100}" title="Only letters, spaces, .-&() allowed"
                           value="<?= isset($_POST['contact_person_name']) ? htmlspecialchars($_POST['contact_person_name']) : '' ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label">Contact Numbers</label>
                    <div id="contacts">
                        <?php
                            $contact_numbers = $_POST['contact_number'] ?? [''];
                            foreach ($contact_numbers as $i => $num):
                        ?>
                        <div class="input-group mb-1">
                            <input type="tel" class="form-control" name="contact_number[]" maxlength="15" required
                                   pattern="\d{10,15}" title="10 to 15 digits"
                                   value="<?= retain_arr('contact_number', $i) ?>">
                            <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addContactField()">Add Contact</button>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email IDs</label>
                    <div id="emails">
                        <?php
                            $ce_email_ids = $_POST['ce_email_id'] ?? [''];
                            foreach ($ce_email_ids as $i => $email):
                        ?>
                        <div class="input-group mb-1">
                            <input type="email" class="form-control" name="ce_email_id[]" maxlength="100" required
                                   value="<?= retain_arr('ce_email_id', $i) ?>">
                            <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addEmailField()">Add Email</button>
                </div>
                <div class="d-grid">
                    <button type="submit" name="customer_submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    <?php elseif ($step === 'third_party'): ?>
        <div class="card p-4 mt-4">
            <h4 class="card-title mb-4"><i class="bi bi-plug"></i> Add NNI Partner Details (by Circuit)</h4>
            <?php if (isset($message) && $message) echo "<div class='mb-3'>$message</div>"; ?>
            <?php if ($customer_details): ?>
                <div class="mb-3">
                    <h5 class="mb-2">Circuit Details</h5>
                    <table class="table table-bordered">
                        <tr>
                            <th>Circuit ID</th>
                            <td><?= htmlspecialchars($customer_details['circuit_id']) ?></td>
                        </tr>
                        <tr>
                            <th>Organization Name</th>
                            <td><?= htmlspecialchars($customer_details['organization_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Customer Address</th>
                            <td><?= htmlspecialchars($customer_details['customer_address']) ?></td>
                        </tr>
                        <tr>
                            <th>City</th>
                            <td><?= htmlspecialchars($customer_details['city']) ?></td>
                        </tr>
                        <tr>
                            <th>Contact Person Name</th>
                            <td><?= htmlspecialchars($customer_details['contact_person_name']) ?></td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
            <form method="post" id="third-party-form" autocomplete="off">
                <input type="hidden" name="circuit_id" value="<?= htmlspecialchars($customer_details['circuit_id'] ?? $circuit_id) ?>">
                <div class="mb-3">
                    <label class="form-label">Select Operator</label>
                    <select name="operator_id" id="operator_id" class="form-select" required>
                        <option value="">Select operator</option>
                        <?php foreach ($operators as $op): ?>
                            <option value="<?= $op['id'] ?>"><?= htmlspecialchars($op['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="dynamic-fields"></div>
                <button type="submit" name="third_party_submit" class="btn btn-success mt-3"><i class="bi bi-save"></i> Save Details</button>
                <a href="operator_portal.php" class="btn btn-secondary mt-3">Back</a>
                <?php if (isset($show_table_view) && $show_table_view && isset($table_row['table_name'])): ?>
                    <a href="view_third_party_details.php?table=<?=urlencode($table_row['table_name'])?>" class="btn btn-info mt-3 ms-2">View Inserted Data</a>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
</div>
<script>
function toggleCircuitInput() {
    const mode = document.getElementById('circuit_mode').value;
    document.getElementById('manual_circuit_group').style.display = (mode === 'manual') ? 'block' : 'none';
}
function addContactField() {
    const div = document.createElement('div');
    div.className = 'input-group mb-1';
    div.innerHTML = `<input type="tel" class="form-control" name="contact_number[]" maxlength="15" required pattern="\\d{10,15}" title="10 to 15 digits">
                     <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>`;
    document.getElementById('contacts').appendChild(div);
}
function addEmailField() {
    const div = document.createElement('div');
    div.className = 'input-group mb-1';
    div.innerHTML = `<input type="email" class="form-control" name="ce_email_id[]" maxlength="100" required>
                     <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>`;
    document.getElementById('emails').appendChild(div);
}
function removeField(btn) {
    btn.parentNode.remove();
}
function validateForm() {
    let valid = true;
    document.querySelectorAll('input[name="contact_number[]"]').forEach(function(input) {
        if (!/^\d{10,15}$/.test(input.value)) {
            valid = false;
        }
    });
    if (!valid) {
        alert("Each contact number must be 10–15 digits.");
        return false;
    }
    return true;
}

// Dynamic operator fields
$(function(){
    $("#operator_id").on("change", function(){
        var opid = $(this).val();
        if (!opid) { $("#dynamic-fields").html(""); return; }
        $.get(window.location.pathname, {ajax:"fields", operator_id:opid}, function(res){
            var data = JSON.parse(res);
            var html = "";
            if (data.fields && data.fields.length > 0) {
                html += '<h5 class="mt-4 mb-3"><i class="bi bi-list-ul"></i> Operator Fields</h5>';
                data.fields.forEach(function(f){
                    html += '<div class="mb-3">';
                    if (f.field_type.toLowerCase() === 'date') {
                        html += '<label class="form-label">' + f.field_name + (f.required == "1" ? " <span class=\'text-danger\'>*</span>" : "") + '</label>';
                        html += '<input type="date" name="' + f.field_name + '" class="form-control" />';
                        html += '<small class="form-text text-muted">Format: YYYY-MM-DD. If left blank, today\'s date will be used.</small>';
                    }
                    else if (f.field_type.toLowerCase() === 'int') {
                        html += '<label class="form-label">' + f.field_name + (f.required == "1" ? " <span class=\'text-danger\'>*</span>" : "") + '</label>';
                        html += '<input type="number" name="' + f.field_name + '" class="form-control" ' + (f.required == "1" ? "required" : "") + '>';
                        html += '<small class="form-text text-muted">Expected: numbers only.</small>';
                    }
                    else if (f.field_type.toLowerCase() === 'varchar') {
                        html += '<label class="form-label">' + f.field_name + (f.required == "1" ? " <span class=\'text-danger\'>*</span>" : "") + '</label>';
                        html += '<input type="text" name="' + f.field_name + '" class="form-control" maxlength="255" ' + (f.required == "1" ? "required" : "") + '>';
                        html += '<small class="form-text text-muted">Expected: alphanumeric (max 255 chars).</small>';
                    }
                    else if (f.field_type.toLowerCase() === 'enum' && f.enum_options && f.enum_options.length > 0) {
                        html += '<label class="form-label">' + f.field_name + (f.required == "1" ? " <span class=\'text-danger\'>*</span>" : "") + '</label>';
                        html += '<select name="' + f.field_name + '" class="form-select" ' + (f.required == "1" ? "required" : "") + '>';
                        html += '<option value="">Select...</option>';
                        f.enum_options.forEach(function(opt){
                            html += '<option value="'+opt+'">'+opt+'</option>';
                        });
                        html += '</select>';
                        html += '<small class="form-text text-muted">Choose one.</small>';
                    }
                    else {
                        html += '<label class="form-label">' + f.field_name + (f.required == "1" ? " <span class=\'text-danger\'>*</span>" : "") + '</label>';
                        html += '<input type="text" name="' + f.field_name + '" class="form-control" ' + (f.required == "1" ? "required" : "") + '>';
                    }
                    html += '</div>';
                });
            } else {
                html = "<div class='alert alert-warning'>No fields defined for this operator table.</div>";
            }
            $("#dynamic-fields").html(html);
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>