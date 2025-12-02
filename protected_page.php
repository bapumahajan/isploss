<?php
session_name('oss_portal');
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

include 'includes/db.php';

$circuit_id = $_GET['circuit_id'] ?? '';
if (!$circuit_id) {
    die("No circuit ID provided.");
}

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
                foreach ($ce_email_ids as $email):
              ?>
                <div class="input-group mb-1">
                  <input type="email" class="form-control" name="ce_email_id[]" maxlength="100" required value="<?= htmlspecialchars($email) ?>">
                  <button type="button" class="btn btn-outline-danger" onclick="removeField(this)" tabindex="-1">-</button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addEmailField()">Add Email</button>
          </div>
          <div class="col-md-6">
            <label class="form-label">Install Date</label>
            <input type="date" class="form-control" name="installation_date" value="<?= htmlspecialchars($data['installation_date'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Address</label>
            <textarea class="form-control" name="customer_address" required><?= htmlspecialchars($data['customer_address']) ?></textarea>
          </div>                    
        </div>
      </div>
    </div>

    <!-- Network Details -->
    <div class="card">
      <div class="card-header">Network Details</div>
      <div class="card-body row g-3">
        <div class="col-md-3">
          <label class="form-label">Product Type</label>
          <select name="product_type" class="form-select" required>
            <?php foreach($product_types as $pt): ?>
              <option value="<?= htmlspecialchars($pt) ?>" <?= $pt === $data['product_type'] ? 'selected' : '' ?>><?= htmlspecialchars($pt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">POP Name</label>
          <select name="pop_name" id="pop_name" class="form-select" required onchange="updatePopIp()">
            <?php foreach($pop_data as $pop): ?>
              <option value="<?= htmlspecialchars($pop['pop_name']) ?>" data-ip="<?= htmlspecialchars($pop['pop_ip']) ?>" <?= $pop['pop_name'] === $data['pop_name'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($pop['pop_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Switch Name</label>
          <select name="switch_name" id="switch_name" class="form-select" required onchange="updateSwitchIp()">
            <?php foreach($switch_data as $switch): ?>
              <option value="<?= htmlspecialchars($switch['switch_name']) ?>" data-ip="<?= htmlspecialchars($switch['switch_ip']) ?>" <?= $switch['switch_name'] === $data['switch_name'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($switch['switch_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Switch Port</label>
          <input class="form-control" name="switch_port" value="<?= htmlspecialchars($data['switch_port'] ?? '') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">POP IP</label>
          <input class="form-control" name="pop_ip" id="pop_ip" value="<?= htmlspecialchars($data['pop_ip']) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Switch IP</label>
          <input class="form-control" name="switch_ip" id="switch_ip" value="<?= htmlspecialchars($data['switch_ip']) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Bandwidth</label>
          <input class="form-control" name="bandwidth" value="<?= htmlspecialchars($data['bandwidth'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Circuit Status</label>
          <select name="circuit_status" class="form-select" required>
            <option value="Active" <?= ($data['circuit_status'] ?? '') === "Active" ? 'selected' : '' ?>>Active</option>
            <option value="Suspended" <?= ($data['circuit_status'] ?? '') === "Suspended" ? 'selected' : '' ?>>Suspended</option>
            <option value="Terminated" <?= ($data['circuit_status'] ?? '') === "Terminated" ? 'selected' : '' ?>>Terminated</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">VLAN</label>
          <input class="form-control" name="vlan" value="<?= htmlspecialchars($data['vlan'] ?? '') ?>">
        </div>
      </div>
    </div>
    <!-- Circuit Network Details -->
    <div class="card">
      <div class="card-header">Circuit Network Details</div>
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label class="form-label">WAN IP</label>
          <input class="form-control<?= isset($_GET['wan_ip_error']) ? ' is-invalid' : '' ?>"
                 name="wan_ip"
                 value="<?= htmlspecialchars($data['wan_ip'] ?? '') ?>"
                 required>
        </div>
        <div class="col-md-4">
          <label class="form-label">WAN Gateway</label>
          <input class="form-control" name="wan_gateway" value="<?= htmlspecialchars($data['wan_gateway'] ?? '') ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">DNS 1</label>
          <input class="form-control" name="dns1" value="<?= htmlspecialchars($data['dns1'] ?? '') ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">DNS 2</label>
          <input class="form-control" name="dns2" value="<?= htmlspecialchars($data['dns2'] ?? '') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Authentication Type</label>
          <select class="form-select" name="auth_type" id="auth_type" onchange="togglePppoeFields()">
            <option value="Static" <?= ($data['auth_type'] ?? '') === 'static' ? 'selected' : '' ?>>Static</option>
            <option value="PPPoE" <?= ($data['auth_type'] ?? '') === 'PPPoE' ? 'selected' : '' ?>>PPPoE</option>
          </select>
        </div>
        <div class="col-md-4" id="pppoe_username_div" style="display: none;">
          <label class="form-label">PPPoE Username</label>
          <input class="form-control" name="PPPoE_auth_username" value="<?= htmlspecialchars($data['PPPoE_auth_username'] ?? '') ?>">
        </div>
        <div class="col-md-4" id="pppoe_password_div" style="display: none;">
          <label class="form-label">PPPoE Password</label>
          <input type="password" class="form-control" name="PPPoE_auth_password" value="<?= htmlspecialchars($data['PPPoE_auth_password'] ?? '') ?>">
        </div>
      </div>
    </div>
    <!-- Circuit IPs -->
    <div class="card">
      <div class="card-header">Additional IPs</div>
      <div class="card-body">
        <div id="ip-list" class="mb-3">
          <?php foreach($ips as $ip): ?>
            <div class="input-group mb-1">
              <input type="text" name="additional_ips[]" class="form-control" value="<?= htmlspecialchars($ip) ?>">
              <button type="button" class="btn btn-danger btn-sm" onclick="removeIp(this)">Remove</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="addIp()">Add IP</button>
      </div>
    </div>

    <!-- Cacti Monitoring -->
    <div class="card">
      <div class="card-header">Cacti Monitoring</div>
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label class="form-label">Cacti URL</label>
          <input class="form-control" name="cacti_url" value="<?= htmlspecialchars($data['cacti_url'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Cacti Username</label>
          <input class="form-control" name="cacti_username" value="<?= htmlspecialchars($data['cacti_username'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Cacti Password</label>
          <input type="password" class="form-control" name="cacti_password" value="<?= htmlspecialchars($data['cacti_password'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div class="text-end mt-3">
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
  </form>
</div>

<script>
  function updatePopIp() {
    let popSelect = document.getElementById('pop_name');
    let popIpInput = document.getElementById('pop_ip');
    let selectedOption = popSelect.options[popSelect.selectedIndex];
    popIpInput.value = selectedOption.getAttribute('data-ip') || '';
  }

  function updateSwitchIp() {
    let switchSelect = document.getElementById('switch_name');
    let switchIpInput = document.getElementById('switch_ip');
    let selectedOption = switchSelect.options[switchSelect.selectedIndex];
    switchIpInput.value = selectedOption.getAttribute('data-ip') || '';
  }

  function addIp() {
    let container = document.getElementById('ip-list');
    let div = document.createElement('div');
    div.className = 'input-group mb-1';
    div.innerHTML = `<input type="text" name="additional_ips[]" class="form-control" placeholder="Enter IP address">
                     <button type="button" class="btn btn-danger btn-sm" onclick="removeIp(this)">Remove</button>`;
    container.appendChild(div);
  }

  function removeIp(button) {
    button.parentElement.remove();
  }

  function togglePppoeFields() {
    let authType = document.getElementById('auth_type').value;
    let usernameDiv = document.getElementById('pppoe_username_div');
    let passwordDiv = document.getElementById('pppoe_password_div');
    if (authType === 'PPPoE') {
      usernameDiv.style.display = 'block';
      passwordDiv.style.display = 'block';
    } else {
      usernameDiv.style.display = 'none';
      passwordDiv.style.display = 'none';
    }
  }

  function addContactField() {
    const div = document.createElement('div');
    div.className = 'input-group mb-1';
    div.innerHTML = `<input type="text" class="form-control" name="contact_number[]" maxlength="15" required pattern="\\d{10,15}" title="10 to 15 digits">
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

  // On page load toggle PPPoE fields if needed
  document.addEventListener('DOMContentLoaded', () => {
    togglePppoeFields();
  });

  // Highlight duplicate IPs on form submit
  document.getElementById('editCircuitForm').addEventListener('submit', function(e) {
    // Gather all IP values
    const ipInputs = document.querySelectorAll('input[name="additional_ips[]"]');
    const ips = [];
    let duplicate = false;
    let duplicateIPs = [];

    // Remove previous highlighting
    ipInputs.forEach(input => input.classList.remove('is-invalid'));

    ipInputs.forEach(input => {
      const val = input.value.trim();
      if (val) {
        if (ips.includes(val)) {
          duplicate = true;
          duplicateIPs.push(val);
        }
        ips.push(val);
      }
    });

    if (duplicate) {
      // Highlight all duplicate fields
      ipInputs.forEach(input => {
        if (duplicateIPs.includes(input.value.trim())) {
          input.classList.add('is-invalid');
        }
      });
      alert('Duplicate IP address detected: ' + [...new Set(duplicateIPs)].join(', ') + '.\nPlease ensure all additional IPs are unique.');
      e.preventDefault();
    }
  });
</script>
</body>
</html>