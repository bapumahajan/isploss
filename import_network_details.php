<?php
require_once 'includes/db.php';

if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
    
    // Optional: skip the header
    $header = fgetcsv($file);

    $pdo->beginTransaction();
    $insertStmt = $pdo->prepare(
        "INSERT INTO network_details
        (circuit_id, product_type, pop_name, switch_ip, switch_port, bandwidth, circuit_status, pop_ip, switch_name, vlan)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    while (($row = fgetcsv($file)) !== false) {
        // Optionally: validate/sanitize $row here
        $insertStmt->execute($row);
    }

    $pdo->commit();
    fclose($file);

    echo "Import successful!";
    // Redirect or show a message
} else {
    echo "File upload failed!";
}
?>