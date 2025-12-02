
<?php
// File: includes/db.php

$host = 'localhost';
$db   = 'customer_management';
$user = 'root';
$pass = 'Bapu@1982';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\PDOException $e) {
    die('Database Connection failed: ' . $e->getMessage());
}
