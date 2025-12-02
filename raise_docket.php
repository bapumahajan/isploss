<?php
session_name('oss_portal');
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['circuit_id'])) {
    echo "<div class='alert alert-danger'>No circuit selected.</div>";
    exit;
}
$circuit_id = $_GET['circuit_id'];

// Fetch circuit details
$stmt = $pdo->prepare("
    SELECT 
        cbi.circuit_id, 
        cbi.organization_name, 
        cbi.customer_address, 
        nd.circuit_status, 
        nd.bandwidth, 
        nd.link_type
    FROM customer_basic_information cbi
    LEFT JOIN network_details nd ON cbi.circuit_id = nd.circuit_id
    WHERE cbi.circuit_id = ?
");
$stmt->execute([$circuit_id]);
$circuit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$circuit) {
    echo "<div class='alert alert-danger mt-4'>Circuit not found.</div>";
    exit;
}

// Fetch fault types for dropdown
$fault_types_stmt = $pdo->prepare("SELECT id, fault_name FROM fault_type ORDER BY fault_name ASC");
$fault_types_stmt->execute();
$fault_types = $fault_types_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle add/edit/delete contact
if (isset($_POST['edit_contact_id'])) {
    $contact_id = intval($_POST['edit_contact_id']);
    $new_contact = trim($_POST['edit_contact_number']);
    if ($new_contact !== '') {
        $update = $pdo->prepare("UPDATE customer_contacts SET contact_number=? WHERE id=? AND circuit_id=?");
        $update->execute([$new_contact, $contact_id, $circuit_id]);
    }
}
if (isset($_POST['delete_contact_id'])) {
    $contact_id = intval($_POST['delete_contact_id']);
    $delete = $pdo->prepare("DELETE FROM customer_contacts WHERE id=? AND circuit_id=?");
    $delete->execute([$contact_id, $circuit_id]);
}
if (isset($_POST['new_contact']) && !empty(trim($_POST['new_contact_number']))) {
    $new_contact = trim($_POST['new_contact_number']);
    $insert_contact = $pdo->prepare("INSERT INTO customer_contacts (circuit_id, contact_number) VALUES (?, ?)");
    $insert_contact->execute([$circuit_id, $new_contact]);
}

// Handle add/edit/delete email
if (isset($_POST['edit_email_id'])) {
    $email_id = intval($_POST['edit_email_id']);
    $new_email = trim($_POST['edit_email_addr']);
    if ($new_email !== '') {
        $update = $pdo->prepare("UPDATE customer_emails SET ce_email_id=? WHERE id=? AND circuit_id=?");
        $update->execute([$new_email, $email_id, $circuit_id]);
    }
}
if (isset($_POST['delete_email_id'])) {
    $email_id = intval($_POST['delete_email_id']);
    $delete = $pdo->prepare("DELETE FROM customer_emails WHERE id=? AND circuit_id=?");
    $delete->execute([$email_id, $circuit_id]);
}
if (isset($_POST['new_email']) && !empty(trim($_POST['new_email_id']))) {
    $new_email = trim($_POST['new_email_id']);
    $insert_email = $pdo->prepare("INSERT INTO customer_emails (circuit_id, ce_email_id) VALUES (?, ?)");
    $insert_email->execute([$circuit_id, $new_email]);
}

// Fetch contacts and emails (with id for edit/delete)
$contact_stmt = $pdo->prepare("SELECT id, contact_number FROM customer_contacts WHERE circuit_id = ?");
$contact_stmt->execute([$circuit_id]);
$contacts = $contact_stmt->fetchAll(PDO::FETCH_ASSOC);

$email_stmt = $pdo->prepare("SELECT id, ce_email_id FROM customer_emails WHERE circuit_id = ?");
$email_stmt->execute([$circuit_id]);
$emails = $email_stmt->fetchAll(PDO::FETCH_ASSOC);

$docket_success = '';
$error_message = '';
$has_open_docket = false;
$docket_info = null;

