<?php
$host = 'localhost';
$db   = 'customer_management';
$user = 'device_database_user';
$pass = 'device_database_password';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
?>