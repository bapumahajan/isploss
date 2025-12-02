<?php
session_start();
include 'includes/db.php';

// Check if the user is logged in (user_id exists in session)
if (!isset($_SESSION['user_id'])) {
    echo "User is not logged in.";
    exit;
}

// Function to fetch user roles using PDO
function getUserRoles($pdo, $user_id) {
    // SQL query to fetch roles based on user_id
    $sql = "
        SELECT r.role_name
        FROM roles r
        JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = :user_id
    ";

    // Prepare statement
    $stmt = $pdo->prepare($sql);

    // Execute the statement
    $stmt->execute(['user_id' => $user_id]);

    // Check if any results are returned
    if ($stmt->rowCount() == 0) {
        return []; // No roles found for this user
    }

    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Extract role names into an array
    $roleNames = array_map(function ($role) {
        return $role['role_name'];
    }, $roles);

    return $roleNames;
}

// Fetch roles for the logged-in user
$roles = getUserRoles($pdo, $_SESSION['user_id']);

// Display roles or message if no roles are assigned
if (empty($roles)) {
    echo 'No roles assigned to this user.';
} else {
    echo 'Roles: ' . implode(', ', $roles); // Output roles as a comma-separated string
}
?>
