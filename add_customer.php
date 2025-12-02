<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_name('oss_portal');
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'includes/db.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    }

    $organization_name     = trim($_POST['organization_name']);
    $customer_address      = trim($_POST['customer_address']);
    $city                  = trim($_POST['city']);
    $contact_person_name   = trim($_POST['contact_person_name']);
    $contact_numbers       = $_POST['contact_number'] ?? [];
    $ce_email_ids          = $_POST['ce_email_id'] ?? [];

    if (!preg_match('/^[a-zA-Z\s\.\-&()]+$/', $organization_name)) {
        $errors[] = "Organization name contains invalid characters.";
    }
    if (!preg_match('/^[a-zA-Z\s\.\-&()]+$/', $contact_person_name)) {
        $errors[] = "Contact person name contains invalid characters.";
    }
    if (!preg_match('/^[a-zA-Z\s\.\-&()]+$/', $city)) {
        $errors[] = "City contains invalid characters.";
    }

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

            if (empty($errors)) {
				unset($_SESSION['csrf_token']);
				header("Location: circuit_check.php?circuit_id=$circuit_id");
				exit;
				}
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Customer</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-size: 0.9rem; }
        .container { max-width: 720px; }
        .form-label { margin-bottom: 0.2rem; }
        .card { border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
<div class="container mt-4">
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
                <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
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
</script>
</body>
</html>
