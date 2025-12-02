<?php
// File: includes/db.php

$host = 'localhost';
$db   = 'customer_management';
$user = 'root';
$pass = 'Bapu@1982';
$charset = 'utf8mb4';

// Create MySQLi connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die('Database Connection failed: ' . $conn->connect_error);
}
?>
