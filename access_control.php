<?php
include 'db.php'; // Include database connection

// Function to get user access permission for a module
function getUserPermission($user_id, $module_id) {
    global $conn;

    // Get the user's role
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        return false; // User not found
    }

    $role = $user['role'];

    // Get the permission for the given role and module
    $stmt = $conn->prepare("SELECT permission FROM access_control WHERE role = ? AND module_id = ?");
    $stmt->bind_param("si", $role, $module_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $permission = $result->fetch_assoc();

    return $permission ? $permission['permission'] : 'none';
}
?>
