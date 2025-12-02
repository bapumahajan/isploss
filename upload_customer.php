<?php
// Include DB connection
require_once 'includes/db.php';

// Enable debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$success = '';
$error = '';
$uploaded_records = [];
$failed_records = [];
$duplicates = 0;
$inserted = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (empty($file)) {
        $error = "No file selected.";
    } else {
        if ($_FILES['csv_file']['type'] !== 'text/csv' && pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION) !== 'csv') {
            $error = "Please upload a valid CSV file.";
        } else {
            if (($handle = fopen($file, 'r')) !== false) {
                $header = fgetcsv($handle, 1000, ',', '"', '\\'); // Read the header row

                while (($data = fgetcsv($handle, 1000, ',', '"', '\\')) !== false) {
                    // Map CSV fields to variables
                    $circuit_id = $data[0];
                    $organization_name = $data[1];
                    $customer_address = $data[2];
                    $city = $data[3];
                    $contact_person_name = $data[4];
                    $contact_number = $data[5];
                    $ce_email_id = $data[6];
                    $product_type = $data[7];
                    $pop_name = $data[8];
                    $pop_ip = $data[9];
                    $switch_name = $data[10];
                    $switch_ip = $data[11];
                    $switch_port = $data[12];
                    $bandwidth = $data[13];
                    $circuit_status = $data[14];
                    $vlan = $data[15];
                    $installation_date = $data[16];
                    $wan_ip = $data[17];
                    $wan_gateway = $data[18];
                    $dns1 = $data[19];
                    $dns2 = $data[20];
                    $auth_type = $data[21];
                    $PPPoE_auth_username = $data[22];
                    $PPPoE_auth_password = $data[23];
                    $cacti_url = $data[24];
                    $cacti_username = $data[25];
                    $cacti_password = $data[26];

                    // Validate required fields
                    if (empty($circuit_id) || empty($organization_name)) {
                        $failed_records[] = ["circuit_id" => $circuit_id ?? "(empty)", "status" => "Missing required fields"];
                        continue;
                    }

                    // Validate installation_date
                    if ($installation_date === 'NA' || empty($installation_date)) {
                        $installation_date = NULL; // Set to NULL if the value is 'NA' or empty
                    } else {
                        // Validate if it's a proper date
                        $date = DateTime::createFromFormat('Y-m-d', $installation_date);
                        if ($date && $date->format('Y-m-d') === $installation_date) {
                            $installation_date = $date->format('Y-m-d'); // Keep the valid date
                        } else {
                            $installation_date = NULL; // Set to NULL if the date is invalid
                        }
                    }

                    // Validate product_type
                    $valid_product_types = ['ILL', 'EBB', 'Point-To-Point']; // Allowed values for the ENUM
                    if (!in_array($product_type, $valid_product_types)) {
                        $product_type = 'ILL'; // Default value if the provided value is invalid
                    }

                    // Validate auth_type
                    $valid_auth_types = ['Static', 'PPPoE']; // Allowed values for the ENUM
                    if (!in_array($auth_type, $valid_auth_types) || empty($auth_type)) {
                        $auth_type = 'Static'; // Default value if the provided value is invalid or empty
                    }

                    // Check for duplicate circuit_id
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customer_basic_information WHERE circuit_id = ?");
                    $stmt->execute([$circuit_id]);
                    $count = $stmt->fetchColumn();

                    if ($count > 0) {
                        // Duplicate found, skip this record
                        $duplicates++;
                        $failed_records[] = ["circuit_id" => $circuit_id, "status" => "Duplicate circuit_id"];
                        continue;
                    }

                    // Insert into `network_details`
                    $stmt = $pdo->prepare("INSERT INTO network_details (
                        circuit_id, product_type, pop_name, pop_ip, switch_name, switch_ip, switch_port, bandwidth, circuit_status, vlan
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if (!$stmt->execute([
                        $circuit_id, $product_type, $pop_name, $pop_ip, $switch_name, $switch_ip,
                        $switch_port, $bandwidth, $circuit_status, $vlan
                    ])) {
                        $failed_records[] = ["circuit_id" => $circuit_id, "status" => "Insert failed in network_details"];
                        continue;
                    }

                    // Insert into `customer_basic_information`
                    $stmt = $pdo->prepare("INSERT INTO customer_basic_information (
                        circuit_id, organization_name, customer_address, City, contact_person_name, contact_number, ce_email_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if (!$stmt->execute([$circuit_id, $organization_name, $customer_address, $city, $contact_person_name, $contact_number, $ce_email_id])) {
                        $failed_records[] = ["circuit_id" => $circuit_id, "status" => "Insert failed in customer_basic_information"];
                        continue;
                    }

                    // Insert into `circuit_network_details`
                    $stmt = $pdo->prepare("INSERT INTO circuit_network_details (
                        circuit_id, installation_date, wan_ip, wan_gateway, dns1, dns2, auth_type, PPPoE_auth_username,
                        PPPoE_auth_password, cacti_url, cacti_username, cacti_password
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if (!$stmt->execute([
                        $circuit_id, $installation_date, $wan_ip, $wan_gateway, $dns1, $dns2,
                        $auth_type, $PPPoE_auth_username, $PPPoE_auth_password, $cacti_url, $cacti_username, $cacti_password
                    ])) {
                        $failed_records[] = ["circuit_id" => $circuit_id, "status" => "Insert failed in circuit_network_details"];
                        continue;
                    }

                    // Insert into `circuit_ips`
                    $stmt = $pdo->prepare("INSERT INTO circuit_ips (circuit_id, ip_address) VALUES (?, ?)");
                    if (!$stmt->execute([$circuit_id, $wan_ip])) {
                        $failed_records[] = ["circuit_id" => $circuit_id, "status" => "Insert failed in circuit_ips"];
                        continue;
                    }

                    // Record successful insert
                    $inserted++;
                    $uploaded_records[] = ["circuit_id" => $circuit_id, "status" => "Uploaded successfully"];
                }
                fclose($handle);

                $success = "$inserted records uploaded successfully. $duplicates duplicates skipped.";
            } else {
                $error = "Unable to open uploaded file.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Customer Circuits CSV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="mb-4">Upload Customer Circuits CSV</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="upload_customer.php" method="post" enctype="multipart/form-data" class="p-4 border rounded shadow bg-white">
            <div class="mb-3">
                <label for="csv_file" class="form-label fw-bold">Choose CSV File</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-success">Upload</button>
            <a href="template.csv" class="btn btn-secondary ms-2">Download CSV Template</a>
        </form>

        <?php if (!empty($uploaded_records) || !empty($failed_records)): ?>
            <h4 class="mt-5">Upload Summary</h4>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Circuit ID</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uploaded_records as $record): ?>
                        <tr class="table-success">
                            <td><?= htmlspecialchars($record['circuit_id']) ?></td>
                            <td><?= htmlspecialchars($record['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php foreach ($failed_records as $record): ?>
                        <tr class="table-danger">
                            <td><?= htmlspecialchars($record['circuit_id']) ?></td>
                            <td><?= htmlspecialchars($record['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>