<?php
session_name('oss_portal');
session_start();
include 'includes/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "Access denied.";
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $new_password = $_POST['new_password'];

    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->execute([$hashed, $username]);

    $message = "<div class='alert alert-success'>Password for <strong>$username</strong> has been reset.</div>";
}

// Get all users
$users = $pdo->query("SELECT username FROM users")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset User Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2>Reset User Password</h2>
    <?= $message ?>
    <form method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Select User</label>
            <select name="username" class="form-select" required>
                <option value="" disabled selected>Select a user</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= htmlspecialchars($user['username']) ?>"><?= htmlspecialchars($user['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <input type="text" name="new_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-warning">Reset Password</button>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </form>
</body>
</html>
