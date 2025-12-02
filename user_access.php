<?php
include 'includes/db.php';

$selected_user = $_POST['username'] ?? '';
$php_files = glob("*.php");
$message = '';

if (isset($_POST['update_access'])) {
    $username = $_POST['username'];
    $access_files = $_POST['access_files'] ?? [];

    // Clear existing access
    $stmt = $pdo->prepare("DELETE FROM user_module_access WHERE username = ?");
    $stmt->execute([$username]);

    // Insert new access
    foreach ($access_files as $file) {
        $stmt = $pdo->prepare("INSERT INTO user_module_access (username, module_name) VALUES (?, ?)");
        $stmt->execute([$username, $file]);
    }

    $message = "Access updated!";
}

// Get all users
$users = $pdo->query("SELECT username FROM users")->fetchAll(PDO::FETCH_COLUMN);

// Get current access
$current_access = [];
if ($selected_user) {
    $stmt = $pdo->prepare("SELECT module_name FROM user_module_access WHERE username = ?");
    $stmt->execute([$selected_user]);
    $current_access = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Module Access</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
    <div class="container">
        <h2>User Module Access Control</h2>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Select User:</label>
                <select name="username" class="form-select" required onchange="this.form.submit()">
                    <option value="">-- Select User --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user ?>" <?= ($user === $selected_user) ? 'selected' : '' ?>><?= $user ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($selected_user): ?>
                <h5>Assign Access to Modules (.php pages)</h5>
                <div class="mb-3">
                    <?php foreach ($php_files as $file): ?>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="access_files[]" value="<?= $file ?>" <?= in_array($file, $current_access) ? 'checked' : '' ?>>
                            <label class="form-check-label"><?= $file ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" name="update_access" class="btn btn-primary">Update Access</button>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
