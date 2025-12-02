<?php
// get_ips.php

include 'includes/db.php';

// Get the POP name or Switch name from the request
$pop_name = $_GET['pop_name'] ?? null;
$switch_name = $_GET['switch_name'] ?? null;

$response = ['status' => 'error', 'message' => ''];

if ($pop_name) {
    // Fetch POP IP based on pop_name
    $sql = "SELECT pop_ip FROM network_details WHERE pop_name = :pop_name LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':pop_name', $pop_name);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $response['status'] = 'success';
        $response['pop_ip'] = $data['pop_ip'];
    } else {
        $response['message'] = 'POP not found.';
    }
} elseif ($switch_name) {
    // Fetch Switch IP based on switch_name
    $sql = "SELECT switch_ip FROM network_details WHERE switch_name = :switch_name LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':switch_name', $switch_name);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $response['status'] = 'success';
        $response['switch_ip'] = $data['switch_ip'];
    } else {
        $response['message'] = 'Switch not found.';
    }
}

echo json_encode($response);
?>
