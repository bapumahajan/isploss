<?php
session_name('oss_portal');
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=()');

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$timeout_duration = 900;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();
require_once 'includes/db.php';

if (!isset($_SESSION['cacti_tokens'])) {
    $_SESSION['cacti_tokens'] = [];
}
function showval($val) {
    return htmlspecialchars(($val !== null && $val !== '') ? $val : 'NA');
}
function showdate($val) {
    if (!$val || $val === '0000-00-00') return 'NA';
    $d = DateTime::createFromFormat('Y-m-d', $val);
    if (!$d) $d = DateTime::createFromFormat('Y-m-d H:i:s', $val);
    return $d ? strtoupper($d->format('d-M-Y')) : 'NA';
}

// Handle complaint submission
$complaint_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['raise_complaint'])) {
    $circuit_id = $_POST['circuit_id'];
    $organization_name = $_POST['organization_name'];
    $bandwidth = $_POST['bandwidth'];
    $link_type = $_POST['link_type'];
    $docket_no = $circuit_id . '-' . date('Ymd-His') . '-' . rand(100,999);
    $incident_status = $_POST['incident_status'];
    $docket_booking_time = date('Y-m-d H:i:s');
    $next_update_time = $_POST['next_update_time'];
    $remarks = $_POST['remarks'];

    // Insert into complaints
    $insert = $pdo->prepare("INSERT INTO complaints 
        (circuit_id, organization_name, bandwidth, link_type, docket_no, incident_status, docket_booking_time, next_update_time, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([
        $circuit_id, $organization_name, $bandwidth, $link_type,
        $docket_no, $incident_status, $docket_booking_time, $next_update_time, $remarks
    ]);

    $complaint_id = $pdo->lastInsertId();

    // Optionally, insert into complaint_history
    $insert_history = $pdo->prepare("INSERT INTO complaint_history
        (rid, incident_status, remarks, next_update_time, updated_time)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert_history->execute([
        $complaint_id, $incident_status, $remarks, $next_update_time, $docket_booking_time
    ]);

    $complaint_success = "Complaint raised successfully. Docket No: " . htmlspecialchars($docket_no);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Customer Circuit Dashboard</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
/* ... your CSS as above ... */
</style>
</head>
<body>
    <div class="topbar">
        <!-- ... your topbar code ... -->
    </div>
    <div class="dashboard-content">
      <div class="dashboard-main">
        <?php
        if (!empty($complaint_success)) {
            echo '<div class="alert alert-success">' . $complaint_success . '</div>';
        }
        if (isset($_GET['search'])) {
            $search = '%' . ($_GET['search'] ?? '') . '%';
            try {
                $stmt = $pdo->prepare("
                    SELECT cbi.*, cnd.*, nd.*
                    FROM customer_basic_information cbi
                    LEFT JOIN circuit_network_details cnd ON cbi.circuit_id = cnd.circuit_id
                    LEFT JOIN network_details nd ON cbi.circuit_id = nd.circuit_id
                    WHERE cbi.circuit_id LIKE :search OR cbi.organization_name LIKE :search OR cnd.wan_ip LIKE :search
                ");
                $stmt->execute(['search' => $search]);
                if ($stmt->rowCount() > 0) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $ip_stmt = $pdo->prepare("SELECT * FROM circuit_ips WHERE circuit_id = ?");
                        $ip_stmt->execute([$row['circuit_id'] ?? '']);
                        $circuit_ips = $ip_stmt->fetchAll(PDO::FETCH_ASSOC);

                        $stmt_contacts = $pdo->prepare("SELECT contact_number FROM customer_contacts WHERE circuit_id = ?");
                        $stmt_contacts->execute([$row['circuit_id'] ?? '']);
                        $contact_numbers = $stmt_contacts->fetchAll(PDO::FETCH_COLUMN);

                        $stmt_emails = $pdo->prepare("SELECT ce_email_id FROM customer_emails WHERE circuit_id = ?");
                        $stmt_emails->execute([$row['circuit_id'] ?? '']);
                        $ce_email_ids = $stmt_emails->fetchAll(PDO::FETCH_COLUMN);

                        $contact_numbers_str = $contact_numbers ? htmlspecialchars(implode(', ', array_filter($contact_numbers))) : 'NA';
                        $ce_email_ids_str = $ce_email_ids ? htmlspecialchars(implode(', ', array_filter($ce_email_ids))) : 'NA';

                        $stmtGraph = $pdo->prepare("SELECT local_graph_id FROM cacti_graphs WHERE circuit_id = ?");
                        $stmtGraph->execute([$row['circuit_id'] ?? '']);
                        $graph = $stmtGraph->fetch(PDO::FETCH_ASSOC);

                        $status_class = match (strtolower($row['circuit_status'] ?? '')) {
                            'active' => 'status-badge',
                            'terminated' => 'status-badge bg-danger',
                            'suspended' => 'status-badge bg-warning text-dark',
                            default => 'status-badge bg-secondary',
                        };
        ?>
        <div class="card">
            <div class="card-header">
                <h5>
                    <?= showval($row['organization_name'] ?? null) ?>
                    <span class="text-muted ms-2" style="color:#8b8b8b;opacity:.8;">[<?= showval($row['circuit_id'] ?? null) ?>]</span>
                </h5>
                <span class="<?= $status_class ?>"><?= showval($row['circuit_status'] ?? null) ?></span>
            </div>
            <ul class="nav modern-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#basic<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-info-circle"></i> Basic</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#network<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-hdd-network"></i> Network</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ip<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-key"></i> IP/Auth</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#circuitips<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-list-ol"></i> IPs</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cacti<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-bar-chart"></i> Cacti</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#thirdparty<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-diagram-2"></i> 3rd Party</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#complaints<?= $row['circuit_id'] ?>" type="button"><i class="bi bi-exclamation-diamond"></i> Complaints</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="basic<?= $row['circuit_id'] ?>">
                    <!-- ... Basic Info ... -->
                </div>
                <div class="tab-pane fade" id="network<?= $row['circuit_id'] ?>">
                    <!-- ... Network Info ... -->
                </div>
                <div class="tab-pane fade" id="ip<?= $row['circuit_id'] ?>">
                    <!-- ... IP/Auth Info ... -->
                </div>
                <div class="tab-pane fade" id="circuitips<?= $row['circuit_id'] ?>">
                    <!-- ... IPs Table ... -->
                </div>
                <div class="tab-pane fade" id="cacti<?= $row['circuit_id'] ?>">
                    <!-- ... Cacti Graph ... -->
                </div>
                <div class="tab-pane fade" id="thirdparty<?= htmlspecialchars($row['circuit_id']) ?>">
                    <!-- ... Third Party Info ... -->
                </div>
                <!-- Complaints Tab -->
                <div class="tab-pane fade" id="complaints<?= $row['circuit_id'] ?>">
                    <h5>Raise Complaint</h5>
                    <form method="post" class="mb-4">
                        <input type="hidden" name="circuit_id" value="<?= htmlspecialchars($row['circuit_id']) ?>">
                        <input type="hidden" name="organization_name" value="<?= htmlspecialchars($row['organization_name']) ?>">
                        <input type="hidden" name="bandwidth" value="<?= htmlspecialchars($row['bandwidth']) ?>">
                        <input type="hidden" name="link_type" value="<?= htmlspecialchars($row['link_type']) ?>">
                        <div class="mb-2">
                            <label for="incident_status_<?= $row['circuit_id'] ?>" class="form-label">Incident Status</label>
                            <select class="form-select" name="incident_status" id="incident_status_<?= $row['circuit_id'] ?>" required>
                                <option value="open">Open</option>
                                <option value="pending">Pending</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="next_update_time_<?= $row['circuit_id'] ?>" class="form-label">Next Update Time</label>
                            <input type="datetime-local" class="form-control" name="next_update_time" id="next_update_time_<?= $row['circuit_id'] ?>" required>
                        </div>
                        <div class="mb-2">
                            <label for="remarks_<?= $row['circuit_id'] ?>" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="remarks_<?= $row['circuit_id'] ?>" required></textarea>
                        </div>
                        <button class="btn btn-primary" type="submit" name="raise_complaint" value="1">Submit Complaint</button>
                    </form>
                    <h6 class="mt-4">Complaint History</h6>
                    <?php
                    // Fetch and display complaints for this circuit
                    $complaints = [];
                    $complaints_stmt = $pdo->prepare("SELECT * FROM complaints WHERE circuit_id = ? ORDER BY docket_booking_time DESC");
                    $complaints_stmt->execute([$row['circuit_id']]);
                    $complaints = $complaints_stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($complaints):
                    ?>
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Docket No</th>
                                    <th>Booking Time</th>
                                    <th>Status</th>
                                    <th>Next Update</th>
                                    <th>Remarks</th>
                                    <th>History</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complaints as $complaint): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($complaint['docket_no']) ?></td>
                                        <td><?= htmlspecialchars($complaint['docket_booking_time']) ?></td>
                                        <td><?= htmlspecialchars($complaint['incident_status']) ?></td>
                                        <td><?= htmlspecialchars($complaint['next_update_time']) ?></td>
                                        <td><?= htmlspecialchars($complaint['remarks']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" type="button" data-bs-toggle="collapse" data-bs-target="#history<?= $complaint['id'] ?>">Show</button>
                                        </td>
                                    </tr>
                                    <tr class="collapse" id="history<?= $complaint['id'] ?>">
                                        <td colspan="6">
                                            <strong>History:</strong>
                                            <ul>
                                                <?php
                                                $history_stmt = $pdo->prepare("SELECT * FROM complaint_history WHERE rid = ? ORDER BY updated_time DESC");
                                                $history_stmt->execute([$complaint['id']]);
                                                $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
                                                foreach ($history as $h) {
                                                    echo "<li>Status: ".htmlspecialchars($h['incident_status']).", Remark: ".htmlspecialchars($h['remarks']).", Updated: ".htmlspecialchars($h['updated_time'])."</li>";
                                                }
                                                ?>
                                            </ul>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">No complaints/dockets for this circuit.</div>
                    <?php endif; ?>
                </div>
                <!-- End Complaints Tab -->
            </div>
        </div>
        <?php
                    }
                } else {
                    echo '<div class="alert alert-warning text-center">No records found matching your search.</div>';
                }
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        ?>
      </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>