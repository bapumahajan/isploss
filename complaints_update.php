<?php
// complaints_update.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once "includes/db.php";

$rid = $_GET['rid'] ?? null;
$docket_no = $_GET['docket_no'] ?? null;

if (!$rid || !$docket_no) {
    die("Invalid request. RID or docket number is missing.");
}

// Fetch existing data
$stmt = $pdo->prepare("SELECT * FROM complaints WHERE id = ? AND docket_no = ?");
$stmt->execute([$rid, $docket_no]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    die("Complaint not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incident_status = $_POST['incident_status'];
    $next_update_time = $_POST['next_update_time'];
    $remarks = $_POST['remarks'];

    $update = $pdo->prepare("UPDATE complaints SET incident_status = ?, next_update_time = ?, updated_time = NOW(), remarks = ? WHERE id = ? AND docket_no = ?");
    $update->execute([$incident_status, $next_update_time, $remarks, $rid, $docket_no]);

    echo "<script>alert('Complaint updated successfully'); window.location.href = 'complaints_dashboard.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Complaint</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h2>Update Complaint - Docket No: <?= htmlspecialchars($docket_no) ?></h2>

    <form method="post" class="mt-3">
        <div class="mb-3">
            <label for="incident_status" class="form-label">Status</label>
            <select name="incident_status" id="incident_status" class="form-select" required>
                <option value="Open" <?= $complaint['incident_status'] == 'Open' ? 'selected' : '' ?>>Open</option>
                <option value="In Progress" <?= $complaint['incident_status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="Resolved" <?= $complaint['incident_status'] == 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="Closed" <?= $complaint['incident_status'] == 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="next_update_time" class="form-label">Next Update Time</label>
            <input type="datetime-local" name="next_update_time" id="next_update_time" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($complaint['next_update_time'])) ?>">
        </div>

        <div class="mb-3">
            <label for="remarks" class="form-label">Remarks</label>
            <textarea name="remarks" id="remarks" class="form-control" rows="4"><?= htmlspecialchars($complaint['remarks']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">Update Complaint</button>
        <a href="complaints_dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>
