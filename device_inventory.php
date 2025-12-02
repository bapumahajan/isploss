<?php
// device_inventory.php
session_name('oss_portal');
session_start();
require 'includes/config.php';

// --- Helper Functions ---
function make_placeholders($ids) {
    return [
        'placeholders' => implode(',', array_fill(0, count($ids), '?')),
        'types' => str_repeat('i', count($ids))
    ];
}

function export_csv($rs, $fields, $filename, $map_callback = null) {
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $out = fopen('php://output', 'w');
    fputcsv($out, $fields);
    $sr = 1;
    while ($r = $rs->fetch_assoc()) {
        if ($map_callback) {
            fputcsv($out, $map_callback($r, $sr++));
        } else {
            fputcsv($out, $r);
        }
    }
    fclose($out);
    exit;
}

// --- CSRF Protection ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Flash Message ---
$flash_message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// --- Handle Add Site ---
$site_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_site') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("CSRF mismatch.");
    $site_name = trim($_POST['site_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    if ($site_name !== '') {
        $stmt = $conn->prepare("INSERT INTO site_master (site_name, address) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("ss", $site_name, $address);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Site added successfully.";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenerate
                $stmt->close();
                header("Location: device_inventory.php");
                exit;
            } else {
                $site_error = "Failed to add site. " . $stmt->error;
                $stmt->close();
            }
        } else {
            $site_error = "Failed to prepare statement for site.";
        }
    } else {
        $site_error = "Site name is required.";
    }
}

// --- Handle Bulk Delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'bulk_delete' && !empty($_POST['selected_ids'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("CSRF mismatch.");
    $ids = array_map('intval', $_POST['selected_ids']);
    if ($ids) {
        $ph = make_placeholders($ids);
        $stmt = $conn->prepare("DELETE FROM device_inventory WHERE id IN ({$ph['placeholders']})");
        if ($stmt) {
            $stmt->bind_param($ph['types'], ...$ids);
            $stmt->execute();
            $stmt->close();
        }
    }
    $_SESSION['message'] = count($ids) . " device(s) deleted successfully.";
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenerate
    header("Location: device_inventory.php");
    exit;
}

// --- Handle Bulk Export ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'bulk_export' && !empty($_POST['selected_ids'])) {
    $ids = array_map('intval', $_POST['selected_ids']);
    if ($ids) {
        $ph = make_placeholders($ids);
        $stmt = $conn->prepare(
            "SELECT di.*, sm.site_name, dm.model_name FROM device_inventory di
             LEFT JOIN site_master sm ON di.site_id = sm.site_id
             LEFT JOIN device_models dm ON di.model_id = dm.model_id
             WHERE di.id IN ({$ph['placeholders']}) ORDER BY di.device_name ASC"
        );
        if ($stmt) {
            $stmt->bind_param($ph['types'], ...$ids);
            $stmt->execute();
            $rs = $stmt->get_result();
            export_csv($rs, [
                'Sr No', 'Site', 'Name', 'IP Address', 'Device Type', 'Device Serial No', 'Model','Location',
                'Address', 'Contact Person', 'Contact Number', 'Installation Date', 'Device Price', 'Remarks'
            ], "device_inventory_selected.csv", function($r, $sr) {
                return [
                    $sr,
                    $r['site_name'],
                    $r['device_name'],
                    $r['device_ip'],
                    $r['device_type'],
                    $r['device_serial_number'],
                    $r['model_name'],
                    $r['device_location'],
                    $r['address'],
                    $r['contact_person'],
                    $r['contact_number'],
                    $r['installation_date'],
                    $r['device_price'],
                    $r['remarks']
                ];
            });
            $stmt->close();
        }
    }
}

