<?php
require_once 'config/database.php';
session_name('oss_portal');
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$table_name = $_GET['table'] ?? '';
$circuit_id = $_GET['circuit_id'] ?? '';
$message = '';

if (!$table_name || !$circuit_id) {
    die("Missing table or circuit_id");
}

// Get fields and types
$fields = $pdo->query("SHOW COLUMNS FROM `$table_name`")->fetchAll(PDO::FETCH_ASSOC);

// Get existing row
$stmt = $pdo->prepare("SELECT * FROM `$table_name` WHERE circuit_id = ?");
$stmt->execute([$circuit_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    die("Row not found for circuit_id: $circuit_id");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $updates = [];
    $params = [];
    foreach ($fields as $f) {
        $fname = $f['Field'];
        if ($fname === 'circuit_id') continue; // Don't edit PK
        $ftype = strtolower($f['Type']);
        $value = $_POST[$fname] ?? null;

        // ENUM: validate value
        if (strpos($ftype, 'enum(') === 0) {
            preg_match("/^enum\((.*)\)$/i", $ftype, $matches);
            $enum_options = array_map(function($v) { return trim($v,"'"); }, explode(",", $matches[1]));
            if (!in_array($value, $enum_options)) {
                $message = "Invalid value for $fname";
                break;
            }
        }

        $updates[] = "`$fname` = ?";
        $params[] = $value;
    }
    if (!$message) {
        $params[] = $circuit_id;
        $sql = "UPDATE `$table_name` SET " . implode(", ", $updates) . " WHERE circuit_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $message = "<span class='text-success'>Record updated!</span>";
        // Reload updated row
        $stmt = $pdo->prepare("SELECT * FROM `$table_name` WHERE circuit_id = ?");
        $stmt->execute([$circuit_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Edit Third Party Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<main class="container my-5">
    <h3 class="mb-4">Edit Third Party Details: <?= htmlspecialchars($table_name) ?> (Circuit ID: <?= htmlspecialchars($circuit_id) ?>)</h3>
    <?= $message ?>
    <form method="post">
        <?php foreach ($fields as $f): 
            $fname = $f['Field'];
            $ftype = strtolower($f['Type']);
            $fvalue = $row[$fname] ?? '';
            ?>
            <div class="mb-3">
                <label class="form-label"><?= htmlspecialchars($fname) ?></label>
                <?php if ($fname === 'circuit_id'): ?>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($fvalue) ?>" readonly>
                <?php elseif (strpos($ftype, 'enum(') === 0): 
                    preg_match("/^enum\((.*)\)$/i", $ftype, $matches);
                    $enum_options = array_map(function($v) { return trim($v,"'"); }, explode(",", $matches[1]));
                ?>
                    <select name="<?= htmlspecialchars($fname) ?>" class="form-select">
                        <?php foreach ($enum_options as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= $opt == $fvalue ? "selected" : "" ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($ftype === 'date'): ?>
                    <input type="date" name="<?= htmlspecialchars($fname) ?>" class="form-control" value="<?= htmlspecialchars($fvalue) ?>">
                <?php elseif ($ftype === 'int' || $ftype === 'float'): ?>
                    <input type="number" name="<?= htmlspecialchars($fname) ?>" class="form-control" value="<?= htmlspecialchars($fvalue) ?>">
                <?php else: ?>
                    <input type="text" name="<?= htmlspecialchars($fname) ?>" class="form-control" value="<?= htmlspecialchars($fvalue) ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <button type="submit" name="update" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
        <a href="view_third_party_details.php?table=<?=urlencode($table_name)?>" class="btn btn-secondary">Back</a>
    </form>
</main>
</body>
</html>