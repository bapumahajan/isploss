<?php
session_name('oss_portal');
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php';
date_default_timezone_set('Asia/Kolkata');

// Session fixation protection
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

function get_csrf_token() {
    if (empty($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || $_SESSION['csrf_token_time'] < time() - 1800) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}
$csrf_token = get_csrf_token();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
function log_error($message, $context = []) {
    error_log("[ComplaintsPortal] $message " . json_encode($context));
}
function validate_contact_number($number) {
    return preg_match('/^(?:\+?\d{1,4}[- ]?)?\d{7,14}$/', $number);
}
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}
function validate_remarks($remarks) {
    return strlen($remarks) >= 3 && strlen($remarks) <= 500;
}
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// --- AJAX HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.'], 403);
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();

    $action = $_POST['ajax'];
    $circuit_id = $_POST['circuit_id'] ?? '';

    try {
        switch ($action) {
            case 'fetch_data':
                $contacts = $pdo->prepare("SELECT id, contact_number FROM customer_contacts WHERE circuit_id=?");
                $contacts->execute([$circuit_id]);
                $emails = $pdo->prepare("SELECT id, ce_email_id FROM customer_emails WHERE circuit_id=?");
                $emails->execute([$circuit_id]);
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
                    'complaints' => $complaints->fetchAll(PDO::FETCH_ASSOC),
                    'csrf_token' => $_SESSION['csrf_token'],
                ]);
                break;
            case 'raise_complaint':
                $remarks = trim($_POST['remarks']);
                $fault_type_id = intval($_POST['fault_type_id']);
                if (!$circuit_id || !$fault_type_id || !validate_remarks($remarks)) {
                    json_response(['success' => false, 'message' => 'Please enter all required fields and ensure that remarks are at least 3 characters.']);
                }
                $open_stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE circuit_id=? AND incident_status='open'");
                $open_stmt->execute([$circuit_id]);
                if ($open_stmt->fetchColumn() > 0) {
                    json_response(['success' => false, 'message' => 'There is already an open complaint for this circuit.']);
                }
                $max_docket_stmt = $pdo->query("SELECT MAX(CAST(docket_no AS UNSIGNED)) AS max_docket FROM complaints WHERE LENGTH(docket_no) >= 5 AND docket_no REGEXP '^[0-9]+$'");
                $max_docket_row = $max_docket_stmt->fetch(PDO::FETCH_ASSOC);
                $next_docket_no = max(10000, intval($max_docket_row['max_docket']) + 1);
                $docket_no = strval($next_docket_no);
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
                json_response([
                    'success' => true, 
                    'docket_no' => $docket_no,
                    'message' => "Complaint raised. Docket No: $docket_no", 
                    'csrf_token' => $_SESSION['csrf_token']
                ]);
                break;
            case 'close_complaint':
                $docket_no = trim($_POST['docket_no']);
                $close_remarks = trim($_POST['close_remarks']);
                $delay_reason = trim($_POST['delay_reason'] ?? '');
                $now = date('Y-m-d H:i:s');
                $check = $pdo->prepare("SELECT incident_status, docket_closed_time, docket_booking_time FROM complaints WHERE docket_no=?");
                $check->execute([$docket_no]);
                $row = $check->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $booking_time = $row['docket_booking_time'];
                    if (empty($booking_time)) {
                        json_response(['success' => false, 'message' => "Booking time missing."], 400);
                    }
                    $booking_ts = strtotime($booking_time);
                    $now_ts = strtotime($now);
                    if ($now_ts < $booking_ts) {
                        json_response(['success' => false, 'message' => "Can't set closure time before docket booking."], 400);
                    }
                    $closure_time = $now;
                    $delay_hours = ($now_ts - $booking_ts) / 3600;
                    if ($delay_hours > 4 && empty($delay_reason)) {
                        json_response([
                            'success' => false, 
                            'delay_required' => true, 
                            'message' => "Delay reason required as closing is more than 4 hours after booking."
                        ], 400);
                    }
                    $full_remarks = $close_remarks;
                    if ($delay_hours > 4 && !empty($delay_reason)) {
                        $full_remarks .= "\nDelay Reason: " . $delay_reason;
                    }
                    if (strtolower($row['incident_status']) === 'open') {
                        $close = $pdo->prepare("UPDATE complaints 
                            SET incident_status='closed', 
                                updated_time=?, 
                                docket_closed_time=?, 
                                remarks=CONCAT(remarks, '\nClosed: ', ?) 
                            WHERE docket_no=?");
                        $close->execute([$closure_time, $closure_time, $full_remarks, $docket_no]);
                        if ($close->rowCount() == 0) {
                            json_response(['success' => false, 'message' => "Update failed: No matching row found."]);
                        }
                        json_response(['success' => true, 'message' => "Complaint $docket_no closed.", 'csrf_token' => $_SESSION['csrf_token']]);
                    } elseif (strtolower($row['incident_status']) === 'closed' && $row['docket_closed_time'] === null) {
                        $close = $pdo->prepare("UPDATE complaints 
                            SET docket_closed_time=?, 
                                remarks=CONCAT(remarks, '\nClosure time set: ', ?) 
                            WHERE docket_no=?");
                        $close->execute([$closure_time, $full_remarks, $docket_no]);
                        json_response(['success' => true, 'message' => "Closure time set for $docket_no.", 'csrf_token' => $_SESSION['csrf_token']]);
                    } else {
                        json_response(['success' => false, 'message' => "Unable to close: Already closed and closure time is set."]);
                    }
                } else {
                    json_response(['success' => false, 'message' => "Unable to close: Not found."]);
                }
                break;
        }
    } catch (PDOException $e) {
        log_error($e->getMessage(), $_POST);
        json_response(['success' => false, 'message' => 'Server error. Please try again.'], 500);
    }
}

