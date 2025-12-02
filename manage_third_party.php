<?php
session_name('oss_portal');
session_start();
require_once 'includes/auth.php';
require_roles(['admin', 'network_manager']);
require_once 'includes/db.php';
date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $operator = trim($_POST['third_party_operator'] ?? '');
    $link_type = trim($_POST['link_type'] ?? '');
    $last_mile = trim($_POST['last_mile_link_media'] ?? '');
    $vlan = trim($_POST['vlan'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($operator && $link_type && $last_mile) {
        $stmt = $pdo->prepare("INSERT INTO third_party_details (third_party_operator, link_type, last_mile_link_media, vlan, remarks) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$operator, $link_type, $last_mile, $vlan, $remarks]);
        $_SESSION['message'] = "New record added successfully.";
        header("Location: manage_third_party.php");
        exit;
    } else {
        $error = "Please fill all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Third Party Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h2>Add New Third Party Details</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Third Party Operator <span class="text-danger">*</span></label>
            <input type="text" name="third_party_operator" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Link Type <span class="text-danger">*</span></label>
            <input type="text" name="link_type" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Last Mile Link Media <span class="text-danger">*</span></label>
            <select name="last_mile_link_media" class="form-select" required>
                <option value="">Select</option>
                <option value="Own">Own</option>
                <option value="Operator">Operator</option>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">VLAN</label>
            <input type="text" name="vlan" class="form-control">
        </div>

        <div class="col-12">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control"></textarea>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">Add Record</button>
            <a href="manage_third_party.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
</body>
</html>
