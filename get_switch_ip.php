<?php
include 'includes/db.php';

$switch_name = $_GET['switch_name'] ?? '';

if ($switch_name) {
    // Fetch switch IP based on switch name
    $sql = "SELECT switch_ip FROM network_details WHERE switch_name = :switch_name LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':switch_name', $switch_name, PDO::PARAM_STR);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(['switch_ip' => $result['switch_ip']]);
    } else {
        echo json_encode(['switch_ip' => '']);
    }
} else {
    echo json_encode(['switch_ip' => '']);
}
?>
