<?php
// File: customer_details.php
session_name('oss_portal');
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
ini_set('display_errors', 0);
error_reporting(E_ALL);

include 'includes/db.php';

// Validate circuit_id
if (empty($_GET['circuit_id'])) {
    die("No circuit ID provided.");
}
$circuit_id = htmlspecialchars($_GET['circuit_id']);

// Helper function
function display($value) {
    return htmlspecialchars($value !== null && $value !== '' ? $value : 'NA');
}

// Fetch main data (add netmask and remark)
$sql = "
  SELECT cbi.*,
         nd.product_type, nd.pop_name, nd.pop_ip, nd.switch_name,
         nd.switch_ip, nd.switch_port, nd.bandwidth,nd.link_type, nd.circuit_status,
         cnd.installation_date, cnd.wan_ip, cnd.netmask, cnd.wan_gateway,
         cnd.dns1, cnd.dns2, cnd.auth_type,
         cnd.PPPoE_auth_username, cnd.PPPoE_auth_password,
         cnd.cacti_url, cnd.cacti_username, cnd.cacti_password,
         cnd.remark
    FROM customer_basic_information AS cbi
    JOIN network_details           AS nd   ON cbi.circuit_id = nd.circuit_id
    LEFT JOIN circuit_network_details AS cnd ON cbi.circuit_id = cnd.circuit_id
   WHERE cbi.circuit_id = :cid
";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':cid', $circuit_id, PDO::PARAM_STR);
$stmt->execute();
$data = $stmt->fetch(PDO::FETCH_ASSOC) ?: die("Not found.");

