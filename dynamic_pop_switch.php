<?php
session_start();
include 'db.php';

// Enable debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Fetch network inventory data
$inventory_result = $conn->query("SELECT * FROM network_inventory");
if (!$inventory_result) {
    die("Error fetching network inventory: " . $conn->error);
}

// Prepare data for JavaScript
$inventory_data = [];
while ($row = $inventory_result->fetch_assoc()) {
    $inventory_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Network Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        // Pass PHP data to JavaScript
        const inventoryData = <?php echo json_encode($inventory_data); ?>;

        function updatePopIp() {
            const popName = document.getElementById("pop_name").value;
            const popIpField = document.getElementById("pop_ip");

            // Find the corresponding POP IP
            const selectedPop = inventoryData.find(item => item.pop_name === popName);
            popIpField.value = selectedPop ? selectedPop.pop_ip : ""; // Set the IP or empty if not found
        }

        function updateSwitchIp() {
            const switchName = document.getElementById("switch_name").value;
            const switchIpField = document.getElementById("switch_ip");

            // Find the corresponding Switch IP
            const selectedSwitch = inventoryData.find(item => item.switch_name === switchName);
            switchIpField.value = selectedSwitch ? selectedSwitch.switch_ip : ""; // Set the IP or empty if not found
        }
    </script>
</head>
<body>
<div class="container mt-5">
    <h1>Add Network Details</h1>

    <form method="POST">
        <div class="mb-3">
            <label for="pop_name" class="form-label">POP Name</label>
            <select class="form-control" id="pop_name" name="pop_name" onchange="updatePopIp()" required>
                <option value="" disabled selected>Select POP Name</option>
                <?php foreach ($inventory_data as $row) { ?>
                    <option value="<?php echo htmlspecialchars($row['pop_name']); ?>">
                        <?php echo htmlspecialchars($row['pop_name']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="pop_ip" class="form-label">POP IP</label>
            <input type="text" class="form-control" id="pop_ip" name="pop_ip" readonly>
        </div>
        <div class="mb-3">
            <label for="switch_name" class="form-label">Switch Name</label>
            <select class="form-control" id="switch_name" name="switch_name" onchange="updateSwitchIp()" required>
                <option value="" disabled selected>Select Switch Name</option>
                <?php foreach ($inventory_data as $row) { ?>
                    <option value="<?php echo htmlspecialchars($row['switch_name']); ?>">
                        <?php echo htmlspecialchars($row['switch_name']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="switch_ip" class="form-label">Switch IP</label>
            <input type="text" class="form-control" id="switch_ip" name="switch_ip" readonly>
        </div>
        <div class="mb-3">
            <label for="switch_port" class="form-label">Switch Port</label>
            <input type="text" class="form-control" name="switch_port" required>
        </div>
        <div class="mb-3">
            <label for="bandwidth" class="form-label">Bandwidth</label>
            <input type="text" class="form-control" name="bandwidth" required>
        </div>
        <div class="mb-3">
            <label for="ckt_status" class="form-label">Circuit Status</label>
            <select class="form-control" name="ckt_status" required>
                <option value="Active">Active</option>
                <option value="Terminated">Terminated</option>
                <option value="Suspended">Suspended</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>
</div>
</body>
</html>