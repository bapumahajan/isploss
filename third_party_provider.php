<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_name('oss_portal');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

require_once __DIR__ . '/includes/db.php'; // PDO connection in $pdo

// Generate CSRF token if missing
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$message = '';
$edit_mode = false;
$edit_id = null;
$edit_name = '';

// CSRF token checker
function check_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle form POST (Add or Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $id = $_POST['id'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!check_csrf_token($token)) {
        $message = "<p style='color:red;'>Invalid CSRF token. Please reload the page and try again.</p>";
    } elseif ($name === '') {
        $message = "<p style='color:red;'>Provider name is required.</p>";
    } else {
        try {
            if ($id) {
                // Edit existing
                $stmt = $pdo->prepare("UPDATE Third_Party_SP SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                $message = "<p style='color:green;'>Provider updated successfully.</p>";
            } else {
                // Add new
                $stmt = $pdo->prepare("INSERT INTO Third_Party_SP (name) VALUES (?)");
                $stmt->execute([$name]);
                $message = "<p style='color:green;'>Provider added successfully.</p>";
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Duplicate entry error code
                $message = "<p style='color:red;'>Provider name already exists.</p>";
            } else {
                $message = "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
}

// Handle Delete action (GET)
if (isset($_GET['delete'], $_GET['csrf_token'])) {
    $delete_id = (int)$_GET['delete'];
    $token = $_GET['csrf_token'];

    if (!check_csrf_token($token)) {
        $message = "<p style='color:red;'>Invalid CSRF token for delete action.</p>";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM Third_Party_SP WHERE id = ?");
            $stmt->execute([$delete_id]);
            $message = "<p style='color:green;'>Provider deleted successfully.</p>";
        } catch (PDOException $e) {
            $message = "<p style='color:red;'>Error deleting provider: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

// Load data for edit if requested (GET)
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM Third_Party_SP WHERE id = ?");
    $stmt->execute([$edit_id]);
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($provider) {
        $edit_mode = true;
        $edit_name = $provider['name'];
    } else {
        $message = "<p style='color:red;'>Provider not found.</p>";
    }
}

// Fetch all providers for display
$providers = $pdo->query("SELECT * FROM Third_Party_SP ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Manage Third Party Service Providers</title>
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f9f9f9;
        padding: 30px;
        color: #333;
        max-width: 800px;
        margin: auto;
    }
    h2, h3 { color: #2c3e50; }
    form {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgb(0 0 0 / 0.1);
        max-width: 500px;
        margin-bottom: 40px;
    }
    label {
        display: block;
        font-weight: 600;
        margin-top: 15px;
        margin-bottom: 6px;
        font-size: 1rem;
    }
    input[type="text"] {
        width: 100%;
        padding: 10px;
        font-size: 1rem;
        border-radius: 4px;
        border: 1px solid #ccc;
        transition: border-color 0.3s ease;
    }
    input[type="text"]:focus {
        border-color: #4CAF50;
        outline: none;
    }
    input[type="submit"], button {
        margin-top: 20px;
        background-color: #27ae60;
        color: white;
        border: none;
        padding: 12px 20px;
        font-size: 1rem;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    input[type="submit"]:hover, button:hover {
        background-color: #219150;
    }
    button.cancel-btn {
        background-color: #95a5a6;
        margin-left: 10px;
    }
    button.cancel-btn:hover {
        background-color: #7f8c8d;
    }
    .message p {
        padding: 10px 15px;
        border-radius: 4px;
        font-weight: 600;
    }
    .message p[style*="color: green"] {
        background-color: #d4edda;
        color: #155724;
    }
    .message p[style*="color: red"] {
        background-color: #f8d7da;
        color: #721c24;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgb(0 0 0 / 0.1);
    }
    th, td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #ddd;
        font-size: 1rem;
    }
    th {
        background-color: #2ecc71;
        color: white;
        font-weight: 600;
    }
    tr:hover {
        background-color: #f1f9f6;
    }
    a.edit-link, a.delete-link {
        text-decoration: none;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 4px;
        transition: background-color 0.3s ease;
    }
    a.edit-link {
        background-color: #3498db;
        color: white;
    }
    a.edit-link:hover {
        background-color: #2980b9;
    }
    a.delete-link {
        background-color: #e74c3c;
        color: white;
        margin-left: 10px;
    }
    a.delete-link:hover {
        background-color: #c0392b;
    }
</style>
<script>
    function confirmDelete(name) {
        return confirm("Are you sure you want to delete provider: " + name + " ?");
    }
</script>
</head>
<body>

<h2><?= $edit_mode ? "Edit Provider" : "Add Third Party Service Provider" ?></h2>

<div class="message">
    <?= $message ?>
</div>

<form method="POST" action="">
    <input type="hidden" name="id" value="<?= htmlspecialchars($edit_id) ?>">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
    <label for="name">Provider Name:</label>
    <input type="text" name="name" id="name" required placeholder="e.g., Airtel, Tata, RailTel" value="<?= htmlspecialchars($edit_name) ?>">
    <input type="submit" value="<?= $edit_mode ? "Update Provider" : "Add Provider" ?>">
    <?php if ($edit_mode): ?>
        <button type="button" class="cancel-btn" onclick="window.location='<?= basename(__FILE__) ?>'">Cancel</button>
    <?php endif; ?>
</form>

<h3>Existing Providers</h3>
<table>
    <thead>
        <tr><th>ID</th><th>Name</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($providers as $provider): ?>
        <tr>
            <td><?= $provider['id'] ?></td>
            <td><?= htmlspecialchars($provider['name']) ?></td>
            <td>
                <a class="edit-link" href="?edit=<?= $provider['id'] ?>">Edit</a>
                <a class="delete-link" href="?delete=<?= $provider['id'] ?>&csrf_token=<?= $csrf_token ?>"
                   onclick="return confirmDelete('<?= addslashes(htmlspecialchars($provider['name'])) ?>')">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
