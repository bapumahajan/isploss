<?php
session_start();
include 'db.php';

// Enable debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Fetch network inventory data
$inventory_result = $conn->query("SELECT * FROM network_inventory");
if (!$inventory_result) {
    die("Error fetching network inventory: " . $conn->error);
}

// Debug fetched data
echo "<pre>Fetched Data:<br>";
while ($row = $inventory_result->fetch_assoc()) {
    print_r($row); // Print each row to confirm data is fetched
}
echo "</pre>";

// Reset pointer for reuse
$inventory_result->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Network Details</title>
</head>
<body>
<h1>Debug Network Details</h1>

<form method="POST">
    <label for="pop_name">POP Name:</label>
    <select name="pop_name" id="pop_name">
        <?php
        $inventory_result->data_seek(0); // Reset pointer
        while ($row = $inventory_result->fetch_assoc()) {
            echo "<option value='" . htmlspecialchars($row['pop_name']) . "'>";
            echo htmlspecialchars($row['pop_name']);
            echo "</option>";
        }
        ?>
    </select>
</form>
</body>
</html>