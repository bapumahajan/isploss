<?php
//filename:complaint_status.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['docket_no'])) {
    echo "<div class='alert alert-danger'>No complaint selected.</div>";
    exit;
}
$docket_no = $_GET['docket_no'];

// Fetch complaint details
$stmt = $pdo->prepare("SELECT * FROM complaints WHERE docket_no = ?");
$stmt->execute([$docket_no]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    echo "<div class='alert alert-danger'>Complaint not found.</div>";
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['incident_status'];
    $remarks = trim($_POST['status_remarks']);
    $update = $pdo->prepare("UPDATE complaints SET incident_status=?, updated_time=NOW(), remarks=? WHERE docket_no=?");
    $update->execute([$new_status, $remarks, $docket_no]);
    $complaint['incident_status'] = $new_status;
    $complaint['remarks'] = $remarks;
    $success = "Status updated successfully!";
}

// Status options
$status_options = [
    'open' => 'Open',
    'pending' => 'Pending',
    'inprogress' => 'In Progress',
    'resolved' => 'Resolved',
    'reopen' => 'Reopen',
    'rejected' => 'Rejected'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Complaint Status</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container py-4">
    <h4>Complaint Docket: <?= htmlspecialchars($complaint['docket_no']) ?></h4>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <form method="post" class="card p-3">
        <div class="mb-2">
            <label class="form-label">Current Status</label>
            <div>
                <span class="badge bg-primary"><?= htmlspecialchars($status_options[$complaint['incident_status']]) ?></span>
            </div>
        </div>
        <div class="mb-2">
            <label for="incident_status" class="form-label">Change Status</label>
            <select name="incident_status" id="incident_status" class="form-select" required>
                <?php foreach ($status_options as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $complaint['incident_status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label for="status_remarks" class="form-label">Remarks</label>
            <textarea name="status_remarks" id="status_remarks" class="form-control" rows="2"><?= htmlspecialchars($complaint['remarks']) ?></textarea>
        </div>
        <button type="submit" name="update_status" class="btn btn-success">Update Status</button>
        <a href="dashboard.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>