<?php
// complaints_portal.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Accept rid directly (same as in dashboard)
$rid = isset($_GET['rid']) ? trim($_GET['rid']) : '';
if (!$rid) {
    echo "Invalid complaint ID.";
    exit;
}

// Fetch complaint details AND customer_address by joining to customer_basic_information
$stmt = $pdo->prepare("
    SELECT c.*, cbi.customer_address
    FROM complaints c
    LEFT JOIN customer_basic_information cbi ON c.circuit_id = cbi.circuit_id
    WHERE c.id = ?
");
$stmt->execute([$rid]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    echo "Complaint not found.";
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incident_status = trim($_POST['incident_status']);
    $remarks = trim($_POST['remarks']);
    $next_update_time = trim($_POST['next_update_time']);
    $customer_communications_call = trim($_POST['customer_communications_call']);
    $alarm_status = trim($_POST['alarm_status']);
    $updated_by = $_SESSION['username'];

    if (empty($incident_status)) $errors[] = "Status is required.";
    if (empty($remarks)) $errors[] = "Remarks are required.";

    if (empty($errors)) {
        // Update complaint
        $update_stmt = $pdo->prepare("UPDATE complaints SET incident_status = ?, remarks = ?, updated_time = NOW() WHERE id = ?");
        $update_stmt->execute([$incident_status, $remarks, $rid]);

        // Insert history
        $history_stmt = $pdo->prepare("INSERT INTO complaint_history (rid, incident_status, remarks, next_update_time, customer_communications_call, alarm_status, updated_by, updated_time) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $history_stmt->execute([$rid, $incident_status, $remarks, $next_update_time ?: null, $customer_communications_call ?: null, $alarm_status ?: null, $updated_by]);

        // Refresh to see updated details
        header("Location: complaints_portal.php?rid=" . urlencode($rid));
        exit;
    }
}

// Fetch complaint history
$history_stmt = $pdo->prepare("SELECT * FROM complaint_history WHERE rid = ? ORDER BY updated_time DESC");
$history_stmt->execute([$rid]);
$histories = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Handle Complaint - <?= htmlspecialchars($complaint['docket_no']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8fafc; padding: 20px; }
        .form-section { margin-bottom: 30px; }
    </style>
</head>
<body>
<div class="container">
    <h4 class="mb-4">Handle Complaint - <b><?= htmlspecialchars($complaint['docket_no']) ?></b></h4>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Complaint Details -->
    <div class="form-section">
        <h5>Current Details</h5>
        <table class="table table-sm table-bordered">
            <tr><th>Organization</th><td><?= htmlspecialchars($complaint['organization_name']) ?></td></tr>
            <tr><th>Customer Address</th><td><?= htmlspecialchars($complaint['customer_address']) ?></td></tr>
            <tr><th>Circuit ID</th><td><?= htmlspecialchars($complaint['circuit_id']) ?></td></tr>
            <tr><th>Status</th><td><?= htmlspecialchars($complaint['incident_status']) ?></td></tr>
            <tr><th>Booking Time</th><td><?= htmlspecialchars($complaint['docket_booking_time']) ?></td></tr>
            <tr><th>Remarks</th><td><?= nl2br(htmlspecialchars($complaint['remarks'])) ?></td></tr>
        </table>
    </div>

    <!-- Update Form -->
    <div class="form-section">
        <h5>Update Complaint</h5>
        <form method="post">
            <div class="mb-3">
                <label>Status <span class="text-danger">*</span></label>
                <select name="incident_status" class="form-select" required>
                    <option value="">-- Select Status --</option>
                    <option value="open" <?= $complaint['incident_status'] == 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="Hold" <?= $complaint['incident_status'] == 'Hold' ? 'selected' : '' ?>>Hold</option>
                    <option value="Pending With Customer" <?= $complaint['incident_status'] == 'Pending With Customer' ? 'selected' : '' ?>>Pending With Customer</option>
                    <option value="closed" <?= $complaint['incident_status'] == 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="mb-3">
                <label>Remarks <span class="text-danger">*</span></label>
                <textarea name="remarks" class="form-control" rows="3" required><?= htmlspecialchars($complaint['remarks']) ?></textarea>
            </div>
            <div class="mb-3">
                <label>Next Update Time</label>
                <input type="datetime-local" name="next_update_time" class="form-control">
            </div>
            <div class="mb-3">
                <label>Customer Communications Call</label>
                <textarea name="customer_communications_call" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
                <label>Alarm Status</label>
                <input type="text" name="alarm_status" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Update Complaint</button>
        </form>
    </div>

    <!-- Complaint History -->
    <div class="form-section">
        <h5>Complaint History</h5>
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Next Update</th>
                    <th>Customer Call</th>
                    <th>Alarm Status</th>
                    <th>Updated By</th>
                    <th>Update Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($histories as $history): ?>
                <tr>
                    <td>
                        <?php
                        $status = htmlspecialchars($history['incident_status']);
                        if ($status == 'open') echo '<span class="badge bg-success">Open</span>';
                        elseif ($status == 'Hold') echo '<span class="badge bg-warning text-dark">Hold</span>';
                        elseif ($status == 'Pending With Customer') echo '<span class="badge bg-info text-dark">Pending</span>';
                        elseif ($status == 'closed') echo '<span class="badge bg-secondary">Closed</span>';
                        else echo $status;
                        ?>
                    </td>
                    <td><?= nl2br(htmlspecialchars($history['remarks'])) ?></td>
                    <td><?= htmlspecialchars($history['next_update_time']) ?></td>
                    <td><?= nl2br(htmlspecialchars($history['customer_communications_call'])) ?></td>
                    <td><?= htmlspecialchars($history['alarm_status']) ?></td>
                    <td><?= htmlspecialchars($history['updated_by']) ?></td>
                    <td><?= htmlspecialchars($history['updated_time']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$histories): ?>
                <tr><td colspan="7" class="text-center text-muted">No history available.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>