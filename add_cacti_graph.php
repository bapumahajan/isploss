<?php
session_name('oss_portal');
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php'; // Your PDO DB connection

if (!isset($_GET['circuit_id']) || empty($_GET['circuit_id'])) {
    die("Invalid circuit ID.");
}

$circuit_id = $_GET['circuit_id'];

// Fetch customer info (organization_name for description)
$stmt = $pdo->prepare("SELECT circuit_id, organization_name FROM customer_basic_information WHERE circuit_id = ?");
$stmt->execute([$circuit_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("No circuit found for this ID.");
}

$error = '';
$success = '';
$current_local_graph_id = null;

// Check if there is already a linked graph for this circuit
$stmt2 = $pdo->prepare("SELECT local_graph_id, description FROM cacti_graphs WHERE circuit_id = ?");
$stmt2->execute([$circuit_id]);
$existing = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $current_local_graph_id = $existing['local_graph_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_local_graph_id = $_POST['local_graph_id'];

    if (!is_numeric($new_local_graph_id) || intval($new_local_graph_id) <= 0) {
        $error = "Local Graph ID must be a positive integer.";
    } else {
        try {
            if ($existing) {
                // Update existing record
                $update = $pdo->prepare("UPDATE cacti_graphs SET local_graph_id = ?, description = ? WHERE circuit_id = ?");
                $update->execute([$new_local_graph_id, $data['organization_name'], $circuit_id]);
                $success = "Local Graph ID updated successfully.";
            } else {
                // Insert new record
                $insert = $pdo->prepare("INSERT INTO cacti_graphs (circuit_id, local_graph_id, description) VALUES (?, ?, ?)");
                $insert->execute([$circuit_id, $new_local_graph_id, $data['organization_name']]);
                $success = "Local Graph ID linked successfully.";
            }
            $current_local_graph_id = $new_local_graph_id;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Link/Edit Cacti Graph to Circuit</title>
</head>
<body>
    <h2>Link/Edit Cacti Graph to Circuit</h2>

    <p><strong>Circuit ID:</strong> <?= htmlspecialchars($data['circuit_id']) ?></p>
    <p><strong>Customer (Organization):</strong> <?= htmlspecialchars($data['organization_name']) ?></p>

    <?php if ($success): ?>
        <p style="color:green;"><?= htmlspecialchars($success) ?></p>
    <?php elseif ($error): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" style="margin-top:20px;">
        <label for="local_graph_id">Cacti Local Graph ID:</label>
        <input type="number" name="local_graph_id" id="local_graph_id" required min="1" 
            value="<?= htmlspecialchars($current_local_graph_id ?? '') ?>">
        <button type="submit"><?= $existing ? 'Update Graph ID' : 'Link Graph' ?></button>
    </form>

    <?php if ($current_local_graph_id): ?>
        <form action="http://192.168.201.193/cacti/graph.php" method="get" target="_blank" style="margin-top:15px;">
            <input type="hidden" name="local_graph_id" value="<?= htmlspecialchars($current_local_graph_id) ?>" />
            <button type="submit">View Cacti Graph</button>
        </form>
    <?php endif; ?>
</body>
</html>
