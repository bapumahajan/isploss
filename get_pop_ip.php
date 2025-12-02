<?php
include 'includes/db.php';

$pop_name = $_GET['pop_name'] ?? '';

if ($pop_name) {
    $sql = "SELECT pop_ip FROM network_details WHERE pop_name = :pop_name LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':pop_name', $pop_name, PDO::PARAM_STR);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(['pop_ip' => $result['pop_ip']]);
    } else {
        echo json_encode(['pop_ip' => '']);
    }
} else {
    echo json_encode(['pop_ip' => '']);
}
