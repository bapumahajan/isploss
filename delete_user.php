<?php
session_start();
include 'db.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id === 0) {
    header("Location: activate_users.php?error=Invalid user ID");
    exit;
}

// Prevent self-deletion
if ($_SESSION['id'] == $id) {
    header("Location: activate_users.php?error=You cannot delete your own account");
    exit;
}

$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    header("Location: activate_users.php?message=User deleted successfully");
} else {
    header("Location: activate_users.php?error=Failed to delete user");
}
$stmt->close();
?>
