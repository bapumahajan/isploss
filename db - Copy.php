<?php
// db.php
$servername = "localhost"; // Update with your database host
$username = "root"; // Update with your DB username
$password = "Bapu@1982"; // Update with your DB password
$dbname = "customer_management"; // Update with your DB name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>