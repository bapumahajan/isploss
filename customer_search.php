<?php
// File: customer_search.php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['circuit_id']) && !empty(trim($_GET['circuit_id']))) {
    $circuit_id = urlencode(trim($_GET['circuit_id']));
    header("Location: view_customer_data.php?circuit_id=$circuit_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Search Circuit</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-5">
  <h2 class="text-center mb-4">Search Customer Circuit</h2>
  <form method="GET" class="d-flex justify-content-center" role="search">
    <div class="input-group w-50">
      <input type="text" name="circuit_id" class="form-control" placeholder="Enter Circuit ID..." required>
      <button class="btn btn-primary" type="submit">Search</button>
    </div>
  </form>
</div>
</body>
</html>