// Fetch extra IPs
$ipsStmt = $pdo->prepare("SELECT ip_address FROM circuit_ips WHERE circuit_id = :cid");
$ipsStmt->bindParam(':cid', $circuit_id, PDO::PARAM_STR);
$ipsStmt->execute();
$ips = $ipsStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all contact numbers
$contact_stmt = $pdo->prepare("SELECT contact_number FROM customer_contacts WHERE circuit_id = ?");
$contact_stmt->execute([$circuit_id]);
$contact_numbers = $contact_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all emails
$email_stmt = $pdo->prepare("SELECT ce_email_id FROM customer_emails WHERE circuit_id = ?");
$email_stmt->execute([$circuit_id]);
$ce_email_ids = $email_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Customer and Circuit Details" />
  <title>Details – <?= display($circuit_id) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" />
  <style>
    body { font-family: 'Roboto', sans-serif; background-color: #f9f9f9; padding: 20px; font-size: 14px; }
    .container { max-width: 960px; margin: 0 auto; }
    .tabs .tab a { color: #007bff; font-weight: 500; transition: color 0.3s; font-size: 14px; }
    .tabs .tab a:hover { color: #0288d1; }
    .tabs .tab a.active { color: #0288d1; border-bottom: 2px solid #0288d1; }
    .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-top: 20px; }
    .card-title { font-size: 1.2rem; font-weight: 500; color: #444; margin-bottom: 20px; }
    .table-container { overflow-x: auto; margin-top: 20px; }
    table.striped > tbody > tr:nth-child(odd) { background-color: #f2f2f2; }
    table td, table th { padding: 10px 8px; text-align: left; white-space: nowrap; font-size: 13px; vertical-align: top; }
    .badge { font-size: 12px; padding: 4px 8px; border-radius: 20px; }
    .copy-btn { color: #0288d1; cursor: pointer; margin-left: 5px; }
    .copy-btn:hover { text-decoration: underline; }
    .btn-back { margin-bottom: 20px; }
    @media only screen and (max-width: 768px) {
      table td, table th { font-size: 12px; }
      .card-title { font-size: 1rem; }
      table td, table th { white-space: normal; }
    }
  </style>
</head>
<body>
<div class="container">
  <a href="view_customer.php" class="btn blue lighten-1 btn-back">
    <i class="material-icons left">arrow_back</i>Back
  </a>
  <h5 class="center-align blue-text text-darken-2">Circuit ID: <?= display($circuit_id) ?></h5>

  <div class="row">
    <div class="col s12">
      <ul class="tabs">
        <li class="tab col s2"><a href="#basic" class="active">Basic Info</a></li>
        <li class="tab col s2"><a href="#network">Network</a></li>
        <li class="tab col s2"><a href="#wan">WAN/DNS</a></li>
        <li class="tab col s3"><a href="#auth">Auth/Cacti</a></li>
        <li class="tab col s3"><a href="#ips">Extra IPs</a></li>
      </ul>
    </div>
  </div>

  <!-- Basic Info -->
  <div id="basic" class="col s12">
    <div class="card">
      <div class="card-content">
        <span class="card-title">Basic Information</span>
        <div class="table-container">
          <table class="striped">
            <tbody>
              <tr>
                <th>Organization</th>
                <td><?= display($data['organization_name']) ?></td>
              </tr>
              <tr>
                <th>Install Date</th>
                <td><?= display($data['installation_date']) ?></td>
              </tr>
              <tr>
                <th>Address</th>
                <td><?= nl2br(display($data['customer_address'])) ?></td>
              </tr>
              <tr>
                <th>Contact Person</th>
                <td><?= display($data['contact_person_name']) ?></td>
              </tr>
              <tr>
                <th>Phone</th>
                <td>
                  <?php
                    if (!empty($contact_numbers)) {
                      echo implode('<br>', array_map('htmlspecialchars', $contact_numbers));
                    } else {
                      echo 'NA';
                    }
                  ?>
                </td>
              </tr>
              <tr>
                <th>Email</th>
                <td>
                  <?php
                    if (!empty($ce_email_ids)) {
                      echo implode('<br>', array_map('htmlspecialchars', $ce_email_ids));
                    } else {
                      echo 'NA';
                    }
                  ?>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Network Info -->
  <div id="network" class="col s12">
    <div class="card">
      <div class="card-content">
        <span class="card-title">Network Details</span>
        <div class="table-container">
          <table class="striped">
            <tbody>
              <tr>
                <th>Product Type</th>
                <td><?= display($data['product_type']) ?></td>
              </tr>
              <tr>
                <th>Circuit Status</th>
                <td>
                  <span class="badge <?= $data['circuit_status'] === 'Active' ? 'green white-text' : 'red white-text' ?>">
                    <?= display($data['circuit_status']) ?>
                  </span>
                </td>
              </tr>
              <tr>
                <th>POP</th>
                <td><?= display($data['pop_name']) ?> (<?= display($data['pop_ip']) ?>)</td>
              </tr>
              <tr>
                <th>Switch</th>
                <td><?= display($data['switch_name']) ?> (<?= display($data['switch_ip']) ?>)</td>
              </tr>
              <tr>
                <th>Port</th>
                <td><?= display($data['switch_port']) ?></td>
              </tr>
              <tr>
                <th>Last mile</th>
                <td><?= display($data['link_type']) ?></td>
              </tr>
              <tr>
                <th>Bandwidth</th>
                <td><?= display($data['bandwidth']) ?></td>
              </tr>
              <tr>
                <th>Remark</th>
                <td><?= nl2br(display($data['remark'])) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- WAN/DNS -->
  <div id="wan" class="col s12">
    <div class="card">
      <div class="card-content">
        <span class="card-title">WAN / DNS</span>
        <div class="table-container">
          <table class="striped">
            <tbody>
              <tr>
                <th>WAN IP</th>
                <td>
                  <span class="copy-btn" id="wan_ip" onclick="copyToClipboard('wan_ip')"><?= display($data['wan_ip']) ?></span>
                </td>
              </tr>
              <tr>
                <th>Netmask</th>
                <td><?= display($data['netmask']) ?></td>
              </tr>
              <tr>
                <th>WAN Gateway</th>
                <td><?= display($data['wan_gateway']) ?></td>
              </tr>
              <tr>
                <th>DNS 1</th>
                <td><?= display($data['dns1']) ?></td>
              </tr>
              <tr>
                <th>DNS 2</th>
                <td><?= display($data['dns2']) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Auth / Cacti -->
  <div id="auth" class="col s12">
    <div class="card">
      <div class="card-content">
        <span class="card-title">Authentication & Cacti Monitoring</span>
        <div class="table-container">
          <table class="striped">
            <tbody>
              <tr>
                <th>Auth Type</th>
                <td><?= display($data['auth_type']) ?></td>
              </tr>
              <tr>
                <th>PPPoE Username</th>
                <td><?= display($data['PPPoE_auth_username']) ?></td>
              </tr>
              <tr>
                <th>PPPoE Password</th>
                <td><?= display($data['PPPoE_auth_password']) ?></td>
              </tr>
              <tr>
                <th>Cacti URL</th>
                <td>
                  <?php if (!empty($data['cacti_url'])): ?>
                    <a href="<?= htmlspecialchars($data['cacti_url']) ?>" target="_blank"><?= display($data['cacti_url']) ?></a>
                  <?php else: ?>
                    NA
                  <?php endif; ?>
                </td>
              </tr>
              <tr>
                <th>Cacti Username</th>
                <td><?= display($data['cacti_username']) ?></td>
              </tr>
              <tr>
                <th>Cacti Password</th>
                <td><?= display($data['cacti_password']) ?></td>
              </tr>
              <!-- REMARK REMOVED FROM HERE -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Extra IPs -->
  <div id="ips" class="col s12">
    <div class="card">
      <div class="card-content">
        <span class="card-title">Additional IP Addresses</span>
        <div class="table-container">
          <?php if (!empty($ips)): ?>
            <table class="striped">
              <thead>
                <tr>
                  <th>#</th>
                  <th>IP Address</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ips as $idx => $ip): ?>
                <tr>
                  <td><?= $idx + 1 ?></td>
                  <td><?= display($ip) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p>No additional IP addresses found.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    var tabs = document.querySelectorAll('.tabs');
    M.Tabs.init(tabs);
  });
  function copyToClipboard(id) {
    var text = document.getElementById(id);
    if (!text) {
      alert('No text found to copy.');
      return;
    }
    var tempInput = document.createElement('input');
    document.body.appendChild(tempInput);
    tempInput.value = text.innerText || text.textContent;
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    M.toast({html: 'Copied to clipboard!'});
  }
</script>
</body>
</html>