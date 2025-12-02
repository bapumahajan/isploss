<?php
// complaints_portal1.php

session_name('oss_portal');
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php';
date_default_timezone_set('Asia/Kolkata');

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Authentication check
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Helper: JSON response for AJAX
function json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// --- AJAX HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token.']);
    }

    // --- Add/Edit/Delete Contact ---
    if ($_POST['ajax'] === 'contact') {
        $circuit_id = $_POST['circuit_id'] ?? '';
        if ($_POST['action'] === 'add') {
            $number = trim($_POST['number']);
            if (!preg_match('/^[0-9+\-\s]{7,20}$/', $number)) {
                json_response(['success' => false, 'message' => 'Invalid contact number.']);
            }
            $stmt = $pdo->prepare("INSERT INTO customer_contacts (circuit_id, contact_number) VALUES (?, ?)");
            $stmt->execute([$circuit_id, $number]);
            json_response(['success' => true, 'message' => 'Contact added.']);
        }
        if ($_POST['action'] === 'edit') {
            $id = intval($_POST['id']);
            $number = trim($_POST['number']);
            if (!preg_match('/^[0-9+\-\s]{7,20}$/', $number)) {
                json_response(['success' => false, 'message' => 'Invalid contact number.']);
            }
            $stmt = $pdo->prepare("UPDATE customer_contacts SET contact_number=? WHERE id=? AND circuit_id=?");
            $stmt->execute([$number, $id, $circuit_id]);
            json_response(['success' => true, 'message' => 'Contact updated.']);
        }
        if ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            $stmt = $pdo->prepare("DELETE FROM customer_contacts WHERE id=? AND circuit_id=?");
            $stmt->execute([$id, $circuit_id]);
            json_response(['success' => true, 'message' => 'Contact deleted.']);
        }
    }

    // --- Add/Edit/Delete Email ---
    if ($_POST['ajax'] === 'email') {
        $circuit_id = $_POST['circuit_id'] ?? '';
        if ($_POST['action'] === 'add') {
            $email = trim($_POST['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(['success' => false, 'message' => 'Invalid email address.']);
            }
            $stmt = $pdo->prepare("INSERT INTO customer_emails (circuit_id, ce_email_id) VALUES (?, ?)");
            $stmt->execute([$circuit_id, $email]);
            json_response(['success' => true, 'message' => 'Email added.']);
        }
        if ($_POST['action'] === 'edit') {
            $id = intval($_POST['id']);
            $email = trim($_POST['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(['success' => false, 'message' => 'Invalid email address.']);
            }
            $stmt = $pdo->prepare("UPDATE customer_emails SET ce_email_id=? WHERE id=? AND circuit_id=?");
            $stmt->execute([$email, $id, $circuit_id]);
            json_response(['success' => true, 'message' => 'Email updated.']);
        }
        if ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            $stmt = $pdo->prepare("DELETE FROM customer_emails WHERE id=? AND circuit_id=?");
            $stmt->execute([$id, $circuit_id]);
            json_response(['success' => true, 'message' => 'Email deleted.']);
        }
    }

    // --- Raise Complaint ---
    if ($_POST['ajax'] === 'raise_complaint') {
        $circuit_id = $_POST['circuit_id'];
        $remarks = trim($_POST['remarks']);
        $fault_type_id = intval($_POST['fault_type_id']);
        // Check for open complaint
        $open_stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE circuit_id=? AND incident_status='open'");
        $open_stmt->execute([$circuit_id]);
        if ($open_stmt->fetchColumn() > 0) {
            json_response(['success' => false, 'message' => 'There is already an open complaint for this circuit.']);
        }
        // Generate docket number
        $max_docket_stmt = $pdo->query("SELECT MAX(CAST(docket_no AS UNSIGNED)) AS max_docket FROM complaints WHERE LENGTH(docket_no) >= 5 AND docket_no REGEXP '^[0-9]+$'");
        $max_docket_row = $max_docket_stmt->fetch(PDO::FETCH_ASSOC);
        $next_docket_no = max(10000, intval($max_docket_row['max_docket']) + 1);
        $docket_no = strval($next_docket_no);

        // Get circuit info (join with network_details for bandwidth/link_type)
        $circuit_stmt = $pdo->prepare("
            SELECT cbi.*, nd.bandwidth, nd.link_type
            FROM customer_basic_information cbi
            LEFT JOIN network_details nd ON cbi.circuit_id = nd.circuit_id
            WHERE cbi.circuit_id = ?
        ");
        $circuit_stmt->execute([$circuit_id]);
        $circuit = $circuit_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$circuit) json_response(['success' => false, 'message' => 'Circuit not found.']);

        $docket_booking_time = date('Y-m-d H:i:s');
        $next_update_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $insert = $pdo->prepare("INSERT INTO complaints 
            (circuit_id, organization_name, bandwidth, link_type, docket_no, docket_booking_time, incident_status, updated_time, next_update_time, remarks, fault_type_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, ?, ?)");
        $insert->execute([
            $circuit_id,
            $circuit['organization_name'],
            $circuit['bandwidth'] ?? '',
            $circuit['link_type'] ?? '',
            $docket_no,
            $docket_booking_time,
            $docket_booking_time,
            $next_update_time,
            $remarks,
            $fault_type_id,
            $_SESSION['username']
        ]);
        json_response(['success' => true, 'message' => "Complaint raised. Docket No: $docket_no"]);
    }

    // --- Close Complaint (sets live docket_closed_time ONLY on close status) ---
    if ($_POST['ajax'] === 'close_complaint') {
        $docket_no = trim($_POST['docket_no']);
        $close_remarks = trim($_POST['close_remarks']);
        $now = date('Y-m-d H:i:s');
        $check = $pdo->prepare("SELECT incident_status, docket_closed_time FROM complaints WHERE docket_no=?");
        $check->execute([$docket_no]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // Only set docket_closed_time if status moves to "closed"
            if (strtolower($row['incident_status']) === 'open') {
                $close = $pdo->prepare("UPDATE complaints SET incident_status='closed', updated_time=?, docket_closed_time=?, remarks=CONCAT(remarks, '\nClosed: ', ?) WHERE docket_no=?");
                $close->execute([$now, $now, $close_remarks, $docket_no]);
                if ($close->rowCount() == 0) {
                    json_response(['success' => false, 'message' => "Update failed: No matching row found."]);
                }
                json_response(['success' => true, 'message' => "Complaint $docket_no closed."]);
            } elseif (strtolower($row['incident_status']) === 'closed' && $row['docket_closed_time'] === null) {
                // If status is already closed but closure time isn't set, set it
                $close = $pdo->prepare("UPDATE complaints SET docket_closed_time=?, remarks=CONCAT(remarks, '\nClosure time set: ', ?) WHERE docket_no=?");
                $close->execute([$now, $close_remarks, $docket_no]);
                json_response(['success' => true, 'message' => "Closure time set for $docket_no."]);
            } else {
                json_response(['success' => false, 'message' => "Unable to close: Already closed and closure time is set."]);
            }
        } else {
            json_response(['success' => false, 'message' => "Unable to close: Not found."]);
        }
    }

    // --- Fetch contacts/emails/complaints (for AJAX refresh) ---
    if ($_POST['ajax'] === 'fetch_data') {
        $circuit_id = $_POST['circuit_id'];
        $contacts = $pdo->prepare("SELECT id, contact_number FROM customer_contacts WHERE circuit_id=?");
        $contacts->execute([$circuit_id]);
        $emails = $pdo->prepare("SELECT id, ce_email_id FROM customer_emails WHERE circuit_id=?");
        $emails->execute([$circuit_id]);
        // Include docket_closed_time in output
        $complaints = $pdo->prepare("
            SELECT c.docket_no, c.docket_booking_time, c.incident_status, c.updated_time, c.docket_closed_time, f.fault_name, c.remarks, c.bandwidth, c.link_type, c.next_update_time
            FROM complaints c
            LEFT JOIN fault_type f ON c.fault_type_id = f.id
            WHERE c.circuit_id = ?
            ORDER BY c.docket_booking_time DESC
        ");
        $complaints->execute([$circuit_id]);
        json_response([
            'contacts' => $contacts->fetchAll(PDO::FETCH_ASSOC),
            'emails' => $emails->fetchAll(PDO::FETCH_ASSOC),
            'complaints' => $complaints->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }
}

// --- Normal page load: fetch circuit info and fault types ---
$search_query = $_GET['search_circuit'] ?? '';
$circuit = null;
$circuit_id = '';
$fault_types = [];
if ($search_query !== '') {
    $stmt = $pdo->prepare("
        SELECT cbi.circuit_id, cbi.organization_name, cbi.customer_address, nd.circuit_status, nd.bandwidth, nd.link_type
        FROM customer_basic_information cbi
        LEFT JOIN network_details nd ON cbi.circuit_id = nd.circuit_id
        WHERE cbi.circuit_id = ? OR cbi.organization_name LIKE ?
        LIMIT 1
    ");
    $stmt->execute([$search_query, "%$search_query%"]);
    $circuit = $stmt->fetch(PDO::FETCH_ASSOC);
    $circuit_id = $circuit ? $circuit['circuit_id'] : '';
}
$fault_types_stmt = $pdo->prepare("SELECT id, fault_name FROM fault_type ORDER BY fault_name ASC");
$fault_types_stmt->execute();
$fault_types = $fault_types_stmt->fetchAll(PDO::FETCH_ASSOC);

// The rest of your HTML and JS remains unchanged, as the issue is fixed in the backend logic above.
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">
<head>
    <title>Complaints Portal (Modern)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f8fafc; font-size: 15px; }
        .card { border-radius: 10px; box-shadow: 0 1px 7px #e1e4e8; }
        .card-header { font-weight: 500; font-size: 1rem; background: #f6f8fa; }
        .compact-list { margin-bottom: 0; font-size: 14px; }
        .compact-list li { padding-bottom: 4px; display: flex; align-items: center; }
        .btn-icon { padding: 0.12rem 0.42rem; font-size: 1.1em; }
        .contacts-emails-title { font-size: 1rem; margin-bottom: 0.3rem; color: #3b4a5a; }
        .form-control, .form-select { font-size: 14px; }
        .form-control-sm { padding: 2px 6px; }
        .card-body { padding: 1rem 1.1rem; }
        .toast-container { z-index: 9999; }
        @media (max-width: 650px) {
            .card-body { padding: 0.7rem 0.4rem; }
        }
    </style>
</head>
<body>
<div class="container py-3">
    <div class="d-flex align-items-center mb-2">
        <h5 class="mb-0" style="font-size:1.1rem;font-weight:600;">Complaints Portal (Modern)</h5>
        <button class="btn btn-outline-dark btn-sm ms-auto" id="toggle-dark"><i class="bi bi-moon"></i></button>
    </div>
    <form method="get" class="mb-3" id="searchForm">
        <div class="input-group input-group-sm">
            <input type="text" name="search_circuit" class="form-control" placeholder="Search Circuit ID or Organization" value="<?= htmlspecialchars($search_query) ?>">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
        </div>
    </form>
    <?php if ($circuit): ?>
        <div class="row g-3" id="mainContent" data-circuit-id="<?= htmlspecialchars($circuit_id) ?>">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-info-circle"></i> Circuit Details</div>
                    <table class="table table-borderless mb-0" style="font-size:14px;">
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
                                <div class="contacts-emails-title d-flex align-items-center">
                                    <i class="bi bi-telephone"></i> <span class="ms-1">Contacts</span>
                                    <button class="btn btn-outline-primary btn-sm btn-icon ms-auto" data-bs-toggle="modal" data-bs-target="#addContactModal"><i class="bi bi-plus"></i></button>
                                </div>
                                <ul class="compact-list list-unstyled" id="contactsList"></ul>
                            </div>
                            <div class="col-sm-6">
                                <div class="contacts-emails-title d-flex align-items-center">
                                    <i class="bi bi-envelope"></i> <span class="ms-1">Emails</span>
                                    <button class="btn btn-outline-primary btn-sm btn-icon ms-auto" data-bs-toggle="modal" data-bs-target="#addEmailModal"><i class="bi bi-plus"></i></button>
                                </div>
                                <ul class="compact-list list-unstyled" id="emailsList"></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white py-2"><i class="bi bi-flag"></i> Raise Complaint</div>
                    <div class="card-body py-2">
                        <form id="raiseComplaintForm">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="circuit_id" value="<?= htmlspecialchars($circuit_id) ?>">
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
                            <button class="btn btn-success btn-sm w-100" type="submit">
                                <i class="bi bi-flag"></i> Submit
                            </button>
                        </form>
                        <div id="openDocketAlert" class="alert alert-warning mt-2 d-none"></div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-secondary text-white py-2"><i class="bi bi-clock-history"></i> Complaint History</div>
                    <div class="card-body p-2">
                        <div style="max-height:280px;overflow:auto;">
                            <table class="table table-sm table-hover mb-0" style="font-size:13px;">
                                <thead>
                                    <tr>
                                        <th>Docket No</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Booking Time</th>
                                        <th>Last Update</th>
                                        <th>Closed Time</th>
                                        <th>Remarks</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="complaintsTable"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modals ... unchanged ... -->
        <!-- Toasts ... unchanged ... -->
    <?php elseif($search_query): ?>
        <div class="alert alert-danger mt-4">Circuit not found. Try again.</div>
    <?php endif; ?>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let circuitId = $('#mainContent').data('circuit-id') || '';
let csrfToken = '<?= $csrf_token ?>';

function showToast(msg, type='primary') {
    $('#liveToast').removeClass().addClass('toast align-items-center text-bg-'+type+' border-0');
    $('#toastMsg').text(msg);
    let toast = new bootstrap.Toast(document.getElementById('liveToast'));
    toast.show();
}

// Dark mode toggle
$('#toggle-dark').on('click', function() {
    let theme = document.documentElement.getAttribute('data-bs-theme');
    document.documentElement.setAttribute('data-bs-theme', theme === 'dark' ? 'auto' : 'dark');
});

// Fetch and render contacts/emails/complaints
function refreshData() {
    $.post('', {ajax:'fetch_data', circuit_id: circuitId, csrf_token: csrfToken}, function(data) {
        // Contacts
        let contactsHtml = '';
        if (data.contacts.length) {
            data.contacts.forEach(function(c) {
                contactsHtml += `<li>
                    <span>${c.contact_number}</span>
                    <button class="btn btn-outline-success btn-sm btn-icon ms-2 edit-contact" data-id="${c.id}" data-number="${c.contact_number}" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-outline-danger btn-sm btn-icon ms-1 delete-contact" data-id="${c.id}" title="Delete"><i class="bi bi-trash"></i></button>
                </li>`;
            });
        } else {
            contactsHtml = '<li class="text-muted">No contact numbers.</li>';
        }
        $('#contactsList').html(contactsHtml);

        // Emails
        let emailsHtml = '';
        if (data.emails.length) {
            data.emails.forEach(function(e) {
                emailsHtml += `<li>
                    <span>${e.ce_email_id}</span>
                    <button class="btn btn-outline-success btn-sm btn-icon ms-2 edit-email" data-id="${e.id}" data-email="${e.ce_email_id}" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-outline-danger btn-sm btn-icon ms-1 delete-email" data-id="${e.id}" title="Delete"><i class="bi bi-trash"></i></button>
                </li>`;
            });
        } else {
            emailsHtml = '<li class="text-muted">No emails.</li>';
        }
        $('#emailsList').html(emailsHtml);

        // Complaints
        let complaintsHtml = '';
        let hasOpen = false;
        data.complaints.forEach(function(c) {
            let statusBadge = c.incident_status === 'open' ? '<span class="badge bg-success">Open</span>' : '<span class="badge bg-secondary">Closed</span>';
            let action = c.incident_status === 'open'
                ? `<form class="closeComplaintForm d-flex" style="gap:2px;">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                        <input type="hidden" name="docket_no" value="${c.docket_no}">
                        <input type="text" name="close_remarks" class="form-control form-control-sm" style="width:110px;" placeholder="Closure remarks" required>
                        <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Close"><i class="bi bi-x"></i></button>
                   </form>`
                : '<span class="text-muted">-</span>';
            complaintsHtml += `<tr>
                <td>${c.docket_no}</td>
                <td>${c.fault_name || ''}</td>
                <td>${statusBadge}</td>
                <td>${c.docket_booking_time}</td>
                <td>${c.updated_time}</td>
                <td>${c.docket_closed_time || '-'}</td>
                <td style="max-width:180px;white-space:pre-wrap;">${c.remarks}</td>
                <td>${action}</td>
            </tr>`;
            if (c.incident_status === 'open') hasOpen = true;
        });
        $('#complaintsTable').html(complaintsHtml);

        // Open docket alert
        if (hasOpen) {
            $('#openDocketAlert').removeClass('d-none').text('There is already an open complaint for this circuit. Please close it before raising a new one.');
            $('#raiseComplaintForm button[type=submit]').prop('disabled', true);
        } else {
            $('#openDocketAlert').addClass('d-none');
            $('#raiseComplaintForm button[type=submit]').prop('disabled', false);
        }
    }, 'json');
}
if (circuitId) refreshData();

// Add Contact
$('#addContactForm').on('submit', function(e) {
    e.preventDefault();
    let number = $(this).find('input[name=number]').val();
    $.post('', {ajax:'contact', action:'add', circuit_id:circuitId, number:number, csrf_token:csrfToken}, function(data) {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) {
            $('#addContactModal').modal('hide');
            refreshData();
        }
    }, 'json');
});

// Edit Contact
$(document).on('click', '.edit-contact', function() {
    $('#editContactId').val($(this).data('id'));
    $('#editContactNumber').val($(this).data('number'));
    $('#editContactModal').modal('show');
});
$('#editContactForm').on('submit', function(e) {
    e.preventDefault();
    let id = $('#editContactId').val();
    let number = $('#editContactNumber').val();
    $.post('', {ajax:'contact', action:'edit', circuit_id:circuitId, id:id, number:number, csrf_token:csrfToken}, function(data) {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) {
            $('#editContactModal').modal('hide');
            refreshData();
        }
    }, 'json');
});

// Delete Contact
$(document).on('click', '.delete-contact', function() {
    if (!confirm('Delete this contact?')) return;
    let id = $(this).data('id');
    $.post('', {ajax:'contact', action:'delete', circuit_id:circuitId, id:id, csrf_token:csrfToken}, function(data) {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) refreshData();
    }, 'json');
});

// Add Email
$('#addEmailForm').on('submit', function(e) {
    e.preventDefault();
    let email = $(this).find('input[name=email]').val();
    $.post('', {ajax:'email', action:'add', circuit_id:circuitId, email:email, csrf_token:csrfToken}, function(data) {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) {
            $('#addEmailModal').modal('hide');
            refreshData();
        }
    }, 'json');
});

// Edit Email
$(document).on('click', '.edit-email', function() {
    $('#editEmailId').val($(this).data('id'));
    $('#editEmailAddr').val($(this).data('email'));
    $('#editEmailModal').modal('show');
});
$('#editEmailForm').on('submit', function(e) {
    e.preventDefault();
    let id = $('#editEmailId').val();
    let email = $('#editEmailAddr').val();
    $.post('', {ajax:'email', action:'edit', circuit_id:circuitId, id:id, email:email, csrf_token:csrfToken}, function(data) {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) {
            $('#editEmailModal').modal('hide');
            refreshData();
        }
    }, 'json');
});

// Delete Email
$(document).on('click', '.delete-email', function() {
    if (!confirm('Delete this email?')) return;
    let id = $(this).data('id');
    $.post('', {ajax:'email', action:'delete', circuit_id:circuitId, id:id, csrf_token:csrfToken}, function(data) {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) refreshData();
    }, 'json');
});

// Raise Complaint
$('#raiseComplaintForm').on('submit', function(e) {
    e.preventDefault();
    let form = $(this);
    let data = form.serializeArray();
    data.push({name:'ajax', value:'raise_complaint'});
    $.post('', $.param(data), function(resp) {
        showToast(resp.message, resp.success ? 'success' : 'danger');
        if (resp.success) {
            form[0].reset();
            refreshData();
        }
    }, 'json');
});

// Close Complaint
$(document).on('submit', '.closeComplaintForm', function(e) {
    e.preventDefault();
    let form = $(this);
    let data = form.serializeArray();
    data.push({name:'ajax', value:'close_complaint'});
    data.push({name:'circuit_id', value:circuitId});
    $.post('', $.param(data), function(resp) {
        showToast(resp.message, resp.success ? 'success' : 'danger');
        if (resp.success) refreshData();
    }, 'json');
});
</script>
</body>
</html>