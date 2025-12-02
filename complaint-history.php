<?php
// --- Database Connection (update with your credentials) ---
$host = 'localhost';
$db   = 'customer_management';
$user = 'root';
$pass = 'Bapu@1982';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo "Database connection failed: " . htmlspecialchars($e->getMessage());
    exit;
}

// --- Fetch complaint history ---
$sql = "SELECT * FROM complaint_history ORDER BY id ASC";
$stmt = $pdo->query($sql);
$complaints = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complaint History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .complaint-card {
            min-width: 260px;
            max-width: 390px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-radius: 12px;
            transition: box-shadow .2s;
        }
        .complaint-card:hover {
            box-shadow: 0 8px 32px rgba(0,0,0,0.13);
        }
        .badge-status {
            font-size: 0.95em;
            padding: 0.4em 0.85em;
        }
        .badge-timer {
            font-size: 0.82em;
            margin-left: 0.5em;
            padding: 0.3em 0.7em;
            border-radius: 0.8em;
        }
        .complaint-title {
            font-weight: 700;
            font-size: 1.08em;
        }
        .complaint-desc, .complaint-remarks {
            color: #555;
            font-size: 0.97em;
            margin-bottom: 0.4em;
        }
        .small-label {
            font-size: 0.89em;
            color: #888;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4">Complaint History</h2>
    <div class="row g-3">
        <?php 
        $now = time();
        foreach($complaints as $row):
            // Status badge logic
            $status = strtolower($row['incident_status'] ?? '');
            $badgeClass = 'bg-secondary';
            if($status=='open') $badgeClass='bg-success';
            elseif($status=='hold') $badgeClass='bg-warning text-dark';
            elseif($status=='pending with customer') $badgeClass='bg-info text-dark';
            elseif($status=='closed') $badgeClass='bg-secondary';

            // Timer/priority logic
            $updated_time = $row['updated_time'] ?? '';
            $createdAt = strtotime($updated_time);
            $nextUpdateTime = !empty($row['next_update_time']) ? strtotime($row['next_update_time']) : null;

            $timerBadge = '';
            $timerText = '';
            $timerClass = 'bg-secondary';

            if ($nextUpdateTime) {
                $secondsLeft = $nextUpdateTime - $now;
                if ($secondsLeft > 600) { // More than 10 minutes to go
                    $timerClass = 'bg-success';
                    $timerText = 'Next update in ' . ceil($secondsLeft/60) . ' min';
                } elseif ($secondsLeft > 0) { // Less than 10 minutes
                    $timerClass = 'bg-warning text-dark';
                    $timerText = 'Next update soon';
                } else { // Past the time
                    $timerClass = 'bg-danger';
                    $timerText = 'Update overdue';
                }
            } else {
                // No next update time: use creation/last update times
                $age = $now - $createdAt;
                if ($age < 300) { // <5 min
                    $timerClass = 'bg-secondary';
                    $timerText = 'New';
                } elseif ($age < 900) { // <15 min
                    $timerClass = 'bg-warning text-dark';
                    $timerText = 'Pending';
                } else { // >15 min
                    $timerClass = 'bg-danger';
                    $timerText = 'Attention';
                }
            }
            $timerBadge = "<span class=\"badge badge-timer $timerClass\">$timerText</span>";
        ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <div class="complaint-card bg-white p-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="complaint-title">
                        <?= htmlspecialchars($row['incident_status']) ?>
                        <?php if ($row['remarks']): ?>
                            <span class="text-muted" style="font-weight:400;">&mdash; <?= htmlspecialchars($row['remarks']) ?></span>
                        <?php endif; ?>
                    </div>
                    <span>
                        <span class="badge badge-status <?= $badgeClass ?>">
                            <?= ucfirst($row['incident_status']) ?>
                        </span>
                        <?= $timerBadge ?>
                    </span>
                </div>
                <?php if ($row['remarks']): ?>
                <div class="complaint-remarks mb-2"><?= htmlspecialchars($row['remarks']) ?></div>
                <?php endif; ?>
                <?php if ($row['customer_communications_call']): ?>
                <div class="small-label mb-1"><strong>Comm:</strong> <?= htmlspecialchars($row['customer_communications_call']) ?></div>
                <?php endif; ?>
                <?php if ($row['alarm_status']): ?>
                <div class="small-label mb-1"><strong>Alarm:</strong> <?= htmlspecialchars($row['alarm_status']) ?></div>
                <?php endif; ?>
                <?php if ($row['updated_by']): ?>
                <div class="small-label mb-1"><strong>By:</strong> <?= htmlspecialchars($row['updated_by']) ?></div>
                <?php endif; ?>
                <?php if ($row['Fault']): ?>
                <div class="small-label mb-1"><strong>Fault:</strong> <?= htmlspecialchars($row['Fault']) ?></div>
                <?php endif; ?>
                <?php if ($row['RFO']): ?>
                <div class="small-label mb-1"><strong>RFO:</strong> <?= htmlspecialchars($row['RFO']) ?></div>
                <?php endif; ?>
                <div class="small-label"><strong>Updated:</strong> <?= date('d M Y, H:i', strtotime($row['updated_time'])) ?></div>
                <?php if ($row['next_update_time']): ?>
                <div class="small-label"><strong>Next Update:</strong> <?= date('d M Y, H:i', strtotime($row['next_update_time'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if(empty($complaints)): ?>
      <div class="alert alert-info mt-4">No complaints found.</div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>