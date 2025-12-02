<?php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'fault_type') {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    $action = $_POST['action'];
    if ($action === 'add') {
        $fault_name = trim($_POST['fault_name']);
        if ($fault_name === '') {
            echo json_encode(['success' => false, 'message' => 'Type name required.']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO fault_type (fault_name) VALUES (?)");
        try {
            $stmt->execute([$fault_name]);
            echo json_encode(['success' => true, 'message' => 'Type added.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Type already exists.']);
        }
        exit;
    }
    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $fault_name = trim($_POST['fault_name']);
        if ($fault_name === '') {
            echo json_encode(['success' => false, 'message' => 'Type name required.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE fault_type SET fault_name=? WHERE id=?");
        try {
            $stmt->execute([$fault_name, $id]);
            echo json_encode(['success' => true, 'message' => 'Type updated.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Type already exists.']);
        }
        exit;
    }
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM fault_type WHERE id=?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Type deleted.']);
        exit;
    }
    if ($action === 'fetch') {
        $stmt = $pdo->query("SELECT id, fault_name FROM fault_type ORDER BY fault_name ASC");
        echo json_encode(['success' => true, 'types' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
}

// Fetch types for initial page load
$stmt = $pdo->query("SELECT id, fault_name FROM fault_type ORDER BY fault_name ASC");
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Complaint Types Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 & jQuery -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-size:15px; }
        .table td, .table th { vertical-align: middle; }
        .edit-row input[type="text"] { width: 100%; }
        .edit-row { background: #fffbe7; }
        .toast-container { z-index: 11000; }
    </style>
</head>
<body>
<div class="container py-4">
    <h3 class="mb-3">Complaint Types</h3>
    <div class="card">
        <div class="card-body">
            <form class="row g-2 align-items-center mb-3" id="addTypeForm">
                <div class="col-sm-8">
                    <input type="text" class="form-control" name="fault_name" id="addTypeInput" placeholder="Add new type" required>
                </div>
                <div class="col-sm-4">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-plus-circle"></i> Add Type
                    </button>
                </div>
            </form>
            <table class="table table-sm table-hover" id="typeTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>Type Name</th>
                        <th style="width:110px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($types as $i => $row): ?>
                    <tr data-id="<?= $row['id'] ?>">
                        <td><?= $i+1 ?></td>
                        <td class="type-name"><?= htmlspecialchars($row['fault_name']) ?></td>
                        <td>
                            <button class="btn btn-outline-primary btn-sm edit-btn" type="button"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-outline-danger btn-sm delete-btn" type="button"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Toast -->
    <div class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3">
        <div id="liveToast" class="toast align-items-center text-bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMsg">Message</div>
                <button type="button" class="btn-close btn-close-white ms-auto me-2" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let csrfToken = '<?= $csrf_token ?>';

// Toast utility
function showToast(msg, type='primary') {
    $('#liveToast').removeClass().addClass('toast align-items-center text-bg-'+type+' border-0');
    $('#toastMsg').text(msg);
    let toast = new bootstrap.Toast(document.getElementById('liveToast'));
    toast.show();
}

// Add Type
$('#addTypeForm').on('submit', function(e) {
    e.preventDefault();
    let faultName = $('#addTypeInput').val().trim();
    if (!faultName) return;
    $.post('', {ajax:'fault_type', action:'add', fault_name:faultName, csrf_token:csrfToken}, function(data) {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) {
            $('#addTypeInput').val('');
            refreshTypes();
        }
    }, 'json');
});

// Edit Type
$(document).on('click', '.edit-btn', function() {
    let row = $(this).closest('tr');
    let id = row.data('id');
    let name = row.find('.type-name').text();
    if (row.hasClass('edit-row')) return;
    // Replace cell with input
    row.addClass('edit-row');
    row.find('.type-name').html('<input type="text" class="form-control form-control-sm type-edit-input" value="'+name.replace(/"/g, '&quot;')+'">');
    row.find('.edit-btn').hide();
    row.find('.delete-btn').hide();
    row.find('td:last').append(
        '<button class="btn btn-success btn-sm save-edit-btn me-1" type="button"><i class="bi bi-check"></i></button>' +
        '<button class="btn btn-secondary btn-sm cancel-edit-btn" type="button"><i class="bi bi-x"></i></button>'
    );
    row.find('.type-edit-input').focus();
});

// Save Edit
$(document).on('click', '.save-edit-btn', function() {
    let row = $(this).closest('tr');
    let id = row.data('id');
    let newName = row.find('.type-edit-input').val().trim();
    if (!newName) {
        showToast('Type name required.', 'danger');
        return;
    }
    $.post('', {ajax:'fault_type', action:'edit', id:id, fault_name:newName, csrf_token:csrfToken}, function(data) {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) refreshTypes();
    }, 'json');
});

// Cancel Edit
$(document).on('click', '.cancel-edit-btn', function() {
    refreshTypes();
});

// Delete Type
$(document).on('click', '.delete-btn', function() {
    let row = $(this).closest('tr');
    let id = row.data('id');
    let name = row.find('.type-name').text();
    if (!confirm('Delete "'+name+'" permanently?')) return;
    $.post('', {ajax:'fault_type', action:'delete', id:id, csrf_token:csrfToken}, function(data) {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) refreshTypes();
    }, 'json');
});

// Refresh table after change
function refreshTypes() {
    $.post('', {ajax:'fault_type', action:'fetch', csrf_token:csrfToken}, function(data) {
        let html = '';
        if (data.success && data.types.length) {
            data.types.forEach(function(t, i) {
                html += `<tr data-id="${t.id}">
                    <td>${i+1}</td>
                    <td class="type-name">${t.fault_name}</td>
                    <td>
                        <button class="btn btn-outline-primary btn-sm edit-btn" type="button"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-danger btn-sm delete-btn" type="button"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`;
            });
        } else {
            html = '<tr><td colspan="3" class="text-muted">No types found.</td></tr>';
        }
        $('#typeTable tbody').html(html);
    }, 'json');
}
</script>
</body>
</html>