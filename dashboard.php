<?php
// filename: dashboard.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_name('oss_portal');
session_start();

// === Security headers ===
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=()');
}
setSecurityHeaders();

// === Session timeout (15 min) ===
$timeout_duration = 900;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// === Authentication check ===
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// === Roles ===
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_USER', 'user');
define('ROLE_FINANCE', 'finance');

$username = $_SESSION['username'];
$role     = $_SESSION['role'] ?? ROLE_USER;

$roleHierarchy = [
    ROLE_USER    => 1,
    ROLE_FINANCE => 2,
    ROLE_MANAGER => 3,
    ROLE_ADMIN   => 4,
];

function hasRole($userRole, $requiredRole) {
    global $roleHierarchy;
    return isset($roleHierarchy[$userRole], $roleHierarchy[$requiredRole]) &&
           $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
}

// === Device count ===
require_once 'includes/config.php';
$deviceCount = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM device_inventory");
if ($res) {
    $row = $res->fetch_assoc();
    $deviceCount = $row['total'];
}

// === Dashboard card definitions (with button text improvements) ===
$cards = [
    // Common cards (all users)
    [
        'title'    => 'Customer Data',
        'icon'     => 'bi-people-fill',
        'desc'     => 'Browse and search customer records.',
        'link'     => 'view_customer_data.php',
        'btn'      => 'btn-outline-primary',
        'btn_text' => 'View',
        'role'     => ROLE_USER,
    ],
    [
        'title'    => 'Customer Dashboard',
        'icon'     => 'bi-person-video2',
        'desc'     => 'Customer dashboard.',
        'link'     => 'customer_status_dashboard.php',
        'btn'      => 'btn-outline-primary',
        'btn_text' => 'View',
        'role'     => ROLE_USER,
    ],
    [
        'title'    => 'Device Inventory',
        'icon'     => 'bi-hdd-network',
        'desc'     => 'Manage all network devices.<br><span class="fw-bold text-dark">Total Devices:</span> <span class="badge rounded-pill bg-primary">'. $deviceCount .'</span>',
        'link'     => 'device_inventory.php',
        'btn'      => 'btn-outline-dark',
        'btn_text' => 'View Devices',
        'role'     => ROLE_USER,
    ],

    // Admin-only cards
    [
        'title'    => 'Add Customer',
        'icon'     => 'bi-person-plus-fill',
        'desc'     => 'Register a new customer into the system.',
        'link'     => 'add_customer.php',
        'btn'      => 'btn-success',
        'btn_text' => 'Add',
        'role'     => ROLE_ADMIN,
    ],
    [
        'title'    => 'Add Network Details',
        'icon'     => 'bi-diagram-3-fill',
        'desc'     => 'Add or update circuit network info.',
        'link'     => 'circuit_check.php',
        'btn'      => 'btn-warning',
        'btn_text' => 'Manage',
        'role'     => ROLE_ADMIN,
    ],
    [
        'title'    => 'NNI Operator Portal',
        'icon'     => 'bi-diagram-3',
        'desc'     => 'Manage operator tables and details.',
        'link'     => 'operator_portal.php',
        'btn'      => 'btn-outline-success',
        'btn_text' => 'Open Portal',
        'role'     => ROLE_ADMIN,
    ],
    [
        'title'    => 'Third Party Network Details',
        'icon'     => 'bi-diagram-3-fill',
        'desc'     => 'Add Third party Network Details.',
        'link'     => 'add_third_party.php',
        'btn'      => 'btn-warning',
        'btn_text' => 'Third Party Details',
        'role'     => ROLE_ADMIN,
    ],
    [
        'title'    => 'Complaints Management',
        'icon'     => 'bi-exclamation-triangle-fill',
        'desc'     => 'Complaints Dashboard.',
        'link'     => 'complaints_dashboard.php',
        'btn'      => 'btn-warning',
        'btn_text' => 'Complaints',
        'role'     => ROLE_ADMIN,
    ],
    [
        'title'    => 'Manage Inventory',
        'icon'     => 'bi-boxes',
        'desc'     => 'Manage POP and Switch IP inventory.',
        'link'     => 'manage_inventory.php',
        'btn'      => 'btn-secondary',
        'btn_text' => 'Manage',
        'role'     => ROLE_ADMIN,
    ],
    [
        'title'    => 'View Details',
        'icon'     => 'bi-card-list',
        'desc'     => 'See circuit and customer overview.',
        'link'     => 'view_customer.php',
        'btn'      => 'btn-info',
        'btn_text' => 'View',
        'role'     => ROLE_ADMIN,
    ],
    [
        'title'    => 'Billing System',
        'icon'     => 'bi-cash-coin',
        'desc'     => 'Billing system.',
        'link'     => 'billing_dashboard.php',
        'btn'      => 'btn-info',
        'btn_text' => 'View',
        'role'     => ROLE_ADMIN,
    ],
    [
        'title'    => 'Import/Export',
        'icon'     => 'bi-arrow-repeat',
        'desc'     => 'Import or export customer data.',
        'link'     => 'import_export.php',
        'btn'      => 'btn-info',
        'btn_text' => 'Import/Export',
        'role'     => ROLE_MANAGER, // Also visible for manager, you can change to ROLE_USER or use an array for multi-role
    ],
    [
        'title'    => 'Activate Users',
        'icon'     => 'bi-person-check-fill',
        'desc'     => 'Approve new user registrations.',
        'link'     => 'activate_users.php',
        'btn'      => 'btn-dark',
        'btn_text' => 'Activate',
        'role'     => ROLE_ADMIN,
    ],
    [
        'title'    => 'Register User',
        'icon'     => 'bi-person-badge-fill',
        'desc'     => 'Add new admin or manager users.',
        'link'     => 'signup.php',
        'btn'      => 'btn-primary',
        'btn_text' => 'Register',
        'role'     => ROLE_ADMIN,
    ],
    [
        'title'    => 'Reset Password',
        'icon'     => 'bi-key-fill',
        'desc'     => 'Reset user passwords securely.',
        'link'     => 'reset_password.php',
        'btn'      => 'btn-danger',
        'btn_text' => 'Reset',
        'role'     => ROLE_ADMIN,
    ],
    [
        'title'    => 'Activity Logs',
        'icon'     => 'bi-journal-text',
        'desc'     => 'Review system activity logs.',
        'link'     => 'activity_log.php',
        'btn'      => 'btn-secondary',
        'btn_text' => 'Logs',
        'role'     => ROLE_MANAGER, // Also visible for manager, you can change to ROLE_USER or use an array for multi-role
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>ISPL CUSTOMER Portal - Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
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
        .btn-outline-success{border-color:#218838;color:#218838;}
        .btn-outline-primary{border-color:#007bff;color:#007bff;}
        .btn-outline-dark{border-color:#343a40;color:#343a40;}
        .btn-outline-warning{border-color:#ffc107;color:#ffc107;}
        .btn-outline-danger{border-color:#dc3545;color:#dc3545;}
        .btn-outline-info{border-color:#17a2b8;color:#17a2b8;}
        .btn-outline-secondary{border-color:#6c757d;color:#6c757d;}
        .btn-outline-dark{border-color:#343a40;color:#343a40;}
        @media (max-width:1200px) { .dashboard-row { gap:1.1rem; } }
        @media (max-width:900px)  { .dashboard-row { gap:1rem; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
                                   .card{min-height:120px;font-size:0.95rem;}
                                   .card-title{font-size:1rem;}
        }
        @media (max-width:600px)  { .dashboard-row { gap:0.7rem; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); }
                                   .card{min-height:95px;font-size:0.93rem;}
                                   .card-title{font-size:0.95rem;}
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
        <i class="bi bi-house-door-fill me-2"></i> ISPL DASHBOARD <span class="brand-accent"></span>
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
  <h3 class="mb-4">Welcome, <?= htmlspecialchars($username) ?> 👋</h3>
  <div class="dashboard-row">
    <?php foreach ($cards as $c): ?>
        <?php if (hasRole($role, $c['role'])): ?>
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title"><i class="bi <?= $c['icon'] ?> icon-card"></i><?= $c['title'] ?></h5>
            <p class="card-text flex-grow-1"><?= $c['desc'] ?></p>
            <a href="<?= $c['link'] ?>" class="btn <?= $c['btn'] ?> mt-auto align-self-start"><?= $c['btn_text'] ?></a>
          </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>