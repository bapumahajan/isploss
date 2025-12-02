<?php
// import_customers_circuits.php
session_name('oss_portal');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_roles(['admin', 'manager']);

function convertToDate($inputDate) {
    $inputDate = trim($inputDate);
    if (empty($inputDate) || strtolower($inputDate) === 'na') return null;

    $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y-M-d'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $inputDate);
        if ($date !== false) return $date->format('Y-m-d');
    }

    $ts = strtotime($inputDate);
    return $ts !== false ? date('Y-m-d', $ts) : null;
}

$errors = [];
$successes = [];
$generalError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $generalError = 'File upload failed. Please try again.';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) {
            $generalError = 'Cannot open uploaded file.';
        } else {
            $headers = fgetcsv($handle);
            $expectedHeaders = [
                'circuit_id', 'organization_name', 'customer_address', 'City', 'contact_person_name',
                'product_type', 'link_type', 'pop_name', 'pop_ip', 'switch_name', 'switch_ip',
                'switch_port', 'bandwidth', 'circuit_status', 'vlan', 'installation_date',
                'wan_ip', 'wan_gateway', 'dns1', 'dns2', 'auth_type', 'PPPoE_auth_username',
                'PPPoE_auth_password', 'cacti_url', 'cacti_username', 'cacti_password',
                'contact_numbers', 'ce_email_ids', 'ip_addresses'
            ];

            if (array_map('strtolower', $headers) !== array_map('strtolower', $expectedHeaders)) {
                $generalError = 'CSV headers do not match expected format.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmtCustomer = $pdo->prepare("
                        INSERT INTO customer_basic_information (circuit_id, organization_name, customer_address, City, contact_person_name)
                        VALUES (:circuit_id, :organization_name, :customer_address, :City, :contact_person_name)
                        ON DUPLICATE KEY UPDATE
                            organization_name = VALUES(organization_name),
                            customer_address = VALUES(customer_address),
                            City = VALUES(City),
                            contact_person_name = VALUES(contact_person_name)
                    ");

                    $stmtNetwork = $pdo->prepare("
                        INSERT INTO network_details (
                            circuit_id, product_type, link_type, pop_name, pop_ip, switch_name, switch_ip,
                            switch_port, bandwidth, circuit_status, vlan
                        ) VALUES (
                            :circuit_id, :product_type, :link_type, :pop_name, :pop_ip, :switch_name, :switch_ip,
                            :switch_port, :bandwidth, :circuit_status, :vlan
                        )
                        ON DUPLICATE KEY UPDATE
                            product_type = VALUES(product_type),
                            link_type = VALUES(link_type),
                            pop_name = VALUES(pop_name),
                            pop_ip = VALUES(pop_ip),
                            switch_name = VALUES(switch_name),
                            switch_ip = VALUES(switch_ip),
                            switch_port = VALUES(switch_port),
                            bandwidth = VALUES(bandwidth),
                            circuit_status = VALUES(circuit_status),
                            vlan = VALUES(vlan)
                    ");

                    $stmtCircuitNet = $pdo->prepare("
                        INSERT INTO circuit_network_details (
                            circuit_id, installation_date, wan_ip, wan_gateway, dns1, dns2, auth_type,
                            PPPoE_auth_username, PPPoE_auth_password, cacti_url, cacti_username, cacti_password
                        ) VALUES (
                            :circuit_id, :installation_date, :wan_ip, :wan_gateway, :dns1, :dns2, :auth_type,
                            :PPPoE_auth_username, :PPPoE_auth_password, :cacti_url, :cacti_username, :cacti_password
                        )
                        ON DUPLICATE KEY UPDATE
                            installation_date = VALUES(installation_date),
                            wan_ip = VALUES(wan_ip),
                            wan_gateway = VALUES(wan_gateway),
                            dns1 = VALUES(dns1),
                            dns2 = VALUES(dns2),
                            auth_type = VALUES(auth_type),
                            PPPoE_auth_username = VALUES(PPPoE_auth_username),
                            PPPoE_auth_password = VALUES(PPPoE_auth_password),
                            cacti_url = VALUES(cacti_url),
                            cacti_username = VALUES(cacti_username),
                            cacti_password = VALUES(cacti_password)
                    ");

                    $stmtDeleteContacts = $pdo->prepare("DELETE FROM customer_contacts WHERE circuit_id = :circuit_id");
                    $stmtInsertContact = $pdo->prepare("INSERT INTO customer_contacts (circuit_id, contact_number) VALUES (:circuit_id, :contact_number)");

                    $stmtDeleteEmails = $pdo->prepare("DELETE FROM customer_emails WHERE circuit_id = :circuit_id");
                    $stmtInsertEmail = $pdo->prepare("INSERT INTO customer_emails (circuit_id, ce_email_id) VALUES (:circuit_id, :ce_email_id)");

                    $stmtDeleteIPs = $pdo->prepare("DELETE FROM circuit_ips WHERE circuit_id = :circuit_id");
                    $stmtInsertIP = $pdo->prepare("INSERT INTO circuit_ips (circuit_id, ip_address) VALUES (:circuit_id, :ip_address)");

                    $rowCount = 0;

                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) !== count($expectedHeaders)) {
                            $errors[] = "Row " . ($rowCount + 2) . ": Invalid column count.";
                            $rowCount++;
                            continue;
                        }

                        $data = array_combine($expectedHeaders, $row);
                        $data['installation_date'] = convertToDate($data['installation_date']);

                        foreach ($data as $key => &$value) {
                            if ($key !== 'circuit_id' && (strtoupper($value) === 'NA' || trim($value) === '')) {
                                $value = null;
                            }
                        }

                        try {
                            $stmtCustomer->execute([
                                ':circuit_id' => $data['circuit_id'],
                                ':organization_name' => $data['organization_name'],
                                ':customer_address' => $data['customer_address'],
                                ':City' => $data['City'],
                                ':contact_person_name' => $data['contact_person_name'],
                            ]);

                            $stmtNetwork->execute([
                                ':circuit_id' => $data['circuit_id'],
                                ':product_type' => $data['product_type'],
                                ':link_type' => $data['link_type'],
                                ':pop_name' => $data['pop_name'],
                                ':pop_ip' => $data['pop_ip'],
                                ':switch_name' => $data['switch_name'],
                                ':switch_ip' => $data['switch_ip'],
                                ':switch_port' => $data['switch_port'],
                                ':bandwidth' => $data['bandwidth'],
                                ':circuit_status' => $data['circuit_status'],
                                ':vlan' => $data['vlan'],
                            ]);

                            $stmtCircuitNet->execute([
                                ':circuit_id' => $data['circuit_id'],
                                ':installation_date' => $data['installation_date'],
                                ':wan_ip' => $data['wan_ip'],
                                ':wan_gateway' => $data['wan_gateway'],
                                ':dns1' => $data['dns1'],
                                ':dns2' => $data['dns2'],
                                ':auth_type' => $data['auth_type'],
                                ':PPPoE_auth_username' => $data['PPPoE_auth_username'],
                                ':PPPoE_auth_password' => $data['PPPoE_auth_password'],
                                ':cacti_url' => $data['cacti_url'],
                                ':cacti_username' => $data['cacti_username'],
                                ':cacti_password' => $data['cacti_password'],
                            ]);

                            $stmtDeleteContacts->execute([':circuit_id' => $data['circuit_id']]);
                            foreach (explode(',', $data['contact_numbers']) as $contact) {
                                $contact = trim($contact);
                                if ($contact) $stmtInsertContact->execute([':circuit_id' => $data['circuit_id'], ':contact_number' => $contact]);
                            }

                            $stmtDeleteEmails->execute([':circuit_id' => $data['circuit_id']]);
                            foreach (explode(',', $data['ce_email_ids']) as $email) {
                                $email = trim($email);
                                if ($email) $stmtInsertEmail->execute([':circuit_id' => $data['circuit_id'], ':ce_email_id' => $email]);
                            }

                            $stmtDeleteIPs->execute([':circuit_id' => $data['circuit_id']]);
                            foreach (explode(',', $data['ip_addresses']) as $ip) {
                                $ip = trim($ip);
                                if ($ip) $stmtInsertIP->execute([':circuit_id' => $data['circuit_id'], ':ip_address' => $ip]);
                            }

                            $successes[] = "Row " . ($rowCount + 2) . " (Circuit ID: {$data['circuit_id']}) imported successfully.";
                        } catch (PDOException $e) {
                            $errors[] = "Row " . ($rowCount + 2) . " (Circuit ID: {$data['circuit_id']}): DB error - " . $e->getMessage();
                        }

                        $rowCount++;
                    }

                    $pdo->commit();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $generalError = 'Database transaction failed: ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<!-- HTML UI -->
<!DOCTYPE html>
<html>
<head>
    <title>Import Customers & Circuits</title>
</head>
<body>
    <h2>Import Customer Circuit CSV</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="csv_file" required>
        <button type="submit">Upload & Import</button>
    </form>

    <?php if ($generalError): ?>
        <p style="color:red;"><strong>Error:</strong> <?= htmlspecialchars($generalError) ?></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <h4>Errors</h4>
        <ul style="color:red;">
            <?php foreach ($errors as $error) echo "<li>" . htmlspecialchars($error) . "</li>"; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($successes)): ?>
        <h4>Success</h4>
        <ul style="color:green;">
            <?php foreach ($successes as $msg) echo "<li>" . htmlspecialchars($msg) . "</li>"; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
