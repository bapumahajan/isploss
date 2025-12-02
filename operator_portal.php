<?php
//Operator_portal.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
session_name('oss_portal');
session_start();

// Security headers
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=()');
}
setSecurityHeaders();

// Session timeout: 15 minutes
$timeout_duration = 900;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Authentication check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'user';

// Get operator tables for view_third_party_details links
$operator_tables = $pdo->query(
    "SELECT operator_tables.table_name, operators.name AS operator_name
     FROM operator_tables
     JOIN operators ON operator_tables.operator_id = operators.id
     ORDER BY operators.name, operator_tables.table_name"
)->fetchAll(PDO::FETCH_ASSOC);

// Define portal cards for grid
$portal_cards = [
    [
        'title'    => 'Add Operator',
        'icon'     => 'bi-person-plus-fill',
        'desc'     => 'Register a new third-party operator.',
        'link'     => 'operator_admin_portal.php?tab=operator',
        'btn'      => 'btn-success',
        'btn_text' => 'Add Operator',
    ],
    [
        'title'    => 'Add Operator Table',
        'icon'     => 'bi-table',
        'desc'     => 'Create a custom table for an operator (with circuit_id).',
        'link'     => 'operator_admin_portal.php?tab=tables',
        'btn'      => 'btn-primary',
        'btn_text' => 'Add Table',
    ],
    [
        'title'    => 'Manage Table Fields',
        'icon'     => 'bi-columns-gap',
        'desc'     => 'Add or manage fields for operator tables.',
        'link'     => 'operator_admin_portal.php?tab=fields',
        'btn'      => 'btn-warning',
        'btn_text' => 'Manage Fields',
    ],
	 [
        'title'    => 'Add customer',
        'icon'     => 'bi-plug',
        'desc'     => 'Add Customer in ISPL main database to generate circuit ID.',
        'link'     => 'customer_and_add-nni_detail.php',
        'btn'      => 'btn-success',
        'btn_text' => 'Add Operator customers',
    ],
	 [
        'title'    => 'View Customer dashboard',
        'icon'     => 'bi-plug',
        'desc'     => 'Add Customer in ISPL main database to generate circuit ID.',
        'link'     => 'view_customer_dashboard.php',
        'btn'      => 'btn-success',
        'btn_text' => 'Customer_dashboard',
    ],
	
    [
        'title'    => 'Add NNI Partner Details (by Circuit)',
        'icon'     => 'bi-plug',
        'desc'     => 'Add details for an operator by selecting a circuit ID.',
        'link'     => 'add_third_party_circuit1.php',
        'btn'      => 'btn-success',
        'btn_text' => 'Add NNI Partner Details',
    ],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Operator/Table Management Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap 5 and Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        html, body { height: 100%; width: 100%; margin: 0; }
        body { background:#f9fafb; padding-top:60px; font-family:'Segoe UI',sans-serif; min-height:100vh; width:100vw; }
        .navbar-custom { background:#003049; box-shadow:0 2px 8px rgba(0,0,0,0.08); min-height:60px; }
        .navbar-brand { color:#f1faee !important; font-weight:600; letter-spacing:2px; font-size:1.25rem; }
        .brand-accent { width:11px;height:11px;border-radius:50%;margin-left:11px;background:#f77f00;box-shadow:0 0 8px 2px #f77f00;animation:accent-pulse 1.7s infinite;vertical-align:middle;}
        @keyframes accent-pulse { 0%{opacity:1;}50%{opacity:.7;}100%{opacity:1;} }
        .navbar-date { font-size:0.97rem; padding:0 4px; color:#f1faee !important; opacity:.8; }
        .profile-dropdown-content{display:none;position:absolute;right:0;top:120%;background:#fff;min-width:180px;box-shadow:0 4px 16px rgba(0,0,0,0.14);border-radius:8px;}
        .profile-dropdown-content a { color:#003049; padding:12px 18px; text-decoration:none; display:block; font-size:0.97rem; border-bottom:1px solid #f1f3f6;}
        .profile-dropdown-content a:last-child {border-bottom:none;}
        .profile-dropdown-content a:hover {background:#f1f3f6;}
        .profile-dropdown.show .profile-dropdown-content{display:block;}
        .dashboard-container { width: 100vw; max-width: 100vw; margin-left: calc(-1 * ((100vw - 100%) / 2)); }
        .dashboard-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 1.5rem; width: 100%; }
        .card{border:none;box-shadow:0 4px 10px rgba(0,0,0,0.08);border-radius:14px;transition:.2s;display:flex;flex-direction:column;justify-content:space-between;background:#fff;}
        .card:hover{transform:scale(1.02);}
        .card-title{color:#003049;font-size:1.15rem;font-weight:500;display:flex;align-items:center;gap:8px;}
        .card-text{font-size:0.98rem;}
        .icon-card{font-size:2rem;margin-right:8px;}
        .card .btn{padding:6px 16px;font-size:1rem;border-radius:18px;}
        .btn-success{background:#218838;border:none;}
        .btn-warning{background:#ffc107;border:none;color:#003049!important;}
        .btn-danger{background:#dc3545;border:none;}
        .btn-info{background:#17a2b8;border:none;}
        .btn-primary{background:#007bff;border:none;}
        .btn-secondary{background:#6c757d;border:none;}
        .btn-dark{background:#343a40;border:none;}
        /* Operator Table Links */
        .operator-table-links {
            font-size: 13px;
            margin-top: 2px;
            max-height: 120px;
            overflow-y: auto;
        }
        .operator-table-links a {
            display: block;
            color: #003049;
            background: #f7f7fa;
            border-radius: 4px;
            margin-bottom: 2px;
            padding: 2px 8px;
            text-decoration: none;
            transition: background 0.2s;
            border: 1px solid #eaeaea;
            font-size: 12px;
        }
        .operator-table-links a:hover {
            background: #f0e7cf;
            color: #d62828;
            border-color: #f77f00;
        }
        @media (max-width:1200px) { .dashboard-row { gap:1.1rem; } }
        @media (max-width:900px)  { .dashboard-row { gap:1rem; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
                                   .card{min-height:120px;font-size:0.95rem;}
                                   .card-title{font-size:1rem;}
                                   .operator-table-links{font-size:11px;}
        }
        @media (max-width:600px)  { .dashboard-row { gap:0.7rem; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); }
                                   .card{min-height:95px;font-size:0.93rem;}
                                   .card-title{font-size:0.95rem;}
                                   .operator-table-links{max-height:80px;}
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const profileBtn=document.querySelector(".profile-btn");
            const profileDropdown=document.querySelector(".profile-dropdown");
            profileBtn?.addEventListener("click",e=>{
                e.preventDefault();profileDropdown.classList.toggle("show");
            });
            document.addEventListener("click",e=>{
                if(!profileDropdown.contains(e.target)) profileDropdown.classList.remove("show");
            });
        });
    </script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <i class="bi bi-diagram-3 me-2"></i> Operator Portal <span class="brand-accent"></span>
        </a>
        <div class="ms-auto d-flex align-items-center">
            <span class="navbar-date text-light me-3"><?= date('d M Y, H:i') ?></span>
            <div class="profile-dropdown position-relative">
                <button class="profile-btn btn btn-link text-light">
                    <?= htmlspecialchars($username) ?> (<?= htmlspecialchars(ucfirst($role)) ?>)
                    <i class="bi bi-person-circle"></i>
                </button>
                <div class="profile-dropdown-content">
                    <a href="change_password.php"><i class="bi bi-shield-lock-fill"></i> Change Password</a>
                    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</nav>
<main class="container-fluid dashboard-container my-5">
    <div class="mb-3">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left-circle"></i> Back to Dashboard
        </a>
    </div>
    <h3 class="mb-4">Operator/Table Management Portal</h3>
    <div class="dashboard-row">
        <?php foreach ($portal_cards as $c): ?>
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-2"><i class="bi <?= $c['icon'] ?> icon-card"></i> <?= $c['title'] ?></h5>
                    <p class="card-text flex-grow-1"><?= $c['desc'] ?></p>
                    <a href="<?= $c['link'] ?>" class="btn <?= $c['btn'] ?> mt-auto align-self-start"><?= $c['btn_text'] ?></a>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- View Third Party Details for All Tables -->
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title mb-2"><i class="bi bi-eye icon-card"></i> View Third Party Details</h5>
                <p class="card-text flex-grow-1">View third party details for operator tables:</p>
                <div class="operator-table-links">
                    <?php if (!empty($operator_tables)): ?>
                        <?php foreach ($operator_tables as $ot): ?>
                            <a href="view_third_party_details.php?table=<?= urlencode($ot['table_name']) ?>" target="_blank">
                                <i class="bi bi-table"></i>
                                <?= htmlspecialchars($ot['operator_name']) ?>
                                <span style="color:#f77f00;">|</span>
                                <?= htmlspecialchars($ot['table_name']) ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-danger">No operator tables found.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- End View Card -->
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>