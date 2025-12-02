<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_name('oss_portal');
session_start();
require 'includes/config.php';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$updated_by = $_SESSION['username'] ?? 'system';

$fields = [
    'site_id' => '',
    'device_name' => '',
    'device_ip' => '',
    'device_type' => '',
    'device_serial_number' => '',
    'model_id' => '',
    'device_location' => '',
    'address' => '',
    'contact_person' => '',
    'contact_number' => '',
    'device_price' => '',
    'installation_date' => '', // NEW FIELD
    'owned_by' => '',
    'remarks' => ''
];

$error = "";

// Fetch sites and addresses
$sites = [];
$site_addresses = [];
$result = $conn->query("SELECT site_id, site_name, address FROM site_master ORDER BY site_name ASC");
while ($row = $result->fetch_assoc()) {
    $sites[$row['site_id']] = $row['site_name'];
    $site_addresses[$row['site_id']] = $row['address'];
}

// Fetch models grouped by vendor
$models = [];
$result = $conn->query("SELECT model_id, model_name, model_cost, vendor FROM device_models ORDER BY vendor ASC, model_name ASC");
while ($row = $result->fetch_assoc()) {
    $models[$row['vendor']][] = $row;
}

// Find selected vendor for the selected model, if set
$selected_vendor = '';
if (!empty($fields['model_id'])) {
    foreach ($models as $vendor => $modelList) {
        foreach ($modelList as $model) {
            if ($model['model_id'] == $fields['model_id']) {
                $selected_vendor = $vendor;
                break 2;
            }
        }
    }
}

