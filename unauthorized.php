<?php
session_name('oss_portal');
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unauthorized Access</title>
    <meta http-equiv="refresh" content="5;url=dashboard.php"> <!-- Optional: auto-redirect -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex justify-content-center align-items-center vh-100">
    <div class="text-center p-5 shadow rounded bg-white">
        <h1 class="text-danger mb-3">403 - Unauthorized</h1>
        <p class="lead">You do not have permission to access this page.</p>
        <p>You will be redirected to the dashboard in a few seconds.</p>
        <a href="dashboard.php" class="btn btn-primary mt-3">Go to Dashboard Now</a>
    </div>
</body>
</html>
