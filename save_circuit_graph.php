<?php
session_name('oss_portal');
session_start();
// save_circuit_graph.php
// Handle POST to insert or update cacti_graphs record

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$circuit_id = $_POST['circuit_id'] ?? null;
$local_graph_id = $_POST['local_graph_id'] ?? null;
$description = $_POST['description'] ?? '';

if (!$circuit_id || !$local_graph_id) {
    die("Required fields missing");
}

$pdo = new PDO("mysql:host=localhost;dbname=yourdb;charset=utf8mb4", "user", "pass");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Use UPSERT - insert if not exists, else update
$sql = "INSERT INTO cacti_graphs (circuit_id, local_graph_id, description) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
          local_graph_id = VALUES(local_graph_id),
          description = VALUES(description)";

$stmt = $pdo->prepare($sql);
$stmt->execute([$circuit_id, $local_graph_id, $description]);

// Redirect back to view page
header("Location: view_circuit_graph.php?circuit_id=" . urlencode($circuit_id));
exit;
