<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Test reached.<br>";
if (file_exists('includes/db.php')) {
    require_once 'includes/db.php';
    echo "DB file included.<br>";
} else {
    die("includes/db.php is missing.");
}
?>