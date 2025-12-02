<?php
// File: validate_unique_fields.php
require_once 'includes/db.php';

header('Content-Type: application/json');

$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? '';
$circuit_id = $_POST['circuit_id'] ?? '';

if (!$field || !$value) {
    echo json_encode(['status' => 'ok']);
    exit;
}

try {
    if ($field === 'wan_ip') {
        $stmt = $pdo->prepare("SELECT circuit_id FROM circuit_network_details WHERE wan_ip = ? AND circuit_id != ?");
        $stmt->execute([$value, $circuit_id]);
        $exists = $stmt->fetchColumn();
        if ($exists) {
            echo json_encode(['status' => 'error', 'field' => 'wan_ip', 'message' => "WAN IP already used by Circuit ID: $exists"]);
            exit;
        }
    }

    if ($field === 'pppoe_user') {
        $stmt = $pdo->prepare("SELECT circuit_id FROM circuit_network_details WHERE PPPoE_auth_username = ? AND circuit_id != ?");
        $stmt->execute([$value, $circuit_id]);
        $exists = $stmt->fetchColumn();
        if ($exists) {
            echo json_encode(['status' => 'error', 'field' => 'pppoe_user', 'message' => "PPPoE username already used by Circuit ID: $exists"]);
            exit;
        }
    }

    if ($field === 'additional_ips') {
        $stmt = $pdo->prepare("SELECT circuit_id FROM circuit_ips WHERE ip_address = ? AND circuit_id != ?");
        $stmt->execute([$value, $circuit_id]);
        $exists = $stmt->fetchColumn();
        if ($exists) {
            echo json_encode(['status' => 'error', 'field' => 'additional_ips', 'message' => "IP already used by Circuit ID: $exists"]);
            exit;
        }
    }

    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
?>
