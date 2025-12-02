<?php
// activity_log.php

session_name('oss_portal');
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';  // PDO connection

// Validate date helper
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Pagination
$limit = max(1, (int)($_GET['limit'] ?? 25));
$offset = max(0, (int)($_GET['offset'] ?? 0));

// Filters
$filter_user = trim($_GET['user'] ?? '');
$filter_action = trim($_GET['action'] ?? '');
$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date = trim($_GET['end_date'] ?? '');

if ($filter_start_date && !validateDate($filter_start_date)) $filter_start_date = '';
if ($filter_end_date && !validateDate($filter_end_date)) $filter_end_date = '';

// Build WHERE clauses
$whereParts = [];
$params = [];

if ($filter_user !== '') {
    $whereParts[] = "user LIKE ?";
    $params[] = "%$filter_user%";
}

if ($filter_action !== '' && strtolower($filter_action) !== 'all') {
    $whereParts[] = "action = ?";
    $params[] = $filter_action;
}

if ($filter_start_date !== '') {
    $whereParts[] = "DATE(created_at) >= ?";
    $params[] = $filter_start_date;
}

if ($filter_end_date !== '') {
    $whereParts[] = "DATE(created_at) <= ?";
    $params[] = $filter_end_date;
}

$whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

try {
    // CSV Export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $sql = "SELECT user, action, table_name, record_id, description, ip_address, created_at
                FROM audit_log
                $whereSql
                ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="activity_log.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['User','Action','Table','Record ID','Description','IP Address','Timestamp']);
        foreach ($rows as $row) {
            fputcsv($output, array_map(fn($v) => $v ?? 'N/A', $row));
        }
        fclose($output);
        exit;
    }

    // Total records for pagination
    $countSql = "SELECT COUNT(*) FROM audit_log $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();

    // Fetch logs with pagination
    $sql = "
        SELECT user, action, table_name, record_id, description, ip_address, created_at
        FROM audit_log
        $whereSql
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$limit, $offset]));
    } catch (PDOException $e) {
        // fallback for drivers that don't support bound LIMIT/OFFSET
        $sqlFallback = "
            SELECT user, action, table_name, record_id, description, ip_address, created_at
            FROM audit_log
            $whereSql
            ORDER BY created_at DESC
            LIMIT $limit OFFSET $offset
        ";
        $stmt = $pdo->prepare($sqlFallback);
        $stmt->execute($params);
    }

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $currentPage = floor($offset / $limit) + 1;
    $totalPages = max(1, ceil($totalRecords / $limit));

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// Helper to build URL preserving filters
function buildUrl($paramsOverride = []) {
    $params = array_merge($_GET, $paramsOverride);
    foreach ($params as $k => $v) if ($v === '') unset($params[$k]);
    return htmlspecialchars($_SERVER['PHP_SELF']) . '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Activity Log</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h3 class="mb-3">Activity Log</h3>

    <form method="get" class="row g-3 mb-3 align-items-end">
        <div class="col-md-2">
            <label for="user" class="form-label">User</label>
            <input type="text" id="user" name="user" class="form-control" value="<?= htmlspecialchars($filter_user) ?>" placeholder="Search user">
        </div>
        <div class="col-md-2">
            <label for="action" class="form-label">Action</label>
            <select id="action" name="action" class="form-select">
                <option value="all" <?= strtolower($filter_action) === 'all' || $filter_action === '' ? 'selected' : '' ?>>All</option>
                <option value="edit" <?= strtolower($filter_action) === 'edit' ? 'selected' : '' ?>>Edit</option>
                <option value="delete" <?= strtolower($filter_action) === 'delete' ? 'selected' : '' ?>>Delete</option>
                <option value="login" <?= strtolower($filter_action) === 'login' ? 'selected' : '' ?>>Login</option>
                <option value="login_failed" <?= strtolower($filter_action) === 'login_failed' ? 'selected' : '' ?>>Login Failed</option>
                <option value="session_timeout" <?= strtolower($filter_action) === 'session_timeout' ? 'selected' : '' ?>>Session Timeout</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="start_date" class="form-label">Start Date</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($filter_start_date) ?>">
        </div>
        <div class="col-md-2">
            <label for="end_date" class="form-label">End Date</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($filter_end_date) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
        <div class="col-md-2">
            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary w-100">Reset</a>
        </div>
        <div class="col-md-2">
            <a href="<?= buildUrl(['export'=>'csv']) ?>" class="btn btn-success w-100">Export CSV</a>
        </div>
    </form>

    <div class="mb-2">Showing <?= count($logs) ?> of <?= $totalRecords ?> logs</div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered table-sm">
            <thead class="table-light text-center align-middle">
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Table</th>
                    <th>Record ID</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs): ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['user']) ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td><?= htmlspecialchars($log['table_name']) ?></td>
                    <td><?= htmlspecialchars($log['record_id']) ?></td>
                    <td><?= nl2br(htmlspecialchars($log['description'])) ?></td>
                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                    <td><?= htmlspecialchars($log['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center">No activity logs found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildUrl(['offset'=>max(0,$offset-$limit),'limit'=>$limit]) ?>">Previous</a>
            </li>
            <li class="page-item disabled">
                <span class="page-link">Page <?= $currentPage ?> of <?= $totalPages ?></span>
            </li>
            <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildUrl(['offset'=>$offset+$limit,'limit'=>$limit]) ?>">Next</a>
            </li>
        </ul>
    </nav>

    <div class="text-center mt-3">
        <a href="dashboard.php" class="btn btn-danger">Back to Dashboard</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
