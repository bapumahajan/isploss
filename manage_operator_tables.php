<?php
require_once 'config.php';

$message = '';

// Delete table logic
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $table_id = intval($_GET['delete']);

    // Get table name
    $stmt = $pdo->prepare("SELECT table_name FROM operator_tables WHERE id = ?");
    $stmt->execute([$table_id]);
    $row = $stmt->fetch();
    if ($row) {
        $table_name = $row['table_name'];

        // Drop actual table
        $pdo->exec("DROP TABLE IF EXISTS `$table_name`");

        // Delete metadata
        $pdo->prepare("DELETE FROM operator_fields WHERE table_id = ?")->execute([$table_id]);
        $pdo->prepare("DELETE FROM operator_tables WHERE id = ?")->execute([$table_id]);

        $message = "Table deleted!";
    }
}

// List tables
$tables = $pdo->query("SELECT t.id, t.table_name, o.name as operator_name 
    FROM operator_tables t 
    JOIN operators o ON t.operator_id = o.id 
    ORDER BY o.name, t.table_name")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Operator Tables</title>
</head>
<body>
<h2>Manage Operator Tables</h2>
<?php if ($message) echo "<p style='color:green;'>$message</p>"; ?>
<table border="1" cellpadding="5">
    <tr>
        <th>Operator</th>
        <th>Table Name</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($tables as $t): ?>
        <tr>
            <td><?= htmlspecialchars($t['operator_name']) ?></td>
            <td><?= htmlspecialchars($t['table_name']) ?></td>
            <td>
                <a href="add_operator_table_fields.php?table_id=<?= $t['id'] ?>">Add Field</a> |
                <a href="?delete=<?= $t['id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
</body>
</html>