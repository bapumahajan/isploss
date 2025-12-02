<?php
// Include database connection file
session_name('oss_portal');
session_start();
include('includes/db.php');

// Fetch customer count by status
$status_query = "SELECT status, COUNT(*) AS customer_count FROM customer_circuits GROUP BY status";
$status_result = mysqli_query($conn, $status_query);

// Fetch customer count by product type
$product_type_query = "SELECT product_type, COUNT(*) AS customer_count FROM customer_circuits GROUP BY product_type";
$product_type_result = mysqli_query($conn, $product_type_query);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container my-5">
        <h1 class="text-center">Customer Report</h1>

        <h3>Status Summary</h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Customer Count</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($status_result && mysqli_num_rows($status_result) > 0) {
                    while ($row = mysqli_fetch_assoc($status_result)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['customer_count']) . "</td>";
                        echo "</tr>";
                    }
                }
                ?>
            </tbody>
        </table>

        <h3>Customer Type Summary</h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Product Type</th>
                    <th>Customer Count</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($product_type_result && mysqli_num_rows($product_type_result) > 0) {
                    while ($row = mysqli_fetch_assoc($product_type_result)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['product_type']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['customer_count']) . "</td>";
                        echo "</tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>
