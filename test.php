<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_name('oss_portal');
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

include 'includes/db.php';
require_once 'includes/audit.php';

$circuit_id = $_GET['circuit_id'] ?? '';
if (!$circuit_id) {
    die("No circuit ID provided.");
}

// Log after circuit_id is set
log_activity(
    $pdo,
    $_SESSION['username'],
    'view',
    'edit_customer_form',
    $circuit_id,
    'Viewed edit form for circuit'
);

// Fetch all related data
$sql = "
  SELECT cbi.*, nd.*, cnd.installation_date, cnd.wan_ip, cnd.wan_gateway,
         cnd.dns1, cnd.dns2, cnd.auth_type, cnd.PPPoE_auth_username,
         cnd.PPPoE_auth_password, cnd.cacti_url, cnd.cacti_username,
         cnd.cacti_password
    FROM customer_basic_information AS cbi
    JOIN network_details AS nd ON cbi.circuit_id = nd.circuit_id
    LEFT JOIN circuit_network_details AS cnd ON nd.circuit_id = cnd.circuit_id
   WHERE cbi.circuit_id = :cid
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['cid' => $circuit_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC) ?: die("No data found.");

// Fetch pop, switch, product lists
$pop_data = $pdo->query("SELECT pop_name, pop_ip FROM pop_inventory")->fetchAll(PDO::FETCH_ASSOC);
$switch_data = $pdo->query("SELECT switch_name, switch_ip FROM switch_inventory")->fetchAll(PDO::FETCH_ASSOC);
$product_types = $pdo->query("SELECT DISTINCT product_type FROM network_details")->fetchAll(PDO::FETCH_COLUMN);

// Fetch additional IPs
$ip_stmt = $pdo->prepare("SELECT ip_address FROM circuit_ips WHERE circuit_id = ?");
$ip_stmt->execute([$circuit_id]);
$ips = $ip_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all contact numbers and emails for this circuit
$contact_stmt = $pdo->prepare("SELECT contact_number FROM customer_contacts WHERE circuit_id = ?");
$contact_stmt->execute([$circuit_id]);
$contact_numbers = $contact_stmt->fetchAll(PDO::FETCH_COLUMN);

$email_stmt = $pdo->prepare("SELECT ce_email_id FROM customer_emails WHERE circuit_id = ?");
$email_stmt->execute([$circuit_id]);
$ce_email_ids = $email_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Edit Circuit <?= htmlspecialchars($circuit_id) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
  body {
    font-size: 13px;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
  }
  .card {
    padding: 10px 15px;
    margin-bottom: 12px;
    border-radius: 6px;
    box-shadow: 0 0 5px rgba(0,0,0,0.05);
    transition: box-shadow 0.3s ease;
  }
  .card:hover {
    box-shadow: 0 0 12px rgba(0,0,0,0.12);
  }
  .card-header {
    font-weight: 600;
    font-size: 0.9rem;
    padding: 8px 12px;
    background-color: #f7f7f7;
  }
  label.form-label {
    font-size: 0.85rem;
    font-weight: 500;
  }
  input.form-control, select.form-select {
    font-size: 0.85rem;
    padding: 5px 8px;
    height: 30px;
  }
  button.btn {
    font-size: 0.85rem;
    padding: 4px 10px;
    transition: background-color 0.2s ease, color 0.2s ease;
  }
  button.btn:hover {
    filter: brightness(90%);
  }
  .input-group .form-control {
    height: 30px;
  }
  /* Reduce spacing between form groups */
  .row.g-3 > [class^="col-"] {
    padding-bottom: 8px;
  }
  /* Smaller spacing for additional IP list buttons */
  #ip-list .btn-danger {
    padding: 3px 8px;
  }
  /* Responsive tweaks */
  @media (max-width: 576px) {
    body {
      font-size: 12px;
    }
    input.form-control, select.form-select {
      height: 28px;
      font-size: 0.8rem;
    }
    .card-header {
      font-size: 0.85rem;
      padding: 6px 10px;
    }
  }
  /* Highlight invalid IPs */
  input.is-invalid {
    border-color: #dc3545 !important;
    background-color: #fff6f6 !important;
  }
