<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_name('oss_portal');
session_start();
date_default_timezone_set('Asia/Kolkata');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=()');

$timeout_duration = 900;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];

require_once 'includes/db.php';

$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Whitelist sort options to prevent SQL injection
$allowed_sorts = [
    'docket_booking_time DESC',
    'docket_booking_time ASC'
];
$sort = $_GET['sort'] ?? 'docket_booking_time DESC';
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'docket_booking_time DESC';
}

$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = '1=1';
$params = [];
if ($status_filter) {
    $where .= ' AND c.incident_status = ?';
    $params[] = $status_filter;
}
if ($search) {
    $where .= ' AND (c.docket_no LIKE ? OR c.organization_name LIKE ? OR c.circuit_id LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints c WHERE $where");
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

$stmt = $pdo->prepare("
    SELECT 
        c.*,
        assigned.assigned_to,
        (
            SELECT MAX(updated_time)
            FROM complaint_history h
            WHERE h.rid = c.id
        ) AS last_update_time
    FROM complaints c
    LEFT JOIN (
        SELECT rid, updated_by AS assigned_to
        FROM complaint_history
        WHERE (rid, updated_time) IN (
            SELECT rid, MAX(updated_time)
            FROM complaint_history
            GROUP BY rid
        )
    ) AS assigned ON assigned.rid = c.id
    WHERE $where
    ORDER BY $sort
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_labels = ['Open'=>'success','Hold'=>'warning','Pending with Customer'=>'info','Closed'=>'secondary'];
$icon_map = ['Open'=>'bi-check-circle','Hold'=>'bi-pause-circle','Pending with Customer'=>'bi-hourglass-split','Closed'=>'bi-check2-square'];

// Optimized: Get all status counts in one query
$status_counts = [];
$q = $pdo->query("SELECT incident_status, COUNT(*) as cnt FROM complaints GROUP BY incident_status");
foreach ($q as $row) {
    $status_counts[$row['incident_status']] = $row['cnt'];
}
foreach (array_keys($status_labels) as $status) {
    if (!isset($status_counts[$status])) $status_counts[$status] = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Complaints Dashboard</title>
    <meta http-equiv="refresh" content="60">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Fira+Mono&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Segoe UI', Arial, sans-serif; font-size: 14px; background: #f6f8fa; color: #222; }
        .navbar { font-size: 15px; letter-spacing: 0.01em; }
        .summary-bar span { background: #fff; border-radius: 1.5em; box-shadow: 0 1px 4px rgba(0,0,0,0.04); padding: 0.3em 1.1em 0.3em 0.7em; margin-right: 0.5em; margin-bottom: 0.3em; }
        .summary-bar .badge { font-size: 12px; padding: 0.3em 0.7em; border-radius: 1em; font-weight: 500; }
        .table-compact th, .table-compact td { padding: 0.45rem 0.6rem !important; font-size: 13px; vertical-align: middle; }
        .table-compact th { background: #e9f0fa; color: #2a3b4d; font-weight: 600; border-bottom: 2px solid #b6c6e3; }
        .table-compact tbody tr:hover { background: #f0f6ff; box-shadow: 0 1px 4px rgba(0,0,0,0.03); }
        .badge-status { font-size: 12px; padding: 0.3em 0.7em; border-radius: 1em; font-weight: 500; letter-spacing: 0.01em; }
        .table-compact .bi { font-size: 1.1em; vertical-align: -0.1em; margin-right: 0.2em; color: #6c757d; }
        .table-compact .btn { font-size: 12px; padding: 0.2em 0.6em; border-radius: 0.3em; }
        .table-compact .btn-outline-info { border-color: #6ec1e4; color: #2196f3; }
        .table-compact .btn-outline-primary { border-color: #7b8cff; color: #3f51b5; }
        .table-compact .btn-outline-info:hover, .table-compact .btn-outline-primary:hover { background: #e3f2fd; }
        .table-compact .text-muted { color: #b0b8c1 !important; }
        .table-compact .timer { font-family: 'Fira Mono', 'Consolas', monospace; font-size: 12px; background: #f3f6fa; border-radius: 0.3em; padding: 0.1em 0.5em; color: #3f51b5; }
        .table-danger { background: #fff0f0 !important; }
        .table-warning { background: #fffbe6 !important; }
        .table-success { background: #f0fff4 !important; }
        @media (max-width: 768px) { .table-compact th, .table-compact td { font-size: 12px; } .table-compact .btn { font-size: 11px; } }
        .search-bar input, .search-bar select { font-size: 13px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm py-1">
    <div class="container-fluid px-1 py-0">
        <a class="navbar-brand" href="#"><i class="bi bi-alarm"></i> Complaints Portal</a>
        <div class="d-flex align-items-center ms-auto" style="font-size:12px;">
            <span class="text-light me-2"><i class="bi bi-person-circle"></i> <?=htmlspecialchars($username)?></span>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm py-0 px-1 me-1"><i class="bi bi-grid"></i> Dashboard</a>
            <a href="logout.php" class="btn btn-outline-light btn-sm py-0 px-1"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 py-3">
    <div class="summary-bar d-flex flex-wrap align-items-center gap-2 mb-2">
        <?php foreach($status_labels as $st=>$col): ?>
            <span class="d-inline-flex align-items-center border border-<?=$col?>">
                <i class="bi <?=$icon_map[$st]?> me-1 text-<?=$col?>" style="font-size:1.1em;"></i>
                <?= htmlspecialchars($st) ?>:
                <span class="badge bg-<?=$col?> ms-2"><?= $status_counts[$st] ?? 0 ?></span>
            </span>
        <?php endforeach; ?>
    </div>

    <ul class="nav nav-tabs mb-3" id="dashboardTabs">
      <li class="nav-item">
        <a class="nav-link active" id="all-tab" data-bs-toggle="tab" href="#all">All Complaints</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" id="circuit-tab" data-bs-toggle="tab" href="#circuit">Circuit Complaints</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" id="export-tab" data-bs-toggle="tab" href="#export">Export Complaints</a>
      </li>
    </ul>
    <div class="tab-content">
      <!-- All Complaints Tab -->
      <div class="tab-pane fade show active" id="all">
        <form method="GET" class="row g-2 mb-3 search-bar px-3 py-3 align-items-end" id="allComplaintsForm">
            <div class="col-md-4 col-12"><label class="form-label mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Docket, Org, Circuit ID" value="<?=htmlspecialchars($search)?>">
            </div>
            <div class="col-md-2 col-6"><label class="form-label mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach(array_keys($status_labels) as $st): ?>
                        <option value="<?=$st?>" <?= $status_filter==$st?'selected':''?>><?= htmlspecialchars($st)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-6"><label class="form-label mb-1">Sort By</label>
                <select name="sort" class="form-select form-select-sm">
                    <option value="docket_booking_time DESC" <?= $sort=='docket_booking_time DESC'?'selected':''?>>Newest First</option>
                    <option value="docket_booking_time ASC" <?= $sort=='docket_booking_time ASC'?'selected':''?>>Oldest First</option>
                </select>
            </div>
            <div class="col-md-2 col-6"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Filter</button></div>
            <div class="col-md-2 col-6"><a href="complaints_portal1.php" class="btn btn-success btn-sm w-100"><i class="bi bi-plus-lg"></i> Raise Complaint</a></div>
        </form>
        <div class="table-responsive">
            <table class="table table-compact table-bordered table-hover align-middle bg-white shadow-sm rounded">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Docket</th>
                        <th>Organization</th>
                        <th>Circuit ID</th>
                        <th>Bandwidth</th>
                        <th>Link Type</th>
                        <th>Status</th>
                        <th>Booking</th>
                        <th>Next Update</th>
                        <th>Elapsed</th>
                        <th>Actions</th>
                        <th>SLA (hrs)</th>
                        <th>Docket Closed Time</th>
                        <th>Raised By</th>
                        <th>Assigned To</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($complaints as $i=>$row): 
                    $status = $row['incident_status'] ?? '';
                    $badgeClass = 'bg-secondary';
                    if(strtolower($status)=='open') $badgeClass='bg-success';
                    elseif(strtolower($status)=='hold') $badgeClass='bg-warning text-dark';
                    elseif(strtolower($status)=='pending with customer') $badgeClass='bg-info text-dark';
                    elseif(strtolower($status)=='closed') $badgeClass='bg-secondary';
                    $last = $row['last_update_time'] ?: $row['docket_booking_time'];
                    $timerStart = strtotime($last)*1000;

                    // Row color logic
                    $colorClass = '';
                    $now = new DateTime();
                    $bookingTime = new DateTime($row['docket_booking_time']);
                    $lastUpdateTime = $row['last_update_time'] ? new DateTime($row['last_update_time']) : $bookingTime;
                    $status_lc = strtolower($row['incident_status'] ?? '');

                    if ($status_lc != 'closed') {
                        if (!empty($row['next_update_time'])) {
                            $nextUpdateTime = new DateTime($row['next_update_time']);
                            $diffMinutes = ($nextUpdateTime->getTimestamp() - $now->getTimestamp()) / 60;
                            if ($diffMinutes < 0) {
                                $colorClass = 'table-danger'; // overdue
                            } elseif ($diffMinutes <= 10) {
                                $colorClass = 'table-warning'; // due soon
                            } else {
                                $colorClass = 'table-success'; // enough time
                            }
                        } else {
                            $elapsedMinutes = ($now->getTimestamp() - $bookingTime->getTimestamp()) / 60;
                            if ($elapsedMinutes >= 15) {
                                $colorClass = 'table-danger'; // more than 15 mins
                            } elseif ($elapsedMinutes >= 5) {
                                $colorClass = 'table-warning'; // between 5 and 15
                            }
                            // else: no color (within 5 minutes)
                        }
                    }
                ?>
                <tr class="<?= $colorClass ?>">
                    <td><?= ($offset + $i + 1) ?></td>
                    <td><?= htmlspecialchars($row['docket_no']) ?></td>
                    <td><?= htmlspecialchars($row['organization_name']) ?></td>
                    <td><?= htmlspecialchars($row['circuit_id']) ?></td>
                    <td><?= htmlspecialchars($row['bandwidth']) ?></td>
                    <td><?= htmlspecialchars($row['link_type']) ?></td>
                    <td><span class="badge <?= $badgeClass ?> badge-status"><?= htmlspecialchars($status) ?></span></td>
                    <td><?= date('d-M-Y H:i', strtotime($row['docket_booking_time'])) ?></td>
                    <td>
                        <?= !empty($row['next_update_time']) 
                            ? date('d-M-Y H:i', strtotime($row['next_update_time'])) 
                            : '<span class="text-muted">N/A</span>' ?>
                    </td>
                    <td>
                        <?php if($status_lc!='closed'): ?>
                            <span id="timer<?=$i?>" class="timer">00:00</span>
                            <script>
                                document.addEventListener('DOMContentLoaded',function(){
                                    startTimer('timer<?=$i?>',<?=$timerStart?>);
                                });
                            </script>
                        <?php else: ?>
                            <?php
                            $booking = new DateTime($row['docket_booking_time']);
                            if (!empty($row['docket_closed_time'])) {
                                $closed = new DateTime($row['docket_closed_time']);
                            } elseif (!empty($row['last_update_time'])) {
                                $closed = new DateTime($row['last_update_time']);
                            } else {
                                $closed = null;
                            }
                            if ($closed) {
                                $interval = $booking->diff($closed);
                                if ($interval->days >= 1) {
                                    $resolutionTime = $interval->format('%a days %h:%I');
                                } else {
                                    $resolutionTime = $interval->format('%H:%I:%S');
                                }
                                echo '<span class="timer text-muted">' . $resolutionTime . '</span>';
                            } else {
                                echo '<span class="text-muted">N/A</span>';
                            }
                            ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-outline-info btn-sm" onclick="window.open('complaint_history.php?id=<?= $row['id'] ?>', '_blank', 'width=900,height=600,scrollbars=yes'); return false;" title="View History">
                            <i class="bi bi-clock-history"></i>
                        </button>
                        <?php if($status_lc!='closed'): ?>
                            <button class="btn btn-outline-primary btn-sm" onclick="openComplaintHandle('<?= $row['id'] ?>')" title="Handle Complaint"><i class="bi bi-pencil-square"></i></button>
                        <?php else: ?>
                            <span class="text-muted">Closed</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        if (!empty($row['docket_closed_time'])) {
                            $booking = new DateTime($row['docket_booking_time']);
                            $closed = new DateTime($row['docket_closed_time']);
                            $diffInSeconds = $closed->getTimestamp() - $booking->getTimestamp();
                            $diffInHours = round($diffInSeconds / 3600, 2);
                            echo $diffInHours . " hrs";
                        } else {
                            echo '<span class="text-muted">N/A</span>';
                        }
                        ?>
                    </td>
                    <td><?= !empty($row['docket_closed_time']) ? htmlspecialchars($row['docket_closed_time']) : '<span class="text-muted">N/A</span>' ?></td>
                    <td><?= htmlspecialchars($row['created_by'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($row['assigned_to'] ?? 'N/A') ?></td>
                </tr>
                <?php endforeach; 
                if(!$complaints): ?><tr><td colspan="15" class="text-center text-muted">No complaints found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($total_pages>1): ?>
        <nav><ul class="pagination justify-content-center mt-3">
          <?php for($p=1;$p<=$total_pages;$p++): ?>
            <li class="page-item <?= $p==$page?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$p]))?>"><?=$p?></a></li>
          <?php endfor;?>
        </ul></nav><?php endif;?>
      </div>
      <!-- Circuit Complaints Tab -->
      <div class="tab-pane fade" id="circuit">
        <?php include 'view_circuit_complaints.php'; ?>
      </div>
      <!-- Export Tab -->
      <div class="tab-pane fade" id="export">
        <div class="mb-3 mt-3">
            <h6>Export Options</h6>
            <form method="get" action="export_complaints.php" target="_blank" id="exportComplaintsForm">
                <div class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label class="form-label mb-0" for="export_status">Status</label>
                        <select name="status" id="export_status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <?php foreach(array_keys($status_labels) as $st): ?>
                                <option value="<?=$st?>"><?= htmlspecialchars($st)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-0" for="export_search">Search</label>
                        <input type="text" name="search" id="export_search" class="form-control form-control-sm" placeholder="Docket, Org, Circuit ID">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-download"></i> Export to CSV</button>
                    </div>
                </div>
            </form>
            <div class="mt-3 text-muted">
                Export includes complaint details, user info, and complaint history.
            </div>
        </div>
      </div>
    </div>
</div>

<!-- Complaint Modal -->
<div class="modal fade" id="complaintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title"><i class="bi bi-pencil-square"></i> Handle Complaint</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="complaintModalFrame" width="100%" height="600" frameborder="0"></iframe>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openComplaintHandle(id){
    document.getElementById('complaintModalFrame').src="complaints_portal_dashboard.php?id="+encodeURIComponent(id);
    new bootstrap.Modal(document.getElementById('complaintModal')).show();
}
function startTimer(elId, startMs){
    const el = document.getElementById(elId);
    function tick(){
        const now = Date.now();
        const elapsed = Math.floor((now - startMs)/1000);
        const hrs = Math.floor(elapsed/3600);
        const mins = Math.floor((elapsed%3600)/60);
        const secs = elapsed%60;
        el.textContent = (hrs>0?String(hrs).padStart(2,'0')+':':'')+String(mins).padStart(2,'0')+':'+String(secs).padStart(2,'0');
        el.classList.remove('text-warning','text-danger');
        if(elapsed>=900) el.classList.add('text-danger');
        else if(elapsed>=600) el.classList.add('text-warning');
    }
    tick(); setInterval(tick,1000);
}
// Tab state preservation logic
document.addEventListener('DOMContentLoaded', function() {
    var hash = window.location.hash;
    if (hash === '#circuit') {
        var tab = document.querySelector('a[href="#circuit"]');
        if (tab) {
            var tabInstance = new bootstrap.Tab(tab);
            tabInstance.show();
        }
    } else if (hash === '#export') {
        var tab = document.querySelector('a[href="#export"]');
        if (tab) {
            var tabInstance = new bootstrap.Tab(tab);
            tabInstance.show();
        }
    } else {
        var tab = document.querySelector('a[href="#all"]');
        if (tab) {
            var tabInstance = new bootstrap.Tab(tab);
            tabInstance.show();
        }
    }

    var allComplaintsForm = document.getElementById('allComplaintsForm');
    if (allComplaintsForm) {
        allComplaintsForm.addEventListener('submit', function(e) {
            window.location.hash = 'all';
        });
    }

    var circuitForm = document.getElementById('circuitForm');
    if (circuitForm) {
        circuitForm.addEventListener('submit', function(e) {
            window.location.hash = 'circuit';
        });
    }

    var exportComplaintsForm = document.getElementById('exportComplaintsForm');
    if (exportComplaintsForm) {
        exportComplaintsForm.addEventListener('submit', function(e) {
            window.location.hash = 'export';
        });
    }
});
</script>
</body>
</html>