<?php
session_name('oss_portal');
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id === 0) {
    header("Location: activate_users.php");
    exit;
}

// CSRF protection: Generate token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: activate_users.php?error=User not found.");
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request token.";
    } else {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $department = trim($_POST['department']);
        $role = $_POST['role'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif (!preg_match("/^\+?[0-9]{10,15}$/", $phone)) {
            $error = "Invalid phone number format.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, department = ?, role = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $full_name, $email, $phone, $department, $role, $is_active, $id);
            if ($stmt->execute()) {
                unset($_SESSION['csrf_token']);
                header("Location: activate_users.php?message=User updated successfully");
                exit;
            } else {
                $error = "Error updating user: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .edit-card {
            max-width: 650px;
            margin: 50px auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 1rem;
        }
        .form-section {
            padding: 2rem;
        }
        .form-header {
            background-color: #0d6efd;
            color: white;
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
            padding: 1rem 2rem;
        }
        .form-header h4 {
            margin: 0;
        }
    </style>
</head>
<body>
<div class="card edit-card">
    <div class="form-header d-flex justify-content-between align-items-center">
        <h4><i class="bi bi-person-lines-fill me-2"></i>Edit User</h4>
        <a href="dashboard.php" class="btn btn-light btn-sm">← Dashboard</a>
    </div>
    <div class="form-section">

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="mb-3">
                <label class="form-label">Username (readonly)</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" readonly>
            </div>

            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                <div class="form-text">Format: +919876543210</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Department</label>
                <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($user['department']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="user" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="viewer" <?= $user['role'] === 'Viewer' ? 'selected' : '' ?>>Viewer</option>
					<option value="viewer" <?= $user['role'] === 'finance' ? 'selected' : '' ?>>finance</option>
					<option value="viewer" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                </select>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= $user['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Active</label>
            </div>

            <div class="d-flex justify-content-between">
                <a href="activate_users.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
