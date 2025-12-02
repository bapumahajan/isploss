<?php
// filename: includes/sidebar.php
?>
<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse" id="sidebarMenu">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" aria-current="page" href="dashboard.php">
                    <i class="bi bi-house-door me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= activeNav('view_customer_data.php') ?>" href="view_customer_data.php">
                    <i class="bi bi-people-fill me-2"></i>
                    Customers
                </a>
            </li>
            <?php if (hasRole($role, ROLE_ADMIN)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('add_customer.php') ?>" href="add_customer.php">
                        <i class="bi bi-person-plus-fill me-2"></i>
                        Add Customer
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('view_customer.php') ?>" href="view_customer.php">
                        <i class="bi bi-card-list me-2"></i>
                        View Details
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('circuit_check.php') ?>" href="circuit_check.php">
                        <i class="bi bi-diagram-3-fill me-2"></i>
                        Network Details
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('manage_inventory.php') ?>" href="manage_inventory.php">
                        <i class="bi bi-boxes me-2"></i>
                        Inventory
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('activate_users.php') ?>" href="activate_users.php">
                        <i class="bi bi-person-check-fill me-2"></i>
                        Activate Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('signup.php') ?>" href="signup.php">
                        <i class="bi bi-person-badge-fill me-2"></i>
                        Register User
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('reset_password.php') ?>" href="reset_password.php">
                        <i class="bi bi-key-fill me-2"></i>
                        Reset Password
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('import_export.php') ?>" href="import_export.php">
                        <i class="bi bi-arrow-repeat me-2"></i>
                        Import/Export
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('activity_log.php') ?>" href="activity_log.php">
                        <i class="bi bi-journal-text me-2"></i>
                        Activity Logs
                    </a>
                </li>
            <?php elseif (hasRole($role, ROLE_MANAGER)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('import_export.php') ?>" href="import_export.php">
                        <i class="bi bi-arrow-repeat me-2"></i>
                        Import/Export
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('activity_log.php') ?>" href="activity_log.php">
                        <i class="bi bi-journal-text me-2"></i>
                        Activity Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('view_customer.php') ?>" href="view_customer.php">
                        <i class="bi bi-eye-fill me-2"></i>
                        View Customers
                    </a>
                </li>
            <?php endif; ?>
            <?php if (hasRole($role, ROLE_USER)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= activeNav('change_password.php') ?>" href="change_password.php">
                        <i class="bi bi-shield-lock-fill me-2"></i>
                        Change Password
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        </div>
</div>