// Search and selection logic
$search_query = $_GET['search_circuit'] ?? '';
$circuit_list = [];
$circuit = null;
$circuit_id = '';
$fault_types = [];
if ($search_query !== '') {
    $stmt = $pdo->prepare("
        SELECT cbi.circuit_id, cbi.organization_name, cbi.customer_address, nd.circuit_status, nd.bandwidth, nd.link_type
        FROM customer_basic_information cbi
        LEFT JOIN network_details nd ON cbi.circuit_id = nd.circuit_id
        WHERE cbi.circuit_id = ? OR cbi.organization_name LIKE ?
    ");
    $stmt->execute([$search_query, "%$search_query%"]);
    $circuit_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($circuit_list) === 1) {
        $circuit = $circuit_list[0];
        $circuit_id = $circuit['circuit_id'];
    } elseif (isset($_GET['selected_circuit'])) {
        foreach ($circuit_list as $row) {
            if ($row['circuit_id'] == $_GET['selected_circuit']) {
                $circuit = $row;
                $circuit_id = $row['circuit_id'];
                break;
            }
        }
    }
}

// Check for open complaint for alert message
$open_docket = null;
if ($circuit_id) {
    $open_stmt = $pdo->prepare("SELECT docket_no FROM complaints WHERE circuit_id=? AND incident_status='open' LIMIT 1");
    $open_stmt->execute([$circuit_id]);
    $open_docket = $open_stmt->fetchColumn();
}
$fault_types_stmt = $pdo->prepare("SELECT id, fault_name FROM fault_type ORDER BY fault_name ASC");
$fault_types_stmt->execute();
$fault_types = $fault_types_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Complaints Portal (Modern)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f8fafc; font-size: 13px; }
        .nav-tabs .nav-link {
            font-size: 15px;
            font-weight: 500;
            color: #1565c0;
        }
        .nav-tabs .nav-link.active {
            background: #f5faff;
            color: #1565c0;
            border-bottom: 2px solid #1565c0;
        }
        .nav-tabs .nav-link.disabled {
            color: #aaa !important;
            cursor: not-allowed;
            background: #eee !important;
        }
        .tab-content {
            background: #fff;
            border: 1px solid #e3e3e3;
            border-radius: 8px;
            padding: 16px 12px;
            margin-bottom: 18px;
        }
        .info-table td {
            padding: 3px 8px;
            font-size: 13px;
        }
        .info-table td:first-child { font-weight: 500; color: #444; width: 110px; }
        .form-label { font-weight: 500; font-size: 13px; margin-bottom: 2px;}
        .form-select, .form-control { font-size: 13px; border-radius: 5px; padding: 4px 7px;}
        .btn-primary, .btn-success {
            font-size: 13px;
            padding: 3px 16px;
            border-radius: 6px;
        }
        .alert { font-size: 13px; padding: 5px 10px; margin-bottom: 7px;}
        .complaints-table th, .complaints-table td {
            font-size: 13px;
            padding: 3px 6px;
        }
        .complaints-table th {
            background: #e8eaf6;
            font-weight: 500;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .complaints-table { width: 100%; }
        @media (max-width: 700px) {
            .tab-content { padding: 8px 4px; }
            .info-table td { font-size: 12px;}
            .complaints-table th, .complaints-table td { font-size: 12px;}
        }
        #raiseSuccessModal .modal-content {border-radius: 8px;}
        #raiseSuccessModal .modal-header {background: #e8f5e9;}
        #raiseSuccessModal .modal-title {color: #2e7d32;}
        #raiseSuccessModal .modal-body {font-size: 15px;}
    </style>
</head>
<body>
<div class="container py-3">
    <div class="d-flex align-items-center mb-2">
        <h5 class="mb-0" style="font-size:1.05rem;font-weight:600;">Complaints Portal (Modern)</h5>
        <button class="btn btn-outline-dark btn-sm ms-auto" id="toggle-dark"><i class="bi bi-moon"></i></button>
    </div>
    <form method="get" class="mb-2" id="searchForm">
        <div class="input-group input-group-sm">
            <input type="text" name="search_circuit" class="form-control" placeholder="Search Circuit ID or Organization" value="<?= e($search_query) ?>">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
        </div>
    </form>

    <?php if ($search_query !== '' && count($circuit_list) > 1): ?>
        <div class="alert alert-info">Multiple circuits/organizations found. Please select:</div>
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>Circuit ID</th>
                    <th>Organization</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Bandwidth</th>
                    <th>Link Type</th>
                    <th>Select</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($circuit_list as $row): ?>
                <tr>
                    <td><?= e($row['circuit_id']) ?></td>
                    <td><?= e($row['organization_name']) ?></td>
                    <td><?= e($row['customer_address']) ?></td>
                    <td><?= e($row['circuit_status']) ?></td>
                    <td><?= e($row['bandwidth']) ?></td>
                    <td><?= e($row['link_type']) ?></td>
                    <td>
                        <form method="get">
                            <input type="hidden" name="search_circuit" value="<?= e($search_query) ?>">
                            <input type="hidden" name="selected_circuit" value="<?= e($row['circuit_id']) ?>">
                            <button class="btn btn-primary btn-sm" type="submit">Select</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($circuit): ?>
        <?php if ($open_docket): ?>
            <div class="alert alert-warning mb-2" style="font-size:14px;">
                <strong>There is already an open complaint for this circuit.</strong>
                Docket No: <span class="text-primary fw-bold"><?= e($open_docket) ?></span>
            </div>
        <?php endif; ?>
        <ul class="nav nav-tabs mb-2" id="mainTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">
                    <i class="bi bi-person-badge"></i> Customer Info
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link<?php if ($open_docket) echo ' disabled'; ?>" id="raise-tab" data-bs-toggle="tab" data-bs-target="#raise" type="button" role="tab" aria-controls="raise" aria-selected="false" <?php if ($open_docket) echo 'tabindex="-1" aria-disabled="true"'; ?>>
                    <i class="bi bi-flag"></i> Raise Complaint
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                    <i class="bi bi-clock-history"></i> Complaint History
                </button>
            </li>
        </ul>
        <div class="tab-content" id="mainTabContent">
            <!-- Tab 1: Customer Info -->
            <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                <table class="info-table mb-0">
                    <tr><td>Organization:</td><td><?= e($circuit['organization_name']) ?></td></tr>
                    <tr><td>Address:</td><td><?= e($circuit['customer_address']) ?></td></tr>
                    <tr><td>Circuit ID:</td><td><?= e($circuit['circuit_id']) ?></td></tr>
                    <tr><td>Status:</td><td><?= e($circuit['circuit_status']) ?></td></tr>
                    <tr><td>Bandwidth:</td><td><?= e($circuit['bandwidth']) ?></td></tr>
                    <tr><td>Link Type:</td><td><?= e($circuit['link_type']) ?></td></tr>
                </table>
            </div>
            <!-- Tab 2: Raise Complaint -->
            <div class="tab-pane fade" id="raise" role="tabpanel" aria-labelledby="raise-tab">
                <?php if ($open_docket): ?>
                    <div class="alert alert-warning mt-2">
                        You cannot raise a new complaint while an existing one is open.<br>
                        Please close Docket No: <span class="text-primary fw-bold"><?= e($open_docket) ?></span> first.
                    </div>
                <?php else: ?>
                <form id="raiseComplaintForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="circuit_id" value="<?= e($circuit_id) ?>">
                    <label for="fault_type_id" class="form-label">Complaint Type</label>
                    <select class="form-select form-select-sm mb-1" name="fault_type_id" id="fault_type_id" required>
                        <option value="">Select Type</option>
                        <?php foreach ($fault_types as $type): ?>
                            <option value="<?= $type['id'] ?>"><?= e($type['fault_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea class="form-control form-control-sm mb-1" name="remarks" id="remarks" rows="2" required></textarea>
                    <button class="btn btn-success w-100 mt-1" type="submit">
                        <i class="bi bi-flag"></i> Submit
                    </button>
                </form>
                <div id="openDocketAlert" class="alert alert-warning mt-2 d-none"></div>
                <!-- Success Modal for complaint raised -->
                <div class="modal fade" id="raiseSuccessModal" tabindex="-1" aria-labelledby="raiseSuccessModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="raiseSuccessModalLabel"><i class="bi bi-check-circle"></i> Complaint Raised Successfully!</h5>
                      </div>
                      <div class="modal-body" id="raiseSuccessModalBody">
                        <!-- Message goes here -->
                      </div>
                    </div>
                  </div>
                </div>
                <?php endif; ?>
            </div>
            <!-- Tab 3: Complaint History -->
            <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                <div style="max-height:220px;overflow:auto;">
                    <table class="table table-sm table-hover complaints-table mb-0">
                        <thead>
                            <tr>
                                <th>Docket No</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Booking Time</th>
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
    <?php elseif($search_query && count($circuit_list) === 0): ?>
        <div class="alert alert-danger mt-2">No circuits found. Try again.</div>
    <?php endif; ?>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateCsrfToken(newToken) {
    window.csrfToken = newToken;
    $('input[name=csrf_token]').val(newToken);
}

let circuitId = '<?= e($circuit_id) ?>';
let csrfToken = '<?= $csrf_token ?>';

$('#raiseComplaintForm').on('submit', function(e) {
    e.preventDefault();
    let form = $(this);
    var remarks = form.find('[name=remarks]').val();
    if (remarks.length < 3) {
        $('#openDocketAlert').removeClass('d-none').addClass('alert-warning').text('Remarks must be at least 3 characters.');
        return;
    }
    let data = form.serializeArray();
    data.push({name:'ajax', value:'raise_complaint'});
    $.post('', $.param(data), function(resp) {
        if (resp.success) {
            form[0].reset();
            $('#openDocketAlert').addClass('d-none');
            // Show success modal with message and docket no
            $('#raiseSuccessModalBody').html(
                `<div>Your complaint has been registered.<br>
                <strong>Docket Number: <span style="color:#1976d2;">${resp.docket_no}</span></strong></div>
                <div class="mt-2 text-success">You will be redirected to the complaints dashboard in 5 seconds.</div>`
            );
            var modal = new bootstrap.Modal(document.getElementById('raiseSuccessModal'));
            modal.show();
            setTimeout(function() {
                window.location.href = "complaints_dashboard.php";
            }, 5000);
            $('#raiseComplaintForm button[type=submit]').prop('disabled', true);
        } else {
            let msg = resp.message;
            if (msg === 'Invalid input.' || msg.indexOf('required fields') !== -1) {
                msg = 'Please enter all required fields and ensure that remarks are at least 3 characters.';
            }
            $('#openDocketAlert').removeClass('d-none').addClass('alert-warning').text(msg);
        }
        if (resp.csrf_token) updateCsrfToken(resp.csrf_token);
    }, 'json');
});

function refreshData() {
    $.post('', {ajax:'fetch_data', circuit_id: circuitId, csrf_token: csrfToken}, function(data) {
        let complaintsHtml = '';
        data.complaints.forEach(function(c) {
            let statusBadge = c.incident_status === 'open' ? '<span class="badge bg-success">Open</span>' : '<span class="badge bg-secondary">Closed</span>';
            let action = c.incident_status === 'open'
                ? `<form class="closeComplaintForm d-flex flex-wrap align-items-center" style="gap:2px;">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                        <input type="hidden" name="docket_no" value="${c.docket_no}">
                        <input type="text" name="close_remarks" class="form-control form-control-sm" style="width:80px;" placeholder="Remarks" required>
                        <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Close"><i class="bi bi-x"></i></button>
                   </form>`
                : '<span class="text-muted">-</span>';
            complaintsHtml += `<tr>
                <td>${c.docket_no}</td>
                <td>${c.fault_name || ''}</td>
                <td>${statusBadge}</td>
                <td>${c.docket_booking_time}</td>
                <td>${c.docket_closed_time || '-'}</td>
                <td style="max-width:120px;white-space:pre-wrap;">${c.remarks}</td>
                <td>${action}</td>
            </tr>`;
        });
        $('#complaintsTable').html(complaintsHtml);

        if (data.csrf_token) updateCsrfToken(data.csrf_token);
    }, 'json');
}
if (circuitId) refreshData();

$(document).on('submit', '.closeComplaintForm', function(e) {
    e.preventDefault();
    let form = $(this);
    let data = form.serializeArray();
    data.push({name:'ajax', value:'close_complaint'});
    data.push({name:'circuit_id', value:circuitId});
    $.post('', $.param(data), function(resp) {
        if (resp.success) {
            refreshData();
            location.reload(); // Refresh to enable complaint form after closing
        } else if (resp.delay_required) {
            if (!form.find('[name=delay_reason]').length) {
                $('<input type="text" name="delay_reason" class="form-control form-control-sm mt-1" style="width:80px;" placeholder="Delay Reason">')
                    .insertBefore(form.find('button[type=submit]'));
            }
            alert(resp.message);
        } else {
            alert(resp.message);
        }
        if (resp.csrf_token) updateCsrfToken(resp.csrf_token);
    }, 'json');
});

$('#toggle-dark').on('click', function() {
    let theme = document.documentElement.getAttribute('data-bs-theme');
    document.documentElement.setAttribute('data-bs-theme', theme === 'dark' ? 'auto' : 'dark');
});
</script>
</body>
</html>