</style>
</head>
<body>
<div class="container mt-5">
  <div class="d-flex justify-content-between mb-4">
    <h3>Edit Circuit: <?= htmlspecialchars($circuit_id) ?></h3>
    <a href="view_customer.php" class="btn btn-secondary">← Back to List</a>
  </div>

  <?php if (isset($_GET['wan_ip_error'], $_GET['conflict_circuit'])): ?>
      <div class="alert alert-danger">
          WAN IP <strong><?= htmlspecialchars($_GET['wan_ip_error']) ?></strong> is already used in circuit ID: <strong><?= htmlspecialchars($_GET['conflict_circuit']) ?></strong>
      </div>
  <?php endif; ?>
  <?php if (isset($_GET['pppoe_error'], $_GET['conflict_circuit'])): ?>
      <div class="alert alert-danger">
          PPPoE Username <strong><?= htmlspecialchars($_GET['pppoe_error']) ?></strong> is already used in circuit ID: <strong><?= htmlspecialchars($_GET['conflict_circuit']) ?></strong>
      </div>
  <?php endif; ?>
  <?php if (isset($_GET['ip_error'], $_GET['conflict_circuit'])): ?>
      <div class="alert alert-danger">
          Additional IP <strong><?= htmlspecialchars($_GET['ip_error']) ?></strong> is already used in circuit ID: <strong><?= htmlspecialchars($_GET['conflict_circuit']) ?></strong>
      </div>
  <?php endif; ?>
  <?php if (!empty($_GET['client_ip_dup'])): ?>
      <div class="alert alert-danger">
          Duplicate IP address found in Additional IPs. Please make sure all IPs are unique.
      </div>
  <?php endif; ?>
  <?php if (!empty($_GET['update_error'])): ?>
      <div class="alert alert-danger">
          Update failed: <?= htmlspecialchars($_GET['update_error']) ?>
      </div>
  <?php endif; ?>

  <form method="POST" action="update_customer.php" id="editCircuitForm">
    <input type="hidden" name="circuit_id" value="<?= htmlspecialchars($circuit_id) ?>">

    <!-- Basic Information -->
    <div class="card">
      <div class="card-header">Basic Information</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Organization</label>
            <input class="form-control" name="organization_name" value="<?= htmlspecialchars($data['organization_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Contact Person</label>
            <input class="form-control" name="contact_person_name" value="<?= htmlspecialchars($data['contact_person_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Contact Numbers</label>
            <div id="contacts">
              <?php
                if (!$contact_numbers) $contact_numbers = [''];
                foreach ($contact_numbers as $num):
              ?>
                <div class="input-group mb-1">
                  <input type="text" class="form-control" name="contact_number[]" maxlength="15" required pattern="\d{10,15}" title="10 to 15 digits" value="<?= htmlspecialchars($num) ?>">
                  <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addContactField()">Add Contact</button>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email IDs</label>
            <div id="emails">
              <?php
                if (!$ce_email_ids) $ce_email_ids = [''];
                foreach ($ce_email_ids as $em):
              ?>
                <div class="input-group mb-1">
                  <input type="email" class="form-control" name="ce_email_id[]" required value="<?= htmlspecialchars($em) ?>">
                  <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addEmailField()">Add Email</button>
          </div>
          <div class="col-md-12">
            <label class="form-label">Remarks</label>
            <textarea class="form-control" name="remarks" rows="2"><?= htmlspecialchars($data['remarks']) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Network Details -->
    <div class="card">
      <div class="card-header">Network Details</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">POP</label>
            <select class="form-select" name="pop_name" required>
              <option value="">-- Select POP --</option>
              <?php foreach ($pop_data as $pop): ?>
                <option value="<?= htmlspecialchars($pop['pop_name']) ?>" <?= ($data['pop_name'] ?? '') === $pop['pop_name'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($pop['pop_name']) ?> (<?= htmlspecialchars($pop['pop_ip']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Switch Name</label>
            <select class="form-select" name="switch_name" required>
              <option value="">-- Select Switch --</option>
              <?php foreach ($switch_data as $sw): ?>
                <option value="<?= htmlspecialchars($sw['switch_name']) ?>" <?= ($data['switch_name'] ?? '') === $sw['switch_name'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sw['switch_name']) ?> (<?= htmlspecialchars($sw['switch_ip']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Switch Port</label>
            <input class="form-control" name="switch_port" maxlength="30" value="<?= htmlspecialchars($data['switch_port'] ?? '') ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Product Type</label>
            <select class="form-select" name="product_type" required>
              <option value="">-- Select Product Type --</option>
              <?php foreach ($product_types as $ptype): ?>
                <option value="<?= htmlspecialchars($ptype) ?>" <?= ($data['product_type'] ?? '') === $ptype ? 'selected' : '' ?>>
                  <?= htmlspecialchars($ptype) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Installation Date</label>
            <input type="date" class="form-control" name="installation_date" value="<?= htmlspecialchars($data['installation_date'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- Circuit Network Details -->
    <div class="card">
      <div class="card-header">Circuit Network Details</div>
      <div class="card-body row g-3">

        <!-- Authentication Type on top -->
        <div class="col-md-4">
          <label class="form-label">Authentication Type</label>
          <select class="form-select" name="auth_type" id="auth_type" onchange="togglePppoeFields()">
            <option value="Static" <?= ($data['auth_type'] ?? '') === 'Static' ? 'selected' : '' ?>>Static</option>
            <option value="PPPoE" <?= ($data['auth_type'] ?? '') === 'PPPoE' ? 'selected' : '' ?>>PPPoE</option>
          </select>
        </div>

        <div class="col-md-4" id="wan_ip_div">
          <label class="form-label">WAN IP</label>
          <input class="form-control" name="wan_ip" id="wan_ip" value="<?= htmlspecialchars($data['wan_ip'] ?? '') ?>">
        </div>

        <div class="col-md-4" id="wan_gateway_div">
          <label class="form-label">WAN Gateway</label>
          <input class="form-control" name="wan_gateway" id="wan_gateway" value="<?= htmlspecialchars($data['wan_gateway'] ?? '') ?>">
        </div>

        <div class="col-md-4" id="dns1_div">
          <label class="form-label">DNS 1</label>
          <input class="form-control" name="dns1" id="dns1" value="<?= htmlspecialchars($data['dns1'] ?? '') ?>">
        </div>

        <div class="col-md-4" id="dns2_div">
          <label class="form-label">DNS 2</label>
          <input class="form-control" name="dns2" id="dns2" value="<?= htmlspecialchars($data['dns2'] ?? '') ?>">
        </div>

        <div class="col-md-4" id="pppoe_username_div" style="display: none;">
          <label class="form-label">PPPoE Username</label>
          <input class="form-control" name="PPPoE_auth_username" id="pppoe_username" value="<?= htmlspecialchars($data['PPPoE_auth_username'] ?? '') ?>">
        </div>

        <div class="col-md-4" id="pppoe_password_div" style="display: none;">
          <label class="form-label">PPPoE Password</label>
          <input type="password" class="form-control" name="PPPoE_auth_password" id="pppoe_password" value="<?= htmlspecialchars($data['PPPoE_auth_password'] ?? '') ?>">
        </div>

      </div>
    </div>

    <!-- Additional IPs -->
    <div class="card">
      <div class="card-header">Additional IPs</div>
      <div class="card-body">
        <div id="ip-list">
          <?php
          if (!$ips) $ips = [''];
          foreach ($ips as $ip):
          ?>
          <div class="input-group mb-2">
            <input type="text" class="form-control" name="additional_ips[]" placeholder="Enter IP address" pattern="^(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.|$)){4}$" title="Enter valid IPv4 address" value="<?= htmlspecialchars($ip) ?>">
            <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addIpField()">Add IP</button>
      </div>
    </div>

    <button type="submit" class="btn btn-primary mt-3">Update Circuit</button>
  </form>
</div>

<script>
  // Dynamically add/remove contact fields
  function addContactField() {
    const container = document.getElementById('contacts');
    const div = document.createElement('div');
    div.className = 'input-group mb-1';
    div.innerHTML = `<input type="text" class="form-control" name="contact_number[]" maxlength="15" required pattern="\\d{10,15}" title="10 to 15 digits">
                     <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>`;
    container.appendChild(div);
  }
  // Dynamically add/remove email fields
  function addEmailField() {
    const container = document.getElementById('emails');
    const div = document.createElement('div');
    div.className = 'input-group mb-1';
    div.innerHTML = `<input type="email" class="form-control" name="ce_email_id[]" required>
                     <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>`;
    container.appendChild(div);
  }
  // Dynamically add/remove additional IP fields
  function addIpField() {
    const container = document.getElementById('ip-list');
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `<input type="text" class="form-control" name="additional_ips[]" placeholder="Enter IP address" pattern="^(?:(?:25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(?:\\.|$)){4}$" title="Enter valid IPv4 address">
                     <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>`;
    container.appendChild(div);
  }
  // Remove a field wrapper
  function removeField(btn) {
    btn.parentNode.remove();
  }

  // Toggle PPPoE fields and set required attributes
  function togglePppoeFields() {
    const authType = document.getElementById('auth_type').value;
    const isPPPoE = authType === 'PPPoE';

    // Show/hide PPPoE username/password fields
    document.getElementById('pppoe_username_div').style.display = isPPPoE ? 'block' : 'none';
    document.getElementById('pppoe_password_div').style.display = isPPPoE ? 'block' : 'none';

    // Toggle required for WAN IP, Gateway, DNS1, DNS2
    document.getElementById('wan_ip').required = !isPPPoE;
    document.getElementById('wan_gateway').required = !isPPPoE;
    document.getElementById('dns1').required = !isPPPoE;
    // DNS2 optional in all cases
    document.getElementById('dns2').required = false;

    // PPPoE fields required only if PPPoE selected
    const pppoeUsername = document.getElementById('pppoe_username');
    const pppoePassword = document.getElementById('pppoe_password');

    if (isPPPoE) {
      pppoeUsername.setAttribute('required', 'required');
      pppoePassword.setAttribute('required', 'required');
    } else {
      pppoeUsername.removeAttribute('required');
      pppoePassword.removeAttribute('required');
    }
  }

  // On page load toggle fields based on saved auth type
  document.addEventListener('DOMContentLoaded', () => {
    togglePppoeFields();
  });

  // Client-side form validation on submit
  document.getElementById('editCircuitForm').addEventListener('submit', function(e) {
    // Validate duplicate IPs in Additional IPs
    const ipInputs = document.querySelectorAll('input[name="additional_ips[]"]');
    const ips = [];
    let duplicates = [];

    ipInputs.forEach(input => input.classList.remove('is-invalid'));

    ipInputs.forEach(input => {
      const val = input.value.trim();
      if (val) {
        if (ips.includes(val)) {
          duplicates.push(val);
        }
        ips.push(val);
      }
    });

    if (duplicates.length > 0) {
      ipInputs.forEach(input => {
        if (duplicates.includes(input.value.trim())) {
          input.classList.add('is-invalid');
        }
      });
      alert('Duplicate IP addresses found in Additional IPs: ' + [...new Set(duplicates)].join(', '));
      e.preventDefault();
      return false;
    }

    // Validate required fields depending on auth type
    const authType = document.getElementById('auth_type').value;
    const wanIp = document.getElementById('wan_ip').value.trim();
    const wanGateway = document.getElementById('wan_gateway').value.trim();
    const dns1 = document.getElementById('dns1').value.trim();
    const pppoeUsername = document.getElementById('pppoe_username').value.trim();
    const pppoePassword = document.getElementById('pppoe_password').value.trim();

    if (authType === 'Static') {
      if (!wanIp || !wanGateway || !dns1) {
        alert('Static Authentication requires WAN IP, WAN Gateway, and DNS 1 to be filled.');
        e.preventDefault();
        return false;
      }
    } else if (authType === 'PPPoE') {
      if (!pppoeUsername || !pppoePassword) {
        alert('PPPoE Authentication requires both Username and Password.');
        e.preventDefault();
        return false;
      }
    }
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
