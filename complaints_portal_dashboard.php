<?php
// complaints_portal_dashboard.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

// Security: check user session
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$complaint_id) {
    echo "Invalid complaint ID.";
    exit;
}

// Fetch complaint details
$stmt = $pdo->prepare("SELECT * FROM complaints WHERE id = ?");
$stmt->execute([$complaint_id]);
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    echo "Complaint not found.";
    exit;
}

$errors = [];
$success = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- BEGIN PATCH: Docket Closed Time Validation ---
function validate_closed_time($booking_time, $closed_time) {
    if (empty($closed_time)) return ''; // no custom time, will use server time
    $booking = strtotime($booking_time);
    $closed = strtotime($closed_time);
    if ($closed === false) return "Invalid Docket Closed Time format.";
    if ($closed < $booking) return "Docket Closed Time cannot be earlier than Booking Time.";
    return '';
}
// --- END PATCH ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    }

    $incident_status = trim($_POST['incident_status'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $next_update_time = trim($_POST['next_update_time'] ?? '');
    $customer_communications_call = trim($_POST['customer_communications_call'] ?? '');
    $alarm_status = trim($_POST['alarm_status'] ?? '');
    $rfo = trim($_POST['rfo'] ?? '');
    $updated_by = $_SESSION['username'];
    $docket_closed_time = trim($_POST['docket_closed_time'] ?? '');

    // Basic validation
    if (empty($incident_status)) $errors[] = "Status is required.";
    if (empty($remarks)) $errors[] = "Remarks are required.";
    if (strtolower($incident_status) == 'closed') {
        if (empty($rfo)) {
            $errors[] = "RFO (Reason For Outage) is required when closing the complaint.";
        }
        // PATCH: If docket_closed_time is empty, use server time. Otherwise, validate it.
        if (empty($docket_closed_time)) {
            $docket_closed_time = date('Y-m-d H:i:s'); // Use server time
            // PATCH: Ensure server time is not before booking time
            if (strtotime($docket_closed_time) < strtotime($complaint['docket_booking_time'])) {
                $docket_closed_time = $complaint['docket_booking_time'];
            }
        } else {
            // PATCH: Validate custom closed time
            // Accept both datetime-local (Y-m-d\TH:i) and Y-m-d H:i formats
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $docket_closed_time) ?: DateTime::createFromFormat('Y-m-d H:i', $docket_closed_time);
            if ($dt) $docket_closed_time = $dt->format('Y-m-d H:i:s');
            $err = validate_closed_time($complaint['docket_booking_time'], $docket_closed_time);
            if ($err) $errors[] = $err;
        }
        if (empty($docket_closed_time)) {
            $errors[] = "Docket Closed Time is required when closing the complaint.";
        }
    } else {
        if (empty($next_update_time)) {
            $errors[] = "Next Update Time is required unless status is Closed.";
        }
    }

    if (empty($errors)) {
        // Update current complaint, including docket_closed_time and closed_by if closing
        if (strtolower($incident_status) == 'closed') {
            $update_stmt = $pdo->prepare("UPDATE complaints SET incident_status = ?, remarks = ?, next_update_time = ?, docket_closed_time = ?, closed_by = ?, updated_time = NOW() WHERE id = ?");
            $update_stmt->execute([
                $incident_status,
                $remarks,
                $next_update_time ?: null,
                $docket_closed_time,
                $updated_by,
                $complaint['id']
            ]);
        } else {
            $update_stmt = $pdo->prepare("UPDATE complaints SET incident_status = ?, remarks = ?, next_update_time = ?, updated_time = NOW() WHERE id = ?");
            $update_stmt->execute([
                $incident_status,
                $remarks,
                $next_update_time ?: null,
                $complaint['id']
            ]);
        }

        // Insert history record (already includes next_update_time)
        $history_stmt = $pdo->prepare("INSERT INTO complaint_history (rid, docket_no, incident_status, remarks, next_update_time, customer_communications_call, alarm_status, rfo, updated_by, updated_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $history_stmt->execute([
            $complaint['id'],
            $complaint['docket_no'],
            $incident_status,
            $remarks,
            $next_update_time ?: null,
            $customer_communications_call ?: null,
            $alarm_status ?: null,
            $rfo ?: null,
            $updated_by
        ]);

        // Refresh the complaint data
        echo "<script>
            if(window.parent && window.parent.location) window.parent.location.reload();
            window.close();
        </script>";
        exit;
    }
}

