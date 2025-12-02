<?php
session_name('oss_portal');
session_start();
require 'db.php';

// Ensure user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$message = '';

// Enable errors for debugging (optional; remove on production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate input
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = "All fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New passwords do not match!";
    } else {
        // Fetch current hashed password
        $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($hashedPassword);

        if ($stmt->fetch()) {
            $stmt->close();

            // Verify current password
            if (password_verify($currentPassword, $hashedPassword)) {
                // Hash new password
                $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update password
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
                $updateStmt->bind_param("ss", $newHashedPassword, $username);
                $updateStmt->execute();
                $updateStmt->close();

                // Logout user
                session_unset();
                session_destroy();

                // Redirect to login page with success message
                header('Location: login.php?message=Password+changed+successfully+please+login+again');
                exit();
            } else {
                $message = "Current password is incorrect.";
            }
        } else {
            $stmt->close();
            $message = "User not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password | OSS Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f7fa;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .change-password-card {
            max-width: 400px;
            width: 100%;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            background: white;
            padding: 30px;
        }
        .change-password-title {
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>
<div class="change-password-card">
    <div class="change-password-title">Change Password</div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="current_password" class="form-label">Current Password</label>
            <input type="password" name="current_password" id="current_password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <input type="password" name="new_password" id="new_password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Change Password</button>
    </form>
</div>
</body>
</html>
