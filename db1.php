<?php
$host = 'localhost';         // or your DB server IP
$dbname = 'customer_management';
$username = 'root';   // replace with your DB username
$password = 'Bapu@1982';   // replace with your DB password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Enable exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Optional: Emulate prepares off for true prepared statements
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}
?>
