<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'db1.php'; // Your PDO connection

echo "<h2>Network Details CSV Upload</h2>";

$errorRows = [];
$errorFilename = 'upload_errors_' . time() . '.csv';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, 'r')) !== false) {
        $rowNumber = 0;

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $rowNumber++;

            // Skip header
            if ($rowNumber == 1 && $data[0] == 'circuit_id') {
                continue;
            }

            if (count($data) !== 11) {
                echo "<strong>❌ Invalid column count at Row $rowNumber:</strong><br>";
                echo "<code>" . implode(" | ", $data) . "</code><br><br>";
                $errorRows[] = array_merge($data, ["Error: Invalid column count"]);
                continue;
            }

            list($circuit_id, $product_type, $link_type, $pop_name, $pop_ip, $switch_name, $switch_ip, $switch_port, $bandwidth, $circuit_status, $vlan) = $data;

            // Validate ENUM values
            $valid_product_types = ['ILL', 'EBB', 'Lease-Line'];
            $valid_link_types = ['Fiber','RF','Dual Fiber','GAZON','Dual Fiber + RF','Ethernet','Fiber + RF','FTTH','FTTH + RF','NA'];
            $valid_circuit_statuses = ['Active','Terminated','Suspended'];

            if (!in_array($product_type, $valid_product_types)) {
                echo "<strong>❌ Invalid product_type at Row $rowNumber:</strong> '$product_type'<br>";
                $errorRows[] = array_merge($data, ["Error: Invalid product_type"]);
                continue;
            }

            if (!in_array($link_type, $valid_link_types)) {
                echo "<strong>❌ Invalid link_type at Row $rowNumber:</strong> '$link_type'<br>";
                $errorRows[] = array_merge($data, ["Error: Invalid link_type"]);
                continue;
            }

            if (!in_array($circuit_status, $valid_circuit_statuses)) {
                echo "<strong>❌ Invalid circuit_status at Row $rowNumber:</strong> '$circuit_status'<br>";
                $errorRows[] = array_merge($data, ["Error: Invalid circuit_status"]);
                continue;
            }

            // Check for duplicate circuit_id
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM network_details WHERE circuit_id = ?");
            $checkStmt->execute([$circuit_id]);
            $count = $checkStmt->fetchColumn();

            if ($count > 0) {
                echo "<strong>❌ Duplicate circuit_id at Row $rowNumber:</strong> '$circuit_id'<br>";
                $errorRows[] = array_merge($data, ["Error: Duplicate circuit_id"]);
                continue;
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO network_details 
                    (circuit_id, product_type, link_type, pop_name, pop_ip, switch_name, switch_ip, switch_port, bandwidth, circuit_status, vlan) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $circuit_id, $product_type, $link_type, $pop_name, $pop_ip,
                    $switch_name, $switch_ip, $switch_port, $bandwidth,
                    $circuit_status, $vlan
                ]);

                echo "✅ Row $rowNumber inserted.<br>";

            } catch (PDOException $e) {
                echo "<strong>❌ DB Error on Row $rowNumber:</strong><br>";
                echo "📛 <strong>PDO Exception:</strong> " . $e->getMessage() . "<br><br>";
                $errorRows[] = array_merge($data, ["Error: " . $e->getMessage()]);
            }
        }

        fclose($handle);

        // Write errors to CSV if any
        if (!empty($errorRows)) {
            if (!is_dir('uploads')) {
                mkdir('uploads', 0755, true);
            }

            $errorFilePath = __DIR__ . '/uploads/' . $errorFilename;
            $fp = fopen($errorFilePath, 'w');

            // Add header with "Error Message"
            fputcsv($fp, ['circuit_id', 'product_type', 'link_type', 'pop_name', 'pop_ip', 'switch_name', 'switch_ip', 'switch_port', 'bandwidth', 'circuit_status', 'vlan', 'error_message']);
            foreach ($errorRows as $row) {
                fputcsv($fp, $row);
            }

            fclose($fp);

            echo "<br><strong>⚠️ Upload completed with some errors.</strong><br>";
            echo "<a href='uploads/$errorFilename' download>📥 Download Error Report</a><br>";
        } else {
            echo "<br><strong>✅ Upload completed successfully with no errors.</strong>";
        }
    } else {
        echo "❌ Failed to open the uploaded file.";
    }
} else {
    // Show upload form
    echo '<form method="POST" enctype="multipart/form-data">
        <label>Select CSV File: <input type="file" name="csv_file" required></label>
        <button type="submit">Upload</button>
    </form>';
}
?>