// Strict date validator: only allow YYYY-MM-DD
function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// Handle POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid form submission. Please reload the page.";
    } else {
        foreach ($fields as $key => $v) {
            $fields[$key] = trim($_POST[$key] ?? '');
        }

        // Validate installation date
        if (!isValidDate($fields['installation_date'])) {
            $error = "Invalid installation date. Must be in YYYY-MM-DD format. Given: " . htmlspecialchars($fields['installation_date']);
        }

        // Continue validation only if no error so far
        if (!$error) {
            $required = [
                'site_id', 'device_name', 'device_ip', 'device_type', 'device_serial_number',
                'model_id', 'device_location', 'address', 'contact_person',
                'contact_number', 'device_price', 'installation_date', 'owned_by'
            ];
            foreach ($required as $r) {
                if ($fields[$r] === '') {
                    $error = "All fields except Remarks are required.";
                    break;
                }
            }
        }

        // Data-specific validation
        if (!$error) {
            if (!in_array($fields['owned_by'], ['Own', 'Operator'])) {
                $error = "Invalid value for Owned By.";
            } elseif (!array_key_exists($fields['site_id'], $sites)) {
                $error = "Invalid site selected.";
            } elseif (!filter_var($fields['device_ip'], FILTER_VALIDATE_IP)) {
                $error = "Invalid IP address.";
            } elseif (!is_numeric($fields['device_price']) || floatval($fields['device_price']) < 0) {
                $error = "Invalid device price.";
            } else {
                // Check for duplicate device_ip (should be unique)
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM device_inventory WHERE device_ip = ?");
                $checkStmt->bind_param("s", $fields['device_ip']);
                $checkStmt->execute();
                $checkStmt->bind_result($ipCount);
                $checkStmt->fetch();
                $checkStmt->close();

                if ($ipCount > 0) {
                    $error = "IP already exists: '{$fields['device_ip']}'";
                } else {
                    // Insert (nullable fields set to NULL if empty)
                    $stmt = $conn->prepare("INSERT INTO device_inventory 
                        (site_id, device_name, device_ip, device_type, device_serial_number, model_id, device_location, address, contact_person, contact_number, device_price, installation_date, owned_by, remarks, updated_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    $bind_address = $fields['address'] !== '' ? $fields['address'] : NULL;
                    $bind_device_price = $fields['device_price'] !== '' ? floatval($fields['device_price']) : NULL;
                    $bind_remarks = $fields['remarks'] !== '' ? $fields['remarks'] : NULL;

                    // MySQL expects DATE or DATETIME for installation_date; add ' 00:00:00' if needed for DATETIME
                    $installation_date_db = $fields['installation_date'];
                    // If your DB column is DATETIME, uncomment the next line:
                    // $installation_date_db .= ' 00:00:00';

                    $stmt->bind_param(
                        "isssssisssdssss",
                        $fields['site_id'],
                        $fields['device_name'],
                        $fields['device_ip'],
                        $fields['device_type'],
                        $fields['device_serial_number'],
                        $fields['model_id'],
                        $fields['device_location'],
                        $bind_address,
                        $fields['contact_person'],
                        $fields['contact_number'],
                        $bind_device_price,
                        $installation_date_db,
                        $fields['owned_by'],
                        $bind_remarks,
                        $updated_by
                    );

                    if ($stmt->execute()) {
                        echo "<script>alert('Device added successfully'); window.location.href='device_inventory.php';</script>";
                        exit;
                    } else {
                        $error = "Failed to insert: " . $stmt->error;
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Device</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .main-fit { max-width: 1100px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 12px; box-shadow: 0 0 10px #0002; }
        .form-label { font-weight: 500; margin-bottom: 4px; }
        .form-control, .form-select { font-size: 0.95em; }
    </style>
</head>
<body>
<div class="main-fit">
    <h4>Add Device</h4>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="row g-3" autocomplete="off" id="deviceForm">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="col-md-4">
            <label class="form-label">Site</label>
            <select class="form-select" name="site_id" id="site_id" required>
                <option value="">-- Select Site --</option>
                <?php foreach ($sites as $sid => $sname): ?>
                    <option value="<?= $sid ?>" <?= $fields['site_id'] == $sid ? 'selected' : '' ?>><?= htmlspecialchars($sname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Device Name</label>
            <input type="text" class="form-control" name="device_name" value="<?= htmlspecialchars($fields['device_name']) ?>" maxlength="100" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Device IP</label>
            <input type="text" class="form-control" name="device_ip" value="<?= htmlspecialchars($fields['device_ip']) ?>" maxlength="45" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Device Type</label>
            <select class="form-select" name="device_type" required>
                <option value="">--Select--</option>
                <option value="Router" <?= $fields['device_type'] == 'Router' ? 'selected' : '' ?>>Router</option>
                <option value="Switch" <?= $fields['device_type'] == 'Switch' ? 'selected' : '' ?>>Switch</option>
                <option value="OLT" <?= $fields['device_type'] == 'OLT' ? 'selected' : '' ?>>OLT</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Serial Number</label>
            <input type="text" class="form-control" name="device_serial_number" value="<?= htmlspecialchars($fields['device_serial_number']) ?>" maxlength="100" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Vendor</label>
            <select class="form-select" id="vendor_select" required>
                <option value="">-- Select Vendor --</option>
                <?php foreach ($models as $vendor => $modelList): ?>
                    <option value="<?= htmlspecialchars($vendor) ?>" <?= $selected_vendor == $vendor ? 'selected' : '' ?>><?= htmlspecialchars($vendor) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Model</label>
            <select class="form-select" name="model_id" id="model_id" required>
                <option value="">-- Select Model --</option>
                <?php foreach ($models as $vendor => $modelList): ?>
                    <?php foreach ($modelList as $model): ?>
                        <option value="<?= $model['model_id'] ?>" data-cost="<?= $model['model_cost'] ?>" data-vendor="<?= htmlspecialchars($vendor) ?>" style="display: none;"
                            <?= $fields['model_id'] == $model['model_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($model['model_name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Location</label>
            <input type="text" class="form-control" name="device_location" value="<?= htmlspecialchars($fields['device_location']) ?>" maxlength="100" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Address</label>
            <input type="text" class="form-control" name="address" id="address" value="<?= htmlspecialchars($fields['address']) ?>" maxlength="255" readonly required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Contact Person</label>
            <input type="text" class="form-control" name="contact_person" value="<?= htmlspecialchars($fields['contact_person']) ?>" maxlength="100" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Contact Number</label>
            <input type="text" class="form-control" name="contact_number" pattern="[0-9+\-\s]{7,20}" maxlength="20" value="<?= htmlspecialchars($fields['contact_number']) ?>" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Device Price</label>
            <input type="number" class="form-control" name="device_price" id="device_price" value="<?= htmlspecialchars($fields['device_price']) ?>" min="0" step="0.01" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Installation Date</label>
            <input type="date" class="form-control" name="installation_date" id="installation_date" value="<?= htmlspecialchars($fields['installation_date']) ?>" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Owned By</label>
            <select class="form-select" name="owned_by" required>
                <option value="">-- Select --</option>
                <option value="Own" <?= $fields['owned_by'] == 'Own' ? 'selected' : '' ?>>Own</option>
                <option value="Operator" <?= $fields['owned_by'] == 'Operator' ? 'selected' : '' ?>>Operator</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Remarks</label>
            <textarea class="form-control" name="remarks" rows="2"><?= htmlspecialchars($fields['remarks']) ?></textarea>
        </div>

        <div class="col-12 text-end">
            <button type="submit" class="btn btn-success btn-sm">Add Device</button>
            <a href="device_inventory.php" class="btn btn-secondary btn-sm">Back</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const siteAddresses = <?= json_encode($site_addresses) ?>;
    const siteSelect = document.getElementById('site_id');
    const addressInput = document.getElementById('address');
    const vendorSelect = document.getElementById('vendor_select');
    const modelSelect = document.getElementById('model_id');
    const devicePrice = document.getElementById('device_price');

    // Address autofill
    siteSelect.addEventListener('change', function() {
        const sid = this.value;
        addressInput.value = siteAddresses[sid] || '';
    });

    // Address set on page load
    if (siteSelect.value) {
        addressInput.value = siteAddresses[siteSelect.value] || '';
    }

    function updateModelOptions() {
        const selectedVendor = vendorSelect.value;
        for (let opt of modelSelect.options) {
            if (!opt.value) continue;
            opt.style.display = opt.getAttribute('data-vendor') === selectedVendor ? '' : 'none';
        }
        // Auto-select model if current model doesn't belong to vendor
        if (modelSelect.value) {
            const selected = modelSelect.querySelector('option[value="' + modelSelect.value + '"]');
            if (selected && selected.getAttribute('data-vendor') !== selectedVendor) {
                modelSelect.value = '';
            }
        }
    }
    vendorSelect.addEventListener('change', updateModelOptions);

    // Set vendor on page load if model is pre-selected
    if (modelSelect.value) {
        let selectedModel = modelSelect.querySelector('option[value="' + modelSelect.value + '"]');
        if (selectedModel) {
            vendorSelect.value = selectedModel.getAttribute('data-vendor');
        }
        updateModelOptions();
    } else {
        updateModelOptions();
    }

    // Autofill device price on model change
    modelSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const cost = selected.getAttribute('data-cost');
        if (cost) devicePrice.value = cost;
    });
});
</script>
</body>
</html>