<?php
//activate_users
session_name('oss_portal');
session_start();
include 'includes/db.php';
include 'access_control.php';

// Ensure only admins can access this page
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Handle activation
if (isset($_GET['activate'])) {
    $id = (int) $_GET['activate'];
    $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: activate_users.php?message=" . urlencode("User activated successfully"));
            exit;
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle deactivation
if (isset($_GET['deactivate'])) {
    $id = (int) $_GET['deactivate'];
    $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: activate_users.php?message=" . urlencode("User deactivated successfully"));
            exit;
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: activate_users.php?message=" . urlencode("User deleted successfully"));
            exit;
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch all users
$stmt = $conn->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Count total users for pagination
$stmt = $conn->prepare("SELECT COUNT(*) FROM users");
$stmt->execute();
$stmt->bind_result($total_users);
$stmt->fetch();
$stmt->close();
$total_pages = ceil($total_users / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activate Users</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7fc;
            font-family: 'Segoe UI', sans-serif;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            margin-top: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #007bff;
            font-weight: 600;
            text-align: center;
            margin-bottom: 20px;
        }
        .back-btn {
            display: block;
            margin-bottom: 20px;
            text-align: right;
        }
        .table thead th {
            background-color: #007bff;
            color: #fff;
            font-size: 14px;
        }
        .table td {
            font-size: 13px;
            vertical-align: middle;
        }
        .btn-group .btn {
            font-size: 12px;
            padding: 4px 10px;
        }
        .alert {
            font-size: 14px;
        }
        .pagination {
            justify-content: center;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="m-0">User Management</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">← Back to Dashboard</a>
    </div>

    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-success text-center">
            <?= htmlspecialchars($_GET['message']) ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
            <tr>
                <th>S.No</th> <!-- Serial Number -->
                <th>Username</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Department</th>
                <th>Created At</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php $serial = 1; ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $serial++ ?></td> <!-- Serial Number -->
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['phone']) ?></td>
                    <td><?= htmlspecialchars($row['department']) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                    <td><?= ucfirst($row['role']) ?></td>
                    <td>
                        <?= $row['is_active'] ? '<span class="text-success">Active</span>' : '<span class="text-danger">Inactive</span>' ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <?php if ($row['is_active'] == 0): ?>
                                <a href="?activate=<?= $row['id'] ?>" class="btn btn-success btn-sm"
                                   aria-label="Activate <?= htmlspecialchars($row['username']) ?>" onclick="return confirm('Activate this user?')">Activate</a>
                            <?php else: ?>
                                <a href="?deactivate=<?= $row['id'] ?>" class="btn btn-warning btn-sm"
                                   aria-label="Deactivate <?= htmlspecialchars($row['username']) ?>" onclick="return confirm('Deactivate this user?')">Deactivate</a>
                            <?php endif; ?>
                            <a href="edit_user.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm" aria-label="Edit <?= htmlspecialchars($row['username']) ?>">Edit</a>
                            <a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm"
                               aria-label="Delete <?= htmlspecialchars($row['username']) ?>" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php if ($result->num_rows === 0): ?>
                <tr><td colspan="10" class="text-center text-muted">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="User pagination">
            <ul class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="activate_users.php?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