// --- CSV Export (all devices) ---
if (isset($_GET['export_csv']) && $_GET['export_csv'] == '1') {
    $rs = $conn->query(
        "SELECT di.*, sm.site_name, dm.model_name FROM device_inventory di
         LEFT JOIN site_master sm ON di.site_id = sm.site_id
         LEFT JOIN device_models dm ON di.model_id = dm.model_id
         ORDER BY di.device_name ASC"
    );
    export_csv($rs, [
        'Sr No', 'Site', 'Name', 'IP Address', 'Device Type', 'Device Serial No', 'Model', 'Make', 'Location',
        'Address', 'Contact Person', 'Contact Number', 'Installation Date', 'Device Price', 'Remarks'
    ], "device_inventory.csv", function($r, $sr) {
        return [
            $sr,
            $r['site_name'],
            $r['device_name'],
            $r['device_ip'],
            $r['device_type'],
            $r['device_serial_number'],
            $r['model_name'],
            $r['make'],
            $r['device_location'],
            $r['address'],
            $r['contact_person'],
            $r['contact_number'],
            $r['installation_date'],
            $r['device_price'],
            $r['remarks']
        ];
    });
}

// --- Fetch all models ---
$models = [];
$model_result = $conn->query("SELECT model_id, model_name, vendor, model_cost FROM device_models ORDER BY model_name ASC");
if ($model_result) {
    while ($row = $model_result->fetch_assoc()) {
        $models[] = $row;
    }
}

// --- Fetch all sites ---
$sites = [];
$site_result = $conn->query("SELECT site_id, site_name FROM site_master ORDER BY site_name ASC");
if ($site_result) {
    while ($row = $site_result->fetch_assoc()) {
        $sites[$row['site_id']] = $row['site_name'];
    }
}

// --- Calculate total and per-site cost ---
$cost_rs = $conn->query("SELECT di.site_id, sm.site_name, SUM(di.device_price) as site_cost FROM device_inventory di LEFT JOIN site_master sm ON di.site_id = sm.site_id GROUP BY di.site_id");
$total_cost = 0;
$site_costs = [];
if ($cost_rs) {
    while ($row = $cost_rs->fetch_assoc()) {
        $site_costs[$row['site_id']] = [
            'name' => $row['site_name'] ?? 'Unknown',
            'cost' => floatval($row['site_cost'])
        ];
        $total_cost += floatval($row['site_cost']);
    }
}