// Get open docket info with fault type if exists
$docket_stmt = $pdo->prepare("
    SELECT c.docket_no, c.docket_booking_time, f.fault_name 
    FROM complaints c
    LEFT JOIN fault_type f ON c.fault_type_id = f.id
    WHERE c.circuit_id = ? AND c.incident_status = 'open' 
    ORDER BY c.docket_booking_time DESC LIMIT 1
");
$docket_stmt->execute([$circuit_id]);
$docket_info = $docket_stmt->fetch(PDO::FETCH_ASSOC);
$has_open_docket = $docket_info ? true : false;

// Only allow raising dockets if circuit is active and no open docket exists
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['raise_complaint']) && $circuit['circuit_status'] === 'Active' && !$has_open_docket) {
    $docket_no = 'ISPL-' . $circuit_id . '-' . date('Ymd-His') . '-' . rand(100,999);
    $incident_status = 'open'; // Always open for new dockets
    $docket_booking_time = date('Y-m-d H:i:s');
    $next_update_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $remarks = $_POST['remarks'];
    $fault_type_id = isset($_POST['fault_type_id']) ? intval($_POST['fault_type_id']) : null;

    // Double check again for duplicates (race condition guard)
    $open_stmt2 = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE circuit_id = ? AND incident_status = 'open'");
    $open_stmt2->execute([$circuit_id]);
    if ($open_stmt2->fetchColumn() > 0) {
        $error_message = "There is already an open complaint for this circuit. Please close it before raising a new one.";
    } else {
        // Insert into complaints
        $insert = $pdo->prepare("INSERT INTO complaints 
            (circuit_id, organization_name, bandwidth, link_type, docket_no, docket_booking_time, incident_status, updated_time, next_update_time, remarks, fault_type_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $circuit_id,
            $circuit['organization_name'],
            $circuit['bandwidth'],
            $circuit['link_type'],
            $docket_no,
            $docket_booking_time,
            $incident_status,
            $docket_booking_time,
            $next_update_time,
            $remarks,
            $fault_type_id
        ]);

        $docket_success = "Complaint raised successfully. Docket No: " . htmlspecialchars($docket_no);
        // Fetch the new docket info for display
        $docket_info = [
            'docket_no' => $docket_no,
            'docket_booking_time' => $docket_booking_time,
            'fault_name' => ''
        ];
        // Get the fault type name
        foreach ($fault_types as $ft) {
            if ($ft['id'] == $fault_type_id) {
                $docket_info['fault_name'] = $ft['fault_name'];
                break;
            }
        }
        $has_open_docket = true;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Raise Complaint</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
    body { background: #f8fafc; font-size: 14px; }
    .card { border-radius: 10px; box-shadow: 0 1px 7px #e1e4e8; }
    .card-header { font-weight: 500; font-size: 1rem; background: #f6f8fa; padding: 8px 16px;}
    .compact-list { margin-bottom: 0; font-size: 13px; }
    .compact-list li { padding-bottom: 4px; display: flex; align-items: center; }
    .inline-form { display:inline; margin:0; padding:0; }
    .inline-input { width:110px; display:inline-block; margin-right:2px; font-size: 13px;}
    .btn-icon { padding: 0.12rem 0.42rem; font-size: 1.1em; }
    .contacts-emails-title { font-size: 0.98rem; margin-bottom: 0.3rem; color: #3b4a5a; }
    .form-control, .form-select { font-size: 13px; }
    .form-control-sm { padding: 2px 6px; }
    .card-body { padding: 1rem 1.1rem; }
    @media (max-width: 650px) {
        .inline-input { width: 60px; }
        .card-body { padding: 0.7rem 0.4rem; }
    }
    </style>
</head>
<body>
<div class="container py-3">
    <div class="d-flex align-items-center mb-2">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm me-2"><i class="bi bi-arrow-left"></i></a>
        <h5 class="mb-0" style="font-size:1.1rem;font-weight:600;">Raise Complaint</h5>
    </div>
    <?php if ($docket_success): ?>
        <div class="alert alert-success py-2 px-3"><?= $docket_success ?></div>
    <?php elseif ($error_message): ?>
        <div class="alert alert-danger py-2 px-3"><?= $error_message ?></div>
    <?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-info-circle"></i> Circuit Details</div>
                <table class="table table-borderless mb-0" style="font-size:13px;">
                    <tr><th width="35%" class="fw-normal">Circuit ID</th><td><?= htmlspecialchars($circuit['circuit_id']) ?></td></tr>
                    <tr><th class="fw-normal">Status</th><td>
                        <?php if ($circuit['circuit_status'] === 'Active'): ?>
                            <span class="badge bg-success"><?= htmlspecialchars($circuit['circuit_status']) ?></span>
                        <?php elseif ($circuit['circuit_status'] === 'Suspended'): ?>
                            <span class="badge bg-warning text-dark"><?= htmlspecialchars($circuit['circuit_status']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= htmlspecialchars($circuit['circuit_status']) ?></span>
                        <?php endif; ?>
                    </td></tr>
                    <tr><th class="fw-normal">Organization Name</th><td><?= htmlspecialchars($circuit['organization_name']) ?></td></tr>
                    <tr><th class="fw-normal">Customer Address</th><td><?= htmlspecialchars($circuit['customer_address']) ?></td></tr>
                    <tr><th class="fw-normal">Bandwidth</th><td><?= htmlspecialchars($circuit['bandwidth']) ?></td></tr>
                    <tr><th class="fw-normal">Link Type</th><td><?= htmlspecialchars($circuit['link_type']) ?></td></tr>
                </table>
            </div>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person-lines-fill"></i> Customer Contacts & Emails</div>
                <div class="card-body py-2">
                    <div class="row">
                        <div class="col-sm-6 border-end">
                            <div class="contacts-emails-title"><i class="bi bi-telephone"></i> Contacts</div>
                            <ul class="compact-list list-unstyled">
                                <?php if ($contacts): ?>
                                    <?php foreach ($contacts as $contact): ?>
                                        <li>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="edit_contact_id" value="<?= $contact['id'] ?>">
                                                <input type="text" class="form-control form-control-sm d-inline-block inline-input" name="edit_contact_number" value="<?= htmlspecialchars($contact['contact_number']) ?>">
                                                <button class="btn btn-outline-success btn-sm btn-icon" type="submit" title="Save"><i class="bi bi-check"></i></button>
                                            </form>
                                            <form method="post" class="inline-form ms-1">
                                                <input type="hidden" name="delete_contact_id" value="<?= $contact['id'] ?>">
                                                <button class="btn btn-outline-danger btn-sm btn-icon" type="submit" title="Delete" onclick="return confirm('Delete this contact?')"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="text-muted">No contact numbers.</li>
                                <?php endif; ?>
                            </ul>
                            <form method="post" class="mt-2 d-flex gap-1">
                                <input type="text" class="form-control form-control-sm" name="new_contact_number" placeholder="Add contact">
                                <button class="btn btn-outline-primary btn-sm btn-icon" type="submit" name="new_contact" value="1"><i class="bi bi-plus"></i></button>
                            </form>
                        </div>
                        <div class="col-sm-6">
                            <div class="contacts-emails-title"><i class="bi bi-envelope"></i> Emails</div>
                            <ul class="compact-list list-unstyled">
                                <?php if ($emails): ?>
                                    <?php foreach ($emails as $email): ?>
                                        <li>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="edit_email_id" value="<?= $email['id'] ?>">
                                                <input type="email" class="form-control form-control-sm d-inline-block inline-input" name="edit_email_addr" value="<?= htmlspecialchars($email['ce_email_id']) ?>">
                                                <button class="btn btn-outline-success btn-sm btn-icon" type="submit" title="Save"><i class="bi bi-check"></i></button>
                                            </form>
                                            <form method="post" class="inline-form ms-1">
                                                <input type="hidden" name="delete_email_id" value="<?= $email['id'] ?>">
                                                <button class="btn btn-outline-danger btn-sm btn-icon" type="submit" title="Delete" onclick="return confirm('Delete this email?')"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="text-muted">No emails.</li>
                                <?php endif; ?>
                            </ul>
                            <form method="post" class="mt-2 d-flex gap-1">
                                <input type="email" class="form-control form-control-sm" name="new_email_id" placeholder="Add email">
                                <button class="btn btn-outline-primary btn-sm btn-icon" type="submit" name="new_email" value="1"><i class="bi bi-plus"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white py-2"><i class="bi bi-flag"></i> Raise Complaint</div>
                <div class="card-body py-2">
                    <?php if ($circuit['circuit_status'] === 'Active'): ?>
                        <?php if ($has_open_docket && $docket_info): ?>
                            <div class="alert alert-warning mb-2 py-2 px-2">
                                <div style="font-size:.98rem;"><strong>Open Docket:</strong></div>
                                <div><span class="fw-semibold">No:</span> <?= htmlspecialchars($docket_info['docket_no']) ?></div>
                                <div><span class="fw-semibold">Booking:</span> <?= htmlspecialchars($docket_info['docket_booking_time']) ?></div>
                                <?php if (!empty($docket_info['fault_name'])): ?>
                                <div><span class="fw-semibold">Complaint Type:</span> <?= htmlspecialchars($docket_info['fault_name']) ?></div>
                                <?php endif; ?>
                                <small class="text-muted">Please close the existing complaint before raising a new one.</small>
                            </div>
                        <?php elseif ($has_open_docket): ?>
                            <div class="alert alert-warning mb-2 py-2 px-2">There is already an open complaint for this circuit.</div>
                        <?php else: ?>
                            <form method="post">
                                <div class="mb-2">
                                    <label for="fault_type_id" class="form-label mb-0" style="font-size: .97em;">Complaint Type</label>
                                    <select class="form-select form-select-sm" name="fault_type_id" id="fault_type_id" required>
                                        <option value="">Select Type</option>
                                        <?php foreach ($fault_types as $type): ?>
                                            <option value="<?= $type['id'] ?>">
                                                <?= htmlspecialchars($type['fault_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label for="remarks" class="form-label mb-0" style="font-size: .97em;">Remarks</label>
                                    <textarea class="form-control form-control-sm" name="remarks" id="remarks" rows="3" required style="font-size:13px;"></textarea>
                                </div>
                                <button class="btn btn-success btn-sm w-100" type="submit" name="raise_complaint" value="1">
                                    <i class="bi bi-flag"></i> Submit
                                </button>
                            </form>
                            <div class="text-muted mt-1" style="font-size:.93em;">Status: <b>Open</b> &nbsp;&nbsp;|&nbsp;&nbsp; Next Update: +15min</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info mb-0 py-2 px-2">Complaints can only be raised for <b>Active</b> circuits.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>