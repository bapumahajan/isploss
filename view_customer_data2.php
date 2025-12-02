<?php
//filename:view_customer_data.php
session_name('oss_portal');
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=()');

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$timeout_duration = 900;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();
require_once 'includes/db.php';

if (!isset($_SESSION['cacti_tokens'])) {
    $_SESSION['cacti_tokens'] = [];
}
function showval($val) {
    return htmlspecialchars(($val !== null && $val !== '') ? $val : 'NA');
}
function showdate($val) {
    if (!$val || $val === '0000-00-00') return 'NA';
    $d = DateTime::createFromFormat('Y-m-d', $val);
    if (!$d) $d = DateTime::createFromFormat('Y-m-d H:i:s', $val);
    return $d ? strtoupper($d->format('d-M-Y')) : 'NA';
}

// Handle Local Graph ID form submission
$cactiGraphSuccess = '';
$cactiGraphError = '';
$cactiGraphUpdatedCircuitId = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['graph_form_circuit_id'], $_POST['local_graph_id'])) {
    $graph_circuit_id = trim($_POST['graph_form_circuit_id']);
    $new_graph_id = trim($_POST['local_graph_id']);

    // Validate
    if (!ctype_digit($new_graph_id) || (int)$new_graph_id < 1) {
        $cactiGraphError = "Invalid Local Graph ID.";
    } else {
        try {
            // Check if already exists
            $stmt = $pdo->prepare("SELECT * FROM cacti_graphs WHERE circuit_id = ?");
            $stmt->execute([$graph_circuit_id]);
            if ($stmt->rowCount() > 0) {
                // Update
                $update = $pdo->prepare("UPDATE cacti_graphs SET local_graph_id = ? WHERE circuit_id = ?");
                $update->execute([$new_graph_id, $graph_circuit_id]);
                $cactiGraphSuccess = "Local Graph ID updated successfully!";
            } else {
                // Insert
                $insert = $pdo->prepare("INSERT INTO cacti_graphs (circuit_id, local_graph_id) VALUES (?, ?)");
                $insert->execute([$graph_circuit_id, $new_graph_id]);
                $cactiGraphSuccess = "Local Graph ID linked successfully!";
            }
            $cactiGraphUpdatedCircuitId = $graph_circuit_id;
        } catch (PDOException $e) {
            $cactiGraphError = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Customer Circuit Dashboard</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body {
    font-family: 'Inter', Arial, sans-serif;
    background: #f7fafc;
    color: #1e293b;
    font-size: 15px;
}
.topbar {
    position: sticky; top: 0; z-index: 100;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    display: flex; align-items: center; justify-content: space-between;
    height: 56px; padding: 0 22px;
    box-shadow: 0 1px 8px 0 #e5e7eb;
}
.topbar-title {
    font-weight: 700; color: #4338ca; font-size: 1.08rem; letter-spacing: .3px;
    display: flex; align-items: center;
    gap: 6px;
}
.topbar-title .bi { color: #6366f1; font-size: 1.2rem; }
.search-bar {
    display: flex; align-items: center;
    background: #f4f6fb;
    border-radius: 8px;
    min-width: 140px; max-width: 260px; padding: 0 10px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px #6366f10a;
    transition: box-shadow 0.13s, border 0.13s;
}
.search-bar:focus-within {
    border: 1.5px solid #6366f1 !important;
    box-shadow: 0 2px 7px #6366f120;
}
.search-bar input {
    border: none; outline: none; background: none; flex: 1;
    font-size: 0.97rem; padding: 7px 0; color: #1e293b;
}
.search-bar button {
    border: none; background: #6366f1; color: #fff;
    border-radius: 6px; padding: 4px 10px 4px 7px;
    margin-left: 3px; font-size: 1em; display: flex; align-items: center;
    transition: background 0.14s;
}
.search-bar button:hover { background: #4338ca; }
.topbar .dropdown .btn {
    color: #4338ca; background: transparent; border: none;
    border-radius: 7px; padding: 6px 10px 6px 6px;
    font-size: 0.95rem;
}
.dashboard-main {
    max-width: 980px; margin: 24px auto 0 auto; padding: 0 1vw;
}
.card {
    background: #fff;
    border-radius: 16px;
    margin-bottom: 22px;
    box-shadow: 0 3px 16px 0 #e5e7eb90;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    color: #1e293b;
}
.card-header {
    border-top: 4px solid #6366f1;
    background: #f7fafc;
    color: #4338ca;
    padding: 14px 24px 8px 24px;
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid #e5e7eb;
}
.card-header h5 { 
    margin: 0;
    font-weight: 600;
    font-size: 1.05rem;
    color: #4338ca;
    letter-spacing: .2px;
}
.status-badge {
    padding: 3px 12px;
    border-radius: 12px;
    font-size: .92rem;
    font-weight: 600;
    background: #f0fdf4;
    color: #22c55e;
    margin-left: 18px;
    letter-spacing: .5px;
}
.status-badge.bg-danger { background: #fef2f2 !important; color: #ef4444 !important; }
.status-badge.bg-warning { background: #fef9c3 !important; color: #eab308 !important; }
.status-badge.bg-secondary { background: #f5f5f5 !important; color: #64748b !important; }
.modern-tabs {
    border-bottom: none; background: #fff;
    padding: 0 24px; display: flex; gap: 0.22rem; overflow-x: auto;
    margin-bottom: -1px;
}
.modern-tabs .nav-link {
    border: none; border-radius: 999px; margin-bottom: -1px;
    background: transparent; color: #6366f1; font-weight: 600;
    font-size: 0.97rem; padding: 7px 13px;
    transition: background .13s, color .13s;
}
.modern-tabs .nav-link.active, .modern-tabs .nav-link:focus, .modern-tabs .nav-link:hover {
    background: #6366f1;
    color: #fff;
}
.tab-content { background: #fff; }
.tab-pane {
    padding: 13px 20px 15px 20px; background: #fff;
    border-radius: 0 0 11px 11px;
}
.tab-pane table { background: transparent; font-size: 0.94rem; }
.tab-pane tr { border-bottom: 1px solid #e5e7eb; }
.tab-pane th {
    font-weight: 500; color: #6366f1; padding: 6px 12px 6px 10px;
    border: none; background: #f4f6fb; width: 150px;
}
.tab-pane td {
    color: #1e293b; padding: 6px 5px; border: none; background: transparent;
}
.tab-pane .table thead th { background: #f4f6fb; color: #6366f1; }
.tab-pane .table tbody tr:nth-child(even) td { background: #f9fafb; }
.tab-pane .table tbody tr:hover td { background: #e0e7ff; transition: background .11s; }
.alert {
    border-radius: 9px;
    font-size: 0.92rem;
    margin: 14px 0;
    background: #f4f6fb;
    color: #1e293b;
    border: 1px solid #6366f1;
}
@media (max-width: 900px) {
    .dashboard-main { max-width: 100vw; }
    .card, .tab-pane, .card-header, .modern-tabs { padding-left: 5px; padding-right: 5px;}
    .tab-pane { padding-left: 5px; padding-right: 5px;}
    .topbar { padding: 0 8px; height: 48px;}
}
@media (max-width: 650px) {
    .tab-pane, .card-header, .modern-tabs { padding-left: 2vw; padding-right: 2vw;}
}
</style>
</head>
<body>
    <div class="topbar">
        <span class="topbar-title">
            <i class="bi bi-diagram-3"></i>
            Search Circuits
        </span>
        <form method="GET" class="search-bar mb-0">
            <input type="text" name="search" placeholder="Search Circuit ID, WAN IP, or Organization"
                value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search'] ?? '') : '' ?>" required>
            <button type="submit"><i class="bi bi-search"></i></button>
        </form>
        <div class="dropdown">
            <button class="btn dropdown-toggle" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle align-text-bottom"></i>
                <?= htmlspecialchars($_SESSION['username']) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                <li>
                    <a class="dropdown-item" href="dashboard.php">
                        <i class="bi bi-grid"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <div class="dashboard-content">
      <div class="dashboard-main">
        <?php
        if (isset($_GET['search'])) {
            $search = '%' . ($_GET['search'] ?? '') . '%';
            try {
                $stmt = $pdo->prepare("
                    SELECT cbi.*, cnd.*, nd.*
                    FROM customer_basic_information cbi
                    LEFT JOIN circuit_network_details cnd ON cbi.circuit_id = cnd.circuit_id
                    LEFT JOIN network_details nd ON cbi.circuit_id = nd.circuit_id
                    WHERE cbi.circuit_id LIKE :search OR cbi.organization_name LIKE :search OR cnd.wan_ip LIKE :search
                ");
                $stmt->execute(['search' => $search]);
                if ($stmt->rowCount() > 0) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $ip_stmt = $pdo->prepare("SELECT * FROM circuit_ips WHERE circuit_id = ?");
                        $ip_stmt->execute([$row['circuit_id'] ?? '']);
                        $circuit_ips = $ip_stmt->fetchAll(PDO::FETCH_ASSOC);

                        $stmt_contacts = $pdo->prepare("SELECT contact_number FROM customer_contacts WHERE circuit_id = ?");
                        $stmt_contacts->execute([$row['circuit_id'] ?? '']);
                        $contact_numbers = $stmt_contacts->fetchAll(PDO::FETCH_COLUMN);

                        $stmt_emails = $pdo->prepare("SELECT ce_email_id FROM customer_emails WHERE circuit_id = ?");
                        $stmt_emails->execute([$row['circuit_id'] ?? '']);
                        $ce_email_ids = $stmt_emails->fetchAll(PDO::FETCH_COLUMN);

                        $contact_numbers_str = $contact_numbers ? htmlspecialchars(implode(', ', array_filter($contact_numbers))) : 'NA';
                        $ce_email_ids_str = $ce_email_ids ? htmlspecialchars(implode(', ', array_filter($ce_email_ids))) : 'NA';

                        $stmtGraph = $pdo->prepare("SELECT local_graph_id FROM cacti_graphs WHERE circuit_id = ?");
                        $stmtGraph->execute([$row['circuit_id'] ?? '']);
                        $graph = $stmtGraph->fetch(PDO::FETCH_ASSOC);

                        $status_class = match (strtolower($row['circuit_status'] ?? '')) {
                            'active' => 'status-badge',
                            'terminated' => 'status-badge bg-danger',
                            'suspended' => 'status-badge bg-warning text-dark',
                            default => 'status-badge bg-secondary',
                        };

                        // Port status variables
                        $switch_ip = $row['switch_ip'] ?? '';
                        $switch_port = $row['switch_port'] ?? '';
                        $circuit_id = $row['circuit_id'] ?? '';
        ?>
        <div class="card">
            <div class="card-header">
                <h5>
                    <?= showval($row['organization_name'] ?? null) ?>
                    <span class="text-muted ms-2" style="color:#8b8b8b;opacity:.8;">[<?= showval($row['circuit_id'] ?? null) ?>]</span>
                </h5>
                <span class="<?= $status_class ?>"><?= showval($row['circuit_status'] ?? null) ?></span>
            </div>
            <ul class="nav modern-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#basic<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-info-circle"></i> Basic</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#network<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-hdd-network"></i> Network</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ip<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-key"></i> IP/Auth</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#circuitips<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-list-ol"></i> IPs</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cacti<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-bar-chart"></i> Cacti</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#thirdparty<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-diagram-2"></i> 3rd Party</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#portstatus<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-plug"></i> Port Status</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="basic<?= $row['circuit_id'] ?>">
                    <table class="table mb-0">
                        <tr><th>Address</th><td><?= showval($row['customer_address'] ?? null) ?></td></tr>
                        <tr><th>City</th><td><?= showval($row['City'] ?? null) ?></td></tr>
                        <tr><th>Contact Person</th><td><?= showval($row['contact_person_name'] ?? null) ?></td></tr>
                        <tr><th>Contact Numbers</th><td><?= $contact_numbers_str ?></td></tr>
                        <tr><th>Email IDs</th><td><?= $ce_email_ids_str ?></td></tr>
                        <tr><th>Installation Date</th><td><?= showdate($row['installation_date'] ?? null) ?></td></tr>
                    </table>
                </div>
                <div class="tab-pane fade" id="network<?= $row['circuit_id'] ?>">
                    <table class="table mb-0">
                        <tr><th>Product Type</th><td><?= showval($row['product_type'] ?? null) ?></td></tr>
                        <tr><th>POP Name</th><td><?= showval($row['pop_name'] ?? null) ?></td></tr>
                        <tr><th>POP IP</th><td><?= showval($row['pop_ip'] ?? null) ?></td></tr>
                        <tr><th>Switch Name</th><td><?= showval($row['switch_name'] ?? null) ?></td></tr>
                        <tr><th>Switch IP</th><td><?= showval($row['switch_ip'] ?? null) ?></td></tr>
                        <tr><th>Switch Port</th><td><?= showval($row['switch_port'] ?? null) ?></td></tr>
                        <tr><th>Bandwidth</th><td><?= showval($row['bandwidth'] ?? null) ?></td></tr>
                        <tr><th>Link Type</th><td><?= showval($row['link_type'] ?? null) ?></td></tr>
                        <tr><th>VLAN</th><td><?= showval($row['vlan'] ?? null) ?></td></tr>
						<tr><th>Remark</th><td><?= nl2br(showval($row['remark'] ?? null)) ?></td></tr>
                    </table>
                </div>
                <div class="tab-pane fade" id="ip<?= $row['circuit_id'] ?>">
                    <table class="table mb-0">
                        <tr><th>WAN IP</th><td><?= showval($row['wan_ip'] ?? null) ?></td></tr>
                        <tr><th>Netmask</th><td><?= showval($row['netmask'] ?? null) ?></td></tr>
                        <tr><th>WAN Gateway</th><td><?= showval($row['wan_gateway'] ?? null) ?></td></tr>
                        <tr><th>DNS 1</th><td><?= showval($row['dns1'] ?? null) ?></td></tr>
                        <tr><th>DNS 2</th><td><?= showval($row['dns2'] ?? null) ?></td></tr>
                        <tr><th>Auth Type</th><td><?= showval($row['auth_type'] ?? null) ?></td></tr>
                        <tr><th>PPPoE Username</th><td><?= showval($row['PPPoE_auth_username'] ?? null) ?></td></tr>
                        <tr><th>PPPoE Password</th><td><?= showval($row['PPPoE_auth_password'] ?? null) ?></td></tr>
                    </table>
                </div>
                <div class="tab-pane fade" id="circuitips<?= $row['circuit_id'] ?>">
                    <?php if ($circuit_ips): ?>
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>IP Address</th>
                                    <th>IP Type</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($circuit_ips as $ip): ?>
                                    <tr>
                                        <td><?= showval($ip['id'] ?? null) ?></td>
                                        <td><?= showval($ip['ip_address'] ?? null) ?></td>
                                        <td><?= showval($ip['ip_type'] ?? null) ?></td>
                                        <td><?= showval($ip['description'] ?? null) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No circuit IPs found for this circuit.</p>
                    <?php endif; ?>
                </div>
                <div class="tab-pane fade" id="portstatus<?= $circuit_id ?>">
                    <h5 class="mb-3"><i class="bi bi-hdd-network"></i> Switch Port Status</h5>
                    <table class="table table-bordered mb-0 w-auto">
                        <tr>
                            <th>Switch IP</th>
                            <td><?= showval($switch_ip) ?></td>
                        </tr>
                        <tr>
                            <th>Switch Port</th>
                            <td><?= showval($switch_port) ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span id="snmp_status_cell_<?= $circuit_id ?>">Checking...</span>
                                <?php if($switch_ip && $switch_port): ?>
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                    onclick="refreshSnmpStatusUp('<?= htmlspecialchars($switch_ip) ?>',
                                                                 '<?= htmlspecialchars($switch_port) ?>',
                                                                 'snmp_status_cell_<?= $circuit_id ?>', this)">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                                <script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    refreshSnmpStatusUp(
                                        '<?= htmlspecialchars($switch_ip) ?>',
                                        '<?= htmlspecialchars($switch_port) ?>',
                                        'snmp_status_cell_<?= $circuit_id ?>',
                                        document.querySelector('[onclick*="snmp_status_cell_<?= $circuit_id ?>"]')
                                    );
                                });
                                </script>
                                <?php else: ?>
                                    <span class="text-muted">NA</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="tab-pane fade" id="cacti<?= $row['circuit_id'] ?>">
                    <?php
                        // Show the success/error only for the circuit that was updated
                        if ($circuit_id === $cactiGraphUpdatedCircuitId && !empty($cactiGraphSuccess)) {
                            echo '<div class="alert alert-success">' . htmlspecialchars($cactiGraphSuccess) . '</div>';
                        } elseif ($circuit_id === $cactiGraphUpdatedCircuitId && !empty($cactiGraphError)) {
                            echo '<div class="alert alert-danger">' . htmlspecialchars($cactiGraphError) . '</div>';
                        }
                        if ($graph):
                            $local_graph_id = $graph['local_graph_id'] ?? '';
                            $token = bin2hex(random_bytes(16));
                            $_SESSION['cacti_tokens'][$token] = $local_graph_id;
                            $graph_url = "cacti_proxy.php?type=graph_image&token=" . $token . "&rra_id=all";
                            ?>
                        <div class="text-center mb-2">
                            <img src="<?= htmlspecialchars($graph_url) ?>" alt="Cacti Graph" class="img-fluid shadow-sm" />
                            <div>
                                <a href="cacti_proxy.php?type=graph_image&token=<?= $token ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm mt-2">
                                    <i class="bi bi-bar-chart"></i> View Full Graph
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php
                        $circuitIdEsc = htmlspecialchars($row['circuit_id'] ?? '');
                        $graph_id_field_id = "local_graph_id_{$circuitIdEsc}_display";
                        $edit_btn_id = "edit_btn_{$circuitIdEsc}";
                        $form_id = "graph_form_{$circuitIdEsc}";
                        $cancel_btn_id = "{$form_id}_cancel";
                        $local_graph_id = $graph['local_graph_id'] ?? '';
                    ?>
                    <div id="<?= $graph_id_field_id ?>" class="mb-2">
                        <span><strong>Local Graph ID:</strong> <?= $local_graph_id ? showval($local_graph_id) : '-' ?></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="<?= $edit_btn_id ?>"><i class="bi bi-pencil"></i> Edit</button>
                    </div>
                    <form method="post" class="mt-2 d-none" id="<?= $form_id ?>">
                        <input type="hidden" name="graph_form_circuit_id" value="<?= $circuitIdEsc ?>">
                        <div class="mb-2">
                            <input type="number" name="local_graph_id" id="local_graph_id_input_<?= $circuitIdEsc ?>" class="form-control form-control-sm"
                                value="<?= $local_graph_id ?>" min="1" required placeholder="Local Graph ID">
                        </div>
                        <button type="submit" class="btn btn-<?= $graph ? 'warning' : 'primary' ?> btn-sm">
                            <?= $graph ? 'Update' : 'Link' ?>
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm ms-2" id="<?= $cancel_btn_id ?>">Cancel</button>
                    </form>
                    <script>
                    (function(){
                        var editBtn = document.getElementById('<?= $edit_btn_id ?>');
                        var displayDiv = document.getElementById('<?= $graph_id_field_id ?>');
                        var form = document.getElementById('<?= $form_id ?>');
                        var cancelBtn = document.getElementById('<?= $cancel_btn_id ?>');
                        if(editBtn && displayDiv && form && cancelBtn) {
                            editBtn.onclick = function() {
                                displayDiv.classList.add('d-none');
                                form.classList.remove('d-none');
                            };
                            cancelBtn.onclick = function() {
                                form.classList.add('d-none');
                                displayDiv.classList.remove('d-none');
                            };
                        }
                    })();
                    </script>
                </div>
                <div class="tab-pane fade" id="thirdparty<?= htmlspecialchars($row['circuit_id']) ?>">
                    <?php if (!empty($third_parties)): ?>
                        <?php foreach ($third_parties as $tp): ?>
                            <table class="table mb-4">
                                <tr><th>Third Party Name</th><td><?= showval($tp['Third_party_name']) ?></td></tr>
                                <tr><th>Third Party Circuit ID</th><td><?= showval($tp['tp_circuit_id']) ?></td></tr>
                                <tr><th>Type of Service</th><td><?= showval($tp['tp_type_service']) ?></td></tr>
                                <tr><th>End A</th><td><?= showval($tp['end_a']) ?></td></tr>
                                <tr><th>End B</th><td><?= showval($tp['end_b']) ?></td></tr>
                                <tr><th>Bandwidth</th><td><?= showval($tp['bandwidth']) ?></td></tr>
                                <tr><th>Created At</th><td><?= showval($tp['created_at']) ?></td></tr>
                            </table>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No third party details available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
                    }
                } else {
                    echo '<div class="alert alert-warning text-center">No records found matching your search.</div>';
                }
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        ?>
      </div>
    </div>
<script>
function refreshSnmpStatusUp(ip, port, cellId, btn) {
    var cell = document.getElementById(cellId);
    if (!cell) return;
    cell.textContent = 'Checking...';
    if(btn) btn.disabled = true;
    fetch('snmp_port_status_up.php?ip=' + encodeURIComponent(ip) +
          '&port=' + encodeURIComponent(port))
    .then(r => r.json())
    .then(data => {
        cell.textContent = data.result?.snmp_status || data.message || "NA";
        if(btn) btn.disabled = false;
    })
    .catch(() => {
        cell.textContent = "Error";
        if(btn) btn.disabled = false;
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>