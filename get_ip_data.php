<?php
// Include the database connection
include 'includes/db.php';

function getPopIp($popName) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT pop_ip FROM network_details WHERE pop_name = :pop_name LIMIT 1");
    $stmt->execute(['pop_name' => $popName]);
    return $stmt->fetchColumn();
}

function getSwitchIp($switchName) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT switch_ip FROM network_details WHERE switch_name = :switch_name LIMIT 1");
    $stmt->execute(['switch_name' => $switchName]);
    return $stmt->fetchColumn();
}

// Check if the request has valid parameters
$type = $_GET['type'] ?? '';
$name = $_GET['name'] ?? '';

$response = [];

if ($type && $name) {
    if ($type == 'pop') {
        $response['pop_ip'] = getPopIp($name);
    } elseif ($type == 'switch') {
        $response['switch_ip'] = getSwitchIp($name);
    }
}

echo json_encode($response);
?>
