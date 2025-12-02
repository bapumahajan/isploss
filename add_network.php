<?php
session_name('oss_portal');
session_start();

require_once 'includes/auth.php'; // RBAC helper
require_roles(['admin', 'network_manager']); // Only allow these roles

$timeout_duration = 900; // 15 minutes

if (isset($_SESSION['LAST_ACTIVITY']) &&
    (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
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

require_once 'includes/db.php'; // $pdo is defined here (PDO connection)
require_once 'includes/audit.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Force redirect if no circuit_id is provided via GET
if (!isset($_GET['circuit_id']) || empty(trim($_GET['circuit_id']))) {
    header("Location: add_customer.php");
    exit();
}

$circuit_data = [];
$success = $error = "";
$new_record_id = 0;

// Pre-fill and lock circuit_id
$prefill_circuit_id = htmlspecialchars($_GET['circuit_id']);

$product_type = $pop_name = $pop_ip = $switch_name = $switch_ip = $switch_port = $bandwidth = $circuit_status = $vlan = "";
$link_type = $_POST['link_type'] ?? '';

// Load inventory
$pop_stmt = $pdo->query("SELECT pop_name, pop_ip FROM pop_inventory");
$pop_inventory = $pop_stmt->fetchAll(PDO::FETCH_ASSOC);

$switch_stmt = $pdo->query("SELECT switch_name, switch_ip FROM switch_inventory");
$switch_inventory = $switch_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_network'])) {
    $circuit_id     = trim($_POST['circuit_id']);  // from hidden field
    $product_type   = $_POST['product_type'];
    $pop_name       = $_POST['pop_name'];
    $pop_ip         = $_POST['pop_ip'];
    $switch_name    = $_POST['switch_name'];
    $switch_ip      = $_POST['switch_ip'];
    $switch_port    = $_POST['switch_port'];
    $bandwidth      = trim($_POST['bandwidth']);
    $circuit_status = $_POST['circuit_status'];
    $vlan           = $_POST['vlan'];

    if (empty($circuit_id) || !preg_match("/^[a-zA-Z0-9-]+$/", $circuit_id)) {
        $error = "Invalid Circuit ID format.";
    } elseif (!is_numeric($bandwidth) || $bandwidth <= 0) {
        $error = "Bandwidth must be a positive number.";
    } 
	else {
        // POP IP Validation
        $stmt_pop = $pdo->prepare("SELECT pop_ip FROM pop_inventory WHERE pop_name = ?");
        $stmt_pop->execute([$pop_name]);
        $expected_pop_ip = $stmt_pop->fetchColumn();

        if ($pop_ip !== $expected_pop_ip) {
            $error = "POP IP does not match the selected POP name.";
        }

        // Switch IP Validation
        $stmt_switch = $pdo->prepare("SELECT switch_ip FROM switch_inventory WHERE switch_name = ?");
        $stmt_switch->execute([$switch_name]);
        $expected_switch_ip = $stmt_switch->fetchColumn();

        if (!$error && $switch_ip !== $expected_switch_ip) {
            $error = "Switch IP does not match the selected Switch name.";
        }
		$valid_link_types = ['Fiber','Dual Fiber','RF','Dual Fiber + RF','Ethernet','Fiber + RF','FTTH','FTTH + RF'];
		if (!in_array($link_type, $valid_link_types)) {
		$error = "Invalid Link Type selected.";
		}

        // Insert only if no error
        if (!$error) {
            $stmt_check = $pdo->prepare("SELECT * FROM network_details WHERE circuit_id = ?");
            $stmt_check->execute([$circuit_id]);
            $circuit_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($circuit_data) {
                $error = "Circuit ID already exists. Please review or update details.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO network_details (circuit_id, product_type, pop_name, switch_ip, switch_port, bandwidth, circuit_status, pop_ip, switch_name, vlan, link_type)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
				$stmt->execute([
					$circuit_id, $product_type, $pop_name, $switch_ip, $switch_port,
					$bandwidth, $circuit_status, $pop_ip, $switch_name, $vlan, $link_type
				]);

                // Audit log for creation
                $new_network = [
                    'circuit_id'     => $circuit_id,
                    'product_type'   => $product_type,
                    'pop_name'       => $pop_name,
                    'pop_ip'         => $pop_ip,
                    'switch_name'    => $switch_name,
                    'switch_ip'      => $switch_ip,
                    'switch_port'    => $switch_port,
                    'bandwidth'      => $bandwidth,
                    'circuit_status' => $circuit_status,
                    'vlan'           => $vlan,
					'link_type'      => $link_type 
                ];
                log_activity(
                    $pdo,
                    $_SESSION['username'],
                    'insert',
                    'network_details',
                    $circuit_id,
                    "Added: " . json_encode(['old'=>null, 'new'=>$new_network])
                );
                header("Location: add_network.php?success=1&id=" . $pdo->lastInsertId());
                exit;
            }
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = "Network details added successfully.";
    $new_record_id = $_GET['id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Network</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-size: 13px; }
        .form-label { font-size: 12px; }
        .btn { font-size: 13px; }
        .alert { margin-top: 20px; }
        .form-section { background-color: #f8f9fa; padding: 20px; border-radius: 8px; }
    </style>
    <script>
        const popData = <?= json_encode($pop_inventory) ?>;
        const switchData = <?= json_encode($switch_inventory) ?>;

        function updatePopIP() {
            const selected = document.getElementById('pop_name').value;
            const match = popData.find(p => p.pop_name === selected);
            document.getElementById('pop_ip').value = match ? match.pop_ip : '';
        }

        function updateSwitchIP() {
            const selected = document.getElementById('switch_name').value;
            const match = switchData.find(s => s.switch_name === selected);
            document.getElementById('switch_ip').value = match ? match.switch_ip : '';
        }
    </script>
</head>
<body>
<div class="container mt-5">
    <div class="card p-4">
        <h4 class="text-center mb-4">Add or Update Network Details</h4>

        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= $success ?></div>
            <div class="d-flex justify-content-between">
                <a href="view_customer.php?id=<?= $new_record_id ?>" target="_blank" class="btn btn-primary">View Added Details</a>
                <a href="dashboard.php" class="btn btn-secondary">Return to Dashboard</a>
            </div>
            <hr>
        <?php elseif ($error): ?>
            <div class="alert alert-danger">❌ <?= $error ?></div>
        <?php endif; ?>

        <form method="POST" class="form-section">
            <input type="hidden" name="add_network" value="1">

            <div class="mb-3">
                <label class="form-label">Circuit ID</label>
                <input type="text" name="circuit_id" class="form-control" readonly value="<?= $prefill_circuit_id ?>">
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Product Type</label>
                    <select name="product_type" class="form-select" required>
                        <option value="ILL" <?= ($product_type == 'ILL') ? 'selected' : '' ?>>ILL</option>
                        <option value="EBB" <?= ($product_type == 'EBB') ? 'selected' : '' ?>>EBB</option>
                        <option value="Lease-Line" <?= ($product_type == 'Lease-Line') ? 'selected' : '' ?>>Lease-Line</option>
                    </select>
                </div>
            </div>
		    <div class="mb-3">
			<label class="form-label">Link Type</label>
			<select name="link_type" class="form-select" required>
				<option value="">-- Select Link Type --</option>
				<option value="Fiber" <?= ($link_type == 'Fiber') ? 'selected' : '' ?>>Fiber</option>
				<option value="Dual Fiber" <?= ($link_type == 'Dual Fiber') ? 'selected' : '' ?>>Dual Fiber</option>
				<option value="Dual Fiber + RF" <?= ($link_type == 'Dual Fiber + RF') ? 'selected' : '' ?>>Dual Fiber + RF</option>
				<option value="Ethernet" <?= ($link_type == 'Ethernet') ? 'selected' : '' ?>>Ethernet</option>
				<option value="RF" <?= ($link_type == 'RF') ? 'selected' : '' ?>>RF</option>
				<option value="Fiber + RF" <?= ($link_type == 'Fiber + RF') ? 'selected' : '' ?>>Fiber + RF</option>
				<option value="FTTH" <?= ($link_type == 'FTTH') ? 'selected' : '' ?>>FTTH</option>
				<option value="FTTH + RF" <?= ($link_type == 'FTTH + RF') ? 'selected' : '' ?>>FTTH + RF</option>
			</select>
			</div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">POP Name</label>
                    <select name="pop_name" id="pop_name" class="form-select" onchange="updatePopIP()" required>
                        <option value="">-- Select POP --</option>
                        <?php foreach ($pop_inventory as $pop): ?>
                            <option value="<?= htmlspecialchars($pop['pop_name']) ?>" <?= ($pop_name == $pop['pop_name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pop['pop_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">POP IP</label>
                    <input type="text" name="pop_ip" id="pop_ip" class="form-control" readonly value="<?= htmlspecialchars($pop_ip) ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Switch Name</label>
                    <select name="switch_name" id="switch_name" class="form-select" onchange="updateSwitchIP()" required>
                        <option value="">-- Select Switch --</option>
                        <?php foreach ($switch_inventory as $switch): ?>
                            <option value="<?= htmlspecialchars($switch['switch_name']) ?>" <?= ($switch_name == $switch['switch_name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($switch['switch_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Switch IP</label>
                    <input type="text" name="switch_ip" id="switch_ip" class="form-control" readonly value="<?= htmlspecialchars($switch_ip) ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Switch Port</label>
                    <input type="text" name="switch_port" class="form-control" required value="<?= htmlspecialchars($switch_port) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">VLAN</label>
                    <input type="text" name="vlan" class="form-control" required value="<?= htmlspecialchars($vlan) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bandwidth (Mbps)</label>
                    <input type="number" name="bandwidth" class="form-control" required value="<?= htmlspecialchars($bandwidth) ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Circuit Status</label>
                <select name="circuit_status" class="form-select" required>
                    <option value="Active" <?= ($circuit_status == 'Active') ? 'selected' : '' ?>>Active</option>
                    <option value="Terminated" <?= ($circuit_status == 'Terminated') ? 'selected' : '' ?>>Terminated</option>
                    <option value="Suspended" <?= ($circuit_status == 'Suspended') ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-success">💾 Save Network</button>
                <a href="dashboard.php" class="btn btn-secondary ms-2">🔙 Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>