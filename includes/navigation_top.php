<?php
// filename: includes/navigation_top.php
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">OSS Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNavbar" aria-controls="topNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="topNavbar">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('view_customer_data.php') ?>" href="view_customer_data.php">Customer Data</a>
                </li>
                <?php if (hasRole($role, ROLE_ADMIN)): ?>
                    <li class="nav-item"><a class="nav-link <?= activeNav('add_customer.php') ?>" href="add_customer.php">Add Customer</a></li>
                    <li class="nav-item"><a class="nav-link <?= activeNav('view_customer.php') ?>" href="view_customer.php">View Details</a></li>
                    <li class="nav-item"><a class="nav-link <?= activeNav('circuit_check.php') ?>" href="circuit_check.php">Network Details</a></li>
                    <li class="nav-item"><a class="nav-link <?= activeNav('manage_inventory.php') ?>" href="manage_inventory.php">Inventory</a></li>
                    <li class="nav-item"><a class="nav-link <?= activeNav('activate_users.php') ?>" href="activate_users.php">Activate Users</a></li>
                    <li class="nav-item"><a class="nav-link <?= activeNav('signup.php') ?>" href="signup.php">Register User</a></li>
                    <li class="nav-item"><a class="nav-link <?= activeNav('reset_password.php') ?>" href="reset_password.php">Reset Password</a></li>
                    <li class="nav-item"><a class="nav-link <?= activeNav('import_export.php') ?>" href="import_export.php">Import/Export</a></li>
                    <li class="nav-item"><a class="nav-link <?= activeNav('activity_log.php') ?>" href="activity_log.php">Activity Logs</a></li>
                <?php elseif (hasRole($role, ROLE_MANAGER)): ?>
                    <li class="nav-item"><a class="nav-link <?= activeNav('import_export.php') ?>" href="import_export.php">Import/Export</a></li>
                    <li class="nav-item"><a class="nav-link <?= activeNav('activity_log.php') ?>" href="activity_log.php">Activity Logs</a></li>
                    <li class="nav-item"><a class="nav-link <?= activeNav('view_customer.php') ?>" href="view_customer.php">View Customers</a></li>
                <?php endif; ?>
                <?php if (hasRole($role, ROLE_USER)): ?>
                    <li class="nav-item"><a class="nav-link <?= activeNav('change_password.php') ?>" href="change_password.php">Change Password</a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="navbar-text text-light">
                        <?= htmlspecialchars($username) ?> (<?= htmlspecialchars(ucfirst($role)) ?>)
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>