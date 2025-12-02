<?php
// filename: includes/dashboard_content.php
?>
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
    <div class="col">
        <div class="card h-100 shadow-sm">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title"><i class="bi bi-people-fill"></i>Customer Data</h5>
                <p class="card-text flex-grow-1">View and manage customer records within the system.</p>
                <a href="view_customer_data.php" class="btn btn-primary mt-auto align-self-start">View Data</a>
            </div>
        </div>
    </div>

    <?php if (hasRole($role, ROLE_ADMIN)): ?>
        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-person-plus-fill"></i>Add Customer</h5>
                    <p class="card-text flex-grow-1">Register a new customer into the OSS Portal.</p>
                    <a href="add_customer.php" class="btn btn-success mt-auto align-self-start">Add New</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-card-list"></i>View Details</h5>
                    <p class="card-text flex-grow-1">See comprehensive details of customers and their circuits.</p>
                    <a href="view_customer.php" class="btn btn-info mt-auto align-self-start">View Details</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-diagram-3-fill"></i>Network Details</h5>
                    <p class="card-text flex-grow-1">Add and manage network information related to customer circuits.</p>
                    <a href="circuit_check.php" class="btn btn-warning mt-auto align-self-start">Manage Network</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-boxes"></i>Inventory Management</h5>
                    <p class="card-text flex-grow-1">Manage inventory of POPs and network switches.</p>
                    <a href="manage_inventory.php" class="btn btn-secondary mt-auto align-self-start">Manage Inventory</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-person-check-fill"></i>Activate Users</h5>
                    <p class="card-text flex-grow-1">Review and activate pending user accounts.</p>
                    <a href="activate_users.php" class="btn btn-primary mt-auto align-self-start">Activate Users</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-person-badge-fill"></i>Register New User</h5>
                    <p class="card-text flex-grow-1">Register new users within the OSS Portal.</p>
                    <a href="signup.php" class="btn btn-success mt-auto align-self-start">Register User</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-key-fill"></i>Reset Password</h5>
                    <p class="card-text flex-grow-1">Reset passwords for existing users.</p>
                    <a href="reset_password.php" class="btn btn-danger mt-auto align-self-start">Reset Password</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-arrow-repeat"></i>Import/Export</h5>
                    <p class="card-text flex-grow-1">Import or export data in CSV format.</p>
                    <a href="import_export.php" class="btn btn-info mt-auto align-self-start">Import/Export</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-journal-text"></i>Activity Logs</h5>
                    <p class="card-text flex-grow-1">View and monitor system activity logs.</p>
                    <a href="activity_log.php" class="btn btn-secondary mt-auto align-self-start">View Logs</a>
                </div>
            </div>
        </div>
    <?php elseif (hasRole($role, ROLE_MANAGER)): ?>
        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-arrow-repeat"></i>Import/Export</h5>
                    <p class="card-text flex-grow-1">Import or export data in CSV format.</p>
                    <a href="import_export.php" class="btn btn-info mt-auto align-self-start">Import/Export</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-journal-text"></i>Activity Logs</h5>
                    <p class="card-text flex-grow-1">View and monitor system activity logs.</p>
                    <a href="activity_log.php" class="btn btn-secondary mt-auto align-self-start">View Logs</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-eye-fill"></i>View Customers</h5>
                    <p class="card-text flex-grow-1">View detailed information about existing customers.</p>
                    <a href="view_customer.php" class="btn btn-info mt-auto align-self-start">View Customers</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (hasRole($role, ROLE_USER)): ?>
        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><i class="bi bi-shield-lock-fill"></i>Change Password</h5>
                    <p class="card-text flex-grow-1">Update your account password for security.</p>
                    <a href="change_password.php" class="btn btn-warning mt-auto align-self-start">Change Password</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>