// Fetch complaint history (now includes RFO)
$history_stmt = $pdo->prepare("SELECT * FROM complaint_history WHERE rid = ? ORDER BY updated_time DESC");
$history_stmt->execute([$complaint['id']]);
$histories = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for datetime-local value
function to_datetime_local($dt) {
    if (!$dt) return '';
    return date('Y-m-d\TH:i', strtotime($dt));
}
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

    <!-- Error Display -->
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
            <tr><th>Circuit ID</th><td><?= htmlspecialchars($complaint['circuit_id']) ?></td></tr>
            <tr><th>Status</th><td><?= htmlspecialchars($complaint['incident_status']) ?></td></tr>
            <tr><th>Booking Time</th><td><?= htmlspecialchars($complaint['docket_booking_time']) ?></td></tr>
            <tr><th>Remarks</th><td><?= nl2br(htmlspecialchars($complaint['remarks'])) ?></td></tr>
            <tr><th>Next Update Time</th><td><?= htmlspecialchars($complaint['next_update_time']) ?></td></tr>
            <tr><th>Closed Time</th><td><?= htmlspecialchars($complaint['docket_closed_time'] ?? '') ?></td></tr>
            <tr><th>Closed By</th><td><?= htmlspecialchars($complaint['closed_by'] ?? '') ?></td></tr>
        </table>
    </div>

    <!-- Update Form -->
    <div class="form-section">
        <h5>Update Complaint</h5>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="mb-3">
                <label>Status <span class="text-danger">*</span></label>
                <select name="incident_status" class="form-select" id="incident_status" required onchange="handleStatusChange()">
                    <option value="">-- Select Status --</option>
                    <option value="Open" <?= (strtolower($_POST['incident_status'] ?? $complaint['incident_status']) == 'open') ? 'selected' : '' ?>>Open</option>
                    <option value="Hold" <?= (strtolower($_POST['incident_status'] ?? $complaint['incident_status']) == 'hold') ? 'selected' : '' ?>>Hold</option>
                    <option value="Pending with Customer" <?= (strtolower($_POST['incident_status'] ?? $complaint['incident_status']) == 'pending with customer') ? 'selected' : '' ?>>Pending With Customer</option>
                    <option value="Closed" <?= (strtolower($_POST['incident_status'] ?? $complaint['incident_status']) == 'closed') ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="mb-3">
                <label>Remarks <span class="text-danger">*</span></label>
                <textarea name="remarks" class="form-control" rows="3" required><?= htmlspecialchars($_POST['remarks'] ?? $complaint['remarks']) ?></textarea>
            </div>
            <div class="mb-3" id="next_update_time_group">
                <label>Next Update Time</label>
                <input type="datetime-local" name="next_update_time" class="form-control"
                    value="<?= htmlspecialchars($_POST['next_update_time'] ?? to_datetime_local($complaint['next_update_time'] ?? '')) ?>">
            </div>
            <div class="mb-3" id="docket_closed_time_group" style="display:none;">
                <label>Docket Closed Time <span class="text-danger">*</span></label>
                <input type="datetime-local" name="docket_closed_time" class="form-control"
                    value="<?= htmlspecialchars($_POST['docket_closed_time'] ?? to_datetime_local($complaint['docket_closed_time'] ?? '')) ?>">
            </div>
            <div class="mb-3">
                <label>Customer Communications Call</label>
                <textarea name="customer_communications_call" class="form-control" rows="2"><?= htmlspecialchars($_POST['customer_communications_call'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label>Alarm Status</label>
                <input type="text" name="alarm_status" class="form-control" value="<?= htmlspecialchars($_POST['alarm_status'] ?? '') ?>">
            </div>
            <div class="mb-3" id="rfo_group" style="display:none;">
                <label>RFO (Reason For Outage) <span class="text-danger">*</span></label>
                <textarea name="rfo" class="form-control" rows="2"><?= htmlspecialchars($_POST['rfo'] ?? '') ?></textarea>
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
                    <th>Docket No</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Next Update</th>
                    <th>Customer Call</th>
                    <th>Alarm Status</th>
                    <th>RFO</th>
                    <th>Updated By</th>
                    <th>Update Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($histories as $history): ?>
                <tr>
                    <td><?= htmlspecialchars($history['docket_no']) ?></td>
                    <td><?= htmlspecialchars($history['incident_status']) ?></td>
                    <td><?= nl2br(htmlspecialchars($history['remarks'])) ?></td>
                    <td><?= htmlspecialchars($history['next_update_time']) ?></td>
                    <td><?= nl2br(htmlspecialchars($history['customer_communications_call'])) ?></td>
                    <td><?= htmlspecialchars($history['alarm_status']) ?></td>
                    <td><?= nl2br(htmlspecialchars($history['rfo'])) ?></td>
                    <td><?= htmlspecialchars($history['updated_by']) ?></td>
                    <td><?= htmlspecialchars($history['updated_time']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$histories): ?>
                <tr><td colspan="9" class="text-center text-muted">No history available.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function handleStatusChange() {
    var status = document.getElementById('incident_status').value.toLowerCase();
    var nextUpdateGroup = document.getElementById('next_update_time_group');
    var rfoGroup = document.getElementById('rfo_group');
    var closedTimeGroup = document.getElementById('docket_closed_time_group');
    if (status === 'closed') {
        nextUpdateGroup.style.display = 'none';
        rfoGroup.style.display = '';
        closedTimeGroup.style.display = '';
    } else {
        nextUpdateGroup.style.display = '';
        rfoGroup.style.display = 'none';
        closedTimeGroup.style.display = 'none';
    }
}
window.onload = handleStatusChange;
</script>
</body>
</html>