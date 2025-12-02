<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Edit Circuit <?= htmlspecialchars($circuit_id) ?></title>
  <link rel="stylesheet" href="edit_customer.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>
<div class="container mt-5">
  <h3>Edit Circuit: <?= htmlspecialchars($circuit_id) ?></h3>
  <form method="POST" action="update_customer.php" id="editCircuitForm">
    <input type="hidden" name="circuit_id" value="<?= htmlspecialchars($circuit_id) ?>">
    <div class="card">
      <div class="card-header">Basic Information</div>
      <div class="card-body">
        <input class="form-control mb-2" name="organization_name" value="<?= htmlspecialchars($data['organization_name']) ?>" required placeholder="Organization">
        <input class="form-control mb-2" name="contact_person_name" value="<?= htmlspecialchars($data['contact_person_name']) ?>" required placeholder="Contact Person">
        <label>Contact Numbers</label>
        <div id="contacts">
          <?php foreach($contact_numbers ?: [''] as $num): ?>
            <div class="input-group mb-1">
              <input type="text" class="form-control" name="contact_number[]" maxlength="15" required value="<?= htmlspecialchars($num) ?>">
              <button type="button" class="btn btn-outline-danger" onclick="removeField(this)">-</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addContactField()">Add Contact</button>
        <label class="mt-2">Email IDs</label>
        <div id="emails">
          <?php foreach($ce_email_ids ?: [''] as $email): ?>
            <div class="input-group mb-1">
              <input type="email" class="form-control" name="ce_email_id[]" maxlength="100" required value="<?= htmlspecialchars($email) ?>">
              <button type="button" class="btn btn-outline-danger" onclick="removeField(this)">-</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addEmailField()">Add Email</button>
        <input type="date" class="form-control mt-2" name="installation_date" value="<?= htmlspecialchars($data['installation_date'] ?? '') ?>">
        <textarea class="form-control mt-2" name="customer_address" required><?= htmlspecialchars($data['customer_address']) ?></textarea>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Network Details</div>
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label>Product Type</label>
          <select name="product_type" class="form-select" required>
            <?php foreach($product_types as $pt): ?>
              <option value="<?= htmlspecialchars($pt) ?>" <?= $pt === $data['product_type'] ? 'selected' : '' ?>><?= htmlspecialchars($pt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label>POP Name</label>
          <select name="pop_name" id="pop_name" class="form-select pop-select" required onchange="updatePopIp()">
            <?php foreach($pop_data as $pop): ?>
              <option value="<?= htmlspecialchars($pop['pop_name']) ?>" data-ip="<?= htmlspecialchars($pop['pop_ip']) ?>" <?= $pop['pop_name'] === $data['pop_name'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($pop['pop_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label>Switch Name</label>
          <select name="switch_name" id="switch_name" class="form-select switch-select" required onchange="updateSwitchIp()">
            <?php foreach($switch_data as $switch): ?>
              <option value="<?= htmlspecialchars($switch['switch_name']) ?>" data-ip="<?= htmlspecialchars($switch['switch_ip']) ?>" <?= $switch['switch_name'] === $data['switch_name'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($switch['switch_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label>POP IP</label>
          <input class="form-control" name="pop_ip" id="pop_ip" value="<?= htmlspecialchars($data['pop_ip']) ?>" required>
        </div>
        <div class="col-md-4">
          <label>Switch IP</label>
          <input class="form-control" name="switch_ip" id="switch_ip" value="<?= htmlspecialchars($data['switch_ip']) ?>" required>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Circuit Network Details</div>
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label>Authentication Type</label>
          <select class="form-select" name="auth_type" id="auth_type" onchange="toggleNetworkFields()">
            <option value="">-- Select Type --</option>
            <option value="Static" <?= (strtolower($data['auth_type'] ?? '') === 'static') ? 'selected' : '' ?>>Static</option>
            <option value="PPPoE" <?= (strtolower($data['auth_type'] ?? '') === 'pppoe') ? 'selected' : '' ?>>PPPoE</option>
          </select>
        </div>
        <div id="network_fields" class="row g-3" style="display:none;">
          <div class="col-md-4" id="wan_ip_field" style="display:none;">
            <label>WAN IP</label>
            <input id="wan_ip" class="form-control" name="wan_ip" value="<?= htmlspecialchars($data['wan_ip'] ?? '') ?>">
          </div>
          <div class="col-md-4 static-fields" style="display:none;">
            <label>Netmask</label>
            <input id="netmask" class="form-control" name="netmask" value="<?= htmlspecialchars($data['netmask'] ?? '') ?>">
          </div>
          <div class="col-md-4 static-fields" style="display:none;">
            <label>WAN Gateway</label>
            <input id="wan_gateway" class="form-control" name="wan_gateway" value="<?= htmlspecialchars($data['wan_gateway'] ?? '') ?>">
          </div>
          <div class="col-md-4 static-fields" style="display:none;">
            <label>DNS 1</label>
            <input id="dns1" class="form-control" name="dns1" value="<?= htmlspecialchars($data['dns1'] ?? '') ?>">
          </div>
          <div class="col-md-4 static-fields" style="display:none;">
            <label>DNS 2</label>
            <input id="dns2" class="form-control" name="dns2" value="<?= htmlspecialchars($data['dns2'] ?? '') ?>">
          </div>
          <div class="col-md-4 pppoe-fields" style="display:none;">
            <label>PPPoE Username</label>
            <input id="pppoe_username" class="form-control" name="PPPoE_auth_username" value="<?= htmlspecialchars($data['PPPoE_auth_username'] ?? '') ?>">
          </div>
          <div class="col-md-4 pppoe-fields" style="display:none;">
            <label>PPPoE Password</label>
            <input id="pppoe_password" type="password" class="form-control" name="PPPoE_auth_password" value="<?= htmlspecialchars($data['PPPoE_auth_password'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>

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
    <div class="text-end mt-3">
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="edit_customer.js"></script>
</body>
</html>