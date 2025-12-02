<?php
session_name('oss_portal');
session_start();
// insert_cacti_graph.php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $circuit_id = intval($_POST['circuit_id']);
    $local_graph_id = intval($_POST['local_graph_id']);
    $description = trim($_POST['description']);

    if ($circuit_id > 0 && $local_graph_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO cacti_graphs (circuit_id, local_graph_id, description) VALUES (?, ?, ?)");
        $stmt->execute([$circuit_id, $local_graph_id, $description]);

        header("Location: view_customer_data.php?msg=Graph added");
        exit;
    } else {
        echo "Invalid input.";
    }
} else {
    echo "Invalid request.";
}
