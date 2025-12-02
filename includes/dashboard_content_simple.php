<?php
// filename: includes/dashboard_content_simple.php
?>
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-people-fill"></i> Customer Data</h5>
                <p class="card-text">View and manage customer records.</p>
                <a href="view_customer_data.php" class="btn btn-primary">View Data</a>
            </div>
        </div>
    </div>

    <?php if (hasRole($role, ROLE_ADMIN)): ?>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-person-plus-fill"></i> Add Customer</h5>
                    <p class="card-text">Register a new customer.</p>
                    <a href="add_customer.php" class="btn btn-primary">Add New</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-card-list"></i> View Details</h5>
                    <p class="card-text">See customer and circuit details.</p>
                    <a href="view_customer.php" class="btn btn-primary">View Details</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-diagram-3-fill"></i> Network Details</h5>
                    <p class="card-text">Manage network information.</p>
                    <a href="circuit_check.php" class="btn btn-primary">Manage Network</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-boxes"></i> Inventory</h5>
                    <p class="card-text">Manage POP and switch inventory.</p>
                    <a href="manage_inventory.php" class="btn btn-primary">Manage Inventory</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-person-check-fill"></i> Activate Users</h5>
                    <p class="card-text">Activate pending user accounts.</p>
                    <a href="activate_users.php" class="btn btn-primary">Activate Users</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-person-badge-fill"></i> Register User</h5>
                    <p class="card-text">Register new system users.</p>
                    <a href="signup.php" class="btn btn-primary">Register User</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-key-fill"></i> Reset Password</h5>
                    <p class="card-text">Reset user passwords.</p>
                    <a href="reset_password.php" class="btn btn-primary">Reset Password</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-arrow-repeat"></i> Import/Export</h5>
                    <p class="card-text">Import or export data.</p>
                    <a href="import_export.php" class="btn btn-primary">Import/Export</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-journal-text"></i> Activity Logs</h5>
                    <p class="card-text">View system activity.</p>
                    <a href="activity_log.php" class="btn btn-primary">View Logs</a>
                </div>
            </div>
        </div>
    <?php elseif (hasRole($role, ROLE_MANAGER)): ?>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-arrow-repeat"></i> Import/Export</h5>
                    <p class="card-text">Import or export data.</p>
                    <a href="import_export.php" class="btn btn-primary">Import/Export</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-journal-text"></i> Activity Logs</h5>
                    <p class="card-text">View system activity.</p>
                    <a href="activity_log.php" class="btn btn-primary">View Logs</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-eye-fill"></i> View Customers</h5>
                    <p class="card-text">View customer information.</p>
                    <a href="view_customer.php" class="btn btn-primary">View Customers</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (hasRole($role, ROLE_USER)): ?>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-shield-lock-fill"></i> Change Password</h5>
                    <p class="card-text">Update your password.</p>
                    <a href="change_password.php" class="btn btn-primary">Change Password</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>