// --- Fetch all devices for DataTables ---
$rs = $conn->query("SELECT di.*, sm.site_name, dm.model_name FROM device_inventory di LEFT JOIN site_master sm ON di.site_id = sm.site_id LEFT JOIN device_models dm ON di.model_id = dm.model_id ORDER BY di.device_name ASC");
$devices = [];
if ($rs) {
    while ($row = $rs->fetch_assoc()) {
        $devices[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Device Inventory</title>
    <meta name="viewport" content="width=device-width">
    <meta name="description" content="View, manage, export device inventory.">
    <link rel="icon" href="favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e3ebf6 0%, #f7fafc 100%);
            font-size: 0.97rem;
            color: #223047;
        }
        .main-card {
            margin: 24px auto 24px auto;
            padding: 28px 22px 22px 22px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px #2222;
            max-width: 99vw;
        }
        h5, h6 {
            font-size: 1.12rem;
            color: #0b3868;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: .6em;
        }
        .compact-table {
            font-size: 0.89em;
            border-radius: 8px;
            overflow: hidden;
        }
        .compact-table th, .compact-table td {
            padding: 0.39rem 0.72rem;
            vertical-align: middle;
            white-space: nowrap;
        }
        .compact-table thead th {
            background: linear-gradient(90deg, #d4e3f9 0%, #f6fafd 100%);
            color: #1a283d;
            font-weight: 600;
            border-bottom: 2px solid #b8d3f3;
        }
        .compact-table tbody tr:hover {
            background: #e8f3ff;
            transition: background .18s;
        }
        .action-col {
            text-align: center;
            min-width: 60px;
        }
        .address-preview {
            max-width: 110px;
            display: inline-block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            font-size: 0.96em;
        }
        .table-summary {
            font-size: 0.95em;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 0.5em;
        }
        .table-summary th, .table-summary td {
            padding: 0.40rem 0.98rem;
        }
        .table-summary th {
            background: #e0f4f6;
            color: #17607a;
            font-weight: 700;
            border-bottom: 2px solid #abdee3;
        }
        .table-summary tr td {
            background: #fafdfe;
        }
        .table-summary .table-secondary td {
            background: #d4f7e8 !important;
            font-weight: bold;
            color: #257640;
        }
        .btn, .form-control, .form-select {
            font-size: 0.95em !important;
            padding: 0.23rem 0.64rem !important;
            border-radius: 7px !important;
        }
        .btn-sm, .form-control-sm, .form-select-sm {
            font-size: 0.93em !important;
            padding: 0.16rem 0.38rem !important;
        }
        .form-control, .form-select {
            height: 2.1em !important;
            margin-bottom: 2px;
        }
        /* Table head row for filters */
        .compact-table thead tr:nth-child(2) th {
            background: #f4f7fc;
            border-bottom: 1px solid #b8d3f3;
        }
        @media (max-width: 900px) {
            .main-card { padding: 10px 2px 8px 2px; }
            .compact-table th, .compact-table td, .table-summary th, .table-summary td { font-size: 0.92em; padding: 0.34rem 0.2rem;}
        }
    </style>
</head>
<body>
<div class="main-card">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2 align-items-center">
        <h5 class="mb-0"><i class="bi bi-hdd-network"></i> Device Inventory</h5>
        <div class="d-flex gap-1 align-items-center">
            <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="bi bi-grid"></i> Dashboard</a>
            <a href="add.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Sites details</a>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSiteModal">
                <i class="bi bi-plus"></i> Add Site
            </button>
            <a href="manage_models.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg"></i> Manage Models</a>
            <a href="site_inventory_cost.php" target="_blank" class="btn btn-outline-info btn-sm">
                <i class="bi bi-bar-chart"></i> View Cost by Site
            </a>
            <a href="?export_csv=1" class="btn btn-outline-success btn-sm">
                <i class="bi bi-download"></i> Export All CSV
            </a>
        </div>
    </div>

    <?php if ($flash_message): ?>
        <div class="alert alert-success py-2 mb-2"><?= htmlspecialchars($flash_message) ?></div>
    <?php endif; ?>
    <?php if ($site_error): ?>
        <div class="alert alert-danger py-2 mb-2"><?= htmlspecialchars($site_error) ?></div>
    <?php endif; ?>

    <!-- Bulk Actions Form -->
    <form id="bulkActionForm" method="post" action="device_inventory.php" class="mb-2 d-flex gap-2 align-items-center">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-danger btn-sm" onclick="return confirm('Delete selected devices?')">Delete Selected</button>
        <button type="submit" name="bulk_action" value="bulk_export" class="btn btn-success btn-sm">Export Selected</button>
    </form>

    <div class="table-responsive">
        <table id="deviceTable" class="table table-sm table-bordered table-striped compact-table w-100">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>#</th>
                    <th>Site</th>
                    <th>Name</th>
                    <th>IP Address</th>
                    <th>Device Type</th>
                    <th>Serial No</th>
                    <th>Model</th>
                    <th>Location</th>
                    <th>Address</th>
                    <th>Contact</th>
                    <th>Installation Date</th>
                    <th>Price (₹)</th>
                    <th>Remarks</th>
                    <th class="action-col">Actions</th>
                </tr>
                <tr>
                    <th></th>
                    <th></th>
                    <th><input type="text" placeholder="Site" class="form-control form-control-sm"></th>
                    <th><input type="text" placeholder="Name" class="form-control form-control-sm"></th>
                    <th><input type="text" placeholder="IP" class="form-control form-control-sm"></th>
                    <th>
                        <select class="form-select form-select-sm">
                            <option value="">Type</option>
                            <option>Router</option>
                            <option>Switch</option>
                            <option>OLT</option>
                        </select>
                    </th>
                    <th><input type="text" placeholder="Serial" class="form-control form-control-sm"></th>
                    <th><input type="text" placeholder="Model" class="form-control form-control-sm"></th>
                    <th><input type="text" placeholder="Location" class="form-control form-control-sm"></th>
                    <th><input type="text" placeholder="Address" class="form-control form-control-sm"></th>
                    <th><input type="text" placeholder="Contact" class="form-control form-control-sm"></th>
                    <th><input type="text" placeholder="Install Date" class="form-control form-control-sm"></th>
                    <th>
                        <input type="number" min="0" placeholder="Min" class="form-control form-control-sm price-min" style="width:46%;display:inline-block;">
                        <input type="number" min="0" placeholder="Max" class="form-control form-control-sm price-max" style="width:46%;display:inline-block;">
                    </th>
                    <th><input type="text" placeholder="Remarks" class="form-control form-control-sm"></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php $sr = 1; foreach ($devices as $row): ?>
                <tr>
                    <td><input type="checkbox" class="row-select" name="selected_ids[]" form="bulkActionForm" value="<?= $row['id'] ?>"></td>
                    <td><?= $sr++ ?></td>
                    <td><?= htmlspecialchars($row['site_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($row['device_name']) ?></td>
                    <td><?= htmlspecialchars($row['device_ip']) ?></td>
                    <td><?= htmlspecialchars($row['device_type']) ?></td>
                    <td><?= htmlspecialchars($row['device_serial_number']) ?></td>
                    <td><?= htmlspecialchars($row['model_name']) ?></td>
                    <td><?= htmlspecialchars($row['device_location']) ?></td>
                    <td>
                        <span class="address-preview"
                            tabindex="0"
                            data-bs-toggle="popover"
                            title="Full Address"
                            data-bs-trigger="focus"
                            data-bs-content="<?= htmlspecialchars($row['address']) ?>">
                            <?= htmlspecialchars(mb_strimwidth($row['address'], 0, 30, '…')) ?>
                        </span>
                    </td>
                    <td>
                        <?= htmlspecialchars($row['contact_person']) ?><br>
                        <small class="text-muted"><?= htmlspecialchars($row['contact_number']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($row['installation_date'] ? date('Y-m-d', strtotime($row['installation_date'])) : '') ?></td>
                    <td><?= number_format($row['device_price'], 2) ?></td>
                    <td><?= htmlspecialchars($row['remarks']) ?></td>
                    <td class="action-col">
                        <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning mb-1" title="Edit"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- Add Site Modal -->
<div class="modal fade" id="addSiteModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="device_inventory.php" class="modal-content">
      <input type="hidden" name="action" value="add_site">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <div class="modal-header"><h5 class="modal-title">Add Site</h5></div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Site Name</label>
          <input type="text" class="form-control form-control-sm" name="site_name" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Address</label>
          <input type="text" class="form-control form-control-sm" name="address">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success btn-sm">Add</button>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.forEach(el => new bootstrap.Popover(el, {html: true, placement: 'auto', trigger: 'focus'}));
});

$(document).ready(function() {
    // DataTables initialization
    var table = $('#deviceTable').DataTable({
        orderCellsTop: true,
        fixedHeader: true,
        responsive: true,
        pageLength: 10,
        order: [[1, 'asc']],
        columnDefs: [
            { orderable: false, targets: [0, 14] }
        ]
    });

    // Per-column filtering
    $('#deviceTable thead tr:eq(1) th').each(function(i) {
        $('input, select', this).on('keyup change', function() {
            if (i === 12) { // Price range column
                table.draw();
            } else {
                if (table.column(i).search() !== this.value) {
                    table.column(i).search(this.value).draw();
                }
            }
        });
    });

    // Custom price range filtering
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var min = parseFloat($('.price-min').val()) || 0;
        var max = parseFloat($('.price-max').val()) || Infinity;
        var price = parseFloat(data[13].replace(/,/g, '')) || 0;
        if ((isNaN(min) || price >= min) && (isNaN(max) || price <= max)) {
            return true;
        }
        return false;
    });

    // Bulk select
    $('#selectAll').on('click', function() {
        $('.row-select').prop('checked', this.checked);
    });
    $('#deviceTable').on('change', '.row-select', function() {
        $('#selectAll').prop('checked', $('.row-select:checked').length === $('.row-select').length);
    });

    // Prevent submitting bulk action with no selection
    $('#bulkActionForm').on('submit', function(e) {
        if ($('.row-select:checked').length == 0) {
            alert('Please select at least one device.');
            e.preventDefault();
            return false;
        }
    });
});
</script>
</body>
</html>